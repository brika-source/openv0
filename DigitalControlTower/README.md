# Digital Control Tower

ASP.NET Core 8 MVC application for the Digital Control Tower, backed by SQL Server through
Entity Framework Core. It replaces the browser-local JSON tracker with a shared database:
pillars → projects → work items → actions, a 14-week tracking grid, comments and an audit trail.

## What is in here

```
DigitalControlTower.sln
src/DigitalControlTower.Web/       ASP.NET Core MVC app (controllers, views, EF Core model)
  Models/                          Entities and enums
  Data/ApplicationDbContext.cs     Mapping, indexes, constraints, audit hook
  Data/JsonExportSeeder.cs         Imports a JSON export into an empty database
  Data/Migrations/                 EF Core Code-First migrations
  Data/seed/                       The JSON export this database was modelled on
db/01_schema.sql                   Stand-alone schema script (idempotent, generated from the migration)
db/02_seed_data.sql                Stand-alone seed script generated from the JSON export
tools/generate_seed_sql.py         Regenerates 02_seed_data.sql from any newer JSON export
```

## Data model

| Table | Holds | Notes |
|---|---|---|
| `Pillars` | Value Protection, Value Creation, Capability, General, Automation, Proficy | `SortOrder` drives display order |
| `Projects` | Project record with owners, dates and savings figures | FK to `Pillars`, optional FK to `Users` |
| `WorkItems` | Workstreams inside a project | Cascade delete from `Projects` |
| `Actions` | The tracked actions (`ACT-0001` …) | Unique `Serial`, cascade delete from `WorkItems` |
| `ActionWeeks` | One row per marked cell of the 14-week grid | Unique on (`ActionItemId`, `WeekIndex`), mark `T`/`R`/`A` |
| `ActionTags` | Free-text tags per action | Unique on (`ActionItemId`, `Tag`) |
| `ActionHistories` | Audit trail (field, from, to, by, at) | Written automatically on save |
| `Comments` | Comments on an action **or** a work item | Check constraint enforces exactly one parent |
| `WeekDates` | Week index → week start date | The 14-week tracking horizon |
| `Users` | People who can be a project's digital owner | Unique email |
| `SerialCounters` | Last issued number per serial family | Seeded from the export (`action` = 385) |

Enums (`Status`, `Priority`, `Quarter`, `Recurrence`, `Scope`, `Mark`) are stored as strings such as
`NotStarted`, `OnTrack`, `AtRisk`, so the tables stay readable in T-SQL. Dates use `date`,
timestamps use `datetime2`, and money/hours columns use `decimal(18,2)`.

## Setting up the database

Point the app at your server by editing `ConnectionStrings:DefaultConnection` in
`src/DigitalControlTower.Web/appsettings.json` (or a user secret / environment variable).

**Option A — run the scripts on your SQL Server:**

```bash
sqlcmd -S localhost -d DigitalControlTower -i db/01_schema.sql
sqlcmd -S localhost -d DigitalControlTower -i db/02_seed_data.sql   # optional: loads the existing tracker data
```

Create the database first (`CREATE DATABASE DigitalControlTower;`). `01_schema.sql` is idempotent;
`02_seed_data.sql` refuses to run when `Pillars` already has rows.

**Option B — let EF Core create it:**

```bash
cd src/DigitalControlTower.Web
dotnet ef database update
```

`appsettings.Development.json` sets `Database:AutoMigrate` and `Database:SeedFromJsonExport` to
`true`, so running in Development applies migrations and imports `Data/seed/*.json` on an empty
database. Both flags default to `false` everywhere else.

## Running

```bash
cd src/DigitalControlTower.Web
dotnet run
```

Then open the URL printed in the console. Pages: **Dashboard** (KPIs, pillar progress, owner load,
what needs attention, recent changes), **Pillars**, **Projects** (filter by pillar/status, project
detail with its work items and actions), **Actions** (search + filter + paging, detail with
comments, history and its week strip), **Week grid** (click a cell to cycle blank → T → R → A →
blank), and **Users**.

The "Acting as" box in the header sets the name recorded on comments and audit rows. Replace
`HttpContextCurrentUser` in `Services/ICurrentUser.cs` with Windows or Entra ID authentication when
you deploy.

## Changing the schema

```bash
cd src/DigitalControlTower.Web
dotnet ef migrations add <Name> -o Data/Migrations
dotnet ef migrations script -i -o ../../db/01_schema.sql     # refresh the stand-alone script
```

To regenerate the seed script from a newer JSON export:

```bash
python3 tools/generate_seed_sql.py <export.json> db/02_seed_data.sql
```
