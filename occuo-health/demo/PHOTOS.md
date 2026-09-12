# Imagery

## What is in the demo now

Five generated artworks, **embedded in `demo/index.html` as data URIs**. They
render with no network connection at all — nothing to download, nothing a proxy
or CDN can block.

| # | Subject | Section |
|---|---------|---------|
| 01 | Audiogram with a 4 kHz notch crossing the action level | Hero |
| 02 | Spirometry flow-volume loop | Health Surveillance |
| 03 | Chest-compression trace at the 30:2 ratio | Emergency readiness |
| 04 | Working posture with joint-angle arcs | Ergonomics |
| 05 | Layered strain-and-recovery curves | Mental health |

Sources are in `demo/art/`, regenerated with `python3 tools/make-artwork.py`.

**These are not photographs.** They are drawn from each service's own
instrument, so they are specific rather than decorative — but they do not show
people, and they should not be described as documentary images of Occuo Health.

## Why there are no photographs

Every image host is blocked by this environment's network policy — stock
libraries, and the CDN that served the AI-generated photographs commissioned
earlier in this project. Those images could be generated but never retrieved,
which is why links to them appeared in earlier drafts and then showed nothing.

There is no workaround available from inside this environment. Photographs have
to come from you.

## Putting real photographs in

Two commands:

```sh
# 1. Replace the five files in demo/art/, keeping the 01- … 05- name order.
#    Use 16:9 or wider; they are cropped with object-fit: cover.
# 2. Re-embed them:
python3 tools/embed-art.py
```

`embed-art.py` matches files to image slots in page order and rewrites each
`src` to a data URI. It refuses to run if the counts do not match, so a missing
file fails loudly rather than leaving a blank panel.

Then update the `data-en-alt` / `data-ar-alt` attributes on each
`<img data-photo>` to describe the new picture, and remove the disclosure note
at the foot of the page once it is no longer accurate.

### What to shoot

In priority order, all on a client site with written consent from everyone
identifiable and the client's permission to publish:

1. **A medical examination in progress** — audiometry or spirometry, on site.
   This is the hero. It is the single image that says what the company does.
2. **First aid training** — an instructor with a manikin and a group of workers.
3. **An ergonomics assessment** — an assessor observing someone at a workstation.
4. **A wide plant interior** with workers in PPE, for sector credibility.
5. **The team** — a straightforward group portrait in scrubs or branded polos.

Real photography beats anything generated here, and in a clinical field it is
also the only kind you can stand behind if a client asks where it was taken.
