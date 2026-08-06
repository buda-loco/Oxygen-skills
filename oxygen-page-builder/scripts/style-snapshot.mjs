#!/usr/bin/env node
/**
 * Style snapshot / diff — prove a refactor is cascade-neutral.
 *
 * Renders a page in headless Chrome, records the computed styles of every
 * element carrying an authored class, and writes them to JSON. Run it before a
 * refactor and after, then `diff` the two: an empty diff is proof that moving
 * declarations (custom_css -> property groups, stylesheet -> selectors,
 * Div -> Container) changed nothing visible.
 *
 * This is the check that made the 68-finding panel-CSS migration safe to do
 * wholesale — "it validates and the page returns 200" does not catch a rule
 * that silently stopped applying.
 *
 * Usage:
 *   node style-snapshot.mjs capture <url> <out.json> [--classes a,b] [--viewport 1440x900]
 *   node style-snapshot.mjs diff <before.json> <after.json>
 *
 * Requires puppeteer resolvable from cwd (see design-detect.sh for the
 * install incantation) and honours PUPPETEER_EXECUTABLE_PATH.
 *
 * By default it snapshots every element whose class list contains at least one
 * class that is NOT an engine auto-class (bde-*, oxy-*-<digits>, breakdance*),
 * i.e. exactly the classes a build authored. Keys are `<class>[n]` so repeated
 * cards stay distinguishable and stable across runs.
 */

import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

/**
 * Resolve puppeteer from the USER'S cwd, not from this script's directory.
 * The skill lives outside any project, so a bare `import('puppeteer')` looks
 * in ~/.claude/... and always fails even when the project has it installed.
 */
async function loadPuppeteer() {
  const req = createRequire(path.join(process.cwd(), 'noop.js'));
  for (const spec of ['puppeteer', 'puppeteer-core']) {
    try { return await import(pathToFileURL(req.resolve(spec)).href); }
    catch { /* try the next */ }
  }
  try { return await import('puppeteer'); } catch { return null; }
}

const PROPS = [
  'display', 'position', 'top', 'right', 'bottom', 'left', 'z-index',
  'width', 'height', 'max-width', 'max-height', 'min-height',
  'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'color', 'background-color', 'opacity',
  'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing',
  'text-transform', 'text-align',
  'border-radius', 'border-top-width', 'border-left-width',
  'flex-direction', 'align-items', 'justify-content', 'gap',
  'grid-template-columns', 'object-fit', 'overflow',
];

