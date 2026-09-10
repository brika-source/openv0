# Corporate Sick Leave & Fit-to-Work Management System

A complete, runnable PHP application — the server-side rewrite of the two HTML
prototypes (`Requester_Portal_Employee_Manager` and
`Approver_Reviewer_Portal_Medical_HR_Admin`).

The prototypes were a single-page JavaScript mock-up in which all data, all
decisions and all access control lived in the browser. This is the same system
implemented properly: a real database, real accounts and passwords, real file
storage, server-enforced permissions, an audit trail, and every screen rendered
by the server.

Everything the prototypes did is here — the same eight screens, the same eleven
demo people, the same bilingual English/Arabic labels, the same visual design,
the same workflow rules.

---

## Quick start

Requirements: **PHP 8.1 or newer** with `pdo_sqlite` (bundled with PHP by
default). Nothing else — no Composer, no build step, no external packages.

```bash
php bin/console.php install          # create the database and demo data
php -S localhost:8000 -t public      # start the server
```

Open <http://localhost:8000> and pick a portal.

**Demo password for every account: `Passw0rd!`**

| Portal | Account | Role |
|---|---|---|
| Requester | Mona Fathy, Karim Adel, Salma Nabil, Youssef Hany, Rania Samir | Employee |
| Requester | Tarek Mostafa, Dina Hosny | Manager |
| Approver | Dr. Amira Fouad, Dr. Hossam Kamal | Medical Team / Occupational Health |
| Approver | Nourhan Adel | HR |
| Approver | System Admin | Admin |

Both portals run from the same installation against the same database, so a
request submitted in one appears immediately in the other — which is what the
two prototype files simulated with shared browser storage.

For Apache, nginx, MySQL or PostgreSQL, see **[INSTALL.md](INSTALL.md)**.

---

## What it does

### Module A — Sick leave

| Screen | Who | What |
|---|---|---|
| Submit Sick Leave (A2) | Employee | Diagnosis, dates, specialty, multiple document uploads |
| My Sick Leave Cases | Employee | Own cases, full timeline, resubmit when information is requested |
| Team Dashboard (8.3) | Manager | Direct reports' **status and dates only** — never a diagnosis |
| Medical Review Queue (A3) | Medical | Longest-waiting first, with SLA state; approve / pending / comment / reject |
| RTW Queue (A4) | Medical | Fit to Return · Return with Restrictions · Not Fit → extend under the same case id |

### Module B — Fit to work

| Screen | Who | What |
|---|---|---|
| Request FTW (B1) | Employee, Manager, HR, Medical | Diagnosis, medical history, job demands checklist, attachments |
| FTW Assessment Queue (B2) | Medical | Fit · Not Fit · Fit with Restrictions · Needs F2F · Needs Diagnostic Tests |

### Module C — Reports, audit and administration

- **Reports & Audit** — multi-select filters (employee, department, status,
  specialty) plus a date range, CSV export, and a printable per-case record.
- **Pattern Flags (8.4)** — informational only; never blocks a submission or an approval.
- **Restriction Expiry (8.6)** — restrictions from both FTW assessments and
  restricted returns to work, due or overdue.
- **Users & Roles** — create accounts, change roles, deactivate and reactivate.
- **Retention / SLA Settings** — retention period, SLA warning and escalation
  thresholds, annual entitlement.
- **Audit Trail** — every sign-in, decision, role change, settings change,
  export and download, append-only.

### Throughout

- **Bilingual** — every label is English and Arabic, exactly as in the prototypes.
- **Notifications** — the same fan-out rules (a person, a role, or one specific
  manager), now with per-user read state and an unread badge.
- **Patient Story** — one employee's whole sick leave and FTW history, shown on
  the review, RTW, assessment and profile screens.
- **SLA** — a case awaiting a medical decision is tracked against the warning
  and escalation thresholds from settings.

---

## How it is built

