using System.Globalization;
using System.Text.Json;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;
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
            "Seed complete: {Users} users, {Pillars} pillars, {Projects} projects, {Items} work items, {Actions} actions.",
            db.Users.Local.Count, db.Pillars.Local.Count, db.Projects.Local.Count,
            db.WorkItems.Local.Count, db.Actions.Local.Count);
    }

    private void Import(JsonElement root)
    {
        // Everyone is identified by their mail alias. Users on file keep the alias from
        // their address; free-text names elsewhere in the export are matched to them, or
        // added as provisional users with a derived handle.
        var people = new PeopleDirectory();

        if (root.TryGetProperty("users", out var users))
        {
            foreach (var u in users.EnumerateArray())
            {
                var id = Str(u, "id");
                var email = Str(u, "email") ?? "";
                if (string.IsNullOrWhiteSpace(id)) continue;

                var user = new AppUser
                {
                    Id = id,
                    Handle = UserHandle.FromEmail(email),
                    Name = Str(u, "name") ?? id,
                    Email = email
                };

                if (string.IsNullOrWhiteSpace(user.Handle)) user.Handle = UserHandle.FromName(user.Name);
                people.Add(user);
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

        if (!root.TryGetProperty("pillars", out var pillars))
        {
            db.Users.AddRange(people.All);
            return;
        }

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
                var digitalOwner = ownerId is null ? null : people.All.FirstOrDefault(u => u.Id == ownerId);
                var pm = people.Resolve(Str(pr, "pm"));
                var project = new Project
                {
                    Id = Str(pr, "id") ?? Guid.NewGuid().ToString("N"),
                    PillarId = pillar.Id,
                    Name = Str(pr, "name") ?? "Unnamed project",
                    Scope = Enum<ProjectScope>(Str(pr, "scope")),
                    DeptOwner = Str(pr, "deptOwner"),
                    DigitalOwnerId = digitalOwner?.Id,
                    PmId = pm?.Id,
                    Status = Enum<ProjectStatus>(Str(pr, "status")) ?? ProjectStatus.NotStarted,
                    StartDate = Date(Str(pr, "startDate")),
                    DueDate = Date(Str(pr, "dueDate")),
                    BaselineDate = Date(Str(pr, "baselineDate")),
                    ImplementationDate = Date(Str(pr, "implementationDate")),
                    ActualCompletionDate = Date(Str(pr, "actualCompletionDate")),
                    CostAvoidance = Money(Str(pr, "costAvoidance")),
                    LabourHoursSaving = Money(Str(pr, "laborHoursSaving")),
                    ProductivityImprovement = Money(Str(pr, "productivityImprovement")),
                    SavingsType = SavingsTypeOf(Str(pr, "savingsType")),
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
                        Notes = Str(it, "notes"),
                        SortOrder = itemOrder++
                    };
                    db.WorkItems.Add(item);
                    ImportComments(it, workItemId: item.Id, actionId: null, people);

                    if (!it.TryGetProperty("actions", out var actions)) continue;

                    foreach (var a in actions.EnumerateArray())
                        ImportAction(a, item.Id, people);
                }
            }
        }

        // Provisional people are discovered while walking the actions, so users are
        // registered once the whole export has been read.
        db.Users.AddRange(people.All);
    }

    private void ImportAction(JsonElement a, string workItemId, PeopleDirectory people)
    {
        var created = Stamp(Str(a, "createdAt")) ?? DateTime.UtcNow;
        var action = new ActionItem
        {
            Id = Str(a, "id") ?? Guid.NewGuid().ToString("N"),
            WorkItemId = workItemId,
            Serial = Str(a, "serial") ?? "",
            Name = Str(a, "name") ?? "Unnamed action",
            Status = Enum<ActionStatus>(Str(a, "status")) ?? ActionStatus.NotStarted,
            Priority = Enum<Priority>(Str(a, "priority")) ?? Priority.Medium,
            Quarter = Enum<Quarter>(Str(a, "quarter")) ?? Quarter.None,
            Recurrence = Enum<Recurrence>(Str(a, "recurrence")) ?? Recurrence.None,
            Source = Str(a, "source") ?? "90-Day",
            Target = Str(a, "target"),
            SuccessCriteria = Str(a, "successCriteria"),
            NextStep = Str(a, "nextStep"),
            Notes = Str(a, "notes"),
            CheckResult = Str(a, "checkResult"),
            // The board's due date is "target"; "reviewDate" is a rarely used
            // second date, kept only as a fallback. Reading reviewDate alone
            // left almost every action with no due date, which silently
            // disabled the reminders and the overdue counts.
            DueDate = Date(Str(a, "target")) ?? Date(Str(a, "reviewDate")),
            CompletionDate = Date(Str(a, "completionDate")),
            CreatedAt = created,
            UpdatedAt = Stamp(Str(a, "updatedAt")) ?? created
        };
        db.Actions.Add(action);

        foreach (var owner in people.ResolveMany(Str(a, "owner")))
            db.ActionOwners.Add(new ActionOwner { ActionItemId = action.Id, UserId = owner.Id });

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
                    By = people.HandleOf(Str(h, "by"), HttpContextCurrentUser.Unknown),
                    At = Stamp(Str(h, "at")) ?? action.UpdatedAt
                });
            }
        }

        ImportComments(a, workItemId: null, actionId: action.Id, people);
    }

    private void ImportComments(JsonElement owner, string? workItemId, string? actionId, PeopleDirectory people)
    {
        if (!owner.TryGetProperty("comments", out var comments) || comments.ValueKind != JsonValueKind.Array) return;

        foreach (var c in comments.EnumerateArray())
        {
            db.Comments.Add(new Comment
            {
                Id = Str(c, "id") ?? Guid.NewGuid().ToString("N"),
                ActionItemId = actionId,
                WorkItemId = workItemId,
                Author = people.HandleOf(Str(c, "author"), HttpContextCurrentUser.Unknown),
                Text = Str(c, "text") ?? "",
                At = Stamp(Str(c, "at")) ?? DateTime.UtcNow
            });
        }
    }

    /// <summary>Reads a string property, trimmed. Missing and blank values come back as null.</summary>
    private static string? Str(JsonElement e, string name) =>
        e.TryGetProperty(name, out var v) && v.ValueKind == JsonValueKind.String ? Blank(v.GetString()) : null;

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

    /// <summary>
    /// The export keeps the savings type as free text ("hard", "Soft saving", ""), so it is
    /// mapped onto the enum; anything unrecognised is left for someone to classify.
    /// </summary>
    private static SavingsType SavingsTypeOf(string? value)
    {
        var text = Blank(value)?.ToLowerInvariant();
        if (text is null) return SavingsType.NotSet;
        if (text.Contains("hard")) return SavingsType.Hard;
        if (text.Contains("soft")) return SavingsType.Soft;
        if (text.Contains("avoid")) return SavingsType.CostAvoidance;
        return SavingsType.Other;
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
