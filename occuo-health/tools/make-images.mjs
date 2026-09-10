#!/usr/bin/env node
/**
 * Brand asset generator — no dependencies.
 *
 * The build environment has no image tooling (ImageMagick, librsvg, Pillow),
 * so raster assets are drawn here directly: a minimal PNG encoder on top of
 * node:zlib, and a signed-distance-field renderer that gives clean analytic
 * anti-aliasing without supersampling.
 *
 *   node tools/make-images.mjs
 *
 * Outputs into src/assets/img/ (which build.mjs then copies to dist/).
 */

import { deflateSync } from 'node:zlib';
import { writeFile, mkdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'assets', 'img');

/* ----------------------------------------------------------- PNG encoder --- */

const CRC_TABLE = (() => {
  const t = new Int32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c;
  }
  return t;
})();

const crc32 = (buf) => {
  let c = -1;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
};

function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}

/** @param {Uint8Array} rgba  width*height*4 */
function encodePng(rgba, width, height) {
  const stride = width * 4;
  const raw = Buffer.alloc((stride + 1) * height);
  for (let y = 0; y < height; y++) {
    raw[y * (stride + 1)] = 0; // filter: none
    Buffer.from(rgba.buffer, rgba.byteOffset + y * stride, stride).copy(raw, y * (stride + 1) + 1);
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8;   // bit depth
  ihdr[9] = 6;   // colour type: RGBA
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

/* ------------------------------------------------------------- SDF canvas --- */

const hex = (h) => {
  const n = parseInt(h.replace('#', ''), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};
const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);
const clamp01 = (v) => (v < 0 ? 0 : v > 1 ? 1 : v);

class Canvas {
  constructor(w, h) {
    this.w = w;
    this.h = h;
    this.px = new Float64Array(w * h * 4); // straight RGBA, 0..255
  }

  /** Composite a colour over a pixel using coverage as alpha. */
  blend(x, y, rgb, cov) {
    if (cov <= 0) return;
    const i = (y * this.w + x) * 4;
    const a = clamp01(cov);
    const p = this.px;
    p[i] = p[i] * (1 - a) + rgb[0] * a;
    p[i + 1] = p[i + 1] * (1 - a) + rgb[1] * a;
    p[i + 2] = p[i + 2] * (1 - a) + rgb[2] * a;
    p[i + 3] = Math.max(p[i + 3], 255 * a);
  }

  /**
   * Fill every pixel where `sdf(x, y) < 0`, anti-aliased across the boundary.
   * `colorAt` may be a constant RGB triple or a function of (x, y).
   */
  fill(sdf, colorAt, alpha = 1, bounds = null) {
    const [x0, y0, x1, y1] = bounds || [0, 0, this.w, this.h];
    const constant = Array.isArray(colorAt);
    for (let y = Math.max(0, y0 | 0); y < Math.min(this.h, Math.ceil(y1)); y++) {
      for (let x = Math.max(0, x0 | 0); x < Math.min(this.w, Math.ceil(x1)); x++) {
        const d = sdf(x + 0.5, y + 0.5);
        if (d > 1) continue;
        const cov = clamp01(0.5 - d) * alpha;
        if (cov > 0) this.blend(x, y, constant ? colorAt : colorAt(x, y), cov);
      }
    }
  }

  /** Opaque background: linear gradient between two stops along a vector. */
  gradient(c1, c2, angleDeg = 135) {
    const a = (angleDeg * Math.PI) / 180;
    const dx = Math.cos(a);
    const dy = Math.sin(a);
    const len = Math.abs(this.w * dx) + Math.abs(this.h * dy);
    for (let y = 0; y < this.h; y++) {
      for (let x = 0; x < this.w; x++) {
        const t = clamp01((x * dx + y * dy) / len + 0.25);
        const c = mix(c1, c2, t);
        const i = (y * this.w + x) * 4;
        this.px[i] = c[0];
        this.px[i + 1] = c[1];
        this.px[i + 2] = c[2];
        this.px[i + 3] = 255;
      }
    }
  }

  /** Soft radial light, added on top of whatever is already there. */
  glow(cx, cy, radius, rgb, strength = 0.16) {
    for (let y = 0; y < this.h; y++) {
      for (let x = 0; x < this.w; x++) {
        const d = Math.hypot(x - cx, y - cy) / radius;
        if (d >= 1) continue;
        const t = (1 - d) ** 2 * strength;
        this.blend(x, y, rgb, t);
      }
    }
  }

  toPng() {
    const out = new Uint8Array(this.w * this.h * 4);
    for (let i = 0; i < out.length; i++) out[i] = Math.round(Math.max(0, Math.min(255, this.px[i])));
    return encodePng(out, this.w, this.h);
  }
}

/* ------------------------------------------------------------------ SDFs --- */

const sdRoundRect = (cx, cy, w, h, r) => (x, y) => {
  const qx = Math.abs(x - cx) - (w / 2 - r);
  const qy = Math.abs(y - cy) - (h / 2 - r);
  return Math.hypot(Math.max(qx, 0), Math.max(qy, 0)) + Math.min(Math.max(qx, qy), 0) - r;
};

const sdCircle = (cx, cy, r) => (x, y) => Math.hypot(x - cx, y - cy) - r;

/** Ring, optionally limited to an angular sweep (degrees, clockwise from +x). */
const sdRingArc = (cx, cy, r, width, startDeg, sweepDeg) => (x, y) => {
  const dx = x - cx;
  const dy = y - cy;
  const ring = Math.abs(Math.hypot(dx, dy) - r) - width / 2;
  if (sweepDeg >= 360) return ring;
  let ang = (Math.atan2(dy, dx) * 180) / Math.PI;
  let rel = (((ang - startDeg) % 360) + 360) % 360;
  if (rel <= sweepDeg) return ring;
  // Outside the sweep: fall back to distance from the nearest cap.
  const cap = (deg) => {
    const a = (deg * Math.PI) / 180;
    return Math.hypot(x - (cx + r * Math.cos(a)), y - (cy + r * Math.sin(a))) - width / 2;
  };
  return Math.min(cap(startDeg), cap(startDeg + sweepDeg));
};

/** Distance to a line segment, minus half the stroke width (round caps). */
const sdSegment = (ax, ay, bx, by, width) => (x, y) => {
  const pax = x - ax;
  const pay = y - ay;
  const bax = bx - ax;
  const bay = by - ay;
  const t = clamp01((pax * bax + pay * bay) / (bax * bax + bay * bay || 1));
  return Math.hypot(pax - bax * t, pay - bay * t) - width / 2;
};

/** Union of segment SDFs — a stroked polyline with round joins. */
const sdPolyline = (points, width) => {
  const segs = [];
  for (let i = 0; i < points.length - 1; i++) {
    segs.push(sdSegment(points[i][0], points[i][1], points[i + 1][0], points[i + 1][1], width));
  }
  return (x, y) => {
    let d = Infinity;
    for (const s of segs) d = Math.min(d, s(x, y));
    return d;
  };
};

/* --------------------------------------------------------------- the mark --- */

const BRAND = {
  d900: hex('#06322e'),
  d950: hex('#04211e'),
  m700: hex('#0b544c'),
  m500: hex('#13857a'),
  l200: hex('#a9ded6'),
  l100: hex('#d7efeb'),
  accent: hex('#dd6a30'),
  white: [255, 255, 255],
};

/** The pulse path from the SVG logo, normalised to a 0..1 box. */
const PULSE = [
  [7, 20], [12.5, 20], [15.5, 13], [20, 27], [23.2, 20], [33, 20],
].map(([x, y]) => [x / 40, y / 40]);

/**
 * Draw the Occuo mark: an open ring with a pulse trace through it.
 * @param {Canvas} c
 * @param {number} x  top-left of the mark box
 * @param {number} size  box size in pixels
 */
function drawMark(c, x, y, size, { ring = BRAND.white, pulse = BRAND.l200 } = {}) {
  const cx = x + size / 2;
  const cy = y + size / 2;
  const r = size * (17 / 40);
  const stroke = size * (2.6 / 40);
  const bounds = [x - stroke, y - stroke, x + size + stroke, y + size + stroke];

  // Open ring: 256° of sweep starting at -40°, matching the SVG dash pattern.
  c.fill(sdRingArc(cx, cy, r, size * (2.4 / 40), -40, 256), ring, 1, bounds);
  c.fill(sdPolyline(PULSE.map(([px, py]) => [x + px * size, y + py * size]), stroke), pulse, 1, bounds);
}

/* ------------------------------------------------------------- generators --- */

/** App icon: brand gradient rounded square with the mark centred. */
function appIcon(size) {
  const c = new Canvas(size, size);
  const pad = size * 0.055;
  const box = size - pad * 2;

  // Transparent outside the squircle, gradient inside.
  const rect = sdRoundRect(size / 2, size / 2, box, box, box * 0.235);
  c.fill(rect, (x, y) => mix(BRAND.m700, BRAND.m500, clamp01((x + y) / (size * 1.7))), 1);
  drawMark(c, size * 0.215, size * 0.215, size * 0.57);
  return c.toPng();
}

/** Open Graph card: 1200x630 brand field, centred mark, accent rule. */
function ogCard() {
  const W = 1200;
  const H = 630;
  const c = new Canvas(W, H);

  c.gradient(BRAND.d950, BRAND.m500, 32);
  c.glow(W * 0.82, H * 0.06, 620, BRAND.l100, 0.2);
  c.glow(W * 0.06, H * 1.02, 520, BRAND.accent, 0.12);

  // Faint concentric rings behind the mark, echoing the logo geometry.
  for (let i = 1; i <= 6; i++) {
    c.fill(sdRingArc(W / 2, H * 0.44, 150 + i * 62, 1.4, 0, 360), BRAND.white, 0.05 - i * 0.006);
  }

  drawMark(c, W / 2 - 110, H * 0.44 - 110, 220);

  // Accent rule beneath the mark — the one warm element on the card.
  c.fill(sdRoundRect(W / 2, H * 0.72, 96, 6, 3), BRAND.accent, 1);

  // Corner tick marks: a quiet frame that keeps the card from reading empty.
  const tick = 46;
  const inset = 54;
  const corners = [
    [[inset, inset + tick], [inset, inset], [inset + tick, inset]],
    [[W - inset - tick, inset], [W - inset, inset], [W - inset, inset + tick]],
    [[inset, H - inset - tick], [inset, H - inset], [inset + tick, H - inset]],
    [[W - inset - tick, H - inset], [W - inset, H - inset], [W - inset, H - inset - tick]],
  ];
  for (const pts of corners) c.fill(sdPolyline(pts, 3), BRAND.white, 0.28);

  return c.toPng();
}

/* -------------------------------------------------------- vector versions --- */

const faviconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" role="img" aria-label="Occuo Health">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#0b544c"/>
      <stop offset="1" stop-color="#13857a"/>
    </linearGradient>
  </defs>
  <rect width="40" height="40" rx="9.4" fill="url(#g)"/>
  <g fill="none" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="20" cy="20" r="13.6" stroke="#ffffff" stroke-width="1.95"
            stroke-dasharray="60.8 25.6" transform="rotate(-40 20 20)"/>
    <path d="M8.4 20h4.4l2.4-5.6 3.6 11.2 2.56-5.6H31.6" stroke="#a9ded6" stroke-width="2.1"/>
  </g>
</svg>
`;

/**
 * Text-bearing Open Graph card. The PNG above is drawn without type because
 * no font rasteriser is available here; convert this file once with any SVG
 * tool if you want the wordmark burned into the social card:
 *   rsvg-convert -w 1200 -h 630 og-default.svg -o og-default.png
 */
const ogSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#04211e"/>
      <stop offset="1" stop-color="#13857a"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.82" cy="0.06" r="0.7">
      <stop offset="0" stop-color="#d7efeb" stop-opacity="0.22"/>
      <stop offset="1" stop-color="#d7efeb" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect width="1200" height="630" fill="url(#glow)"/>
  <g transform="translate(96 232)">
    <rect width="104" height="104" rx="26" fill="#ffffff" fill-opacity="0.1"/>
    <g transform="translate(12 12) scale(2)" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="20" cy="20" r="17" stroke="#ffffff" stroke-width="2.4"
              stroke-dasharray="76 32" transform="rotate(-40 20 20)"/>
      <path d="M7 20h5.5l3-7 4.5 14 3.2-7H33" stroke="#a9ded6" stroke-width="2.6"/>
    </g>
  </g>
  <g font-family="Sora, Segoe UI, sans-serif" fill="#ffffff">
    <text x="240" y="272" font-size="30" letter-spacing="6" fill="#a9ded6" font-weight="600">OCCUO HEALTH</text>
    <text x="240" y="336" font-size="52" font-weight="700">Occupational health,</text>
    <text x="240" y="398" font-size="52" font-weight="700">built for Egyptian workplaces</text>
  </g>
  <rect x="240" y="436" width="96" height="6" rx="3" fill="#dd6a30"/>
  <text x="240" y="492" font-family="Inter, Segoe UI, sans-serif" font-size="24"
        fill="#ffffff" fill-opacity="0.72">Surveillance · Ergonomics · Mental health · Emergency readiness</text>
</svg>
`;

/* -------------------------------------------------------------------- run --- */

await mkdir(OUT, { recursive: true });

const files = [
  ['favicon.svg', faviconSvg],
  ['og-default.svg', ogSvg],
  ['og-default.png', ogCard()],
  ['icon-192.png', appIcon(192)],
  ['icon-512.png', appIcon(512)],
  ['apple-touch-icon.png', appIcon(180)],
];

for (const [name, data] of files) {
  await writeFile(join(OUT, name), data);
  const size = Buffer.isBuffer(data) ? data.length : Buffer.byteLength(data);
  console.log(`  ${name.padEnd(22)} ${(size / 1024).toFixed(1)} KB`);
}
console.log(`wrote ${files.length} brand assets → src/assets/img/`);
