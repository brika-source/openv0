/**
 * Page templates. Each export returns { path, title, description, body, jsonLd }
 * and is rendered through layout.page() by build.mjs.
 */

import { icon } from './icons.mjs';
import {
  site, services, industries, process, differentiators,
  stats, values, testimonials, faqs, about,
} from '../../content/site.js';
import { url, esc, sectionHead, accordion, pageHero, breadcrumbs } from './layout.mjs';

/* ------------------------------------------------------ structured data --- */

const ld = (obj) => `<script type="application/ld+json">${JSON.stringify(obj, null, 0)}</script>`;

export const organisationLd = () =>
  ld({
    '@context': 'https://schema.org',
    '@type': ['MedicalBusiness', 'Organization'],
    '@id': `${site.url}/#organisation`,
    name: site.name,
    legalName: site.legalName,
    description: site.description,
    url: site.url,
    telephone: site.contact.phone,
    email: site.contact.email,
    image: `${site.url}/assets/img/og-default.png`,
    logo: `${site.url}/assets/img/favicon.svg`,
    address: {
      '@type': 'PostalAddress',
      addressLocality: site.contact.city,
      addressCountry: site.contact.country,
      streetAddress: site.contact.addressLines[0],
    },
    areaServed: { '@type': 'Country', name: 'Egypt' },
    medicalSpecialty: 'Occupational Medicine',
    availableLanguage: ['en', 'ar'],
    sameAs: site.social.map((s) => s.href),
    hasOfferCatalog: {
      '@type': 'OfferCatalog',
      name: 'Occupational health services',
      itemListElement: services.map((s) => ({
        '@type': 'Offer',
        itemOffered: { '@type': 'Service', name: s.title, description: s.summary, url: `${site.url}/services/${s.slug}/` },
      })),
    },
  });

const breadcrumbLd = (trail) =>
  ld({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: trail.map((t, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: t.label,
      item: `${site.url}${t.href}`,
    })),
  });

const faqLd = (items) =>
  ld({
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: items.map((f) => ({
      '@type': 'Question',
      name: f.q,
      acceptedAnswer: { '@type': 'Answer', text: f.a },
    })),
  });

/* ----------------------------------------------------- shared components --- */

const serviceCard = (s, i) => `
<a class="card card--link" href="${url(`/services/${s.slug}/`)}" data-reveal data-reveal-delay="${(i % 4) + 1}">
  <span class="card__icon">${icon(s.icon, { size: 24 })}</span>
  <h3>${esc(s.title)}</h3>
  <p>${esc(s.summary)}</p>
  <span class="card__foot link-arrow">Explore ${icon('arrow-right', { size: 16 })}</span>
</a>`;

const statsBand = () => `
<div class="stats" data-reveal>
  ${stats
    .map(
      (s) => `
  <div class="stat">
    <span class="stat__value"><span data-count="${s.value}">${s.value.toLocaleString('en-GB')}</span>${esc(s.suffix)}</span>
    <span class="stat__label">${esc(s.label)}</span>
  </div>`
    )
    .join('')}
</div>`;

const complianceStrip = () => `
<ul class="strip" data-reveal>
  <li>${icon('scale', { size: 18 })} Egyptian Labour Law No. 12/2003</li>
  <li>${icon('shield-plus', { size: 18 })} ISO 45001-aligned programmes</li>
  <li>${icon('activity', { size: 18 })} ERC first aid &amp; resuscitation guidelines</li>
  <li>${icon('globe', { size: 18 })} Arabic &amp; English delivery</li>
  <li>${icon('map-pin', { size: 18 })} On-site nationwide</li>
</ul>`;

const processGrid = () => `
<div class="process">
  ${process
    .map(
      (p, i) => `
  <article class="process__step" data-reveal data-reveal-delay="${(i % 4) + 1}">
    <span class="process__num">${esc(p.step)}</span>
    <h3>${esc(p.title)}</h3>
    <p>${esc(p.body)}</p>
  </article>`
    )
    .join('')}
</div>`;

const industryGrid = (list = industries) => `
<div class="grid grid--3">
  ${list
    .map(
      (n, i) => `
  <article class="industry" data-reveal data-reveal-delay="${(i % 3) + 1}">
    <span class="industry__icon">${icon(n.icon, { size: 22 })}</span>
    <div>
      <h3>${esc(n.title)}</h3>
      <p>${esc(n.body)}</p>
    </div>
  </article>`
    )
    .join('')}
</div>`;

const quoteFigure = (t) => `
<figure class="quote-block" data-reveal>
  ${icon('quote', { size: 28 })}
  <blockquote>${esc(t.quote)}</blockquote>
  <figcaption>
    <span class="quote-avatar" aria-hidden="true">${esc(t.name.charAt(0))}</span>
    <span><strong>${esc(t.name)}</strong><br>${esc(t.role)}</span>
  </figcaption>
</figure>`;

/* ================================================================== HOME === */

