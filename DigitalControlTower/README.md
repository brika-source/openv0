# Digital Control Tower

ASP.NET Core 8 MVC application for the Digital Control Tower, backed by SQL Server through
Entity Framework Core. It replaces the browser-local JSON tracker with a shared database:
pillars → projects → work items → actions, a 14-week tracking grid, comments and an audit trail.

**For the server owner: everything needed to install is in `deploy/INSTALL.md`.**
**To look at the tower without installing anything: open `standalone/digital_control_tower_2126_5.html`.**

**People are identified by their mail alias — the handle.** Walaa Brika is `brika.wm`,
Hassan Ismail is `ismail.he`. Action owners, project managers, digital owners, comment
authors and audit entries all use that handle rather than a typed-in name, so the same
person is never split across "Hassan Ismail", "Hassan ismail" and "hassan" again.

## What the app does

| Screen | What it is for |
|---|---|
| **Dashboard** | KPIs, pillar progress, what needs attention — plus **Workload by owner**, **Projects over time**, today's **Digital team meeting**, the value delivered and the reminder queue |
| **Meetings action plan** | Every digital team meeting: participants, minutes, and the actions raised. Opens straight on today's date |
| **Pillars / Projects / Actions** | The 90-day plan, searchable and filterable, with comments and a full audit trail |
| **Week grid** | The 14-week tracking matrix; click a cell to cycle blank → T → R → A |
| **Reports → Value added** | What the department delivered: hard vs soft saving, by pillar, by owner |
| **Reports → What is missing** | Actions with no due date or owner, overdue actions, projects with no value, empty projects and work items |
| **Reports → Reminders** | What the next reminder run will send, a Run now button, and the log of what went out |
| **Users** | Handles, names and addresses — the addresses the reminders go to |

## What is in here

```
DigitalControlTower.sln
deploy/INSTALL.md                  Server owner's install guide (start here to deploy)
deploy/install-windows-service.ps1 Installs the published app as a Windows service
deploy/publish-self-contained.ps1  Builds a copy that carries its own .NET runtime
src/DigitalControlTower.Web/       ASP.NET Core MVC app (controllers, views, EF Core model)
  Models/                          Entities and enums
  Data/ApplicationDbContext.cs     Mapping, indexes, constraints, audit hook, context roll-up
  Data/JsonExportSeeder.cs         Imports a JSON export into an empty database
  Data/PeopleDirectory.cs          Maps legacy free-text names onto users with handles
  Services/UserHandle.cs           Derives and normalises handles
  Services/ReminderService.cs      Works out and sends the "due in two days" mails
  Services/IEmailSender.cs         SMTP delivery for Outlook/Exchange
  Data/Migrations/                 EF Core Code-First migrations
  Data/seed/                       The JSON export this database was modelled on
standalone/digital_control_tower_2126_5.html  The whole tower in one HTML file — open it, no server needed
db/01_schema.sql                   Stand-alone schema script (idempotent, generated from the migration)
db/02_seed_data.sql                Stand-alone seed script generated from the JSON export
tools/generate_seed_sql.py         Regenerates 02_seed_data.sql from any newer JSON export
tools/build_html_data.py           Builds the dataset the single-file edition carries
tools/refresh_html_data.py         Swaps that dataset into standalone/digital_control_tower_2126_5.html
tools/verify_seed_equivalence.py   Checks the SQL scripts and the importer load identical data
```

## Data model

| Table | Holds | Notes |
|---|---|---|
| `Pillars` | Value Protection, Value Creation, Capability, General, Automation, Proficy | `SortOrder` drives display order |
| `Projects` | Project record with owners, dates and savings figures | FK to `Pillars`, optional FK to `Users` |
| `WorkItems` | Workstreams inside a project | Cascade delete from `Projects` |
| `Actions` | The tracked actions (`ACT-0001` …) | Unique `Serial`; `WorkItemId` optional, `ProjectId`/`PillarId` carry the rolled-up context, `MeetingId` and `RelatedActionId` link it back to where it came from |
| `Meetings` | Digital team meetings (`MTG-0001` …), with the minutes | One per date by default, reachable by date |
| `MeetingParticipants` | Who attended | Unique on (`MeetingId`, `UserId`) |
| `ReminderLogs` | Every reminder mail, and whether it was delivered | |
| `ActionOwners` | Which people own an action | Unique on (`ActionItemId`, `UserId`); an action may have several owners |
| `ActionWeeks` | One row per marked cell of the 14-week grid | Unique on (`ActionItemId`, `WeekIndex`), mark `T`/`R`/`A` |
| `ActionTags` | Free-text tags per action | Unique on (`ActionItemId`, `Tag`) |
| `ActionHistories` | Audit trail (field, from, to, by, at) | Written automatically on save |
| `Comments` | Comments on an action **or** a work item | Check constraint enforces exactly one parent |
| `WeekDates` | Week index → week start date | The 14-week tracking horizon |
| `Users` | Everyone referenced anywhere in the tower | Unique `Handle` and `Email`; `IsProvisional` flags a guessed address |
| `SerialCounters` | Last issued number per serial family | Seeded from the export (`action` = 385) |

