using DigitalControlTower.Web.Models;

namespace DigitalControlTower.Web.Views.Reports;

/// <summary>One block of the "what is missing" report: a heading, a hint and the rows.</summary>
public record ActionGapModel(string Title, string Hint, IReadOnlyList<ActionItem> Actions);

public record ProjectGapModel(string Title, string Hint, IReadOnlyList<Project> Projects);
