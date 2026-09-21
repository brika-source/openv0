using System.Globalization;
using System.Text.Json;
using DigitalControlTower.Web.Models;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Data;

/// <summary>
/// One-shot import of a Digital Control Tower JSON export into SQL Server.
/// Runs only when the database has no pillars yet, so restarts are safe.
/// </summary>
public class JsonExportSeeder(ApplicationDbContext db, IWebHostEnvironment env, ILogger<JsonExportSeeder> logger)
{
    public const string DefaultRelativePath = "Data/seed/digital-control-tower-export.json";

    public async Task SeedAsync(string? relativePath = null, CancellationToken ct = default)
    {
        if (await db.Pillars.AnyAsync(ct))
        {
            logger.LogInformation("Seed skipped: the database already contains pillars.");
            return;
        }

        var path = Path.Combine(env.ContentRootPath, relativePath ?? DefaultRelativePath);
        if (!File.Exists(path))
        {
            logger.LogWarning("Seed skipped: export file not found at {Path}.", path);
            return;
        }

        await using var stream = File.OpenRead(path);
        using var doc = await JsonDocument.ParseAsync(stream, cancellationToken: ct);
        var root = doc.RootElement;

        Import(root);
        await db.SaveChangesAsync(ct);

        logger.LogInformation(
            "Seed complete: {Pillars} pillars, {Projects} projects, {Items} work items, {Actions} actions.",
            db.Pillars.Local.Count, db.Projects.Local.Count, db.WorkItems.Local.Count, db.Actions.Local.Count);
    }

    private void Import(JsonElement root)
    {
        var userIds = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

        if (root.TryGetProperty("users", out var users))
        {
            foreach (var u in users.EnumerateArray())
            {
                var id = Str(u, "id");
                if (string.IsNullOrWhiteSpace(id) || !userIds.Add(id)) continue;
                db.Users.Add(new AppUser { Id = id, Name = Str(u, "name") ?? id, Email = Str(u, "email") ?? "" });
            }
        }

        if (root.TryGetProperty("weekDates", out var weekDates))
        {
            var index = 0;
            foreach (var w in weekDates.EnumerateArray())
            {
                if (Date(w.GetString()) is { } start)
                    db.WeekDates.Add(new WeekDate { WeekIndex = index, StartDate = start });
                index++;
            }
        }

        if (root.TryGetProperty("serials", out var serials))
        {
            foreach (var s in serials.EnumerateObject())
                db.SerialCounters.Add(new SerialCounter { Name = s.Name, LastValue = s.Value.TryGetInt32(out var v) ? v : 0 });
        }

        if (!root.TryGetProperty("pillars", out var pillars)) return;

        var pillarOrder = 0;
        foreach (var p in pillars.EnumerateArray())
        {
            var pillar = new Pillar
            {
                Id = Str(p, "id") ?? $"pillar-{pillarOrder}",
                Name = Str(p, "name") ?? "Unnamed pillar",
                SortOrder = pillarOrder++
            };
            db.Pillars.Add(pillar);

            if (!p.TryGetProperty("projects", out var projects)) continue;

            foreach (var pr in projects.EnumerateArray())
            {
                var ownerId = Str(pr, "digitalOwner");
                var project = new Project
                {
                    Id = Str(pr, "id") ?? Guid.NewGuid().ToString("N"),
                    PillarId = pillar.Id,
                    Name = Str(pr, "name") ?? "Unnamed project",
                    Scope = Enum<ProjectScope>(Str(pr, "scope")),
                    DeptOwner = Str(pr, "deptOwner"),
                    DigitalOwnerId = ownerId is not null && userIds.Contains(ownerId) ? ownerId : null,
                    Pm = Str(pr, "pm"),
                    Status = Enum<ProjectStatus>(Str(pr, "status")) ?? ProjectStatus.NotStarted,
                    StartDate = Date(Str(pr, "startDate")),
                    DueDate = Date(Str(pr, "dueDate")),
                    BaselineDate = Date(Str(pr, "baselineDate")),
                    ImplementationDate = Date(Str(pr, "implementationDate")),
                    ActualCompletionDate = Date(Str(pr, "actualCompletionDate")),
                    CostAvoidance = Money(Str(pr, "costAvoidance")),
                    LabourHoursSaving = Money(Str(pr, "laborHoursSaving")),
                    ProductivityImprovement = Money(Str(pr, "productivityImprovement")),
                    SavingsType = Blank(Str(pr, "savingsType")),
                    CreatedAt = Stamp(Str(pr, "createdAt")) ?? DateTime.UtcNow
                };
                project.UpdatedAt = project.CreatedAt;
                db.Projects.Add(project);

                if (!pr.TryGetProperty("items", out var items)) continue;

                var itemOrder = 0;
                foreach (var it in items.EnumerateArray())
                {
                    var item = new WorkItem
                    {
                        Id = Str(it, "id") ?? Guid.NewGuid().ToString("N"),
                        ProjectId = project.Id,
                        Name = Str(it, "name") ?? "Unnamed work item",
                        Notes = Blank(Str(it, "notes")),
                        SortOrder = itemOrder++
                    };
                    db.WorkItems.Add(item);
                    ImportComments(it, workItemId: item.Id, actionId: null);

                    if (!it.TryGetProperty("actions", out var actions)) continue;

                    foreach (var a in actions.EnumerateArray())
                        ImportAction(a, item.Id);
                }
            }
        }
    }

