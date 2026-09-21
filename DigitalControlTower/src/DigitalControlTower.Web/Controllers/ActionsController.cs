using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;
using DigitalControlTower.Web.ViewModels;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Rendering;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class ActionsController(ApplicationDbContext db, SerialService serials, ICurrentUser currentUser) : Controller
{
    private const string EditableFields =
        nameof(ActionItem.WorkItemId) + "," + nameof(ActionItem.Name) + "," +
        nameof(ActionItem.Status) + "," + nameof(ActionItem.Priority) + "," + nameof(ActionItem.Quarter) + "," +
        nameof(ActionItem.Recurrence) + "," + nameof(ActionItem.Source) + "," + nameof(ActionItem.Target) + "," +
        nameof(ActionItem.SuccessCriteria) + "," + nameof(ActionItem.NextStep) + "," + nameof(ActionItem.Notes) + "," +
        nameof(ActionItem.CheckResult) + "," + nameof(ActionItem.DueDate) + "," + nameof(ActionItem.CompletionDate);

    public async Task<IActionResult> Index(ActionsIndexViewModel filter, CancellationToken ct)
    {
        var query = db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.Pillar)
            .Include(a => a.Project)
            .Include(a => a.WorkItem)
            .AsNoTracking()
            .AsQueryable();

        if (!string.IsNullOrWhiteSpace(filter.Search))
        {
            var term = filter.Search.Trim();
            query = query.Where(a =>
                a.Name.Contains(term) ||
                a.Serial.Contains(term) ||
                (a.Notes != null && a.Notes.Contains(term)) ||
                a.Owners.Any(o => o.User!.Handle.Contains(term) || o.User!.Name.Contains(term)) ||
                (a.WorkItem != null && a.WorkItem.Name.Contains(term)) ||
                (a.Project != null && a.Project.Name.Contains(term)));
        }

        if (!string.IsNullOrWhiteSpace(filter.PillarId))
            query = query.Where(a => a.PillarId == filter.PillarId);

        if (!string.IsNullOrWhiteSpace(filter.ProjectId))
            query = query.Where(a => a.ProjectId == filter.ProjectId);

        if (filter.Origin is { } origin) query = query.Where(a => a.Origin == origin);
        if (filter.WithoutDueDate) query = query.Where(a => a.DueDate == null);
        if (filter.WithoutOwner) query = query.Where(a => !a.Owners.Any());
        if (filter.OverdueOnly)
        {
            var today = DateOnly.FromDateTime(DateTime.UtcNow);
            query = query.Where(a => a.DueDate != null && a.DueDate < today
                                     && a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled);
        }

        if (filter.Status is { } status) query = query.Where(a => a.Status == status);
        if (filter.Priority is { } priority) query = query.Where(a => a.Priority == priority);
        if (filter.Quarter is { } quarter) query = query.Where(a => a.Quarter == quarter);

        if (!string.IsNullOrWhiteSpace(filter.Owner))
        {
            // Owners are filtered by handle so a filtered URL stays readable: ?Owner=brika.wm
            var handle = UserHandle.Normalise(filter.Owner);
            query = query.Where(a => a.Owners.Any(o => o.User!.Handle == handle));
        }

        filter.TotalCount = await query.CountAsync(ct);
        filter.Page = Math.Max(1, filter.Page);
        filter.PageSize = filter.PageSize is < 10 or > 200 ? 25 : filter.PageSize;

        filter.Actions = await query
            .OrderBy(a => a.Serial)
            .Skip((filter.Page - 1) * filter.PageSize)
            .Take(filter.PageSize)
            .ToListAsync(ct);

        await PopulateFilterListsAsync(filter, ct);
        return View(filter);
    }

    public async Task<IActionResult> Details(string id, CancellationToken ct)
    {
        var action = await db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.Pillar)
            .Include(a => a.Project)
            .Include(a => a.WorkItem)!.ThenInclude(i => i!.Project)!.ThenInclude(p => p!.Pillar)
            .Include(a => a.Meeting)
            .Include(a => a.RelatedAction)
            .Include(a => a.Weeks)
            .Include(a => a.Tags)
            .Include(a => a.Comments)
            .Include(a => a.History)
            .AsNoTracking()
            .FirstOrDefaultAsync(a => a.Id == id, ct);

        if (action is null) return NotFound();

        ViewBag.Weeks = await db.WeekDates.AsNoTracking().OrderBy(w => w.WeekIndex).ToListAsync(ct);
        return View(action);
    }

    public async Task<IActionResult> Create(string? workItemId, CancellationToken ct)
    {
        await PopulateEditListsAsync(workItemId, [], ct);
        return View(new ActionItem { WorkItemId = workItemId ?? string.Empty, Serial = "(assigned on save)" });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Create(
        [Bind(EditableFields)] ActionItem input,
        string[]? ownerIds,
        string? tags,
        CancellationToken ct)
    {
        // Serial is issued by the server, so it is never posted and must not be validated.
        ModelState.Remove(nameof(ActionItem.Serial));

        await ValidateContextAsync(input, ct);
        var owners = await ResolveOwnersAsync(ownerIds, ct);

        if (!ModelState.IsValid)
        {
            await PopulateEditListsAsync(input.WorkItemId, owners.Select(u => u.Id), ct);
            ViewBag.Tags = tags;
            input.Serial = "(assigned on save)";
            return View(input);
        }

        input.Id = $"action-{Guid.NewGuid():N}"[..18];
        input.Serial = await serials.NextActionSerialAsync(ct);
        if (string.IsNullOrWhiteSpace(input.WorkItemId)) input.WorkItemId = null;
        if (input.Status == ActionStatus.Complete && input.CompletionDate is null)
            input.CompletionDate = DateOnly.FromDateTime(DateTime.UtcNow);

        db.Actions.Add(input);
        foreach (var owner in owners)
            input.Owners.Add(new ActionOwner { ActionItemId = input.Id, UserId = owner.Id });
        ApplyTags(input, tags);

        await db.SaveChangesAsync(ct);
        TempData["Success"] = $"Action {input.Serial} created.";
        return RedirectToAction(nameof(Details), new { id = input.Id });
    }

    public async Task<IActionResult> Edit(string id, CancellationToken ct)
    {
        var action = await db.Actions
            .Include(a => a.Tags)
            .Include(a => a.Owners)
            .FirstOrDefaultAsync(a => a.Id == id, ct);
        if (action is null) return NotFound();

        await PopulateEditListsAsync(action.WorkItemId, action.Owners.Select(o => o.UserId), ct);
        ViewBag.Tags = string.Join(", ", action.Tags.Select(t => t.Tag));
        return View(action);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(
        string id,
        [Bind(EditableFields)] ActionItem input,
        string[]? ownerIds,
        string? tags,
        CancellationToken ct)
    {
        var action = await db.Actions
            .Include(a => a.Tags)
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .FirstOrDefaultAsync(a => a.Id == id, ct);
        if (action is null) return NotFound();

        // Serial stays as issued, so the form does not post it.
        ModelState.Remove(nameof(ActionItem.Serial));

        await ValidateContextAsync(input, ct);
        var owners = await ResolveOwnersAsync(ownerIds, ct);

        if (!ModelState.IsValid)
        {
            await PopulateEditListsAsync(input.WorkItemId, owners.Select(u => u.Id), ct);
            ViewBag.Tags = tags;
            input.Id = id;
            input.Serial = action.Serial;
            return View(input);
        }

        action.WorkItemId = string.IsNullOrWhiteSpace(input.WorkItemId) ? null : input.WorkItemId;
        action.Name = input.Name;
        action.Status = input.Status;
        action.Priority = input.Priority;
        action.Quarter = input.Quarter;
        action.Recurrence = input.Recurrence;
        action.Source = input.Source;
        action.Target = input.Target;
        action.SuccessCriteria = input.SuccessCriteria;
        action.NextStep = input.NextStep;
        action.Notes = input.Notes;
        action.CheckResult = input.CheckResult;
        action.DueDate = input.DueDate;
        action.CompletionDate = input.CompletionDate;

        // Completing an action stamps the completion date when the user left it empty.
        if (action.Status == ActionStatus.Complete && action.CompletionDate is null)
            action.CompletionDate = DateOnly.FromDateTime(DateTime.UtcNow);

        ApplyOwners(action, owners);
        ApplyTags(action, tags);

        await db.SaveChangesAsync(ct);
        TempData["Success"] = $"Action {action.Serial} updated.";
        return RedirectToAction(nameof(Details), new { id = action.Id });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> UpdateStatus(string id, ActionStatus status, string? returnUrl, CancellationToken ct)
    {
        var action = await db.Actions.FirstOrDefaultAsync(a => a.Id == id, ct);
        if (action is null) return NotFound();

        action.Status = status;
        if (status == ActionStatus.Complete && action.CompletionDate is null)
            action.CompletionDate = DateOnly.FromDateTime(DateTime.UtcNow);

        await db.SaveChangesAsync(ct);
        TempData["Success"] = $"{action.Serial} moved to {status}.";
        return SafeRedirect(returnUrl, nameof(Details), new { id });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> AddComment(string id, string text, CancellationToken ct)
    {
        if (string.IsNullOrWhiteSpace(text))
        {
            TempData["Error"] = "A comment cannot be empty.";
            return RedirectToAction(nameof(Details), new { id });
        }

        if (!await db.Actions.AnyAsync(a => a.Id == id, ct)) return NotFound();

        db.Comments.Add(new Comment
        {
            ActionItemId = id,
            Author = currentUser.Handle,
            Text = text.Trim(),
            At = DateTime.UtcNow
        });

        await db.SaveChangesAsync(ct);
        return RedirectToAction(nameof(Details), new { id });
    }

    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var action = await db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.Pillar)
            .Include(a => a.Project)
            .Include(a => a.WorkItem)
            .AsNoTracking()
            .FirstOrDefaultAsync(a => a.Id == id, ct);

        return action is null ? NotFound() : View(action);
    }

    [HttpPost, ActionName("Delete")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteConfirmed(string id, CancellationToken ct)
    {
        var action = await db.Actions.FirstOrDefaultAsync(a => a.Id == id, ct);
        if (action is null) return NotFound();

        // Another action may follow on from this one. That link cannot cascade (it points
        // back at the same table), so it is cleared before the row goes.
        await db.Actions.Where(a => a.RelatedActionId == id)
            .ExecuteUpdateAsync(set => set.SetProperty(a => a.RelatedActionId, (string?)null), ct);

        db.Actions.Remove(action);
        await db.SaveChangesAsync(ct);
        TempData["Success"] = $"Action {action.Serial} deleted.";
        return RedirectToAction(nameof(Index));
    }

    /// <summary>
    /// A plan action must sit under a work item; an action raised in a meeting may instead
    /// be attached to a project or a pillar, or to nothing at all while it is being placed.
    /// </summary>
    private async Task ValidateContextAsync(ActionItem input, CancellationToken ct)
    {
        if (string.IsNullOrWhiteSpace(input.WorkItemId))
        {
            ModelState.Remove(nameof(ActionItem.WorkItemId));
            if (input.Origin == ActionOrigin.Plan)
                ModelState.AddModelError(nameof(ActionItem.WorkItemId), "Choose the work item this action belongs to.");
            return;
        }

        if (!await db.WorkItems.AnyAsync(i => i.Id == input.WorkItemId, ct))
            ModelState.AddModelError(nameof(ActionItem.WorkItemId), "Choose the work item this action belongs to.");
    }

    /// <summary>Looks up the posted owner ids, ignoring blanks and duplicates.</summary>
    private async Task<List<AppUser>> ResolveOwnersAsync(string[]? ownerIds, CancellationToken ct)
    {
        var ids = (ownerIds ?? [])
            .Where(id => !string.IsNullOrWhiteSpace(id))
            .Distinct()
            .ToList();

        if (ids.Count == 0) return [];

        var users = await db.Users.Where(u => ids.Contains(u.Id)).ToListAsync(ct);
        if (users.Count != ids.Count)
            ModelState.AddModelError("ownerIds", "One of the selected owners no longer exists.");

        return users;
    }

    /// <summary>Replaces the owner set and records the change on the action's history.</summary>
    private void ApplyOwners(ActionItem action, List<AppUser> owners)
    {
        var before = action.OwnerHandles;
        var wanted = owners.Select(u => u.Id).ToHashSet();

        foreach (var existing in action.Owners.ToList())
        {
            if (wanted.Contains(existing.UserId)) continue;
            action.Owners.Remove(existing);
            db.ActionOwners.Remove(existing);
        }

        foreach (var user in owners.Where(u => action.Owners.All(o => o.UserId != u.Id)))
            action.Owners.Add(new ActionOwner { ActionItemId = action.Id, UserId = user.Id, User = user });

        var after = action.OwnerHandles;
        if (before == after) return;

        // Owners live in their own table, so the SaveChanges audit hook cannot see the
        // change; record it here where both the old and the new handles are known.
        db.ActionHistories.Add(new ActionHistory
        {
            ActionItemId = action.Id,
            Field = "owners",
            FromValue = before.Length == 0 ? null : before,
            ToValue = after.Length == 0 ? null : after,
            By = currentUser.Handle,
            At = DateTime.UtcNow
        });
    }

    /// <summary>Replaces the tag set from a comma separated input.</summary>
    private void ApplyTags(ActionItem action, string? tags)
    {
        var wanted = (tags ?? string.Empty)
            .Split(',', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();

        foreach (var existing in action.Tags.ToList())
        {
            if (!wanted.Contains(existing.Tag, StringComparer.OrdinalIgnoreCase))
            {
                action.Tags.Remove(existing);
                db.ActionTags.Remove(existing);
            }
        }

        foreach (var tag in wanted.Where(t => !action.Tags.Any(x => string.Equals(x.Tag, t, StringComparison.OrdinalIgnoreCase))))
            action.Tags.Add(new ActionTag { ActionItemId = action.Id, Tag = tag });
    }

    private async Task PopulateFilterListsAsync(ActionsIndexViewModel filter, CancellationToken ct)
    {
        var pillars = await db.Pillars.AsNoTracking().OrderBy(p => p.SortOrder).ToListAsync(ct);
        filter.Pillars = new SelectList(pillars, nameof(Pillar.Id), nameof(Pillar.Name), filter.PillarId);

        var projects = await db.Projects.AsNoTracking()
            .Where(p => filter.PillarId == null || filter.PillarId == "" || p.PillarId == filter.PillarId)
            .OrderBy(p => p.Name)
            .ToListAsync(ct);
        filter.Projects = new SelectList(projects, nameof(Project.Id), nameof(Project.Name), filter.ProjectId);

        var owners = await db.ActionOwners.AsNoTracking()
            .Select(o => o.User!.Handle)
            .Distinct()
            .OrderBy(handle => handle)
            .ToListAsync(ct);
        filter.Owners = new SelectList(owners, filter.Owner);
    }

    private async Task PopulateEditListsAsync(string? workItemId, IEnumerable<string> selectedOwnerIds, CancellationToken ct)
    {
        var items = await db.WorkItems.AsNoTracking()
            .Include(i => i.Project)!.ThenInclude(p => p!.Pillar)
            .OrderBy(i => i.Project!.Pillar!.SortOrder).ThenBy(i => i.Project!.Name).ThenBy(i => i.SortOrder)
            .Select(i => new
            {
                i.Id,
                Label = i.Project!.Pillar!.Name + " / " + i.Project.Name + " / " + i.Name
            })
            .ToListAsync(ct);

        ViewBag.WorkItems = new SelectList(items, "Id", "Label", workItemId);

        var users = await db.Users.AsNoTracking()
            .OrderBy(u => u.Handle)
            .Select(u => new { u.Id, Label = u.Handle + " — " + u.Name })
            .ToListAsync(ct);

        ViewBag.Owners = new MultiSelectList(users, "Id", "Label", selectedOwnerIds);
    }

    private IActionResult SafeRedirect(string? returnUrl, string action, object routeValues) =>
        !string.IsNullOrWhiteSpace(returnUrl) && Url.IsLocalUrl(returnUrl)
            ? Redirect(returnUrl)
            : RedirectToAction(action, routeValues);
}
