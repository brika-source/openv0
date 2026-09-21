using Microsoft.Extensions.Options;

namespace DigitalControlTower.Web.Services;

/// <summary>
/// Runs the reminder sweep once a day at the configured time. It also runs shortly
/// after start-up, so a server that was down at the scheduled minute still catches up.
/// </summary>
public class ReminderBackgroundService(
    IServiceScopeFactory scopes,
    IOptions<ReminderOptions> options,
    ILogger<ReminderBackgroundService> logger) : BackgroundService
{
    private readonly ReminderOptions _options = options.Value;

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        if (!_options.Enabled)
        {
            logger.LogInformation("Reminder service is switched off in configuration.");
            return;
        }

        await Task.Delay(TimeSpan.FromMinutes(1), stoppingToken).ContinueWith(_ => { }, CancellationToken.None);

        while (!stoppingToken.IsCancellationRequested)
        {
            await RunOnceAsync(stoppingToken);

            var wait = TimeUntilNextRun(DateTime.Now);
            logger.LogInformation("Next reminder run in {Hours:F1} hours.", wait.TotalHours);

            try
            {
                await Task.Delay(wait, stoppingToken);
            }
            catch (OperationCanceledException)
            {
                return;
            }
        }
    }

    private async Task RunOnceAsync(CancellationToken ct)
    {
        try
        {
            using var scope = scopes.CreateScope();
            var reminders = scope.ServiceProvider.GetRequiredService<ReminderService>();
            var sent = await reminders.SendDueRemindersAsync(DateOnly.FromDateTime(DateTime.Today), ct);
            logger.LogInformation("Reminder run finished: {Sent} mail(s) sent.", sent);
        }
        catch (Exception ex)
        {
            logger.LogError(ex, "Reminder run failed; will try again at the next scheduled time.");
        }
    }

    /// <summary>Time from now until the next occurrence of the configured hour and minute.</summary>
    internal TimeSpan TimeUntilNextRun(DateTime now)
    {
        var todayRun = now.Date.Add(_options.RunAt.ToTimeSpan());
        var next = todayRun > now ? todayRun : todayRun.AddDays(1);
        return next - now;
    }
}
