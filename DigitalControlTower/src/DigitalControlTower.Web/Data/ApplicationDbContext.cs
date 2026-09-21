using DigitalControlTower.Web.Models;
using DigitalControlTower.Web.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.ChangeTracking;

namespace DigitalControlTower.Web.Data;

public class ApplicationDbContext(DbContextOptions<ApplicationDbContext> options, ICurrentUser? currentUser = null)
    : DbContext(options)
{
    public DbSet<AppUser> Users => Set<AppUser>();
    public DbSet<Pillar> Pillars => Set<Pillar>();
    public DbSet<Project> Projects => Set<Project>();
    public DbSet<WorkItem> WorkItems => Set<WorkItem>();
    public DbSet<ActionItem> Actions => Set<ActionItem>();
    public DbSet<ActionWeek> ActionWeeks => Set<ActionWeek>();
    public DbSet<ActionTag> ActionTags => Set<ActionTag>();
    public DbSet<ActionHistory> ActionHistories => Set<ActionHistory>();
    public DbSet<Comment> Comments => Set<Comment>();
    public DbSet<WeekDate> WeekDates => Set<WeekDate>();
    public DbSet<SerialCounter> SerialCounters => Set<SerialCounter>();

    /// <summary>Action fields whose changes are written to the audit trail.</summary>
    private static readonly string[] AuditedActionFields =
    [
        nameof(ActionItem.Status), nameof(ActionItem.Owner), nameof(ActionItem.Priority),
        nameof(ActionItem.Quarter), nameof(ActionItem.Name), nameof(ActionItem.Target),
        nameof(ActionItem.ReviewDate), nameof(ActionItem.CompletionDate), nameof(ActionItem.NextStep)
    ];

    protected override void OnModelCreating(ModelBuilder b)
    {
        base.OnModelCreating(b);

        // Enums are persisted as their names so the tables stay readable in T-SQL.
        b.Entity<ActionItem>().Property(a => a.Status).HasConversion<string>().HasMaxLength(32);
        b.Entity<ActionItem>().Property(a => a.Priority).HasConversion<string>().HasMaxLength(32);
        b.Entity<ActionItem>().Property(a => a.Quarter).HasConversion<string>().HasMaxLength(32);
        b.Entity<ActionItem>().Property(a => a.Recurrence).HasConversion<string>().HasMaxLength(32);
        b.Entity<Project>().Property(p => p.Status).HasConversion<string>().HasMaxLength(32);
        b.Entity<Project>().Property(p => p.Scope).HasConversion<string>().HasMaxLength(32);
        b.Entity<ActionWeek>().Property(w => w.Mark).HasConversion<string>().HasMaxLength(8);

        b.Entity<AppUser>().HasIndex(u => u.Email).IsUnique();

        b.Entity<Pillar>().HasIndex(p => p.Name).IsUnique();

        b.Entity<Project>()
            .HasOne(p => p.Pillar).WithMany(p => p.Projects)
            .HasForeignKey(p => p.PillarId).OnDelete(DeleteBehavior.Cascade);

        b.Entity<Project>()
            .HasOne(p => p.DigitalOwner).WithMany(u => u.OwnedProjects)
            .HasForeignKey(p => p.DigitalOwnerId).OnDelete(DeleteBehavior.SetNull);

        b.Entity<Project>().HasIndex(p => p.PillarId);
        b.Entity<Project>().HasIndex(p => p.Status);

        b.Entity<WorkItem>()
            .HasOne(i => i.Project).WithMany(p => p.Items)
            .HasForeignKey(i => i.ProjectId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<WorkItem>().HasIndex(i => i.ProjectId);

        b.Entity<ActionItem>()
            .HasOne(a => a.WorkItem).WithMany(i => i.Actions)
            .HasForeignKey(a => a.WorkItemId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<ActionItem>().HasIndex(a => a.Serial).IsUnique();
        b.Entity<ActionItem>().HasIndex(a => a.WorkItemId);
        b.Entity<ActionItem>().HasIndex(a => a.Status);
        b.Entity<ActionItem>().HasIndex(a => a.Owner);

        b.Entity<ActionWeek>()
            .HasOne(w => w.ActionItem).WithMany(a => a.Weeks)
            .HasForeignKey(w => w.ActionItemId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<ActionWeek>().HasIndex(w => new { w.ActionItemId, w.WeekIndex }).IsUnique();

        b.Entity<ActionTag>()
            .HasOne(t => t.ActionItem).WithMany(a => a.Tags)
            .HasForeignKey(t => t.ActionItemId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<ActionTag>().HasIndex(t => new { t.ActionItemId, t.Tag }).IsUnique();

        b.Entity<ActionHistory>()
            .HasOne(h => h.ActionItem).WithMany(a => a.History)
            .HasForeignKey(h => h.ActionItemId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<ActionHistory>().HasIndex(h => new { h.ActionItemId, h.At });

        b.Entity<Comment>()
            .HasOne(c => c.ActionItem).WithMany(a => a.Comments)
            .HasForeignKey(c => c.ActionItemId).OnDelete(DeleteBehavior.Cascade);
        // Cascading from two parents onto one table trips SQL Server's multiple-cascade-paths
        // rule, so work item comments are removed explicitly in WorkItemsController.
        b.Entity<Comment>()
            .HasOne(c => c.WorkItem).WithMany(i => i.Comments)
            .HasForeignKey(c => c.WorkItemId).OnDelete(DeleteBehavior.Restrict);
        b.Entity<Comment>().ToTable(t => t.HasCheckConstraint(
            "CK_Comments_SingleParent",
            "([ActionItemId] IS NOT NULL AND [WorkItemId] IS NULL) OR ([ActionItemId] IS NULL AND [WorkItemId] IS NOT NULL)"));

        b.Entity<WeekDate>().Property(w => w.WeekIndex).ValueGeneratedNever();
    }

    public override int SaveChanges()
    {
        StampAndAudit();
        return base.SaveChanges();
    }

    public override Task<int> SaveChangesAsync(CancellationToken cancellationToken = default)
    {
        StampAndAudit();
        return base.SaveChangesAsync(cancellationToken);
    }

    /// <summary>Maintains UpdatedAt and appends audit rows for modified actions.</summary>
    private void StampAndAudit()
    {
        var now = DateTime.UtcNow;
        var actor = currentUser?.Name ?? "System";

        // Timestamps are only filled in when the caller left them unset, so an import
        // of an existing export keeps the original CreatedAt/UpdatedAt values.
        foreach (var entry in ChangeTracker.Entries<Project>())
        {
            if (entry.State == EntityState.Added)
            {
                if (entry.Entity.CreatedAt == default) entry.Entity.CreatedAt = now;
                if (entry.Entity.UpdatedAt == default) entry.Entity.UpdatedAt = entry.Entity.CreatedAt;
            }
            else if (entry.State == EntityState.Modified)
            {
                entry.Entity.UpdatedAt = now;
            }
        }

        var audits = new List<ActionHistory>();
        foreach (var entry in ChangeTracker.Entries<ActionItem>())
        {
            if (entry.State == EntityState.Added)
            {
                if (entry.Entity.CreatedAt == default) entry.Entity.CreatedAt = now;
                if (entry.Entity.UpdatedAt == default) entry.Entity.UpdatedAt = entry.Entity.CreatedAt;
                continue;
            }

            if (entry.State != EntityState.Modified) continue;

            foreach (var field in AuditedActionFields)
            {
                var property = entry.Property(field);
                if (!property.IsModified) continue;

                var from = Format(property.OriginalValue);
                var to = Format(property.CurrentValue);
                if (from == to) continue;

                audits.Add(new ActionHistory
                {
                    ActionItemId = entry.Entity.Id,
                    Field = char.ToLowerInvariant(field[0]) + field[1..],
                    FromValue = from,
                    ToValue = to,
                    By = actor,
                    At = now
                });
            }

            entry.Entity.UpdatedAt = now;
        }

        if (audits.Count > 0) ActionHistories.AddRange(audits);
    }

    private static string? Format(object? value) => value switch
    {
        null => null,
        DateOnly d => d.ToString("yyyy-MM-dd"),
        DateTime dt => dt.ToString("O"),
        Enum e => e.ToString(),
        _ => value.ToString()
    };
}
