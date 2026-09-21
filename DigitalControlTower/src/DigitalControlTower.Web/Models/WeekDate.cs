using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>Calendar definition of the tracking horizon: week index to week start date.</summary>
public class WeekDate
{
    [Key]
    [Display(Name = "Week")]
    public int WeekIndex { get; set; }

    [DataType(DataType.Date), Display(Name = "Week starting")]
    public DateOnly StartDate { get; set; }

    public string Label => $"W{WeekIndex + 1}";
}
