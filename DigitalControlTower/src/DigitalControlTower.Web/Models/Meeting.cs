using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>
/// A digital team meeting: who was there, the minutes, and the actions raised.
/// Those actions are ordinary actions, so they also show up in the master plan.
/// </summary>
public class Meeting
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"meeting-{Guid.NewGuid():N}"[..21];

    /// <summary>Reference shown on screen, e.g. MTG-0007.</summary>
    [Required, StringLength(32), Display(Name = "Serial")]
    public string Serial { get; set; } = string.Empty;

    [DataType(DataType.Date), Display(Name = "Meeting date")]
    public DateOnly Date { get; set; }

    [Required, StringLength(200), Display(Name = "Title")]
    public string Title { get; set; } = "Digital team meeting";

    [Display(Name = "Minutes")]
    public string? Minutes { get; set; }

    [StringLength(64), Display(Name = "Chaired by")]
    public string? ChairUserId { get; set; }

    [ForeignKey(nameof(ChairUserId))]
    public AppUser? Chair { get; set; }

    [Display(Name = "Created")]
    public DateTime CreatedAt { get; set; }

    [Display(Name = "Updated")]
    public DateTime UpdatedAt { get; set; }

    public ICollection<MeetingParticipant> Participants { get; set; } = new List<MeetingParticipant>();
    public ICollection<ActionItem> Actions { get; set; } = new List<ActionItem>();

    /// <summary>Participant handles, comma separated. Requires the participants to be loaded.</summary>
    [NotMapped]
    public string ParticipantHandles => string.Join(", ", Participants
        .Select(p => p.User?.Handle)
        .Where(h => !string.IsNullOrWhiteSpace(h))
        .OrderBy(h => h));
}
