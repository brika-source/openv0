using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>One record per reminder email, so a run can be traced and never repeats.</summary>
public class ReminderLog
{
    public int Id { get; set; }

    [Required, StringLength(64), Display(Name = "Sent to")]
    public string Handle { get; set; } = string.Empty;

    [Required, StringLength(256), Display(Name = "Email")]
    public string Email { get; set; } = string.Empty;

    [Display(Name = "Actions included")]
    public int ActionCount { get; set; }

    [StringLength(1000), Display(Name = "Serials")]
    public string Serials { get; set; } = string.Empty;

    [Display(Name = "Sent")]
    public DateTime SentAt { get; set; } = DateTime.UtcNow;

    [Display(Name = "Delivered")]
    public bool Succeeded { get; set; }

    [StringLength(1000), Display(Name = "Error")]
    public string? Error { get; set; }
}
