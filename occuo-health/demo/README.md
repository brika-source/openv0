# Demo page

`index.html` — one self-contained file. Open it in any browser; no build step,
no server.

## Bilingual

English and Arabic, switched from the control at the top of the page. The
choice sets `lang` and `dir` on the document, swaps every string, and is
remembered in `localStorage`. A visitor whose browser is set to Arabic gets
Arabic on first load.

The layout is written entirely in CSS logical properties, so right-to-left is a
genuine mirror rather than a patch: image sides swap, chips and navigation flow
right-to-left, arrows reverse. Latin technical strings (`FEV1/FVC`, `85 dB(A)`,
phone numbers, email) stay left-to-right inside the Arabic layout, which is how
they are actually read.

Arabic is set in Cairo; English in Sora and Inter. Measurements use IBM Plex
Mono in both languages.

All 135 strings are paired — `npm`-free check in
`tools/`-style verification confirms `data-en` and `data-ar` counts match, and
that no English leaks into the Arabic view.

## Editing copy

Every translatable element carries `data-en` and `data-ar`. Four variants exist
for cases plain text cannot cover:

| Attribute | For |
|---|---|
| `data-en` / `data-ar` | text content (the normal case) |
| `data-en-html` / `data-ar-html` | text containing inline markup, e.g. the hero `<em>` |
| `data-en-ph` / `data-ar-ph` | input placeholders |
| `data-en-alt` / `data-ar-alt` | image alt text |

Add both languages whenever you add a string. If one is missing, that element
simply keeps whatever text it had.

## Structure

Hero · eight services · health surveillance · figures · emergency readiness ·
ergonomics · mental health · approach · sectors · FAQ · contact.

Copy is deliberately short: a headline of four to six words, one supporting
line, and three chips. The photography carries the weight.

## The design device

Each photograph carries the clinical reading being taken in it — `FEV1/FVC
0.78`, `REBA 9 → 3`, the ERC `30:2` ratio. An accent rule draws itself across
the top of each reading as it scrolls into view. It is what stops the images
reading as stock photography.

## Behaviour

Works without JavaScript: content renders, and the FAQ uses native
`<details name="faq">` so it opens unaided. With JavaScript you also get the
language switch, scroll reveals and inline form validation (error messages
appear in the active language).

`prefers-reduced-motion` disables every animation. Verified 320px–1920px in
both languages with no horizontal scroll.

## Before publishing

Photography is AI-generated; readings, figures and contact details are sample
data. The footer says so, in both languages. See `PHOTOS.md`.

**The Arabic copy is mine, not a professional translation.** It is accurate
Modern Standard Arabic in a business register, but have a native speaker in
your market read it before it goes live — particularly the clinical and legal
terms.
