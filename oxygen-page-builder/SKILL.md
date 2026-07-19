---
name: oxygen-page-builder
description: Use when creating or editing anything in Oxygen 6 (Breakdance engine) — pages, oxygen_template/header/footer posts, classes/selectors, Global Settings, WooCommerce layouts, forms, menus, sliders, or brand CSS; when touching _oxygen_data trees or uploads/oxygen/css; when a page won't open in the builder ("IO-TS decoding failed") or brand CSS silently doesn't apply; or when running wp-cli against a WordPress + Oxygen site.
---

# Oxygen 6.1 page builder (Breakdance engine)

Oxygen 6 is a complete rewrite on the **Breakdance engine** — classic Oxygen v3/v4 knowledge
(`ct_section`, `ct_id`, shortcodes) does NOT apply. Everything here is verified against the live
plugin (v6.1.0). Two independent stores drive a page:
1. **Tree** (structure + content) — post meta `_oxygen_data`.
2. **Styling** — global classes ("selectors", option `oxygen_oxy_selectors_json_string`) +
   **Global Settings** (brand colors/fonts/section width, option `oxygen_global_settings_json_string`).

**Golden rule: the PHP renderer is lenient; the builder runs strict io-ts validation.** A tree can
render on the front-end yet fail to open in the builder. Never guess shapes — use the toolbox below,
`oxy_golden()`, or a builder-saved sample.

## Project rules (example conventions — swap the brand specifics for your project's)
1. **Everything on-brand.** No element ships with its default look — after adding ANY element
   (native, WC, plugin markup), add brand CSS for its rendered classes (example brand: accent `#0f766e`,
   ink `#1f2937`, Oswald uppercase headings), scoped per GOTCHAS.md rules, in
   the global stylesheet (a CssCode node in your HEADER template → `post-<id>.css`; header, not footer — see GOTCHAS §bde-div cascade) or a page-local
   CssCode node.
2. **Native, individually-editable elements over code elements.** The goal is USER-EDITABILITY:
   every content piece must be a component the user can click and change in the builder — one
   `Text`(hN) per heading, one `RichText` per paragraph/list, `Image`/`Button`/`FAQ`/… per piece.
   **Litmus test: can the user select and edit this exact piece in the builder?**
   - ⛔ `EssentialElements\PostContent` (un-editable dynamic mirror — explicitly rejected here),
     one giant RichText blob, or a PhpCode echoing `the_content` all FAIL the test.
   - PhpCode/HtmlCode are last resorts for genuinely data-shaped output (product loops, meta-driven
     tables, dynamic menus) — keep them small leaf renderers and say why no native element fit.
   - Check ELEMENTS.md first: 165 native elements (WC set, MenuBuilder, AdvancedTabs, FormBuilder,
     Advancedslider, FAQ…).
3. **Post/term LISTS → prefer the native loop builder (`OxygenElements\PostsLoop`), not a PhpCode loop**
   — so the user can edit query + card design in the visual editor. This overrides the "PhpCode for
   loops" fallback for POST LISTS specifically (blog archive, related posts, etc.).
   ✔ **Shapes now documented (golden-sampled 2026-07-17):** query at `content.query.query`
   (custom/text/php modes) and the per-item card as a referenced GLOBAL BLOCK at
   `content.repeated_block.global_block` — see PROPERTIES.md §PostsLoop. Hand-authoring works with
   that shape; still verify in the builder after writing (io-ts is client-side). The card block
   carries the per-post design; dynamic bindings inside it resolve per-post. Related-to-current-post
   lists use php-query mode (RECIPES §Related-posts as a native PostsLoop).
4. **Every visual element carries its OWN class — no bare/class-less elements.** Put a BEM/brand class
   on `settings.advanced.classes` (reference-stylesheet) or a native selector on `meta.classes` for
   EVERY authored element (Div, Section, Text, RichText, Image, Button, WrapperLink/ContainerLink/
   TextLink, Icon, Html5Video). An element with no authored class only gets Oxygen's auto `bde-`/`oxy-`
   id-classes — the user can't select/edit it cleanly, and its design ends up applied "at a distance."
   **Design belongs ON the element's own class**, never via descendant selectors that reach class-less
   children (`.prose p{…}`, `.content-hero h1{…}`), never on the auto id-class, never node-level-only.
   ⚠ Most common violation: **contextual styling** — the parent (`.prose`, `.content-hero`,
   `.section-heading`) has a class and its Text/RichText children have none. It renders, but fails this
   rule. Give each child its own class and move its rule onto that class. Audit any site with
   `scripts/examples/audit-classes.php` (read-only; lists every class-less authored element).