Enums (`Status`, `Priority`, `Quarter`, `Recurrence`, `Scope`, `Mark`) are stored as strings such as
`NotStarted`, `OnTrack`, `AtRisk`, so the tables stay readable in T-SQL. Dates use `date`,
timestamps use `datetime2`, and money/hours columns use `decimal(18,2)`.

`Comments.Author` and `ActionHistories.By` store the handle as text, so the record survives
someone leaving; every other person reference is a foreign key to `Users`. Those links are
`NO ACTION` rather than cascading, because SQL Server allows only one cascading path between
a pair of tables — `UsersController` clears them when a person is deleted.

## Meetings that feed the master plan

**Digital team meeting** on the home page opens today's meeting, creating it the first
time. The date can be changed, participants are picked from the handles in `Users`, and
the minutes are free text.

Actions raised in a meeting are **ordinary actions in the same table**, so they appear in
the action list, the week grid, the reports and the reminder run like any other. They
always start *Not Started*, and each one can be tied to:

- **a pillar** — rolls up to that pillar,
- **a project** — rolls up to that project and its pillar,
- **a 90-day work item** — filed in the plan exactly like a plan action,
- **another action** — recorded as a follow-on, inheriting that action's context.

Every action carries `ProjectId` and `PillarId` of its own, kept in step with its work item
by the context, which is why a meeting action attached only to a pillar still rolls up
everywhere. Deleting a meeting keeps its actions; the link is cleared.

## Reminders

Owners are mailed **two days before an action is due**, with anything already overdue in
the same mail. The sweep runs daily at `Reminders:RunAt`, and an action is reminded once
per due date — moving the date re-arms it. `Reports → Reminders` shows what the next run
will send, sends it on demand, and logs every attempt.

Configure the relay under `Email` in `appsettings.Production.json` (see
`deploy/INSTALL.md` §4). With `Email:Enabled` false the run still works out and records
who would be mailed, which is a safe way to try it.

## Value added

Each project carries a **value type** (hard saving, soft saving, cost avoidance, other),
an annual financial value, labour hours saved, a productivity percentage and a note on how
the figure was arrived at. `Reports → Value added` totals that by type, by pillar and by
owner, and flags the projects where nothing has been captured yet.

`Reports → What is missing` is the other half of that: actions with no due date (so nobody
is reminded), actions with no owner, overdue actions, projects with no value, projects
with no owner, projects with no work items, and work items with no actions. Each row links
straight to the screen that fixes it.

### How people are matched when importing

The export holds free text where a person belongs. The importer resolves it like this:

1. A name that matches a user on file, including the known spellings (`Mohamed ibrahem`,
   `Hassan ismail`, `wala`, `Islam`), maps to that user's handle.
2. `Islam Amer, Walaa Brika` and `mohamed / hassan` become two owners on the same action.
3. `Unknown`, `n/a`, `tbd` and similar are not treated as people.
4. Anything left is added as a **provisional** user with a derived handle — `Ahmed Morgan`
   becomes `morgan.a`, `Randa` becomes `randa` — and `<handle>@pg.com` as a placeholder
   address. They are badged *provisional* on the Users page; fix the address there, and
   merge any duplicates the old free text left behind (`morgan` vs `morgan.a`).

On the supplied export that turns 20 spellings into 14 people: 105 "Islam Amer" plus 2
"Islam" become 109 actions for `amer.is`, and 66 "Hassan Ismail" plus 23 "Hassan ismail"
plus 11 shared "mohamed / hassan" become 100 for `ismail.he`.

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

Then open the URL printed in the console. Pages: **Dashboard** (KPIs, pillar progress, load per handle,
what needs attention, recent changes), **Pillars**, **Projects** (filter by pillar/status, project
detail with its work items and actions), **Actions** (search + filter by handle + paging, detail
with comments, history and its week strip), **Week grid** (click a cell to cycle blank → T → R →
A → blank), and **Users** (handles, full names, addresses).

The "Acting as" box in the header takes a handle and is what gets recorded on comments and
audit rows. Replace `HttpContextCurrentUser` in `Services/ICurrentUser.cs` with Windows or
Entra ID authentication when you deploy: the alias inside `DOMAIN\brika.wm` or
`brika.wm@pg.com` is already reduced to the handle there.

Owner filters use the handle, so a filtered list has a readable URL:
`/Actions?Owner=brika.wm`.

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
