using System.Diagnostics;
using DigitalControlTower.Web.Models;
using Microsoft.AspNetCore.Mvc;

namespace DigitalControlTower.Web.Controllers;

public class HomeController : Controller
{
    public IActionResult Index() => RedirectToAction("Index", "Dashboard");

    [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
    public IActionResult Error() =>
        View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
}
