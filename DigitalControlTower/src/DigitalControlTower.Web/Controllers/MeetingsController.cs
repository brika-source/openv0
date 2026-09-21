using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;
using DigitalControlTower.Web.ViewModels;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Rendering;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

/// <summary>
/// The meetings action plan. A meeting opens straight on today's date, its actions are
/// ordinary actions, and each one can be tied to a pillar, a project, a 90-day action or
/// another action — so whatever is agreed here also shows up in the master plan.
/// </summary>
public class MeetingsController(ApplicationDbContext db, SerialService serials, ICurrentUser currentUser) : Controller
{
    public async Task<IActionResult> Index(CancellationToken ct)
    {
        var rows = await db.Meetings
            .Include(m => m.Participants).ThenInclude(p => p.User)
            .AsNoTracking()
            .OrderByDescending(m => m.Date).ThenByDescending(m => m.CreatedAt)
            .Select(m => new MeetingListRow
            {
                Meeting = m,
                ActionCount = m.Actions.Count,
                OpenCount = m.Actions.Count(a => a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled)
            })
            .ToListAsync(ct);

        return View(rows);
    }

    /// <summary>Opens today's meeting, creating it the first time it is opened.</summary>
    public async Task<IActionResult> Today(CancellationToken ct)
    {
        var today = DateOnly.FromDateTime(DateTime.Today);
        var meeting = await GetOrCreateAsync(today, ct);
        return RedirectToAction(nameof(Open), new { date = meeting.Date.ToString("yyyy-MM-dd") });
    }

    /// <summary>Opens the meeting for a date, creating it if there is not one yet.</summary>
    public async Task<IActionResult> Open(string? date, CancellationToken ct)
    {
        var day = DateOnly.TryParse(date, out var parsed) ? parsed : DateOnly.FromDateTime(DateTime.Today);
        var meeting = await GetOrCreateAsync(day, ct);
        return RedirectToAction(nameof(Details), new { id = meeting.Id });
    }

