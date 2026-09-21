using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Models;
using Microsoft.EntityFrameworkCore;

namespace DigitalControlTower.Web.Services;

/// <summary>Issues the ACT-0000 and MTG-0000 serials, continuing from the export's numbers.</summary>
public class SerialService(ApplicationDbContext db)
{
    public const string ActionCounter = "action";
    public const string MeetingCounter = "meeting";

    public Task<string> NextActionSerialAsync(CancellationToken ct = default) =>
        NextAsync(ActionCounter, "ACT", () => db.Actions.CountAsync(ct), ct);

    public Task<string> NextMeetingSerialAsync(CancellationToken ct = default) =>
        NextAsync(MeetingCounter, "MTG", () => db.Meetings.CountAsync(ct), ct);

    private async Task<string> NextAsync(string counterName, string prefix, Func<Task<int>> countSoFar, CancellationToken ct)
    {
        var counter = await db.SerialCounters.FirstOrDefaultAsync(c => c.Name == counterName, ct);
        if (counter is null)
        {
            counter = new SerialCounter { Name = counterName, LastValue = await countSoFar() };
            db.SerialCounters.Add(counter);
        }

        counter.LastValue++;
        await db.SaveChangesAsync(ct);
        return $"{prefix}-{counter.LastValue:D4}";
    }
}
