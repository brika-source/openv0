using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>
/// A free text note. Exactly one of <see cref="ActionItemId"/> or <see cref="WorkItemId"/>
/// is set; the database enforces that with a check constraint.
/// </summary>
public class Comment
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"comment-{Guid.NewGuid():N}"[..19];

    [StringLength(64)]
    public string? ActionItemId { get; set; }

    [ForeignKey(nameof(ActionItemId))]
    public ActionItem? ActionItem { get; set; }

    [StringLength(64)]
    public string? WorkItemId { get; set; }

    [ForeignKey(nameof(WorkItemId))]
    public WorkItem? WorkItem { get; set; }

    [Required, StringLength(128), Display(Name = "Author")]
    public string Author { get; set; } = "Unknown";

    [Required, Display(Name = "Comment")]
    public string Text { get; set; } = string.Empty;

    [Display(Name = "Posted")]
    public DateTime At { get; set; } = DateTime.UtcNow;
}
