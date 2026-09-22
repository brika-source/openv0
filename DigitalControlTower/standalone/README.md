# Single-file edition — your own UI

`digital_control_tower_2126_5.html` is the board you sent (`…_2126_4.html`), with the new
features added **inside it**. Nothing about the existing design was changed: the topbar,
hero, award banner, pillar cards, tabs, tables and both themes are the same file's CSS —
718 CSS lines untouched, 21 added, all built from its own tokens.

## What was added

| | |
|---|---|
| **Handles** | People are shown by their mail alias — `brika.wm`, `ismail.he`. The old free-text spellings ("Hassan ismail", "hassan") resolve to one handle, and owner pickers list handles |
| **Meetings Action Plan** (new tab) | The digital team's own meetings. Opens straight on today's date, participants are ticked from the team, minutes live with the meeting |
| **Digital team meeting** (home card) | One click from the dashboard to today's meeting |
| **Meeting actions** | Start as *Not Started*, take a due date, and relate to a pillar, a project, a master plan item or another action — then they are filed there, so Master Plan, Action Plan, Due Soon, Reports and the reminders all pick them up |
| **Reminders** (Due Soon tab) | One mail per owner, two days before the due date, with anything overdue in the same message. "Email in Outlook" opens it addressed and written; "Copy text" is the fallback |
| **What's missing** (Reports tab) | Actions with no date, actions with no owner, projects with no value, projects with no items, items with no actions |
| **Value added per owner** (Portfolio Health) | Hard, soft and total value per person, beside the portfolio totals already there |

## Saving still works the same way

The file rewrites itself on **Save data**, and the copy it writes carries the new code as
well — tested by saving from inside the page and reloading the result.
