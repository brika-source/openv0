#!/usr/bin/env python3
"""
Turn a Digital Control Tower JSON export into a T-SQL seed script.

Usage:
    python3 generate_seed_sql.py <export.json> <output.sql>

The mapping matches Data/JsonExportSeeder.cs, so loading the JSON through the
web app and running this script give the same rows.
"""
import json
import re
import sys
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


def norm(value):
    """Collapse an export label ("Not Started", "Bi-weekly") to a lookup key."""
    return re.sub(r"[ _-]", "", (value or "")).lower()


def enum(value, table, default=None):
    return table.get(norm(value), default)


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


def stamp(value, fallback="1900-01-01T00:00:00"):
    text = (value or "").strip()
    if not text:
        text = fallback
    try:
        return "'" + datetime.fromisoformat(text.replace("Z", "+00:00")).strftime("%Y-%m-%dT%H:%M:%S") + "'"
    except ValueError:
        return "'" + fallback + "'"


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

    users, week_dates, counters = [], [], []
    pillars, projects, items, actions = [], [], [], []
    weeks, tags, history, comments = [], [], [], []
    user_ids = set()

    for user in data.get("users", []):
        uid = (user.get("id") or "").strip()
        if not uid or uid in user_ids:
            continue
        user_ids.add(uid)
        users.append([q(uid), qs(user.get("name"), uid), qs(user.get("email"), uid + "@local")])

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
            owner = (project.get("digitalOwner") or "").strip()
            created = stamp(project.get("createdAt"))
            projects.append([
                q(prid), q(pid), qs(project.get("name"), "Unnamed project"),
                q(enum(project.get("scope"), PROJECT_SCOPE)),
                q(project.get("deptOwner")),
                q(owner) if owner in user_ids else "NULL",
                q(project.get("pm")),
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
                        qs(comment.get("author"), "Unknown"), qs(comment.get("text"), ""),
                        stamp(comment.get("at")),
                    ])

                for action in item.get("actions", []):
                    aid = action.get("id") or ""
                    created = stamp(action.get("createdAt"))
                    actions.append([
                        q(aid), q(iid), qs(action.get("serial"), aid),
                        qs(action.get("name"), "Unnamed action"), q(action.get("owner")),
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
                            qs(entry.get("by"), "Unknown"), stamp(entry.get("at")),
                        ])

                    for comment in action.get("comments", []):
                        comments.append([
                            q(comment.get("id")), q(aid), "NULL",
                            qs(comment.get("author"), "Unknown"), qs(comment.get("text"), ""),
                            stamp(comment.get("at")),
                        ])

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

    insert(out, "Users", ["Id", "Name", "Email"], users)
    insert(out, "WeekDates", ["WeekIndex", "StartDate"], week_dates)
    insert(out, "SerialCounters", ["Name", "LastValue"], counters)
    insert(out, "Pillars", ["Id", "Name", "SortOrder"], pillars)
    insert(out, "Projects", [
        "Id", "PillarId", "Name", "Scope", "DeptOwner", "DigitalOwnerId", "Pm", "Status",
        "StartDate", "DueDate", "BaselineDate", "ImplementationDate", "ActualCompletionDate",
        "CostAvoidance", "LabourHoursSaving", "ProductivityImprovement", "SavingsType",
        "CreatedAt", "UpdatedAt"], projects)
    insert(out, "WorkItems", ["Id", "ProjectId", "Name", "Notes", "SortOrder"], items)
    insert(out, "Actions", [
        "Id", "WorkItemId", "Serial", "Name", "Owner", "Status", "Priority", "Quarter",
        "Recurrence", "Source", "Target", "SuccessCriteria", "NextStep", "Notes", "CheckResult",
        "ReviewDate", "CompletionDate", "CreatedAt", "UpdatedAt"], actions)
    insert(out, "ActionWeeks", ["ActionItemId", "WeekIndex", "Mark"], weeks)
    insert(out, "ActionTags", ["ActionItemId", "Tag"], tags)
    insert(out, "ActionHistories", ["Id", "ActionItemId", "Field", "FromValue", "ToValue", "By", "At"], history)
    insert(out, "Comments", ["Id", "ActionItemId", "WorkItemId", "Author", "Text", "At"], comments)

    out += ["COMMIT TRANSACTION;", "GO", "", "SET NOEXEC OFF;", "GO", ""]

    with open(target, "w", encoding="utf-8") as handle:
        handle.write("\n".join(out))

    print(f"{target}: {len(pillars)} pillars, {len(projects)} projects, {len(items)} work items, "
          f"{len(actions)} actions, {len(weeks)} week marks, {len(history)} history rows, "
          f"{len(comments)} comments, {len(users)} users.")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
