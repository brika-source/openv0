using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>
/// Links an action to the people who own it. An action usually has one owner, but the
/// tracker has always allowed shared ones ("Islam Amer, Walaa Brika"), so this is a set.
/// </summary>
public class ActionOwner
{
    public int Id { get; set; }

    [Required, StringLength(64)]
    public string ActionItemId { get; set; } = string.Empty;

    [ForeignKey(nameof(ActionItemId))]
    public ActionItem? ActionItem { get; set; }

    [Required, StringLength(64)]
    public string UserId { get; set; } = string.Empty;

    [ForeignKey(nameof(UserId))]
    public AppUser? User { get; set; }
}
