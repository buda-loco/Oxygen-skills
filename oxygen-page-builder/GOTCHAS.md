# Gotchas — symptom → cause → fix

Every entry here was hit for real on the production build this skill came from. Scan the table, then
read the matching section. (`site-builds/xxx.php` citations = that project's private script archive;
representative examples ship in `scripts/examples/`.)

| Symptom | Cause → fix |
|---|---|
| Builder shows "Validation Error: IO-TS decoding failed" | Tree missing `_nextNodeId` and/or `status` → §io-ts |
| Node ids / class uuids silently empty | `global $x` inside `wp eval-file` → §eval-file scope |
| `wp eval-file script.php --myflag` — flag missing from `$args` | wp-cli swallows unknown `--flags` itself → pass positional words instead (`fetch`, not `--fetch`) |
| Heading color/size won't change despite class | Class on RichText WRAPPER, rule `h1..h6{}` wins → §headings |
| CSS rule is in post-15.css but styles nothing (WC page) | `.breakdance .woocommerce X` matches NOTHING → §dead WC selector |
| Grids/flex collapse into left-aligned vertical stacks | Engine `.breakdance .bde-div{display:flex;…}` at 0,2,0 beats single-class rules → §bde-div cascade |
| Whole stylesheet dead after prefixing (all tokens undefined) | A CSS comment glued to `:root` made the prefixer emit `.breakdance :root` → §comment-strip |
| FAQ: border too dark / last item unbordered / padding gone | You fought the element's own CSS vars → §FAQ vars |
| FAQ renders empty | Data in `questions` only; front-end reads `content.settings.items` → §render keys |
| Button renders `<button>`, doesn't navigate | `link` missing `type` or `url` — needs BOTH `{type:'url', url:…}` |
| A link wrapping children renders `href="#"` | Used `EssentialElements\WrapperLink` (its `url` key doesn't render) → use `OxygenElements\ContainerLink` → §wrapper-link-href |
| PhpCode/HtmlCode prints its source as literal text | `php_code` string didn't start with `<?php` → §phpcode-open-tag |
| Ported `p + p` / `.x > child` CSS doesn't apply in Oxygen | RichText/Text wraps content in a `.bde-rich-text` div, breaking adjacent/direct-child selectors → §richtext-wrapper |
| A code/Component node stacks/misaligns inside a flex/grid parent | The node wraps its output in a block div, so the parent lays out ONE wrapper, not the inner items → §code-node-wrapper (fix: `display:contents`) |
| Reference CSS keyed to an `<html>` class (e.g. `.js`) silently dead | The `.breakdance ` prefixer rewrote it to `.breakdance .js …`, which never matches (`.js` is on `<html>`, body's PARENT) → §html-class-prefix |
| Accent-filled button inside your outline box | Wrapper classes don't restyle inner atom — target `.bde-button__button` with `!important` |
| Body copy unreadable (#333 on black) on dark sections | Container recolor only inherits; direct `.prose p{color}` wins → §invert leaf recolor |
| `.loc-grid` children stack in one column | Gutenberg group has an inner container — grid `.loc-grid > .wp-block-group__inner-container` |
| Page's Gutenberg content wiped to a 49-byte launcher | Saved an Oxygen page in the WP block editor → §Gutenberg wipe (guard installed) |
| `oxygen_template` ignored despite settings meta | Settings stored as PHP array (reads back "Array") — must be a JSON STRING → PROPERTIES.md §templates |
| Form success/error banner unstyled | Class is `.breakdance-form-message--success` (single dash after "form", not `__message`) |
| Form submits but no email arrives | `content.actions.email.emails[0].to` defaults to `false` → set a recipient |
| Section content pinned left | `.section-container` is `align-items:flex-start` by default |
| Related/upsell `<h2>` has no bottom margin | Engine ships `…section.related.products>h2{margin-bottom:0}` at 0,3,2 → `!important` on `.bde-related-products h2,.bde-upsell-products h2` |
| Slide background image tiny/repeating | It renders on inner `.advanced-slider__slide` (744px default), not `.bde-advancedslide` → RECIPES.md §slider |
| Store shows "Coming Soon" placeholder | WooCommerce 10.x ships it ON → `update_option('woocommerce_coming_soon','no')` |
| Imported menu links point at the old domain | WXR keeps source URLs → `wp search-replace 'old.local' 'new.local' --all-tables` |
| Template's CSS stale after `oxy_write_tree` (tree meta verified new, `?v=` hash unchanged) | Page regen doesn't compile template CSS — `generateCacheForPost($templateId)` too → §template-cache |
| Site has no images/CSS through an https tunnel (Live Links/ngrok) | Trees store absolute `http://` URLs; proxy rewrites host not scheme → §absolute-urls (mind both traps) |
| Scripted `<img>` ships bare full-size src, no srcset | Renderer prints pre-computed `media.attributes.srcset|sizes` strings the builder UI normally fills → §image-srcset |
| A heading shows up in the builder as "Text" with the wrong controls/preview styles | Built as Text + `settings.advanced.tag='h1'` (renders identical front-end markup!) → use `EssentialElements\Heading` (`content.content.text` + `content.content.tags`) — `oxy_heading()` in lib.php |
| Selector merge re-runs orphan every meta.classes reference / promotion attaches nothing | `saveSelectors()` persists a FLAT array, not the `{selectors:[…]}` wrapper it accepts — readers expecting the wrapper see an empty store → §selector-store |
| Panel edits on a class do nothing; class layout collapses | Selectors compile UNPREFIXED (0,1,0) into `oxy-selectors.css`, losing to the engine reset and the reference sheet → paint-on-selectors, layout-on-elements/stylesheet split → §selector-cascade |
| Whole sections invisible in the builder canvas (front end fine) | JS-gated reveal CSS hides them; the observer never fires in the canvas iframe → skip the gate + behaviour JS when `?breakdance_iframe=1` → §canvas-reveal |
| Image element shows empty "Choose" in the builder (renders fine on front end) | Scripted media object too minimal — control needs full `wp_prepare_attachment_for_js()` JSON → §image-media-shape |
| A tiny image is inexplicably huge (a 450px logo at 550KB) | It carries a monster embedded ICC/Photoshop profile — sips AND ffmpeg preserve it (even `-map_metadata -1`). Re-encode with PIL (`Image.convert('RGB').save()` without `icc_profile`) to drop it (verified 1.25MB→8KB) |
| Dynamically-bound cover image ships full-size (no srcset) | Oxygen Image url-mode (`post_featured_image_url`) can't carry srcset — render the cover via a PhpCode `wp_get_attachment_image(get_post_thumbnail_id())` inside the loop instead |
| Client reports "the post type was never created" (ACF screens empty) but the CPT works | Code-registered CPTs/groups are invisible in ACF's admin UI → migrate to the ACF store + JSON sync for handover → RECIPES §ACF Pro content model |
| CPT registered twice / fatal after ACF-store migration | The same post type must live in ONE home — remove the `register_post_type()` code when moving to the ACF store → RECIPES §ACF Pro content model |
| CPT singles 404 right after registration changes | Stale rewrites → `flush_rewrite_rules()` once (option-flag pattern) |
| Programmatic `.svg` sideload returns an upload error / attachment #0 | WP disallows the svg mime — scoped `upload_mimes` filter around the write; also hand-write width/height metadata or the `<img>` ships without dimensions → RECIPES §Elegant SVG placeholders |
| ACF fields added programmatically don't appear in the editor (script reported success) | Local JSON wins reads by key; DB-API mutations are invisible — rebuild the group from ONE canonical def + write the JSON directly → §acf-json-mutation |
| PostsLoop renders "Choose a Component from the dropdown" per item | `repeated_block.global_block` is null — an undefined `$card` PHP var serialized; resolve the oxygen_block id (by title) BEFORE writing → RECIPES §Related-posts as a native PostsLoop |
| Static-first host 404s every `?query` URL (search, `?type=` filters) but bare paths work | `.htaccess` `-f` test used `%{REQUEST_URI}`, which keeps the query string on LiteSpeed → test the path capture `$1` instead → §static-first-litespeed |
| Full-height `position:fixed` overlay/drawer renders only as tall as its parent; children spill past its background | An ancestor has `transform`/`filter`/`backdrop-filter`/`will-change` → it's the containing block, not the viewport → size with `height:100dvh`, anchor `top:0` → §fixed-in-filtered-ancestor |

---

## §io-ts — builder-strict, renderer-lenient

The PHP renderer only needs `root/id/data/children`. The **builder** runs strict io-ts codecs on load —
a tree can render on the front-end yet fail to open in the builder.

**Verified live (2026-07-11, builder 6.1.0):**
- Missing `_nextNodeId` + `status` → **"IO-TS decoding failed"** (reproduced on a test page).
- `root.data.properties = []` vs `{}` → **both open fine** (home #10 and footer #15 have `[]`;
  Breakdance's own fallback templates ship `[]`). Earlier "must be `{}`" advice was overcautious —
  still WRITE `{}` (it's what the builder itself saves; `scripts/lib.php` does this) but don't
  "fix" existing trees just for this.
- Also required: unique **int** node ids; every non-root node's `_parentId` = its real parent's id.

`scripts/validate-tree.php` checks all of this. Final proof is always opening
`http://example.local?oxygen=builder&id=<ID>` (io-ts runs client-side; nothing server-side can fully prove it).

## §accessible-link — Button is the ONLY element that renders a real `<a>` (verified 2026-07-19)
**For a semantic, keyboard-focusable link/CTA, use `EssentialElements\Button` (`oxy_button`).** It's the
only link-type element whose rendered root is a true anchor: `<div class="bde-button …"> ><a
class="bde-button__button" href> ><span class="button-atom__text">TEXT</span></a>`. The `<a>` is
focusable, announced as a link, and navigates without JS.
- ⚠ **`OxygenElements\TextLink` and `EssentialElements\TextLink` both render a `<span href>`** (plus a
  `breakdance-link` class + JS click handler), NOT an `<a>`. So does **`OxygenElements\ContainerLink`**
  (`<span class="oxy-container-link" href>`). They're clickable via JS but are **not** semantic/focusable
  anchors — an accessibility fail for a CTA. A "fake button" `Text`/`<span class="btn">` is worse still
  (no href at all). See project rule 6.
- **Styling caveat:** with Button, your `advanced.classes` (e.g. `btn btn--ghost`) land on the OUTER
  `.bde-button` wrapper div — NOT the visible `<a>`. So brand button CSS must target the anchor:
  neutralize the wrapper (`.btn.bde-button{background:none!important;border:0!important;padding:0!important}`)
  and style `.btn.bde-button .bde-button__button{…}` (+ `.button-atom__text{color:inherit}` so the label
  colour follows). Scope to `.bde-button` so plain non-Button `.btn` elements are untouched.
- Use **ContainerLink** only when you must wrap ARBITRARY CHILD ELEMENTS in a click target (logo→home,
  image card, whole-tile link) and semantic-anchor focus isn't required — see §wrapper-link-href for its
  working shape. For a text/label CTA, prefer Button.

## §wrapper-link-href — WrapperLink outputs `href="#"` (ContainerLink renders a `<span href>`, not `<a>`)
To wrap arbitrary children in a click target, use **`OxygenElements\ContainerLink`**, NOT
`EssentialElements\WrapperLink`. ⚠ Note ContainerLink renders a **`<span>`** (+ `breakdance-link` JS),
not a semantic `<a>` — fine for a clickable card, but for a focusable/semantic link use Button
(§accessible-link). ContainerLink's `html.twig` is `%%CHILDREN%%` and its
render reads href from **`content.content.url`** (string) + target from
`content.content.open_in_new_tab` (bool → `_blank`/`_self`). WrapperLink's
`defaultProperties` also advertise `content.content.url`, but that key does NOT render an
href — the element outputs `href="#"` regardless (a §render-keys mismatch; its true render
key wasn't locatable — it registers dynamically, no element dir). Symptom is nasty: brand
CSS classes on the link still apply, so it LOOKS right in screenshots while every link is
dead. Shape that works (set all three so both render AND the builder link-field populate):
```php
oxy_el('OxygenElements\\ContainerLink', ['content'=>['content'=>[
  'url' => $url,                                 // render reads this → href
  'link' => ['type'=>'url','url'=>$url],         // builder link control reads this
  'open_in_new_tab' => $external,                // → target="_blank"
]]], $children);
```
Verify with the RENDERED html (`grep href=`), never by trusting the class list.

## §phpcode-open-tag — PhpCode must start with `<?php`
`OxygenElements\PhpCode`'s `php_code` is executed as a PHP file: if the string doesn't begin
with `<?php`, the whole thing is emitted as **literal text** (e.g. a nav printed
`<a href="%s">` verbatim). Every `php_code` heredoc starts with `<?php` on its own line.
(HtmlCode is the opposite — raw markup, no tag.)

## §richtext-wrapper — RichText/Text wrap their content in a div
Every `oxy_rich`/`oxy_text` renders its content INSIDE a wrapper div
(`.bde-rich-text`, etc.). So ported CSS that assumes **adjacent** or **direct-child**
relationships between content pieces won't match if you split them across separate nodes:
- `.body p + p{margin-top}` → put consecutive `<p>` in **ONE** RichText (real adjacent
  siblings). Separate RichText nodes each nest their `<p>` a level down → rule never matches
  (symptom: crammed paragraphs).
- `.row{display:grid} .row > .k / .row > .v` stacking → make `.k`/`.v` **direct children**
  (two `oxy_text` spans in the parent), not both inside one RichText (symptom: "KV" run
  together, no gap).
General rule: if a reference rule relies on element adjacency/child position, mirror that DOM
shape with native nodes — don't bury it in one RichText.

## §code-node-wrapper — code/Component nodes break a flex/grid parent
Code (`HtmlCode`/`PhpCode`) and `Component` nodes render their output inside a block
wrapper div (`.oxy-html-code`, `.oxy-php-code`, the component wrapper). So if the PARENT is
`display:flex/grid` and expects the inner `<a>`/items as **direct children**, the parent
lays out the ONE wrapper instead (symptom: nav links or icons stack/misalign; a horizontal
row goes vertical). Fix: give the code node a class and set **`display:contents`** on it so
its children rise to become the parent's flex/grid items:
```css
.nav-links, .menu-php { display: contents; }   /* wrapper vanishes from layout */
```
(Or restructure so the styled flex/grid container is emitted INSIDE the code node.)

Subtler variant (2026-07-16): an inline `<svg>` icon inside an HtmlCode node, centred by
the parent's `place-items:center` circle, sits ABOVE centre — the grid centres the wrapper
div, while the svg inside it rides the text baseline (line-height space below). Fix both
levels: `display:contents` on the wrapper AND `display:block` on the svg.

## §html-class-prefix — reference CSS keyed to an `<html>` class
When scoping the reference stylesheet under `.breakdance ` (§bde-div cascade), the prefixer
must NOT prefix selectors that start at the `<html>` level. A JS flag like `html.js` (common
for "hide until revealed" patterns) written as `.js …` gets rewritten to `.breakdance .js …`
— which matches **nothing**, because `.js` is on `<html>`, the PARENT of `body.breakdance`.
Same root cause as §comment-strip's `:root` failure. Fix: write such rules starting with
`html` (e.g. `html.js :is(...)`) so the prefixer's `^(html|body|:root|\*)` skip leaves them
intact; or put the flag class on `<body>` instead of `<html>`.

## §eval-file scope
`wp eval-file` runs the script body inside a FUNCTION scope: top-level `$vars` are not global, so
`global $x` in a helper silently yields empty (this emptied node ids and class uuids twice).
Use `static` registries — `scripts/lib.php` (`oxy_nid()`, `oxy_uuid()`) already does.

## §headings
`global-settings.css` has a direct `h1..h6{color;font-size}` rule. A RichText renders `<h1>` inside
a wrapper `<div>`; a class on the wrapper only *inherits*, and the direct tag rule wins. Use
`OxygenElements\Text` + `settings.advanced.tag: h1` so the class lands ON the `<h1>` (0,1,0 beats 0,0,1).

**Per-section heading COLOUR:** Global Settings emits `h1..h6{color:var(--bde-headings-color)}` at
(0,0,1) — a direct tag rule that beats a section container's inherited colour. If a design inverts
headings per section (light-on-dark hero/CTA vs dark-on-light body), a FIXED headings colour makes
some headings invisible (e.g. ink-on-ink). Set the Global Settings headings colour to **`inherit`**
(`settings.colors.headings = 'inherit'` → `--bde-headings-color: inherit`) so each heading takes its
section container's colour, restoring the reference stylesheet's model.

**Heading rhythm:** reference `base.css` gives headings bottom margin only. Ported prose reads crammed:
add `.prose h2{margin-top:var(--sp-10)}` / `h3{--sp-8}` / `h4{--sp-6}` + `.prose>*:first-child{margin-top:0}`.

## §dead WC selector (the big one)
On a Breakdance-rendered page, `breakdance` and `woocommerce` are BOTH classes on `<body>` — never
nested. So `.breakdance .woocommerce X` (what the prefixer produces from `.woocommerce X`) is a
descendant chain that **matches zero elements**: the rule compiles into `post-15.css`, grep finds it,
and it silently styles nothing while engine defaults (`breakdance-woocommerce.css`) win.

Native WC elements render under `.bde-*` wrappers + an inner `.breakdance-woocommerce` div (NOT `.woocommerce`).
**Fix — target the real wrappers** (these ARE nested under `body.breakdance`), with `!important` to beat
equal-specificity engine defaults:
- price → `.bde-wooproductprice .amount` (whole-product block: `.bde-product .price .amount`)
- main CTA → `.bde-wooproductcartbutton button.single_add_to_cart_button`
- quantity stepper → `.bde-quantity-button`
- loop/related/upsell card buttons → `.bde-woo-product-footer a.button` (catches simple
  `a.add_to_cart_button` AND variable `a.button.product_type_variable`)

**Verify with `element.matches(sel)` in the browser console, never by grepping the CSS file** —
presence in the file ≠ matching an element.

**⚠ NUANCE (verified 2026-07-11): the WC *page* elements DO nest a `.woocommerce` div.**
`EssentialElements\{Woopageshoppingcart,Woopagecheckout,Woopageaccount}` render
`<div class="bde-woopage… breakdance-woocommerce"><div class="woocommerce">…</div></div>` — so on
cart/checkout/my-account, `.breakdance .woocommerce X` **DOES match** (confirmed via `element.matches`
returning true for `.breakdance .woocommerce button.button`). The dead-selector trap is specific to the
single-**product** `Product` element (`.bde-product.breakdance-woocommerce`, no inner `.woocommerce`).
So the brand `woocommerce.css` `.breakdance .woocommerce …` rules DO style cart/checkout/account; they
DON'T style the PDP. Still always confirm with `element.matches`, per element.

## §WC notice blue-dot + dashboard blue box (engine defaults leak)
Two off-brand WooCommerce styles from the engine's `breakdance-woocommerce.css`, not from brand CSS:
1. **Notice icon**: `.woocommerce-info::before` (and `-message/-error`) is an empty 16px **blue circle**
   (`background:#0ea5e9`, `content:""`) that overlaps the notice text on cart-empty / coupon / no-orders.
   Kill it: `.breakdance .woocommerce-info::before,…{content:none!important;display:none!important;background:none!important}`.
2. **My-account dashboard greeting** is a *plain* `<p>` (no class) that the engine styles as a blue info
   box (`bg:#e0f2fe`, blue `::before`, 48px left pad) and forces WC links blue. Override scoped to
   unclassed paragraphs so real notices are untouched:
   `.breakdance .woocommerce-MyAccount-content > p:not([class]){background:transparent!important;padding:0!important;color:var(--c-ink)!important}` (+ `::before{content:none}`), and
   `.breakdance .woocommerce-MyAccount-content a{color:var(--c-ink)!important}`.

## §`.form-row` class collision (custom vs WooCommerce)
A generic brand rule `.form-row{display:grid;grid-template-columns:1fr 1fr}` (built for a custom checkout)
**collides with WooCommerce's native `.form-row`** class — every WC checkout/account row becomes a
2-col grid that puts the **label in column 1 and the input in column 2**, disconnecting them (symptom:
"form is hard to read, labels far from fields"). Fix: scope WC form layout explicitly and override the
grid — `.breakdance .woocommerce form .form-row{display:flex!important;flex-direction:column;gap:7px}`
(label above input), and put paired fields side by side via the wrapper:
`.woocommerce-billing-fields__field-wrapper{display:grid;grid-template-columns:1fr 1fr}` +
`.form-row{grid-column:1/-1}` / `.form-row-first{grid-column:1}` / `.form-row-last{grid-column:2}`.
General lesson: brand utility class names (`.form-row`, `.section`, `.container`) can collide with
plugin/WC markup — scope WC overrides under `.woocommerce …`.

## §oxy_golden() fatals on some dynamic/WC elements (CLI)
`oxy_golden('EssentialElements\\Woopageshoppingcart' | 'Woopagecheckout' | 'Woopageaccount' |
'PostTitle' | 'PostContent')` throws a WP **"critical error"** fatal under `wp eval-file` (their
`defaultProperties` touch WC/loop globals absent in CLI). These elements take `properties: null` anyway
(like `Product`) — build the node with `oxy_el($slug, null)` and skip the golden.

## §full-page screenshots blank late-painted sections
`take_screenshot(fullPage:true)` sometimes renders a section (e.g. the footer `.info-bar` accent band) as
**empty** even though the DOM has its content — a capture/stitch artifact, not a bug. Confirm anything
that "looks empty/broken" in a full-page shot with a **viewport** screenshot (scrollIntoView first) or
`element.innerText` before treating it as a defect.

## §bde-div cascade
Engine ships `.breakdance .bde-div{display:flex;flex-direction:column;align-items:flex-start;text-align:left;max-width:100%}`
at specificity 0,2,0. Every Div gets `.bde-div`, so this beats your single-class reference rules
(0,1,0) and collapses grids/flex into left-aligned stacks. Fix (both parts):
1. Scope the ENTIRE reference stylesheet under `.breakdance ` (equal 0,2,0 + loads later = wins).
   Leave `html/body/:root/*` rules unprefixed; prefix inside `@media`;
   leave `@keyframes`/`@font-face` bodies verbatim.
2. Prepend a reset FIRST: `.breakdance .bde-div{display:block;flex-direction:row;align-items:normal;justify-content:normal;text-align:inherit}`.

**Where the CssCode node goes: the HEADER template, not the footer** (corrected 2026-07-16).
The engine reset lives in the `post-*-defaults.css` files, and ALL `-defaults.css` load before
ALL non-default CSS. Non-default order is header → page → footer:
```
post-*-defaults.css      engine reset (0,2,0)
global-settings.css
post-<header>.css        ← reference stylesheet: after the reset → wins the tie
post-<page>.css          ← BUILDER EDITS: after the reference CSS → the user wins
post-<footer>.css
```
In the footer the stylesheet also beats the reset — but it beats the PAGE css too, so every
style a user sets in the builder silently loses the 0,2,0 tie (verified: a hero font-weight
change saved+compiled correctly into post-<page>.css yet stayed invisible — reads exactly
like "is there a cache?"). The header slot satisfies both constraints.

## §comment-strip (prefixing killer)
**STRIP all CSS comments BEFORE prefixing.** A `/* label */` glued in front of a selector makes the
token start with `/`, defeating the `html/body/:root/*` skip; `:root` becomes `.breakdance :root`
(matches nothing — `:root` is `<html>`, body's PARENT), which undefines every design token and kills
the whole stylesheet. Skip prefixing when the trimmed selector starts with `:` or `*` or matches `^(html|body)\b`.
Note `.breakdance-form-*` selectors contain the substring `.breakdance`, so the prefixer leaves them
alone — scope form styling under your own wrapper class for specificity instead.
Two more prefixer rules (burned 2026-07-17):
- Split selector lists on TOP-LEVEL commas only (track paren depth) — a naive `split(',')`
  prefixes the INSIDE of `:where(h1, h2, …)` / `:is(…)`, silently changing the rule.
- Base TAG rules (`h1{}`, `p{}`, `a{}`) prefix to `.breakdance h1` (0,1,1) which BEATS class
  selectors (0,1,0) — panel typography knobs silently no-op ("changed the weight, nothing
  happened"). Write base type as `:where(.breakdance) :where(h1,h2,h3,h4), html :where(…)`
  (0,0,0; the `:`-prefix skip leaves it verbatim) so classes and element edits always win.

## §FAQ vars — tune, don't fight
Every `.bde-faq__item` gets a FULL border (`--faqBorderColor` #000, `--faqBorderWidth` 2px) and items
overlap by `margin-top:calc(var(--faqBorderWidth)*-1)` into one box; padding comes from
`--faqItemVerticalPadding`/`--faqItemHorizontalPadding` (16px). Set these vars on your wrapper class
(e.g. `.acme-faq{--faqBorderColor:var(--c-gray-200);--faqBorderWidth:1px;…}`). Adding a competing
per-item `border-bottom` or overriding padding directly breaks the overlap and strips the horizontal pad.
**Never touch `.bde-faq__answer` display/sizing** — collapse = `default.css` `display:none` + `faq.js`
toggling `.is-active`; brand only typography/color/spacing.
**General principle: Breakdance elements expose `--*` custom-props for their design controls
(`default.css` holds defaults) — restyle by overriding those vars on a wrapper class, not by
re-declaring internals.**

## §render keys — html.twig is the truth
An element's REAL render key can differ from the first key in its `defaultProperties`
(e.g. FAQ renders `content.settings.items`; `questions` is legacy builder-side — items-less trees
render EMPTY). Confirm against the element's `html.twig` + the live HTML. Element sources live under
FIVE roots (dir names use Underscored_Label_Words, not the slug):
- `plugins/oxygen/subplugins/breakdance-elements/elements/` (EssentialElements)
- `plugins/oxygen/subplugins/oxygen-elements/elements/` (OxygenElements)
- `plugins/oxygen/plugin/elements/` (core registration/APIs)
- `plugins/breakdance-elements-for-oxygen/elements/` (add-on, e.g. `Frequently_Asked_Questions/`)
- `plugins/breakdance-forms-for-oxygen/elements/` (forms)
Each element dir: `{element.php, html.twig, default.css, css.twig}`. Some OxygenElements ship an
EMPTY html.twig (render comes from PHP) — then `element.php`'s `contentControls()` gives the true
property paths (each control's section/id chain, e.g. Image = `content.image.media`, and its
`condition` entries spell paths out verbatim). `defaultProperties` shows the path SKELETON but omits
value-carrying fields (Image's default has no `media`/`url` — the actual image goes in
`content.image.media = {id,url,sizes:{full:{url}}}`, verified by render test 2026-07-11).

Two more indirections (both hit on Advancedslider, 2026-07-11):
- **Preset sections**: when `element.php` builds controls with `getPresetSection("EssentialElements\\
  AtomV1SwiperSettings", "Slider", "slider", …)`, the actual control tree lives in ANOTHER element
  (here `SliderOptionsPreset` under `oxygen/subplugins/breakdance-elements/elements/`, registered via
  `oxygen/plugin/elements/preset-sections/atoms/*.php`). The section's third arg ("slider") is the
  path segment: controls land under `design.slider.*`. `oxy_golden()` returns `defaultProperties:
  false` for such elements — goldens can't help; read the preset element's controls.
- **JS-consumed options**: runtime options may never touch html.twig — Advancedslider emits
  `dependencies()['inlineScripts']` = `window.BreakdanceSwiper().update({settings:{{ design.slider.
  settings|json_encode }}, …})`, so the stored object is passed VERBATIM to a JS consumer
  (`dependencies-files/breakdance-swiper/breakdance-swiper.js`). That JS file is the value-shape
  ground truth (it revealed `autoplay:"enabled"` is a STRING, speeds are `{number}`, and that it
  deep-merges defaults + honors `prefers-reduced-motion`). Check `dependencies()`/`actions()` in
  element.php when twig shows only wrappers/macros.

## §invert leaf recolor
`.prose--invert{color:#fff}` colours the CONTAINER; `.prose p{color:var(--c-gray-800)}` still wins on
the `<p>` (direct rule beats inherited colour) → #333 text on near-black. Recolour the leaf tags:
`.prose--invert p,.prose--invert li,.prose--invert strong{color:rgba(255,255,255,.85)!important}`.
Same class of bug for ANY inverted-on-dark text.

## §Gutenberg wipe (guard installed)
A post with `_oxygen_data` shows only `<!-- wp:breakdance/block-breakdance-launcher /-->` in the WP
block editor; saving there overwrites `post_content` with that ~49-byte launcher, destroying real
Gutenberg content (which `PostContent`-style mirrors depend on).
- **Permanent fix (installed): `mu-plugins/acme-oxygen-guard.php`** — `wp_insert_post_data`
  filter (priority 9999): for any post carrying `_oxygen_data`, if the incoming save is
  empty/launcher-only and current content isn't blank, keep the existing content (`wp_slash`).
  Post-type-agnostic (gates only on the meta), skips revisions, can't be deactivated from wp-admin.
  Real (non-blank) edits pass through.
- Recovery for pages wiped BEFORE the guard: `wp_get_post_revisions($id)` → restore newest non-blank
  revision via `wp_update_post(['ID'=>$id,'post_content'=>wp_slash($rev->post_content)])`.
- Rule stands regardless: content the user must edit belongs in the Oxygen TREE as native editable
  elements, not in `post_content` mirrored by `PostContent`.
- Note: intentionally-empty `post_content` on tree-native pages (e.g. home #10) is NOT a wipe.

## §misc (each burned once)
- `oxygen_global_settings_json_string` is double-encoded — pass `wp_json_encode($s)` INTO `set_global_option`.
- `typography.font_family` takes a font SLUG (`gfont-oswald`), not a family name.
- Rewriting a page tree overwrites manual builder edits on that page; when rebuilding the FOOTER
  template, re-inject its global `CssCode`/`JavaScriptCode` nodes or the site-wide stylesheet/JS drop.
- `wp media import <path> --porcelain` → attachment id; `wp_get_attachment_url($id)` for the URL.
- Structured custom fields come back already-unserialized from `get_post_meta(...,true)`; cast rows
  `(array)` before `['key']` access (may be stdClass).
- After importing products: `wp wc tool run regenerate_product_lookup_tables` + `wp rewrite flush`.
- Form JS (`awesome-form@1`) auto-enqueues via element dependencies once the node is in the tree AND
  `generateCacheForPost` ran; verify by fetching the page HTML (grep pipelines lie), search `breakdanceForm.init`.
- Templates aren't public: `oxygen_template` URLs 404. Preview a disabled template by copying its
  `get_tree()` onto a throwaway page, screenshot, delete.
- Unsplash/Pexels SEARCH endpoints are blocked/JS, but DIRECT photo URLs (`images.unsplash.com/photo-<id>?w=…`)
  and Picsum work for placeholder imagery; import via `wp media import`.

## Traps added 2026-07-14
- **`wp media import <URL>` fails on extensionless URLs** — "Sorry, you are not allowed to upload
  this file type" (wording is locale-dependent). Unsplash URLs have no `.jpg` in the path (extension
  is a query param), so WP's filetype check rejects them. Fix: `curl -s -o /tmp/x.jpg "<url>"` FIRST, then `wp media import /tmp/x.jpg
  --post_id=<id> --featured_image --porcelain`. (To find real product photo IDs when guessing 404s:
  `WebFetch` an `unsplash.com/s/photos/<query>` page and ask for the `images.unsplash.com/photo-*` src
  URLs — then contact-sheet with `montage` and VIEW before assigning; blind ID guesses return wrong subjects.)
- **HPOS orders aren't in `wp_posts`** — `wp post delete <orderId>` reports success but the order still
  exists (WooCommerce High-Performance Order Storage keeps it in `wp_wc_orders`). Delete via the WC API:
  `wp eval '$o=wc_get_order(599); $o && $o->delete(true);'`. Verify with `count(wc_get_orders(["limit"=>-1]))`.
- **`#place_order` (checkout submit) ID-specificity** — `breakdance-woocommerce.css` styles
  `.woocommerce #place_order` at ID specificity (1,1,0) → white text + `capitalize`, beating class-level
  `.button.alt` brand rules for color/transform (background may still win, so it looks half-branded).
  Fix: an ID-specificity brand rule with `!important` in footer #15:
  `.breakdance .woocommerce button#place_order{color:var(--c-ink)!important;text-transform:uppercase!important;...}`.
  Same family as the dead-WC-selector trap above. `site-builds/fix_place_order_button.php`.
- **AdvancedTabs hover colour is per-instance** — a `.bde-advanced-tabs` tab's hover title colour comes
  from the element's own settings compiled to `post-<id>.css` as `--hoverColor` on the instance class
  (`.bde-advanced-tabs-<postId>-<nodeId>`), which OUT-specifies the global `.bde-advanced-tabs …` brand
  rule. On the PDP (#522) dark tab bar, `--hoverColor` = a muted accent `#0f766e` which reads dim/grey next to
  the white non-active tabs ("hover too dark"). Fix by overriding the INSTANCE selector in footer #15
  with `!important`. `site-builds/fix_pdp_tab_hover.php`.
- **fullPage screenshots lie about late-painted content** — a `take_screenshot(fullPage:true)` on a tall
  page (checkout, PDP) can capture a region BEFORE its content paints (AJAX order-review, AOS scroll-reveal
  `.product-card`/`.reveal` blocks that are `opacity:0` until in-view), so a populated `.info-bar` band or a
  product-card row looks "empty". GROUND TRUTH = DOM: `getComputedStyle`, `getBoundingClientRect`, the a11y
  snapshot — not the fullPage PNG. Verify computed values before "fixing" a phantom bug. Headless MCP
  `hover` can also catch a colour mid-`transition`; re-measure the hovered element's computed colour.

## Native loop elements can't be hand-authored via injection (2026-07-14)
`OxygenElements\PostsLoop` (Post Loop Builder) and `EssentialElements\Postslist` are the native,
builder-editable way to render post lists (Project rule 3). BUT they are NOT injectable blind:
`oxy_golden('OxygenElements\\PostsLoop')` fatals, dumping its `defaultChildren` fatals, and
`oxy_write_tree` of a `Postslist` with `properties:null` fatals (WP "critical error"). There is no
usable shape in `docs/oxygen/` (builder-oriented). REQUIRED workflow: temp-admin login → add a Post
Loop in the real builder → configure query → Save → `\Breakdance\Data\get_tree($id)` → copy the exact
subtree → restyle with brand classes. Do not guess the shape. Blog list #602 + single #574 related use
PhpCode loops in the interim (working) until a golden sample is captured.

## generateCacheForPost fatals on WC single-product templates (2026-07-14)
`oxy_write_tree` ends with `\Breakdance\Render\generateCacheForPost($id)`, which RENDERS the tree. On
the **Single Product (PDP) template #522** the native `Product` element fatals ("critical error")
because there is no product in the WP-CLI context. The tree meta is written BEFORE the render, so a
fatal there leaves the DB correct but aborts the script. **Fix:** when editing WC single-product
templates, replicate `oxy_write_tree`'s body (wire parents → validate → `set_meta`) but wrap
`generateCacheForPost` in `try/catch(\Throwable)`. The real front-end (with product context)
regenerates the cache on next view. See `scripts/examples/place_component.php`.

## WooProductsList query field is `product_count`, NOT `product_count_to_show` (2026-07-14)
`EssentialElements\Wooproductslist`: the builder control is `content.content.product_count_to_show`,
but the renderer (`getProducts()` in `breakdance-woocommerce/util/products.php`) reads
`content.content['product_count']` for the limit. Set BOTH (limit + control label agree) or it shows
the default 9 / all products. Also `order_by` (date|price|…) + `order`; layout via
`design.layout.layout` (grid|slider). Columns override: CSS var `--bde-woo-products-list-products-per-row`.

## Placing a reusable component (Global Block) via OxygenElements\Component (2026-07-14)
Insert `oxy_el('OxygenElements\Component', ['content'=>['content'=>['block'=>['componentId'=>ID,'targets'=>[]]]]])`
at the END of a page/template's root children (the global footer #15 is applied separately, so
end-of-root = bottom of page). **Seed `oxy_nid(oxy_max_id_r($tree['root']))` first** or the new node's
id collides with existing ones ("duplicate node id 100"). A Global Block may itself contain a
`PostsLoop` that references ANOTHER Global Block (e.g. block #646 → loop → post-card #628); this
2-level nesting renders fine. Idempotency: skip if a Component with that `componentId` already exists.

## §image-srcset — scripted Image elements ship NO srcset (2026-07-16)
The Image element's `attributes()` template does not call WP at render time — it prints the
**pre-computed strings** `content.image.media.attributes.srcset` and `.sizes` verbatim. The builder
UI populates them when a user picks an image; a scripted tree that only sets `media = {id,url,sizes}`
renders a bare full-size `src` (a "looks fine, weighs 7 MB" page). Populate them yourself:
`wp_get_attachment_image_srcset($id, $size)` / `wp_get_attachment_image_sizes($id, $size)` — or use
`oxy_image(..., opts: ['sizes' => '…'])` in lib.php, which now does this. Related facts, verified:
- `lazy_load: false` OMITS the `loading` attribute entirely → eager. Use for the LCP hero image.
- `settings.advanced.attributes` land on the `<img>` tag itself (it's the element's root), so
  `width`/`height` (CLS) and `fetchpriority: high` work as custom attributes.
- `src` comes from `media.sizes[size].url` — if you set `size` to anything but `full`, you must also
  provide that key in the `media.sizes` map.
- Contrast: a PhpCode renderer emitting images gets srcset FREE via `wp_get_attachment_image()`
  (pass `sizes` in the attrs array) — often simpler than wiring Image elements when the images
  are CPT-driven data (e.g. a logo wall from a Brands post type).

## §template-cache — a template's compiled CSS needs its OWN cache regen (2026-07-16)
`generateCacheForPost()` on the PAGES does not refresh CSS compiled from a header/footer
TEMPLATE's tree. After `oxy_write_tree()` on a template — especially one carrying the
reference-CSS CssCode node (§bde-div cascade) — call `generateCacheForPost($templateId)`
too, or `post-<template>.css` on disk stays stale while the tree meta is already new.
Symptom that burned an hour: tree verified updated (walked the meta, new CSS present),
front end kept serving the old rules with an unchanged `?v=` hash. Regen loop for a
site is pages AND templates: `foreach([...pages, header, footer] as $id) generateCacheForPost($id)`.

## §absolute-urls — trees store scheme-frozen absolute URLs; TLS tunnels break (2026-07-16)
Everything scripted into trees is an ABSOLUTE http:// URL frozen at build time: image
src, the §image-srcset attribute strings, video src, link fields. Any TLS-terminating
preview proxy (Local's Live Links, ngrok, a CDN in front of staging) then serves an https
page whose subresources are http → the browser blocks ALL of them as mixed content
("site loads with no images/CSS through the tunnel").

Two traps in the fix, both verified the hard way:
1. An unconditional `<meta http-equiv="Content-Security-Policy"
   content="upgrade-insecure-requests">` is NOT a no-op on plain http — browsers apply
   it there too, upgrading every subresource to `https://<local-domain>`, whose
   self-signed cert is rejected (`ERR_CERT_AUTHORITY_INVALID`). It breaks the local site.
2. Server-side `is_ssl()` gating alone can't be trusted: Live Links rewrites the response
   BODY (host), not the request headers, so tunnel requests can look like plain local
   http to WP.

Robust shape (wp_head, priority 0): if `is_ssl()` emit the meta; otherwise emit a tiny
inline script that checks `location.protocol` IN THE BROWSER (always knows the real
scheme) and `document.head.prepend()`s the same meta before any stylesheet/image request
— inert on plain http. Optionally also honour `X-Forwarded-Proto` in wp-config so
canonicals/og:url are right when the proxy IS visible.

## §selector-store — saveSelectors persists a FLAT array, not the wrapper it accepts (2026-07-17)
`\Breakdance\BreakdanceOxygen\Selectors\saveSelectors()` ACCEPTS
`json_encode(['selectors'=>[...],'collections'=>[...]])` but PERSISTS option
`oxygen_oxy_selectors_json_string` as the bare selectors ARRAY (verified 6.1.0). Any reader
expecting `$decoded['selectors']` gets null → merge-by-name starts from "empty" (minting NEW
uuids that orphan every `meta.classes` reference), and class-promotion silently attaches
nothing while the DOM still LOOKS right (plain classes render identically). Use lib.php's
`oxy_read_selectors()` (normalizes both shapes) and verify promotion by walking the tree for
meta.classes uuids — never by grepping the rendered class attribute.

## §selector-cascade — classes carry PAINT; layout physically cannot live on selectors (2026-07-17)
Selectors compile UNPREFIXED into `uploads/oxygen/css/oxy-selectors.css` at specificity
0,1,0, loading after global-settings.css and BEFORE the header-template reference CSS and
page CSS. Consequences, all verified:
- The engine reset `.breakdance .bde-div{display:flex;…}` (0,2,0, in every -defaults.css)
  BEATS any selector's display/flex/grid/text-align/max-width on a Div. This is Breakdance's
  own model: layout belongs to elements (0,2,0 in post-<page>.css, loads last), shared paint
  belongs to classes. Don't fight it.
- The element panel's layout_v2 grid is uniform `repeat(N,1fr)` only — art-directed span
  grids can't move there either. Structural CSS stays in the reference stylesheet.
- The workable split (BEM migration, this build): typography/colors/backgrounds/
  padding/margin/borders/shadows/transitions → selectors (panel-editable; var()/clamp()/
  color-mix() pass through verbatim via {"number":0,"unit":"custom","style":"<css>"}); 
  display/grid/position/size/media-queries/pseudo/hover/keyframes → reference stylesheet.
- MIGRATED declarations must be REMOVED from the reference sheet (it loads later and wins
  ties, making panel edits silently no-op — the same class of bug as §bde-div cascade's
  footer-vs-header ordering).
- Descendant rules (`.cta p`, `.shot img`) hide styling from the panel AND outrank selectors
  (0,2,1) — dissolve them: BEM class on the child + rule rewritten to the class.
- Every element gets a class (BEM). An element with no class has no design-panel handle a
  user can find. lib.php: `oxy_selector()`, `oxy_save_selectors()` (uuid-preserving merge),
  `oxy_selector_uuid()`, `oxy_promote_classes_to_selectors()` (move plain names → meta.classes
  after registration; run selectors script BEFORE tree builds).

## §canvas-reveal — scroll-reveal systems make sections LOOK deleted in the builder (2026-07-17)
A JS-gated reveal pattern (`html.js .x{opacity:0}` + IntersectionObserver) hides whole
sections in the builder canvas: the gate script runs (it's in wp_head) but the observer
never fires inside the canvas iframe, so about/work/section-heads sit at opacity:0 while
excluded elements (cards, marquees, footer) render — it reads as "the builder ate my
sections". Canvas requests carry `?breakdance_iframe=1` (`isRequestFromBuilderIframe()`).
Fix in the site plugin: when that param is present, DON'T print the `html.js` gate and
DON'T enqueue the behaviour JS (drag rails/marquee cloning would fight builder selection
anyway). Front end untouched.

## §image-media-shape — the builder's Media control needs the FULL wp.media JSON (2026-07-17)
A minimal `media = {id,url,sizes:{full:{url}}}` renders perfectly on the front end, but the
builder's Image control shows an EMPTY "Choose" state and the canvas draws a placeholder —
it looks like the image was deleted. The control expects the attachment JSON the picker
itself writes. Producer: `wp_prepare_attachment_for_js($id)` (id/title/filename/url/alt/
sizes{thumbnail,medium,large,…} …). lib.php's `oxy_image()` now builds media this way and
overlays `media.attributes.srcset|sizes` (§image-srcset) on top. Symptom family reminder:
front-end-renders-fine ≠ builder-works — ALWAYS eyeball the builder after scripting trees.

## §acf-json-mutation — you cannot mutate a JSON-synced field group through the DB API (2026-07-17)
With ACF local JSON active (`acf/settings/load_json`), the group READS from the JSON file
by key — the file wins over the database. A chain of traps, each burned in sequence:
1. `acf_update_field(['parent' => $groupId, …])` creates the field post, but the edit
   screen and `get_field()` keep serving the JSON's field list — the new field is
   INVISIBLE. (Symptom: "the fields aren't in the editor" while the import script
   reported success.)
2. `acf_import_field_group()` on a JSON-synced key can MISS the existing group and create
   a DUPLICATE group post — now there are two, and reads still come from JSON.
3. After an import, SAME-REQUEST `acf_get_fields()` returns the stale local store — write
   JSON from it and you clobber the file (we shrank an 11-field group to 1 and broke the
   form; deleting "the duplicate" purged the real fields).
THE RELIABLE PATH: treat the JSON FILE as the write target. Keep ONE canonical group
definition in a script (keys frozen), and on change: purge the group's DB posts, delete
the JSON, `acf_import_field_group($canonical)` once (clean DB), then WRITE THE JSON
DIRECTLY from the canonical array (json_encode — never from a same-request read-back).
Field VALUES are never at risk — they live in post meta referenced by field key, and
survive any definition rebuild as long as keys stay identical (verified: place/galleries/
toggles all intact after a full purge-and-rebuild).

## §js-minify-asi — never token-join a script you didn't parse (2026-07-18)
When optimizing a static export, minifying JS by collapsing ALL whitespace (the way you can
for CSS) SILENTLY BREAKS scripts: JavaScript has Automatic Semicolon Insertion, so a newline
is sometimes a statement terminator (`return\n x` ≠ `return x`; a line ending an expression
followed by `(`/`[` on the next line fuses into a call/index). A regex minifier also can't
tell a real `//`/`/* */` comment from those tokens INSIDE a string or regex literal
(`"http://…"`, `/a\/b/`). Doing it right needs a real JS parser (terser/esbuild) — out of
scope for an in-plugin PHP routine. THE SAFE ROUTINE (verified): only strip WHOLE-LINE
comments, blank lines, and indentation, and NEVER join two lines — every original newline is
preserved, so ASI is untouched and no string/regex body is ever edited. Modest size win, but
the real compression is the gzip sibling (lossless, ~65% off even minified JS). CSS is the
opposite — no ASI, comments only `/* */`, string literals rare — so full whitespace-collapse
minify is safe there. Rule of thumb: aggressive-minify CSS, line-safe-minify JS, gzip both.
See RECIPES §Static Mirror asset optimization for the delivery (content-negotiated `.gz`).

## §oxygen-google-fonts — Oxygen prints its OWN `fonts.googleapis.com` <link> (no handle) (2026-07-18)
Oxygen/Breakdance emits a render-blocking Google Fonts stylesheet for the Global Settings font
DIRECTLY into `<head>` — printed inline, NOT via `wp_enqueue_style`, so there is no handle to
`wp_dequeue_style()` and no filter to unhook. Symptom: a FOUC on headings (swap flash) plus a
cross-origin font fetch you didn't ask for, and it survives every attempt to dequeue the font.
FIX (self-host + strip), all three:
1. Ship the font as SAME-ORIGIN woff2 + a local `@font-face` stylesheet (`font-display:swap`,
   `unicode-range` per subset so latin-ext/italic load on demand) and enqueue THAT.
2. `<link rel=preload href=…woff2 as=font type=font/woff2 crossorigin>` the primary file at
   `wp_head` priority 1, so headings paint with the real face on the first frame (no swap).
3. Remove Oxygen's own link with an OUTPUT BUFFER on `template_redirect` (front-end only):
   `ob_start(fn($h)=>preg_replace('#\s*<link\b[^>]*fonts\.googleapis\.com[^>]*>#i','',$h))`.
Bonus: the static export then ships with ZERO external Google calls. Cheap in production, where
static-first serves and this PHP rarely runs. VERIFY against LIVE WP (bypass any static/edge
cache with a `?cb=<n>` query — see §static-first-litespeed): `grep -c fonts.googleapis` must be
0 and the preload + local `fonts.css` present. See RECIPES §Self-hosting webfonts.

## §static-first-litespeed — serving 404s query-string URLs; %{REQUEST_URI} keeps the query (2026-07-18)
The Static Mirror serving block (`.htaccess`) serves the export for bare paths but ENDS in a
catch-all `RewriteRule ^ - [R=404,L]`. Symptom: on a LiteSpeed / nginx-hybrid managed host EVERY
query-string URL — `?type=x`, `?s=x`, any `?…` — returns a bare WEBSERVER 404 (`iso-8859-1`,
~355 bytes — NOT WP's styled utf-8 404; that content-type is your tell the request never reached
PHP), while the same path without a query serves fine.
**ROOT CAUSE + FIX (verified 2026-07-18):** the static-file test used `%{DOCUMENT_ROOT}/…latest%{REQUEST_URI} -f`.
On Apache `%{REQUEST_URI}` is the path only, but **on LiteSpeed it still contains the query string**,
so `/portfolio/?type=film` tested a file literally named `portfolio/?type=film` (never exists) and
fell through to the 404. Fix: test the RewriteRule's **path capture `$1`** instead — `mod_rewrite`
strips the query before matching the rule, so `$1` is query-free on both servers (`$N` in a
`RewriteCond` references the RewriteRule that follows it):
```
RewriteCond %{DOCUMENT_ROOT}/{$m}/$1 -f
RewriteRule ^(.*)$ {$m}/$1 [L]
RewriteCond %{DOCUMENT_ROOT}/{$m}/$1/index.html -f
RewriteRule ^(.*?)/?$ {$m}/$1/index.html [L]
```
The static page then serves for `?type=`/`?s=` and the site's own client-side filter/search
overlay handles the query. (See `scripts/examples/static-mirror.php` → `htaccess_rules()`.)
Second trap: calling `StaticMirror\run()` on the remote RE-WRITES the serving block + re-creates
`advanced-cache.php` + `WP_CACHE` as a side effect, so a routine (or auto-) re-export silently
re-applies whatever rules the plugin ships — deploy the fixed plugin FIRST, or a stale copy keeps
re-breaking. If you'd rather not serve static at all on a flaky host, disable it and serve live WP:
overwrite `.htaccess` with just the standard `# BEGIN WordPress` block, `rm advanced-cache.php`,
`wp config set WP_CACHE false --raw`, and set `static_mirror_auto` to `0` so nothing re-enables it.
Two adjacent managed-host traps burned the same day:
- **Edge/proxy cache** (`x-proxy-cache: HIT`, host-level, separate from WP + Static Mirror):
  caches bare anonymous GET HTML, IGNORES client `Cache-Control: no-cache`, REJECTS `PURGE`
  (403). Only the hosting panel's purge button or its TTL clears it; query strings bypass it.
  After a deploy that changes HTML, bare pages stay stale until purged — prove it with a
  `?cb=<n>` cache-buster (forces a MISS → the real live render).
- **Web opcache ≠ CLI opcache.** `wp eval 'opcache_reset()'` resets the CLI SAPI, NOT the web
  one; a freshly rsynced `.php` can keep running old bytecode. `validate_timestamps` +
  `revalidate_freq` (often 2s) means the web SAPI self-heals in seconds — but a loopback
  re-export INSIDE that window re-captures the stale render. Bump the file mtime and wait past
  `revalidate_freq` before re-exporting.

## §fixed-in-filtered-ancestor — position:fixed sizes to a transformed/blurred ancestor, not the viewport (2026-07-19)
A `position: fixed` element is fixed to the VIEWPORT only if no ancestor "captures" it. Any
ancestor with `transform`, `filter`, **`backdrop-filter`**, `perspective`, `will-change:
transform`, or `contain: paint/layout/strict` (all non-`none`) becomes the fixed element's
containing block — so its `inset`/`top`/`bottom`/`%` resolve against THAT ancestor's box, not the
screen. Symptom that burned us: a mobile nav drawer `.nav{position:fixed; inset:0 0 0 auto}` meant
to be full-height rendered only ~144px tall — the exact height of `.hdr`, which had
`backdrop-filter: blur(12px)` for the glass header. With `justify-content:center`, the menu items
overflowed the short box: the top links hid behind the header and the last link + socials spilled
out below the drawer's background (looked like "Contact isn't in the menu"). Computed `height`
matching a parent's height (not the viewport) is the tell — inspect it before touching the CSS.
**Fix:** give the fixed child an explicit viewport-relative size and anchor ONE edge, instead of
relying on `top:0`+`bottom:0` (which resolve to the trapping ancestor): `top:0; bottom:auto;
height:100dvh` (`100svh` fallback). `dvh`/`svh` are viewport units, so height is correct regardless
of the containing block; `top:0` is fine when the trapping ancestor sits at the viewport top (a
top-fixed header does). If the ancestor can also translate (scroll-hide header), un-hide it while
the overlay is open so the child isn't dragged off-screen. Alternative: portal the overlay out to
`<body>` — not always possible inside a builder's fixed tree. Don't "fix" it by removing the
backdrop-filter; that's the design.

## Class-less elements + images-as-spans (2026-07-19)
Two hard rules (SKILL.md §4, §5), each a real build defect found by audit:

- **Symptom:** an element can't be selected/edited cleanly in the builder, or its styling "comes from
  nowhere." **Cause:** it has NO authored class — only Oxygen's auto `bde-`/`oxy-` id-classes — and is
  styled contextually by a parent's descendant selector (`.prose p`, `.content-hero h1`) or by the auto
  id-class / node-level design. **Fix:** give the element its own BEM/brand class
  (`settings.advanced.classes` or a native selector) and move its CSS onto THAT class. Every authored
  element (Div/Section/Text/RichText/Image/Button/links/Icon/video) must carry one.
  - Audit: `scripts/examples/audit-classes.php` (read-only) — walks every `_oxygen_data` tree and lists
    class-less authored elements per post (native widgets, Component refs, composite children, code
    nodes are class-optional and excluded).
  - ⚠ You can't blindly auto-fix: a meaningful class needs a *semantic* BEM name and its rule migrated
    off the descendant/id selector (which changes specificity). Do it per section, then re-audit.
- **Symptom:** an "image" has no image controls (alt/sizes/lazy/swap) and isn't editable as an image.
  **Cause:** it was placed as a Text/span (or an `<img>` inside RichText). **Fix:** content image →
  `OxygenElements\Image`; background/decorative image → a `Div` with a background layer. NEVER a span.

## Empty selector `breakpoint_base` must be {} not [] (2026-07-19)
A native selector with NO design still needs `properties.breakpoint_base` as an **object** `{}`.
PHP `oxy_selector($name, [])` produced `"breakpoint_base":[]` (empty array) → the builder throws
**"IO-TS decoding failed"** for EVERY page (selectors are global), even though the front-end renders and
`validate-tree.php` passes (it checks the tree, NOT the selectors option). Fix in `oxy_selector`:
`'breakpoint_base' => ($groups ?: new \stdClass())`. To repair existing: read `oxy_read_selectors()`,
set any empty `breakpoint_base` to `new stdClass()`, `saveSelectors()`. Same PHP []-vs-{} trap as tree
root properties — any "empty map" written from PHP needs `new stdClass()`/`(object)[]`.

## Container vs Div for selector-editable LAYOUT — the key to design-in-selectors (2026-07-19, VERIFIED)
To make an element's LAYOUT fully controllable from a **Selector** (class) — so design lives in the
selector, is reusable, and shows in the design panel — the element MUST be **`OxygenElements\Container`**
(`.oxy-container`), NOT **`EssentialElements\Div`** (`.bde-div`).
- The engine ships `.breakdance .bde-div{display:flex;flex-direction:column;align-items:flex-start;
  text-align:left;max-width:100%;position:relative;background-size:cover}` at **(0,2,0)**. A plain
  Selector compiles to `.my-class{…}` at **(0,1,0)** → it LOSES those 7 layout props on a `.bde-div`
  (the selector's `display:grid` silently becomes the reset's flex/block). This is why selector-based
  layout on Divs fails.
- **`.oxy-container` has NO such reset** → a selector's `display:grid`/flex/etc. WINS. Verified live:
  `.grid-sample-container` (on an `OxygenElements\Container`) computes `display:grid` 2×2, padding/border/bg
  all from the selector.
- **Rule for full-editability builds:** use `OxygenElements\Container` for every layout wrapper, put ALL
  design in a named selector (`meta.classes`), never node-level. Content leaves (Text/Image/Button) take
  selectors too (no reset competes on their props). Reserve `EssentialElements\Div` only where you don't
  need selector-driven layout.
- Selector group shapes are builder-authored & confirmed: `layout.{display, grid.simple_grid_template_columns/rows,
  grid_align/flex_align.{primary_axis,cross_axis}, gap.{row,column}{}, flex_direction}`,
  `background.background_color` (8-digit hex ok), `spacing.spacing.padding.{t,r,b,l}{number,unit,style}`,
  `borders.borders.{side}{width{},color,style}` (+ `border_radius`), `typography.{color,text_align,text_transform}`,
  `size.{width,height,aspect_ratio,object_fit,overflow}`, `position.position`. All under `properties.breakpoint_base`.

### Three caveats when converting an existing (reference-stylesheet) page to design-in-selectors
Learned converting a live page's content blocks (cards, image-behind-text tiles, video, overlays) —
Div→Container + selectors + real Images. The architecture works, but three things WILL bite:

1. **Shared structural classes are a site-wide trap — never convert them per-page.** Classes like
   `.section`/`.container`/`.section-heading`/`.featured-grid` are used by DOZENS of pages via
   `settings.advanced.classes` strings + the reference stylesheet. If you (a) make a same-named
   *selector* and attach it on ONE page and (b) delete the stylesheet rule, every OTHER page's plain-string
   reference goes unstyled. Worse: `.container{max-width:1600px}` as a (0,1,0) selector LOSES to the
   `.bde-div` reset's `max-width:100%` (0,2,0) unless the element is ALSO flipped Div→Container. So a
   shared primitive can only move to selectors as a **site-wide migration**: create the selectors →
   `oxy_promote_classes_to_selectors()` on EVERY page that uses the name (promotes advanced string →
   `meta.classes` uuid) → flip those Divs→Container → THEN remove the stylesheet rules. Do it as one
   scoped operation, or leave the primitive as a reference-stylesheet class (legitimate Strategy B; the
   `.breakdance`-prefixed rule at (0,2,0) already ties/beats the reset). Check usage first:
   `SELECT post_id FROM wp_postmeta WHERE meta_key='_oxygen_data' AND meta_value LIKE '%classname%'`.
2. **[SUPERSEDED 2026-07-20 — see §Selector-conversion specificity ladder]** ~~Some CSS can't live in
   a selector.~~ It can: the `position` group carries `top/right/bottom/left` offsets + `z_index`
   (verified live), and everything else — `::before`/`::after` pseudo-elements, `:hover`, descendant
   rules, inline `@media` — goes in the selector's **`custom_css`**
   (`properties.breakpoint_base.custom_css.custom_css`, `:selector` placeholder). Nothing needs to
   stay in the reference stylesheet for a design-in-selectors build except genuinely SHARED rules
   (loop-context, cross-class groups) and RichText inner-tag typography.
3. **Converting a fake-image `<span>` to a real `Image` in place** (rule 5 cleanup): mutate the node so
   it keeps its id + tree position — set `data.type='OxygenElements\\Image'`, copy
   `oxy_image($attId)['data']['properties']` onto it, then `meta.classes=[selectorUuid]`. Remove the
   per-instance background rule the span relied on (`.oxy-text-<page>-<id>{background:…url(…)…}`), and if
   the old span-background baked in a dark gradient for text legibility, re-add it as a `.name::after`
   overlay (caveat 2). Decorative tile images then get `alt=""`+`aria-hidden` (rule 6). To source the
   attachment id from a URL: `get_page_by_path('slug',OBJECT,'attachment')` or a `guid LIKE '%/file.jpg'`
   query.

### Site-wide shared-primitive migration (reference-stylesheet class → Selector across all pages)
Proven playbook (migrated `section`/`content-hero`/`section-heading`/`container` across ~55 pages, verified).
Per class: **measure live → reconcile the selector to the real CSS → targeted-promote → flip Div→Container
if it sets a reset prop → strip the base rule → verify computed on several pages (desktop + a ≤767 width)**.
Back up the DB first; do ONE class at a time, safest first. Three non-obvious gotchas:

1. **A Selector compiles a GLOBAL `.name{}` rule (0,1,0) that also styles PhpCode/HtmlCode-emitted
   elements** — not just tree nodes carrying the selector via `meta.classes`. So when you strip the
   reference-stylesheet `.breakdance .name{…}` rule, the kept PhpCode renderers (nav, footer, product
   grids) that echo `class="name"` KEEP their styling from the selector's compiled rule. Verified:
   `.section`/`.container` selectors style the PhpCode PLP `<section class="section container">`. This is
   what makes the migration safe despite the sanctioned-PhpCode code nodes. (Tree-node Divs still need the
   Div→Container flip so the selector's `max-width`/layout beats the `.bde-div` reset; raw PhpCode elements
   aren't `.bde-div`, so no reset competes there.)
2. **Reference CSS breakpoints rarely match Oxygen's built-ins.** A `@media (max-width:768px)` override
   maps to Oxygen's **Phone Landscape** breakpoint = `max-width:767px` (see the responsive table in
   PROPERTIES.md; built-ins are 1119/1023/767/479). Converting a responsive rule shifts the breakpoint ~1px
   — visually identical, but note it. There is no native 768px breakpoint.
3. **`oxy_save_selectors()` doesn't always recompile `oxy-selectors.css`.** After saving, a selector can be
   in the store (`oxygen_oxy_selectors_json_string`) yet MISSING from the compiled
   `uploads/oxygen/css/oxy-selectors.css` — so its rule silently doesn't exist and every element using it
   loses that design (e.g. all containers went full-width). **Always verify the compiled rule after saving**
   (`grep '\.name{' uploads/oxygen/css/oxy-selectors.css`); if absent, force it:
   `\Breakdance\BreakdanceOxygen\Selectors\saveSelectors(json_encode([...]))` again +
   `\Breakdance\Render\generateCacheForGlobalSettings()`.
   **The mechanism, read from `selectors.php` (2026-08-06):** `saveSelectors` SKIPS regeneration
   entirely when the incoming list equals the stored one, and regeneration reads through
   `getOxySelectors()`, which is **statically memoised per process**. Two corollaries: (a) REMOVING a
   selector then regenerating in the same process rebuilds the CSS from the stale memo — the deleted
   rule survives; (b) a follow-up "resave the same list" is a no-op, not a fix. Deletion recipe:
   filter the list → `saveSelectors` → then call `generateCacheForGlobalSettings()` in a FRESH
   process. This is also the root cause of the two-saves-lose-the-second-batch trap.

**Cascade-tie entanglement — why partial migrations regress.** The reference stylesheet leans on
source-order tie-breaking between co-occurring same-specificity classes (`.post-body` overrides
`.container`'s max-width; `.post-hero` overrides `.content-hero`; both are `.breakdance .x` = 0,2,0, later
wins). Converting ONE of a co-occurring pair to a Selector drops it to (0,1,0), so the still-stylesheet
partner (0,2,0) now wins — which may be right (the override was meant to win) or a regression (the converted
class was meant to win). Migrate in cascade order (most-base classes like `container` first), or accept the
resulting state and verify it. Classes that only override on a DIFFERENT axis are safe (e.g. `.section`
sets padding-block, `.container` sets padding-inline → no conflict). Verify the whole site opens in the
builder afterward (io-ts) — a batch `oxy_validate_tree_json()` over every `_oxygen_data` post + a real
builder load of a sample per post type.

## Selector-conversion specificity ladder (verified 2026-07-20, component-block migration)

When moving a class from the reference stylesheet into a registered Selector, the selector's group
props compile as `.name{}` at **(0,1,0)** — three families of engine/theme rules beat that, and each
has a fix that keeps the design ON the selector (in its `custom_css`):

1. **Theme tag rules** `.breakdance h1/h2/h3/p{…}` (0,1,1) beat selector typography on Text headings
   (font-size/letter-spacing/line-height survive in the tag rule). Fix: duplicate those declarations in
   the selector's custom_css as `.breakdance :selector{…}` (0,2,0).
2. **`.breakdance .oxy-text{margin:0}`** (0,2,0, loads after oxy-selectors.css) kills any margin a
   selector sets on a Text element. Fix: `.breakdance .oxy-text:selector{margin:…}` (0,3,0).
3. **Engine/`.bde-*` defaults at (0,3,0)** (e.g. `.breakdance .bde-advanced-tabs .bde-tabs__tab`)
   beat `.breakdance :selector .bde-tabs__tab`. Fix: chain deeper —
   `.breakdance :selector .bde-advanced-tabs .bde-tabs__tab` (0,4,0).

Selector `custom_css` shape (verified compile): `properties.breakpoint_base.custom_css.custom_css` =
raw CSS string; `:selector` is replaced with `.name` and the block is emitted verbatim AFTER the
class rule — pseudo-elements (`::after` overlays), `:hover`, `.is-active`, descendant rules, and
inline `@media(...){…}` all work there. Responsive breakpoint-sibling group overrides and
`background.backgrounds[]` image layers are also verified live now — shapes in PROPERTIES.md
(§Property groups, §Responsive breakpoints); helpers in lib.php:
`oxy_selector($name, $groups, $customCss, $breakpoints)` and `oxy_bg_image()`.

Third leg of the recipe = the **Div→Container flip** (§Container vs Div above; helper
`oxy_flip_divs_to_containers()`) — additional proof: Container emits ZERO compiled engine CSS.
Flip + selector groups + custom_css converted a full set of component Global Blocks to
panel-editable with zero visual diff.

## AdvancedTabs golden defaultChildren are BROKEN on Oxygen installs (verified 2026-07-20)

`oxy_golden('EssentialElements\AdvancedTabs')` children (4× TabContent) contain a nested
`EssentialElements\Image` — a slug NOT REGISTERED in Oxygen mode (the registered ones are
`OxygenElements\Image` / `EssentialElements\Image2`) → renderer fatal + io-ts fail. This was the real
cause of the "AdvancedTabs fatals inside a Global Block" blocker (misdiagnosed as JS enqueue —
`tabs@1/advanced-tabs.js` actually enqueues FINE inside a Component placement, verified interactive).
Recipe: keep the golden's AdvancedTabs props (`content.content.tabs` = titles), build TabContent
children from the golden SHELL with `defaultChildren => []`, and put your own RichText inside each.
Same trap likely applies to other composites whose demo children use `EssentialElements\Image` —
inserting them via the real builder ALSO plants the invalid node (this broke a live product
template once).

---

## New traps (verified on the Boostribe build, 2026-07)

### §svg-height-auto — an inline SVG (or `<img>` SVG) renders 0×0
`.breakdance img{max-width:100%;height:auto}` (specificity 0,1,1) is in the reset. An SVG authored
`<svg width="100%" height="100%" viewBox="0 0 W H">` has **no intrinsic pixel size**, so `height:auto`
resolves to 0 → the element is 0×0 and invisible (looked "empty" even though the file 200s). A plain
class like `.logo{height:64px}` (0,1,0) **loses** to `.breakdance img`. Fix: beat it with a
2-class selector (`.wrap .logo{height:64px}`, 0,2,0) **or** set explicit `width` AND `height`, and
`max-width:none`. Verify with `el.getBoundingClientRect()` — never trust the rule is "in the file".

### §heading-color-reset — `<hN>` text stays dark on a dark section
A real heading tag (`h1`–`h6`) inside a purple/photo hero renders in the theme/engine heading color
(near-black) **even though the section sets `color:#fff`** — the heading doesn't inherit reliably.
Fix: set `color` **on the heading's own class** (`.hero__word{color:#fff}` or a token), not just on
the parent section. Same root cause as "body copy unreadable on dark sections", but bites headings
specifically because they carry their own default color.

### §global-in-helper — `global $x` is empty inside build *helper functions* too
Trap #3 (`global` empty under `wp eval-file`) isn't limited to top scope. A build helper
`function logo($f){ global $U; return … $U.$f …; }` invoked from an eval-file script gets an **empty
`$U`** → relative `src="logos/x.png"` → 404 (renders broken while the file itself is fine). Section
markup built in the *main* script scope worked; only the helper broke. Fix: **hardcode the base path
inside the helper** (or pass it as a parameter). Never rely on `global`/closures-over-outer-vars
under eval-file.

### §dropbox-zero-byte — a copied asset is 0 bytes and renders broken (but 200s)
Copying a file that is a Dropbox/iCloud **online-only placeholder** yields a **0-byte** file. It
serves HTTP **200** (empty body), so `curl -o /dev/null -w %{http_code}` looks fine, but every
`<img>`/logo/photo renders blank. Fix: `stat -f%z "$src"` the SOURCE *before* copying; if 0, have
the user "Make Available Offline" (Finder) or open each file once, then re-copy. **A 200 is not
proof the asset has bytes** — check size or `naturalWidth>0` in the browser.

### §button-inner-box — `oxy_button` shows a colored box inside your styled button
`oxy_button` renders `<div class="btn bde-button"><a class="bde-button__button" href>text</a></div>`
— the **inner `.bde-button__button` is the real `<a href>`** and carries a default (often blue-ish)
style that appears as a box inside your wrapper. Putting your `.btn` paint on the wrapper alone
leaves the inner box showing. Fix: keep the wrapper as the paint surface AND neutralize the inner:
`.btn.bde-button{padding:0}` + `.btn.bde-button .bde-button__button{background:transparent;color:#fff;
padding:.9em 2.1em;border-radius:4px;font:inherit}`. (The `<a href>` on the inner is what makes
Button the a11y-correct CTA — see §accessible-link.)

## §eval-file-silent-noop — `wp eval-file` can silently skip an entire script
**Symptom:** `wp eval-file build.php` exits 0 with ZERO output — no echo, no error, no tree write.
The same file runs perfectly via `include`. Observed triggers: single lines longer than ~2–4KB
(giant inline SVG path data), and one 40KB build file that failed even after shortening lines.
**Fix:** a tiny runner file that `include`s the real script — eval-file the runner:
```php
<?php // run_build.php
error_reporting(E_ALL); ini_set('display_errors','stderr');
register_shutdown_function(function(){ $e=error_get_last();
  if ($e && in_array($e['type'],[E_ERROR,E_PARSE,E_COMPILE_ERROR,E_CORE_ERROR]))
    fwrite(STDERR,"### FATAL: {$e['message']} @ {$e['file']}:{$e['line']}\n"); });
include __DIR__ . '/build.php';
```
Also keep long string literals wrapped (SVG path data treats newlines as whitespace, and PHP
single-quoted strings may contain literal newlines). If a build "succeeds" but the front-end
doesn't change, suspect this FIRST — check the script's echo actually appeared.

## §native-image-is-img — the Image element IS the `<img>`
`OxygenElements\Image` renders the `<img>` tag itself carrying your `settings.advanced.classes`
— there is NO wrapper div. So sizing/cropping (`aspect-ratio`, `object-fit:cover`) must go
directly ON your class (`.breakdance .my-cell{display:block;aspect-ratio:1;object-fit:cover;}`),
never on a `.my-cell img` descendant (matches nothing). The engine's
`.breakdance img{max-width:100%;height:auto}` (0,1,1) may also need a scoped override.

## §thumbnail-cascade — don't glob a folder WP writes thumbnails into
Importing images to the media library (`wp_insert_attachment` + `wp_generate_attachment_metadata`)
from an uploads subfolder you ALSO glob on every build is a runaway loop: WP writes resized
copies (`img-300x167.webp`) INTO that folder, the next build imports the thumbnails as new
"photos", which spawn more thumbnails (24 grid cells → 136 in three builds). Fixes (use both):
1) filter the glob: `!preg_match('/-\d+x\d+\.[a-z]+$/i', basename($p))`;
2) idempotency meta on each import (`update_post_meta($aid,'_my_source_file',$file)`) and reuse.

## §carousel-lazy-img — lazy images in a transformed track never load
`loading="lazy"` images inside a carousel track that is `transform: translateX(...)`-ed
off-screen NEVER intersect the viewport, so they never load — slides/avatars render blank
forever. Carousel/slider media must be eager: drop `loading="lazy"`, keep `decoding="async"`.
(Also true for anything revealed by transform/opacity rather than scroll.)

## §plain-classes-invisible-in-builder — advanced.classes style fine but give the builder NOTHING
**Symptom:** the page renders perfectly, the class audit passes ("every element has a class"),
yet the builder's Structure tree shows anonymous `Container / Text / Rich Text` rows and clicking
an element offers no class handle in the design panel. The client WILL call this out.
**Cause:** `settings.advanced.classes` are plain strings for the reference-stylesheet strategy —
they render into the DOM but are invisible to the builder UI. Only REGISTERED selectors attached
via `meta.classes` (uuids) appear in the Structure tree / design panel.
**Fix (retrofit that survives rebuilds)** — wrap your page-write in auto-register-and-promote;
empty-shell selectors are fine (design keeps living in the reference stylesheet; the selector
name still renders as the DOM class):
```php
function register_and_promote(array $tree): array {
  $names = [];
  $walk = function($n) use (&$walk,&$names){
    foreach (($n['data']['properties']['settings']['advanced']['classes'] ?? []) as $c) $names[$c]=1;
    foreach (($n['children'] ?? []) as $c2) $walk($c2);
  };
  foreach ($tree as $n) $walk($n);
  $have = [];
  foreach (oxy_read_selectors()['selectors'] as $s) $have[$s['name'] ?? ''] = 1;
  $new = [];
  foreach (array_keys($names) as $nm) if (empty($have[$nm])) $new[] = oxy_selector($nm, []);
  if ($new) oxy_save_selectors($new);              // merge-by-name keeps stored uuids stable
  return oxy_promote_classes_to_selectors($tree);  // plain strings -> meta.classes uuids
}
oxy_write_tree($id, register_and_promote(oxy_flip_divs_to_containers($tree, $n)));
```
Verify by decoding the stored tree (remember `_oxygen_data` = `{tree_json_string: "<json>"}` —
decode TWICE) and counting nodes with `meta.classes` vs leftover `advanced.classes`: after
promotion the leftovers should be 0 and every uuid must resolve in `oxy_read_selectors()`.

## §overflow-hidden-kills-view-timeline — scroll-driven animation frozen at one value
**Symptom:** a CSS scroll-driven animation (`animation-timeline: view()`) works perfectly on a
bare test page, but on the real site the element just sits at a fixed offset and never moves.
**Cause:** `view()` tracks the element's **nearest scroll container** — and `overflow:hidden`
(standard on decorated sections to contain watermarks) CREATES a scroll container. The section
never scrolls → timeline progress never changes.
**Fix:** on every section hosting a scroll-animated element use the fallback chain
`overflow:hidden;overflow:clip;` — `clip` clips identically but creates NO scroll container.

**Scope trap (learned at scale):** once reveals ALSO ride `view()` (entrances converted to CSS
scroll-driven), this bites on **every section that hosts a revealed element**, not just the few that
host parallax decoratives — a single unclipped `overflow:hidden` ancestor freezes the reveal at
"fully shown". Do a **site-wide `overflow:hidden` → `overflow:hidden;overflow:clip` sweep** across all
section CSS, not a per-section fix. Fail-safe stays intact: the hidden state is gated so off-mode /
reduced-motion / a JS failure always leave content visible
(old browsers keep `hidden` and typically use a JS fallback computing against the viewport,
which is immune). Beware page-local CSS re-declaring the section's `overflow:hidden` AFTER the
global fix — the page rule wins and re-freezes it.

---

# Findings from the Bold & Groovy build (2026-08-07)

A full port of an approved Penpot design system into Oxygen 6: tokens, cards, nav/footer,
a single template and an archive template. Everything below was hit live and verified.

## §template-settings-double-encode — a template that silently never applies
`_oxygen_template_settings` is **double-encoded**. `oxy_template_settings()` writes it through
`\Breakdance\Data\set_meta()`, which JSON-encodes AGAIN, so the stored value is
`"{\"type\":\"everywhere\",...}"` — a JSON *string* containing the JSON object.

Writing it yourself with `update_post_meta($id, '_oxygen_template_settings', wp_json_encode($s))`
stores `{"type":...}` — single-encoded, structurally plausible, and **the template never
applies**. No error, no warning; the template is simply ignored and the page renders without it.
Symptom: your header/footer/template CSS never loads on the front end.

**Always use `oxy_template_settings()`.** Verified against three separate installs, all of which
store the double-encoded form.

### The other way to break it: writing a CORRECT value unslashed (verified 2026-08-14)
`oxy_template_settings()` is not available to you when the value is already correct and you are
just **copying it between installs** — a design export/import, a migration, a staging sync. There
the trap is the opposite of the one above:

```php
update_post_meta($id, '_oxygen_data', wp_slash($p['tree']));              // slashed ✔
update_post_meta($id, '_oxygen_template_settings', $p['template_settings']); // NOT slashed ✘
```

`update_post_meta()` runs every value through `wp_unslash()` before storing. The settings value is
a JSON string whose CONTENT is escaped JSON, so unslashing strips the inner `\"` and what lands in
the row is:

```
"{"type":"everywhere","ruleGroups":[],"priority":10}"     ← no longer valid JSON
```

Same end state as single-encoding — `json_decode` → null, the template matches nothing, its
stylesheet is never enqueued — reached from the other direction. **`wp_slash()` is mandatory on
BOTH meta keys**, not just `_oxygen_data`. The tree usually gets it because its breakage is loud
(the builder refuses to open); template settings get missed because nothing complains at all.

**Why this is so hard to spot.** Every signal says the write worked: the importer reports OK, the
meta row is populated and the right length, the tree imports perfectly, the page returns 200 and
renders its content, and there is no PHP error anywhere — a string that has stopped being valid
JSON is not an error condition, it is a string. On a real site this ran green through five
consecutive deploys while the front end served **no design tokens at all**: no `--c-*` custom
properties defined anywhere, because the header template carrying them never applied.

**Detect it from the outside.** Do not assert on the write; assert on the rendered HTML.
`curl` the live page and grep for the stylesheet filenames it must link:

```bash
curl -s "$URL" | grep -q 'post-6.css'   || echo 'token stylesheet NOT loading'
```

That one grep is the only thing that caught it. Compare the raw meta across installs when it
fires — the difference is visible at a glance:

```
local  '"{\"type\":\"everywhere\",\"ruleGroups\":[],\"priority\":10}"'   ✔
live   '"{"type":"everywhere","ruleGroups":[],"priority":10}"'           ✘
```

## §oxygen-data-is-nested-json — reading a tree back
`_oxygen_data` is **not** the tree. It is `{"tree_json_string": "<json>"}` — the tree is a
STRING inside it, so reading a node back takes two decodes:
```php
$meta = json_decode(get_post_meta($id, '_oxygen_data', true), true);
$tree = json_decode($meta['tree_json_string'], true);
$css  = $tree['root']['children'][0]['data']['properties']['content']['content']['css_code'];
```
One decode yields the wrapper and `['root']` is missing. Guard the read and throw — a blind
`?? null` here silently rebuilds a header with an empty stylesheet.

## §button-wrapper-underline — text-decoration cannot be removed by a child
`oxy_button` renders `<div class="btn bde-button"><a class="bde-button__button">`; the
hand-written form is `<a class="btn bde-button"><span>`. Either way **the wrapper can be the
anchor**, so it carries the browser's default underline — and `text-decoration` is painted by
the ancestor and **cannot be removed by a descendant**. `text-decoration:none` on
`.bde-button__button` looks correct in the CSS and does nothing; every box button renders as a
filled box PLUS an underlined label.

Put it on the wrapper: `.btn.bde-button{text-decoration:none}`. A variant whose rule IS the
design (a tertiary text link) should draw it with `box-shadow: inset 0 -1px 0`, which is
unaffected.

## §focus-ring-per-ground — dark surfaces must redeclare --focus-ring
A single zero-specificity floor is the right shape:
```css
:where(a[href],button,input,select,textarea,summary,[tabindex]):focus-visible{
  outline:var(--rule-thin) solid var(--focus-ring,var(--focus-on-paper));outline-offset:2px;}
```
⚠ But **every dark surface must set `--focus-ring` itself**. Ground classes (`.ground-ink`) set
it; nav / footer / CTA / card bands carry their OWN class names, so without an explicit
declaration they fall through to the light-ground default. Measured: accent-brown on near-black
came out at **2.3:1**, under the 3:1 that WCAG 1.4.11 requires of a focus indicator — an
invisible focus ring that every automated check passes.

## §focus-visible-needs-real-keyboard — `.focus()` is not a test
`element.focus()` does **not** reliably match `:focus-visible` — the browser only applies it
when the last interaction was keyboard. An audit loop that focuses each element and reads
`outlineStyle` reports "no focus indicator" for elements that are perfectly styled.

Verify with real keyboard input (CDP `Input.dispatchKeyEvent` / a Tab keypress), then read
`document.activeElement` and its computed outline. That test also caught the opposite: elements
that DID match `:focus-visible` but were showing Chrome's default blue ring rather than a brand
one — which the synthetic probe had scored as a pass.

## §svg-naturalwidth-zero — not proof of a broken image
An SVG with only a `viewBox` and no intrinsic size reports `naturalWidth === 0` while rendering
perfectly. An audit that flags `naturalWidth === 0` as "broken image" will report a wall of
false positives on any SVG-heavy page. Verify with an actual HTTP status (`fetch(src)`) and a
non-zero rendered height instead. Related: §svg-height-auto.

## §acf-flexible-needs-field-keys — sub-values silently dropped
Writing ACF **Flexible Content** with `update_field()` must use field **KEYS**, not names:
```php
update_field('field_zs_modules', [[
  'acf_fc_layout'            => 'branding',
  'field_zs_branding_title'  => 'Branding',
  'field_zs_branding_images' => [12, 13, 14],
]], $postId);
```
With names, ACF cannot resolve sub-fields inside a layout: **the rows save, every sub-value is
dropped, and nothing errors.** You get the right number of layouts, all empty. Any index built
from those values (pools, relationship caches) then indexes nothing while reporting success.

Also: `update_field()` does **not** fire `acf/save_post`, so plugins that rebuild derived data
on save do not run. Call their reindex function explicitly after a scripted write.

## §namespaced-helper-plugins — function_exists on a bare name
Sibling plugins are often namespaced. `function_exists('ep_attachment')` is **false** when the
function is `\ElegantPlaceholders\ep_attachment`. If your guard falls back to a default (`0`,
`''`) rather than throwing, you write a whole content set full of zeros and every step reports
success. Guard on the **fully-qualified** name and **throw** rather than degrade.

## §oembed-needs-a-fallback
`wp_oembed_get()` returns empty when the provider is unreachable, rate-limited, or the URL is
unsupported — common on local installs. An unguarded video block then renders as an empty
coloured box. Always fall back to the poster image as a link out.

## §mega-panel-100vw — dropdowns and the scrollbar
A full-bleed mega panel written as `position:absolute; left:50%; width:100vw;
transform:translateX(-50%)` adds a **horizontal scrollbar on every page**: `100vw` includes the
scrollbar width. Anchor to the nav instead — `.nav{position:relative}`, `.nav__item{position:
static}`, `.nav__panel{position:absolute;left:0;right:0}` — which spans the bar with no
overflow and no magic numbers.

## §penpot-generatemarkup-duplicates — when porting artwork
(Penpot-side, but it bites any design port.) `penpot.generateMarkup()` emits each shape once
per render layer — fills, strokes, shadows. A naive path extraction produced **53 paths for 14
shapes** and a 26KB logo, four copies of every glyph stacked on top of each other. Dedupe by
path data: 26KB → 6KB.

Also: **Penpot ignores `lineHeight` at render.** The values stored in its typographies were
never seen by anyone. Porting them literally produced display type whose line box was shorter
than the cap height, so consecutive lines collided. Measure the real artwork (the y-delta
between stacked text shapes ÷ font size) rather than trusting stored metadata.

## §heredoc-delimiter-in-css — a CSS comment can terminate your heredoc
Build scripts hold big stylesheets in a nowdoc (`<<<'CSS' … CSS;`). PHP 7.3+ ends a heredoc at
the first line whose **first non-whitespace token is the delimiter**, followed by any
non-alphanumeric character — a space counts.

So a comment line inside the stylesheet like:
```
   CSS min-width did take but overflowed the viewport…
```
silently **closes the heredoc 117 lines early**. The error is reported at the heredoc's OPENING
line and reads `Invalid body indentation level (expecting an indentation level of at least 3)`,
which points nowhere near the real cause and looks like an indentation problem.

Find it with `grep -n "^[[:space:]]*CSS" file.php` — any hit other than the real closing marker
is the culprit. Reword the comment, or pick a delimiter that cannot start an English sentence
(`BYG_CSS`, `STYLESHEET_EOF`). `php -l` catches it; run it on every build script before wp-eval,
because via `wp eval-file` this surfaces only as "There has been a critical error on this website".

## §oxygen-own-classes-win — four verified cases (2026-08-07)
Oxygen's own element classes repeatedly beat hand-written CSS at EQUAL specificity, winning on
source order. Each of these presented as a design bug, not a cascade bug.

1. **`.oxy-container{position:relative}`** beats `.your-class{position:absolute}` (both 0,1,0).
   Symptom: an absolutely-positioned child renders in flow — a bottom-anchored caption appears
   at the TOP of its card. Fix: two-class selector `.card .card__caption` (0,2,0).
2. **The Global Settings button atom sets a colour on `.bde-button__button`**, which beats
   inheritance. Anything inside a `Button` styled with `color:inherit` or `currentColor` silently
   takes the atom's colour instead of its container's. Symptom: near-white links on a cream
   panel; hairline borders written as `currentColor` vanishing. **Rule: inside a Button element,
   colour must always be EXPLICIT.**
3. **`.breakdance-dropdown-custom-content` is `display:flex` with 30px padding.** A grid placed
   inside sizes to content, not to the panel, and colour fields stop short of the edges.
4. **`.breakdance-menu-link` etc. carry their own typography** — brand the RENDERED classes
   (`nav.breakdance-menu > ul.breakdance-menu-list > li.breakdance-menu-item > a.breakdance-menu-link`),
   not your own wrapper class.

## §dropdown-paint-the-body — never restyle a dropdown's layout
`MenuCustomDropdown` renders:
```
.breakdance-dropdown--custom      ← wrapper: trigger + floater. NOT a surface.
  .breakdance-dropdown-toggle     ← the trigger button
  .breakdance-dropdown-floater    ← Breakdance positions this. NEVER touch.
    .breakdance-dropdown-body     ← the panel surface. Paint THIS.
```
Painting the **wrapper** puts the panel background behind the trigger, so every menu item renders
as a coloured block. Overriding the **floater's** `position/left/top` takes show-hide away from
Breakdance and leaves every panel permanently open and stacked in the bar. Both observed live.

Panel width is a MenuBuilder property, not CSS:
`design.desktop_menu.dropdowns.wrapper.placement` = `left|center|right|full-width|section-width`.
`content.content.custom_width` is read by the floater's JS and did not take. A CSS `min-width`
does take but overflows the viewport on right-hand items, because the floater is anchored to its
trigger. Use the native property.

## §nth-of-type-inside-a-loop — never fires
A `PostsLoop` wraps every card alone inside its own `article.bde-loop-item`, so a card is always
`:nth-of-type(1)` and a cycle like `.card:nth-of-type(3n+2)` never matches. Count on the loop
item instead: `.bde-loop-item:nth-of-type(3n+2) .card{…}`.

## §image-element-has-no-loading-prop
`OxygenElements\Image` emits `loading="lazy"` from a BOOLEAN at **`content.image.lazy_load`**
(`{% if content.image.lazy_load %}`). There is no `loading` property — setting
`content.content.loading = 'eager'` writes a key nothing reads, and the image stays lazy. For an
above-the-fold logo set `lazy_load => false` plus a literal `fetchpriority` attribute.

## §stringdata-has-no-constructor — a registered field that silently returns nothing
`\Breakdance\DynamicData\StringData` has **no constructor**. `new StringData($value)` compiles,
runs, and discards the value: every dynamic field resolves to an empty string, with no error
anywhere and the shortcode correctly consumed (so it looks like a context bug, not an API bug).
Use the factory:
```php
return \Breakdance\DynamicData\StringData::fromString($string);
```
The value lives on a public `value` property; `StringField` declares exactly four abstract
methods — `handler`, `label`, `category`, `slug`. Reflect on the class before writing a handler.

⚠ Also: do not name a namespaced helper `current()` — it collides with the PHP built-in, the
same trap WordPress plugins hit with `register_taxonomy()`.

## §acf-repeater-is-editable-via-registered-fields
Oxygen dynamic data resolves **per post**; an ACF Flexible Content row is finer-grained, so a
global block rendered in a loop cannot know which row it is showing. To keep a repeater's design
builder-editable: hold the current row in a context, expose it as **registered dynamic fields**,
give each layout its own `oxygen_block`, and render with `\Breakdance\Render\render($blockId)`.
Only the `foreach` and a media leaf stay code — a gallery of N images cannot be a fixed set of
builder slots. Working implementation: `mu-plugins/byg-module-fields.php` on boldandgroovy.

# Findings from the Bold & Groovy build (2026-08-07, session 2)

Six more templates and a fidelity pass over the whole site. The theme running through most of
these: **a build script reported success while writing nothing, and every automated check
stayed green.**

## §php-null-coalesce-breaks-references — the one that hid for hours
A recursive tree walk that mutates nodes MUST NOT use `??` in the `foreach`:

```php
foreach ($node['children'] ?? [] as &$child) { $walk($child); }   // ⛔ mutations discarded
```

`??` evaluates to a **temporary copy** of the array, so `&$child` binds to the copy's elements
and every edit is thrown away. Correct:

```php
if (isset($node['children']) && is_array($node['children'])) {
    foreach ($node['children'] as &$child) { $walk($child); }
    unset($child);
}
```

**Why it is so dangerous:** the "wrote it" message usually comes from a `$done`/`$found` flag
captured by reference in the closure, which propagates perfectly — so the script is cheerfully
truthful about having done nothing. Three scripts hit this in one session; the site ran
unstyled for hours because the templates still rendered, still passed `validate-tree.php`,
still passed the panel lint, and still passed the whole a11y/SEO sweep. **Nothing automated
checks that a grid is a grid.**

Two rules out of it:
1. **Read the value back out of the database**, not out of the variable you just wrote. End
   every write script with a re-read that prints present/missing per selector.
2. **A green audit is not a rendered page.** Open a browser and read computed styles.

## §node-id-collision-on-append — `Tree invalid, NOT written`
`oxy_el()` starts its node-id counter from scratch, so appending to an **existing** page's tree
produces ids that collide with what is already there and `oxy_write_tree()` rejects the whole
tree (it refuses rather than corrupting — good, but the message points nowhere).

```php
oxy_nid(oxy_max_id_r($tree['root']));   // BEFORE building any new node
```

## §match-the-source-not-the-compiled-css
String-replacing inside a CssCode node must match the **source**, not
`uploads/oxygen/css/post-<id>.css`. The compiled file strips trailing semicolons and
indentation, so `min-height:108px}` never matches the `min-height:108px;}` actually stored.
The replacement silently no-ops. Read the node and `var_export()` the line before writing the
matcher.

## §unbalanced-css-comment-eats-the-rules
Editing prose into an existing `/* … */` block can leave an **orphaned `*/`** mid-comment.
Everything after it parses as garbage and **the rules that follow never reach the stylesheet** —
while the build script still reports success, because it only checks that the marked block was
written, not that its contents are valid CSS.

Symptom: the rule is present in the source node and absent from the compiled file. Diagnose by
counting delimiters in the block:

```php
$block.count('/*') === $block.count('*/')
```

Worth doing whenever a rule mysteriously does not apply. (Distinct from
§heredoc-delimiter-in-css, which truncates the PHP string itself.)

## §mu-plugin-load-order-and-lazy-classes
Two separate traps when factoring shared code out of `byg-*` mu-plugins:

1. **mu-plugins load in filename order.** A registrar that callers invoke *at include time*
   must sort first — `byg-dynamic-fields.php` sorts AFTER `byg-client-fields.php` and every
   request died with `Call to undefined function`. Name it `byg-0-dynamic-fields.php`.
2. **A class extending a PLUGIN class cannot be declared at mu-plugin file scope.** Oxygen is a
   regular plugin, so `\Breakdance\DynamicData\StringField` does not exist yet. A top-level
   `class X extends StringField` is a fatal, and guarding it with `class_exists` is worse — it
   is silently skipped and every field goes missing with no error. Declare it **inside** the
   `wp_loaded` callback (an anonymous class is fine).

## §not-contributes-specificity — Breakdance rules are (0,3,0)
`:not()` contributes **its argument's** specificity. Breakdance ships:

```css
.breakdance-menu--anim-fade:not(.breakdance-menu--dropdown-slide) .breakdance-dropdown-floater{…}
```

which is **(0,3,0)**, not (0,2,0). A two-class override loses to it no matter that
`post-865.css` loads later. Measured: a hover grace period written as
`.nav__item .breakdance-dropdown-floater{transition:…}` never applied — the computed transition
stayed at Breakdance's. **Four classes win.**

What made it deceptive: *other* rules in the same block (width, position) **did** apply, because
their competitors were weaker. The file looked like it was working. Verify with
`getComputedStyle()` on the exact property, not by eye.

Related and worth knowing: the header's CssCode nodes have an order (`byg-tokens`,
`byg-components`, `byg-chrome`). At equal specificity **the last one wins** — an override
written into an earlier node loses to the original in a later one.

## §min-height-plus-aspect-ratio-is-a-min-width
```css
.work-card{aspect-ratio:825/537; min-height:280px;}
```
Together these impose a **minimum WIDTH** of 280 × 1.536 = **430px**. Below a 430px column the
element refuses to shrink and pushes the document sideways — a horizontal scrollbar at 390px on
every page carrying one.

⚠ `min-width:0` — the usual grid-item remedy — does **nothing** here. The constraint is
arithmetic, not min-content. Release the ratio at the breakpoint:
`@media (max-width:639px){.work-card{aspect-ratio:auto;min-height:0}}`.

(Separately, grid items *do* default to `min-width:auto`; that is a real and different cause of
the same symptom.)

## §container-padding-inside-the-cap
```css
.container{max-width:1200px; padding-inline:120px}   /* content = 960, not 1200 */
```
The padding sits **inside** the max-width, so a design whose artboard is 1440 with content from
x120 to x1320 comes out **240px narrow site-wide** — every grid, every column. Symptom: a
4-column footer measuring 222px tracks where the board says 282.

```css
.container{max-width:calc(var(--w-full) + 2 * var(--container-pad));padding-inline:var(--container-pad)}
```

Catch it by measuring the CONTENT box (`width − paddingLeft − paddingRight`) against the board,
not the element width.

## §button-base-class-carries-the-layout
`oxy_button` renders `<div class="btn bde-button"><a class="bde-button__button">`. On a site
where `.btn.bde-button` carries the LAYOUT (`display:flex`, hug-content) and `.btn--primary`
only carries the paint, writing `['btn--primary']` **without the base class** leaves the wrapper
at `display:block`. It stretches to the container and gets painted, while the actual anchor
stays its natural width.

**Only the anchor is clickable** — measured 17–29% of the visible button on three homepage CTAs.
Always pass the base class: `['btn', 'btn--primary']`. Audit with
`anchorWidth / wrapperWidth` per `.bde-button`.

## §full-bleed-rule-use-markup-not-100vw
A divider that must run edge-to-edge inside a centred `.container` is best done as a **full-width
sibling div**, not a pseudo-element at `left:50%;transform:translateX(-50%);width:100vw`.
`100vw` includes the scrollbar (measured 1728 against a 1713 viewport) and overhangs the right
edge — and `overflow-x:clip` on the ancestor did **not** contain it. A plain div needs no
viewport units and cannot overflow. See also §mega-panel-100vw.

## §mega-menu-floater-anchoring — refines §dropdown-paint-the-body
That entry says never to touch the floater's `position/left/top`. Refined by measurement:

**Show/hide is `opacity`+`visibility`, not position** — so changing where the floater is
*anchored* does NOT break Breakdance's open/close. What breaks it is removing the transition
or the visibility handling.

The defect worth fixing: the floater is `position:absolute` inside its own
`.breakdance-dropdown` (relative), so it is **left-anchored to a 66–87px trigger** while being
~1160px wide. Every panel overflowed the viewport — measured 288 / 399 / 500 / **606** px —
putting a whole column off-screen and adding a horizontal scrollbar whenever a menu opened.

```css
@media (min-width:1024px){
  .nav{position:relative;}
  .nav__item .breakdance-dropdown{position:static;}          /* release the per-trigger anchor */
  .nav__item .breakdance-dropdown-floater{
    top:100%;left:auto;right:0;                              /* grow leftward into space that exists */
    width:min(760px, calc(100vw - 2 * var(--container-pad)));
  }
}
```

**Hover robustness follows from the geometry.** Once the panel horizontally covers every
trigger and the vertical gap is 0, there is no direction you can leave a trigger downward and
miss it. Add on top:

```css
/* grace period on the HIDE direction only; four classes — see §not-contributes-specificity */
.nav .breakdance-menu .nav__item .breakdance-dropdown-floater{
  transition:opacity 160ms ease 180ms, visibility 0s linear 340ms;}
.nav .breakdance-menu .nav__item:hover .breakdance-dropdown-floater{
  transition:opacity 160ms ease 0s, visibility 0s linear 0s; pointer-events:auto;}
.nav__item:hover::after{content:"";position:absolute;left:0;right:0;top:100%;height:16px;}
```

`pointer-events:auto` while fading is what lets re-entry during the grace window cancel the
close; without it the fading panel is inert and the pointer falls through.

⚠ **Synthetic mouse events do not trigger `:hover`.** Test with real pointer input (CDP), and
note that a teleporting hover never exercises the diagonal path that actually breaks menus —
reason about the geometry as well.

## §acf-read-top-level-fields-by-key
`get_field('blurb', "term_{$id}")` returned the **client** post-type group's wysiwyg — the value
came back `<p>`-wrapped, from a different field entirely. ACF resolves an unqualified **name**
against every registered group and the first match wins, regardless of which object the
`$post_id` refers to; it then applies *that* field's type formatting.

Read top-level fields by **KEY** (`get_field('field_zs_service_tagline', …)`). Shared names are
only safe for module SUB-fields, which are read through their parent. Give term fields distinct
names too, so a template reaching past your helpers does not hit the same collision.

Also verified: **ACF term fields are plain `termmeta`**, so Oxygen's `term_custom_field` reaches
every one of them (including flattened repeater rows) with no glue code.

## §penpot-dump-every-shape-type
A Penpot audit that filters to `text` and `rectangle` **silently drops groups, boards, paths and
images**. A whole decorative element (a rotated three-rectangle group) was missed on a first
comparison pass because of exactly this, and only surfaced when a human pointed at it.

When diffing a board, dump every type and filter afterwards. Also check the PAGE board, not
just the component spec sheet — page-level decoration is layered over components and does not
appear in the component's own board.

## §post-type-any-skips-oxygen-cpts — "rebuild everything with a tree" quietly rebuilds nothing (2026-08-14)
`get_posts(['post_type' => 'any', …])` does **not** mean every post type. WP_Query resolves `any`
to every type NOT registered with `exclude_from_search` — and that flag is exactly what Oxygen
sets on its own types. So this loop, which reads like it covers the whole site:

```php
foreach (get_posts(['post_type'=>'any','post_status'=>'any','numberposts'=>-1,'fields'=>'ids']) as $i) {
    if (get_post_meta($i, '_oxygen_data', true)) \Breakdance\Render\generateCacheForPost($i);
}
```

silently skips **every `oxygen_header`, `oxygen_footer`, `oxygen_template` and `oxygen_block`** —
i.e. the entire template layer, including whichever template carries your global stylesheet. It
rebuilds pages and posts, prints a plausible count, and exits 0.

Symptom: `post-<id>.css` for a header/footer/template **404s**, so the front end loads page CSS but
no global CSS. Pairs viciously with §template-settings-double-encode — both end in "the template's
stylesheet is missing", from unrelated causes, and fixing one leaves the other.

List the types explicitly. The same list `export-design.php` uses:

```php
$types = ['oxygen_header','oxygen_footer','oxygen_template','oxygen_block','page','post'];
foreach ($types as $t) {
    foreach (get_posts(['post_type'=>$t,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids']) as $i) {
        if (get_post_meta($i, '_oxygen_data', true)) \Breakdance\Render\generateCacheForPost($i);
    }
}
```

⚠ `'any'` is still CORRECT when you want public, viewable content — collecting URLs to fetch, for
instance, where `is_post_type_viewable()` filters right after (`scripts/verify-site.php` does this
deliberately). The rule is about intent: `any` means "publicly searchable", never "all".

**Related:** `wp oxygen clear_cache` only rebuilds GLOBAL stylesheets. Per-post files are generated
on demand, so a design push that clears the cache without regenerating per-post CSS leaves pages
pointing at stale files — or none. A deploy's design step needs both.

## §wrong-local-socket — wp-cli silently talks to ANOTHER site's database (2026-08-16)
`scripts/wp-eval.sh` used to autodiscover Local's MySQL socket with:

```bash
SOCK="$(ls "$HOME/Library/Application Support/Local/run/"*/mysql/mysqld.sock | head -1)"
```

With more than one Local site running that picks **the first socket alphabetically**, which
is very unlikely to be yours. wp-cli then runs with the right `WP_ROOT` — your plugins, your
`wp-config.php` — against **someone else's database**. Nothing warns you, because nothing is
wrong from wp-cli's point of view.

Caught when `validate-tree.php 76` reported:

```
Post 76: "Caña" (attachment, inherit)
FAIL: no _oxygen_data meta on post 76
```

The page it was pointed at is a published page with a 216-node tree. The plausible reading is
"my tree is corrupt"; the truth was "you are looking at a different site". Read-only that day —
a **write** script would have edited the wrong client's content, and the only trace would be
someone else's site changing for no reason.

Now fixed: the socket is derived from `/Local/run/<id>` as recorded in the site's own `.envrc`
or `wp-config.php`, a lone socket is used only when it is unambiguous, and **two or more
running sites with no positive match is a hard error** listing the candidates.

Two general points, both of which cost time here:

- **A wrong-database symptom looks exactly like a data-corruption symptom.** When a query
  returns something impossible for the object you asked about, verify WHICH database you are
  connected to before you start diagnosing the data. `wp option get siteurl` or
  `get_bloginfo('name')` settles it in one call.
- Under `set -euo pipefail`, a `grep` that legitimately finds nothing **kills the script**,
  and inside detection logic that means it dies before printing the error it was written to
  print — exit 1, no output. Guard every probe pipeline with `|| true`. (Made this exact
  mistake in the first cut of the fix; the refusal path was unreachable.)
