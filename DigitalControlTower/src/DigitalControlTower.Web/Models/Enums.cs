using System.ComponentModel.DataAnnotations;

namespace DigitalControlTower.Web.Models;

/// <summary>Lifecycle of a single action inside the control tower.</summary>
public enum ActionStatus
{
    [Display(Name = "Not Started")] NotStarted = 0,
    [Display(Name = "Needs Definition")] NeedsDefinition = 1,
    [Display(Name = "On Track")] OnTrack = 2,
    [Display(Name = "At Risk")] AtRisk = 3,
    [Display(Name = "Delayed")] Delayed = 4,
    [Display(Name = "Complete")] Complete = 5,
    [Display(Name = "Cancelled")] Cancelled = 6
}

public enum Priority
{
    [Display(Name = "Low")] Low = 0,
    [Display(Name = "Medium")] Medium = 1,
    [Display(Name = "High")] High = 2,
    [Display(Name = "Critical")] Critical = 3
}

public enum Quarter
{
    [Display(Name = "Not set")] None = 0,
    [Display(Name = "Q1")] Q1 = 1,
    [Display(Name = "Q2")] Q2 = 2,
    [Display(Name = "Q3")] Q3 = 3,
    [Display(Name = "Q4")] Q4 = 4
}

public enum Recurrence
{
    [Display(Name = "None")] None = 0,
    [Display(Name = "Weekly")] Weekly = 1,
    [Display(Name = "Bi-weekly")] BiWeekly = 2,
    [Display(Name = "Monthly")] Monthly = 3,
    [Display(Name = "Quarterly")] Quarterly = 4
}

public enum ProjectStatus
{
    [Display(Name = "Not Started")] NotStarted = 0,
    [Display(Name = "Active")] Active = 1,
    [Display(Name = "On Hold")] OnHold = 2,
    [Display(Name = "Complete")] Complete = 3,
    [Display(Name = "Cancelled")] Cancelled = 4
}

public enum ProjectScope
{
    [Display(Name = "Local")] Local = 0,
    [Display(Name = "Regional")] Regional = 1,
    [Display(Name = "Global")] Global = 2
}

/// <summary>Mark placed in a week cell of the 14-week tracking grid.</summary>
public enum WeekMark
{
    [Display(Name = "Target")] T = 0,
    [Display(Name = "Revised")] R = 1,
    [Display(Name = "Actual")] A = 2
}