async function capture(url, out, { classes, viewport }) {
  const puppeteer = await loadPuppeteer();
  if (!puppeteer) {
    console.error('puppeteer is required, resolved from cwd. Install it here:\n'
      + '  PUPPETEER_SKIP_DOWNLOAD=1 npm i puppeteer\n'
      + '  export PUPPETEER_EXECUTABLE_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"');
    process.exit(2);
  }
  const [w, h] = (viewport || '1440x900').split('x').map(Number);
  const browser = await puppeteer.default.launch({
    headless: true,
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
  });
  const page = await browser.newPage();
  await page.setViewport({ width: w, height: h });
  await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

  // Entrance animations start elements at opacity 0 and reveal on scroll, and
  // scroll-driven (oxs-parallax / animation-timeline) layers move continuously.
  // Sweep so every reveal fires, return to the top, then SETTLE: sample opacity
  // across the document until two consecutive reads match, so the snapshot
  // records at-rest values. Without this, two captures of an unchanged page
  // differ by a few thousandths of opacity and every diff is noise.
  await page.evaluate(async () => {
    const max = document.documentElement.scrollHeight;
    for (let y = 0; y <= max; y += Math.floor(window.innerHeight * 0.8)) {
      window.scrollTo({ top: y, behavior: 'instant' });
      await new Promise(r => setTimeout(r, 80));
    }
    window.scrollTo({ top: 0, behavior: 'instant' });

    const sample = () => [...document.querySelectorAll('[class]')]
      .map(el => getComputedStyle(el).opacity).join('|');
    let prev = null;
    for (let i = 0; i < 25; i++) {                 // ≤5s, exits as soon as stable
      await new Promise(r => setTimeout(r, 200));
      const now = sample();
      if (now === prev) break;
      prev = now;
    }

    // FREEZE. An INFINITE animation (a looping colour cycle, a marquee) has no
    // at-rest value at all, so settling can never converge on it. Seek every
    // animation — CSS, WAAPI and scroll-driven alike — to time 0 and pause, so
    // each capture measures the identical frame. Deterministic beats "current".
    for (const a of document.getAnimations()) {
      try { a.currentTime = 0; a.pause(); } catch { /* scroll timelines may refuse */ }
    }
    await new Promise(r => requestAnimationFrame(() => setTimeout(r, 120)));
  });

  const data = await page.evaluate((PROPS, only) => {
    // Engine-generated classes carry no authored design and their names are
    // position-dependent (`oxy-container-73-148-73-2`), so keying on them makes
    // diffs unstable. `oxy-container`/`oxy-text` are engine base classes too —
    // they have no digits, so a naive digit test lets them through.
    const isAuto = c =>
      /^bde-/.test(c) || /^breakdance/.test(c) || /^oxy-/.test(c) ||
      /^is-anim/.test(c) || /^swiper/.test(c) || /^oxs-/.test(c);
    const wanted = only ? new Set(only) : null;
    const seen = {};
    const out = {};
    for (const el of document.querySelectorAll('[class]')) {
      const authored = [...el.classList].filter(c => !isAuto(c));
      if (!authored.length) continue;
      const key0 = authored[0];
      if (wanted && !authored.some(c => wanted.has(c))) continue;
      seen[key0] = (seen[key0] || 0) + 1;
      const key = `${key0}[${seen[key0]}]`;
      const cs = getComputedStyle(el);
      const rec = {};
      for (const p of PROPS) {
        const v = cs.getPropertyValue(p);
        if (v) rec[p] = v.trim();
      }
      const r = el.getBoundingClientRect();
      rec['~box'] = `${Math.round(r.width)}x${Math.round(r.height)}`;
      out[key] = rec;
    }
    return out;
  }, PROPS, classes || null);

  await browser.close();
  fs.writeFileSync(out, JSON.stringify({ url, viewport: `${w}x${h}`, data }, null, 2));
  console.log(`captured ${Object.keys(data).length} elements -> ${out}`);
}

function diff(aPath, bPath) {
  const A = JSON.parse(fs.readFileSync(aPath, 'utf8'));
  const B = JSON.parse(fs.readFileSync(bPath, 'utf8'));
  const a = A.data, b = B.data;
  const keys = [...new Set([...Object.keys(a), ...Object.keys(b)])].sort();
  let changes = 0, gone = 0, added = 0;

  for (const k of keys) {
    if (!(k in b)) { console.log(`- ${k}  MISSING in after`); gone++; continue; }
    if (!(k in a)) { console.log(`+ ${k}  NEW in after`); added++; continue; }
    const props = [...new Set([...Object.keys(a[k]), ...Object.keys(b[k])])];
    const d = props.filter(p => a[k][p] !== b[k][p]);
    if (d.length) {
      changes++;
      console.log(`~ ${k}`);
      for (const p of d) console.log(`    ${p}: ${a[k][p] ?? '(unset)'} -> ${b[k][p] ?? '(unset)'}`);
    }
  }
  const total = changes + gone + added;
  console.log(total === 0
    ? `\nIDENTICAL — ${keys.length} elements, no computed-style differences. Refactor is cascade-neutral.`
    : `\n${total} difference(s): ${changes} changed, ${gone} missing, ${added} new, of ${keys.length} elements.`);
  process.exit(total === 0 ? 0 : 1);
}

const [cmd, ...rest] = process.argv.slice(2);
const flag = n => { const i = rest.indexOf(n); return i === -1 ? null : rest[i + 1]; };
const positional = rest.filter((v, i) => !v.startsWith('--') && !(i > 0 && rest[i - 1].startsWith('--')));

if (cmd === 'capture' && positional.length >= 2) {
  await capture(positional[0], positional[1], {
    classes: flag('--classes')?.split(','),
    viewport: flag('--viewport'),
  });
} else if (cmd === 'diff' && positional.length >= 2) {
  diff(positional[0], positional[1]);
} else {
  console.log('usage:\n'
    + '  node style-snapshot.mjs capture <url> <out.json> [--classes a,b] [--viewport 1440x900]\n'
    + '  node style-snapshot.mjs diff <before.json> <after.json>');
  process.exit(1);
}
