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
        db.Pillars.Remove(pillar);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Pillar '{pillar.Name}' deleted.";
        return RedirectToAction(nameof(Index));
    }
}
