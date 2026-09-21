using System.Globalization;
using System.Text;

namespace DigitalControlTower.Web.Services;

/// <summary>
/// Everyone in the control tower is identified by their mail alias — the part of the
/// address before the @, such as <c>brika.wm</c> for Walaa Brika. These helpers derive
/// and normalise that handle.
/// </summary>
public static class UserHandle
{
    /// <summary>Lower-cases and trims a handle so comparisons are stable.</summary>
    public static string Normalise(string? handle) =>
        (handle ?? string.Empty).Trim().ToLowerInvariant();

    /// <summary>Takes the alias out of an address: "brika.wm@pg.com" -> "brika.wm".</summary>
    public static string FromEmail(string? email)
    {
        var text = (email ?? string.Empty).Trim();
        var at = text.IndexOf('@');
        return Normalise(at > 0 ? text[..at] : text);
    }

    /// <summary>
    /// Best-effort handle for someone with no address on file, following the same
    /// surname-then-initials shape: "Ahmed Morgan" -> "morgan.a", "Randa" -> "randa".
    /// Only used when importing legacy free-text owners; correct it in Users afterwards.
    /// </summary>
    public static string FromName(string? name)
    {
        var parts = Strip(name)
            .Split(new[] { ' ', '.', '-', '_' }, StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
            .ToArray();

        if (parts.Length == 0) return string.Empty;
        if (parts.Length == 1) return Normalise(parts[0]);

        var surname = Normalise(parts[^1]);
        var initials = string.Concat(parts[..^1].Select(p => char.ToLowerInvariant(p[0])));
        return $"{surname}.{initials}";
    }

    /// <summary>Drops accents and anything that is not a letter, digit or separator.</summary>
    private static string Strip(string? value)
    {
        var decomposed = (value ?? string.Empty).Normalize(NormalizationForm.FormD);
        var builder = new StringBuilder(decomposed.Length);

        foreach (var ch in decomposed)
        {
            if (CharUnicodeInfo.GetUnicodeCategory(ch) == UnicodeCategory.NonSpacingMark) continue;
            if (char.IsLetterOrDigit(ch) || ch is ' ' or '.' or '-' or '_') builder.Append(ch);
        }

        return builder.ToString().Normalize(NormalizationForm.FormC);
    }
}
