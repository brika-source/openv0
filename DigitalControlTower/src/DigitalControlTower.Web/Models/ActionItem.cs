using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>The unit of work tracked week by week in the control tower.</summary>
public class ActionItem
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"action-{Guid.NewGuid():N}"[..18];

    [Required, StringLength(64)]
    public string WorkItemId { get; set; } = string.Empty;

    [ForeignKey(nameof(WorkItemId))]
    public WorkItem? WorkItem { get; set; }

    /// <summary>Human readable reference, e.g. ACT-0334. Unique across the tower.</summary>
    [Required, StringLength(32), Display(Name = "Serial")]
    public string Serial { get; set; } = string.Empty;

    [Required, StringLength(400), Display(Name = "Action")]
    public string Name { get; set; } = string.Empty;

    [StringLength(128), Display(Name = "Owner")]
    public string? Owner { get; set; }

    [Display(Name = "Status")]
    public ActionStatus Status { get; set; } = ActionStatus.NotStarted;

    [Display(Name = "Priority")]
    public Priority Priority { get; set; } = Priority.Medium;

    [Display(Name = "Quarter")]
    public Quarter Quarter { get; set; } = Quarter.None;

    [Display(Name = "Recurrence")]
    public Recurrence Recurrence { get; set; } = Recurrence.None;

    [StringLength(64), Display(Name = "Source")]
    public string Source { get; set; } = "90-Day";

    [StringLength(400), Display(Name = "Target")]
    public string? Target { get; set; }

    [Display(Name = "Success criteria")]
    public string? SuccessCriteria { get; set; }

    [Display(Name = "Next step")]
    public string? NextStep { get; set; }

    [Display(Name = "Notes")]
    public string? Notes { get; set; }

    [Display(Name = "Check result")]
    public string? CheckResult { get; set; }

    [DataType(DataType.Date), Display(Name = "Review date")]
    public DateOnly? ReviewDate { get; set; }

    [DataType(DataType.Date), Display(Name = "Completion date")]
    public DateOnly? CompletionDate { get; set; }

    [Display(Name = "Created")]
    public DateTime CreatedAt { get; set; }

    [Display(Name = "Updated")]
    public DateTime UpdatedAt { get; set; }

    public ICollection<ActionWeek> Weeks { get; set; } = new List<ActionWeek>();
    public ICollection<ActionTag> Tags { get; set; } = new List<ActionTag>();
    public ICollection<ActionHistory> History { get; set; } = new List<ActionHistory>();
    public ICollection<Comment> Comments { get; set; } = new List<Comment>();

    [NotMapped]
    public bool IsOpen => Status is not (ActionStatus.Complete or ActionStatus.Cancelled);
}
