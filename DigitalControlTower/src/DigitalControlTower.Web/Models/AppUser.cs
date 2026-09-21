using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>
/// A person in the control tower. Everything on screen refers to people by
/// <see cref="Handle"/> — the mail alias, e.g. "brika.wm" for Walaa Brika.
/// </summary>
public class AppUser
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"user-{Guid.NewGuid():N}"[..13];

    /// <summary>Mail alias, e.g. "brika.wm". Unique, lower case, used everywhere on screen.</summary>
    [Required, StringLength(64), Display(Name = "Handle")]
    [RegularExpression("^[a-z0-9][a-z0-9._-]*$",
        ErrorMessage = "A handle is lower case and may contain letters, digits, dots, dashes and underscores.")]
    public string Handle { get; set; } = string.Empty;

    [Required, StringLength(128), Display(Name = "Full name")]
    public string Name { get; set; } = string.Empty;

    [Required, StringLength(256), EmailAddress, Display(Name = "Email")]
    public string Email { get; set; } = string.Empty;

    /// <summary>
    /// Set on people created while importing legacy free-text owners, whose address
    /// was guessed rather than known. Clear it once the real address is filled in.
    /// </summary>
    [Display(Name = "Provisional")]
    public bool IsProvisional { get; set; }

    public ICollection<Project> OwnedProjects { get; set; } = new List<Project>();
    public ICollection<Project> ManagedProjects { get; set; } = new List<Project>();
    public ICollection<ActionOwner> ActionOwnerships { get; set; } = new List<ActionOwner>();

    public override string ToString() => Handle;
}
