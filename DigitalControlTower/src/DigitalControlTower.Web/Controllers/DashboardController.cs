using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Services;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.ViewModels;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class DashboardController(ApplicationDbContext db, ReminderService reminders) : Controller
{
    private static readonly ActionStatus[] ClosedStatuses = [ActionStatus.Complete, ActionStatus.Cancelled];

    /// <summary>Row label for actions that have no owner at all.</summary>
    private const string UnassignedHandle = "no owner yet";

    public async Task<IActionResult> Index(CancellationToken ct)
    {
        var today = DateOnly.FromDateTime(DateTime.UtcNow);

        var statusCounts = await db.Actions
            .GroupBy(a => a.Status)
            .Select(g => new { g.Key, Count = g.Count() })
            .ToListAsync(ct);

        var pillars = await db.Pillars
            .OrderBy(p => p.SortOrder)
            .Select(p => new DashboardViewModel.PillarSummary
            {
                Id = p.Id,
                Name = p.Name,
                Projects = p.Projects.Count,
                Actions = p.Projects.SelectMany(pr => pr.Items).SelectMany(i => i.Actions).Count(),
                Complete = p.Projects.SelectMany(pr => pr.Items).SelectMany(i => i.Actions)
                    .Count(a => a.Status == ActionStatus.Complete),
                AtRisk = p.Projects.SelectMany(pr => pr.Items).SelectMany(i => i.Actions)
                    .Count(a => a.Status == ActionStatus.AtRisk || a.Status == ActionStatus.Delayed)
            })
            .ToListAsync(ct);

        // Grouped aggregates are projected to an anonymous type first: EF cannot
        // translate a projection straight into a record's positional constructor.
        var ownerLoads = await db.ActionOwners
            .GroupBy(o => o.User!.Handle)
            .Select(g => new
            {
                Owner = g.Key,
                Open = g.Count(o => o.ActionItem!.Status != ActionStatus.Complete
                                    && o.ActionItem!.Status != ActionStatus.Cancelled),
                Complete = g.Count(o => o.ActionItem!.Status == ActionStatus.Complete)
            })
            .OrderByDescending(o => o.Open)
            .Take(8)
            .ToListAsync(ct);

        var topOwners = ownerLoads
            .Select(o => new DashboardViewModel.OwnerLoad(o.Owner, o.Open, o.Complete))
            .ToList();

        // Workload per person, in the same four buckets the board uses. Joint
        // ownership counts for each owner, which the card says on its face.
        var ownerRows = await db.ActionOwners
            .Select(o => new
            {
                Handle = o.User!.Handle,
                o.ActionItem!.Status,
                o.ActionItem!.DueDate
            })
            .ToListAsync(ct);

        // Actions nobody owns get their own row, and everyone on file appears
        // even with nothing on their plate — both are what makes the chart
        // usable for balancing work rather than just admiring it.
        var unowned = await db.Actions
            .Where(a => !a.Owners.Any())
            .Select(a => new { Handle = UnassignedHandle, a.Status, a.DueDate })
            .ToListAsync(ct);

        var idleHandles = await db.Users
            .Where(u => !u.ActionOwnerships.Any())
            .Select(u => u.Handle)
            .ToListAsync(ct);

        var workload = ownerRows.Concat(unowned)
            .GroupBy(o => o.Handle)
            .Select(g => new DashboardViewModel.WorkloadRow(
                Handle: g.Key,
                Complete: g.Count(o => o.Status == ActionStatus.Complete),
                Overdue: g.Count(o => o.Status != ActionStatus.Complete && o.Status != ActionStatus.Cancelled
                                      && o.DueDate != null && o.DueDate < today),
                Attention: g.Count(o => o.Status != ActionStatus.Complete && o.Status != ActionStatus.Cancelled
                                        && !(o.DueDate != null && o.DueDate < today)
                                        && (o.Status == ActionStatus.AtRisk || o.Status == ActionStatus.Delayed
                                            || o.Status == ActionStatus.NeedsDefinition)),
                OnTrack: g.Count(o => o.Status != ActionStatus.Complete && o.Status != ActionStatus.Cancelled
                                      && !(o.DueDate != null && o.DueDate < today)
                                      && o.Status != ActionStatus.AtRisk && o.Status != ActionStatus.Delayed
                                      && o.Status != ActionStatus.NeedsDefinition)))
            .Concat(idleHandles.Select(h => new DashboardViewModel.WorkloadRow(h, 0, 0, 0, 0)))
            .OrderByDescending(r => r.Total)
            .ThenBy(r => r.Handle == UnassignedHandle ? 1 : 0)
            .ThenBy(r => r.Handle)
            .ToList();

        // Project start and due dates are mostly empty in the plan, so a span
        // falls back to the dates carried by the project's own actions.
        // Only the ends of each range are needed, so they are taken as scalar
        // min/max aggregates: EF cannot translate a list built inside a query.
        var projectDates = await db.Projects
            .Select(p => new
            {
                p.Id,
                p.Name,
                PillarName = p.Pillar!.Name,
                p.Pillar!.SortOrder,
                p.StartDate,
                p.DueDate,
                p.BaselineDate,
                p.ImplementationDate,
                p.ActualCompletionDate,
                ActionDueMin = p.Items.SelectMany(i => i.Actions).Min(a => a.DueDate),
                ActionDueMax = p.Items.SelectMany(i => i.Actions).Max(a => a.DueDate),
                ActionDoneMin = p.Items.SelectMany(i => i.Actions).Min(a => a.CompletionDate),
                ActionDoneMax = p.Items.SelectMany(i => i.Actions).Max(a => a.CompletionDate)
            })
            .ToListAsync(ct);

        var timeline = new List<DashboardViewModel.TimelineRow>();
        var undated = 0;
        foreach (var p in projectDates)
        {
            var own = new[] { p.StartDate, p.DueDate, p.BaselineDate, p.ImplementationDate, p.ActualCompletionDate }
                .Where(d => d.HasValue).Select(d => d!.Value).ToList();
            var all = own
                .Concat(new[] { p.ActionDueMin, p.ActionDueMax, p.ActionDoneMin, p.ActionDoneMax }
                    .Where(d => d.HasValue).Select(d => d!.Value))
                .ToList();
            if (all.Count == 0) { undated++; continue; }
            timeline.Add(new DashboardViewModel.TimelineRow(
                p.Id, p.Name, p.PillarName, p.SortOrder,
                all.Min(), all.Max(), own.Count > 0));
        }
        timeline = timeline.OrderBy(r => r.Start).ThenBy(r => r.End).ToList();

        var attention = await db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.WorkItem)!.ThenInclude(i => i!.Project)!.ThenInclude(p => p!.Pillar)
            .Where(a => a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled
                        && (a.Status == ActionStatus.AtRisk
                            || a.Status == ActionStatus.Delayed
                            || (a.DueDate != null && a.DueDate < today)))
            .OrderBy(a => a.DueDate ?? DateOnly.MaxValue)
            .ThenByDescending(a => a.Priority)
            .Take(15)
            .ToListAsync(ct);

        var recent = await db.ActionHistories
            .Include(h => h.ActionItem)
            .OrderByDescending(h => h.At)
            .Take(12)
            .ToListAsync(ct);

        // The value columns are read and totalled here rather than summed in SQL: there
        // are only a few dozen projects, and it keeps the query portable.
        var value = await db.Projects
            .Select(p => new
            {
                Type = p.SavingsType,
                Money = p.CostAvoidance ?? 0,
                Hours = p.LabourHoursSaving ?? 0,
                Productivity = p.ProductivityImprovement ?? 0
            })
            .ToListAsync(ct);

        var model = new DashboardViewModel
        {
            TotalActions = statusCounts.Sum(s => s.Count),
            CompleteActions = statusCounts.Where(s => s.Key == ActionStatus.Complete).Sum(s => s.Count),
            OpenActions = statusCounts.Where(s => !ClosedStatuses.Contains(s.Key)).Sum(s => s.Count),
            OverdueActions = await db.Actions.CountAsync(
                a => a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled
                     && a.DueDate != null && a.DueDate < today, ct),
            ProjectCount = await db.Projects.CountAsync(ct),
            ActiveProjects = await db.Projects.CountAsync(p => p.Status == ProjectStatus.Active, ct),
            StatusBreakdown = statusCounts
                .Select(s => new DashboardViewModel.StatusCount(s.Key, s.Count))
                .OrderBy(s => s.Status)
                .ToList(),
            Pillars = pillars,
            TopOwners = topOwners,
            Attention = attention,
            RecentActivity = recent,
            CurrentWeek = await db.WeekDates
                .Where(w => w.StartDate <= today)
                .OrderByDescending(w => w.StartDate)
                .FirstOrDefaultAsync(ct),
            TodaysMeeting = await db.Meetings
                .Include(m => m.Participants).ThenInclude(p => p.User)
                .AsNoTracking()
                .FirstOrDefaultAsync(m => m.Date == today, ct),
            LastMeeting = await db.Meetings
                .AsNoTracking()
                .Where(m => m.Date < today)
                .OrderByDescending(m => m.Date)
                .FirstOrDefaultAsync(ct),
            MeetingActionsOpen = await db.Actions.CountAsync(
                a => a.Origin == ActionOrigin.Meeting
                     && a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled, ct),
            TotalFinancialValue = value.Sum(v => v.Money),
            TotalHoursSaved = value.Sum(v => v.Hours),
            ProjectsWithoutValue = value.Count(v => v.Type == SavingsType.NotSet
                                                    && v.Money == 0 && v.Hours == 0 && v.Productivity == 0),
            ActionsWithoutDueDate = await db.Actions.CountAsync(
                a => a.DueDate == null
                     && a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled, ct),
            Workload = workload,
            Timeline = timeline,
            TimelineStart = timeline.Count > 0 ? timeline.Min(r => r.Start) : today,
            TimelineEnd = timeline.Count > 0 ? timeline.Max(r => r.End) : today,
            UndatedProjects = undated
        };

        var batches = await reminders.PreviewAsync(today, ct);
        model.RemindersDueNextRun = batches.Sum(b => b.DueSoon.Count);
        model.ReminderTargetDate = today.AddDays(2);

        return View(model);
    }
}
