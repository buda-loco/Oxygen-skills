#!/usr/bin/env node
/**
 * Open each tree in the Oxygen builder and assert it decodes.
 *
 * This is the ONLY check that proves a tree is editable: validate-tree.php
 * checks what the PHP renderer needs, but the builder runs a separate, stricter
 * io-ts schema client-side. A tree can validate, render perfectly, and still
 * hard-fail with "Validation Error: IO-TS decoding failed" the moment anyone
 * opens it — and because selectors are shared, ONE bad selector breaks the
 * builder for every page at once.
 *
 * Usage: builder-check.mjs <baseUrl> <oneTimeLoginUrl> <id,id,id>
 * Exits 0 when every id opens clean.
 */

import path from 'node:path';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

async function loadPuppeteer() {
  const req = createRequire(path.join(process.cwd(), 'noop.js'));
  for (const dir of [process.cwd(), path.join(process.env.HOME || '', '.cache/oxygen-skill-detect')]) {
    try {
      const r = createRequire(path.join(dir, 'noop.js'));
      return await import(pathToFileURL(r.resolve('puppeteer')).href);
    } catch { /* next */ }
  }
  try { return await import('puppeteer'); } catch { return null; }
}

const [base, loginUrl, idsCsv] = process.argv.slice(2);
if (!base || !loginUrl || !idsCsv) {
  console.error('usage: builder-check.mjs <baseUrl> <loginUrl> <id,id,id>');
  process.exit(2);
}
const ids = idsCsv.split(',').map(s => s.trim()).filter(Boolean);

const puppeteer = await loadPuppeteer();
if (!puppeteer) {
  console.error('  puppeteer not resolvable — run scripts/design-detect.sh once to seed the cache');
  process.exit(2);
}

const browser = await puppeteer.default.launch({
  headless: true,
  executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });

let bad = 0;
try {
  // The login link is one-time; spend it once and reuse the session cookie.
  await page.goto(loginUrl, { waitUntil: 'networkidle2', timeout: 60000 });

  for (const id of ids) {
    const url = `${base}/?oxygen=builder&id=${id}`;
    try {
      await page.goto(url, { waitUntil: 'networkidle2', timeout: 90000 });
      // The builder boots asynchronously; the error banner appears well after
      // networkidle. Poll for either the canvas or the failure text.
      const verdict = await page.waitForFunction(() => {
        const t = document.body?.innerText || '';
        if (t.includes('IO-TS') || t.includes('decoding failed')) return 'IOTS';
        if (t.includes('Save') || document.querySelector('iframe')) return 'OK';
        return false;
      }, { timeout: 45000, polling: 500 }).then(h => h.jsonValue()).catch(() => 'TIMEOUT');

      if (verdict === 'OK') { console.log(`    #${id} opens`); }
      else { console.log(`    #${id} ${verdict === 'IOTS' ? 'IO-TS DECODE FAILED' : 'did not finish loading'}`); bad++; }
    } catch (e) {
      console.log(`    #${id} error: ${e.message}`);
      bad++;
    }
  }
} finally {
  await browser.close();
}
process.exit(bad === 0 ? 0 : 1);