export function home() {
  const body = `
<section class="hero">
  <div class="container hero__grid">
    <div>
      <p class="kicker" data-reveal>Occupational health · Egypt</p>
      <h1 class="hero__title" data-reveal data-reveal-delay="1">
        Keep your people <em>fit for the work</em> they actually do.
      </h1>
      <p class="hero__lead" data-reveal data-reveal-delay="2">
        Occuo Health designs and runs occupational health programmes for Egyptian employers —
        medical surveillance, ergonomics, mental health, emergency readiness and crisis planning.
        Scoped from your real exposures, delivered on your site, measured against a baseline.
      </p>
      <div class="hero__actions" data-reveal data-reveal-delay="3">
        <a class="btn btn--accent btn--lg" href="${url('/contact/')}">Request a proposal ${icon('arrow-right', { size: 17 })}</a>
        <a class="btn btn--ghost btn--lg" href="${url('/services/')}">Explore services</a>
      </div>
      <ul class="hero__trust" data-reveal data-reveal-delay="4">
        <li>${icon('check', { size: 17 })} On-site mobile teams</li>
        <li>${icon('check', { size: 17 })} Qualified occupational physicians</li>
        <li>${icon('check', { size: 17 })} Audit-ready records</li>
      </ul>
    </div>

    <div class="hero__visual" data-reveal data-reveal-delay="2">
      <div class="hero-card">
        <div class="hero-card__head">
          <h3>Surveillance programme</h3>
          <span class="pill">${icon('activity', { size: 14 })} Live</span>
        </div>
        <div class="hero-card__rows">
          <div class="hero-row">
            <span class="hero-row__icon">${icon('stethoscope', { size: 18 })}</span>
            <span class="hero-row__label">Periodic medicals — Line 3<span>142 employees · noise &amp; solvent exposure</span></span>
            <span class="hero-row__status">Complete</span>
          </div>
          <div class="hero-row">
            <span class="hero-row__icon">${icon('activity', { size: 18 })}</span>
            <span class="hero-row__label">Audiometry re-test<span>9 employees flagged at baseline</span></span>
            <span class="hero-row__status hero-row__status--due">Due</span>
          </div>
          <div class="hero-row">
            <span class="hero-row__icon">${icon('hard-hat', { size: 18 })}</span>
            <span class="hero-row__label">Confined-space fitness<span>Certification valid to Q3</span></span>
            <span class="hero-row__status">Complete</span>
          </div>
          <div class="hero-row">
            <span class="hero-row__icon">${icon('shield-plus', { size: 18 })}</span>
            <span class="hero-row__label">ERC first aid refresher<span>Emergency response team · 18 seats</span></span>
            <span class="hero-row__status hero-row__status--due">Scheduled</span>
          </div>
        </div>
      </div>
      <div class="hero-badge hero-badge--br">
        ${icon('lock', { size: 22 })}
        <span><strong>Clinical detail stays private</strong>Employers see fitness decisions only</span>
      </div>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container">${complianceStrip()}</div>
</section>

<section class="section section--alt">
  <div class="container">
    ${sectionHead({
      kicker: 'What we do',
      title: 'Eight programmes, one occupational health partner',
      lead: 'Each one can be bought on its own. Run together, they share a single employee record, a single reporting calendar and a single point of accountability.',
    })}
    <div class="grid grid--4">${services.map(serviceCard).join('')}</div>
    <p style="margin-top:2rem" data-reveal>
      <a class="link-arrow" href="${url('/services/')}">Compare all services in detail ${icon('arrow-right', { size: 16 })}</a>
    </p>
  </div>
</section>

<section class="section section--inverse">
  <div class="container">
    ${sectionHead({
      kicker: 'Why Occuo Health',
      title: 'Occupational health that changes the workplace, not just the filing cabinet',
      lead: 'Most providers sell examinations. We sell the reduction in risk that the examinations are supposed to produce — and we report on whether it happened.',
      light: true,
    })}
    <div class="grid grid--3">
      ${differentiators
        .map(
          (d, i) => `
      <article class="card" style="background:rgb(255 255 255 / .05); border-color:rgb(255 255 255 / .12)" data-reveal data-reveal-delay="${(i % 3) + 1}">
        <span class="card__icon" style="background:rgb(255 255 255 / .1); color:#fff">${icon(d.icon, { size: 22 })}</span>
        <h3 style="color:#fff">${esc(d.title)}</h3>
        <p style="color:rgb(255 255 255 / .74)">${esc(d.body)}</p>
      </article>`
        )
        .join('')}
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${sectionHead({
      kicker: 'How we work',
      title: 'Assess before you buy anything',
      lead: 'A five-stage cycle that starts with your hazards and ends with a review against the numbers we set at the beginning.',
      align: 'center',
    })}
    ${processGrid()}
  </div>
</section>

<section class="section section--sunken">
  <div class="container">${statsBand()}</div>
</section>

<section class="section">
  <div class="container">
    ${sectionHead({
      kicker: 'Sectors',
      title: 'Built around how each sector actually works',
      lead: 'A construction site at peak phase, a rotating-shift plant and a corporate head office share almost none of the same risks. The programme should not be the same either.',
    })}
    ${industryGrid(industries.slice(0, 6))}
    <p style="margin-top:2rem" data-reveal>
      <a class="link-arrow" href="${url('/industries/')}">See all sectors we work in ${icon('arrow-right', { size: 16 })}</a>
    </p>
  </div>
</section>

<section class="section section--alt">
  <div class="container">
    <div class="split">
      <div>
        ${sectionHead({
          kicker: 'In practice',
          title: 'What clients say',
          lead: 'The programmes that work are the ones that survive contact with a live operation — a running production line, a turnaround, a night shift.',
        })}
        <ul class="checklist" data-reveal>
          <li>${icon('check', { size: 14 })} Mobilisation in two to three weeks for a standard scope</li>
          <li>${icon('check', { size: 14 })} Examinations scheduled around your shift pattern, not ours</li>
          <li>${icon('check', { size: 14 })} Reporting clean enough to hand to a parent-company audit</li>
        </ul>
      </div>
      <div class="grid" style="gap:1rem">
        ${testimonials.map(quoteFigure).join('')}
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container container--narrow">
    ${sectionHead({
      kicker: 'Questions',
      title: 'The things employers ask us first',
      align: 'center',
    })}
    ${accordion(faqs.slice(0, 5), { id: 'home-faq' })}
    <p style="margin-top:2rem;text-align:center" data-reveal>
      <a class="link-arrow" href="${url('/faq/')}">Read all questions and answers ${icon('arrow-right', { size: 16 })}</a>
    </p>
  </div>
</section>`;

  return {
    path: '/',
    title: `${site.name} — Occupational Health Services in Egypt`,
    description:
      'Occupational health for Egyptian employers: medical surveillance, ergonomics, mental health, emergency readiness and crisis planning — scoped to your real exposures.',
    body,
    jsonLd: organisationLd() + faqLd(faqs.slice(0, 5)),
  };
}

