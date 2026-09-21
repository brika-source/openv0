using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;

namespace DigitalControlTower.Web.Controllers;

/// <summary>
/// What the nightly reminder run will send, and the record of what it has sent.
/// "Send now" is here so the mail path can be proven the day it is configured.
/// </summary>
public class RemindersController(
    ApplicationDbContext db,
    ReminderService reminders,
    IEmailSender email,
    IOptions<ReminderOptions> reminderOptions,
    IOptions<EmailOptions> emailOptions) : Controller
{
    public async Task<IActionResult> Index(CancellationToken ct)
    {
        var today = DateOnly.FromDateTime(DateTime.Today);

        ViewBag.Today = today;
        ViewBag.DaysBefore = reminderOptions.Value.DaysBeforeDue;
        ViewBag.TargetDate = today.AddDays(reminderOptions.Value.DaysBeforeDue);
        ViewBag.RunAt = reminderOptions.Value.RunAt;
        ViewBag.Scheduled = reminderOptions.Value.Enabled;
        ViewBag.MailEnabled = email.IsEnabled;
        ViewBag.MailHost = emailOptions.Value.Host;
        ViewBag.RedirectAllTo = emailOptions.Value.RedirectAllTo;
        ViewBag.Log = await db.ReminderLogs.AsNoTracking()
            .OrderByDescending(r => r.SentAt).Take(25).ToListAsync(ct);

        return View(await reminders.PreviewAsync(today, ct));
    }

    /// <summary>Runs the sweep now, exactly as the scheduled run would.</summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> SendNow(CancellationToken ct)
    {
        var sent = await reminders.SendDueRemindersAsync(DateOnly.FromDateTime(DateTime.Today), ct);

        TempData["Success"] = email.IsEnabled
            ? $"Reminder run finished: {sent} mail(s) sent."
            : $"Reminder run finished: {sent} mail(s) prepared. Email is switched off, so nothing left the server — " +
              "set Email:Enabled and the SMTP host in appsettings.json.";

        return RedirectToAction(nameof(Index));
    }
}
