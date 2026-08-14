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
2. **Builder-editability is NON-NEGOTIABLE.** Every piece of a page must be selectable and
   editable in the Oxygen visual editor. Not "preferably" — this is the acceptance bar, and a
   template that renders perfectly but cannot be edited in the builder is **not done**.
   **Litmus test: can the user select and edit this exact piece in the builder?**
   - One `Text`(hN) per heading, one `RichText` per paragraph, `Image`/`Button`/`FAQ`/… per piece.
   - ⛔ `EssentialElements\PostContent`, one giant RichText blob, or a PhpCode echoing
     `the_content` all FAIL the test.
   - **⚠ The old "PhpCode is a last resort for data-shaped output" wording was an escape hatch,
     and it got taken constantly** — a whole site was once built as PhpCode nav + PhpCode footer
     + PhpCode templates, each individually justified as "dynamic menus" or "a small leaf
     renderer", none of it editable. Justifying the exception one node at a time is how you end
     up with zero editable nodes. Assume there IS a native element and go find it.
   - Check ELEMENTS.md FIRST: 165 native elements. Menus → `MenuBuilder` +
     `MenuCustomDropdown`/`MenuCustomArea` (this is Oxygen's mega-menu primitive, and it takes
     arbitrary child elements — images, loops, anything). Lists → `PostsLoop`. Repeating
     structures → a `Component` (`oxygen_block`) with Component Properties.
   - If the native shape is **undocumented**, that is a reason to golden-sample it (build once
     in the real builder, read `_oxygen_data` back) and add it to PROPERTIES.md — NOT a reason
     to fall back to PhpCode. Extending this skill is cheaper than shipping dead weight.
   - Genuine remaining exceptions: a `CssCode` node holding the global stylesheet, and a small
     `JavaScriptCode` node for behaviour no native Interaction covers. Both are infrastructure,
     not content.
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

scripts/verify-site.sh [ids…] [--builder] [--detect]   # THE VERIFY BATTERY — run this, not the
                                       # individual checks: trees + selectors + panel lint +
                                       # front-end a11y/SEO, optionally the builder IO-TS pass
                                       # (needs OXY_LOGIN_URL) and the design detector