/* ======================================================== SERVICES (hub) === */

export function servicesIndex() {
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'Services', href: '/services/' },
  ];

  const body = `
${pageHero({
  kicker: 'Services',
  title: 'Occupational health programmes,<br>scoped to your exposures',
  lead: 'Eight programmes covering the full occupational health obligation — from statutory medical fitness through to crisis planning. Buy one, or run them as a single contract with one employee record and one reporting calendar.',
  trail,
  actions: `
    <a class="btn btn--accent" href="${url('/contact/')}">Request a proposal ${icon('arrow-right', { size: 16 })}</a>
    <a class="btn btn--ghost" href="tel:${esc(site.contact.phoneHref)}">${icon('phone', { size: 16 })} Speak to us</a>`,
})}

<section class="section">
  <div class="container">
    ${services
      .map(
        (s, i) => `
    <article class="service-row" data-reveal>
      <span class="service-row__num">${String(i + 1).padStart(2, '0')}</span>
      <div>
        <h2 class="service-row__title">${icon(s.icon, { size: 24 })} ${esc(s.title)}</h2>
        <p style="margin-top:.4rem;font-size:.9375rem">${esc(s.short)}</p>
      </div>
      <p>${esc(s.summary)}</p>
      <a class="btn btn--ghost btn--sm" href="${url(`/services/${s.slug}/`)}">Details ${icon('arrow-right', { size: 15 })}</a>
    </article>`
      )
      .join('')}
  </div>
</section>

<section class="section section--sunken">
  <div class="container">
    ${sectionHead({
      kicker: 'How engagement works',
      title: 'From first call to first delivery day',
      align: 'center',
    })}
    ${processGrid()}
  </div>
</section>

<section class="section">
  <div class="container container--narrow">
    ${sectionHead({ kicker: 'Questions', title: 'Before you shortlist a provider', align: 'center' })}
    ${accordion(faqs, { id: 'svc-faq' })}
  </div>
</section>`;

  return {
    path: '/services/',
    title: `Occupational Health Services in Egypt — ${site.name}`,
    description:
      'Health surveillance, emergency readiness, ergonomics, mental health, sleep management, smoking cessation, coaching and crisis planning for employers in Egypt.',
    body,
    jsonLd: breadcrumbLd(trail) + faqLd(faqs),
  };
}

/* ===================================================== SERVICE (detail) === */

