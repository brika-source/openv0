using DigitalControlTower.Web.Models;
using Microsoft.AspNetCore.Mvc.Rendering;

namespace DigitalControlTower.Web.ViewModels;

public class ActionsIndexViewModel
{
    public IReadOnlyList<ActionItem> Actions { get; set; } = [];

    public string? Search { get; set; }
    public string? PillarId { get; set; }
    public string? ProjectId { get; set; }
    public ActionStatus? Status { get; set; }
    public Priority? Priority { get; set; }
    public Quarter? Quarter { get; set; }
    public string? Owner { get; set; }
    public ActionOrigin? Origin { get; set; }

    /// <summary>Gap filters, also used by the links on the "what is missing" report.</summary>
    public bool WithoutDueDate { get; set; }
    public bool WithoutOwner { get; set; }
    public bool OverdueOnly { get; set; }

    public int Page { get; set; } = 1;
    public int PageSize { get; set; } = 25;
    public int TotalCount { get; set; }
    public int TotalPages => PageSize == 0 ? 1 : Math.Max(1, (int)Math.Ceiling((double)TotalCount / PageSize));

    public SelectList? Pillars { get; set; }
    public SelectList? Projects { get; set; }
    public SelectList? Owners { get; set; }

    public bool HasFilters =>
        !string.IsNullOrWhiteSpace(Search) || !string.IsNullOrWhiteSpace(PillarId) ||
        !string.IsNullOrWhiteSpace(ProjectId) || Status is not null || Priority is not null ||
        Quarter is not null || !string.IsNullOrWhiteSpace(Owner) || Origin is not null ||
        WithoutDueDate || WithoutOwner || OverdueOnly;
}
