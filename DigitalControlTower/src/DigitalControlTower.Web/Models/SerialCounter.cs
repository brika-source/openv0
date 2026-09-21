using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>Last issued number per serial family (action, meeting, review).</summary>
public class SerialCounter
{
    [Key]
    [StringLength(32)]
    public string Name { get; set; } = string.Empty;

    public int LastValue { get; set; }
}