export function serviceDetail(service) {
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'Services', href: '/services/' },
    { label: service.title, href: `/services/${service.slug}/` },
  ];

  const related = services.filter((s) => s.slug !== service.slug).slice(0, 3);
  const sectionId = (t) => t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

  const body = `
${pageHero({
  kicker: 'Service',
  title: esc(service.title),
  lead: esc(service.summary),
  trail,
  actions: `<a class="btn btn--accent" href="${url('/contact/')}?service=${encodeURIComponent(service.slug)}">Request a proposal ${icon('arrow-right', { size: 16 })}</a>`,
  aside: `
    <div class="panel panel--brand">
      <p class="kicker kicker--light" style="margin-bottom:.6rem">In one line</p>
      <p style="font-size:1.0625rem;line-height:1.55">${esc(service.highlight)}</p>
    </div>`,
})}

<section class="section">
  <div class="container with-aside">
    <div>
      <div class="prose" data-reveal>
        <p class="lead">${esc(service.intro)}</p>
      </div>

      ${service.sections
        .map(
          (sec) => `
      <section id="${sectionId(sec.title)}" style="margin-top:clamp(2.5rem,5vw,3.5rem)" data-reveal>
        <h2 style="font-size:var(--step-3)">${esc(sec.title)}</h2>
        <p style="color:var(--text-muted);max-width:62ch">${esc(sec.body)}</p>
        <ul class="checklist" style="margin-top:1.25rem">
          ${sec.bullets.map((b) => `<li>${icon('check', { size: 14 })} ${esc(b)}</li>`).join('')}
        </ul>
      </section>`
        )
        .join('')}

      <section id="questions" style="margin-top:clamp(2.5rem,5vw,3.5rem)" data-reveal>
        <h2 style="font-size:var(--step-3)">Common questions</h2>
        ${accordion(service.faq, { id: `${service.slug}-faq` })}
      </section>

      <div class="callout" style="margin-top:2.5rem" data-reveal>
        ${icon('map-pin', { size: 20 })}
        <p><strong>Delivered on your site.</strong> ${esc(service.title)} is normally delivered at your location and scheduled around your shift pattern. Where specialist diagnostics are required, referrals run through our clinic network.</p>
      </div>
    </div>

    <aside>
      <nav class="toc" aria-label="On this page">
        <h2>On this page</h2>
        <ul>
          ${service.sections.map((sec) => `<li><a href="#${sectionId(sec.title)}">${esc(sec.title)}</a></li>`).join('')}
          <li><a href="#questions">Common questions</a></li>
        </ul>
      </nav>

      <div class="panel panel--sand" style="margin-top:1.25rem">
        <h3 style="font-size:var(--step-1);margin-bottom:.6rem">Talk it through</h3>
        <p style="font-size:var(--step--1);color:var(--text-muted);margin-bottom:1rem">
          Tell us your headcount and your main exposures and we will tell you what this programme would involve for you.
        </p>
        <a class="btn btn--primary btn--sm btn--block" href="${url('/contact/')}">Request a proposal</a>
        <a class="btn btn--ghost btn--sm btn--block" style="margin-top:.5rem" href="tel:${esc(site.contact.phoneHref)}">${icon('phone', { size: 15 })} ${esc(site.contact.phone)}</a>
      </div>
    </aside>
  </div>
</section>

<section class="section section--sunken">
  <div class="container">
    ${sectionHead({ kicker: 'Related', title: 'Programmes that work well alongside this' })}
    <div class="related">${related.map(serviceCard).join('')}</div>
  </div>
</section>`;

  return {
    path: `/services/${service.slug}/`,
    title: `${service.title} | ${site.name} Egypt`,
    description: service.summary.slice(0, 158),
    body,
    jsonLd:
      breadcrumbLd(trail) +
      faqLd(service.faq) +
      ld({
        '@context': 'https://schema.org',
        '@type': 'Service',
        name: service.title,
        description: service.summary,
        serviceType: 'Occupational health',
        provider: { '@id': `${site.url}/#organisation` },
        areaServed: { '@type': 'Country', name: 'Egypt' },
        url: `${site.url}/services/${service.slug}/`,
      }),
  };
}

/* ================================================================= ABOUT === */

