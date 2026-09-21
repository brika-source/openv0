using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.ViewModels;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Rendering;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

/// <summary>The 14-week tracking grid: one row per action, one column per week.</summary>
public class WeekGridController(ApplicationDbContext db) : Controller
{
    public async Task<IActionResult> Index(string? pillarId, string? projectId, bool openOnly, CancellationToken ct)
    {
        var query = db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.Weeks)
            .Include(a => a.WorkItem)!.ThenInclude(i => i!.Project)
            .AsNoTracking()
            .AsQueryable();

        if (!string.IsNullOrWhiteSpace(pillarId)) query = query.Where(a => a.WorkItem!.Project!.PillarId == pillarId);
        if (!string.IsNullOrWhiteSpace(projectId)) query = query.Where(a => a.WorkItem!.ProjectId == projectId);
        if (openOnly) query = query.Where(a => a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled);

        // The grid renders 14 cells per row, so an unfiltered tower would produce a very
        // heavy page. Cap the rows and tell the user to narrow down instead.
        const int maxRows = 100;
        var total = await query.CountAsync(ct);
        var actions = await query
            .OrderBy(a => a.WorkItem!.Project!.Name).ThenBy(a => a.WorkItem!.SortOrder).ThenBy(a => a.Serial)
            .Take(maxRows)
            .ToListAsync(ct);

        var model = new WeekGridViewModel
        {
            Weeks = await db.WeekDates.AsNoTracking().OrderBy(w => w.WeekIndex).ToListAsync(ct),
            PillarId = pillarId,
            ProjectId = projectId,
            OpenOnly = openOnly,
            TotalCount = total,
            MaxRows = maxRows,
            Rows = actions.Select(a => new WeekGridViewModel.GridRow
            {
                Action = a,
                ProjectName = a.WorkItem?.Project?.Name ?? "",
                WorkItemName = a.WorkItem?.Name ?? "",
                Marks = a.Weeks.ToDictionary(w => w.WeekIndex, w => w.Mark)
            }).ToList(),
            Pillars = new SelectList(
                await db.Pillars.AsNoTracking().OrderBy(p => p.SortOrder).ToListAsync(ct),
                nameof(Pillar.Id), nameof(Pillar.Name), pillarId),
            Projects = new SelectList(
                await db.Projects.AsNoTracking()
                    .Where(p => pillarId == null || pillarId == "" || p.PillarId == pillarId)
                    .OrderBy(p => p.Name).ToListAsync(ct),
                nameof(Project.Id), nameof(Project.Name), projectId)
        };

        return View(model);
    }

    /// <summary>
    /// Cycles one cell: empty -> T -> R -> A -> empty. The grid posts a single
    /// "actionId:weekIndex" value so the page needs only one form and one token.
    /// </summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Toggle(string cell, string? returnUrl, CancellationToken ct)
    {
        var parts = (cell ?? string.Empty).Split(':', 2);
        if (parts.Length != 2 || !int.TryParse(parts[1], out var weekIndex)) return BadRequest();

        var actionId = parts[0];
        if (!await db.Actions.AnyAsync(a => a.Id == actionId, ct)) return NotFound();

        var existing = await db.ActionWeeks.FirstOrDefaultAsync(w => w.ActionItemId == actionId && w.WeekIndex == weekIndex, ct);
        if (existing is null)
        {
            db.ActionWeeks.Add(new ActionWeek { ActionItemId = actionId, WeekIndex = weekIndex, Mark = WeekMark.T });
        }
        else if (existing.Mark == WeekMark.T)
        {
            existing.Mark = WeekMark.R;
        }
        else if (existing.Mark == WeekMark.R)
        {
            existing.Mark = WeekMark.A;
        }
        else
        {
            db.ActionWeeks.Remove(existing);
        }

        await db.SaveChangesAsync(ct);

        return !string.IsNullOrWhiteSpace(returnUrl) && Url.IsLocalUrl(returnUrl)
            ? Redirect(returnUrl)
            : RedirectToAction(nameof(Index));
    }
}
