using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>A person who can own a project or be referenced on an action.</summary>
public class AppUser
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"user-{Guid.NewGuid():N}"[..13];

    [Required, StringLength(128), Display(Name = "Name")]
    public string Name { get; set; } = string.Empty;

    [Required, StringLength(256), EmailAddress, Display(Name = "Email")]
    public string Email { get; set; } = string.Empty;

    public ICollection<Project> OwnedProjects { get; set; } = new List<Project>();
}
