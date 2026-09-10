#!/usr/bin/env node
/**
 * Post-build sanity checks. Not a full HTML validator — it catches the
 * regressions that actually happen when content is edited: broken internal
 * links, missing metadata, unlabelled images, duplicate H1s, orphan pages.
 *
 *   node tools/check.mjs        (run after `npm run build`)
 */

import { readdir, readFile, stat } from 'node:fs/promises';
import { join, dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIST = join(dirname(fileURLToPath(import.meta.url)), '..', 'dist');
const problems = [];
const warnings = [];

async function walk(dir) {
  const out = [];
  for (const e of await readdir(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) out.push(...(await walk(p)));
    else out.push(p);
  }
  return out;
}

const files = await walk(DIST);
const htmlFiles = files.filter((f) => f.endsWith('.html'));
const exists = async (p) => !!(await stat(p).catch(() => null));

for (const file of htmlFiles) {
  const rel = relative(DIST, file);
  const html = await readFile(file, 'utf8');
  const fail = (msg) => problems.push(`${rel}: ${msg}`);
  const warn = (msg) => warnings.push(`${rel}: ${msg}`);

  /* --- required metadata --- */
  if (!/<title>[^<]{10,70}<\/title>/.test(html)) fail('missing or badly sized <title> (10–70 chars)');
  const desc = html.match(/<meta name="description" content="([^"]*)"/);
  if (!desc) fail('missing meta description');
  else if (desc[1].length < 50 || desc[1].length > 165) warn(`meta description is ${desc[1].length} chars (aim 50–165)`);
  if (!/rel="canonical"/.test(html)) fail('missing canonical link');
  if (!/property="og:image"/.test(html)) fail('missing og:image');
  if (!/<html lang="/.test(html)) fail('missing lang attribute');

  /* --- headings --- */
  const h1s = html.match(/<h1[\s>]/g) || [];
  if (h1s.length === 0) fail('no <h1>');
  if (h1s.length > 1) fail(`${h1s.length} <h1> elements (expected exactly 1)`);

  /* --- accessibility --- */
  for (const img of html.match(/<img\b[^>]*>/g) || []) {
    if (!/\balt=/.test(img)) fail(`<img> without alt: ${img.slice(0, 70)}`);
  }
  for (const a of html.match(/<a\b[^>]*>\s*<\/a>/g) || []) fail(`empty link: ${a.slice(0, 70)}`);
  if (!/class="skip-link"/.test(html)) warn('no skip link');

  /* --- internal links resolve to a real file --- */
  const hrefs = [...html.matchAll(/href="(\/[^"#?]*)"/g)].map((m) => m[1]);
  for (const href of new Set(hrefs)) {
    const target = href.endsWith('/') ? join(DIST, href, 'index.html') : join(DIST, href);
    if (!(await exists(target))) fail(`broken internal link → ${href}`);
  }

  /* --- structured data parses --- */
  for (const m of html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
    try {
      JSON.parse(m[1]);
    } catch (e) {
      fail(`invalid JSON-LD: ${e.message}`);
    }
  }

  /* --- leftover markers --- */
  const stripped = html.replace(/\splaceholder="[^"]*"/g, '');
  if (/PLACEHOLDER|TODO:|Lorem ipsum/.test(stripped)) warn('contains a PLACEHOLDER/TODO marker in rendered output');
}

/* --- sitemap covers every page --- */
const sitemap = await readFile(join(DIST, 'sitemap.xml'), 'utf8');
const listed = [...sitemap.matchAll(/<loc>([^<]*)<\/loc>/g)].map((m) =>
  m[1].replace(/^https?:\/\/[^/]+/, '')
);
for (const file of htmlFiles) {
  const rel = relative(DIST, file);
  if (rel === '404.html') continue;
  const path = '/' + rel.replace(/index\.html$/, '');
  if (!listed.includes(path)) problems.push(`sitemap.xml missing ${path}`);
}

/* --- report --- */
const label = (n, s) => `${n} ${s}${n === 1 ? '' : 's'}`;
console.log(`checked ${label(htmlFiles.length, 'page')}`);
if (warnings.length) {
  console.log(`\n${label(warnings.length, 'warning')}:`);
  warnings.forEach((w) => console.log(`  ! ${w}`));
}
if (problems.length) {
  console.log(`\n${label(problems.length, 'problem')}:`);
  problems.forEach((p) => console.log(`  ✗ ${p}`));
  process.exit(1);
}
console.log(problems.length ? '' : '\nno problems found');
