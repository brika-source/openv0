/**
 * Page shell + shared UI components.
 *
 * Everything here returns an HTML string. There is no template engine and no
 * runtime dependency: the build imports these functions, concatenates strings
 * and writes static files.
 */

import { icon } from './icons.mjs';
import { site, services } from '../../content/site.js';

/**
 * Path prefix for all internal links and assets.
 * Set BASE_PATH=/repo-name when hosting under a subdirectory
 * (e.g. a GitHub Pages project site). Default is domain root.
 */
export const BASE = (process.env.BASE_PATH || '').replace(/\/$/, '');

/** Build an internal URL, honouring BASE. */
export const url = (p = '/') => `${BASE}${p.startsWith('/') ? p : `/${p}`}`;

/** Escape text for safe interpolation into HTML. */
export const esc = (s = '') =>
  String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/* ---------------------------------------------------------------- nav model */

export const nav = [
  { label: 'Services', href: '/services/', children: services.map((s) => ({ label: s.title, href: `/services/${s.slug}/`, desc: s.short, icon: s.icon })) },
  { label: 'Industries', href: '/industries/' },
  { label: 'Approach', href: '/about/#approach' },
  { label: 'About', href: '/about/' },
  { label: 'FAQ', href: '/faq/' },
];

/* ------------------------------------------------------------------- brand */

export function logo({ compact = false } = {}) {
  return `
<span class="logo${compact ? ' logo--compact' : ''}">
  <span class="logo__mark" aria-hidden="true">
    <svg viewBox="0 0 40 40" fill="none" role="presentation">
      <circle cx="20" cy="20" r="17" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"
              stroke-dasharray="76 32" transform="rotate(-40 20 20)"/>
      <path class="logo__pulse" d="M7 20h5.5l3-7 4.5 14 3.2-7H33"
            stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </span>
  <span class="logo__text">
    <span class="logo__name">Occuo<span>Health</span></span>
    ${compact ? '' : '<span class="logo__sub">Occupational Health · Egypt</span>'}
  </span>
</span>`;
}

/* ------------------------------------------------------------------ header */

function navItem(item, current) {
  const active = current === item.href || (item.children && current.startsWith('/services/'));
  if (!item.children) {
    return `<li><a class="nav__link${active ? ' is-active' : ''}" href="${url(item.href)}">${esc(item.label)}</a></li>`;
  }
  const cols = item.children
    .map(
      (c) => `
      <a class="megamenu__item" href="${url(c.href)}">
        <span class="megamenu__icon">${icon(c.icon, { size: 20 })}</span>
        <span>
          <strong>${esc(c.label)}</strong>
          <em>${esc(c.desc)}</em>
        </span>
      </a>`
    )
    .join('');

  return `
<li class="nav__item nav__item--has-menu">
  <button class="nav__link nav__toggle${active ? ' is-active' : ''}" type="button"
          aria-expanded="false" aria-controls="megamenu-services">
    ${esc(item.label)} ${icon('chevron-down', { size: 16, cls: 'nav__caret' })}
  </button>
  <div class="megamenu" id="megamenu-services" hidden>
    <div class="megamenu__inner">
      <div class="megamenu__grid">${cols}</div>
      <div class="megamenu__aside">
        <p class="megamenu__kicker">Not sure where to start?</p>
        <p>A site health risk assessment establishes what you are actually exposed to — and what you can safely stop paying for.</p>
        <a class="btn btn--sm btn--primary" href="${url('/contact/')}">Book an assessment ${icon('arrow-right', { size: 15 })}</a>
      </div>
    </div>
  </div>
</li>`;
}