5. **Images: use the correct element — NEVER a span/Text.** A content image = the **`Image`** element
   (`OxygenElements\Image`, PROPERTIES §Image). A decorative/background image = a **`Div`** (or Section)
   with a background layer — never an `<img>` stuffed in a Text/span. Faking an image with a Text/span
   means no image controls (alt, sizes, lazy, swap) and it isn't editable as an image in the builder.
   HARD RULE (verified pain point).
6. **Accessibility + SEO are part of "done" — verify them on every build, never skip.** A page that
   renders is not finished until both check out. These are CRUCIAL, not optional polish.
   - **Images:** every `<img>` MUST have an `alt` attribute. Informative image → descriptive alt
     (`oxy_image` pulls it from the media library — so the ATTACHMENT must have alt text, or the img
     ships with none). Decorative / meaning-carried-by-adjacent-text (category tiles, hero-behind-text,
     overlay photos) → explicit **`alt=""` + `aria-hidden="true"`** so screen readers skip it. Empty
     `custom_alt` does NOT emit `alt=""` — the renderer omits it; force it via
     `settings.advanced.attributes = [{name:'alt',value:''},{name:'aria-hidden',value:'true'}]`.
     ⚠ A missing media-library alt is the #1 cause of alt-less images — check the attachment, not just the node.
   - **Interactive elements must be real controls:** a clickable CTA must render a semantic, focusable
     anchor — use the **`Button`** element (`oxy_button`), the ONLY one whose root is a true `<a href>`.
     ⚠ `TextLink` and `ContainerLink` render a `<span href>` + `breakdance-link` JS (clickable but NOT a
     focusable/announced anchor), and a `Text`/`<span class="btn">` has no href at all — both fail a11y.
     See GOTCHAS §accessible-link (incl. the styling caveat: `btn` classes land on Button's wrapper div,
     so target `.btn.bde-button .bde-button__button`). If you see `<span class="btn">`, it's a bug.
   - **Headings:** exactly ONE `<h1>` per page (slider heroes emit one H1 per slide — demote to `h2`
     + add one `.sr-only` H1; see SEO.md); heading levels nest sensibly (no h2→h4 jumps).
   - **SEO structures present:** `<title>`, `meta[name=description]`, OpenGraph, `link[rel=canonical]`,
     `<html lang>`. On this kind of build they come from an SEO mu-plugin — confirm it's active and the
     tags actually render (see SEO.md).
   - **How to verify (do it every build):** load the front-end and run the audit snippet in SEO.md
     §a11y-seo-audit (counts alt-less imgs, span-buttons, H1s, and missing meta) — the same way you
     verify CSS with `element.matches()`. "It probably has alt" is not verification.

## Toolbox (use these, don't hand-roll)
All in `scripts/` next to this file; site must be running in Local:
```bash
scripts/wp-eval.sh build.php          # run a PHP script via wp eval-file (env/socket/phar handled)
scripts/wp-eval.sh -- post list       # any wp-cli command
scripts/wp-eval.sh scripts/validate-tree.php <postId> fetch   # ALWAYS after writing a tree
```
```php
require '/path/to/oxygen-page-builder/scripts/lib.php';
$id = wp_insert_post([...]);
oxy_write_tree($id, [                    // wires _parentId/_nextNodeId/status, validates, set_meta,
  oxy_div([                              // regenerates cache. Throws instead of writing a bad tree.
    oxy_text('TITLE', 'h1', ['content-hero__title']),   // heading = Text + tag (never RichText <h1>)
    oxy_rich('<p>…</p>', ['prose']),
    oxy_faq([['q'=>'…','a'=>'<p>…</p>']], ['acme-faq']),
    oxy_button('CTA', '/contact/', ['btn-cta']),        // link gets BOTH type AND url
    oxy_link('/', [oxy_image($logoId)], ['brand']),     // wrap children in <a> (ContainerLink, not WrapperLink)
  ], ['container']),
]);
```
Other helpers: `oxy_image`, `oxy_css/js/html/php`, `oxy_golden($slug)` (builder's exact
defaultProperties/defaultChildren — first stop for composites like AdvancedTabs/FormBuilder; some
elements return `false` (e.g. Advancedslider) — then read the element source per GOTCHAS.md §render
keys), `oxy_selector`+`oxy_save_selectors` (merge-by-name), `oxy_template_settings`
(JSON-string location meta), `oxy_nid`/`oxy_uuid` (static registries — `wp eval-file` is
function-scoped, `global` silently fails).
- Worked examples (page build, native loop, component placement, dynamic data, CSS injection):
  `scripts/examples/` (patterns to copy).

## Workflow for any build
1. Write the tree with lib.php (or copy a `scripts/examples/` pattern). Unknown shape? `oxy_golden()`,
   or build once in the real builder + read `_oxygen_data` back.
