# What changed from the HTML prototypes

The two source files —
`Approver_Reviewer_Portal_Medical_HR_Admin_3.html` and
`Requester_Portal_Employee_Manager_3.html` — were byte-identical apart from one
line (`const PORTAL_TYPE = 'approver' | 'requester'`). Both are implemented here
as two entry points into a single application.

**Nothing was removed.** Every screen, rule, label and visual detail is present.
This is the list of what was fixed or added on top.

---

## Faithfully preserved

- All 326 label keys in both English and Arabic, converted mechanically from the
  source rather than retyped (`app/Lang/labels.php`).
- The complete stylesheet, copied verbatim, then extended.
- The eleven demo people, eight sick leave cases, two FTW assessments and six
  notifications, with the same relative dates.
- Every workflow rule, including the ones that look like quirks and are
  deliberate:
  - approving with *no* RTW required closes the case immediately;
  - "Not Fit — Extend" keeps the **same case id** and appends an extension;
  - pattern flags are informational and never block anything;
  - a case in `Returned – With Restrictions` does not count against entitlement.
- The tab set for each of the five roles.
- Notification fan-out targets: a person (`U1`), a role (`role:medical`), or one
  specific manager (`role:manager:U6`).

---

## Fixed

### 1. Data lived in the browser
`localStorage` / `window.storage` under one key, with a 4-second polling loop.
Clearing browser data destroyed everything, and "sharing" between portals only
worked inside one browser profile.

**Now:** a real database (SQLite by default; MySQL and PostgreSQL supported),
properly normalised across fifteen tables, with indexes and foreign-key-shaped
relationships. Both portals read and write the same rows.

### 2. Anyone could be anyone
The login screen was a dropdown of accounts with no password.

**Now:** bcrypt password hashes, sign-in by user id or e-mail, account
activation state, self-service password change, and session regeneration on
sign-in.

### 3. Access control was decorative
`doApprove()`, `changeRole()` and every other decision were global JavaScript
functions. Any user could call any of them from the console, and `TABS_BY_ROLE`
only decided which buttons were drawn.

**Now:** the router checks portal, CSRF token, session and role before a handler
runs; services re-check the caller's relationship to the record. The test suite
asserts that HR cannot approve, that an employee cannot record an FTW outcome,
that a manager cannot open another manager's report's case, and that a
non-admin cannot change roles.

### 4. The confidentiality rule was stated but not applied
The manager's team dashboard displayed a banner reading *"Diagnosis and medical
detail are confidential to Occupational Health. This view shows status and dates
only, per field-level access control"* — and then linked "view restriction" to
`openReviewCase()`, the same modal the medical team used, showing the diagnosis,
the full patient story and every document.

**Now:** managers get their own templates
(`manager/case-restriction`, `manager/ftw-restriction`) that are handed only the
restriction text, its review date, the status and the dates. The diagnosis never
reaches the template, and attachments are refused to managers by the download
controller. A test asserts the manager's pages contain no diagnosis string.

### 5. Two reviewers could silently overwrite each other
There was no check that a case was still awaiting a decision.

**Now:** every transition re-reads the case and refuses if it has already been
actioned, with a clear message. The whole transition — status, history entry,
documents, notifications — runs in one database transaction.

### 6. Uploads were filenames only
`f.name` was stored; the file itself was discarded.

**Now:** files are written to `storage/uploads` (outside the web root) under
random names, validated by size, extension and sniffed content type, and served
only through a controller that checks the caller's role. Re-uploading the same
filename still creates version 2, as before.

### 7. No input validation
Any values were accepted, including a To-date before the From-date.

**Now:** dates are checked for validity, ordering and plausibility; required
fields are enforced server-side; numeric settings are clamped to sane ranges;
an SLA escalation threshold below the warning threshold is corrected.

### 8. Printing used `document.write` into a pop-up
Blocked by default in most browsers.

**Now:** a real printable page at its own URL, styled for paper, that the
browser prints or saves as PDF.

### 9. Mixed-language sentences were unreadable
Composed strings concatenated bilingual fragments, producing
`Escalated — / تم التصعيد — 52h unactioned (lead notified) / ساعة بدون إجراء…`,
which interleaves both languages and both text directions inside one line.

**Now:** `I18n::sentence()` composes each language separately and stacks them:

```
Escalated — 52h unactioned (lead notified)
تم التصعيد — 52 ساعة بدون إجراء (تم إبلاغ رئيس الفريق)
```

### 10. Layout depended on `white-space: pre-line`
That was how the stacked English/Arabic labels got their line break — but it
also renders every newline in the markup as a blank line.

**Now:** the label helper emits a real `<br>` after escaping, and the fragile
rule is gone. Inline `style=""` attributes were likewise moved into classes, so
a strict `style-src 'self'` policy holds with no exceptions.

---

## Added

| Addition | Why |
|---|---|
| **Audit trail** (`audit_log` + Admin tab) | Occupational-health records need a defensible who-did-what-when. Records sign-ins, decisions, role changes, settings changes, exports and downloads. |
| **Notification read state** | The prototype's badge showed the total count forever. Now unread is tracked per user and clears when the inbox is opened. |
| **User administration** | Create accounts, deactivate and reactivate. An admin cannot demote or deactivate themselves. |
| **Password management** | Self-service change, plus `php bin/console.php passwd`. |
| **CSV export improvements** | UTF-8 BOM so Excel opens Arabic correctly, strict RFC 4180 quoting, and extra columns (employee id, total days, extension count). |
| **Bookmarkable filtered reports** | Filters are a GET form, so a filtered view is a URL, and the CSV button exports exactly what is on screen. |
| **Console tool** | `install`, `migrate`, `seed`, `reset`, `status`, `passwd`, `roles`. |
| **Four test suites** | 1,089 assertions covering the rules, the workflows, every screen and the security boundaries. |
| **Security headers and CSP** | No inline script, style or event handler anywhere. |
| **Works without JavaScript** | Every screen renders and every workflow completes with scripting disabled; the JS file only adds toasts, confirmations, filter counters and panel toggles. |
| **Portal landing page** | The prototypes were two separate files; this is one installation, so a chooser page routes to either portal and shows which are already signed in. |

---

## Behavioural notes worth knowing

- **One browser, both portals.** The session holds one login per portal, so a
  reviewer and a requester can be signed in side by side in two tabs — the
  closest equivalent of having both prototype files open.
- **Timestamps are stored in one timezone** (`APP_TIMEZONE`, UTC by default) as
  `Y-m-d H:i:s` strings, and dates as `Y-m-d`. Both sort correctly as strings on
  every supported database, and all date arithmetic happens in PHP, so there is
  no driver-specific date behaviour anywhere.
- **Seeded documents carry metadata only.** The prototypes never had real files,
  so the eight seeded document rows have no file behind them; the UI lists them
  without a download link, and the download route returns 404 rather than
  erroring. Anything uploaded through the running application is a real file.