export function aboutPage() {
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'About', href: '/about/' },
  ];

  const body = `
${pageHero({
  kicker: 'About us',
  title: 'We were built to fix the gap<br>between compliance and health',
  lead: about.mission,
  trail,
})}

<section class="section">
  <div class="container split">
    <div class="prose" data-reveal>
      <p class="kicker">Our story</p>
      <h2 style="font-size:var(--step-3)">Why we structure the work backwards</h2>
      ${about.story.map((p) => `<p style="color:var(--text-muted)">${esc(p)}</p>`).join('')}
    </div>
    <div class="grid" style="gap:1rem" data-reveal data-reveal-delay="2">
      <div class="panel panel--brand">
        <p class="kicker kicker--light" style="margin-bottom:.6rem">Vision</p>
        <p style="font-size:var(--step-1);line-height:1.55">${esc(about.vision)}</p>
      </div>
      <div class="panel panel--sand">
        <p class="kicker" style="margin-bottom:.6rem">What we commit to</p>
        <ul class="checklist">
          ${about.commitments.map((c) => `<li>${icon('check', { size: 14 })} ${esc(c)}</li>`).join('')}
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section section--sunken">
  <div class="container">${statsBand()}</div>
</section>

<section class="section" id="approach">
  <div class="container">
    ${sectionHead({
      kicker: 'Our approach',
      title: 'Five stages, and a review that can say “this did not work”',
      lead: 'The review stage is the one most providers leave out. It is also the only stage that tells you whether the previous four were worth paying for.',
      align: 'center',
    })}
    ${processGrid()}
  </div>
</section>

<section class="section section--alt">
  <div class="container">
    ${sectionHead({
      kicker: 'What we stand on',
      title: 'Four principles we do not trade against a contract',
    })}
    <div class="grid grid--2">
      ${values
        .map(
          (v, i) => `
      <article class="card" data-reveal data-reveal-delay="${(i % 2) + 1}">
        <h3>${esc(v.title)}</h3>
        <p>${esc(v.body)}</p>
      </article>`
        )
        .join('')}
    </div>
  </div>
</section>

<section class="section section--inverse">
  <div class="container">
    ${sectionHead({
      kicker: 'Why employers choose us',
      title: 'The differences that show up in year two',
      light: true,
    })}
    <div class="grid grid--3">
      ${differentiators
        .map(
          (d, i) => `
      <article class="card" style="background:rgb(255 255 255 / .05); border-color:rgb(255 255 255 / .12)" data-reveal data-reveal-delay="${(i % 3) + 1}">
        <span class="card__icon" style="background:rgb(255 255 255 / .1); color:#fff">${icon(d.icon, { size: 22 })}</span>
        <h3 style="color:#fff">${esc(d.title)}</h3>
        <p style="color:rgb(255 255 255 / .74)">${esc(d.body)}</p>
      </article>`
        )
        .join('')}
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${sectionHead({ kicker: 'Services', title: 'What we deliver', align: 'center' })}
    <div class="grid grid--4">${services.map(serviceCard).join('')}</div>
  </div>
</section>`;

  return {
    path: '/about/',
    title: `About ${site.name} — Occupational Health in Egypt`,
    description:
      'Occuo Health builds occupational health programmes for Egyptian employers around real workplace exposures — rigorous, practical, and measured against outcomes.',
    body,
    jsonLd: breadcrumbLd(trail),
  };
}

/* ============================================================ INDUSTRIES === */

export function industriesPage() {
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'Industries', href: '/industries/' },
  ];

  const body = `
${pageHero({
  kicker: 'Industries',
  title: 'The same obligation.<br>Very different hazards.',
  lead: 'Occupational health obligations are written generally and applied specifically. These are the sectors we work in most, and what usually dominates the risk picture in each.',
  trail,
})}

<section class="section">
  <div class="container">${industryGrid()}</div>
</section>

<section class="section section--alt">
  <div class="container split">
    <div data-reveal>
      ${sectionHead({
        kicker: 'Multi-site employers',
        title: 'One protocol set. One employee record. One reporting calendar.',
        lead: 'Programmes that were designed for a single plant tend to fracture when they are scaled to five. We define up front what is held centrally and what each site owns.',
      })}
      <ul class="checklist">
        <li>${icon('check', { size: 14 })} A shared protocol library, versioned centrally</li>
        <li>${icon('check', { size: 14 })} Site-level scheduling with a group reporting cycle</li>
        <li>${icon('check', { size: 14 })} Consistent fitness criteria across every location</li>
        <li>${icon('check', { size: 14 })} Group-level trend reporting for central HSE and HR</li>
        <li>${icon('check', { size: 14 })} A single point of contact for contract and escalation</li>
      </ul>
    </div>
    <div class="panel panel--brand" data-reveal data-reveal-delay="2">
      <p class="kicker kicker--light" style="margin-bottom:.6rem">Contractor and turnaround cover</p>
      <h3 style="color:#fff">Short-duration, high-density work</h3>
      <p>Shutdowns, turnarounds and construction peaks concentrate risk into a few weeks and multiply headcount on site. We mobilise temporary site clinics, standby ambulance cover and contractor medical screening for exactly that window.</p>
      <ul class="checklist checklist--light" style="margin-top:1.25rem">
        <li>${icon('check', { size: 14 })} Contractor fitness screening at gate-in</li>
        <li>${icon('check', { size: 14 })} Temporary site clinic set-up and staffing</li>
        <li>${icon('check', { size: 14 })} Standby paramedic and ambulance cover</li>
        <li>${icon('check', { size: 14 })} Demobilisation records handed over on close-out</li>
      </ul>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${sectionHead({ kicker: 'Programmes', title: 'Match a sector risk to a programme', align: 'center' })}
    <div class="grid grid--4">${services.map(serviceCard).join('')}</div>
  </div>
</section>`;

  return {
    path: '/industries/',
    title: `Industries We Serve — Occupational Health Egypt | ${site.name}`,
    description:
      'Occupational health for manufacturing, construction, oil and gas, pharmaceutical, logistics, hospitality, energy and corporate employers across Egypt.',
    body,
    jsonLd: breadcrumbLd(trail),
  };
}

