using DigitalControlTower.Web.Models;

namespace DigitalControlTower.Web.ViewModels;

public class DashboardViewModel
{
    public int TotalActions { get; set; }
    public int OpenActions { get; set; }
    public int CompleteActions { get; set; }
    public int OverdueActions { get; set; }
    public int ProjectCount { get; set; }
    public int ActiveProjects { get; set; }

    public decimal CompletionRate => TotalActions == 0 ? 0 : Math.Round(100m * CompleteActions / TotalActions, 1);

    public IReadOnlyList<StatusCount> StatusBreakdown { get; set; } = [];
    public IReadOnlyList<PillarSummary> Pillars { get; set; } = [];
    public IReadOnlyList<OwnerLoad> TopOwners { get; set; } = [];
    public IReadOnlyList<ActionItem> Attention { get; set; } = [];
    public IReadOnlyList<ActionHistory> RecentActivity { get; set; } = [];
    public WeekDate? CurrentWeek { get; set; }

    /// <summary>Today's digital team meeting, when one has been opened already.</summary>
    public Meeting? TodaysMeeting { get; set; }
    public Meeting? LastMeeting { get; set; }
    public int MeetingActionsOpen { get; set; }

    /// <summary>Value delivered so far, for the headline on the home page.</summary>
    public decimal TotalFinancialValue { get; set; }
    public decimal TotalHoursSaved { get; set; }
    public int ProjectsWithoutValue { get; set; }

    /// <summary>Reminders that will go out on the next run.</summary>
    public int RemindersDueNextRun { get; set; }
    public DateOnly ReminderTargetDate { get; set; }
    public int ActionsWithoutDueDate { get; set; }

    /// <summary>Every person carrying work, heaviest first.</summary>
    public IReadOnlyList<WorkloadRow> Workload { get; set; } = [];
    /// <summary>Dated projects, earliest start first.</summary>
    public IReadOnlyList<TimelineRow> Timeline { get; set; } = [];
    public DateOnly TimelineStart { get; set; }
    public DateOnly TimelineEnd { get; set; }
    /// <summary>Projects with no date anywhere, so nothing to place on the timeline.</summary>
    public int UndatedProjects { get; set; }

    public record StatusCount(ActionStatus Status, int Count);
    public record OwnerLoad(string Owner, int Open, int Complete);

    /// <summary>Workload for one person, split the way the board splits it.</summary>
    /// <remarks>
    /// An action owned jointly is counted for each of its owners, so the rows
    /// can add up to more than the number of actions.
    /// </remarks>
    public record WorkloadRow(string Handle, int Complete, int OnTrack, int Attention, int Overdue)
    {
        public int Total => Complete + OnTrack + Attention + Overdue;
    }

    /// <summary>When a project runs, for the timeline on the dashboard.</summary>
    /// <remarks>
    /// <see cref="FromOwnDates"/> is false when the span had to be derived from
    /// the dates on the project's actions, because the project itself carries none.
    /// </remarks>
    public record TimelineRow(
        string ProjectId, string ProjectName, string PillarName, int PillarIndex,
        DateOnly Start, DateOnly End, bool FromOwnDates);

    public class PillarSummary
    {
        public string Id { get; set; } = string.Empty;
        public string Name { get; set; } = string.Empty;
        public int Projects { get; set; }
        public int Actions { get; set; }
        public int Complete { get; set; }
        public int AtRisk { get; set; }
        public decimal Progress => Actions == 0 ? 0 : Math.Round(100m * Complete / Actions, 1);
    }
}
