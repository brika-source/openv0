using DigitalControlTower.Web.Models;

namespace DigitalControlTower.Web.ViewModels;

public class ProjectDetailsViewModel
{
    public Project Project { get; set; } = null!;
    public int TotalActions { get; set; }
    public int CompleteActions { get; set; }
    public int OpenActions { get; set; }
    public decimal Progress => TotalActions == 0 ? 0 : Math.Round(100m * CompleteActions / TotalActions, 1);
}
