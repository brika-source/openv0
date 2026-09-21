using System.ComponentModel.DataAnnotations;
using System.Reflection;
using DigitalControlTower.Web.Models;

namespace DigitalControlTower.Web.Extensions;

public static class DisplayExtensions
{
    /// <summary>Friendly label from the [Display] attribute, e.g. NotStarted -> "Not Started".</summary>
    public static string DisplayName(this Enum value)
    {
        var member = value.GetType().GetMember(value.ToString()).FirstOrDefault();
        return member?.GetCustomAttribute<DisplayAttribute>()?.Name ?? value.ToString();
    }

    /// <summary>Bootstrap badge class used for a status pill.</summary>
    public static string BadgeClass(this ActionStatus status) => status switch
    {
        ActionStatus.Complete => "bg-success",
        ActionStatus.OnTrack => "bg-primary",
        ActionStatus.AtRisk => "bg-warning text-dark",
        ActionStatus.Delayed => "bg-danger",
        ActionStatus.NeedsDefinition => "bg-info text-dark",
        ActionStatus.Cancelled => "bg-dark",
        _ => "bg-secondary"
    };

    public static string BadgeClass(this Priority priority) => priority switch
    {
        Priority.Critical => "bg-danger",
        Priority.High => "bg-warning text-dark",
        Priority.Medium => "bg-secondary",
        _ => "bg-light text-dark"
    };

    public static string BadgeClass(this SavingsType type) => type switch
    {
        SavingsType.Hard => "bg-success",
        SavingsType.Soft => "bg-info text-dark",
        SavingsType.CostAvoidance => "bg-primary",
        SavingsType.Other => "bg-secondary",
        _ => "bg-light text-dark"
    };

    public static string BadgeClass(this ProjectStatus status) => status switch
    {
        ProjectStatus.Active => "bg-primary",
        ProjectStatus.Complete => "bg-success",
        ProjectStatus.OnHold => "bg-warning text-dark",
        ProjectStatus.Cancelled => "bg-dark",
        _ => "bg-secondary"
    };
}