    public async Task<IActionResult> Details(string id, CancellationToken ct)
    {
        var meeting = await db.Meetings
            .Include(m => m.Participants).ThenInclude(p => p.User)
            .Include(m => m.Chair)
            .FirstOrDefaultAsync(m => m.Id == id, ct);

        if (meeting is null) return NotFound();

        var model = new MeetingViewModel
        {
            Meeting = meeting,
            Actions = await db.Actions
                .Include(a => a.Owners).ThenInclude(o => o.User)
                .Include(a => a.Pillar)
                .Include(a => a.Project)
                .Include(a => a.WorkItem)!.ThenInclude(i => i!.Project)
                .Include(a => a.RelatedAction)
                .AsNoTracking()
                .Where(a => a.MeetingId == meeting.Id)
                .OrderBy(a => a.Serial)
                .ToListAsync(ct)
        };

        await PopulateListsAsync(model, ct);
        return View(model);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Save(
        string id,
        string title,
        DateOnly date,
        string? minutes,
        string? chairUserId,
        string[]? participantIds,
        CancellationToken ct)
    {
        var meeting = await db.Meetings.Include(m => m.Participants).FirstOrDefaultAsync(m => m.Id == id, ct);
        if (meeting is null) return NotFound();

        meeting.Title = string.IsNullOrWhiteSpace(title) ? "Digital team meeting" : title.Trim();
        meeting.Date = date;
        meeting.Minutes = string.IsNullOrWhiteSpace(minutes) ? null : minutes.Trim();
        meeting.ChairUserId = string.IsNullOrWhiteSpace(chairUserId) ? null : chairUserId;

        var wanted = (participantIds ?? []).Where(p => !string.IsNullOrWhiteSpace(p)).Distinct().ToHashSet();
        foreach (var existing in meeting.Participants.ToList())
        {
            if (wanted.Contains(existing.UserId)) continue;
            meeting.Participants.Remove(existing);
            db.MeetingParticipants.Remove(existing);
        }

        var known = await db.Users.Where(u => wanted.Contains(u.Id)).Select(u => u.Id).ToListAsync(ct);
        foreach (var userId in known.Where(u => meeting.Participants.All(p => p.UserId != u)))
            meeting.Participants.Add(new MeetingParticipant { MeetingId = meeting.Id, UserId = userId });

        await db.SaveChangesAsync(ct);
        TempData["Success"] = "Meeting saved.";
        return RedirectToAction(nameof(Details), new { id = meeting.Id });
    }

    /// <summary>
    /// Adds an action agreed in the meeting. It starts Not Started, as the team asked,
    /// and is linked to whatever context was chosen so it rolls up into the master plan.
    /// </summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> AddAction(
        string id,
        string name,
        string[]? ownerIds,
        DateOnly? dueDate,
        Priority priority,
        string? linkType,
        string? pillarId,
        string? projectId,
        string? workItemId,
        string? relatedActionId,
        string? notes,
        CancellationToken ct)
    {
        var meeting = await db.Meetings.FirstOrDefaultAsync(m => m.Id == id, ct);
        if (meeting is null) return NotFound();

        if (string.IsNullOrWhiteSpace(name))
        {
            TempData["Error"] = "An action needs a description.";
            return RedirectToAction(nameof(Details), new { id });
        }

        var action = new ActionItem
        {
            Id = $"action-{Guid.NewGuid():N}"[..18],
            Serial = await serials.NextActionSerialAsync(ct),
            Name = name.Trim(),
            Origin = ActionOrigin.Meeting,
            Source = "Meeting",
            MeetingId = meeting.Id,
            Status = ActionStatus.NotStarted,   // meeting actions always start here
            Priority = priority,
            DueDate = dueDate,
            Notes = string.IsNullOrWhiteSpace(notes) ? null : notes.Trim(),
            Quarter = QuarterOf(dueDate ?? meeting.Date)
        };

        // The link decides how far up the hierarchy the action is attached. A work item
        // gives it a full plan context; a project or pillar attaches it at that level.
        switch (linkType)
        {
            case "workItem" when !string.IsNullOrWhiteSpace(workItemId):
                action.WorkItemId = workItemId;
                break;
            case "project" when !string.IsNullOrWhiteSpace(projectId):
                action.ProjectId = projectId;
                action.PillarId = await db.Projects.Where(p => p.Id == projectId)
                    .Select(p => p.PillarId).FirstOrDefaultAsync(ct);
                break;
            case "pillar" when !string.IsNullOrWhiteSpace(pillarId):
                action.PillarId = pillarId;
                break;
            case "action" when !string.IsNullOrWhiteSpace(relatedActionId):
                var parent = await db.Actions.AsNoTracking().FirstOrDefaultAsync(a => a.Id == relatedActionId, ct);
                if (parent is not null)
                {
                    action.RelatedActionId = parent.Id;
                    action.WorkItemId = parent.WorkItemId;
                    action.ProjectId = parent.ProjectId;
                    action.PillarId = parent.PillarId;
                }
                break;
        }

        db.Actions.Add(action);

        var wantedOwners = (ownerIds ?? Array.Empty<string>())
            .Where(o => !string.IsNullOrWhiteSpace(o))
            .Distinct()
            .ToList();
        var owners = await db.Users
            .Where(u => wantedOwners.Contains(u.Id))
            .Select(u => u.Id)
            .ToListAsync(ct);
        foreach (var ownerId in owners)
            action.Owners.Add(new ActionOwner { ActionItemId = action.Id, UserId = ownerId });

        await db.SaveChangesAsync(ct);
        TempData["Success"] = $"{action.Serial} added to the meeting and to the action plan.";
        return RedirectToAction(nameof(Details), new { id });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var meeting = await db.Meetings.FirstOrDefaultAsync(m => m.Id == id, ct);
        if (meeting is null) return NotFound();

        // Actions raised in the meeting outlive it: the link is cleared, the work stays.
        await db.Actions.Where(a => a.MeetingId == id)
            .ExecuteUpdateAsync(set => set.SetProperty(a => a.MeetingId, (string?)null), ct);

        db.Meetings.Remove(meeting);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"Meeting {meeting.Serial} deleted; its actions were kept in the plan.";
        return RedirectToAction(nameof(Index));
    }

