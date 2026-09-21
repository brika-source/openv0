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
    public DbSet<ActionOwner> ActionOwners => Set<ActionOwner>();
    public DbSet<ActionWeek> ActionWeeks => Set<ActionWeek>();
    public DbSet<ActionTag> ActionTags => Set<ActionTag>();
    public DbSet<ActionHistory> ActionHistories => Set<ActionHistory>();
    public DbSet<Comment> Comments => Set<Comment>();
    public DbSet<Meeting> Meetings => Set<Meeting>();
    public DbSet<MeetingParticipant> MeetingParticipants => Set<MeetingParticipant>();
    public DbSet<ReminderLog> ReminderLogs => Set<ReminderLog>();
    public DbSet<WeekDate> WeekDates => Set<WeekDate>();
    public DbSet<SerialCounter> SerialCounters => Set<SerialCounter>();

    /// <summary>Action fields whose changes are written to the audit trail.</summary>
    private static readonly string[] AuditedActionFields =
    [
        nameof(ActionItem.Status), nameof(ActionItem.Priority),
        nameof(ActionItem.Quarter), nameof(ActionItem.Name), nameof(ActionItem.Target),
        nameof(ActionItem.DueDate), nameof(ActionItem.CompletionDate), nameof(ActionItem.NextStep)
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
        b.Entity<Project>().Property(p => p.SavingsType).HasConversion<string>().HasMaxLength(32);
        b.Entity<ActionItem>().Property(a => a.Origin).HasConversion<string>().HasMaxLength(16);
        b.Entity<ActionWeek>().Property(w => w.Mark).HasConversion<string>().HasMaxLength(8);

        b.Entity<AppUser>().HasIndex(u => u.Handle).IsUnique();
        b.Entity<AppUser>().HasIndex(u => u.Email).IsUnique();

        b.Entity<Pillar>().HasIndex(p => p.Name).IsUnique();

        b.Entity<Project>()
            .HasOne(p => p.Pillar).WithMany(p => p.Projects)
            .HasForeignKey(p => p.PillarId).OnDelete(DeleteBehavior.Cascade);

        // A project points at two people (digital owner and PM). SQL Server refuses more
        // than one cascading or SET NULL path between the same pair of tables, so both are
        // restricted and UsersController clears them before deleting someone.
        b.Entity<Project>()
            .HasOne(p => p.DigitalOwner).WithMany(u => u.OwnedProjects)
            .HasForeignKey(p => p.DigitalOwnerId).OnDelete(DeleteBehavior.Restrict);

        b.Entity<Project>()
            .HasOne(p => p.Pm).WithMany(u => u.ManagedProjects)
            .HasForeignKey(p => p.PmId).OnDelete(DeleteBehavior.Restrict);

        b.Entity<Project>().HasIndex(p => p.PillarId);
        b.Entity<Project>().HasIndex(p => p.Status);

        b.Entity<WorkItem>()
            .HasOne(i => i.Project).WithMany(p => p.Items)
            .HasForeignKey(i => i.ProjectId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<WorkItem>().HasIndex(i => i.ProjectId);

        b.Entity<ActionItem>()
            .HasOne(a => a.WorkItem).WithMany(i => i.Actions)
            .HasForeignKey(a => a.WorkItemId).OnDelete(DeleteBehavior.Cascade);

        // The project and pillar links are the rolled-up context, kept in step with the
        // work item below. They are restricted so they never add a second cascade path.
        b.Entity<ActionItem>()
            .HasOne(a => a.Project).WithMany(p => p.Actions)
            .HasForeignKey(a => a.ProjectId).OnDelete(DeleteBehavior.Restrict);
        b.Entity<ActionItem>()
            .HasOne(a => a.Pillar).WithMany(p => p.Actions)
            .HasForeignKey(a => a.PillarId).OnDelete(DeleteBehavior.Restrict);
        b.Entity<ActionItem>()
            .HasOne(a => a.Meeting).WithMany(m => m.Actions)
            .HasForeignKey(a => a.MeetingId).OnDelete(DeleteBehavior.SetNull);
        b.Entity<ActionItem>()
            .HasOne(a => a.RelatedAction).WithMany()
            .HasForeignKey(a => a.RelatedActionId).OnDelete(DeleteBehavior.NoAction);

        b.Entity<ActionItem>().HasIndex(a => a.Serial).IsUnique();
        b.Entity<ActionItem>().HasIndex(a => a.WorkItemId);
        b.Entity<ActionItem>().HasIndex(a => a.ProjectId);
        b.Entity<ActionItem>().HasIndex(a => a.PillarId);
        b.Entity<ActionItem>().HasIndex(a => a.MeetingId);
        b.Entity<ActionItem>().HasIndex(a => a.Status);
        b.Entity<ActionItem>().HasIndex(a => a.DueDate);

        b.Entity<ActionOwner>()
            .HasOne(o => o.ActionItem).WithMany(a => a.Owners)
            .HasForeignKey(o => o.ActionItemId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<ActionOwner>()
            .HasOne(o => o.User).WithMany(u => u.ActionOwnerships)
            .HasForeignKey(o => o.UserId).OnDelete(DeleteBehavior.Restrict);
        b.Entity<ActionOwner>().HasIndex(o => new { o.ActionItemId, o.UserId }).IsUnique();
        b.Entity<ActionOwner>().HasIndex(o => o.UserId);

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

        b.Entity<Meeting>().HasIndex(m => m.Serial).IsUnique();
        b.Entity<Meeting>().HasIndex(m => m.Date);
        b.Entity<Meeting>()
            .HasOne(m => m.Chair).WithMany()
            .HasForeignKey(m => m.ChairUserId).OnDelete(DeleteBehavior.Restrict);

        b.Entity<MeetingParticipant>()
            .HasOne(p => p.Meeting).WithMany(m => m.Participants)
            .HasForeignKey(p => p.MeetingId).OnDelete(DeleteBehavior.Cascade);
        b.Entity<MeetingParticipant>()
            .HasOne(p => p.User).WithMany()
            .HasForeignKey(p => p.UserId).OnDelete(DeleteBehavior.Restrict);
        b.Entity<MeetingParticipant>().HasIndex(p => new { p.MeetingId, p.UserId }).IsUnique();

        b.Entity<ReminderLog>().HasIndex(r => r.SentAt);

        b.Entity<WeekDate>().Property(w => w.WeekIndex).ValueGeneratedNever();
    }

    public override int SaveChanges()
    {
        ResolveActionContext();
        StampAndAudit();
        return base.SaveChanges();
    }

    public override Task<int> SaveChangesAsync(CancellationToken cancellationToken = default)
    {
        ResolveActionContext();
        StampAndAudit();
        return base.SaveChangesAsync(cancellationToken);
    }

    /// <summary>
    /// Rolls an action's context up from its work item: an action under a work item always
    /// reports that work item's project and pillar, so every report can group on the action
    /// itself. Actions raised in a meeting keep whatever context was chosen for them.
    /// </summary>
    private void ResolveActionContext()
    {
        var entries = ChangeTracker.Entries<ActionItem>()
            .Where(e => e.State is EntityState.Added or EntityState.Modified)
            .ToList();
        if (entries.Count == 0) return;

        var wanted = entries
            .Select(e => e.Entity.WorkItemId)
            .Where(id => !string.IsNullOrEmpty(id))
            .Distinct()
            .ToList();

        var map = wanted.Count == 0
            ? new Dictionary<string, (string ProjectId, string PillarId)>()
            : WorkItems.AsNoTracking()
                .Where(i => wanted.Contains(i.Id))
                .Select(i => new { i.Id, i.ProjectId, i.Project!.PillarId })
                .ToDictionary(x => x.Id, x => (x.ProjectId, x.PillarId));

        foreach (var entry in entries)
        {
            var action = entry.Entity;
            if (string.IsNullOrEmpty(action.WorkItemId)) continue;

            // A work item added in the same unit of work is not in the database yet.
            if (!map.TryGetValue(action.WorkItemId, out var context))
            {
                var local = WorkItems.Local.FirstOrDefault(i => i.Id == action.WorkItemId);
                var project = local is null
                    ? null
                    : Projects.Local.FirstOrDefault(p => p.Id == local.ProjectId);
                if (local is null || project is null) continue;
                context = (local.ProjectId, project.PillarId);
            }

            action.ProjectId = context.ProjectId;
            action.PillarId = context.PillarId;
        }
    }

    /// <summary>Maintains UpdatedAt and appends audit rows for modified actions.</summary>
    private void StampAndAudit()
    {
        var now = DateTime.UtcNow;
        var actor = currentUser?.Handle ?? "system";

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

        foreach (var entry in ChangeTracker.Entries<Meeting>())
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
