using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>Audit row written whenever a tracked field on an action changes.</summary>
public class ActionHistory
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"hist-{Guid.NewGuid():N}"[..16];

    [Required, StringLength(64)]
    public string ActionItemId { get; set; } = string.Empty;

    [ForeignKey(nameof(ActionItemId))]
    public ActionItem? ActionItem { get; set; }

    [Required, StringLength(64), Display(Name = "Field")]
    public string Field { get; set; } = string.Empty;

    [StringLength(400), Display(Name = "From")]
    public string? FromValue { get; set; }

    [StringLength(400), Display(Name = "To")]
    public string? ToValue { get; set; }

    [StringLength(128), Display(Name = "Changed by")]
    public string By { get; set; } = "Unknown";

    [Display(Name = "Changed at")]
    public DateTime At { get; set; } = DateTime.UtcNow;
}
