#!/usr/bin/env python3
"""
Turn a Digital Control Tower JSON export into a T-SQL seed script.

Usage:
    python3 generate_seed_sql.py <export.json> <output.sql>

People are written as users identified by their handle (the mail alias, e.g.
"brika.wm"). Free-text owner names in the export are matched to those users, and
anyone not on file is added as a provisional user with a derived handle. The
mapping mirrors Data/JsonExportSeeder.cs and Data/PeopleDirectory.cs, so loading
the JSON through the web app and running this script give the same rows.
"""
import json
import re
import sys
import unicodedata
from datetime import datetime

ACTION_STATUS = {
    "notstarted": "NotStarted", "needsdefinition": "NeedsDefinition", "ontrack": "OnTrack",
    "atrisk": "AtRisk", "delayed": "Delayed", "complete": "Complete", "cancelled": "Cancelled",
}
PRIORITY = {"low": "Low", "medium": "Medium", "high": "High", "critical": "Critical"}
QUARTER = {"q1": "Q1", "q2": "Q2", "q3": "Q3", "q4": "Q4", "none": "None"}
RECURRENCE = {"none": "None", "weekly": "Weekly", "biweekly": "BiWeekly",
              "monthly": "Monthly", "quarterly": "Quarterly"}
PROJECT_STATUS = {"notstarted": "NotStarted", "active": "Active", "onhold": "OnHold",
                  "complete": "Complete", "cancelled": "Cancelled"}
PROJECT_SCOPE = {"local": "Local", "regional": "Regional", "global": "Global"}
WEEK_MARK = {"t": "T", "r": "R", "a": "A"}

# Spellings seen in the export that mean a person already on file.
KNOWN_ALIASES = {
    "mohamed ibrahim": "mostafaabdellatif.mi",
    "mohamed ibrahem": "mostafaabdellatif.mi",
    "mohamed": "mostafaabdellatif.mi",
    "shady barrage": "barrage.sm",
    "shady": "barrage.sm",
    "walaa brika": "brika.wm",
    "walaa": "brika.wm",
    "wala": "brika.wm",
    "hassan ismail": "ismail.he",
    "hassan": "ismail.he",
    "islam amer": "amer.is",
    "islam": "amer.is",
}
UNKNOWN_HANDLE = "unknown"

# Placeholders that appear where a name should be but name nobody.
NOT_PEOPLE = {"unknown", "n/a", "na", "none", "tbd", "tbc", "-", "--", "?"}


def norm(value):
    """Collapse an export label ("Not Started", "Bi-weekly") to a lookup key."""
    return re.sub(r"[ _-]", "", (value or "")).lower()


def enum(value, table, default=None):
    return table.get(norm(value), default)


def name_key(value):
    """Collapse spacing and case so "Hassan  ismail" matches "Hassan Ismail"."""
    return " ".join((value or "").split()).lower()


def strip_accents(value):
    decomposed = unicodedata.normalize("NFD", value or "")
    kept = [c for c in decomposed
            if unicodedata.category(c) != "Mn" and (c.isalnum() or c in " .-_")]
    return unicodedata.normalize("NFC", "".join(kept))


def handle_from_email(email):
    text = (email or "").strip()
    local = text.split("@", 1)[0] if "@" in text else text
    return local.strip().lower()


def handle_from_name(name):
    parts = [p for p in re.split(r"[ .\-_]+", strip_accents(name).strip()) if p]
    if not parts:
        return ""
    if len(parts) == 1:
        return parts[0].lower()
    return parts[-1].lower() + "." + "".join(p[0].lower() for p in parts[:-1])


class People:
    """Mirrors Data/PeopleDirectory.cs."""

    def __init__(self):
        self.by_handle = {}
        self.by_name = {}
        self.by_id = {}
        self.order = []

    def add(self, user):
        self.by_handle[user["handle"]] = user
        self.by_name[name_key(user["name"])] = user
        self.by_id[user["id"]] = user
        self.order.append(user)

    def resolve(self, name):
        text = (name or "").strip()
        if not text or text.lower() in NOT_PEOPLE:
            return None

        alias = KNOWN_ALIASES.get(name_key(text))
        if alias and alias in self.by_handle:
            return self.by_handle[alias]

        if name_key(text) in self.by_name:
            return self.by_name[name_key(text)]

        handle = handle_from_email(text) if "@" in text else handle_from_name(text)
        if not handle:
            return None
        if handle in self.by_handle:
            return self.by_handle[handle]

        created = {
            "id": f"user-prov-{len(self.order):03d}",
            "handle": handle,
            "name": text,
            "email": f"{handle}@pg.com",
            "provisional": True,
        }
        self.add(created)
        return created

    def resolve_many(self, value):
        if not value or not value.strip():
            return []
        out, seen = [], set()
        for part in re.split(r"[,/&;]", value):
            part = part.strip()
            if not part:
                continue
            user = self.resolve(part)
            if user and user["handle"] not in seen:
                seen.add(user["handle"])
                out.append(user)
        return out

    def handle_of(self, name, fallback):
        user = self.resolve(name)
        return user["handle"] if user else fallback


