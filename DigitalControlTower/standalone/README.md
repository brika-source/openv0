# Single-file edition — your own UI

`digital_control_tower_2126_5.html` is the board you sent (`…_2126_4.html`), with the new
features added **inside it**. Nothing about the existing design was changed: the topbar,
hero, award banner, pillar cards, tabs, tables and both themes are the same file's CSS —
718 CSS lines untouched, 21 added, all built from its own tokens.

## What was added

| | |
|---|---|
| **Handles** | People are shown by their mail alias — `brika.wm`, `ismail.he`. The old free-text spellings ("Hassan ismail", "hassan") resolve to one handle, and owner pickers list handles |
| **Meetings Action Plan** (new tab, 🤝) | The digital team's own meetings. Opens straight on today's date, participants are ticked from the team, minutes live with the meeting |
| **Digital team meeting** (home card) | One click from the dashboard to today's meeting |
| **Meeting actions** | Start as *Not Started*, take a due date, and relate to a pillar, a project, a master plan item or another action — then they are filed there, so Master Plan, Action Plan, Due Soon, Reports and the reminders all pick them up |
| **Reminders** (Due Soon tab) | One mail per owner, two days before the due date, with anything overdue in the same message. "Email in Outlook" opens it addressed and written; "Copy text" is the fallback |
| **What's missing** (Reports tab) | Actions with no date, actions with no owner, projects with no value, projects with no items, items with no actions |
| **Value added per owner** (Portfolio Health) | Hard, soft and total value per person, beside the portfolio totals already there |
| **Workload by owner** (Home) | One stacked bar per person — complete, on track, needs attention, overdue. Click a row to open that person's card. An action with two owners counts for each, so the rows can add up to more than 297 |
| **Projects over time** (Home) | A month-by-month timeline of when each project runs, coloured by pillar, with today marked |

## Where a meeting action is filed

| Related to | Where the action lands |
|---|---|
| A master plan item | that item, exactly as if it had been added there |
| A project | that project's *Meeting Actions* item |
| A pillar | that pillar's *Meeting Actions* project and item |
| Another action | beside the action it follows on from |
| Nothing yet | a hidden *Meetings* home, the same way Reviews already work |

Either way it shows in Master Plan, Action Plan, Due Soon, Reports and the reminders.
Deleting a meeting keeps its actions.

## The data in it

The board carries the **22 Sep 2026** export: 6 pillars, 41 projects, 98 master plan
items, 297 actions and the 5 handles, with the priorities, quarters and change history
the export holds. This is the same data the database scripts and the web app load, so the
single file and the server show the same tower.

## What the timeline can and cannot show

A project is placed by its own start/due dates when it has them, and otherwise
by the earliest and latest dates on its actions. **No project in the plan
carries a start date yet**, so in practice every bar comes from action dates.
17 of the 41 projects can be placed; the other 24 carry no date anywhere and
are counted under the chart rather than quietly dropped. Fill in project dates,
or action target dates, and they appear on their own.

Row creation timestamps are deliberately not used. They record when somebody
typed a row, not when the work runs, and the database fills them in at import
for every row — counting them would drag every project back to import day.

## Saving still works the same way

The file rewrites itself on **Save data**, and the copy it writes carries the new code as
well — tested by saving from inside the page and reloading the result.
