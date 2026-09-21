using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class PillarsController(ApplicationDbContext db) : Controller
{
    public async Task<IActionResult> Index(CancellationToken ct)
    {
        var pillars = await db.Pillars
            .Include(p => p.Projects)
            .AsNoTracking()
            .OrderBy(p => p.SortOrder)
            .ToListAsync(ct);

        ViewBag.ActionCounts = await db.Actions
            .GroupBy(a => a.WorkItem!.Project!.PillarId)
            .Select(g => new { PillarId = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.PillarId, x => x.Count, ct);

        return View(pillars);
    }

    public IActionResult Create() => View(new Pillar());

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Create([Bind(nameof(Pillar.Name), nameof(Pillar.SortOrder))] Pillar pillar, CancellationToken ct)
    {
        if (!ModelState.IsValid) return View(pillar);

        pillar.Id = $"pillar-{Guid.NewGuid():N}"[..15];
        db.Pillars.Add(pillar);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Pillar '{pillar.Name}' created.";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Edit(string id, CancellationToken ct)
    {
        var pillar = await db.Pillars.FirstOrDefaultAsync(p => p.Id == id, ct);
        return pillar is null ? NotFound() : View(pillar);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(string id, [Bind(nameof(Pillar.Name), nameof(Pillar.SortOrder))] Pillar input, CancellationToken ct)
    {
        var pillar = await db.Pillars.FirstOrDefaultAsync(p => p.Id == id, ct);
        if (pillar is null) return NotFound();
        if (!ModelState.IsValid) { input.Id = id; return View(input); }

        pillar.Name = input.Name;
        pillar.SortOrder = input.SortOrder;
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Pillar '{pillar.Name}' updated.";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var pillar = await db.Pillars.Include(p => p.Projects).AsNoTracking().FirstOrDefaultAsync(p => p.Id == id, ct);
        return pillar is null ? NotFound() : View(pillar);
    }

    [HttpPost, ActionName("Delete")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteConfirmed(string id, CancellationToken ct)
    {
        var pillar = await db.Pillars
            .Include(p => p.Projects).ThenInclude(pr => pr.Items).ThenInclude(i => i.Comments)
            .FirstOrDefaultAsync(p => p.Id == id, ct);
        if (pillar is null) return NotFound();

        db.Comments.RemoveRange(pillar.Projects.SelectMany(p => p.Items).SelectMany(i => i.Comments));

        // Same rule as deleting a project: plan actions go, meeting actions are unlinked.
        var itemIds = pillar.Projects.SelectMany(p => p.Items).Select(i => i.Id).ToList();
        var doomed = await db.Actions.Where(a => a.WorkItemId != null && itemIds.Contains(a.WorkItemId)).ToListAsync(ct);
        var doomedIds = doomed.Select(a => a.Id).ToList();
        await db.Actions.Where(a => a.RelatedActionId != null && doomedIds.Contains(a.RelatedActionId))
            .ExecuteUpdateAsync(set => set.SetProperty(a => a.RelatedActionId, (string?)null), ct);
        db.Actions.RemoveRange(doomed);
        foreach (var orphan in await db.Actions.Where(a => a.PillarId == pillar.Id && a.WorkItemId == null).ToListAsync(ct))
        {
            orphan.ProjectId = null;
            orphan.PillarId = null;
        }

        db.Pillars.Remove(pillar);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Pillar '{pillar.Name}' deleted.";
        return RedirectToAction(nameof(Index));
    }
}