/* =============================================================== CONTACT === */

export function contactPage() {
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'Contact', href: '/contact/' },
  ];

  const body = `
${pageHero({
  kicker: 'Contact',
  title: 'Tell us about your site',
  lead: 'Send us your headcount, sector and main exposures. You will get a scoped proposal — not a rate card — normally within two working days.',
  trail,
})}

<section class="section">
  <div class="container with-aside">
    <div data-reveal>
      <h2 style="font-size:var(--step-3)">Request a proposal</h2>
      <p class="lead" style="margin-bottom:2rem">Every field marked with an asterisk helps us scope properly. The more we know about the exposures, the less generic the proposal.</p>

      <form class="form" id="enquiry-form" novalidate
            data-mailto="${esc(site.contact.salesEmail)}"
            method="post" action="#">
        <div class="form__row form__row--2">
          <div class="field">
            <label for="f-name">Your name <span aria-hidden="true">*</span></label>
            <input id="f-name" name="name" type="text" autocomplete="name" required minlength="2">
            <p class="field__error" aria-live="polite"></p>
          </div>
          <div class="field">
            <label for="f-company">Company <span aria-hidden="true">*</span></label>
            <input id="f-company" name="company" type="text" autocomplete="organization" required minlength="2">
            <p class="field__error" aria-live="polite"></p>
          </div>
        </div>

        <div class="form__row form__row--2">
          <div class="field">
            <label for="f-email">Work email <span aria-hidden="true">*</span></label>
            <input id="f-email" name="email" type="email" autocomplete="email" required>
            <p class="field__error" aria-live="polite"></p>
          </div>
          <div class="field">
            <label for="f-phone">Phone</label>
            <input id="f-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel">
            <p class="field__error" aria-live="polite"></p>
          </div>
        </div>

        <div class="form__row form__row--2">
          <div class="field">
            <label for="f-headcount">Employees on site</label>
            <select id="f-headcount" name="headcount">
              <option value="">Select a range</option>
              <option>Fewer than 50</option>
              <option>50 – 200</option>
              <option>200 – 500</option>
              <option>500 – 1,000</option>
              <option>More than 1,000</option>
              <option>Multiple sites</option>
            </select>
            <p class="field__error" aria-live="polite"></p>
          </div>
          <div class="field">
            <label for="f-service">Service of interest</label>
            <select id="f-service" name="service">
              <option value="">Not sure yet — advise me</option>
              ${services.map((s) => `<option value="${esc(s.slug)}">${esc(s.title)}</option>`).join('')}
              <option value="multiple">A combined programme</option>
            </select>
            <p class="field__error" aria-live="polite"></p>
          </div>
        </div>

        <div class="field">
          <label for="f-message">What are your main exposures or concerns? <span aria-hidden="true">*</span></label>
          <textarea id="f-message" name="message" required minlength="10"
                    placeholder="e.g. Rotating-shift plant, noise on two production lines, solvent exposure in finishing, and an upcoming client HSE audit."></textarea>
          <p class="field__hint">Noise, chemicals, dust, manual handling, shift patterns, work at height, audit deadlines — anything that shapes the scope.</p>
          <p class="field__error" aria-live="polite"></p>
        </div>

        <!-- Honeypot: hidden from people, irresistible to bots. -->
        <div class="visually-hidden" aria-hidden="true">
          <label for="f-website">Company website</label>
          <input id="f-website" name="company_website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <label class="checkbox">
          <input type="checkbox" name="consent" required>
          <span>I agree that Occuo Health may use these details to respond to my enquiry, as described in the <a href="${url('/privacy/')}">privacy notice</a>. <span aria-hidden="true">*</span></span>
        </label>

        <p class="form__status" id="form-status" role="status" hidden></p>

        <div>
          <button class="btn btn--accent btn--lg" type="submit">${icon('send', { size: 17 })} Send enquiry</button>
        </div>
      </form>
    </div>

    <aside>
      <div class="contact-cards">
        <div class="contact-card">
          <span class="contact-card__icon">${icon('phone', { size: 20 })}</span>
          <div>
            <h3>Call us</h3>
            <a href="tel:${esc(site.contact.phoneHref)}">${esc(site.contact.phone)}</a>
            <p style="margin-top:.25rem">${esc(site.contact.hours)}</p>
          </div>
        </div>
        <div class="contact-card">
          <span class="contact-card__icon">${icon('mail', { size: 20 })}</span>
          <div>
            <h3>Email</h3>
            <a href="mailto:${esc(site.contact.email)}">${esc(site.contact.email)}</a><br>
            <a href="mailto:${esc(site.contact.salesEmail)}">${esc(site.contact.salesEmail)}</a>
          </div>
        </div>
        <div class="contact-card">
          <span class="contact-card__icon">${icon('whatsapp', { size: 20 })}</span>
          <div>
            <h3>WhatsApp</h3>
            <a href="https://wa.me/${esc(site.contact.whatsapp.replace(/\D/g, ''))}" rel="noopener noreferrer" target="_blank">Message us</a>
            <p style="margin-top:.25rem">Fastest for scheduling questions</p>
          </div>
        </div>
        <div class="contact-card">
          <span class="contact-card__icon">${icon('map-pin', { size: 20 })}</span>
          <div>
            <h3>Office</h3>
            <p>${site.contact.addressLines.map(esc).join('<br>')}</p>
          </div>
        </div>
      </div>

      <div class="callout" style="margin-top:1.25rem">
        ${icon('siren', { size: 20 })}
        <p><strong>Urgent site cover?</strong> ${esc(site.contact.emergencyNote)} Call rather than email.</p>
      </div>
    </aside>
  </div>
</section>

<section class="section section--sunken">
  <div class="container container--narrow">
    ${sectionHead({ kicker: 'Before you write', title: 'Questions we can answer right now', align: 'center' })}
    ${accordion(faqs.slice(0, 4), { id: 'contact-faq' })}
  </div>
</section>`;

  return {
    path: '/contact/',
    title: `Contact ${site.name} — Request an Occupational Health Proposal`,
    description:
      'Request a scoped occupational health proposal for your site in Egypt. Tell us your headcount, sector and exposures — we reply within two working days.',
    body,
    jsonLd:
      breadcrumbLd(trail) +
      ld({
        '@context': 'https://schema.org',
        '@type': 'ContactPage',
        name: `Contact ${site.name}`,
        url: `${site.url}/contact/`,
        mainEntity: { '@id': `${site.url}/#organisation` },
      }),
  };
}

