using DigitalControlTower.Web.Models;

namespace DigitalControlTower.Web.ViewModels;

/// <summary>What the digital department delivered, sliced the ways the team asked for.</summary>
public class ValueReportViewModel
{
    public decimal TotalFinancial { get; set; }
    public decimal TotalHours { get; set; }
    public decimal AverageProductivity { get; set; }
    public int ProjectsWithValue { get; set; }
    public int ProjectsWithoutValue { get; set; }
    public int ProjectCount => ProjectsWithValue + ProjectsWithoutValue;

    public decimal HardSaving { get; set; }
    public decimal SoftSaving { get; set; }
    public decimal CostAvoidance { get; set; }
    public decimal OtherValue { get; set; }

    public IReadOnlyList<ValueSlice> ByType { get; set; } = [];
    public IReadOnlyList<ValueSlice> ByPillar { get; set; } = [];
    public IReadOnlyList<ValueSlice> ByOwner { get; set; } = [];
    public IReadOnlyList<Project> Projects { get; set; } = [];

    /// <summary>Only projects with a value captured, when the reader wants the delivered list.</summary>
    public bool WithValueOnly { get; set; }
    public string? PillarId { get; set; }
    public SavingsType? Type { get; set; }

    /// <summary>One bar of the report: a name, the money, the hours and how many projects.</summary>
    public class ValueSlice
    {
        public string Label { get; set; } = string.Empty;
        public decimal Financial { get; set; }
        public decimal Hours { get; set; }
        public int Projects { get; set; }
        public decimal ShareOf(decimal total) => total == 0 ? 0 : Math.Round(100m * Financial / total, 1);
    }
}

/// <summary>The "what is missing" report: the gaps that stop the tower being trusted.</summary>
public class GapReportViewModel
{
    public GapFilter Filter { get; set; } = GapFilter.All;

    public IReadOnlyList<ActionItem> ActionsWithoutDueDate { get; set; } = [];
    public IReadOnlyList<ActionItem> ActionsWithoutOwner { get; set; } = [];
    public IReadOnlyList<ActionItem> OverdueActions { get; set; } = [];
    public IReadOnlyList<Project> ProjectsWithoutValue { get; set; } = [];
    public IReadOnlyList<Project> ProjectsWithoutOwner { get; set; } = [];
    public IReadOnlyList<Project> ProjectsWithoutItems { get; set; } = [];
    public IReadOnlyList<WorkItem> ItemsWithoutActions { get; set; } = [];

    public int TotalGaps =>
        ActionsWithoutDueDate.Count + ActionsWithoutOwner.Count + OverdueActions.Count +
        ProjectsWithoutValue.Count + ProjectsWithoutOwner.Count +
        ProjectsWithoutItems.Count + ItemsWithoutActions.Count;

    public bool Shows(GapFilter section) => Filter is GapFilter.All || Filter == section;
}

public enum GapFilter
{
    All = 0,
    ActionsWithoutDueDate = 1,
    ActionsWithoutOwner = 2,
    OverdueActions = 3,
    ProjectsWithoutValue = 4,
    ProjectsWithoutOwner = 5,
    ProjectsWithoutItems = 6,
    ItemsWithoutActions = 7
}
