namespace DigitalControlTower.Web.Services;

/// <summary>
/// SMTP settings for the reminder mails. Point Host/Port at the Outlook or Exchange
/// relay; leave <see cref="Enabled"/> false and nothing is sent, which is how the app
/// starts out of the box.
/// </summary>
public class EmailOptions
{
    public const string Section = "Email";

    public bool Enabled { get; set; }
    public string Host { get; set; } = "smtp.office365.com";
    public int Port { get; set; } = 587;
    public bool UseStartTls { get; set; } = true;

    /// <summary>Leave blank on an internal relay that authenticates by IP.</summary>
    public string? UserName { get; set; }
    public string? Password { get; set; }

    public string FromAddress { get; set; } = "digital.controltower@pg.com";
    public string FromName { get; set; } = "Digital Control Tower";

    /// <summary>
    /// When set, every mail goes here instead of the real owner — use it to try the
    /// reminders out on a test mailbox before pointing them at the team.
    /// </summary>
    public string? RedirectAllTo { get; set; }
}

/// <summary>When the daily reminder run happens and how far ahead it looks.</summary>
public class ReminderOptions
{
    public const string Section = "Reminders";

    public bool Enabled { get; set; } = true;

    /// <summary>Days before the due date that the owner is reminded. The team asked for 2.</summary>
    public int DaysBeforeDue { get; set; } = 2;

    /// <summary>Local time of day the run starts, 24h clock.</summary>
    public TimeOnly RunAt { get; set; } = new(7, 0);

    /// <summary>Also chase actions whose due date has already passed and are still open.</summary>
    public bool IncludeOverdue { get; set; } = true;
}