/* =================================================================== FAQ === */

export function faqPage() {
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'FAQ', href: '/faq/' },
  ];

  const grouped = services.map((s) => ({ title: s.title, slug: s.slug, faq: s.faq }));

  const body = `
${pageHero({
  kicker: 'Questions & answers',
  title: 'Everything employers ask us,<br>answered in one place',
  lead: 'General questions about how occupational health works in Egypt, followed by the specific questions we get about each programme.',
  trail,
})}

<section class="section">
  <div class="container with-aside">
    <div>
      <section id="general" data-reveal>
        <h2 style="font-size:var(--step-3)">General</h2>
        ${accordion(faqs, { id: 'general-faq' })}
      </section>

      ${grouped
        .map(
          (g) => `
      <section id="${esc(g.slug)}" style="margin-top:clamp(2.5rem,5vw,3.5rem)" data-reveal>
        <h2 style="font-size:var(--step-3)">${esc(g.title)}</h2>
        ${accordion(g.faq, { id: `${g.slug}-page-faq` })}
        <p style="margin-top:1.25rem">
          <a class="link-arrow" href="${url(`/services/${g.slug}/`)}">About ${esc(g.title)} ${icon('arrow-right', { size: 16 })}</a>
        </p>
      </section>`
        )
        .join('')}
    </div>

    <aside>
      <nav class="toc" aria-label="On this page">
        <h2>Jump to</h2>
        <ul>
          <li><a href="#general">General</a></li>
          ${grouped.map((g) => `<li><a href="#${esc(g.slug)}">${esc(g.title)}</a></li>`).join('')}
        </ul>
      </nav>
      <div class="panel panel--sand" style="margin-top:1.25rem">
        <h3 style="font-size:var(--step-1);margin-bottom:.6rem">Still unanswered?</h3>
        <p style="font-size:var(--step--1);color:var(--text-muted);margin-bottom:1rem">Send us the question directly — we will answer it whether or not it turns into work.</p>
        <a class="btn btn--primary btn--sm btn--block" href="${url('/contact/')}">Ask us</a>
      </div>
    </aside>
  </div>
</section>`;

  return {
    path: '/faq/',
    title: `Occupational Health FAQ — Egypt | ${site.name}`,
    description:
      'Answers on employer obligations under Egyptian Labour Law No. 12/2003, on-site delivery, medical confidentiality, multi-site programmes and mobilisation times.',
    body,
    jsonLd:
      breadcrumbLd(trail) +
      faqLd([...faqs, ...services.flatMap((s) => s.faq)]),
  };
}

/* ============================================================ LEGAL / 404 === */

