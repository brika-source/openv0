using DigitalControlTower.Web.Models;
using Microsoft.AspNetCore.Mvc.Rendering;

namespace DigitalControlTower.Web.ViewModels;

/// <summary>Everything the meeting page needs: the meeting, its actions, and the pickers.</summary>
public class MeetingViewModel
{
    public Meeting Meeting { get; set; } = null!;

    /// <summary>Actions raised in this meeting, newest first.</summary>
    public IReadOnlyList<ActionItem> Actions { get; set; } = [];

    public MultiSelectList? People { get; set; }
    public SelectList? Chairs { get; set; }
    public SelectList? Pillars { get; set; }
    public SelectList? Projects { get; set; }
    public SelectList? PlanActions { get; set; }
    public SelectList? MeetingActions { get; set; }

    public int OpenCount => Actions.Count(a => a.IsOpen);
    public int OverdueCount => Actions.Count(a => a.IsOverdue);
}

/// <summary>A row of the meetings list.</summary>
public class MeetingListRow
{
    public Meeting Meeting { get; set; } = null!;
    public int ActionCount { get; set; }
    public int OpenCount { get; set; }
}
