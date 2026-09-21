using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace DigitalControlTower.Web.Models;

/// <summary>A project belonging to a pillar; groups the work items that carry the actions.</summary>
public class Project
{
    [Key]
    [StringLength(64)]
    public string Id { get; set; } = $"project-{Guid.NewGuid():N}"[..20];

    [Required, StringLength(64)]
    public string PillarId { get; set; } = string.Empty;

    [ForeignKey(nameof(PillarId))]
    public Pillar? Pillar { get; set; }

    [Required, StringLength(200), Display(Name = "Project")]
    public string Name { get; set; } = string.Empty;

    [Display(Name = "Scope")]
    public ProjectScope? Scope { get; set; }

    [StringLength(128), Display(Name = "Department owner")]
    public string? DeptOwner { get; set; }

    [StringLength(64), Display(Name = "Digital owner")]
    public string? DigitalOwnerId { get; set; }

    [ForeignKey(nameof(DigitalOwnerId))]
    public AppUser? DigitalOwner { get; set; }

    [StringLength(64), Display(Name = "Project manager")]
    public string? PmId { get; set; }

    [ForeignKey(nameof(PmId))]
    public AppUser? Pm { get; set; }

    [Display(Name = "Status")]
    public ProjectStatus Status { get; set; } = ProjectStatus.NotStarted;

    [DataType(DataType.Date), Display(Name = "Start date")]
    public DateOnly? StartDate { get; set; }

    [DataType(DataType.Date), Display(Name = "Due date")]
    public DateOnly? DueDate { get; set; }

    [DataType(DataType.Date), Display(Name = "Baseline date")]
    public DateOnly? BaselineDate { get; set; }

    [DataType(DataType.Date), Display(Name = "Implementation date")]
    public DateOnly? ImplementationDate { get; set; }

    [DataType(DataType.Date), Display(Name = "Actual completion date")]
    public DateOnly? ActualCompletionDate { get; set; }

    [Column(TypeName = "decimal(18,2)"), Display(Name = "Cost avoidance")]
    public decimal? CostAvoidance { get; set; }

    [Column(TypeName = "decimal(18,2)"), Display(Name = "Labour hours saving")]
    public decimal? LabourHoursSaving { get; set; }

    [Column(TypeName = "decimal(18,2)"), Display(Name = "Productivity improvement %")]
    public decimal? ProductivityImprovement { get; set; }

    [StringLength(64), Display(Name = "Savings type")]
    public string? SavingsType { get; set; }

    [Display(Name = "Created")]
    public DateTime CreatedAt { get; set; }

    [Display(Name = "Updated")]
    public DateTime UpdatedAt { get; set; }

    public ICollection<WorkItem> Items { get; set; } = new List<WorkItem>();
}
