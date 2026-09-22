#!/usr/bin/env python3
"""
Swaps the data embedded in the single-file HTML edition for a newer export.

Usage:
    python3 build_html_data.py <export.json> /tmp/dct-data.json
    python3 refresh_html_data.py /tmp/dct-data.json ../standalone/digital_control_tower_2126_5.html

The page itself is the source of truth for markup, styling and behaviour; only the
block between the seed-data script tags is replaced.
"""
import json
import re
import sys

MARKER = re.compile(
    r'(<script type="application/json" id="seed-data">)(.*?)(</script>)', re.S)


def main(data_path, html_path):
    data = json.load(open(data_path, encoding="utf-8"))
    # Escaping "<" keeps a stray </script> inside the data from closing the tag early.
    blob = json.dumps(data, ensure_ascii=False, separators=(",", ":")).replace("<", "\\u003c")

    html = open(html_path, encoding="utf-8").read()
    if not MARKER.search(html):
        sys.exit("No seed-data block found in " + html_path)

    updated = MARKER.sub(lambda m: m.group(1) + blob + m.group(3), html, count=1)
    open(html_path, "w", encoding="utf-8").write(updated)

    print("{}: {} users, {} pillars, {} projects, {} work items, {} actions, {} meetings".format(
        html_path, len(data["users"]), len(data["pillars"]), len(data["projects"]),
        len(data["items"]), len(data["actions"]), len(data.get("meetings", []))))


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
