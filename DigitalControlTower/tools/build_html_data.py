#!/usr/bin/env python3
"""
Builds the dataset embedded in the single-file HTML edition of the control tower.

Usage:
    python3 build_html_data.py <export.json> <output.json>

It reuses the mapping in generate_seed_sql.py — handles, resolved owners, rolled-up
project and pillar on every action — so the HTML file holds exactly what the database does.
"""
import json
import sys
from datetime import datetime

from generate_seed_sql import (ACTION_STATUS, PRIORITY, PROJECT_SCOPE, PROJECT_STATUS,
                               QUARTER, RECURRENCE, WEEK_MARK, People, enum, savings_type)


def money(value):
    text = str(value or "").replace(",", "").replace("$", "").replace("%", "").strip()
    try:
        return float(text)
    except ValueError:
        return None


def date(value):
    text = (value or "").strip()
    if not text:
        return None
    try:
        return datetime.fromisoformat(text.replace("Z", "+00:00")).strftime("%Y-%m-%d")
    except ValueError:
        return None


def stamp(value):
    text = (value or "").strip()
    if not text:
        return None
    try:
        return datetime.fromisoformat(text.replace("Z", "+00:00")).strftime("%Y-%m-%dT%H:%M:%S")
    except ValueError:
        return None


def clean(value):
    text = (value or "").strip()
    return text or None


def main(source, target):
    export = json.load(open(source, encoding="utf-8"))
    people = People()

    for user in export.get("users", []):
        uid = (user.get("id") or "").strip()
        if not uid or uid in people.by_id:
            continue
        email = user.get("email") or ""
        name = user.get("name") or uid
        from generate_seed_sql import handle_from_email, handle_from_name
        people.add({"id": uid, "handle": handle_from_email(email) or handle_from_name(name),
                    "name": name, "email": email, "provisional": False})

    pillars, projects, items, actions = [], [], [], []

    for order, pillar in enumerate(export.get("pillars", [])):
        pid = pillar.get("id") or f"pillar-{order}"
        pillars.append({"id": pid, "name": clean(pillar.get("name")) or "Unnamed pillar", "sort": order})

        for project in pillar.get("projects", []):
            prid = project.get("id") or ""
            owner = (project.get("digitalOwner") or "").strip()
            pm = people.resolve(project.get("pm"))
            projects.append({
                "id": prid, "pillarId": pid,
                "name": clean(project.get("name")) or "Unnamed project",
                "scope": enum(project.get("scope"), PROJECT_SCOPE),
                "deptOwner": clean(project.get("deptOwner")),
                "ownerId": owner if owner in people.by_id else None,
                "pmId": pm["id"] if pm else None,
                "status": enum(project.get("status"), PROJECT_STATUS, "NotStarted"),
                "start": date(project.get("startDate")), "due": date(project.get("dueDate")),
                "baseline": date(project.get("baselineDate")),
                "impl": date(project.get("implementationDate")),
                "actual": date(project.get("actualCompletionDate")),
                "money": money(project.get("costAvoidance")),
                "hours": money(project.get("laborHoursSaving")),
                "productivity": money(project.get("productivityImprovement")),
                "savingsType": savings_type(project.get("savingsType")),
                "valueNotes": None,
            })

            for item_order, item in enumerate(project.get("items", [])):
                iid = item.get("id") or ""
                items.append({"id": iid, "projectId": prid,
                              "name": clean(item.get("name")) or "Unnamed work item",
                              "notes": clean(item.get("notes")), "sort": item_order})

                for action in item.get("actions", []):
                    aid = action.get("id") or ""
                    created = stamp(action.get("createdAt"))
                    actions.append({
                        "id": aid, "workItemId": iid, "projectId": prid, "pillarId": pid,
                        "meetingId": None, "relatedActionId": None, "origin": "Plan",
                        "serial": clean(action.get("serial")) or aid,
                        "name": clean(action.get("name")) or "Unnamed action",
                        "ownerIds": [o["id"] for o in people.resolve_many(action.get("owner"))],
                        "status": enum(action.get("status"), ACTION_STATUS, "NotStarted"),
                        "priority": enum(action.get("priority"), PRIORITY, "Medium"),
                        "quarter": enum(action.get("quarter"), QUARTER, "None"),
                        "recurrence": enum(action.get("recurrence"), RECURRENCE, "None"),
                        "source": clean(action.get("source")) or "90-Day",
                        "target": clean(action.get("target")),
                        "success": clean(action.get("successCriteria")),
                        "next": clean(action.get("nextStep")),
                        "notes": clean(action.get("notes")),
                        "check": clean(action.get("checkResult")),
                        "due": date(action.get("reviewDate")),
                        "completion": date(action.get("completionDate")),
                        "createdAt": created,
                        "weeks": {str(int(k)): enum(v, WEEK_MARK)
                                  for k, v in (action.get("weeks") or {}).items()
                                  if str(k).isdigit() and enum(v, WEEK_MARK)},
                        "tags": [t.strip() for t in (action.get("tags") or []) if t.strip()],
                        "history": [{
                            "id": h.get("id"), "field": h.get("field"),
                            "from": clean(h.get("from")), "to": clean(h.get("to")),
                            "by": people.handle_of(h.get("by"), "unknown"),
                            "at": stamp(h.get("at")),
                        } for h in action.get("history", [])],
                        "comments": [{
                            "id": c.get("id"),
                            "author": people.handle_of(c.get("author"), "unknown"),
                            "text": c.get("text") or "", "at": stamp(c.get("at")),
                        } for c in action.get("comments", [])],
                    })

    data = {
        "generatedAt": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "weekDates": [d for d in (date(w) for w in export.get("weekDates", [])) if d],
        "users": [{"id": u["id"], "handle": u["handle"], "name": u["name"],
                   "email": u["email"], "provisional": u["provisional"]} for u in people.order],
        "pillars": pillars,
        "projects": projects,
        "items": items,
        "actions": actions,
        "meetings": [],
        "serials": {"action": int((export.get("serials") or {}).get("action", len(actions))),
                    "meeting": int((export.get("serials") or {}).get("meeting", 0))},
    }

    with open(target, "w", encoding="utf-8") as handle:
        json.dump(data, handle, ensure_ascii=False, separators=(",", ":"))

    print(f"{target}: {len(data['users'])} users, {len(pillars)} pillars, {len(projects)} projects, "
          f"{len(items)} work items, {len(actions)} actions, "
          f"{sum(len(a['ownerIds']) for a in actions)} owner links, "
          f"{sum(len(a['weeks']) for a in actions)} week marks")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
