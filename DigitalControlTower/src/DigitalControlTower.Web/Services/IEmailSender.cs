using System.Net;
using System.Net.Mail;
using Microsoft.Extensions.Options;

namespace DigitalControlTower.Web.Services;

public record EmailMessage(string To, string Subject, string HtmlBody, string TextBody);

public interface IEmailSender
{
    /// <summary>True when mail is actually configured; false means the app only logs.</summary>
    bool IsEnabled { get; }

    Task SendAsync(EmailMessage message, CancellationToken ct = default);
}

/// <summary>
/// Sends through the configured SMTP relay. Everything Outlook needs is in appsettings:
/// host, port, STARTTLS and, on a relay that wants them, a user name and password.
/// </summary>
public class SmtpEmailSender(IOptions<EmailOptions> options, ILogger<SmtpEmailSender> logger) : IEmailSender
{
    private readonly EmailOptions _options = options.Value;

    public bool IsEnabled => _options.Enabled;

    public async Task SendAsync(EmailMessage message, CancellationToken ct = default)
    {
        var to = string.IsNullOrWhiteSpace(_options.RedirectAllTo) ? message.To : _options.RedirectAllTo;

        if (!_options.Enabled)
        {
            logger.LogInformation("Email disabled; would have sent '{Subject}' to {To}.", message.Subject, to);
            return;
        }

        using var mail = new MailMessage
        {
            From = new MailAddress(_options.FromAddress, _options.FromName),
            Subject = message.Subject,
            Body = message.HtmlBody,
            IsBodyHtml = true
        };
        mail.To.Add(to);
        mail.AlternateViews.Add(AlternateView.CreateAlternateViewFromString(message.TextBody, null, "text/plain"));
        mail.AlternateViews.Add(AlternateView.CreateAlternateViewFromString(message.HtmlBody, null, "text/html"));

        using var client = new SmtpClient(_options.Host, _options.Port) { EnableSsl = _options.UseStartTls };
        if (!string.IsNullOrWhiteSpace(_options.UserName))
        {
            client.UseDefaultCredentials = false;
            client.Credentials = new NetworkCredential(_options.UserName, _options.Password);
        }

        await client.SendMailAsync(mail, ct);
        logger.LogInformation("Sent '{Subject}' to {To}.", message.Subject, to);
    }
}