    private async Task<Meeting> GetOrCreateAsync(DateOnly date, CancellationToken ct)
    {
        var existing = await db.Meetings
            .Where(m => m.Date == date)
            .OrderBy(m => m.CreatedAt)
            .FirstOrDefaultAsync(ct);

        if (existing is not null) return existing;

        var meeting = new Meeting
        {
            Serial = await serials.NextMeetingSerialAsync(ct),
            Date = date,
            Title = "Digital team meeting"
        };

        // Whoever opens the meeting chairs it by default, and is the first participant.
        var me = await db.Users.FirstOrDefaultAsync(u => u.Handle == currentUser.Handle, ct);
        if (me is not null)
        {
            meeting.ChairUserId = me.Id;
            meeting.Participants.Add(new MeetingParticipant { MeetingId = meeting.Id, UserId = me.Id });
        }

        db.Meetings.Add(meeting);
        await db.SaveChangesAsync(ct);
        return meeting;
    }

    private async Task PopulateListsAsync(MeetingViewModel model, CancellationToken ct)
    {
        var users = await db.Users.AsNoTracking()
            .OrderBy(u => u.Handle)
            .Select(u => new { u.Id, Label = u.Handle + " — " + u.Name })
            .ToListAsync(ct);

        model.People = new MultiSelectList(users, "Id", "Label",
            model.Meeting.Participants.Select(p => p.UserId));
        model.Chairs = new SelectList(users, "Id", "Label", model.Meeting.ChairUserId);

        model.Pillars = new SelectList(
            await db.Pillars.AsNoTracking().OrderBy(p => p.SortOrder).ToListAsync(ct),
            nameof(Pillar.Id), nameof(Pillar.Name));

        model.Projects = new SelectList(
            await db.Projects.AsNoTracking()
                .Include(p => p.Pillar)
                .OrderBy(p => p.Pillar!.SortOrder).ThenBy(p => p.Name)
                .Select(p => new { p.Id, Label = p.Pillar!.Name + " / " + p.Name })
                .ToListAsync(ct),
            "Id", "Label");

        ViewBag.WorkItems = new SelectList(
            await db.WorkItems.AsNoTracking()
                .Include(i => i.Project)!.ThenInclude(p => p!.Pillar)
                .OrderBy(i => i.Project!.Pillar!.SortOrder).ThenBy(i => i.Project!.Name).ThenBy(i => i.SortOrder)
                .Select(i => new { i.Id, Label = i.Project!.Pillar!.Name + " / " + i.Project.Name + " / " + i.Name })
                .ToListAsync(ct),
            "Id", "Label");

        model.PlanActions = new SelectList(
            await db.Actions.AsNoTracking()
                .Where(a => a.Origin == ActionOrigin.Plan)
                .OrderBy(a => a.Serial)
                .Select(a => new { a.Id, Label = a.Serial + " — " + a.Name })
                .ToListAsync(ct),
            "Id", "Label");

        model.MeetingActions = new SelectList(
            await db.Actions.AsNoTracking()
                .Where(a => a.MeetingId != null)
                .OrderBy(a => a.Serial)
                .Select(a => new { a.Id, Label = a.Serial + " — " + a.Name })
                .ToListAsync(ct),
            "Id", "Label");
    }

    private static Quarter QuarterOf(DateOnly date) => date.Month switch
    {
        >= 1 and <= 3 => Quarter.Q1,
        >= 4 and <= 6 => Quarter.Q2,
        >= 7 and <= 9 => Quarter.Q3,
        _ => Quarter.Q4
    };
}
