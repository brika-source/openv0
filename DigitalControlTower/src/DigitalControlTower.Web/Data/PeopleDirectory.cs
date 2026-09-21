using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;

namespace DigitalControlTower.Web.Data;

/// <summary>
/// Resolves the free-text people names in a legacy export ("Hassan ismail",
/// "Islam Amer, Walaa Brika", "Randa") to real users identified by their handle.
/// Anyone not already on file is added as a provisional user with a derived handle.
/// </summary>
public class PeopleDirectory
{
    /// <summary>Spellings seen in the export that mean a person already on file.</summary>
    private static readonly Dictionary<string, string> KnownAliases = new(StringComparer.OrdinalIgnoreCase)
    {
        ["mohamed ibrahim"] = "mostafaabdellatif.mi",
        ["mohamed ibrahem"] = "mostafaabdellatif.mi",
        ["mohamed"] = "mostafaabdellatif.mi",
        ["shady barrage"] = "barrage.sm",
        ["shady"] = "barrage.sm",
        ["walaa brika"] = "brika.wm",
        ["walaa"] = "brika.wm",
        ["wala"] = "brika.wm",
        ["hassan ismail"] = "ismail.he",
        ["hassan"] = "ismail.he",
        ["islam amer"] = "amer.is",
        ["islam"] = "amer.is"
    };

    /// <summary>Placeholders that appear where a name should be but name nobody.</summary>
    private static readonly HashSet<string> NotPeople = new(StringComparer.OrdinalIgnoreCase)
    {
        "unknown", "n/a", "na", "none", "tbd", "tbc", "-", "--", "?"
    };

    private readonly Dictionary<string, AppUser> _byHandle = new(StringComparer.OrdinalIgnoreCase);
    private readonly Dictionary<string, AppUser> _byName = new(StringComparer.OrdinalIgnoreCase);
    private readonly List<AppUser> _created = [];

    /// <summary>Users invented for names that were not on file; they need a real address later.</summary>
    public IReadOnlyList<AppUser> ProvisionalUsers => _created;

    public IEnumerable<AppUser> All => _byHandle.Values;

    public void Add(AppUser user)
    {
        _byHandle[user.Handle] = user;
        _byName[Key(user.Name)] = user;
    }

    /// <summary>
    /// Splits a free-text owner field into people. "Islam Amer/Khaled Hassan" and
    /// "mohamed / hassan" both yield two users; an empty field yields none.
    /// </summary>
    public IEnumerable<AppUser> ResolveMany(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) yield break;

        var names = value.Split(new[] { ',', '/', '&', ';' }, StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
            .Where(part => part.Length > 0)
            .ToList();

        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var name in names)
        {
            var user = Resolve(name);
            if (user is not null && seen.Add(user.Handle)) yield return user;
        }
    }

    /// <summary>Resolves a single name, creating a provisional user when it is new.</summary>
    public AppUser? Resolve(string? name)
    {
        var text = (name ?? string.Empty).Trim();
        if (text.Length == 0 || NotPeople.Contains(text)) return null;

        if (KnownAliases.TryGetValue(Key(text), out var aliasHandle) && _byHandle.TryGetValue(aliasHandle, out var aliased))
            return aliased;

        if (_byName.TryGetValue(Key(text), out var byName)) return byName;

        var handle = text.Contains('@') ? UserHandle.FromEmail(text) : UserHandle.FromName(text);
        if (handle.Length == 0) return null;
        if (_byHandle.TryGetValue(handle, out var byHandle)) return byHandle;

        var created = new AppUser
        {
            Id = $"user-{Guid.NewGuid():N}"[..13],
            Handle = handle,
            Name = text,
            Email = $"{handle}@pg.com",
            IsProvisional = true
        };

        Add(created);
        _created.Add(created);
        return created;
    }

    /// <summary>The handle for a name, for fields that store text rather than a reference.</summary>
    public string HandleOf(string? name, string fallback)
    {
        var user = Resolve(name);
        return user?.Handle ?? fallback;
    }

    /// <summary>Collapses spacing and case so "Hassan  ismail" matches "Hassan Ismail".</summary>
    private static string Key(string? value) =>
        string.Join(' ', (value ?? string.Empty)
            .Split(' ', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries))
            .ToLowerInvariant();
}
