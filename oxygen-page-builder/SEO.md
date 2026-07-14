# SEO on demosite (Oxygen + WooCommerce, no SEO plugin)

There is **no SEO plugin** (Yoast/RankMath). Know exactly what WP core + WooCommerce give for free so you
don't duplicate, and fill the rest with the `acme-seo.php` mu-plugin. **Always verify by fetching
the rendered HTML and grepping the `<head>`** — never assume a tag is there.

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

## Still-open / optional
- BreadcrumbList JSON-LD (no visible breadcrumbs on the storefront today).
- Real product images would populate `og:image` per-product and WC Product schema `image` (else it falls
  back to a brand default) — track this in your project notes.
- If the blog grows, add `home`/blog-archive meta + consider a proper SEO plugin.
