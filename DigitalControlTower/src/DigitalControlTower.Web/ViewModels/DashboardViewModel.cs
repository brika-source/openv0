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

    public record StatusCount(ActionStatus Status, int Count);
    public record OwnerLoad(string Owner, int Open, int Complete);

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
