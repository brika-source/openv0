# Digital Control Tower — installation

For the server owner. Everything is in this package; nothing is downloaded during install.

Two files decide the install: **`appsettings.Production.json`** (connection string, mail)
and the two scripts in **`db/`**. Nothing else needs editing.

---

## 1. What the server needs

| | |
|---|---|
| Windows Server | 2016 or newer (the app also runs on Linux — see §7) |
| SQL Server | 2016 or newer, any edition including Express |
| Runtime | **ASP.NET Core 8 Hosting Bundle** — <https://dotnet.microsoft.com/download/dotnet/8.0> → "Hosting Bundle" |

The Hosting Bundle installs the .NET runtime and the IIS module. Install it before
continuing, and restart IIS afterwards (`iisreset`) if the site will run under IIS.

If installing a runtime is not allowed on the box, run `publish-self-contained.ps1` on any
machine with the .NET 8 **SDK** instead: it produces a folder that carries its own runtime.

## 2. Create the database

In SQL Server Management Studio, or with `sqlcmd`:

```sql
CREATE DATABASE DigitalControlTower;
```

Then create the tables, and load the existing tracker data:

```cmd
sqlcmd -S <server> -d DigitalControlTower -i db\01_schema.sql
sqlcmd -S <server> -d DigitalControlTower -i db\02_seed_data.sql
```

`01_schema.sql` can be run again safely — it skips anything already there.
`02_seed_data.sql` refuses to run if the database already has data, so it can never
duplicate the 6 pillars / 41 projects / 98 work items / 297 actions it loads.

Give the account the site runs under `db_datareader`, `db_datawriter` and `EXECUTE` on
that database. A domain service account with Windows authentication is the usual choice.

## 3. Point the app at the database

Open `app\appsettings.Production.json` and set the connection string:

```json
"ConnectionStrings": {
  "DefaultConnection": "Server=SQLHOST\\INSTANCE;Database=DigitalControlTower;Trusted_Connection=True;TrustServerCertificate=True;MultipleActiveResultSets=true"
}
```

For SQL authentication instead of Windows:

```
Server=SQLHOST;Database=DigitalControlTower;User Id=dct_app;Password=…;TrustServerCertificate=True;MultipleActiveResultSets=true
```

## 4. Turn on the reminder mails (Outlook)

Owners are mailed **two days before an action is due**, and anything already overdue
rides along in the same mail. Set this in `app\appsettings.Production.json`:

```json
"Email": {
  "Enabled": true,
  "Host": "smtp.office365.com",
  "Port": 587,
  "UseStartTls": true,
  "UserName": "digital.controltower@pg.com",
  "Password": "…",
  "FromAddress": "digital.controltower@pg.com",
  "FromName": "Digital Control Tower",
  "RedirectAllTo": ""
},
"Reminders": {
  "Enabled": true,
  "DaysBeforeDue": 2,
  "RunAt": "07:00",
  "IncludeOverdue": true
}
```

- On an **internal relay** that authorises by IP, leave `UserName` and `Password` empty.
- Put a single address in `RedirectAllTo` to send every reminder to one mailbox while you
  test; clear it when you are happy.
- Leave `Email.Enabled` as `false` and the app still works — it just does not send.

Check it from the site itself: **Reports → Reminders** lists exactly what the next run will
send and has a **Run now** button, then shows the result of each attempt.

## 5. Run it

**As a Windows service** (simplest, no IIS):

```powershell
# from an elevated PowerShell in this folder
.\install-windows-service.ps1 -InstallPath "C:\Apps\DigitalControlTower" -Port 8080
```

The script copies `app\` to the install path, registers the service
`DigitalControlTower`, sets it to start automatically and starts it.
Open `http://<server>:8080`.

**Under IIS** instead:

1. Copy `app\` to e.g. `C:\inetpub\DigitalControlTower`.
2. New Application Pool: .NET CLR version **No Managed Code**, identity = your service account.
3. New Site pointing at that folder, bound to a port or host name, using that pool.
4. Give the pool identity read access to the folder.

`web.config` is already in `app\` — IIS needs nothing else.

**To try it before installing anything**, just run it:

```cmd
cd app
dotnet DigitalControlTower.Web.dll --urls http://0.0.0.0:8080
```

## 6. Check it worked

1. Open the site — the dashboard shows 6 pillars and 297 actions.
2. **Digital team meeting** on the home page opens today's meeting.
3. **Reports → Value added** totals the business value.
4. **Reports → Reminders** shows the reminder queue.

If the page shows an error instead, the log says why: Windows **Event Viewer → Application**
for the service, or `logs\stdout` under IIS once `stdoutLogEnabled` is set to `true` in
`web.config`.

## 7. Linux instead of Windows

Same package. Install the ASP.NET Core 8 runtime, copy `app/`, and run it behind nginx
with a systemd unit:

```ini
[Service]
WorkingDirectory=/opt/dct/app
ExecStart=/usr/bin/dotnet /opt/dct/app/DigitalControlTower.Web.dll --urls http://0.0.0.0:8080
Environment=ASPNETCORE_ENVIRONMENT=Production
Restart=always
User=dct
```

## 8. Upgrading later

Stop the service, replace the contents of `app\` (keep your
`appsettings.Production.json`), run any new script in `db\`, start the service.

## 9. Support notes

- Times are the server's local time; the reminder run at `RunAt` uses it.
- The app never deletes data on start-up. `Database:AutoMigrate` is **off** in production:
  the SQL scripts are the only thing that touches the schema.
- Everyone is identified by their mail alias (`brika.wm`), which is also where the
  reminder is sent. Check **Users** for anyone badged *provisional* — those addresses were
  derived from the old free-text owner names and should be confirmed.
