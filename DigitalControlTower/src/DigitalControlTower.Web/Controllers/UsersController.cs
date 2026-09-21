using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class UsersController(ApplicationDbContext db) : Controller
{
    private const string EditableFields =
        nameof(AppUser.Handle) + "," + nameof(AppUser.Name) + "," +
        nameof(AppUser.Email) + "," + nameof(AppUser.IsProvisional);

    public async Task<IActionResult> Index(CancellationToken ct)
    {
        var users = await db.Users
            .Include(u => u.OwnedProjects)
            .Include(u => u.ManagedProjects)
            .AsNoTracking()
            .OrderBy(u => u.Handle)
            .ToListAsync(ct);

        ViewBag.ActionCounts = await db.ActionOwners
            .GroupBy(o => o.UserId)
            .Select(g => new { UserId = g.Key, Count = g.Count() })
            .ToDictionaryAsync(x => x.UserId, x => x.Count, ct);

        return View(users);
    }

    public IActionResult Create() => View(new AppUser());

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Create([Bind(EditableFields)] AppUser user, CancellationToken ct)
    {
        Normalise(user);
        await ValidateAsync(user, null, ct);

        if (!ModelState.IsValid) return View(user);

        user.Id = $"user-{Guid.NewGuid():N}"[..13];
        db.Users.Add(user);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"User '{user.Handle}' created.";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Edit(string id, CancellationToken ct)
    {
        var user = await db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        return user is null ? NotFound() : View(user);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(string id, [Bind(EditableFields)] AppUser input, CancellationToken ct)
    {
        var user = await db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        if (user is null) return NotFound();

        Normalise(input);
        await ValidateAsync(input, id, ct);

        if (!ModelState.IsValid) { input.Id = id; return View(input); }

        user.Handle = input.Handle;
        user.Name = input.Name;
        user.Email = input.Email;
        user.IsProvisional = input.IsProvisional;
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"User '{user.Handle}' updated.";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var user = await db.Users
            .Include(u => u.OwnedProjects)
            .Include(u => u.ManagedProjects)
            .AsNoTracking()
            .FirstOrDefaultAsync(u => u.Id == id, ct);

        if (user is null) return NotFound();

        ViewBag.ActionCount = await db.ActionOwners.CountAsync(o => o.UserId == id, ct);
        return View(user);
    }

    [HttpPost, ActionName("Delete")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteConfirmed(string id, CancellationToken ct)
    {
        var user = await db.Users
            .Include(u => u.OwnedProjects)
            .Include(u => u.ManagedProjects)
            .FirstOrDefaultAsync(u => u.Id == id, ct);
        if (user is null) return NotFound();

        // The person's links are restricted rather than cascaded (SQL Server allows only
        // one cascading path between two tables), so clear them here: projects keep their
        // record with an empty owner, and the person drops off the actions they owned.
        foreach (var project in user.OwnedProjects) project.DigitalOwnerId = null;
        foreach (var project in user.ManagedProjects) project.PmId = null;
        db.ActionOwners.RemoveRange(await db.ActionOwners.Where(o => o.UserId == id).ToListAsync(ct));

        db.Users.Remove(user);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"User '{user.Handle}' deleted.";
        return RedirectToAction(nameof(Index));
    }

    /// <summary>Sets the "acting as" cookie whose handle is stamped on comments and audit rows.</summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public IActionResult SignInAs(string handle, string? returnUrl)
    {
        if (!string.IsNullOrWhiteSpace(handle))
        {
            Response.Cookies.Append(HttpContextCurrentUser.CookieName, UserHandle.Normalise(handle), new CookieOptions
            {
                HttpOnly = true,
                SameSite = SameSiteMode.Lax,
                Expires = DateTimeOffset.UtcNow.AddDays(30)
            });
        }

        return !string.IsNullOrWhiteSpace(returnUrl) && Url.IsLocalUrl(returnUrl)
            ? Redirect(returnUrl)
            : RedirectToAction("Index", "Dashboard");
    }

    /// <summary>Falls back to the mail alias when no handle was typed, and lower-cases it.</summary>
    private static void Normalise(AppUser user)
    {
        user.Email = (user.Email ?? string.Empty).Trim();
        user.Name = (user.Name ?? string.Empty).Trim();
        user.Handle = string.IsNullOrWhiteSpace(user.Handle)
            ? UserHandle.FromEmail(user.Email)
            : UserHandle.Normalise(user.Handle);
    }

    private async Task ValidateAsync(AppUser user, string? existingId, CancellationToken ct)
    {
        // The handle is derived after binding, so re-validate it here.
        ModelState.Remove(nameof(AppUser.Handle));
        if (string.IsNullOrWhiteSpace(user.Handle))
            ModelState.AddModelError(nameof(AppUser.Handle), "Enter a handle, or an email address to derive it from.");

        if (await db.Users.AnyAsync(u => u.Handle == user.Handle && u.Id != existingId, ct))
            ModelState.AddModelError(nameof(AppUser.Handle), "That handle is already taken.");

        if (await db.Users.AnyAsync(u => u.Email == user.Email && u.Id != existingId, ct))
            ModelState.AddModelError(nameof(AppUser.Email), "That email address is already registered.");
    }
}
