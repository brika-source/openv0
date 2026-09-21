using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Services;

/// <summary>Issues the ACT-0000 style serials, continuing from the numbers in the export.</summary>
public class SerialService(ApplicationDbContext db)
{
    public const string ActionCounter = "action";

    public async Task<string> NextActionSerialAsync(CancellationToken ct = default)
    {
        var counter = await db.SerialCounters.FirstOrDefaultAsync(c => c.Name == ActionCounter, ct);
        if (counter is null)
        {
            var highest = await db.Actions.CountAsync(ct);
            counter = new SerialCounter { Name = ActionCounter, LastValue = highest };
            db.SerialCounters.Add(counter);
        }

        counter.LastValue++;
        await db.SaveChangesAsync(ct);
        return $"ACT-{counter.LastValue:D4}";
    }
}
