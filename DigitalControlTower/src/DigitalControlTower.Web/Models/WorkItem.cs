using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>A workstream inside a project. Actions hang off work items.</summary>
public class WorkItem
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"item-{Guid.NewGuid():N}"[..16];

    [Required, StringLength(64)]
    public string ProjectId { get; set; } = string.Empty;

    [ForeignKey(nameof(ProjectId))]
    public Project? Project { get; set; }

    [Required, StringLength(200), Display(Name = "Work item")]
    public string Name { get; set; } = string.Empty;

    [Display(Name = "Notes")]
    public string? Notes { get; set; }

    [Display(Name = "Sort order")]
    public int SortOrder { get; set; }

    public ICollection<ActionItem> Actions { get; set; } = new List<ActionItem>();
    public ICollection<Comment> Comments { get; set; } = new List<Comment>();
}