function legalPage({ path, title, heading, description, blocks }) {
  const trail = [
    { label: 'Home', href: '/' },
    { label: heading, href: path },
  ];

  const body = `
${pageHero({ kicker: 'Legal', title: esc(heading), lead: esc(description), trail })}

<section class="section">
  <div class="container container--narrow prose">
    <div class="callout" style="margin-bottom:2rem">
      ${icon('file', { size: 20 })}
      <p><strong>Template document.</strong> This notice is a starting point drafted for a general occupational health provider. Have it reviewed against your actual data practices and Egyptian law before publication.</p>
    </div>
    ${blocks
      .map((b) => `<h2 style="font-size:var(--step-2)">${esc(b.h)}</h2>${b.p.map((t) => `<p style="color:var(--text-muted)">${esc(t)}</p>`).join('')}`)
      .join('')}
    <p style="color:var(--text-muted);margin-top:2rem"><em>Last updated: <span data-current-year>2026</span>. Questions about this notice can be sent to <a href="mailto:${esc(site.contact.email)}">${esc(site.contact.email)}</a>.</em></p>
  </div>
</section>`;

  return { path, title, description, body, jsonLd: breadcrumbLd(trail) };
}

export function privacyPage() {
  return legalPage({
    path: '/privacy/',
    heading: 'Privacy notice',
    title: `Privacy Notice — ${site.name}`,
    description: 'How Occuo Health handles personal data, website enquiry data and occupational health records.',
    blocks: [
      { h: 'Who we are', p: [`${site.legalName} provides occupational health services to employers in Egypt. This notice explains what personal data we collect through this website and how we handle it.`] },
      { h: 'Website enquiries', p: ['When you submit an enquiry we collect your name, company, email address, telephone number and the details you choose to include about your site. We use this only to respond to your enquiry and to prepare a proposal.', 'We do not sell enquiry data, and we do not use it for unrelated marketing without your consent.'] },
      { h: 'Occupational health records', p: ['Clinical records created during medical examinations are held separately from website data, under medical confidentiality. They are accessible to the examining clinician and, where required, to the employee.', 'Employers receive fitness-to-work determinations and anonymised aggregate reporting. They do not receive underlying clinical findings.', 'Records are retained for the period required by the applicable regulations for the exposure concerned, and securely destroyed afterwards.'] },
      { h: 'Analytics and cookies', p: ['This website sets no advertising or tracking cookies. Your theme preference (light or dark) is stored in your browser using local storage and is never transmitted to us.', 'If analytics are introduced in future, this notice will be updated before they are enabled.'] },
      { h: 'Your rights', p: ['You may request access to the personal data we hold about you, ask for it to be corrected, or ask for it to be deleted where we are not required to retain it. Requests relating to occupational health records may be subject to clinical and legal retention requirements.'] },
      { h: 'Contact', p: [`Data protection questions can be sent to ${site.contact.email}.`] },
    ],
  });
}

export function termsPage() {
  return legalPage({
    path: '/terms/',
    heading: 'Terms of use',
    title: `Terms of Use — ${site.name}`,
    description: 'Terms governing use of the Occuo Health website and the status of the information published on it.',
    blocks: [
      { h: 'Website content', p: ['The information on this website describes the services we offer. It is general information about occupational health practice and is not medical advice for any individual, nor a legal opinion on your obligations as an employer.'] },
      { h: 'No clinician–patient relationship', p: ['Reading this website does not create a clinician–patient relationship. Individual health concerns should be raised with a qualified clinician. Fitness-to-work determinations are made only after an examination.'] },
      { h: 'Regulatory references', p: ['References to Egyptian Labour Law No. 12 of 2003, ISO 45001 and resuscitation guidelines are provided for orientation. The obligations that apply to your organisation depend on your sector, headcount and hazard profile, and should be confirmed for your specific circumstances.'] },
      { h: 'Service scope', p: ['Nothing on this website constitutes an offer. Services, deliverables and timescales are defined in a written proposal and contract.'] },
      { h: 'Intellectual property', p: [`The content, design and code of this website are the property of ${site.legalName} unless otherwise stated.`] },
      { h: 'Third-party links', p: ['We are not responsible for the content of external websites we link to.'] },
    ],
  });
}

export function notFoundPage() {
  const body = `
<section class="section" style="padding-block:clamp(4rem,10vw,8rem)">
  <div class="container container--narrow" style="text-align:center">
    <p class="kicker" style="justify-content:center">Error 404</p>
    <h1 style="font-size:var(--step-5)">This page has moved on</h1>
    <p class="lead" style="margin-inline:auto;max-width:52ch">
      The address you followed does not exist on this site. It may have been renamed, or the link may be out of date.
    </p>
    <div class="hero__actions" style="justify-content:center">
      <a class="btn btn--accent btn--lg" href="${url('/')}">Back to the homepage</a>
      <a class="btn btn--ghost btn--lg" href="${url('/services/')}">Browse services</a>
    </div>
    <div class="grid grid--4" style="margin-top:3.5rem;text-align:start">
      ${services.slice(0, 4).map(serviceCard).join('')}
    </div>
  </div>
</section>`;

  return {
    path: '/404.html',
    title: `Page not found — ${site.name}`,
    description:
      'The page you were looking for could not be found. Browse Occuo Health occupational health services for employers in Egypt, or return to the homepage.',
    body,
    jsonLd: '',
  };
}
