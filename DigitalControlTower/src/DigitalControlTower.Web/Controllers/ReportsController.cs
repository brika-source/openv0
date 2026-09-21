using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Extensions;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.ViewModels;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Rendering;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class ReportsController(ApplicationDbContext db) : Controller
{
    public IActionResult Index() => RedirectToAction(nameof(Value));

    /// <summary>
    /// The value the department added: money, hours and productivity, split by hard and
    /// soft saving, by pillar, and by the person who owns the project.
    /// </summary>
    public async Task<IActionResult> Value(string? pillarId, SavingsType? type, bool withValueOnly, CancellationToken ct)
    {
        var query = db.Projects
            .Include(p => p.Pillar)
            .Include(p => p.DigitalOwner)
            .Include(p => p.Pm)
            .AsNoTracking()
            .AsQueryable();

        if (!string.IsNullOrWhiteSpace(pillarId)) query = query.Where(p => p.PillarId == pillarId);
        if (type is { } wanted) query = query.Where(p => p.SavingsType == wanted);
        if (withValueOnly)
        {
            query = query.Where(p => p.SavingsType != SavingsType.NotSet
                                     || (p.CostAvoidance ?? 0) != 0
                                     || (p.LabourHoursSaving ?? 0) != 0
                                     || (p.ProductivityImprovement ?? 0) != 0);
        }

        var projects = await query.OrderBy(p => p.Pillar!.SortOrder).ThenBy(p => p.Name).ToListAsync(ct);

        var model = new ValueReportViewModel
        {
            PillarId = pillarId,
            Type = type,
            WithValueOnly = withValueOnly,
            Projects = projects,
            TotalFinancial = projects.Sum(p => p.CostAvoidance ?? 0),
            TotalHours = projects.Sum(p => p.LabourHoursSaving ?? 0),
            ProjectsWithValue = projects.Count(p => p.HasValue),
            ProjectsWithoutValue = projects.Count(p => !p.HasValue),
            HardSaving = projects.Where(p => p.SavingsType == SavingsType.Hard).Sum(p => p.CostAvoidance ?? 0),
            SoftSaving = projects.Where(p => p.SavingsType == SavingsType.Soft).Sum(p => p.CostAvoidance ?? 0),
            CostAvoidance = projects.Where(p => p.SavingsType == SavingsType.CostAvoidance).Sum(p => p.CostAvoidance ?? 0),
            OtherValue = projects.Where(p => p.SavingsType == SavingsType.Other).Sum(p => p.CostAvoidance ?? 0)
        };

        var improving = projects.Where(p => (p.ProductivityImprovement ?? 0) != 0).ToList();
        model.AverageProductivity = improving.Count == 0
            ? 0
            : Math.Round(improving.Average(p => p.ProductivityImprovement ?? 0), 1);

        model.ByType = projects
            .GroupBy(p => p.SavingsType)
            .Select(g => new ValueReportViewModel.ValueSlice
            {
                Label = g.Key.DisplayName(),
                Financial = g.Sum(p => p.CostAvoidance ?? 0),
                Hours = g.Sum(p => p.LabourHoursSaving ?? 0),
                Projects = g.Count()
            })
            .OrderByDescending(s => s.Financial)
            .ToList();

        model.ByPillar = projects
            .GroupBy(p => p.Pillar?.Name ?? "—")
            .Select(g => new ValueReportViewModel.ValueSlice
            {
                Label = g.Key,
                Financial = g.Sum(p => p.CostAvoidance ?? 0),
                Hours = g.Sum(p => p.LabourHoursSaving ?? 0),
                Projects = g.Count()
            })
            .OrderByDescending(s => s.Financial)
            .ToList();

        // "Per owner" reads the digital owner first, falling back to the project manager,
        // so a project counts once against whoever is accountable for delivering it.
        model.ByOwner = projects
            .GroupBy(p => p.DigitalOwner?.Handle ?? p.Pm?.Handle ?? "unassigned")
            .Select(g => new ValueReportViewModel.ValueSlice
            {
                Label = g.Key,
                Financial = g.Sum(p => p.CostAvoidance ?? 0),
                Hours = g.Sum(p => p.LabourHoursSaving ?? 0),
                Projects = g.Count()
            })
            .OrderByDescending(s => s.Financial)
            .ToList();

        ViewBag.Pillars = new SelectList(
            await db.Pillars.AsNoTracking().OrderBy(p => p.SortOrder).ToListAsync(ct),
            nameof(Pillar.Id), nameof(Pillar.Name), pillarId);

        return View(model);
    }

    /// <summary>What is missing: actions with no date or owner, projects with no value, empty items.</summary>
    public async Task<IActionResult> Gaps(GapFilter filter, CancellationToken ct)
    {
        var today = DateOnly.FromDateTime(DateTime.UtcNow);

        var openActions = db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.Pillar)
            .Include(a => a.Project)
            .Include(a => a.WorkItem)!.ThenInclude(i => i!.Project)
            .AsNoTracking()
            .Where(a => a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled);

        var model = new GapReportViewModel { Filter = filter };

        if (model.Shows(GapFilter.ActionsWithoutDueDate))
            model.ActionsWithoutDueDate = await openActions.Where(a => a.DueDate == null)
                .OrderBy(a => a.Serial).ToListAsync(ct);

        if (model.Shows(GapFilter.ActionsWithoutOwner))
            model.ActionsWithoutOwner = await openActions.Where(a => !a.Owners.Any())
                .OrderBy(a => a.Serial).ToListAsync(ct);

        if (model.Shows(GapFilter.OverdueActions))
            model.OverdueActions = await openActions.Where(a => a.DueDate != null && a.DueDate < today)
                .OrderBy(a => a.DueDate).ToListAsync(ct);

        var projects = db.Projects
            .Include(p => p.Pillar)
            .Include(p => p.DigitalOwner)
            .Include(p => p.Pm)
            .AsNoTracking();

        if (model.Shows(GapFilter.ProjectsWithoutValue))
            model.ProjectsWithoutValue = await projects
                .Where(p => p.SavingsType == SavingsType.NotSet
                            && (p.CostAvoidance ?? 0) == 0
                            && (p.LabourHoursSaving ?? 0) == 0
                            && (p.ProductivityImprovement ?? 0) == 0)
                .OrderBy(p => p.Pillar!.SortOrder).ThenBy(p => p.Name)
                .ToListAsync(ct);

        if (model.Shows(GapFilter.ProjectsWithoutOwner))
            model.ProjectsWithoutOwner = await projects
                .Where(p => p.DigitalOwnerId == null && p.PmId == null)
                .OrderBy(p => p.Name).ToListAsync(ct);

        if (model.Shows(GapFilter.ProjectsWithoutItems))
            model.ProjectsWithoutItems = await projects
                .Where(p => !p.Items.Any())
                .OrderBy(p => p.Name).ToListAsync(ct);

        if (model.Shows(GapFilter.ItemsWithoutActions))
            model.ItemsWithoutActions = await db.WorkItems
                .Include(i => i.Project)!.ThenInclude(p => p!.Pillar)
                .AsNoTracking()
                .Where(i => !i.Actions.Any())
                .OrderBy(i => i.Project!.Name).ThenBy(i => i.Name)
                .ToListAsync(ct);

        return View(model);
    }
}
