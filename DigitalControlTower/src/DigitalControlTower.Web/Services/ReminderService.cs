using System.Net;
using System.Text;
using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;

namespace DigitalControlTower.Web.Services;

/// <summary>An owner and the actions they are being reminded about.</summary>
public record ReminderBatch(AppUser Owner, IReadOnlyList<ActionItem> DueSoon, IReadOnlyList<ActionItem> Overdue)
{
    public int Count => DueSoon.Count + Overdue.Count;
    public IEnumerable<ActionItem> All => DueSoon.Concat(Overdue);
}

/// <summary>
/// Works out who should be reminded about what, and sends it. The rule the team asked
/// for is two days before the due date; overdue actions are chased in the same mail.
/// An action is only reminded once per due date, so moving a date re-arms it.
/// </summary>
public class ReminderService(
    ApplicationDbContext db,
    IEmailSender email,
    IOptions<ReminderOptions> options,
    ILogger<ReminderService> logger)
{
    private readonly ReminderOptions _options = options.Value;

    /// <summary>Everything that would go out today, without sending or recording anything.</summary>
    public async Task<List<ReminderBatch>> PreviewAsync(DateOnly today, CancellationToken ct = default)
    {
        var target = today.AddDays(_options.DaysBeforeDue);

        var candidates = await db.Actions
            .Include(a => a.Owners).ThenInclude(o => o.User)
            .Include(a => a.WorkItem)!.ThenInclude(i => i!.Project)
            .Include(a => a.Project)
            .Include(a => a.Pillar)
            .AsNoTracking()
            .Where(a => a.Status != ActionStatus.Complete && a.Status != ActionStatus.Cancelled
                        && a.DueDate != null
                        && (a.DueDate == target || (_options.IncludeOverdue && a.DueDate < today)))
            .ToListAsync(ct);

        var batches = new Dictionary<string, (AppUser Owner, List<ActionItem> Due, List<ActionItem> Late)>();

        foreach (var action in candidates)
        {
            foreach (var owner in action.Owners.Select(o => o.User).Where(u => u is not null))
            {
                if (!batches.TryGetValue(owner!.Id, out var batch))
                {
                    batch = (owner, [], []);
                    batches[owner.Id] = batch;
                }

                if (action.DueDate == target) batch.Due.Add(action);
                else batch.Late.Add(action);
            }
        }

        return batches.Values
            .Select(b => new ReminderBatch(
                b.Owner,
                b.Due.OrderBy(a => a.Serial).ToList(),
                b.Late.OrderBy(a => a.DueDate).ThenBy(a => a.Serial).ToList()))
            .Where(b => b.Count > 0)
            .OrderBy(b => b.Owner.Handle)
            .ToList();
    }

    /// <summary>Sends today's reminders, skipping owners already reminded for the same due date.</summary>
    public async Task<int> SendDueRemindersAsync(DateOnly today, CancellationToken ct = default)
    {
        var target = today.AddDays(_options.DaysBeforeDue);
        var batches = await PreviewAsync(today, ct);
        var sent = 0;

        foreach (var batch in batches)
        {
            // Only the "due in N days" part is de-duplicated; an overdue action keeps
            // being chased, but never on its own — it rides along with a due one.
            var fresh = batch.DueSoon
                .Where(a => a.ReminderSentForDueDate != a.DueDate)
                .ToList();

            if (fresh.Count == 0) continue;

            var payload = new ReminderBatch(batch.Owner, fresh, batch.Overdue);
            var log = new ReminderLog
            {
                Handle = batch.Owner.Handle,
                Email = batch.Owner.Email,
                ActionCount = payload.Count,
                Serials = string.Join(", ", payload.All.Select(a => a.Serial)).Truncate(1000),
                SentAt = DateTime.UtcNow
            };

            try
            {
                await email.SendAsync(Compose(payload, target), ct);
                log.Succeeded = true;
                sent++;

                var ids = fresh.Select(a => a.Id).ToList();
                await db.Actions.Where(a => ids.Contains(a.Id))
                    .ExecuteUpdateAsync(set => set
                        .SetProperty(a => a.ReminderSentAt, DateTime.UtcNow)
                        .SetProperty(a => a.ReminderSentForDueDate, a => a.DueDate), ct);
            }
            catch (Exception ex)
            {
                log.Succeeded = false;
                log.Error = ex.Message.Truncate(1000);
                logger.LogError(ex, "Reminder to {Handle} failed.", batch.Owner.Handle);
            }

            db.ReminderLogs.Add(log);
            await db.SaveChangesAsync(ct);
        }

        return sent;
    }

    /// <summary>Builds the mail: a short lead, the actions due, then anything already late.</summary>
    public EmailMessage Compose(ReminderBatch batch, DateOnly dueOn)
    {
        var subject = $"Digital Control Tower — {batch.DueSoon.Count} action(s) due {dueOn:ddd d MMM}" +
                      (batch.Overdue.Count > 0 ? $", {batch.Overdue.Count} overdue" : "");

        var html = new StringBuilder();
        html.Append("<div style=\"font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#212529\">");
        html.Append($"<p>Hello {WebUtility.HtmlEncode(batch.Owner.Name)},</p>");
        html.Append($"<p>These actions are due on <strong>{dueOn:dddd d MMMM yyyy}</strong>.</p>");
        html.Append(Table(batch.DueSoon));

        if (batch.Overdue.Count > 0)
        {
            html.Append("<p style=\"margin-top:18px\">Still open past their due date:</p>");
            html.Append(Table(batch.Overdue));
        }

        html.Append("<p style=\"color:#6c757d;font-size:12px;margin-top:18px\">" +
                    "Sent by the Digital Control Tower. Update the action in the tower to change or close it.</p></div>");

        var text = new StringBuilder();
        text.AppendLine($"Hello {batch.Owner.Name},");
        text.AppendLine();
        text.AppendLine($"Due on {dueOn:dddd d MMMM yyyy}:");
        foreach (var action in batch.DueSoon)
            text.AppendLine($"  {action.Serial}  {action.Name}  [{action.Status}]  {action.ContextPath}");

        if (batch.Overdue.Count > 0)
        {
            text.AppendLine();
            text.AppendLine("Overdue:");
            foreach (var action in batch.Overdue)
                text.AppendLine($"  {action.Serial}  {action.Name}  due {action.DueDate:yyyy-MM-dd}  [{action.Status}]");
        }

        return new EmailMessage(batch.Owner.Email, subject, html.ToString(), text.ToString());
    }

    private static string Table(IReadOnlyList<ActionItem> actions)
    {
        if (actions.Count == 0) return "<p>—</p>";

        var html = new StringBuilder();
        html.Append("<table cellpadding=\"6\" cellspacing=\"0\" style=\"border-collapse:collapse;width:100%\">");
        html.Append("<tr style=\"background:#f1f3f5;text-align:left\">" +
                    "<th>Serial</th><th>Action</th><th>Where</th><th>Due</th><th>Status</th></tr>");

        foreach (var action in actions)
        {
            html.Append("<tr style=\"border-top:1px solid #dee2e6\">");
            html.Append($"<td style=\"white-space:nowrap\">{WebUtility.HtmlEncode(action.Serial)}</td>");
            html.Append($"<td>{WebUtility.HtmlEncode(action.Name)}</td>");
            html.Append($"<td style=\"color:#6c757d\">{WebUtility.HtmlEncode(action.ContextPath)}</td>");
            html.Append($"<td style=\"white-space:nowrap\">{action.DueDate:yyyy-MM-dd}</td>");
            html.Append($"<td>{action.Status}</td>");
            html.Append("</tr>");
        }

        html.Append("</table>");
        return html.ToString();
    }
}

file static class StringExtensions
{
    public static string Truncate(this string value, int max) =>
        value.Length <= max ? value : value[..max];
}
