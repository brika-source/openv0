using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.ViewModels;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Rendering;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class ProjectsController(ApplicationDbContext db) : Controller
{
    private const string EditableFields =
        nameof(Project.PillarId) + "," + nameof(Project.Name) + "," + nameof(Project.Scope) + "," +
        nameof(Project.DeptOwner) + "," + nameof(Project.DigitalOwnerId) + "," + nameof(Project.PmId) + "," +
        nameof(Project.Status) + "," + nameof(Project.StartDate) + "," + nameof(Project.DueDate) + "," +
        nameof(Project.BaselineDate) + "," + nameof(Project.ImplementationDate) + "," +
        nameof(Project.ActualCompletionDate) + "," + nameof(Project.CostAvoidance) + "," +
        nameof(Project.LabourHoursSaving) + "," + nameof(Project.ProductivityImprovement) + "," +
        nameof(Project.SavingsType) + "," + nameof(Project.ValueNotes);

    public async Task<IActionResult> Index(string? pillarId, ProjectStatus? status, string? search, CancellationToken ct)
    {
        var query = db.Projects
            .Include(p => p.Pillar)
            .Include(p => p.DigitalOwner)
            .Include(p => p.Pm)
            .AsNoTracking()
            .AsQueryable();

        if (!string.IsNullOrWhiteSpace(pillarId)) query = query.Where(p => p.PillarId == pillarId);
        if (status is { } s) query = query.Where(p => p.Status == s);
        if (!string.IsNullOrWhiteSpace(search))
        {
            var term = search.Trim();
            query = query.Where(p =>
                p.Name.Contains(term) ||
                (p.Pm != null && (p.Pm.Handle.Contains(term) || p.Pm.Name.Contains(term))) ||
                (p.DigitalOwner != null && p.DigitalOwner.Handle.Contains(term)));
        }

        ViewBag.Pillars = new SelectList(
            await db.Pillars.AsNoTracking().OrderBy(p => p.SortOrder).ToListAsync(ct),
            nameof(Pillar.Id), nameof(Pillar.Name), pillarId);
        ViewBag.PillarId = pillarId;
        ViewBag.Status = status;
        ViewBag.Search = search;

        var projects = await query
            .OrderBy(p => p.Pillar!.SortOrder).ThenBy(p => p.Name)
            .Select(p => new
            {
                Project = p,
                Actions = p.Items.SelectMany(i => i.Actions).Count(),
                Complete = p.Items.SelectMany(i => i.Actions).Count(a => a.Status == ActionStatus.Complete)
            })
            .ToListAsync(ct);

        ViewBag.ActionCounts = projects.ToDictionary(x => x.Project.Id, x => (x.Actions, x.Complete));
        return View(projects.Select(x => x.Project).ToList());
    }

    public async Task<IActionResult> Details(string id, CancellationToken ct)
    {
        var project = await db.Projects
            .Include(p => p.Pillar)
            .Include(p => p.DigitalOwner)
            .Include(p => p.Pm)
            .Include(p => p.Items.OrderBy(i => i.SortOrder))
                .ThenInclude(i => i.Actions.OrderBy(a => a.Serial))
                    .ThenInclude(a => a.Owners).ThenInclude(o => o.User)
            .Include(p => p.Items)
                .ThenInclude(i => i.Comments)
            .AsNoTracking()
            .FirstOrDefaultAsync(p => p.Id == id, ct);

        if (project is null) return NotFound();

        var actions = project.Items.SelectMany(i => i.Actions).ToList();
        return View(new ProjectDetailsViewModel
        {
            Project = project,
            TotalActions = actions.Count,
            CompleteActions = actions.Count(a => a.Status == ActionStatus.Complete),
            OpenActions = actions.Count(a => a.IsOpen)
        });
    }

    public async Task<IActionResult> Create(string? pillarId, CancellationToken ct)
    {
        await PopulateListsAsync(pillarId, null, null, ct);
        return View(new Project { PillarId = pillarId ?? string.Empty });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Create([Bind(EditableFields)] Project project, CancellationToken ct)
    {
        if (!await db.Pillars.AnyAsync(p => p.Id == project.PillarId, ct))
            ModelState.AddModelError(nameof(Project.PillarId), "Choose the pillar this project belongs to.");

        if (!ModelState.IsValid)
        {
            await PopulateListsAsync(project.PillarId, project.DigitalOwnerId, project.PmId, ct);
            return View(project);
        }

        project.DigitalOwnerId = string.IsNullOrWhiteSpace(project.DigitalOwnerId) ? null : project.DigitalOwnerId;
        project.PmId = string.IsNullOrWhiteSpace(project.PmId) ? null : project.PmId;
        project.Id = $"project-{Guid.NewGuid():N}"[..20];
        db.Projects.Add(project);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Project '{project.Name}' created.";
        return RedirectToAction(nameof(Details), new { id = project.Id });
    }

    public async Task<IActionResult> Edit(string id, CancellationToken ct)
    {
        var project = await db.Projects.FirstOrDefaultAsync(p => p.Id == id, ct);
        if (project is null) return NotFound();

        await PopulateListsAsync(project.PillarId, project.DigitalOwnerId, project.PmId, ct);
        return View(project);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(string id, [Bind(EditableFields)] Project input, CancellationToken ct)
    {
        var project = await db.Projects.FirstOrDefaultAsync(p => p.Id == id, ct);
        if (project is null) return NotFound();

        if (!await db.Pillars.AnyAsync(p => p.Id == input.PillarId, ct))
            ModelState.AddModelError(nameof(Project.PillarId), "Choose the pillar this project belongs to.");

        if (!ModelState.IsValid)
        {
            await PopulateListsAsync(input.PillarId, input.DigitalOwnerId, input.PmId, ct);
            input.Id = id;
            return View(input);
        }

        var pillarChanged = project.PillarId != input.PillarId;
        project.PillarId = input.PillarId;
        project.Name = input.Name;
        project.Scope = input.Scope;
        project.DeptOwner = input.DeptOwner;
        project.DigitalOwnerId = string.IsNullOrWhiteSpace(input.DigitalOwnerId) ? null : input.DigitalOwnerId;
        project.PmId = string.IsNullOrWhiteSpace(input.PmId) ? null : input.PmId;
        project.Status = input.Status;
        project.StartDate = input.StartDate;
        project.DueDate = input.DueDate;
        project.BaselineDate = input.BaselineDate;
        project.ImplementationDate = input.ImplementationDate;
        project.ActualCompletionDate = input.ActualCompletionDate;
        project.CostAvoidance = input.CostAvoidance;
        project.LabourHoursSaving = input.LabourHoursSaving;
        project.ProductivityImprovement = input.ProductivityImprovement;
        project.SavingsType = input.SavingsType;
        project.ValueNotes = input.ValueNotes;

        // Actions carry the pillar so reports can group on them directly; moving a project
        // to another pillar has to carry its actions across too.
        if (pillarChanged)
        {
            await db.Actions
                .Where(a => a.ProjectId == project.Id)
                .ExecuteUpdateAsync(set => set.SetProperty(a => a.PillarId, project.PillarId), ct);
        }

        await db.SaveChangesAsync(ct);
        TempData["Success"] = $"Project '{project.Name}' updated.";
        return RedirectToAction(nameof(Details), new { id = project.Id });
    }

    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var project = await db.Projects
            .Include(p => p.Pillar)
            .Include(p => p.Items).ThenInclude(i => i.Actions)
            .AsNoTracking()
            .FirstOrDefaultAsync(p => p.Id == id, ct);

        return project is null ? NotFound() : View(project);
    }

    [HttpPost, ActionName("Delete")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteConfirmed(string id, CancellationToken ct)
    {
        var project = await db.Projects
            .Include(p => p.Items).ThenInclude(i => i.Comments)
            .FirstOrDefaultAsync(p => p.Id == id, ct);
        if (project is null) return NotFound();

        // Work item comments are restricted rather than cascaded, so clear them first.
        db.Comments.RemoveRange(project.Items.SelectMany(i => i.Comments));

        // Plan actions go with the work items they hang off; actions raised in a meeting
        // and merely filed against this project are kept, with the link cleared.
        var itemIds = project.Items.Select(i => i.Id).ToList();
        var doomed = await db.Actions.Where(a => a.WorkItemId != null && itemIds.Contains(a.WorkItemId)).ToListAsync(ct);
        var doomedIds = doomed.Select(a => a.Id).ToList();
        await db.Actions.Where(a => a.RelatedActionId != null && doomedIds.Contains(a.RelatedActionId))
            .ExecuteUpdateAsync(set => set.SetProperty(a => a.RelatedActionId, (string?)null), ct);
        db.Actions.RemoveRange(doomed);
        foreach (var orphan in await db.Actions.Where(a => a.ProjectId == project.Id && a.WorkItemId == null).ToListAsync(ct))
        {
            orphan.ProjectId = null;
            orphan.PillarId = null;
        }

        db.Projects.Remove(project);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Project '{project.Name}' deleted.";
        return RedirectToAction(nameof(Index));
    }

    private async Task PopulateListsAsync(string? pillarId, string? ownerId, string? pmId, CancellationToken ct)
    {
        ViewBag.Pillars = new SelectList(
            await db.Pillars.AsNoTracking().OrderBy(p => p.SortOrder).ToListAsync(ct),
            nameof(Pillar.Id), nameof(Pillar.Name), pillarId);

        // People are picked by handle, with the full name alongside for recognition.
        var users = await db.Users.AsNoTracking()
            .OrderBy(u => u.Handle)
            .Select(u => new { u.Id, Label = u.Handle + " \u2014 " + u.Name })
            .ToListAsync(ct);

        ViewBag.Owners = new SelectList(users, "Id", "Label", ownerId);
        ViewBag.Pms = new SelectList(users, "Id", "Label", pmId);
    }
}
