# SEO, social sharing & accessibility (Oxygen + WooCommerce, no SEO plugin)

There is **no SEO plugin** (Yoast/RankMath). Know exactly what WP core + WooCommerce give for free so you
don't duplicate, and fill the rest with the `acme-seo.php` mu-plugin. **Always verify by fetching
the rendered HTML and grepping the `<head>`** — never assume a tag is there. SEO, **social-sharing meta
(OG/Twitter)**, and **accessibility** are all part of "done" (SKILL.md rule 6) — run §a11y-seo-audit
(bottom of this file) on every build; zero findings = pass.

## What WP core + WooCommerce already provide (don't re-add)
- `<title>` via `document_title_parts` = "Page Title – {blogname}" (so set `blogname`/`blogdescription`
  right — `wp option update blogname`). Front-page title = blogname + tagline; keep the tagline short
  (~60-char title target).
- `rel="canonical"` — **SINGULAR posts/pages/products ONLY** (`rel_canonical`). Archives get NONE.
- Robots meta: WP core `noindex` on **search results**; WooCommerce `noindex, follow` on
  **cart / checkout / my-account** (+ their canonicals). Don't duplicate these.
- `<html lang="es-AR">`, clean permalinks (`/%postname%/`), `/wp-sitemap.xml`, `/robots.txt` (with WC
  disallows + sitemap ref). `blog_public` option = the master indexable switch (1 = indexable).
- **WooCommerce Product JSON-LD** on every PDP (name/offers/price/availability) — no need to add Product schema.

## What's missing without a plugin → the `acme-seo.php` mu-plugin adds it
Hooks on `wp_head`:
1. **Meta description** — source priority: product short-desc → product desc → post excerpt → post content
   (trimmed) → tagline. Per-type overrides for front page & shop. Cap 160 chars.