2. Brand-style every new class (rule 1). Where CSS lives: global = the HEADER template's CssCode node (loads after the engine reset, before page CSS → builder edits still win)
   (preserve it when rebuilding #15!); page-local = an `oxy_css()` node on the page.
3. Verify — all four, every time:
   - `scripts/wp-eval.sh scripts/validate-tree.php <id> fetch` (io-ts invariants + trap checks + front-end 200)
   - open `http://example.local?oxygen=builder&id=<id>` — must load with no "IO-TS decoding failed"
   - CSS: check `element.matches(sel)` in the browser console — NEVER trust "the rule is in the file"
     (see GOTCHAS.md §dead WC selector).
   - **a11y + SEO (rule 6):** run the front-end audit snippet in SEO.md §a11y-seo-audit — 0 alt-less
     imgs, 0 span-buttons, exactly 1 H1, and title/description/OG/canonical/lang all present.

## Work general → specific (CSS and templates)
The single highest-leverage habit. Push every decision as far UP its ladder as it can live; step down
one rung only when the thing genuinely doesn't generalize. Specific layers override general ones
cleanly (that's the cascade / template-priority doing the work for you), and the general layers stay
small, auditable, and idempotent.

**CSS ladder (top = do first):**
1. **Global Settings** — palette swatches, heading/body fonts, container width. One write, every
   picker and element inherits it.
2. **Global stylesheet** (ONE CssCode node in the HEADER template — after the engine reset, before page CSS, so builder edits win) — `:root`
   design tokens (`--c-*`), base element classes (`.container`, `.section`, `.btn`, `.prose`),
   and brand overrides for third-party markup (WooCommerce). Grow it only via MARKED, idempotent
   blocks (RECIPES §node surgery).
3. **Reusable classes/selectors** — `.product-card`, `.post-card`: one class reused everywhere beats
   N copies of the same panel styling.
4. **Template-level CssCode** — styles that only one template family needs.
5. **Page-local `oxy_css()` node** — true one-page styles.
6. **Per-element panel settings** — LAST resort; invisible to grep, unmaintainable at scale.
   Symptom you've violated the ladder: the same color/spacing hand-set on many elements — hoist it.

**Template ladder (top = do first):**
1. **Site-wide chrome** — header/footer templates (these also carry the global CSS/JS nodes).
2. **Broad archetypes** — one template per WIDE type: `all-product-archives`, `product`, `post`,
   `post-archives`, `search`, `404`.
3. **Specialize with dynamic data, not new templates** — a term-aware header
   (`single_term_title()`/`term_description()`) lets ONE archive template serve shop + every
   category/tag. Fork a narrower template (`category`, single-term) only when structure — not
   content — must differ.
4. **Priority as tiebreaker** — when two templates match, higher priority wins; keep numbers spaced
   (e.g. 10/30/50) so later insertions fit between.
5. **Per-page trees** — real one-offs only (WC cart/checkout/account pages, landing pages).
   If you're about to duplicate a template to change one string, go back to rung 3.

## Two styling strategies (pick per task)
- **A. Native selectors** — every style as `breakpoint_base` groups on selectors attached via
  `meta.classes` (uuids). Click-to-edit in the design panel. Shapes: PROPERTIES.md.
- **B. Reference stylesheet** — native DOM + plain classes (`settings.advanced.classes`) + the
  reference CSS in the global CssCode node (comment-strip + `.breakdance`-prefix + `.bde-div` reset
  — GOTCHAS.md). Pixel-faithful; structure stays editable. This is how the original production site
  was built, porting an existing theme's stylesheet into Oxygen.
- **Hybrid (recommended endpoint — upgraded 2026-07-20, verified at scale)** — EVERY element
  carries a BEM class; the class is a registered SELECTOR holding ALL of its design: paint AND
  layout/position groups, responsive `breakpoint_*` sibling overrides, and pseudo/hover/descendant/
  media leftovers in the selector's `custom_css` (`oxy_selector($name, $groups, $customCss,
  $breakpoints)`). Layout wrappers must be `OxygenElements\Container`, not `EssentialElements\Div`
  (`oxy_flip_divs_to_containers()`) — the `.bde-div` engine reset (0,2,0) beats selectors (0,1,0);
  Container has NO engine CSS, so selectors win. When a theme tag rule / `.oxy-text` reset /
  `.bde-*` default still outranks a group prop, escalate that declaration inside custom_css per
  GOTCHAS §specificity ladder. Register selectors FIRST, then build trees through
  `oxy_promote_classes_to_selectors()` so class names attach as meta.classes uuids. Only genuinely
  SHARED rules (loop-context, cross-class groups) and RichText inner-tag typography stay in the
  reference stylesheet. Rule: an element without a class gives the user no design-panel handle —
  never ship one.

## Where to look
| Need | File |
|---|---|
| Element inventory (165) + source-file roots | ELEMENTS.md |
| Exact write-shapes: tree/node, selector property groups, Global Settings, template settings, element content keys | PROPERTIES.md |
| End-to-end recipes: pages, Gutenberg→components, WC PDP/PLP, **cart/checkout/my-account, search, 404, single-post, template-coverage checklist**, `.aux-box`, forms, menus, sliders, reference-CSS, images, footer rebuilds | RECIPES.md |
| Symptom→fix table + every trap that burned us (io-ts, dead WC selectors, .bde-div cascade, comment-strip, FAQ vars, Gutenberg wipe guard…) | GOTCHAS.md |
| Worked examples (page build, native loop, component placement, dynamic data, CSS injection) | scripts/examples/ |
| SEO: what WP/WC give free, an `seo` mu-plugin pattern (meta desc/OG/schema/archive-canonical), Oxygen H1 + empty-post_content gotchas, audit recipe | SEO.md |

> **Per-project inventory:** keep a `PROJECT-STATE.md` in your own repo tracking what's built where
> (template/component IDs, global setup, known debt). It's intentionally not shipped here — it's
> project-specific. See the README for the suggested shape.

## Known coverage gaps (Oxygen features this skill doesn't shape-document yet)
The original build didn't exercise these, so no verified write-shapes exist here. They're real Oxygen 6
features — for each, use the golden-sample workflow (build once in the real builder → read
`_oxygen_data`/options back) before writing programmatically, and consult the official docs topic:
- **Element display conditions & template condition arrays** (AND/OR rules; custom PHP conditions) —
  official docs: *Dynamic Data → Conditions*, *Templating → Conditions / Applying Templates*. This
  skill only uses template `type` + `priority`.
- **Component Properties** — parameterized component instances (per-placement text/image/visibility
  overrides). Without them, a placed component repeats identically everywhere (documented behavior
  here); with them, one block serves many contexts. Official docs: *Design → Components*.
- **Variables** (Color/Number/Unit/FontFamily/ImageURL collections, per-element overrides) — this
  skill covers only the Global Settings palette. Official docs: *Design → Variables*.
- **Native Interactions** (Click/Scroll-Into-View/Page-Load triggers → toggle class, show/hide) —
  this skill hand-rolls scroll-reveal with an IntersectionObserver JS node; Interactions are the
  builder-editable alternative. Official docs: *Design → Interactions*.
- **ACF / Meta Box dynamic data** — MOSTLY CLOSED: content model (RECIPES §ACF Pro content
  model) and the text binding shape (`acf_field_<FIELD_KEY>` + `text_dynamic_meta`,
  PROPERTIES §Dynamic data binding) are verified. Still unshaped: image/gallery-field bindings
  on Image elements, repeater loops, relationship queries — golden-sample when first needed.
- **Form hooks** — `breakdance_form_validate_field` filter + the developer Form Actions API, beyond
  the FormBuilder shapes in RECIPES. Official docs: *Forms → Hooks & Actions API*.

## The deadliest traps (details in GOTCHAS.md)
1. Missing `_nextNodeId`/`status` → builder "IO-TS decoding failed" (verified live; lib prevents).
2. `.breakdance .woocommerce X` matches NOTHING (both classes sit on `<body>`) — style WC via
   `.bde-*` wrappers + `!important`; verify with `element.matches()`.
3. `wp eval-file` is function-scoped: `global` silently yields empty — static registries only.
4. Engine's `.breakdance .bde-div{display:flex;…}` (0,2,0) collapses reference layouts — scope
   reference CSS under `.breakdance` + prepend the bde-div reset; STRIP comments before prefixing.
5. Never save an Oxygen page in the WP block editor (wipes `post_content` to a launcher; guard
   mu-plugin installed, but the rule stands — content belongs in the tree as native elements).
6. **Wrapping-link = `oxy_link()`/`ContainerLink`, NEVER `WrapperLink`** — WrapperLink outputs
   `href="#"` (classes still apply, so it looks fine while every link is dead). §wrapper-link-href.
7. **PhpCode `php_code` must start with `<?php`** or it prints as literal text. §phpcode-open-tag.
8. A code/`Component` node wraps its output in a block div → breaks a flex/grid PARENT (items go
   through one wrapper). `display:contents` on the code node, or emit the flex container inside it.
   §code-node-wrapper. Same shape: RichText wraps content, breaking `p+p`/direct-child CSS
   (§richtext-wrapper).
