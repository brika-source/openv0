using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>Someone who attended a meeting.</summary>
public class MeetingParticipant
{
    public int Id { get; set; }

    [Required, StringLength(64)]
    public string MeetingId { get; set; } = string.Empty;

    [ForeignKey(nameof(MeetingId))]
    public Meeting? Meeting { get; set; }

    [Required, StringLength(64)]
    public string UserId { get; set; } = string.Empty;

    [ForeignKey(nameof(UserId))]
    public AppUser? User { get; set; }
}