scripts/design-detect.sh <url>         # design antipatterns (handles the puppeteer setup)
node scripts/style-snapshot.mjs capture <url> before.json   # prove a refactor changed nothing:
node scripts/style-snapshot.mjs diff before.json after.json # capture, refactor, capture, diff
```
`assets/` holds copyable per-project mu-plugins: `mu-oxygen-entrance-fixes.php` (entrance a11y +
no-JS + ghost-init guard) and `mu-oxygen-dynamic-related.php` (relationship-hop dynamic fields).
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
⚠ **ONE `oxy_probe()` PER PROCESS.** Selector CSS regenerates once per process (same root cause as
GOTCHAS §3, re-confirmed on 6.1.1), so in a loop only the FIRST probe is real: later iterations
return the first one's CSS, or `(nothing emitted)`. Both failure modes look like findings — a loop
probing opacity returned an identical value for eight different inputs, which reads as "the property
is ignored" rather than "the tool is stale". Drive the loop from the SHELL, one `wp eval-file` per
case, and pass the value in via `getenv`. Renaming the probe within one process does NOT help.

**`oxy_probe($groups, $expect)`** — golden-sample an unknown property shape: registers a throwaway
selector, compiles, reports which declarations actually reached the CSS, restores the store
byte-for-byte. Use it before trusting ANY undocumented group ("the panel has a control" ≠ "my
guessed shape emits"); it handles the minified-CSS and palette-sentinel traps for you.
**`oxy_delete_selectors($names, $force=false)`** — the removal `oxy_save_selectors` can't do.
Refuses names still referenced by a live tree (revisions don't count); returns `needs_regen`, and
when true you must call `oxy_regen_selector_css()` **from a fresh process** (GOTCHAS §3).
- Worked examples (page build, native loop, component placement, dynamic data, CSS injection):
  `scripts/examples/` (patterns to copy).

## Workflow for any build
0. **Packaging decision — PROMPT the user, don't silently pick.** Before building any new section
   or widget, classify it: (a) one-off page tree; (b) reusable **Component** (`oxygen_block` —
   shows in the builder's Components panel, drags onto any page, content fully click-editable,
   but NO per-instance overrides — same content everywhere it's placed); (c) **custom Element**
   (own controls in the + panel, usually backed by a CPT so content is wp-admin work — PROPERTIES
   §Custom element plugin). When the piece could plausibly recur on other pages, or is an
   interactive/data-driven widget the client might want to drop elsewhere (a video wall, a quiz,
   a logo cloud, a stats band), ASK the user which packaging they want (AskUserQuestion with the
   trade-offs) — packaging determines who can edit what later and is expensive to change once
   content exists. Defaults if the user leaves it to you: page section → Component; markup + JS
   behaviour + own settings → custom Element + CPT; truly page-specific → plain tree, still built
   from classed native elements.
1. Write the tree with lib.php (or copy a `scripts/examples/` pattern). Unknown shape? `oxy_golden()`,
   or build once in the real builder + read `_oxygen_data` back.
2. Brand-style every new class (rule 1). Where CSS lives: global = the HEADER template's CssCode node (loads after the engine reset, before page CSS → builder edits still win)
   (preserve it when rebuilding #15!); page-local = an `oxy_css()` node on the page.
3. Verify — all five, every time:
   - `scripts/wp-eval.sh scripts/validate-tree.php <id> fetch` (io-ts invariants + trap checks + front-end 200)
   - open `http://example.local?oxygen=builder&id=<id>` — must load with no "IO-TS decoding failed"
   - CSS: check `element.matches(sel)` in the browser console — NEVER trust "the rule is in the file"
     (see GOTCHAS.md §dead WC selector). For an override, read the exact property back with
     `getComputedStyle()` — a rule can be in the compiled file and still lose the cascade
     (GOTCHAS §not-contributes-specificity).
   - **After any script that writes a tree or a stylesheet, re-read it FROM THE DATABASE** and
     assert what you expect is present. A write script's success message usually comes from a
     flag, not from the write (GOTCHAS §php-null-coalesce-breaks-references) — three scripts in
     one session reported success while writing nothing, and every check below stayed green.
   - **A green audit is not a rendered page.** io-ts, panel lint and the a11y/SEO sweep say
     nothing about layout: they all passed while the site rendered unstyled. Open a browser,
     and measure at 390 / 1280 / 1600 (GOTCHAS §container-padding-inside-the-cap,
     §min-height-plus-aspect-ratio-is-a-min-width).
   - **a11y + SEO (rule 6):** run the front-end audit snippet in SEO.md §a11y-seo-audit — 0 alt-less
     imgs, 0 span-buttons, exactly 1 H1, and title/description/OG/canonical/lang all present.
   - **panel-expressibility:** `scripts/wp-eval.sh scripts/examples/lint-panel-css.php` — every
     `⚠ panel-lint` finding is a declaration a property group can express. Move it, or justify it
     in-place with `/*panel-exempt: reason*/`. Zero unexplained findings = done. (The same lint
     fires from `oxy_selector()` at write time, so a build run that prints warnings is telling you
     about ITS OWN sins, not historical ones.)
   - **design antipatterns (optional, if the `impeccable` skill is installed):**
     `npx -y impeccable detect http://<site>/` from a dir with puppeteer available
     (`PUPPETEER_SKIP_DOWNLOAD=1 npm i puppeteer` + `PUPPETEER_EXECUTABLE_PATH` to system Chrome).
     It renders the page in headless Chrome and reports design tells AND mechanical defects.
     **Triage before acting:** `script-error` and `content-hidden-at-rest` are always real bugs
     (they caught an entrance replay-by-default and 9 ghost-init crashes on a build this skill's
     own audits had passed). Taste findings (`ai-color-palette`, `overused-font`, `kicker-above-
     heading`…) get judged against the BRAND MANUAL — the brief wins; a locked brand palette or
     face is never a finding to fix. `low-contrast … opacity stack` during entrance staggers is a
     mid-animation screenshot, not a defect — see PROPERTIES §Entrance.

