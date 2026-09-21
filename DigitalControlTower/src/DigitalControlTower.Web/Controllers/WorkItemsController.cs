using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class WorkItemsController(ApplicationDbContext db, ICurrentUser currentUser) : Controller
{
    public async Task<IActionResult> Details(string id, CancellationToken ct)
    {
        var item = await db.WorkItems
            .Include(i => i.Project)!.ThenInclude(p => p!.Pillar)
            .Include(i => i.Actions.OrderBy(a => a.Serial)).ThenInclude(a => a.Owners).ThenInclude(o => o.User)
            .Include(i => i.Comments)
            .AsNoTracking()
            .FirstOrDefaultAsync(i => i.Id == id, ct);

        return item is null ? NotFound() : View(item);
    }

    public async Task<IActionResult> Create(string projectId, CancellationToken ct)
    {
        var project = await db.Projects.AsNoTracking().FirstOrDefaultAsync(p => p.Id == projectId, ct);
        if (project is null) return NotFound();

        ViewBag.ProjectName = project.Name;
        return View(new WorkItem { ProjectId = projectId });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Create(
        [Bind(nameof(WorkItem.ProjectId), nameof(WorkItem.Name), nameof(WorkItem.Notes))] WorkItem item,
        CancellationToken ct)
    {
        var project = await db.Projects.AsNoTracking().FirstOrDefaultAsync(p => p.Id == item.ProjectId, ct);
        if (project is null) return NotFound();

        if (!ModelState.IsValid)
        {
            ViewBag.ProjectName = project.Name;
            return View(item);
        }

        item.Id = $"item-{Guid.NewGuid():N}"[..16];
        item.SortOrder = await db.WorkItems.CountAsync(i => i.ProjectId == item.ProjectId, ct);
        db.WorkItems.Add(item);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Work item '{item.Name}' added.";
        return RedirectToAction("Details", "Projects", new { id = item.ProjectId });
    }

    public async Task<IActionResult> Edit(string id, CancellationToken ct)
    {
        var item = await db.WorkItems.Include(i => i.Project).FirstOrDefaultAsync(i => i.Id == id, ct);
        if (item is null) return NotFound();

        ViewBag.ProjectName = item.Project?.Name;
        return View(item);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(
        string id,
        [Bind(nameof(WorkItem.Name), nameof(WorkItem.Notes), nameof(WorkItem.SortOrder))] WorkItem input,
        CancellationToken ct)
    {
        var item = await db.WorkItems.Include(i => i.Project).FirstOrDefaultAsync(i => i.Id == id, ct);
        if (item is null) return NotFound();

        // A work item cannot be moved between projects here, so ProjectId is not posted.
        ModelState.Remove(nameof(WorkItem.ProjectId));

        if (!ModelState.IsValid)
        {
            ViewBag.ProjectName = item.Project?.Name;
            input.Id = id;
            input.ProjectId = item.ProjectId;
            return View(input);
        }

        item.Name = input.Name;
        item.Notes = input.Notes;
        item.SortOrder = input.SortOrder;
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Work item '{item.Name}' updated.";
        return RedirectToAction("Details", "Projects", new { id = item.ProjectId });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> AddComment(string id, string text, CancellationToken ct)
    {
        if (!await db.WorkItems.AnyAsync(i => i.Id == id, ct)) return NotFound();

        if (string.IsNullOrWhiteSpace(text))
        {
            TempData["Error"] = "A comment cannot be empty.";
            return RedirectToAction(nameof(Details), new { id });
        }

        db.Comments.Add(new Comment
        {
            WorkItemId = id,
            Author = currentUser.Handle,
            Text = text.Trim(),
            At = DateTime.UtcNow
        });
        await db.SaveChangesAsync(ct);

        return RedirectToAction(nameof(Details), new { id });
    }

    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var item = await db.WorkItems
            .Include(i => i.Project)
            .Include(i => i.Actions)
            .AsNoTracking()
            .FirstOrDefaultAsync(i => i.Id == id, ct);

        return item is null ? NotFound() : View(item);
    }

    [HttpPost, ActionName("Delete")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteConfirmed(string id, CancellationToken ct)
    {
        var item = await db.WorkItems
            .Include(i => i.Comments)
            .Include(i => i.Actions)
            .FirstOrDefaultAsync(i => i.Id == id, ct);
        if (item is null) return NotFound();

        var projectId = item.ProjectId;
        var actionIds = item.Actions.Select(a => a.Id).ToList();
        await db.Actions.Where(a => a.RelatedActionId != null && actionIds.Contains(a.RelatedActionId))
            .ExecuteUpdateAsync(set => set.SetProperty(a => a.RelatedActionId, (string?)null), ct);

        db.Comments.RemoveRange(item.Comments);
        db.Actions.RemoveRange(item.Actions);
        db.WorkItems.Remove(item);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Work item '{item.Name}' deleted.";
        return RedirectToAction("Details", "Projects", new { id = projectId });
    }
}
