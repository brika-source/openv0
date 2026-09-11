# Demo photography

Five photographs were commissioned for this demo and generated with Gamma
(`flux`-class model, 2752 × 1536, 16:9). They are referenced in
`demo/index.html` by their CDN URL.

| # | Subject | Used in | URL |
|---|---------|---------|-----|
| 1 | Audiometry — nurse conducting a hearing test on a factory worker | Hero | `https://cdn.gamma.app/j0tp2zv5h8boufy/design-anything/We4iTZrctzW7M0mmTfc8x/7K9yktL5SNH--X6eq4j2u.jpg` |
| 2 | Spirometry — lung-function test in an on-site medical room | Health Surveillance | `https://cdn.gamma.app/j0tp2zv5h8boufy/design-anything/qubUsCPxmKj5ew1Tl6bif/4ZmrEZODtyLK--gIuYyko.jpg` |
| 3 | CPR training — instructor and workers around a manikin | Emergency readiness | `https://cdn.gamma.app/j0tp2zv5h8boufy/design-anything/6t7GdJu38WwnvyPxObanq/R6yzE10LhCsW5iEZ6BI9w.jpg` |
| 4 | Ergonomics assessment at a production-line workstation | Ergonomics band | `https://cdn.gamma.app/j0tp2zv5h8boufy/design-anything/31SZSM3WOqetdX5q3mVql/6d8qXSID19eduo7eQQHim.jpg` |
| 5 | Counselling session in a warm consulting room | Mental health band | `https://cdn.gamma.app/j0tp2zv5h8boufy/design-anything/c8IzTl9KN4MSsGaKLGups/kJjkTPb9sc4ZAPzE1kyCL.jpg` |

## Download these

**These CDN links are the only copy.** Save all five locally before the URLs
expire:

```sh
mkdir -p src/assets/img/photos
# then download each URL above into that folder
```

## Before using them publicly

These are **AI-generated images, not documentary photographs of Occuo Health**.
That is fine for a layout demo. Using them on the live site means presenting
staged scenes as if they were your operations, which is a credibility risk in
a clinical field — and a real one if a client ever asks where the photo was
taken.

Two honest options:

1. **Commission real photography** at a client site (with written consent from
   everyone identifiable, and the client's permission to publish). Always the
   stronger choice — it is verifiable and it shows your actual teams.
2. **Keep AI imagery but label it.** Some employers will not care; others will.
   If you keep it, do not caption or imply that a scene shows a specific client.

The demo footer carries a visible note saying the photography is AI-generated
and the readings are sample data. Remove it only when both statements stop
being true.

## Replacing them

Every photo sits in a `.photo` box with a gradient ground behind it, so a
missing or slow image degrades to a designed panel rather than a broken icon.
Swap the `src` on each `<img data-photo>` in `demo/index.html`; nothing else
needs to change. Keep images at 16:9 or wider — the boxes crop with
`object-fit: cover`.
