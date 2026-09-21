namespace DigitalControlTower.Web.Services;

/// <summary>Who is making the change, recorded on comments and audit rows as a handle.</summary>
public interface ICurrentUser
{
    /// <summary>The acting person's mail alias, e.g. "brika.wm", or "unknown".</summary>
    string Handle { get; }
}

/// <summary>
/// Resolves the actor from the authenticated principal, falling back to the "dct_user"
/// cookie that the header picker sets, then to "unknown". Swap this for Windows or
/// Entra ID authentication when the app is deployed: the alias in the sign-in name is
/// exactly the handle used here.
/// </summary>
public class HttpContextCurrentUser(IHttpContextAccessor accessor) : ICurrentUser
{
    public const string CookieName = "dct_user";
    public const string Unknown = "unknown";

    public string Handle
    {
        get
        {
            var ctx = accessor.HttpContext;
            if (ctx is null) return "system";

            var identityName = ctx.User?.Identity?.Name;
            if (!string.IsNullOrWhiteSpace(identityName))
            {
                // DOMAIN\brika.wm or brika.wm@pg.com both reduce to the alias.
                var withoutDomain = identityName.Contains('\\') ? identityName[(identityName.IndexOf('\\') + 1)..] : identityName;
                return UserHandle.FromEmail(withoutDomain);
            }

            if (ctx.Request.Cookies.TryGetValue(CookieName, out var cookie) && !string.IsNullOrWhiteSpace(cookie))
                return UserHandle.Normalise(cookie);

            return Unknown;
        }
    }
}
