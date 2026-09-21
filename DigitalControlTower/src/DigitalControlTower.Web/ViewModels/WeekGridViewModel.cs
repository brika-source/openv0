using DigitalControlTower.Web.Models;
using Microsoft.AspNetCore.Mvc.Rendering;

namespace DigitalControlTower.Web.ViewModels;

/// <summary>Rows of the 14-week tracking grid for one pillar or project.</summary>
public class WeekGridViewModel
{
    public IReadOnlyList<WeekDate> Weeks { get; set; } = [];
    public IReadOnlyList<GridRow> Rows { get; set; } = [];

    public string? PillarId { get; set; }
    public string? ProjectId { get; set; }
    public bool OpenOnly { get; set; }

    /// <summary>How many actions match the filter, before the row cap is applied.</summary>
    public int TotalCount { get; set; }

    public int MaxRows { get; set; }

    public bool IsTruncated => TotalCount > Rows.Count;

    public SelectList? Pillars { get; set; }
    public SelectList? Projects { get; set; }

    public class GridRow
    {
        public ActionItem Action { get; set; } = null!;
        public string ProjectName { get; set; } = string.Empty;
        public string WorkItemName { get; set; } = string.Empty;
        public IReadOnlyDictionary<int, WeekMark> Marks { get; set; } = new Dictionary<int, WeekMark>();
    }
}