2. **Open Graph + Twitter Card** — `og:{type,site_name,title,description,url,image,locale}` +
   `twitter:{card=summary_large_image,title,description,image}`. Image = product image → featured →
   brand default attachment (#556 here; products have no images).
3. **Organization + WebSite JSON-LD** on the front page (WebSite carries a `SearchAction` /
   Sitelinks-search-box pointing at `/?s={search_term_string}`).
4. **Canonical for ARCHIVES** (`is_shop()`, product taxonomies, front page) — the gap WP core leaves.

## Oxygen-specific SEO gotchas (hit 2026-07-11)
- **Multiple `<h1>` from a slider hero.** A tree-native home with an `Advancedslider` emits **one `<h1>`
  per slide** (each slide's `Text` was tag `h1`) → 3 H1s on the home. Fix: change slider slide headings to
  `h2` (keep the styling class, e.g. `.hero__wordmark`, since the CSS targets the class not the tag) and
  prepend ONE **`.sr-only` `<h1>`** with brand + keywords. `.sr-only` = the standard visually-hidden clip
  rule (added to `post-15.css`). Result: exactly one keyword-rich H1, invisible.
- **Tree-native pages have EMPTY `post_content`** (the design lives in `_oxygen_data`), so a
  content-derived meta description comes back blank. Guard it: build the description, `trim()`, and if
  empty fall back to the tagline — **trim BEFORE the empty check** (whitespace-only content is truthy and
  silently skips the fallback → empty description).
- Headings inside the tree already use proper tags (`Text` + `settings.advanced.tag`), so per-page H1s on
  content pages are fine — the slider is the exception.

## Audit recipe (fetch + grep, per page type)
For home / a product / a content page / shop / search / cart, fetch the URL server-side
(`wp_remote_get`) and extract: `<title>` (+ length), `meta[name=description]`, `link[rel=canonical]`,
`og:title`/`og:image`, `meta[name=robots]`, `<html lang>`, H1 **count** (must be 1), and
`application/ld+json` count. Confirm: search = `noindex`; cart/checkout/account = `noindex` + canonical;
shop = has canonical + indexable; every page = has description + OG + one H1. (Script pattern in the
session scratchpad `seo-audit.php`.)

## Social sharing meta (Open Graph + Twitter Card) — verify per page
Sharing any URL on Facebook / LinkedIn / WhatsApp / X / Slack pulls **`og:title` + `og:description` +
`og:image`** (and `twitter:card=summary_large_image` + `twitter:image`). These come from the seo
mu-plugin (§"What's missing" item 2), NOT from Oxygen — so a page can look perfect and still share as a
bare link if the plugin's off or the image source is empty. **Always confirm the rendered tags** (audit
snippet below). Key gotchas:
- `og:image`/`twitter:image` resolve product image → featured image → brand-default attachment. A page
  with none of those shares the brand default — fine, but if you want a page-specific share image, give
  the post a **featured image** (even tree-native pages honor it for OG).
- `og:url` must be the canonical URL; `og:locale` matches `<html lang>` (`es_AR` form, underscore).
- `og:type` = `website` (front/pages) vs `article` (posts) vs `product` (PDP) — the plugin sets this;
  verify on each type.

## Accessibility — part of "done" (SKILL.md rule 6), verify every build
- **Every `<img>` needs `alt`.** Informative → descriptive alt (comes from the ATTACHMENT's media-library
  alt via `oxy_image`; if the attachment has none, the img ships alt-less — fix the attachment or set a
  custom alt). Decorative / text-carries-the-meaning (category tiles, hero-behind-text, overlay photos)
  → **explicit `alt="" ` + `aria-hidden="true"`**. Empty `custom_alt` does NOT render `alt=""` (the
  renderer omits it) → force via `settings.advanced.attributes=[{name:'alt',value:''},{name:'aria-hidden',value:'true'}]`.
- **CTAs are real controls** (`Button`/`TextLink`/`ContainerLink` → `<a>`/`<button>`), never a
  `Text`/`<span class="btn">` (not focusable, no href, not announced). A styled span is a bug.
- **One `<h1>` per page** (slider heroes → demote slides to h2 + one `.sr-only` h1, above); heading
  levels nest (no h2→h4 skips).
- Interactive/native WC markup: keep visible focus states and don't `outline:none` without a replacement.

## §a11y-seo-audit — front-end audit snippet (run in the browser console on the live URL)
The counterpart to `element.matches()` for CSS — never assume, measure. Zero findings = pass.
```js
(() => {
  const q = s => [...document.querySelectorAll(s)];
  const imgsNoAlt   = q('img').filter(i => !i.hasAttribute('alt')).map(i => i.src.split('/').pop());
  const spanButtons = q('.btn,[class*="btn"]').filter(b => b.tagName==='SPAN').map(b => b.textContent.trim());
  const h1s         = q('h1').map(h => h.textContent.trim().slice(0,40));
  const meta = {
    title:      document.title || 'MISSING',
    description:document.querySelector('meta[name=description]')?.content ? 'ok' : 'MISSING',
    canonical:  document.querySelector('link[rel=canonical]')?.href || 'MISSING',
    lang:       document.documentElement.lang || 'MISSING',
    og_title:   document.querySelector('meta[property="og:title"]')?.content ? 'ok':'MISSING',
    og_image:   document.querySelector('meta[property="og:image"]')?.content || 'MISSING',
    og_desc:    document.querySelector('meta[property="og:description"]')?.content ? 'ok':'MISSING',
    twitter:    document.querySelector('meta[name="twitter:card"]')?.content || 'MISSING',
  };
  return { PASS: !imgsNoAlt.length && !spanButtons.length && h1s.length===1
                 && !Object.values(meta).includes('MISSING'),
           imgs_without_alt: imgsNoAlt, span_buttons: spanButtons, h1_count: h1s.length, h1s, meta };
})()
```

## Still-open / optional
- BreadcrumbList JSON-LD (no visible breadcrumbs on the storefront today).
- Real product images would populate `og:image` per-product and WC Product schema `image` (else it falls
  back to a brand default) — track this in your project notes.
- If the blog grows, add `home`/blog-archive meta + consider a proper SEO plugin.

## §contrast-audit — browser-computed WCAG check (run per page)
Static CSS reading lies about contrast (cascade resets, inherited colors, layers). Audit RENDERED
values in the console: walk `body *` text nodes, compute `getComputedStyle` color vs the first
non-transparent ancestor `backgroundColor`, WCAG ratio threshold 4.5 (3 for ≥24px or ≥18.66px bold).
Three false-positive guards learned in production:
1. **Composite text alpha over the bg** before comparing (faint decorative watermarks at alpha<.35
   are decorative — skip, don't "fix").
2. **Skip media-backed elements** — text over videos/images/bg-images (an ancestor with a large
   `<video>/<img>` child or background-image) can't be judged against background-color; verify
   those visually (screenshot).
3. `elementsFromPoint` only works in-viewport — don't trust it for off-screen sections.
Real bugs this catches that greps miss: headings re-darkened by the `.oxy-text`/tag reset on
colored bands (a LATENT bug that surfaces when a band's bg changes), accent-color links on brand
backgrounds, "on-trend" solid teal/brand text on white. Fix pattern: accessible color TOKENS
(`--tc-teal-text` for teal-as-text on light, `--tc-link-on-purple` for accents on brand bg) —
never per-element patches.
