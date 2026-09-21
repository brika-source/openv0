# Single-file edition

`DigitalControlTower.html` is the whole tower in one file: open it in a browser and it
runs — no server, no database, no install. It carries the same data as the SQL scripts
(16 users, 6 pillars, 41 projects, 98 work items, 297 actions) and the same model: handles
for people, actions rolled up to a project and pillar, meetings whose actions flow into the
plan, the 14-week grid, value reporting and the reminder queue.

Use it to look at the plan, run a meeting or show the tower to someone before the server
is ready. Changes are kept in that browser only (`localStorage`), so two people opening the
file do not see each other's edits — that is what the installed application is for. The
**Data** screen copies everything back out as JSON.

It cannot send the reminder mails; it shows exactly what the server will send, mail by mail.

## Rebuilding it

To load a newer export into the page, build the dataset and swap it in — the markup,
styling and behaviour stay in the HTML file itself:

```bash
cd tools
python3 build_html_data.py ../src/DigitalControlTower.Web/Data/seed/<export>.json /tmp/dct-data.json
python3 refresh_html_data.py /tmp/dct-data.json ../standalone/DigitalControlTower.html
```
