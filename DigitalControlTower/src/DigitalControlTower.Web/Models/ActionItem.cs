using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>Where an action came from.</summary>
public enum ActionOrigin
{
    [Display(Name = "90-day plan")] Plan = 0,
    [Display(Name = "Meeting")] Meeting = 1
}

/// <summary>
/// The unit of work tracked week by week. Plan actions hang off a work item;
/// actions raised in a meeting may instead point straight at a project, a pillar
/// or another action. Either way they live in this one table, so the master plan,
/// the week grid, the reports and the reminders all see them.
/// </summary>
public class ActionItem
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"action-{Guid.NewGuid():N}"[..18];

    [StringLength(64)]
    public string? WorkItemId { get; set; }

    [ForeignKey(nameof(WorkItemId))]
    public WorkItem? WorkItem { get; set; }

    /// <summary>
    /// Kept in step with the work item's project by the context, or set directly on an
    /// action raised in a meeting. Every report groups on this rather than walking the
    /// hierarchy, so meeting actions roll up exactly like plan actions.
    /// </summary>
    [StringLength(64)]
    public string? ProjectId { get; set; }

    [ForeignKey(nameof(ProjectId))]
    public Project? Project { get; set; }

    /// <summary>Kept in step with the project's pillar, or set directly for a meeting action.</summary>
    [StringLength(64)]
    public string? PillarId { get; set; }

    [ForeignKey(nameof(PillarId))]
    public Pillar? Pillar { get; set; }

    /// <summary>The meeting this action was raised in, when it came out of one.</summary>
    [StringLength(64)]
    public string? MeetingId { get; set; }

    [ForeignKey(nameof(MeetingId))]
    public Meeting? Meeting { get; set; }

    /// <summary>Another action this one follows on from.</summary>
    [StringLength(64), Display(Name = "Related action")]
    public string? RelatedActionId { get; set; }

    [ForeignKey(nameof(RelatedActionId))]
    public ActionItem? RelatedAction { get; set; }

    [Display(Name = "Origin")]
    public ActionOrigin Origin { get; set; } = ActionOrigin.Plan;

    /// <summary>Human readable reference, e.g. ACT-0334. Unique across the tower.</summary>
    [Required, StringLength(32), Display(Name = "Serial")]
    public string Serial { get; set; } = string.Empty;

    [Required, StringLength(400), Display(Name = "Action")]
    public string Name { get; set; } = string.Empty;

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

    /// <summary>When the action is due. Reminders go out two days before this date.</summary>
    [DataType(DataType.Date), Display(Name = "Due date")]
    public DateOnly? DueDate { get; set; }

    [DataType(DataType.Date), Display(Name = "Completion date")]
    public DateOnly? CompletionDate { get; set; }

    /// <summary>Set once the "due in two days" reminder has gone out for the current due date.</summary>
    [Display(Name = "Reminder sent")]
    public DateTime? ReminderSentAt { get; set; }

    [DataType(DataType.Date)]
    public DateOnly? ReminderSentForDueDate { get; set; }

    [Display(Name = "Created")]
    public DateTime CreatedAt { get; set; }

    [Display(Name = "Updated")]
    public DateTime UpdatedAt { get; set; }

    /// <summary>The people who own this action, referenced by handle on screen.</summary>
    public ICollection<ActionOwner> Owners { get; set; } = new List<ActionOwner>();

    public ICollection<ActionWeek> Weeks { get; set; } = new List<ActionWeek>();
    public ICollection<ActionTag> Tags { get; set; } = new List<ActionTag>();
    public ICollection<ActionHistory> History { get; set; } = new List<ActionHistory>();
    public ICollection<Comment> Comments { get; set; } = new List<Comment>();

    [NotMapped]
    public bool IsOpen => Status is not (ActionStatus.Complete or ActionStatus.Cancelled);

    [NotMapped]
    public bool IsOverdue => IsOpen && DueDate is { } due && due < DateOnly.FromDateTime(DateTime.UtcNow);

    /// <summary>Owner handles, comma separated: "ismail.he, amer.is". Requires the owners to be loaded.</summary>
    [NotMapped]
    public string OwnerHandles => string.Join(", ", Owners
        .Select(o => o.User?.Handle)
        .Where(h => !string.IsNullOrWhiteSpace(h))
        .OrderBy(h => h));

    /// <summary>"Pillar / Project / Work item" as far as it is known. Requires the links to be loaded.</summary>
    [NotMapped]
    public string ContextPath => string.Join(" / ", new[]
        {
            Pillar?.Name ?? WorkItem?.Project?.Pillar?.Name,
            Project?.Name ?? WorkItem?.Project?.Name,
            WorkItem?.Name
        }
        .Where(part => !string.IsNullOrWhiteSpace(part)));
}
