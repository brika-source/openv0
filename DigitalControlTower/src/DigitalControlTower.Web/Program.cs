using DigitalControlTower.Web.Data;
using DigitalControlTower.Web.Services;
using Microsoft.EntityFrameworkCore;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddControllersWithViews();
builder.Services.AddHttpContextAccessor();
builder.Services.AddScoped<ICurrentUser, HttpContextCurrentUser>();
builder.Services.AddScoped<SerialService>();
builder.Services.AddScoped<JsonExportSeeder>();

// Reminders: SMTP settings and the daily "due in two days" sweep.
builder.Services.Configure<EmailOptions>(builder.Configuration.GetSection(EmailOptions.Section));
builder.Services.Configure<ReminderOptions>(builder.Configuration.GetSection(ReminderOptions.Section));
builder.Services.AddScoped<IEmailSender, SmtpEmailSender>();
builder.Services.AddScoped<ReminderService>();
builder.Services.AddHostedService<ReminderBackgroundService>();

var connectionString = builder.Configuration.GetConnectionString("DefaultConnection")
    ?? throw new InvalidOperationException("Connection string 'DefaultConnection' is not configured.");

builder.Services.AddDbContext<ApplicationDbContext>(options =>
    options.UseSqlServer(connectionString, sql =>
    {
        sql.EnableRetryOnFailure(maxRetryCount: 3, maxRetryDelay: TimeSpan.FromSeconds(10), errorNumbersToAdd: null);
        sql.MigrationsAssembly(typeof(ApplicationDbContext).Assembly.FullName);
    }));

var app = builder.Build();

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles();
app.UseRouting();
app.UseAuthorization();

app.MapControllerRoute(name: "default", pattern: "{controller=Dashboard}/{action=Index}/{id?}");

// Applies pending migrations and, on an empty database, imports the JSON export.
// Both steps are opt-in through configuration so production deployments stay in control.
await using (var scope = app.Services.CreateAsyncScope())
{
    var config = scope.ServiceProvider.GetRequiredService<IConfiguration>();
    var logger = scope.ServiceProvider.GetRequiredService<ILogger<Program>>();

    if (config.GetValue("Database:AutoMigrate", false))
    {
        var db = scope.ServiceProvider.GetRequiredService<ApplicationDbContext>();
        logger.LogInformation("Applying database migrations...");
        await db.Database.MigrateAsync();
    }

    if (config.GetValue("Database:SeedFromJsonExport", false))
    {
        var seeder = scope.ServiceProvider.GetRequiredService<JsonExportSeeder>();
        await seeder.SeedAsync(config.GetValue<string?>("Database:SeedFilePath"));
    }
}

app.Run();

/// <summary>Exposed so integration tests can boot the application host.</summary>
public partial class Program;
