# Occuo Health — website

A rebuild of [occuohealtheg.com](https://occuohealtheg.com): new information
architecture, new design system, new content structure.

Zero dependencies. No framework, no bundler, no `node_modules`. A ~300-line
build script turns one content file into a 17-page static site that will run on
any host that can serve files.

```
npm run build     # → dist/
npm run dev       # build, then serve dist/ at http://localhost:4321
npm run images    # regenerate favicons, app icons and the social card
npm run check     # post-build link / metadata / accessibility checks
```

Requires Node 18+. Nothing to install.

---

## What changed, and why

**Structure.** The original site presented services as a flat list of pages with
no path between them. This version has a real hierarchy — a services hub, eight
detail pages generated from one data file, an industries page that maps sectors
to programmes, and a consolidated FAQ. Every service is reachable in one click
from any page via the header mega menu, and every detail page ends by pointing
at three related programmes.

**Content.** Each service page now answers the questions a buyer actually asks:
what is covered, what the employer receives, how confidentiality works, how long
mobilisation takes. That last part matters more than design for a B2B
occupational health buyer.

**Design.** A deep petrol green over a warm limestone neutral, with terracotta
reserved exclusively for primary actions — so a call to action is never
ambiguous. Type is Sora for headings, Inter for body, both fluid via `clamp()`.
Everything is driven by CSS custom properties, which is what makes the dark
theme ~40 lines instead of a second stylesheet.

**Engineering.** Static HTML, one 43 KB stylesheet, one 8 KB script, inline SVG
icons. Nothing blocks rendering except the font stylesheet, and there is a real
fallback stack behind it. The site works with JavaScript disabled: navigation,
content, forms and the statistics all render.

---

## Project layout

```
occuo-health/
├── content/site.js          ← all copy, services, FAQs, contact details
├── build.mjs                ← the whole build (+ a dev server)
├── src/
│   ├── lib/
│   │   ├── layout.mjs       ← page shell, header, footer, shared components
│   │   ├── pages.mjs        ← one function per page type
│   │   └── icons.mjs        ← 40 inline SVG icons
│   └── assets/
│       ├── css/main.css     ← design system + components
│       ├── js/main.js       ← progressive enhancement only
│       └── img/             ← generated brand assets
├── tools/
│   ├── make-images.mjs      ← PNG encoder + SDF renderer for brand assets
│   └── check.mjs            ← post-build QA
└── dist/                    ← build output (committed, so it can be hosted as-is)
```

### Editing content

Almost everything lives in `content/site.js`. Adding a ninth service means
appending one object to the `services` array — the build then generates its
detail page, its card on the homepage and services hub, its entry in the header
mega menu and the footer, its FAQ block, its `Service` structured data and its
sitemap entry. Nothing else needs touching.

Never edit files in `dist/` — the next build overwrites them.

---

## Before you launch

The site is complete, but some values in `content/site.js` are marked
`PLACEHOLDER` because I had no verified source for them. **Occupational health
is a regulated field; publishing unverifiable claims is a real risk.** Work
through these:

| Where | What to replace |
|---|---|
| `site.url` | Production origin. Canonicals, OG tags and the sitemap all derive from it. |
| `site.contact.*` | Phone, WhatsApp, email addresses, street address, opening hours. All are invented. |
| `site.social` | Real profile URLs — or delete the channels you do not run. |
| `stats` | **Every figure is invented.** Replace with verified numbers or delete the array (the band disappears cleanly). |
| `testimonials` | Replace with real, attributable quotes and written permission to publish, or delete the array. |
| `site.founded` | Actual founding year. |

Legal pages at `/privacy/` and `/terms/` are drafted templates and carry a
visible notice saying so. Have them reviewed against your actual data practices
and Egyptian law, then remove the notice from `legalPage()` in
`src/lib/pages.mjs`.

Regulatory references — Egyptian Labour Law No. 12/2003, ISO 45001, ERC
resuscitation guidelines — are phrased as alignment, never as certification.
If Occuo Health does hold certifications, say so explicitly; if it does not,
leave the wording as it is.

`npm run check` warns if any `PLACEHOLDER` marker reaches the rendered output.

---

## Wiring up the contact form

The form at `/contact/` validates in the browser and includes a honeypot field,
but has no backend. Until one exists it falls back to composing an email so
enquiries are never silently lost.

To connect a real endpoint, set `data-endpoint` on the form in
`contactPage()` (`src/lib/pages.mjs`) and point `action` at your handler —
Formspree, Netlify Forms, or your own script. `src/assets/js/main.js` skips the
mailto fallback as soon as `data-endpoint` is present.

If you handle enquiry data through a third party, update `/privacy/` to say so.

---

## Deploying

`dist/` is a plain directory of static files.

- **Netlify / Vercel** — build command `npm run build`, publish directory `dist`.
  `_redirects` and `_headers` are already written for you.
- **GitHub Pages** — publish `dist/`. For a project site (not a root domain),
  build with `BASE_PATH=/repo-name npm run build` so links and assets resolve.
  `.nojekyll` is generated so the underscore files survive.
- **cPanel / shared hosting** — upload the contents of `dist/` to `public_html`.
  Directory-style URLs (`/about/`) work because each is a real folder with an
  `index.html`.

Set `site.url` before building for production — the sitemap, canonical URLs and
social tags are absolute.

---

## Notes on decisions you might want to revisit

**Fonts load from Google Fonts.** That is one third-party request and a privacy
consideration under some interpretations of GDPR. Self-hosting is a drop-in
change: download the WOFF2 files into `src/assets/fonts/`, replace the
`<link>` in `layout.mjs` with `@font-face` rules, and add `font-display: swap`.

**No photography.** The hero uses a composed UI card rather than stock imagery,
because generic stock photos of people in hard hats actively damage credibility
in this sector. Real site and team photography would be a genuine upgrade —
`.hero__visual` is the place to put it.

**English only.** The CSS is written entirely in logical properties, and there
is an RTL block at the bottom of `main.css` plus an Arabic font stack already
defined, so an Arabic build needs translated content and `dir="rtl"` — not a
layout rewrite. Given the market, this is probably the highest-value next
addition, and I did not attempt machine-translating clinical copy.

**No analytics.** Nothing is tracked and no cookies are set beyond a
`localStorage` theme preference, which is what `/privacy/` currently states.
Adding analytics means updating that page.

**The social card has no text on it.** This build environment has no font
rasteriser, so `og-default.png` is drawn geometrically. `og-default.svg` ships
alongside it with the full wordmark — convert it once and overwrite the PNG:

```sh
rsvg-convert -w 1200 -h 630 src/assets/img/og-default.svg -o src/assets/img/og-default.png
```

---

## Accessibility and quality

Built in, and verified in a headless browser during development:

- Skip link, visible focus rings, landmarks, one `<h1>` per page
- Mega menu and mobile drawer are keyboard operable and close on `Escape`
- Accordions use proper `aria-expanded` / `aria-controls` and real `<button>`s
- `prefers-reduced-motion` disables reveals, counters and smooth scrolling
- `prefers-color-scheme` respected, with an explicit override that persists
- No horizontal scroll at 390px; theme set before first paint, so no flash
- JSON-LD: `MedicalBusiness`, `Service`, `FAQPage`, `BreadcrumbList`
- `npm run check` fails the build on broken internal links, missing metadata,
  images without `alt`, duplicate `<h1>`s, invalid JSON-LD or sitemap gaps

Not done: no automated axe/Lighthouse run, no cross-browser testing beyond
Chromium, and no real-user performance measurement.