## Design direction & taste (bridge to the design skills)

This skill owns the Oxygen MECHANICS — shapes, panels, verification. Design DIRECTION is owned by
the design-taste skills when they're installed (`design-taste-frontend` for anti-slop bias
correction on new surfaces, `impeccable` for critique/audit/refinement playbooks). Load one BEFORE
designing any new page or section from scratch; skip them when porting an approved design — there,
the mockup/brand manual is the brief and **the brief always wins** over any taste rule (a brand
lilac stays lilac even though a detector calls it "AI purple").

The handful of their rules that recur on builder-page work, distilled (full sets live in those
skills):

- **One entrance moment, not one per section** — and entrances are `advanced.once => true`, below
  the fold only (PROPERTIES §Entrance). Scroll-scrub and entrance are the two motion systems; a
  third needs a reason.
- **No duplicate CTA intent on a page** — one label per intent (contact, test, buy), reused
  verbatim wherever that intent appears.
- **Eyebrow/kicker restraint** — max ~1 per 3 sections, and never the same eyebrow text twice on
  one page. If a section needs a label, its position on the page usually already labels it.
- **Layout-family repetition** — a section layout (cards-row, split, full-quote) appears at most
  once per page; two adjacent sections must not read as the same template.
- **Uppercase is for short labels** — a 40+ character all-caps CTA label is a copy problem, not a
  styling one.
- **States are part of done** — hover, focus, disabled, loading, empty, error; this skill's a11y
  checks cover focus/contrast, the design skills cover the rest.

