using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Controllers;

public class UsersController(ApplicationDbContext db) : Controller
{
    public async Task<IActionResult> Index(CancellationToken ct) =>
        View(await db.Users.Include(u => u.OwnedProjects).AsNoTracking().OrderBy(u => u.Name).ToListAsync(ct));

    public IActionResult Create() => View(new AppUser());

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Create([Bind(nameof(AppUser.Name), nameof(AppUser.Email))] AppUser user, CancellationToken ct)
    {
        if (await db.Users.AnyAsync(u => u.Email == user.Email, ct))
            ModelState.AddModelError(nameof(AppUser.Email), "That email address is already registered.");

        if (!ModelState.IsValid) return View(user);

        user.Id = $"user-{Guid.NewGuid():N}"[..13];
        db.Users.Add(user);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"User '{user.Name}' created.";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Edit(string id, CancellationToken ct)
    {
        var user = await db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        return user is null ? NotFound() : View(user);
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Edit(string id, [Bind(nameof(AppUser.Name), nameof(AppUser.Email))] AppUser input, CancellationToken ct)
    {
        var user = await db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        if (user is null) return NotFound();

        if (await db.Users.AnyAsync(u => u.Email == input.Email && u.Id != id, ct))
            ModelState.AddModelError(nameof(AppUser.Email), "That email address is already registered.");

        if (!ModelState.IsValid) { input.Id = id; return View(input); }

        user.Name = input.Name;
        user.Email = input.Email;
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"User '{user.Name}' updated.";
        return RedirectToAction(nameof(Index));
    }

    public async Task<IActionResult> Delete(string id, CancellationToken ct)
    {
        var user = await db.Users.Include(u => u.OwnedProjects).AsNoTracking().FirstOrDefaultAsync(u => u.Id == id, ct);
        return user is null ? NotFound() : View(user);
    }

    [HttpPost, ActionName("Delete")]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> DeleteConfirmed(string id, CancellationToken ct)
    {
        var user = await db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        if (user is null) return NotFound();

        db.Users.Remove(user);
        await db.SaveChangesAsync(ct);

        TempData["Success"] = $"User '{user.Name}' deleted.";
        return RedirectToAction(nameof(Index));
    }

    /// <summary>Sets the "acting as" cookie used to attribute comments and audit rows.</summary>
    [HttpPost]
    [ValidateAntiForgeryToken]
    public IActionResult SignInAs(string name, string? returnUrl)
    {
        if (!string.IsNullOrWhiteSpace(name))
        {
            Response.Cookies.Append(Services.HttpContextCurrentUser.CookieName, name.Trim(), new CookieOptions
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
}