def q(value):
    """Quote a string as an NVARCHAR literal, or NULL when blank."""
    if value is None:
        return "NULL"
    text = str(value).strip()
    if text == "":
        return "NULL"
    return "N'" + text.replace("'", "''") + "'"


def qs(value, fallback):
    """Same as q() but never NULL - used for NOT NULL columns."""
    result = q(value)
    return result if result != "NULL" else q(fallback)


def date(value):
    text = (value or "").strip()
    if not text:
        return "NULL"
    try:
        return "'" + datetime.fromisoformat(text.replace("Z", "+00:00")).strftime("%Y-%m-%d") + "'"
    except ValueError:
        return "NULL"


GENERATED_AT = datetime.utcnow()


def stamp(value, fallback=None):
    """
    A datetime2 literal, keeping the milliseconds the export carries. Rows with no
    timestamp of their own are stamped with the import time, which is what the app's
    JSON importer does too.
    """
    text = (value or "").strip()
    moment = fallback or GENERATED_AT
    if text:
        try:
            moment = datetime.fromisoformat(text.replace("Z", "+00:00")).replace(tzinfo=None)
        except ValueError:
            pass
    return "'" + moment.strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "'"


def money(value):
    text = re.sub(r"[,$%\s]", "", str(value or ""))
    if not text:
        return "NULL"
    try:
        return str(float(text))
    except ValueError:
        return "NULL"


def insert(out, table, columns, rows, batch=200):
    """Emit INSERT statements in batches; SQL Server caps a VALUES list at 1000 rows."""
    if not rows:
        return
    out.append(f"PRINT 'Seeding {table} ({len(rows)} rows)...';")
    for start in range(0, len(rows), batch):
        chunk = rows[start:start + batch]
        out.append(f"INSERT INTO [{table}] ({', '.join('[' + c + ']' for c in columns)}) VALUES")
        out.append(",\n".join("    (" + ", ".join(row) + ")" for row in chunk) + ";")
    out.append("GO")
    out.append("")


