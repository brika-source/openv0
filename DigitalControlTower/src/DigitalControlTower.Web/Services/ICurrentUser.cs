namespace DigitalControlTower.Web.Services;

/// <summary>Who is making the change, for the audit trail.</summary>
public interface ICurrentUser
{
    string Name { get; }
}

/// <summary>
/// Resolves the actor from the authenticated principal, falling back to the
/// "dct_user" cookie that the header drop-down sets, then to "Unknown".
/// Swap this for Windows/Entra ID authentication when the app is deployed.
/// </summary>
public class HttpContextCurrentUser(IHttpContextAccessor accessor) : ICurrentUser
{
    public const string CookieName = "dct_user";

    public string Name
    {
        get
        {
            var ctx = accessor.HttpContext;
            if (ctx is null) return "System";

            var identityName = ctx.User?.Identity?.Name;
            if (!string.IsNullOrWhiteSpace(identityName)) return identityName;

            if (ctx.Request.Cookies.TryGetValue(CookieName, out var cookie) && !string.IsNullOrWhiteSpace(cookie))
                return cookie;

            return "Unknown";
        }
    }
}
