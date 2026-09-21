using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

public class ActionTag
{
    public int Id { get; set; }

    [Required, StringLength(64)]
    public string ActionItemId { get; set; } = string.Empty;

    [ForeignKey(nameof(ActionItemId))]
    public ActionItem? ActionItem { get; set; }

    [Required, StringLength(64), Display(Name = "Tag")]
    public string Tag { get; set; } = string.Empty;
}