## Work general → specific (CSS and templates)
The single highest-leverage habit. Push every decision as far UP its ladder as it can live; step down
one rung only when the thing genuinely doesn't generalize. Specific layers override general ones
cleanly (that's the cascade / template-priority doing the work for you), and the general layers stay
small, auditable, and idempotent.

**CSS ladder (top = do first):**
1. **Global Settings** — palette swatches, heading/body fonts, container width, and site-wide
   Button styling (`settings.buttons.primary/secondary` — PROPERTIES §Global Settings → Buttons).
   One write, every picker and element inherits it.
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
- **`custom_css` is GATED, not merely discouraged.** `oxy_selector()` lints every custom_css block
  at write time (`oxy_panel_lint()`, lib.php) and warns on any plain `:selector{…}` declaration a
  property group can express — typography, background-color, spacing, border-radius, size, display/
  gap, position. Legitimate custom CSS is exactly: pseudo-states, descendant/context rules
  (RichText inner tags, ground flips, third-party markup), non-width media queries, custom
  properties, and the overflow double-write — everything else either moves to a group or carries an
  in-code justification: `/*panel-exempt: <why no group works>*/`. Audit an existing site with
  `scripts/examples/lint-panel-css.php`; a build whose run output prints `⚠ panel-lint` is not done.

## Where to look
| Need | File |
|---|---|
| **Type & spacing — the DEFAULT system for text/headings/buttons/sections** (responsive modular scale + spacing-rhythm tokens; role→step map; a11y outline) | **TYPOGRAPHY.md** |
| Element inventory (165) + source-file roots | ELEMENTS.md |
| Exact write-shapes: tree/node, selector property groups, Global Settings, template settings, element content keys | PROPERTIES.md |
| End-to-end recipes: pages, Gutenberg→components, WC PDP/PLP, **cart/checkout/my-account, search, 404, single-post, template-coverage checklist**, `.aux-box`, forms, menus, sliders, reference-CSS, images, footer rebuilds, **host/domain migration** | RECIPES.md |
| Symptom→fix table + every trap that burned us (io-ts, dead WC selectors, .bde-div cascade, comment-strip, FAQ vars, Gutenberg wipe guard…) | GOTCHAS.md |
| Worked examples (page build, native loop, component placement, dynamic data, CSS injection) | scripts/examples/ |
| SEO: what WP/WC give free, an `seo` mu-plugin pattern (meta desc/OG/schema/archive-canonical), Oxygen H1 + empty-post_content gotchas, audit recipe | SEO.md |
| Media & motion: photo/video hero+scrim, hover-play video (GIF alt), scroll-scrubbed & autoplay-once Lottie, custom JS carousel, mix-blend duotone, 2-weight self-hosted fonts, ACF logo-cloud component | RECIPES.md §Media & motion |

> **DEFAULT typography.** For ANY build, set up all text/headings/buttons/section-spacing with the
> token system in **TYPOGRAPHY.md** (define `--fs-*`/`--sp-*`/`--tc-*` once in the global CssCode
> node; every size/space/colour references a token; audit that 0 literal `font-size` remains on
> text). It's rung 2 of the CSS ladder — do it before per-page styling.

> **Per-project inventory:** keep a `PROJECT-STATE.md` in your own repo tracking what's built where
> (template/component IDs, global setup, known debt). It's intentionally not shipped here — it's
> project-specific. See the README for the suggested shape.

## Known coverage gaps (Oxygen features this skill doesn't shape-document yet)
The original build didn't exercise these, so no verified write-shapes exist here. They're real Oxygen 6
features — for each, use the golden-sample workflow (build once in the real builder → read
`_oxygen_data`/options back) before writing programmatically, and consult the official docs topic:
- ~~**Template condition arrays**~~ — **NOW SHAPED (2026-08-07):** `ruleSlug` + operand strings,
  OR across groups / AND within a group. See PROPERTIES §Template condition ruleGroups. Still
  unshaped: *element display* conditions and custom PHP conditions.
- ~~**`MenuBuilder` + `MenuCustomDropdown` / `MenuCustomArea`**~~ — **NOW COVERED (2026-08-07).**
  Native menu / mega-menu primitives; `MenuCustomArea` takes arbitrary children, so image-led
  panels are native, not code. A PhpCode nav is NOT an acceptable substitute. Read
  GOTCHAS §dropdown-paint-the-body (what to paint, what never to touch) and
  §mega-menu-floater-anchoring (the floater is anchored to its TRIGGER and overflows the
  viewport on right-hand items — measured 606px; plus the hover grace period).
- ~~**Components / Global Blocks**~~ — **NOW FULLY SHAPED (2026-08-06).** A reusable Component IS an
  `oxygen_block` post with a normal `_oxygen_data` tree (RECIPES §Reusable Global Block), AND
  **Component Properties per-instance overrides WORK** in 6.1.0 — the old "hook never called,
  duplicate the block instead" note was wrong/outdated. It needs BOTH halves: `editableProperties`
  on the block's node and `targets`+`properties` on each placement. Full shape and the render path:
  PROPERTIES §Component Properties. Reach for this before duplicating a component to change a string.
- **Variables** (Color/Number/Unit/FontFamily/ImageURL collections, per-element overrides) — this
  skill covers only the Global Settings palette. Official docs: *Design → Variables*.
- **An official Oxygen MCP server — NEW, UNMAPPED, and potentially supersedes chunks of this skill.**
  6.1.0 ships nothing matching `mcp` (verified: zero files/dirs). The 6.2-beta2 changelog references
  it twice — "stop the MCP set-global-settings tool from breaking global settings saves" and
  "support `:where()`/`:is()` selectors in MCP" — so there is at least a global-settings tool and
  selector/CSS handling. This skill's whole method is hand-authored PHP through `wp eval-file`;
  a supported tool surface for global settings and selectors would replace some of the fiddliest
  parts (double-encoded `global_settings_json_string`, hand-built property groups). **Enumerate the
  actual tools on a 6.2 install before writing any of it down — do not transcribe the changelog.**
- ~~**Native Interactions**~~ — **NOW SHAPED (2026-08-06, click-toggle verified in a browser):**
  `settings.interactions.interactions`, full trigger/action vocabulary in PROPERTIES §Interactions.
  Together with entrance animations (PROPERTIES §Entrance) this covers most of what used to need a
  JavaScriptCode node — reach for them BEFORE hand-rolling show/hide, tabs, toggles or reveals.
  Still hand-rolled here: scroll-scrubbed / autoplay-once Lottie and hover-play video
  (RECIPES §Media & motion). Official docs: *Design → Interactions*.
- **ACF / Meta Box dynamic data** — MOSTLY CLOSED: content model (RECIPES §ACF Pro content
  model), the text binding shape (`acf_field_<FIELD_KEY>` + `text_dynamic_meta`,
  PROPERTIES §Dynamic data binding), and now the **ACF options-page + image-repeater loop**
  rendered via PhpCode (RECIPES §ACF logo-cloud component) are verified. Still unshaped: image/gallery
  bindings on the native *Image* element, relationship queries — golden-sample when first needed.
  ⚠ Read top-level ACF fields by **KEY**, never by name — GOTCHAS §acf-read-top-level-fields-by-key.
- ~~**Term loops / taxonomy templates / search**~~ — **NOW SHAPED (2026-08-07).**
  `OxygenElements\TermLoopBuilder`, the `taxonomy-archive` + `search` template conditions, and
  `EssentialElements\SearchForm`: PROPERTIES §Shapes verified on the Bold & Groovy build.
  ⚠ `OxygenElements\DynamicDataLoop` resolves rows against `get_the_ID()` and therefore
  **cannot** read a repeater stored on a term.
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
8b. `wp eval-file` can SILENTLY no-op a whole build script (exit 0, no output, no write) — long
   single lines are one trigger. Run big builds via a tiny `include`-runner file; if a build
   "succeeds" without its echo output, suspect this first. §eval-file-silent-noop.
8c. `OxygenElements\Image` renders the `<img>` ITSELF with your class — style/crop the class
   directly, never `.cls img`. §native-image-is-img. Lazy imgs inside transformed carousel
   tracks never load (§carousel-lazy-img); globbing a folder WP writes thumbnails into
   cascades imports (§thumbnail-cascade).
8. A code/`Component` node wraps its output in a block div → breaks a flex/grid PARENT (items go
   through one wrapper). `display:contents` on the code node, or emit the flex container inside it.
   §code-node-wrapper. Same shape: RichText wraps content, breaking `p+p`/direct-child CSS
   (§richtext-wrapper).
9. **`global` fails in build HELPER functions too** (not just top scope) — a helper using
   `global $base` under `wp eval-file` yields empty → relative img `src` → 404. Hardcode/param the
   path. §global-in-helper.
10. **Inline-SVG / SVG-`<img>` renders 0×0** — `.breakdance img{height:auto}` beats a class
    `height`, and a `width/height:100%` SVG has no intrinsic size. Size it with a 2-class selector
    or explicit width+height. §svg-height-auto.
11. **A copied asset is 0 bytes** (Dropbox/iCloud online-only placeholder) — 200s but renders
    blank. `stat -f%z` the source before copying; "Make Available Offline" first. §dropbox-zero-byte.
12. **Heading text stays dark on a dark hero** — set `color` on the heading's OWN class, not the
    section. §heading-color-reset. And **`oxy_button` shows a blue inner box** — neutralize
    `.btn.bde-button .bde-button__button`. §button-inner-box.
13. **`wp_slash()` on BOTH `_oxygen_data` AND `_oxygen_template_settings`** — `update_post_meta`
    unslashes, and the settings value is a JSON string containing escaped JSON, so writing it
    unslashed leaves invalid JSON. The template then matches nothing and its stylesheet never
    loads: a whole site renders with no design tokens, with no error anywhere and the meta row
    still populated. Ran green through five deploys. §template-settings-double-encode.
14. **`post_type => 'any'` skips every Oxygen post type** (they set `exclude_from_search`), so a
    "rebuild all trees" loop silently misses every header/footer/template/block. List the types.
    And `oxygen clear_cache` rebuilds GLOBAL css only — per-post files need their own regen.
    §post-type-any-skips-oxygen-cpts.
15. **Assert on the RENDERED page, not the write.** Both traps above report success at every
    layer — the write returns true, the row is populated, the page 200s. `curl | grep` for the
    stylesheet filenames the page must link; it is the only check that catches either.
