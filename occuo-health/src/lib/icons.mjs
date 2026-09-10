/**
 * Inline SVG icon set.
 *
 * Icons are inlined at build time rather than loaded as a font or sprite
 * sheet: no extra request, no FOUT, and they inherit `currentColor` so a
 * single CSS token controls colour in both themes.
 *
 * All icons share a 24x24 viewBox and a 1.6 stroke width so they sit on the
 * same optical weight when mixed in a list.
 */

const paths = {
  stethoscope:
    '<path d="M5 3v5a4 4 0 0 0 8 0V3"/><path d="M3 3h2M11 3h2"/><path d="M9 12v3a6 6 0 0 0 12 0v-1"/><circle cx="21" cy="11" r="2"/>',
  'shield-plus':
    '<path d="M12 3 4 6v6c0 4.5 3.2 8.4 8 9.5 4.8-1.1 8-5 8-9.5V6l-8-3Z"/><path d="M12 9v6M9 12h6"/>',
  activity: '<path d="M3 12h4l2.5-7 5 14L17 12h4"/>',
  brain:
    '<path d="M12 5.5a3 3 0 0 0-5.7-1.3A2.8 2.8 0 0 0 4 7a2.9 2.9 0 0 0 .6 1.8A3 3 0 0 0 5 14.6 3 3 0 0 0 8 19a3 3 0 0 0 4 1.4Z"/><path d="M12 5.5a3 3 0 0 1 5.7-1.3A2.8 2.8 0 0 1 20 7a2.9 2.9 0 0 1-.6 1.8A3 3 0 0 1 19 14.6 3 3 0 0 1 16 19a3 3 0 0 1-4 1.4Z"/><path d="M12 5.5v15"/>',
  moon: '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
  'no-smoking':
    '<path d="M3 14h13v4H3z"/><path d="M19 14h2v4h-2z"/><path d="M16 6c0 2 2 2 2 4"/><circle cx="12" cy="12" r="9.5"/><path d="M5.5 5.5l13 13"/>',
  compass: '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
  siren:
    '<path d="M6 18v-5a6 6 0 0 1 12 0v5"/><rect x="4" y="18" width="16" height="3.5" rx="1.2"/><path d="M12 4V2M4.5 7 3 5.8M19.5 7 21 5.8"/>',
  factory:
    '<path d="M3 21V10l6 4V10l6 4V6h6v15Z"/><path d="M18 11h1M18 15h1"/>',
  'hard-hat':
    '<path d="M4 16a8 8 0 0 1 16 0"/><path d="M9 16V7.5A1.5 1.5 0 0 1 10.5 6h3A1.5 1.5 0 0 1 15 7.5V16"/><rect x="2.5" y="16" width="19" height="3.5" rx="1.2"/>',
  flame:
    '<path d="M12 3s5 4.2 5 9a5 5 0 0 1-10 0c0-2 1-3.4 1-3.4S9.5 11 11 11c0-3 1-6.5 1-8Z"/>',
  pill: '<rect x="2.5" y="8.5" width="19" height="7" rx="3.5" transform="rotate(-45 12 12)"/><path d="M9 9l6 6"/>',
  truck:
    '<path d="M2 6h11v10H2zM13 9h4l3 3v4h-7z"/><circle cx="6.5" cy="18" r="1.8"/><circle cx="16.5" cy="18" r="1.8"/>',
  building:
    '<path d="M4 21V4a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v17"/><path d="M14 10h5a1 1 0 0 1 1 1v10"/><path d="M7 7h4M7 11h4M7 15h4M17 14h1M17 18h1"/>',
  utensils: '<path d="M6 3v7a2 2 0 0 0 4 0V3M8 12v9"/><path d="M17 3c-1.5 1.5-2 3-2 5s.7 3 2 3v10"/>',
  zap: '<path d="M13 2 5 13h6l-1 9 8-11h-6l1-9Z"/>',
  'map-pin': '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
  scale:
    '<path d="M12 3v18M7 21h10M4 8h16M4 8l-2.5 6a3.5 3.5 0 0 0 5 0Zm16 0-2.5 6a3.5 3.5 0 0 0 5 0Z"/><path d="M12 5 4 8M12 5l8 3"/>',
  lock: '<rect x="4" y="10.5" width="16" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
  chart: '<path d="M3 3v18h18"/><path d="M7 15v-4M12 17V8M17 17v-6"/>',
  users:
    '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M17.5 14.4A6.5 6.5 0 0 1 21.5 20"/>',
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/>',
  phone:
    '<path d="M6 3h3l2 5-2.2 1.4a12 12 0 0 0 5.8 5.8L16 13l5 2v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4 5.2 2 2 0 0 1 6 3Z"/>',
  mail: '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>',
  'arrow-right': '<path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>',
  check: '<path d="m4.5 12.5 5 5 10-11"/>',
  'chevron-down': '<path d="m6 9.5 6 6 6-6"/>',
  menu: '<path d="M3.5 7h17M3.5 12h17M3.5 17h17"/>',
  close: '<path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  quote:
    '<path d="M9.5 6C6.5 7.4 5 10 5 13.2V18h5.8v-5.6H8.2c0-1.9.8-3.3 2.4-4.2Zm9.3 0c-3 1.4-4.5 4-4.5 7.2V18H20v-5.6h-2.6c0-1.9.8-3.3 2.4-4.2Z"/>',
  sun: '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.4M12 19.6V22M4.2 4.2l1.7 1.7M18.1 18.1l1.7 1.7M2 12h2.4M19.6 12H22M4.2 19.8l1.7-1.7M18.1 5.9l1.7-1.7"/>',
  linkedin:
    '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7.5 10.5V17M7.5 7.4v.1M11.5 17v-3.6a2.1 2.1 0 0 1 4.2 0V17"/><path d="M11.5 10.5V17"/>',
  facebook:
    '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M15 8h-1.5A2.5 2.5 0 0 0 11 10.5V21M9 13h5"/>',
  instagram:
    '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.8"/><path d="M17.2 6.8v.1"/>',
  whatsapp:
    '<path d="M3.5 20.5 5 16.4A8 8 0 1 1 8 19.2Z"/><path d="M9 9.2c0 3 2 5 4.8 5.6.6.1 1.2-.3 1.2-1v-.7l-1.7-.7-.8.9a4.6 4.6 0 0 1-2.1-2.2l.9-.8-.7-1.7h-.7c-.6 0-1 .5-.9 1.1Z"/>',
  send: '<path d="m3 11 18-8-8 18-2.2-7.8L3 11Z"/>',
  file: '<path d="M13 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9Z"/><path d="M13 3v6h6"/>',
  search: '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
};

/**
 * @param {string} name  key from the set above
 * @param {{size?: number, cls?: string}} [opts]
 */
export function icon(name, opts = {}) {
  const body = paths[name];
  if (!body) throw new Error(`Unknown icon: "${name}"`);
  const size = opts.size ?? 24;
  const cls = opts.cls ? ` class="${opts.cls}"` : '';
  return (
    `<svg${cls} width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" ` +
    `stroke="currentColor" stroke-width="1.6" stroke-linecap="round" ` +
    `stroke-linejoin="round" aria-hidden="true" focusable="false">${body}</svg>`
  );
}

export const iconNames = Object.keys(paths);