export function header(current = '/') {
  return `
<a class="skip-link" href="#main">Skip to main content</a>

<div class="topbar">
  <div class="container topbar__inner">
    <p class="topbar__note">${icon('shield-plus', { size: 15 })} Occupational health programmes for employers across Egypt</p>
    <div class="topbar__links">
      <a href="tel:${esc(site.contact.phoneHref)}">${icon('phone', { size: 15 })} ${esc(site.contact.phone)}</a>
      <a href="mailto:${esc(site.contact.email)}">${icon('mail', { size: 15 })} ${esc(site.contact.email)}</a>
    </div>
  </div>
</div>

<header class="header" id="header">
  <div class="container header__inner">
    <a class="header__brand" href="${url('/')}" aria-label="${esc(site.name)} — home">${logo()}</a>

    <nav class="nav" id="primary-nav" aria-label="Primary">
      <ul class="nav__list">
        ${nav.map((i) => navItem(i, current)).join('\n        ')}
      </ul>
      <div class="nav__cta">
        <a class="btn btn--ghost btn--sm" href="tel:${esc(site.contact.phoneHref)}">${icon('phone', { size: 16 })} Call</a>
        <a class="btn btn--primary btn--sm" href="${url('/contact/')}">Request a proposal</a>
      </div>
    </nav>

    <div class="header__actions">
      <button class="icon-btn" id="theme-toggle" type="button" aria-label="Switch to dark theme">
        <span class="icon-btn__sun">${icon('sun', { size: 19 })}</span>
        <span class="icon-btn__moon">${icon('moon', { size: 19 })}</span>
      </button>
      <button class="icon-btn nav-burger" id="nav-toggle" type="button"
              aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu">
        <span class="nav-burger__open">${icon('menu', { size: 22 })}</span>
        <span class="nav-burger__close">${icon('close', { size: 22 })}</span>
      </button>
    </div>
  </div>
  <div class="header__progress" id="scroll-progress" aria-hidden="true"></div>
</header>
<div class="nav-scrim" id="nav-scrim" hidden></div>`;
}

/* ------------------------------------------------------------------ footer */

export function footer() {
  const socials = site.social
    .map(
      (s) =>
        `<a class="social" href="${esc(s.href)}" rel="noopener noreferrer" target="_blank" aria-label="${esc(s.label)}">${icon(s.icon, { size: 19 })}</a>`
    )
    .join('');

  return `
<section class="cta-band">
  <div class="container cta-band__inner">
    <div>
      <p class="kicker kicker--light">Start with the risk, not the invoice</p>
      <h2>Find out what your workplace actually needs.</h2>
      <p class="cta-band__lead">A site health risk assessment gives you a ranked picture of your exposures and a programme scoped against them — including the tests you can stop paying for.</p>
    </div>
    <div class="cta-band__actions">
      <a class="btn btn--accent btn--lg" href="${url('/contact/')}">Request a proposal ${icon('arrow-right', { size: 17 })}</a>
      <a class="btn btn--outline-light btn--lg" href="tel:${esc(site.contact.phoneHref)}">${icon('phone', { size: 17 })} ${esc(site.contact.phone)}</a>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container">
    <div class="footer__top">
      <div class="footer__brand">
        <a href="${url('/')}" aria-label="${esc(site.name)} — home">${logo()}</a>
        <p class="footer__blurb">${esc(site.description)}</p>
        <div class="footer__social">${socials}</div>
      </div>

      <nav class="footer__col" aria-label="Services">
        <h3>Services</h3>
        <ul>${services.map((s) => `<li><a href="${url(`/services/${s.slug}/`)}">${esc(s.title)}</a></li>`).join('')}</ul>
      </nav>

      <nav class="footer__col" aria-label="Company">
        <h3>Company</h3>
        <ul>
          <li><a href="${url('/about/')}">About Occuo Health</a></li>
          <li><a href="${url('/about/#approach')}">Our approach</a></li>
          <li><a href="${url('/industries/')}">Industries</a></li>
          <li><a href="${url('/faq/')}">Questions &amp; answers</a></li>
          <li><a href="${url('/contact/')}">Contact</a></li>
        </ul>
      </nav>

      <div class="footer__col footer__col--contact">
        <h3>Contact</h3>
        <ul class="footer__contact">
          <li>${icon('phone', { size: 17 })}<a href="tel:${esc(site.contact.phoneHref)}">${esc(site.contact.phone)}</a></li>
          <li>${icon('mail', { size: 17 })}<a href="mailto:${esc(site.contact.email)}">${esc(site.contact.email)}</a></li>
          <li>${icon('map-pin', { size: 17 })}<span>${site.contact.addressLines.map(esc).join('<br>')}</span></li>
          <li>${icon('clock', { size: 17 })}<span>${esc(site.contact.hours)}</span></li>
        </ul>
        <p class="footer__note">${esc(site.contact.emergencyNote)}</p>
      </div>
    </div>

    <div class="footer__bottom">
      <p>&copy; <span data-current-year>2026</span> ${esc(site.legalName)}. All rights reserved.</p>
      <ul>
        <li><a href="${url('/privacy/')}">Privacy notice</a></li>
        <li><a href="${url('/terms/')}">Terms of use</a></li>
        <li><a href="${url('/contact/')}">Contact</a></li>
      </ul>
    </div>
  </div>
</footer>`;
}

/* ------------------------------------------------- reusable UI components */