```
config/config.php        All settings; every value overridable by environment variable
app/
  bootstrap.php          Autoloader, configuration, error handling
  Config, Database       PDO wrapper (SQLite / MySQL / PostgreSQL)
  Schema, Seeder         Table creation and the demo data set
  Session, Csrf          Session hardening and CSRF tokens
  Domain                 Statuses, roles, portals, specialties, job demands
  Helpers, I18n          Formatting, escaping, the bilingual dictionary
  Lang/labels.php        326 label keys × 2 languages, ported from the prototypes
  Repo/                  Users, Cases, Ftw, Notifications, Settings, Audit, Sequences
  Service/               Auth, Access, SickLeave, FtwService, Analytics,
                         PatientStory, Reports, Uploads
  Http/                  Router, controllers, View, Layout, Request, Response, Url
resources/views/         Plain PHP templates, one per screen
public/                  index.php + assets — the only web-reachable directory
storage/                 Database file, uploads, logs (never web-reachable)
bin/console.php          install · migrate · seed · reset · status · passwd
tests/                   Four suites, 1,089 assertions
```

No framework and no dependencies: a 40-line autoloader, a small router, plain
PHP templates. Every query is a prepared statement; every template output is
escaped.

---

## Security

- **Passwords** hashed with `password_hash()` (bcrypt).
- **Server-enforced access control.** The router checks the portal, the CSRF
  token, the session and the role before any handler runs. A request cannot
  reach a screen its role is not entitled to.
- **Field-level confidentiality.** A manager sees status, dates and restrictions
  for their own direct reports — never a diagnosis, medical history or
  attachment. This is enforced by giving the manager templates a different data
  set, not by hiding markup.
- **CSRF** tokens on every state-changing request.
- **Session fixation** defeated by regenerating the session id on sign-in;
  sessions idle out after 45 minutes.
- **SQL injection** — prepared statements everywhere; `LIKE` patterns escaped so
  `%` and `_` are literal.
- **XSS** — every value escaped on output; a strict Content-Security-Policy
  (`script-src 'self'; style-src 'self'`) with no inline scripts, no inline
  styles and no inline event handlers.
- **Uploads** stored outside the web root under random names, type-checked by
  content sniffing rather than by the browser-supplied type, and served only
  through a controller that checks the caller's role.
- **Headers** — `X-Content-Type-Options`, `X-Frame-Options: DENY`,
  `Referrer-Policy`, `Cache-Control: no-store` (these are medical records).

---

## Tests

```bash
sh tests/run.sh
```

Starts its own server, runs everything, stops it again.

| Suite | Assertions | Covers |
|---|---|---|
| `tests/unit.php` | 169 | Dates, escaping, the label dictionary, entitlement, SLA, pattern flags, expiry, KPIs, notification targeting, sequences |
| `tests/security.php` | 120 | XSS payloads, SQL injection, CSRF forgery, session fixation, headers, direct file access |
| `tests/http.php` | 152 | Every workflow end to end over real HTTP with real sessions and uploads |
| `tests/sweep.php` | 648 | Every screen for every role: status, complete document, balanced markup, no PHP notices |

All 1,089 pass, and the run fails if anything reaches `storage/logs/php-error.log`.

---

## Console

```bash
php bin/console.php install                    # schema + demo data (safe to re-run)
php bin/console.php migrate                    # add missing tables, keep data
php bin/console.php reset                      # wipe and reload the demo data
php bin/console.php status                     # driver, row counts, settings
php bin/console.php passwd <email> <password>  # set a password
```

---

## Differences from the prototypes

Every one is a fix or an addition; nothing was dropped. See
[CHANGES-FROM-PROTOTYPE.md](CHANGES-FROM-PROTOTYPE.md) for the full list, the
most important being:

1. **Data lives on a server**, not in browser storage, so it survives, can be
   backed up, and is genuinely shared between the two portals.
2. **Accounts have passwords.** The prototypes let anyone pick any identity.
3. **Permissions are enforced server-side.** In the prototypes every decision
   function was a global JavaScript function any user could call.
4. **The confidentiality rule actually holds.** The prototypes' manager view
   linked to a modal that showed the full patient story, diagnosis included.
5. **Concurrent decisions are detected** — two reviewers can no longer
   overwrite one another silently.
6. **Uploads are real files**, not just remembered filenames.
7. **An audit trail** records who did what, when, and from where.
