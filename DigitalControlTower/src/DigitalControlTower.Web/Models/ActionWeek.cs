using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>One cell of the 14-week grid for an action.</summary>
public class ActionWeek
{
    public int Id { get; set; }

    [Required, StringLength(64)]
    public string ActionItemId { get; set; } = string.Empty;

    [ForeignKey(nameof(ActionItemId))]
    public ActionItem? ActionItem { get; set; }

    /// <summary>Zero based index into <see cref="WeekDate"/>.</summary>
    [Range(0, 51), Display(Name = "Week")]
    public int WeekIndex { get; set; }

    [Display(Name = "Mark")]
    public WeekMark Mark { get; set; } = WeekMark.T;
}
