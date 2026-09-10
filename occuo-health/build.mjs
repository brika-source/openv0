#!/usr/bin/env node
/**
 * Occuo Health — static site build.
 *
 * Zero dependencies. Reads content/site.js, renders every page through the
 * templates in src/lib, copies src/assets verbatim, and writes the result to
 * dist/ ready to upload to any static host.
 *
 *   node build.mjs                 → build to dist/
 *   node build.mjs --serve         → build, then serve dist/ on :4321
 *   BASE_PATH=/repo node build.mjs → build for a subdirectory host
 */

import { mkdir, rm, writeFile, readdir, copyFile, stat } from 'node:fs/promises';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { dirname, join, extname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

import { site, services } from './content/site.js';
import { page, BASE } from './src/lib/layout.mjs';
import * as tpl from './src/lib/pages.mjs';

const root = dirname(fileURLToPath(import.meta.url));
const SRC = join(root, 'src');
const DIST = join(root, 'dist');

/* ------------------------------------------------------------- page list --- */

const pages = [
  tpl.home(),
  tpl.servicesIndex(),
  ...services.map((s) => tpl.serviceDetail(s)),
  tpl.industriesPage(),
  tpl.aboutPage(),
  tpl.faqPage(),
  tpl.contactPage(),
  tpl.privacyPage(),
  tpl.termsPage(),
  tpl.notFoundPage(),
];

/* ------------------------------------------------------------- utilities --- */

/** Map a site path to a file inside dist/. '/about/' → 'about/index.html'. */
function outputFile(path) {
  if (path.endsWith('.html')) return path.replace(/^\//, '');
  return join(path.replace(/^\//, ''), 'index.html');
}

async function writeOut(relPath, contents) {
  const target = join(DIST, relPath);
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, contents, 'utf8');
  return target;
}

async function copyDir(from, to) {
  await mkdir(to, { recursive: true });
  let count = 0;
  for (const entry of await readdir(from, { withFileTypes: true })) {
    const src = join(from, entry.name);
    const dest = join(to, entry.name);
    if (entry.isDirectory()) count += await copyDir(src, dest);
    else {
      await copyFile(src, dest);
      count += 1;
    }
  }
  return count;
}

/* ------------------------------------------------------- generated files --- */

function sitemap() {
  const today = new Date().toISOString().slice(0, 10);
  const priority = (p) => (p === '/' ? '1.0' : p.startsWith('/services/') ? '0.8' : '0.6');
  const entries = pages
    .filter((p) => !p.path.endsWith('.html'))
    .map(
      (p) => `  <url>
    <loc>${site.url}${p.path}</loc>
    <lastmod>${today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>${priority(p.path)}</priority>
  </url>`
    )
    .join('\n');

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${entries}
</urlset>
`;
}

const robots = () => `User-agent: *
Allow: /

Sitemap: ${site.url}/sitemap.xml
`;

const manifest = () =>
  JSON.stringify(
    {
      name: `${site.name} — Occupational Health Egypt`,
      short_name: site.name,
      description: site.description,
      start_url: `${BASE}/`,
      scope: `${BASE}/`,
      display: 'standalone',
      background_color: '#fcfaf6',
      theme_color: '#06322e',
      lang: 'en',
      icons: [
        { src: `${BASE}/assets/img/favicon.svg`, sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        { src: `${BASE}/assets/img/icon-192.png`, sizes: '192x192', type: 'image/png' },
        { src: `${BASE}/assets/img/icon-512.png`, sizes: '512x512', type: 'image/png' },
      ],
    },
    null,
    2
  );

/**
 * Netlify/Vercel-style redirect + header hints. Harmless on hosts that
 * ignore them, useful on the ones that do not.
 */
const netlifyFiles = () => ({
  '_redirects': `/index.html   /   301\n/*  /404.html  404\n`,
  '_headers': `/*
  X-Content-Type-Options: nosniff
  Referrer-Policy: strict-origin-when-cross-origin
  X-Frame-Options: SAMEORIGIN
  Permissions-Policy: geolocation=(), microphone=(), camera=()

/assets/*
  Cache-Control: public, max-age=31536000, immutable
`,
});

/* ------------------------------------------------------------------ build --- */

async function build() {
  const t0 = Date.now();
  await rm(DIST, { recursive: true, force: true });
  await mkdir(DIST, { recursive: true });

  // 1. Pages
  const seen = new Set();
  for (const p of pages) {
    if (seen.has(p.path)) throw new Error(`Duplicate page path: ${p.path}`);
    seen.add(p.path);
    await writeOut(outputFile(p.path), page(p));
  }

  // 2. Assets
  const assetCount = await copyDir(join(SRC, 'assets'), join(DIST, 'assets'));

  // 3. Root files
  await writeOut('sitemap.xml', sitemap());
  await writeOut('robots.txt', robots());
  await writeOut('site.webmanifest', manifest());
  for (const [name, contents] of Object.entries(netlifyFiles())) await writeOut(name, contents);
  // GitHub Pages otherwise strips directories beginning with an underscore.
  await writeOut('.nojekyll', '');

  console.log(
    `built ${pages.length} pages + ${assetCount} assets → dist/  (${Date.now() - t0}ms)` +
      (BASE ? `  base=${BASE}` : '')
  );
  return pages.length;
}

/* ------------------------------------------------------------ dev server --- */

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.webp': 'image/webp',
  '.xml': 'application/xml; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.webmanifest': 'application/manifest+json',
};

function serve(port = 4321) {
  createServer(async (req, res) => {
    try {
      let path = decodeURIComponent(new URL(req.url, 'http://x').pathname);
      if (BASE && path.startsWith(BASE)) path = path.slice(BASE.length) || '/';
      let file = join(DIST, path);
      const info = await stat(file).catch(() => null);
      if (!info || info.isDirectory()) file = join(file, 'index.html');
      const data = await readFile(file);
      res.writeHead(200, { 'content-type': MIME[extname(file)] || 'application/octet-stream' });
      res.end(data);
    } catch {
      const notFound = await readFile(join(DIST, '404.html')).catch(() => 'Not found');
      res.writeHead(404, { 'content-type': 'text/html; charset=utf-8' });
      res.end(notFound);
    }
  }).listen(port, () => console.log(`serving dist/ → http://localhost:${port}${BASE}/`));
}

await build();
if (process.argv.includes('--serve')) serve(Number(process.env.PORT) || 4321);