def main(source, target):
    data = json.load(open(source, encoding="utf-8"))
    people = People()

    for user in data.get("users", []):
        uid = (user.get("id") or "").strip()
        if not uid or uid in people.by_id:
            continue
        email = user.get("email") or ""
        name = user.get("name") or uid
        handle = handle_from_email(email) or handle_from_name(name)
        people.add({"id": uid, "handle": handle, "name": name, "email": email, "provisional": False})

    week_dates, counters = [], []
    pillars, projects, items, actions = [], [], [], []
    owners, weeks, tags, history, comments = [], [], [], [], []

    for index, week in enumerate(data.get("weekDates", [])):
        if date(week) != "NULL":
            week_dates.append([str(index), date(week)])

    for name, value in (data.get("serials") or {}).items():
        counters.append([q(name), str(int(value or 0))])

    for order, pillar in enumerate(data.get("pillars", [])):
        pid = pillar.get("id") or f"pillar-{order}"
        pillars.append([q(pid), qs(pillar.get("name"), "Unnamed pillar"), str(order)])

        for project in pillar.get("projects", []):
            prid = project.get("id") or ""
            owner_id = (project.get("digitalOwner") or "").strip()
            pm = people.resolve(project.get("pm"))
            created = stamp(project.get("createdAt"))
            projects.append([
                q(prid), q(pid), qs(project.get("name"), "Unnamed project"),
                q(enum(project.get("scope"), PROJECT_SCOPE)),
                q(project.get("deptOwner")),
                q(owner_id) if owner_id in people.by_id else "NULL",
                q(pm["id"]) if pm else "NULL",
                q(enum(project.get("status"), PROJECT_STATUS, "NotStarted")),
                date(project.get("startDate")), date(project.get("dueDate")),
                date(project.get("baselineDate")), date(project.get("implementationDate")),
                date(project.get("actualCompletionDate")),
                money(project.get("costAvoidance")), money(project.get("laborHoursSaving")),
                money(project.get("productivityImprovement")),
                q(project.get("savingsType")), created, created,
            ])

            for item_order, item in enumerate(project.get("items", [])):
                iid = item.get("id") or ""
                items.append([
                    q(iid), q(prid), qs(item.get("name"), "Unnamed work item"),
                    q(item.get("notes")), str(item_order),
                ])
                for comment in item.get("comments", []):
                    comments.append([
                        q(comment.get("id")), "NULL", q(iid),
                        qs(people.handle_of(comment.get("author"), UNKNOWN_HANDLE), UNKNOWN_HANDLE),
                        qs(comment.get("text"), ""), stamp(comment.get("at")),
                    ])

                for action in item.get("actions", []):
                    aid = action.get("id") or ""
                    created = stamp(action.get("createdAt"))
                    actions.append([
                        q(aid), q(iid), qs(action.get("serial"), aid),
                        qs(action.get("name"), "Unnamed action"),
                        q(enum(action.get("status"), ACTION_STATUS, "NotStarted")),
                        q(enum(action.get("priority"), PRIORITY, "Medium")),
                        q(enum(action.get("quarter"), QUARTER, "None")),
                        q(enum(action.get("recurrence"), RECURRENCE, "None")),
                        qs(action.get("source"), "90-Day"),
                        q(action.get("target")), q(action.get("successCriteria")),
                        q(action.get("nextStep")), q(action.get("notes")), q(action.get("checkResult")),
                        date(action.get("reviewDate")), date(action.get("completionDate")),
                        created, stamp(action.get("updatedAt")) if action.get("updatedAt") else created,
                    ])

                    for owner in people.resolve_many(action.get("owner")):
                        owners.append([q(aid), q(owner["id"])])

                    for index, mark in (action.get("weeks") or {}).items():
                        marked = enum(mark, WEEK_MARK)
                        if marked and str(index).isdigit():
                            weeks.append([q(aid), str(int(index)), q(marked)])

                    for tag in dict.fromkeys(t.strip() for t in (action.get("tags") or []) if t.strip()):
                        tags.append([q(aid), q(tag)])

                    for entry in action.get("history", []):
                        history.append([
                            q(entry.get("id")), q(aid), qs(entry.get("field"), "unknown"),
                            q(entry.get("from")), q(entry.get("to")),
                            qs(people.handle_of(entry.get("by"), UNKNOWN_HANDLE), UNKNOWN_HANDLE),
                            stamp(entry.get("at")),
                        ])

                    for comment in action.get("comments", []):
                        comments.append([
                            q(comment.get("id")), q(aid), "NULL",
                            qs(people.handle_of(comment.get("author"), UNKNOWN_HANDLE), UNKNOWN_HANDLE),
                            qs(comment.get("text"), ""), stamp(comment.get("at")),
                        ])

    # Users are emitted last because provisional people are discovered while walking actions.
    users = [[q(u["id"]), q(u["handle"]), qs(u["name"], u["handle"]), qs(u["email"], u["handle"] + "@pg.com"),
              "1" if u["provisional"] else "0"] for u in people.order]

    out = [
        "/*",
        " * Digital Control Tower - seed data",
        f" * Generated from {source.split('/')[-1]} by tools/generate_seed_sql.py",
        " * Run 01_schema.sql first. Safe to re-run only on an empty database.",
        " */",
        "SET NOCOUNT ON;",
        "GO",
        "",
        "IF EXISTS (SELECT 1 FROM [Pillars])",
        "BEGIN",
        "    RAISERROR('Pillars already contains rows - clear the database before seeding.', 16, 1);",
        "    SET NOEXEC ON;",
        "END",
        "GO",
        "",
        "BEGIN TRANSACTION;",
        "GO",
        "",
    ]

    insert(out, "Users", ["Id", "Handle", "Name", "Email", "IsProvisional"], users)
    insert(out, "WeekDates", ["WeekIndex", "StartDate"], week_dates)
    insert(out, "SerialCounters", ["Name", "LastValue"], counters)
    insert(out, "Pillars", ["Id", "Name", "SortOrder"], pillars)
    insert(out, "Projects", [
        "Id", "PillarId", "Name", "Scope", "DeptOwner", "DigitalOwnerId", "PmId", "Status",
        "StartDate", "DueDate", "BaselineDate", "ImplementationDate", "ActualCompletionDate",
        "CostAvoidance", "LabourHoursSaving", "ProductivityImprovement", "SavingsType",
        "CreatedAt", "UpdatedAt"], projects)
    insert(out, "WorkItems", ["Id", "ProjectId", "Name", "Notes", "SortOrder"], items)
    insert(out, "Actions", [
        "Id", "WorkItemId", "Serial", "Name", "Status", "Priority", "Quarter",
        "Recurrence", "Source", "Target", "SuccessCriteria", "NextStep", "Notes", "CheckResult",
        "ReviewDate", "CompletionDate", "CreatedAt", "UpdatedAt"], actions)
    insert(out, "ActionOwners", ["ActionItemId", "UserId"], owners)
    insert(out, "ActionWeeks", ["ActionItemId", "WeekIndex", "Mark"], weeks)
    insert(out, "ActionTags", ["ActionItemId", "Tag"], tags)
    insert(out, "ActionHistories", ["Id", "ActionItemId", "Field", "FromValue", "ToValue", "By", "At"], history)
    insert(out, "Comments", ["Id", "ActionItemId", "WorkItemId", "Author", "Text", "At"], comments)

    out += ["COMMIT TRANSACTION;", "GO", "", "SET NOEXEC OFF;", "GO", ""]

    with open(target, "w", encoding="utf-8") as handle:
        handle.write("\n".join(out))

    provisional = sum(1 for u in people.order if u["provisional"])
    print(f"{target}: {len(users)} users ({provisional} provisional), {len(pillars)} pillars, "
          f"{len(projects)} projects, {len(items)} work items, {len(actions)} actions, "
          f"{len(owners)} owner links, {len(weeks)} week marks, {len(history)} history rows, "
          f"{len(comments)} comments.")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
