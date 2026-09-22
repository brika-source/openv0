#!/usr/bin/env python3
"""Check that db/02_seed_data.sql loads the same data as the web app's importer.

The package offers two ways to get the export into SQL Server: run the scripts
in db/, or let the app import Data/seed/digital-control-tower-export.json on
first start. They must agree, so this compares them row by row.

Usage:
    python3 verify_seed_equivalence.py <imported.db> [seed.sql]

<imported.db> is a SQLite database written by the app's importer. Timestamps
are normalised away, because each loader stamps "imported at" with its own
clock for the rows the export leaves undated, and provisional users are
compared by handle, because each loader mints their ids its own way.
"""
import re, sqlite3, sys, datetime

import os

HERE = os.path.dirname(os.path.abspath(__file__))
DB = sys.argv[1] if len(sys.argv) > 1 else sys.exit(__doc__)
SQL = sys.argv[2] if len(sys.argv) > 2 else os.path.join(HERE, "..", "db", "02_seed_data.sql")

text = open(SQL, encoding="utf-8-sig").read()

def split_values(s):
    """Split a VALUES tuple body on commas that are outside N'...' literals."""
    out, buf, in_str, i = [], [], False, 0
    while i < len(s):
        c = s[i]
        if in_str:
            if c == "'":
                if i + 1 < len(s) and s[i + 1] == "'":
                    buf.append("'"); i += 2; continue
                in_str = False; i += 1; continue
            buf.append(c); i += 1; continue
        if c == "'":
            in_str = True; i += 1; continue
        if c == ",":
            out.append("".join(buf).strip()); buf = []; i += 1; continue
        buf.append(c); i += 1
    out.append("".join(buf).strip())
    return out

TS = re.compile(r"^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}")

def norm(v):
    v = v.strip()
    if v.upper() == "NULL":
        return None
    # split_values already dropped the quote characters, so an N-prefixed
    # unicode literal arrives as "Nvalue"; strip that marker.
    if v.startswith("N"):
        v = v[1:]
    if TS.match(v):
        return "<ts>"
    return v

# Large tables are split into several INSERT batches, so a statement ends at
# the next INSERT as well as at GO/PRINT/end-of-file.
statements = re.findall(
    r"INSERT INTO \[(\w+)\] \(([^)]*)\) VALUES\s*(.*?);\s*(?=\nGO|\nPRINT|\nINSERT|\Z)",
    text, re.S)

def norm_db(v):
    if v is None:
        return None
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, datetime.datetime):
        return "<ts>"
    s = str(v)
    if TS.match(s):
        return "<ts>"
    return s


TUPLE = re.compile(r"\(((?:[^()']|'(?:''|[^'])*')*)\)")

def parsed(table_filter=None):
    """Rows of the seed script, per table, with the batches joined back up."""
    out = {}
    for table, colblob, body in statements:
        cols = [c.strip().strip("[]") for c in colblob.split(",")]
        bucket = out.setdefault(table, (cols, []))
        assert bucket[0] == cols, "column list differs between batches of " + table
        for tup in TUPLE.findall(body):
            vals = split_values(tup)
            assert len(vals) == len(cols), "arity in " + table
            bucket[1].append([norm(v) for v in vals])
    return out

SQL_TABLES = parsed()

con = sqlite3.connect(DB)
con.row_factory = sqlite3.Row

def norm_db(v):
    if v is None:
        return None
    if isinstance(v, (int, float)):
        return str(v)
    s = str(v)
    return "<ts>" if TS.match(s) else s

# People not named in the export are added as provisional users, and the two
# loaders mint their ids differently (the script numbers them, the importer
# uses a random suffix), so compare those by handle.
ucols, urows = SQL_TABLES["Users"]
uix = {c: i for i, c in enumerate(ucols)}
PROV_SQL = {r[uix["Id"]]: "prov:" + r[uix["Handle"]] for r in urows
            if r[uix["IsProvisional"]] == "1"}
PROV_DB = {r["Id"]: "prov:" + r["Handle"]
           for r in con.execute("SELECT Id, Handle FROM Users WHERE IsProvisional = 1")}
assert len(PROV_SQL) == len(PROV_DB) > 0, (len(PROV_SQL), len(PROV_DB))
print("provisional users compared by handle: %d\n" % len(PROV_SQL))

def alias(v, m):
    return m.get(v, v) if isinstance(v, str) else v

ok = True
grand = 0
for table in SQL_TABLES:
    cols, rows = SQL_TABLES[table]
    a = sorted((tuple(alias(v, PROV_SQL) for v in r) for r in rows), key=repr)
    cur = con.execute("SELECT %s FROM [%s]" % (",".join("[%s]" % c for c in cols), table))
    b = sorted((tuple(alias(norm_db(r[c]), PROV_DB) for c in cols) for r in cur.fetchall()), key=repr)
    grand += len(a)
    same = a == b
    ok &= same
    print("%-16s sql=%-4d db=%-4d %s" % (table, len(a), len(b), "match" if same else "DIFFER"))
    if not same:
        for x in a:
            if x not in b:
                print("    only in sql:", x); break
        for x in b:
            if x not in a:
                print("    only in db :", x); break

print()
print("compared %d rows across %d tables" % (grand, len(SQL_TABLES)))
print("RESULT:", "IDENTICAL" if ok else "MISMATCH")
sys.exit(0 if ok else 1)
