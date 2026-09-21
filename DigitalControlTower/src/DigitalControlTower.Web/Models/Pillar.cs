using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>Top level of the hierarchy: Value Protection, Value Creation, Capability, ...</summary>
public class Pillar
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"pillar-{Guid.NewGuid():N}"[..15];

    [Required, StringLength(128), Display(Name = "Pillar")]
    public string Name { get; set; } = string.Empty;

    [Display(Name = "Sort order")]
    public int SortOrder { get; set; }

    public ICollection<Project> Projects { get; set; } = new List<Project>();

    /// <summary>Actions rolled up to this pillar, including ones raised in a meeting.</summary>
    public ICollection<ActionItem> Actions { get; set; } = new List<ActionItem>();
}