export function sectionHead({ kicker, title, lead, align = 'left', light = false }) {
  return `
<div class="section-head${align === 'center' ? ' section-head--center' : ''}" data-reveal>
  ${kicker ? `<p class="kicker${light ? ' kicker--light' : ''}">${esc(kicker)}</p>` : ''}
  <h2 class="section-head__title">${title}</h2>
  ${lead ? `<p class="section-head__lead">${lead}</p>` : ''}
</div>`;
}

export function breadcrumbs(trail) {
  return `
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol>
    ${trail
      .map((t, i) =>
        i === trail.length - 1
          ? `<li aria-current="page">${esc(t.label)}</li>`
          : `<li><a href="${url(t.href)}">${esc(t.label)}</a></li>`
      )
      .join('')}
  </ol>
</nav>`;
}

export function accordion(items, { id = 'faq' } = {}) {
  return `
<div class="accordion" data-accordion>
  ${items
    .map(
      (item, i) => `
  <div class="accordion__item">
    <h3>
      <button class="accordion__trigger" type="button" id="${id}-t-${i}"
              aria-expanded="false" aria-controls="${id}-p-${i}">
        <span>${esc(item.q)}</span>
        ${icon('plus', { size: 20, cls: 'accordion__sign' })}
      </button>
    </h3>
    <div class="accordion__panel" id="${id}-p-${i}" role="region" aria-labelledby="${id}-t-${i}" hidden>
      <div class="accordion__body"><p>${esc(item.a)}</p></div>
    </div>
  </div>`
    )
    .join('')}
</div>`;
}

export function pageHero({ kicker, title, lead, trail, actions = '', aside = '' }) {
  return `
<section class="page-hero">
  <div class="page-hero__glow" aria-hidden="true"></div>
  <div class="container">
    ${trail ? breadcrumbs(trail) : ''}
    <div class="page-hero__grid${aside ? ' page-hero__grid--split' : ''}">
      <div>
        ${kicker ? `<p class="kicker">${esc(kicker)}</p>` : ''}
        <h1 class="page-hero__title">${title}</h1>
        ${lead ? `<p class="page-hero__lead">${lead}</p>` : ''}
        ${actions ? `<div class="hero__actions">${actions}</div>` : ''}
      </div>
      ${aside ? `<div class="page-hero__aside">${aside}</div>` : ''}
    </div>
  </div>
</section>`;
}

/* -------------------------------------------------------------- page shell */

/**
 * @param {object} o
 * @param {string} o.title      full <title> text
 * @param {string} o.description meta description
 * @param {string} o.path       site-relative path, e.g. '/services/'
 * @param {string} o.body       page markup
 * @param {string} [o.jsonLd]   additional JSON-LD blocks
 * @param {string} [o.bodyClass]
 */
export function page({ title, description, path, body, jsonLd = '', bodyClass = '' }) {
  const canonical = `${site.url}${path}`;
  const ogImage = `${site.url}${url('/assets/img/og-default.png')}`;

  return `<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${esc(title)}</title>
<meta name="description" content="${esc(description)}">
<link rel="canonical" href="${esc(canonical)}">
<meta name="theme-color" content="#06322E" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#04211E" media="(prefers-color-scheme: dark)">

<meta property="og:type" content="website">
<meta property="og:site_name" content="${esc(site.name)}">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(description)}">
<meta property="og:url" content="${esc(canonical)}">
<meta property="og:image" content="${esc(ogImage)}">
<meta property="og:locale" content="en_EG">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(description)}">
<meta name="twitter:image" content="${esc(ogImage)}">

<link rel="icon" href="${url('/assets/img/favicon.svg')}" type="image/svg+xml">
<link rel="apple-touch-icon" href="${url('/assets/img/apple-touch-icon.png')}">
<link rel="manifest" href="${url('/site.webmanifest')}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="${url('/assets/css/main.css')}">
<script>
/* Runs before first paint: sets the theme (no light-to-dark flash) and marks
   the document as scripted, which is what allows the scroll-reveal CSS to
   hide anything at all. Without JS the .js class never lands and every
   section renders immediately. */
(function(){document.documentElement.classList.add('js');
try{var t=localStorage.getItem('occuo-theme');var d=window.matchMedia('(prefers-color-scheme: dark)').matches;
document.documentElement.dataset.theme=t||(d?'dark':'light');}catch(e){}})();
</script>
</head>
<body${bodyClass ? ` class="${bodyClass}"` : ''}>
${header(path)}
<main id="main">
${body}
</main>
${footer()}
<script src="${url('/assets/js/main.js')}" defer></script>
${jsonLd}
</body>
</html>`;
}