    private void ImportAction(JsonElement a, string workItemId)
    {
        var created = Stamp(Str(a, "createdAt")) ?? DateTime.UtcNow;
        var action = new ActionItem
        {
            Id = Str(a, "id") ?? Guid.NewGuid().ToString("N"),
            WorkItemId = workItemId,
            Serial = Str(a, "serial") ?? "",
            Name = Str(a, "name") ?? "Unnamed action",
            Owner = Blank(Str(a, "owner")),
            Status = Enum<ActionStatus>(Str(a, "status")) ?? ActionStatus.NotStarted,
            Priority = Enum<Priority>(Str(a, "priority")) ?? Priority.Medium,
            Quarter = Enum<Quarter>(Str(a, "quarter")) ?? Quarter.None,
            Recurrence = Enum<Recurrence>(Str(a, "recurrence")) ?? Recurrence.None,
            Source = Blank(Str(a, "source")) ?? "90-Day",
            Target = Blank(Str(a, "target")),
            SuccessCriteria = Blank(Str(a, "successCriteria")),
            NextStep = Blank(Str(a, "nextStep")),
            Notes = Blank(Str(a, "notes")),
            CheckResult = Blank(Str(a, "checkResult")),
            ReviewDate = Date(Str(a, "reviewDate")),
            CompletionDate = Date(Str(a, "completionDate")),
            CreatedAt = created,
            UpdatedAt = Stamp(Str(a, "updatedAt")) ?? created
        };
        db.Actions.Add(action);

        if (a.TryGetProperty("weeks", out var weeks) && weeks.ValueKind == JsonValueKind.Object)
        {
            foreach (var w in weeks.EnumerateObject())
            {
                if (!int.TryParse(w.Name, out var index)) continue;
                var mark = Enum<WeekMark>(w.Value.GetString());
                if (mark is null) continue;
                db.ActionWeeks.Add(new ActionWeek { ActionItemId = action.Id, WeekIndex = index, Mark = mark.Value });
            }
        }

        if (a.TryGetProperty("tags", out var tags) && tags.ValueKind == JsonValueKind.Array)
        {
            foreach (var tag in tags.EnumerateArray()
                         .Select(t => t.GetString())
                         .Where(t => !string.IsNullOrWhiteSpace(t))
                         .Select(t => t!.Trim())
                         .Distinct(StringComparer.OrdinalIgnoreCase))
            {
                db.ActionTags.Add(new ActionTag { ActionItemId = action.Id, Tag = tag });
            }
        }

        if (a.TryGetProperty("history", out var history) && history.ValueKind == JsonValueKind.Array)
        {
            foreach (var h in history.EnumerateArray())
            {
                db.ActionHistories.Add(new ActionHistory
                {
                    Id = Str(h, "id") ?? Guid.NewGuid().ToString("N"),
                    ActionItemId = action.Id,
                    Field = Str(h, "field") ?? "unknown",
                    FromValue = Str(h, "from"),
                    ToValue = Str(h, "to"),
                    By = Blank(Str(h, "by")) ?? "Unknown",
                    At = Stamp(Str(h, "at")) ?? action.UpdatedAt
                });
            }
        }

        ImportComments(a, workItemId: null, actionId: action.Id);
    }

    private void ImportComments(JsonElement owner, string? workItemId, string? actionId)
    {
        if (!owner.TryGetProperty("comments", out var comments) || comments.ValueKind != JsonValueKind.Array) return;

        foreach (var c in comments.EnumerateArray())
        {
            db.Comments.Add(new Comment
            {
                Id = Str(c, "id") ?? Guid.NewGuid().ToString("N"),
                ActionItemId = actionId,
                WorkItemId = workItemId,
                Author = Blank(Str(c, "author")) ?? "Unknown",
                Text = Str(c, "text") ?? "",
                At = Stamp(Str(c, "at")) ?? DateTime.UtcNow
            });
        }
    }

    private static string? Str(JsonElement e, string name) =>
        e.TryGetProperty(name, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() : null;

    private static string? Blank(string? value) => string.IsNullOrWhiteSpace(value) ? null : value.Trim();

    private static DateOnly? Date(string? value) =>
        DateOnly.TryParse(Blank(value), CultureInfo.InvariantCulture, DateTimeStyles.None, out var d) ? d : null;

    private static DateTime? Stamp(string? value) =>
        DateTime.TryParse(Blank(value), CultureInfo.InvariantCulture, DateTimeStyles.AdjustToUniversal | DateTimeStyles.AssumeUniversal, out var dt)
            ? dt
            : null;

    private static decimal? Money(string? value)
    {
        var text = Blank(value);
        if (text is null) return null;
        text = text.Replace(",", "").Replace("$", "").Replace("%", "").Trim();
        return decimal.TryParse(text, NumberStyles.Any, CultureInfo.InvariantCulture, out var d) ? d : null;
    }

    /// <summary>Parses export labels such as "Not Started" or "Bi-weekly" into enum members.</summary>
    private static T? Enum<T>(string? value) where T : struct, System.Enum
    {
        var text = Blank(value);
        if (text is null) return null;
        var normalised = text.Replace(" ", "").Replace("-", "").Replace("_", "");
        return System.Enum.TryParse<T>(normalised, ignoreCase: true, out var parsed) ? parsed : null;
    }
}
