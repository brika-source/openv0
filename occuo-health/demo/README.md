# Demo page

`index.html` is a single self-contained file: open it in any browser, no build
step and no server. It presents the whole site on one page so the design can be
reviewed in one pass.

## What it contains

Hero · compliance strip · eight services · health surveillance · statistics ·
emergency readiness · ergonomics · mental health · approach · why Occuo ·
client voices · industries · FAQ · proposal form · closing call to action.

## The design device

Every photograph carries the clinical reading being taken in it — `85 dB(A)`
against the hearing-conservation action level, `REBA 9 → 3` after a workstation
correction, `FEV1/FVC 0.78`. An accent rule draws itself across the top of each
reading as it scrolls into view.

This is the vernacular of occupational health: a worker measured against a
threshold, repeatedly, over time. It is what stops the photography reading as
stock imagery. IBM Plex Mono is used for readings and nothing else, beside
Sora for display and Inter for body text.

## Behaviour

Everything works without JavaScript. Content renders (the scroll reveal is
scoped to a `.js` class set before first paint), the FAQ uses native
`<details name="faq">` so it opens and closes unaided, and the form falls back
to the browser's own validation. With JavaScript, sections reveal on scroll,
readings animate, and the form validates inline before handing a completed
enquiry to the visitor's mail client.

`prefers-reduced-motion` disables every animation. Verified from 320px to
1920px with no horizontal scroll.

## Before using any of this publicly

The photography is AI-generated and the readings, statistics, testimonials and
contact details are illustrative sample data. The footer says so. See
`PHOTOS.md` for the argument about replacing the imagery, and the root
`README.md` section "Before you launch" for everything else that needs a
verified value.
