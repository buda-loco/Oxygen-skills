# Recipes — end-to-end builds verified on a real production site

All of these ran for real on the production storefront this skill was distilled from. Citations like
`site-builds/xxx.php` refer to that project's private script archive — six representative scripts
ship in `scripts/examples/`; the other citations are provenance notes (each recipe's text is
self-contained). Use `scripts/lib.php` for new work. Post IDs (footer #15, templates #521/#522, …)
are illustrative — yours will differ.

## New page (native, builder-editable) — the canonical flow

```php
<?php // build-my-page.php — run: scripts/wp-eval.sh build-my-page.php
require '/path/to/oxygen-page-builder/scripts/lib.php';

$id = wp_insert_post(['post_title'=>'Financing','post_name'=>'financing',
                      'post_type'=>'page','post_status'=>'publish']);
oxy_write_tree($id, [
  oxy_div([
    oxy_text('FINANCING', 'h1', ['content-hero__title']),
    oxy_rich('<p>Intro paragraph…</p>', ['prose']),
    oxy_faq([
      ['q'=>'Which cards do you accept?', 'a'=>'<p>…</p>'],
      ['q'=>'Do you offer installments?','a'=>'<p>…</p>'],
    ], ['acme-faq']),
    oxy_button('CONTACT US', '/contact/', ['btn-cta']),
  ], ['content-hero','container']),
]);
echo "page $id\n";
```
Then ALWAYS: `scripts/wp-eval.sh scripts/validate-tree.php <id> fetch` and open
`http://example.local?oxygen=builder&id=<id>`. Brand-style every new class (PROJECT RULE) —
add CSS to the global stylesheet (footer #15 CssCode → `post-15.css`) or a page-local `oxy_css()` node.

> **RULE §4 — a class on EVERY element, from the start.** Every `oxy_*` factory takes a `$classes`
> arg — always pass one (BEM/brand name), for containers too (`oxy_div([...], ['card__body'])`), never
> just the leaf. Style that class directly; don't lean on descendant selectors (`.card p`) that leave
> children class-less. Images use `oxy_image()` (never a Text/span); backgrounds use a `Div` (§5).
> Retrofit/verify an existing site: `scripts/examples/audit-classes.php` (read-only) →
> `scripts/examples/fix-classes.php <id> [apply]` (adds a derived class to each class-less element).
> `oxy_ensure_class(&$node,'name')` / `oxy_needs_class($type)` in lib.php are the building blocks.

**Parsing existing Gutenberg content into editable components** (how the 16 content pages were built —
`site-builds/build_internal_pages.php` `content_nodes()`): run `apply_filters('the_content', $post->post_content)`,
`DOMDocument`-parse the top-level HTML, emit one `oxy_text(…, 'hN')` per heading + one `oxy_rich()` per
paragraph/list. NOT one giant RichText, NOT `PostContent` (see SKILL.md rules).

## Editing an EXISTING page/template safely (node surgery — how hero v2 was applied)
Never rebuild a live tree from scratch to change one thing. Read → mutate in place → write back:
```php
$tree = \Breakdance\Data\get_tree($id);          // assoc arrays; round-trips cleanly
$mutate = function (&$n) use (&$mutate) {
    if (($n['data']['type'] ?? '') === 'EssentialElements\\Advancedslider') {
        $n['data']['properties']['design']['slider']['settings'] = [ /* only what changes */ ];
    }
    foreach ($n['children'] as &$c) { $mutate($c); } unset($c);
};
$root = $tree['root']; $mutate($root);
oxy_write_tree($id, $root['children']);          // re-validates everything, keeps all other nodes
```
- All 77 home-page nodes survived byte-identical except the one edited (verified 2026-07-11).
  `oxy_write_tree` normalizes root properties to `{}` and recomputes `_nextNodeId` — both harmless.
- Back up first: save `get_post_meta($id,'_oxygen_data',true)` to a file.
- Growing the global stylesheet: append a MARKED block to footer #15's CssCode string (guard on the
  marker for idempotence) instead of string-surgery on the existing 80KB — later equal-specificity
  rules win, old rules stay inert.

## Verifying in the builder without the user (temp admin)
The builder needs a logged-in session; io-ts runs client-side, so this is the only real proof:
```bash
scripts/wp-eval.sh -- user create claude-qa-temp claude-qa-temp@example.com \
  --role=administrator --user_pass="$(openssl rand -base64 18)"
# browser: log in at /wp-login.php → open ?oxygen=builder&id=<ID> → expect NO "IO-TS decoding failed"
scripts/wp-eval.sh -- user delete claude-qa-temp --yes   # always clean up
```
Slider behavior check on the front-end console: `Object.values(window.swiperInstances)[0].params`
(instances are keyed by slider node id). Note autoplay is intentionally OFF inside the builder.

## Golden-sample workflow (any unknown shape)
1. `oxy_golden('EssentialElements\AdvancedTabs')` → the exact `defaultProperties` + `defaultChildren`
   the builder inserts (deep-copied). Override content, inject.
2. Still unsure? Build it once in the real builder, Save, read `_oxygen_data` back and copy the JSON.
3. Confirm the render key against the element's `html.twig` (GOTCHAS.md §render keys).

## Embedding a full reference stylesheet (faithful 1:1 recreation, "strategy B")
Build native DOM with `Div`/`Text` (+ `settings.advanced.classes` plain names) mirroring the reference
markup; drop the reference CSS into ONE `CssCode` node in the **HEADER template** and JS into ONE
`JavaScriptCode` node in a global template, so they apply site-wide. The header (not the footer!) is
the one slot whose compiled CSS loads after the engine reset but BEFORE the page CSS — so the
reference rules win the engine while builder edits still win the reference rules
(GOTCHAS.md §bde-div cascade, corrected 2026-07-16).
- **Use `Div`, not `Section`** — Section injects `.section-container` (own max-width/padding/align)
  that fights reference CSS. Page root can hold Divs directly (inside `<main>`).
- Prefixing pipeline (MANDATORY, in order): strip CSS comments → prefix every selector with
  `.breakdance ` except `:root`/`*`/`html`/`body` → prepend the `.bde-div` reset. Details + why:
  GOTCHAS.md §comment-strip, §bde-div cascade. (`site-builds/fix_css_specificity.php` did this.)
- Your reference design can be any existing theme/stylesheet (in the original build: a Timber/Twig
  theme — tokens in `assets/css/`, per-template structure in `views/*.twig`).

## WooCommerce templates (PDP / PLP)
The `oxygen-zero` theme delegates everything to Oxygen templates — WC pages render BLANK until
templates exist. Create `oxygen_template` posts (PROPERTIES.md §templates): PLP = type
`all-product-archives`, PDP = type `product`, priority 30.
- **Native editable PDP (what #522 uses)**: tree = `Section > EssentialElements\Product` (both
  `properties: null`) — WC's ENTIRE single product (gallery+thumbs+zoom, title, price, working
  variation add-to-cart, tabs, related) as one selectable block, brand-styled by the global
  `woocommerce.css`. Trade-off: WC's standard layout; custom meta tabs need a
  `woocommerce_product_tabs` hook. `Wooproductslist` = the editable archive/loop element.
- **Custom-design PhpCode variant** (full fidelity, not builder-editable): one `PhpCode` echoing
  reference markup from live data — PLP: `wc_get_products()` → `.product-card`s; PDP: set
  `global $product` + `woocommerce_template_single_add_to_cart()` (functional variation form with
  nonces/AJAX). `site-builds/build_pdp.php` / `build_wc_templates.php`.
- Style native WC ONLY via `.bde-*` wrappers (GOTCHAS.md §dead WC selector). Include the brand
  `woocommerce.css` in the global stylesheet for cart/checkout/account.
- New store: `update_option('woocommerce_coming_soon','no')`; after imports run
  `wp wc tool run regenerate_product_lookup_tables` + `wp rewrite flush`.
- Site migration: `wp export` (WXR) → `wp import file.xml --authors=create` (needs
  `wordpress-importer`, WC active on both, uploads/ copied). Carries variable products + variations +
  `pa_*` attributes + menus. THEN `wp search-replace 'old.local' 'new.local' --all-tables`.

## WC system pages: Cart / Checkout / My-account (per-page native tree)
The `oxygen-zero` theme renders these BLANK until they have an Oxygen tree (same as any WC page). The
simplest, safe, builder-editable fix is to give each WC page its OWN `_oxygen_data` tree with the
matching native WC page element (NOT a template — no priority games, no hijack risk):
```php
oxy_write_tree(18 /*cart*/,     [ oxy_div([oxy_text('Your cart','h1')],['content-hero','container']),
                                  oxy_div([oxy_el('EssentialElements\\Woopageshoppingcart', null)],['container','section','wc-page']) ]);
oxy_write_tree(19 /*checkout*/,  [ …hero…, oxy_div([oxy_el('EssentialElements\\Woopagecheckout', null)],['container','section','wc-page']) ]);
oxy_write_tree(20 /*account*/,   [ …hero…, oxy_div([oxy_el('EssentialElements\\Woopageaccount', null)],['container','section','wc-page']) ]);
```
Page IDs from `get_option('woocommerce_{cart,checkout,myaccount}_page_id')`. Each native element renders
the full WC page (cart table+coupon+totals; checkout billing+order+payment; account login/register/
dashboard/orders/addresses). `Woopageaccount` shows login+register when logged out, the dashboard when
logged in. `properties: null` (their `oxy_golden` fatals — see GOTCHAS). Brand-style via the WC page
elements' nested `.woocommerce` div (the dead-selector trap does NOT apply here — GOTCHAS §nuance).
Empty-cart / checkout-empty auto-redirect is WC behaviour (checkout with empty cart → cart).
- **Make the store functional** (else checkout says "no payment methods", defaults to US): set
  `woocommerce_default_country='AR:C'`, `woocommerce_currency='ARS'`, enable an offline gateway
  (`woocommerce_bacs_settings['enabled']='yes'` = Transferencia), and
  `woocommerce_enable_myaccount_registration='yes'` for the register form.

## My-account per-tab heading (WC renders none)
WC doesn't print a heading telling the user which account tab they're on. Add it with an mu-plugin
(`mu-plugins/acme-account-heading.php`) hooking `woocommerce_account_content` (priority 1) and
detecting the endpoint from `$wp->query_vars` against `wc_get_account_menu_items()`; echo
`<h2 class="wc-account-heading">`. Style that class in the global stylesheet.

## Search results template (dynamic PhpCode — the legit case)
Search is a query (`?s=…`), not a page → needs an `oxygen_template` type `search` (priority 30). Native
`content-hero` Text `h1` + one `PhpCode` results body (genuinely dynamic across post types, so PhpCode is
correct here). Reuse the PLP `.product-card`/`.plp-grid` classes for product hits, a simple `.search-page`
list for page/post hits, an `.aux-box` no-results state with a shop CTA, and a `.search-refine` form:
```php
$q=trim(get_search_query());
$products=$q?wc_get_products(['s'=>$q,'limit'=>24,'status'=>'publish']):[];
$pages=$q?(new WP_Query(['s'=>$q,'post_type'=>['page','post'],'posts_per_page'=>12]))->posts:[];
```
(from the original build; its search template was #570.)

## 404 + single blog post templates (coverage)
- **404**: `oxygen_template` type `404` (`is_404()`), native `content-hero` with Text `h1` "404" + subtitle
  + `RichText` + two `Button`s (home, shop). All static natives — no dynamic needed.
- **Single blog post**: `oxygen_template` type = the **post-type slug** (`post` → `is_singular('post')`,
  priority 30). This targets ONLY blog posts, NEVER pages (pages are `is_singular('page')`), so it can't
  hijack the content pages' own trees. Tree = `content-hero > EssentialElements\PostTitle` (dynamic) +
  `prose > EssentialElements\PostContent` (dynamic).
  **PostTitle/PostContent are CORRECT in a TEMPLATE** — the "no PostContent" project rule is about
  hand-built CONTENT PAGES (where each piece must be individually editable); a template renders whatever
  post is queried, so dynamic PostTitle/PostContent are the only sensible tools. (Templates #573/#574 here.)

## Site template-coverage checklist ("make sure the user is covered")
Audit that every request type resolves to a brand layout, not the empty `oxygen-zero` fallback. Verify each
by fetching a real URL (blank ≈ header+footer only, ~40KB). Type slugs live in `plugin/themeless/rules/`;
a single of any public post type uses **that post type's slug** as the template type.
| Request | Coverage (original build, illustrative) |
|---|---|
| Front page | page #10 own tree (`page_on_front`) |
| Content pages | each page's own `_oxygen_data` tree |
| Single product (PDP) | `oxygen_template` type `product` #522 |
| Product archives (shop/cat/tag) | `oxygen_template` type `all-product-archives` #521 |
| Cart / Checkout / My-account | per-page trees #18/#19/#20 (native Woopage* elements) |
| Search results | `oxygen_template` type `search` #570 |
| 404 | `oxygen_template` type `404` #573 |
| Single blog post | `oxygen_template` type `post` #574 |
| Header / Footer | `oxygen_header` #14 / `oxygen_footer` #15 (type `everywhere`) |
Still-open gaps to consider if used: blog/post **archive** (`home`/blog index), author/date archives,
comments. None are surfaced in this store's nav today.

## Reusable auxiliary panel — `.aux-box` component
1px light-gray container with padding for supplementary content (contact details, no-results, callouts):
```css
.aux-box{border:1px solid var(--c-gray-200);background:var(--c-gray-50);padding:clamp(22px,3vw,34px)}
.aux-box>*:first-child{margin-top:0}   /* heading never clashes with the box top or the element above */
.aux-box>*:last-child{margin-bottom:0}
```
Apply by adding `aux-box` to a Div's `settings.advanced.classes` (kept `prose` too for typography). Used
for "Otros canales" on the form pages and the search no-results state. Systemic spacing companion:
`.content-hero + .section{padding-top:var(--sp-6)}` removes the doubled hero+section top padding (was
88px across ~19 hero+section pages) so titles don't float far from their intro.

## Reusable Components (Oxygen "Components" = Global Blocks)
Oxygen's **"Components"** ARE Breakdance **Global Blocks** — post type **`oxygen_block`** (const
`BREAKDANCE_BLOCK_POST_TYPE`; `__bdox` relabels "Global Block"→"Component" in oxygen mode). Build once,
reuse anywhere, edit centrally (all instances update).
- **Create a component**: `wp_insert_post(['post_type'=>'oxygen_block','post_status'=>'publish','post_title'=>…])`
  then `oxy_write_tree($blockId, [...])` — same tree mechanics as a page. It appears in the builder's
  Components library.
- **Place it on a page**: an `OxygenElements\Component` node pointing at the block id:
  `oxy_el('OxygenElements\\Component', ['content'=>['content'=>['block'=>['componentId'=>$blockId]]]])`.
  Front-end renders via `\Breakdance\Render\renderGlobalBlock($componentId)` (`Component/ssr.php`).
  In the builder, insert from the Components panel. Components have no public URL — preview by dropping
  Component nodes onto a throwaway page.
- **Component library built here (#575–583)**: `comp-suplementario` (`.aux-box`), three
  `comp-rtbox-{ink,accent,light}` colour text boxes, `comp-banner-color` / `comp-banner-img-{left,right}`
  (split image+text) / `comp-banner-bg` (full-bleed image + overlay), `comp-hero`. Brand CSS = the
  `REUSABLE COMPONENTS` block in footer #15 (`.rt-box--*`, `.cta-banner--*`, `.comp-hero`,
  `.btn-cta--on-dark/--on-accent`). Split banners are a 2-col grid collapsing to 1 col <768px; image-bg
  reads its photo from a CSS `background-image` (swap centrally).
- **Audit heuristic — what should become a Component**: any block that repeats across pages with the same
  shape but different copy/image — hero sections, alternating image+text feature rows (`editorial-row`/
  `feature-row`), promo/CTA banners, callout/aux panels, coloured message boxes. Build the shape once as
  an `oxygen_block`; vary content per placement (or keep identical and edit centrally).
- **Building NATIVE composites (Tabs/Slider/Accordion) inside a component** — `oxy_golden()`'s
  `defaultChildren` are in ELEMENT-DEFINITION format (`{slug,defaultProperties,defaultChildren}`), NOT
  tree-node format, so you can't inject them directly. Use **`oxy_element_tree($slug)`** (lib.php) which
  converts the whole composite to a valid node with fresh ids, then override content. Traps hit 2026-07-11:
  - **AdvancedTabs**: nav titles come from `content.content.tabs[]`, but each tab's CONTENT is a
    **child `EssentialElements\TabContent`** panel (per `html.twig` `%%CHILDREN%%`) — titles-only renders
    empty panels. AND the tabs toggle JS did NOT enqueue when the element was embedded via a Global Block
    (all panels showed at once). **Fix that shipped: a self-contained `HtmlCode` tab** (buttons + panels +
    a tiny inline `currentScript.closest('[data-ctab]')` toggle) + CSS — reliable inside Components.
    Sliders (`Advancedslider`) DID work embedded (Swiper enqueues), so this is element-specific — prefer
    native, fall back to HtmlCode for interactive composites that don't enqueue.
  - Golden `defaultChildren` may contain internal atoms (`EssentialElements\Image`, not a builder slug) →
    `oxy_write_tree`'s validator rejects them. Build such composites from known-good elements instead.
  - `Wooproductslist` component: `content.content.product_count_to_show` + `design.layout.layout` =
    `'grid'` or `'slider'` (carousel; `design.layout.slider.settings.advanced.slides_per_view`).

### ⚠ Node-surgery id collision (appending to an existing tree)
When you append a FRESH `oxy_el()`/`oxy_*()` node to a tree you READ back (Component placement, adding a
banner, etc.), the new node's id comes from `oxy_nid()` which **starts at 100** and will collide with the
existing tree's ids (symptom: `oxy_write_tree` throws `duplicate node id 100`). **Seed the counter above
the tree's max first:** `oxy_nid(oxy_max_id_r($tree['root']));` BEFORE creating the new node(s). (This is
why the Component placements above compute max id first.) `generateCacheForPost` on a `Product`-template
is fine once ids are unique — the earlier "critical error" was this collision, not the Product render.

## Dynamic menus in header/footer (PhpCode pattern)
`oxygen-zero` registers NO nav locations (menus exist but are unwired; Oxygen expects its MenuBuilder).
For the faithful reference header we render by menu NAME in PhpCode (`site-builds/build_header_faithful.php`):
```php
$items = wp_get_nav_menu_items('Principal'); $by = [];
foreach ($items as $it) { $by[(int)$it->menu_item_parent][] = $it; }
// walk $by[0] = top level; $by[$id] = children
```
Depth → reference structure: grandchildren ⇒ `.has-mega` + `.mega` (tabs = child "columns", panels =
their leaf links); children only ⇒ `.has-drop` + `.dropdown`; none ⇒ plain link. Menu-item CSS class
`is-highlight` → `.is-highlight`. Same `$by` tree feeds the mobile `.m-nav` drawer.
(Native alternative: `EssentialElements\MenuBuilder` + `MenuDropdown` — use it when builder-editable
nav matters more than 1:1 fidelity.)

## Data-driven content (products, loops, custom fields) — PhpCode pattern
For dynamic/WP-data content, one `PhpCode` echoing reference-classed markup from live data beats
encoding dynamic content as native trees. Accessors that came up:
`wc_get_products(['category'=>[$slug],'exclude'=>[$id],'limit'=>N,'status'=>'publish'])`,
`$product->get_price_html()/get_image_id()/get_children()/get_sku()`,
`woocommerce_template_single_add_to_cart()`, `wp_get_nav_menu_items('Name')`,
`wp_get_post_terms($id,'product_cat')`, `wp_get_attachment_image($id,'woocommerce_thumbnail')`.
- PDP custom meta (original build example): `spec_components`/`tech_specs` = `[{label,value,note}]`,
  `geometry` = `{sizes:[], rows:[{label,values:[]}]}`, plus `discipline`/`fork_travel`/`rear_travel`/`wheel_size`.
- **Interactivity is free via data-attributes** — the global `main.js` wires the reference hooks:
  `[data-tabs]`+`[data-tab]`/`[data-panel]` (spec tabs), `[data-mega-tab]`/`[data-mega-panel]`
  (mega-menu), `[data-mm-toggle]`/`[data-mm-close]` (mobile drawer). Match classes/attrs exactly.
- Icons: inline `<svg class="icon" viewBox="0 0 24 24">…` strings (no icon library exists).
- PhpCode runs in full WP/WC context (`global $product` set on single-product templates; fallback
  `wc_get_product(get_queried_object_id())`).

## Native forms (`EssentialElements\FormBuilder`, breakdance-forms-for-oxygen)
The editable, plugin-managed form (renders, validates, AJAX-submits, stores submissions, emails).
Prefer over hand-rolled `<form>`; siblings: `LoginForm`, `RegisterForm`, `SearchForm`, `WooCheckout*`.
- **ONE node — fields are DATA, not children**:
  `content.form.form_name`, `content.form.fields[] = [{type:'text|email|textarea|…', label,
  advanced:{required:true, id:'name'}}]`, `content.form.submit_text`, `success_message`,
  `error_message`; `content.actions.actions[]` (keep golden default = store_submission + email);
  `content.actions.store_submission = {submission_title:'{email}', store_files:true}`;
  `content.actions.email.emails[] = [{message:'{all_fields}', subject, from, from_name:'{name}',
  reply_to:'{email}', to:false}]`; `design.form.theme = 'default'`.
- **Copy the golden shape** (`oxy_golden('EssentialElements\FormBuilder')`), override only fields/
  messages/`to`. Deep-copy per form (oxy_golden already does) — shared arrays leak fields between forms.
- **`emails[0].to` defaults to `false` → mail goes NOWHERE** until set (e.g. `get_option('admin_email')`
  or a merge tag). Merge tags = field ids: `{all_fields}`, `{name}`, `{email}`, `{<id>}`.
- Field types text/email/textarea confirmed; select/radio need an options shape — harvest from a
  builder-saved sample before scripting (text worked fine for phone/date here).
- Submit JS auto-enqueues (`awesome-form@1` + inline `breakdanceForm.init`) once the node is in the
  tree and `generateCacheForPost` ran.
- Rendered classes to brand-style (under your own wrapper class — the prefixer skips
  `.breakdance-form-*`): `div.bde-form-builder`; `form.breakdance-form.breakdance-form--vertical`
  (carries `data-options` JSON + hidden `form_id`/`post_id`);
  `div.breakdance-form-field.breakdance-form-field--{type}` > `label.breakdance-form-field__label`
  (+ `span.breakdance-form-field__required`) > `input|textarea.breakdance-form-field__input`;
  `button.breakdance-form-button.breakdance-form-button__submit.button-atom--primary`;
  result banner `div.breakdance-form-message--success/--error` (single dash after "form"!).
- Input names are `fields[<id>]`; `<form id>` = `{form_name-slug}{nodeId}`. Scope brand CSS
  `.acme-form .breakdance-form-field__input {…}` (0,2,0 beats engine's 0,1,0 — no !important).
  Center a standalone form with `margin-inline:auto`.
- Replacing CF7: split `post_content` on `/\[contact-form-7[^\]]*\]/`, render halves as prose, drop
  the FormBuilder between; neutralize strays with `add_shortcode('contact-form-7','__return_empty_string')`
  (mu-plugin `acme-forms.php`). Verified end-to-end: AJAX POST → email + stored submission →
  success banner + clearOnSuccess.

## Slider (`EssentialElements\Advancedslider` — Swiper.js)
Children = `Advancedslide` (each: Heading/Text/Button…). Dots `.swiper-pagination-bullet`, slides
`.bde-advancedslide`. Default `slidesPerView` = 1. DOM: `<slider node> > .breakdance-swiper-wrapper >
.swiper` with `.swiper-pagination` and `.swiper-button-prev/next` as SIBLINGS of `.swiper` inside the
wrapper (pagination sits below the slides in flow on this design — restyle bullets, don't reposition). Slide background image goes NATIVELY in the slide's
`design.background.layers.breakpoint_base` (PROPERTIES.md table) — but renders on the INNER
`.advanced-slider__slide` (default `width:744px; background-size:auto; repeat`). Full-bleed hero fix:
```css
.hero-slider .advanced-slider__slide{width:100%!important;min-height:50vh;background-size:cover!important;
  background-repeat:no-repeat!important;display:flex!important;align-items:center;justify-content:center}
```
+ `::after` dark overlay + content at `z-index:2`. Home #10's hero IS this (3 editable slides).
`.tpl-slider` brands dots/arrows. Swiper `nth-child` CSS works despite clone slides.
(`site-builds/build_hero_slider.php`, `build_home_templates.php`.)

**Autoplay/loop/speed are NATIVE** — set `design.slider.settings` on the slider node (exact value
shapes in PROPERTIES.md; `"enabled"` strings, `{number}` speeds). The settings object is passed
verbatim to `window.BreakdanceSwiper().update()` (`dependencies-files/breakdance-swiper/
breakdance-swiper.js` is the consumer and the shape ground-truth; defaults deep-merge, so set only
what you change). Engine already handles `prefers-reduced-motion` (autoplay off) and disables
autoplay inside the builder. Verify at runtime via `Object.values(window.swiperInstances)[0].params`.
Home #10 hero v2 (2026-07-11): autoplay 5.5s + pause-on-hover + keep-cycling-after-swipe, infinite
loop, 800ms slide; branded dash pagination + square ghost arrows via the `HERO SLIDER v2` block
appended to footer #15's CssCode.

## Images
`wp media import <path-or-url> --porcelain` → attachment id → `wp_get_attachment_url($id)`.
Import your brand wordmark once as an attachment and reuse its id in header/footer (`.site-logo`,
dark bars). Placeholder photography: Unsplash DIRECT photo URLs
(search endpoints are blocked; `images.unsplash.com/photo-<id>?w=…` + Picsum work) — imported as
#549–560, applied via CSS `background-image: linear-gradient(rgba(0,0,0,.4),…), url(…)` (gradient =
legibility overlay). Products still have NO images (`.ph` placeholders).

## Scroll-reveal (AOS-lite)
`.reveal`/`.is-in` CSS in the global stylesheet + an IntersectionObserver appended to the footer JS
node (`site-builds/add_aos_js.php`) that tags known blocks (`.editorial-row`, `.feature-row`,
`.product-card`, `.featured-grid>*`, `.loc-card`, `.section-heading`, `.acme-faq .bde-faq__item`, …).
Classes added BY JS so no-JS/reduced-motion never hides content.

## Native loop builder (Repeated Block) — PREFER for post/term lists (Project rule 3)
`OxygenElements\PostsLoop` ("Repeated Block") + `OxygenElements\TermLoopBuilder`. Architecture
(verified 2026-07-14 via builder-saved sample on scratch page):
- **Query** in `content.query.query`: `{active:"custom"|"text"|"array", text:"post_type=post",
  custom:{source:"post_types", postTypes:["post",…], postsPerPage:8, orderBy:"date", order:"DESC",
  ignoreCurrentPost, ignoreStickyPosts, conditions:[[[]]], totalPosts, offset, date:"all", …},
  php:"return ['post_type'=>'post'];"}`. Term loop: `content.query.term_query` = PHP-return string.
- `content.pagination` = `{pagination:"numbersprevnext", show_all_page_numbers:true}`.
- `content.filter_bar` = `{enable:true, all_filter:true, hide_uncategorized:true, …}` (built-in
  category filter UI — a big reason to prefer this over PhpCode).
- **The repeated item is a COMPONENT block, not inline children** ("Repeated Block → Component"
  dropdown → `oxygen_block` id). So the item template MUST be a pre-created Component, and to show
  post data that Component MUST contain **dynamic-data fields** (Featured Image=featured image,
  Post Title with Link=post URL, Post Excerpt). A STATIC component repeats identically N times.
### PROVEN recipe (2026-07-14, live on blog archive #602)
Component-based item + injectable Repeated Block:
1. **Item = a Component (`oxygen_block`)** referenced by `content.repeated_block = {"global_block": <id>}`.
   Build the component with **native auto-resolving post-field elements** — these render the current
   loop post WITHOUT any data-point binding: `EssentialElements\PostTitle` (set heading level via
   `content.content.tags` = `'h3'` — `settings.advanced.tag` is IGNORED and it defaults to h1!),
   `EssentialElements\PostExcerpt`, `EssentialElements\PostMeta`. ⚠ Generic `Heading`/`Image`/`Text` do
   NOT auto-bind (stay static) — and Featured-Image / post-link need data points that must be picked in
   the builder (no reliable hand-authored JSON). Posts here have no images, so title+excerpt+meta suffices.
2. **Repeated Block** (`OxygenElements\PostsLoop`): copy a builder-saved node verbatim, then tweak
   `content.query.query.custom.postTypes`/`postsPerPage` + `.php`. Set `content.repeated_block.global_block`
   to the component id. `content.filter_bar.enable=true` gives a real category filter (Isotope) for free.
3. **Layout columns are Isotope-controlled** — CSS `width` on `.bde-loop-item` does NOT override it; set
   columns in the Repeated Block's builder design panel. **Card click-through** = a post-URL data point on
   the title/card (builder). `site-builds/port_native_loop_602.php` + `finalize_blog_native_loop.php`.
   `EssentialElements\PostTitle` renders `<h1>` by default — in a loop set `tags:'h3'` to avoid many h1s.
- Blog archive #602 = native loop ✓. Related posts #574 = same recipe, still PhpCode (convert when wanted).

## Rebuilding the footer (or any template carrying global assets)
Footer #15 holds the site-wide `CssCode` + `JavaScriptCode` nodes. When regenerating its tree, READ the
current tree first (`\Breakdance\Data\get_tree(15)`), keep those two nodes, and re-inject them alongside
the new content — or the whole site loses its stylesheet/scripts.

## Blog / post archive (added 2026-07-14)
The blog index is `is_home()`, NOT `is_archive()` — so `all-archives` type MISSES it. Use template type
**`post-archives`** (callback `(is_archive()||is_home()) && !is_product_category() && !is_product_tag()
&& !is_woocommerce()`): covers the blog index + post category/tag/date/author, EXCLUDES WC archives (no
conflict with PLP #521), and never hits the static front page (#10 is `is_front_page`, `is_home()` false
there). Steps: (1) create a "Blog" page + `update_option('page_for_posts', $id)` (gives `/blog/`),
(2) `oxygen_template` post + `oxy_template_settings($id,'post-archives',30)`, (3) tree = `content-hero`
Div with a PhpCode dynamic H1 (`is_home()` → posts-page title; else `get_the_archive_title()`) + a PhpCode
`have_posts()` loop of `.post-card`s (+ `paginate_links` + empty state). `wp rewrite flush` after.
`scripts/examples/build_blog_archive.php`. Full type-slug list: `post-archives`, `all-archives`, `category`,
`date`, `author`, `all-product-archives`, `page`, `post`, `product`, `search`, `404`, `front-page`,
`everywhere`, `all-singles`.

## PLP dynamic category/tag header (added 2026-07-14)
Reuse PLP #521 for shop + all product-cat/tag archives instead of a dedicated template. The `.cat-hero`
title was hardcoded "Tienda" → showed on category pages too. Fix: replace the static h1/p inside
`.cat-hero__inner` with ONE PhpCode: `is_product_taxonomy()` → `single_term_title('',false)` +
`term_description()`; else "Tienda" + gama copy. (Don't use `woocommerce_page_title()` for the shop case —
it returns the shop PAGE title "Shop", not "Tienda".) `site-builds/fix_plp_category_header.php`.

## Product / placeholder images (added 2026-07-14)
Products shipped with none (`.ph` placeholders). Demo fix: `curl` a DIRECT `images.unsplash.com/photo-…`
to a local `.jpg` (URL import fails filetype — GOTCHAS), then `wp media import <file> --post_id=<product>
--featured_image --porcelain --title="… (demo placeholder)"`. To REPLACE + avoid orphans: capture
`get_post_thumbnail_id()` first, import new, then `wp post delete <old> --force`. Verify shop cards render
`<img>` (not `.ph`) and PDP gallery shows the file. ⚠ Demo images ≠ real SKUs — flag "swap before launch".
`site-builds/build_product_images.php` (initial set + a later per-category re-swap, #617-619).

## WooCommerce localized strings stored as English (added 2026-07-14)
Even at a non-English locale (e.g. `es_ES`), the checkout & register privacy notices render English because they're stored
option VALUES (not translatable defaults): `woocommerce_checkout_privacy_policy_text` and
`woocommerce_registration_privacy_policy_text`. `wp option update` them to your language, keeping the
`[privacy_policy]` token (WC swaps it for the linked Privacy Policy page).

### Dynamic data via injection — CAPTURED shape (2026-07-14, from builder-saved #628)
Dynamic data is a **shortcode** in the value field + a sibling `*_dynamic_meta` object. This unlocks
injecting clickable loops / dynamic fields (earlier thought builder-only):
- **Field slugs** live in `oxygen/plugin/dynamic-data/fields/*` (e.g. `post_permalink`, `post_title`, …).
- **WrapperLink** (make a whole card clickable): `content.content.link = {type:"url",
  url:"[breakdance_dynamic field='post_permalink']", dynamicMeta:{field:"post_permalink",
  shortcode:"[breakdance_dynamic field='post_permalink']", attributes:[]}}`. Wrap the card's inner
  elements as its children.
- **TextLink**: `content.content.url = "[breakdance_dynamic field='post_permalink']"` +
  `content.content.url_dynamic_meta = {field, shortcode, attributes:[]}` (note: `url_dynamic_meta`,
  not `dynamicMeta`).
- **Image = dynamic featured image** (PROVEN 2026-07-14, injected into #628): `content.image =
  {from:"url", url:"[breakdance_dynamic field='post_featured_image_url']", url_dynamic_meta:{field,
  shortcode, attributes:[]}, lazy_load:true, alt:"custom", custom_alt:"[breakdance_dynamic field='post_title']"}`.
  The Image URL field accepts `image_url` dynamics; field slug `post_featured_image_url` (returnTypes
  `['url']`). Renders `<img class="oxy-image post-card__image">`. `oxy_image()` in lib.php is media-library
  only — build this node by hand with `oxy_el('OxygenElements\Image', …)`.
- **Inserting into an existing component/tree**: seed the id counter past the current max first —
  `oxy_nid(oxy_max_id_r($root))` — or new `oxy_el()` nodes collide with existing ids (100,101,…).
- Native `PostTitle`/`PostExcerpt`/`PostMeta` still auto-resolve WITHOUT a shortcode (loop context).
- **Blog list #602 + related #574 = native loop, clickable (WrapperLink), each card has a dynamic
  featured Image.** Both reference component #628. Layout is class-scoped so they differ:
  - #602 loop class = `post-loop-featured` → **vertical list, first post featured** (hero row via
    `.bde-loop-item:first-child`, `FEATURED` ::after badge, image height capped with `clamp()`).
  - #574 loop class = `post-loop-grid` → 3-col grid (image-on-top cards).
  Grid/flex go on the inner `.bde-loop` wrapper (the real items nest there), NOT the outer
  `.bde-post-loop`. Isotope (filter bar) absolutely-positions items and BLOCKS CSS grid/flex, so the
  filter bar is OFF on these; for filter-bar + grid together, set columns in the builder's Isotope panel.

## ACF Pro content model — CPTs + field groups (verified 6.8.6, 2026-07-17)

Two registration homes; pick ONE per object (registering the same CPT in both fatally
double-registers):
- **Code** (`register_post_type()` on init + `acf_add_local_field_group()` on acf/init):
  versioned by definition, but INVISIBLE in ACF's admin — the client/next dev sees empty
  "Post Types"/"Field Groups" screens and reports "the post type was never created".
- **ACF store** (UI-editable) **+ JSON sync into the plugin** — the handover-friendly
  endpoint. Best of both: click-editable, and every UI save rewrites versioned JSON.

JSON sync wiring (site plugin):
```php
add_filter('acf/settings/save_json', fn() => plugin_dir_path(__FILE__) . 'acf-json');
add_filter('acf/settings/load_json', fn($p) => [...$p, plugin_dir_path(__FILE__) . 'acf-json']);
```
Fresh install → ACF shows "Sync available" for everything in `acf-json/`.

Programmatic writes into the ACF store (register the save_json path FIRST so JSON lands):
- Post type: `acf_update_post_type([...])` — ACF's OWN schema, not register_post_type args
  (authoritative: `includes/post-types/class-acf-post-type.php::get_settings_array()`).
  Key facts: `key` = `post_type_<slug>` (stored as post_name of an `acf-post-type` post —
  guard idempotency on it); `menu_icon` accepts a string OR `{type:'dashicons',value:…}`
  (the array is what the UI picker writes); custom slugs via
  `rewrite = {permalink_rewrite:'custom_permalink', slug, with_front, feeds, pages}`;
  set `advanced_configuration => true` or the UI hides the configured extras. Post-type
  JSON is written automatically on save (there is NO `acf_write_json_post_type()`).
- Field group: `acf_import_field_group($group)` with the exact
  `acf_add_local_field_group()` array + `'active'=>true` — keys are PRESERVED, so content
  written under code-registered fields keeps resolving after migration (verified:
  values + repeater/gallery intact). Guard with `acf_get_field_group($key)['ID']`.
  For JSON call `acf_write_json_field_group($saved)` explicitly after import.
- After registrations change: `flush_rewrite_rules()` once (option-flag pattern), or the
  CPT permalinks 404.

Field-group conventions that pay off: stable prefixed keys (`field_<proj>_*`) — FROZEN
once content exists (post meta stores the key in `_<name>`); `hide_on_screen:
['the_content']` when ACF is the whole form; gallery/repeater need Pro; wysiwyg with
`toolbar:'basic', media_upload:0` for client-safe copy fields.

Admin columns for the client (site plugin, works with either registration home):
`manage_<cpt>_posts_columns` filter + `manage_<cpt>_posts_custom_column` action reading
`get_field()` (cover thumb / counts / ★ featured).

⚠ Oxygen integration status: dynamic-data BINDINGS of ACF fields inside `_oxygen_data`
trees and PostsLoop remain golden-sample gaps (see SKILL.md known-gaps) — build one in
the real builder and read it back before scripting.

## CPT-driven rail/grid: PostsLoop + dynamic card (the full chain, verified 2026-07-17)
1. **Card = Global Block** reusing the design's existing card classes 1:1 — every content
   piece dynamic: ContainerLink url = `[breakdance_dynamic field='post_permalink']`, Image
   url-mode src = `…post_featured_image_url`, Texts bound to `post_title` / `acf_field_<KEY>`
   (+ `text_dynamic_meta` mirrors). The shortcode resolves in link/image string props, not
   just text (verified).
2. **Loop node** in the page tree (node surgery, not rebuild): `content.query.query`
   (custom mode for plain lists; **php mode for meta filters** — e.g. a `featured` toggle
   driving a home rail) + `content.repeated_block.global_block = <card ID>`.
3. **CSS unwrap**: the loop emits `.bde-post-loop > .bde-loop > article.bde-loop-item`;
   `display:contents` on all three inside the styled parent keeps existing flex/snap CSS.
4. **Single template**: `oxygen_template` + `oxy_template_settings($id, '<post_type>', 30)`
   (per-post-type singular rules generate automatically for public CPTs) — dynamic phead +
   ACF bindings + PhpCode renderers for gallery/repeater fields (image/repeater BINDINGS
   still unshaped; PhpCode with `get_field()` is the sanctioned renderer).
5. Editor UX: field groups `style:'seamless'` + `position:'acf_after_title'` + field
   `menu_order` = the "form right under the title" feel; `ui_on_text/ui_off_text` on
   true_false toggles.

## Elegant SVG placeholders (plugin template, verified 2026-07-17)
Ship `scripts/examples/elegant-placeholders.php` as a standalone plugin when a build needs
placeholder imagery that looks INTENTIONAL (warm paper tones, hairline rule, small-caps
label + dimensions + optional sublabel; deterministic tint per label). Three surfaces:
- `ep_svg($w, $h, ['label','sublabel','tone'])` — the raw SVG string.
- `ep_attachment($w, $h, ['label','sublabel','caption','alt'])` — media-library
  placeholder, idempotent per (w,h,label) via `_ep_key` meta. Feed the IDs to ACF
  gallery/image fields so templates render real attachments the client later REPLACES.
- `/?ep=1200x800&label=Hero&sublabel=Placeholder` — ad-hoc endpoint (no file) for
  mocking inside the builder.
Palette/font filterable (`ep_palette`, `ep_font`). Two traps encoded in it:
- `wp_upload_bits('x.svg', …)` FAILS silently-ish (error string) — WP disallows the svg
  mime. Allow it with a SCOPED `upload_mimes` filter around the one call; never globally
  (svg upload is an XSS vector for untrusted users).
- SVG attachments get no intermediate sizes/metadata → `wp_get_attachment_image()` emits
  no width/height (CLS). Write them yourself: `wp_update_attachment_metadata($id,
  ['width'=>$w,'height'=>$h,'file'=>_wp_relative_upload_path($file)])`.
ALWAYS say "placeholder" in the artwork itself (sublabel) — stock photos posing as the
client's product mislead reviews; a labeled placeholder invites replacement.

## Artwork-tinted backgrounds (4-point gallery wall, verified 2026-07-17)
Give a dark hero its colour temperature from the artwork itself: extract the image's four
QUADRANT colours server-side (GD), emit them as scoped CSS vars, paint four corner
radial-gradients over the base ink. The gradient then mirrors the artwork's own colour
placement (sky corners cool, rock corners warm).
- Extraction that actually works: central crop (~18% margins — product shots are often
  framed objects on neutral walls, plain averages sample mostly wall) + **saturation²-
  weighted** averaging per quadrant (neutrals barely register; a plain mean grays out) +
  a gentle saturation lift (×1.45) BEFORE mixing ~60% toward the base dark (so the tint
  survives darkening and text contrast holds).
- Cache in attachment meta keyed by a fingerprint of file+algorithm-version.
- Renderer: CMS colour-picker overrides first (one field per corner), auto-extraction as
  the default; emit `<style>.hero{--wall-1:…}</style>`; CSS uses
  `radial-gradient(110% 100% at 0% 0%, var(--wall-1, transparent) 0%, transparent 60%)`
  ×4 corners over the base — vars absent = original design (safe fallback).

## Related-posts as a native PostsLoop (php-query mode, verified 2026-07-17)
"Related work" / "you might also like" is a POST LIST → PostsLoop + a card Global Block,
NOT a PhpCode loop (Project Rule 3). The related-to-CURRENT-post query is dynamic, so use
**php query mode** — it runs at render on the single, where `get_the_ID()` is the current
post. Taxonomy-match first, then PAD so the row is never short:
```php
'query' => ['query' => ['active' => 'php', 'custom' => null, 'text' => '', 'php' =>
  "\$cur=get_the_ID();
   \$ids=wp_get_post_terms(\$cur,'df_work_type',['fields'=>'ids']);
   \$rel=[];
   if(\$ids && !is_wp_error(\$ids)){ \$rel=get_posts(['post_type'=>'df_project','posts_per_page'=>4,
     'post__not_in'=>[\$cur],'fields'=>'ids','tax_query'=>[['taxonomy'=>'df_work_type','field'=>'term_id','terms'=>\$ids]]]); }
   if(count(\$rel)<4){ \$more=get_posts(['post_type'=>'df_project','posts_per_page'=>4,
     'post__not_in'=>array_merge([\$cur],\$rel),'fields'=>'ids']); \$rel=array_slice(array_merge(\$rel,\$more),0,4); }
   if(!\$rel){ return ['post__in'=>[0]]; }               // no matches → render nothing
   return ['post_type'=>'df_project','post__in'=>\$rel,'orderby'=>'post__in','posts_per_page'=>4];"]],
'repeated_block' => ['global_block' => $CARD_ID, 'tag' => 'article'],
```
- Card Global Block = fully dynamic (permalink/cover/title/subheading via oxy_dyn_* — the
  loop sets per-post context). Reusable across every single of that type.
- `orderby=>'post__in'` preserves the taxonomy-first-then-padded order.
- Unwrap the loop into your card grid with `display:contents` on
  `.grid .bde-post-loop, .grid .bde-loop, .grid article.bde-loop-item` (§code-node-wrapper).
- ⚠ `repeated_block.global_block` MUST be a RESOLVED integer at write time. A bare
  undefined `$card` PHP var serializes to `null` → the loop renders
  "Choose a Component from the dropdown" empties. Resolve the block id by title in the
  build script (get_posts oxygen_block) before writing the tree.

## Static Mirror — export the whole site as attack-proof plain files (verified 2026-07-18)
`scripts/examples/static-mirror.php` (+ its static-search.js companion): a standalone
plugin that crawls the site over LOOPBACK HTTP (so the export is exactly what a visitor
gets, whatever rendered it — Oxygen trees, PhpCode, options), harvests every referenced
local asset (srcset variants, video, posters, css/js incl. depth-1 url() follows),
rewrites the origin (production URL option, or root-relative), strips dead-on-static WP
head links (oEmbed/REST/shortlink — two of them smuggle the origin URL-ENCODED past a
plain str_replace), writes a styled 404.html + .htaccess (ErrorDocument + immutable
asset caching) + a zip. Search keeps working via an exported search-index.json + a small
script that hooks the site's search overlay client-side (only injected into the export).
- The honest security claim: a same-host cache protects NOTHING (wp-login/PHP still
  reachable). Protection = deploy the export to the public host and keep WP private —
  the public surface has no PHP, no DB, no login.
- Trap: custom template_redirect endpoints (llms.txt-style) serve content with HTTP 404
  — WP marks unknown routes 404 BEFORE template_redirect; call `status_header(200)`.
- Verify by SERVING the export (`python3 -m http.server`) and curling routes + assets +
  a full-text origin-remnant sweep — never by eyeballing the file tree.

**Static-first serving on the SAME server + hidden login** (the plugin's second half):
- `advanced-cache.php` DROP-IN serves the export before WP connects to the DB, for every
  public request. It bails (→ live WP) for: any `wordpress_logged_in_` cookie (editors
  preview), the secret login slug, and wp-cron. Needs `define('WP_CACHE', true)` in
  wp-config — detect it with a REGEX on `define(...'WP_CACHE'...)`, NOT a substring test
  (false-positives on the salt strings, which contain "WP_CACHE"-like noise... verified).
- Apache/LiteSpeed also get an `.htaccess` block (placed ABOVE the WordPress block) that
  serves files with zero PHP on the public path; on nginx the drop-in is the mechanism.
  ⚠ On a LiteSpeed / nginx-hybrid MANAGED host the `.htaccess` block mis-routes: its catch-all
  `RewriteRule ^ - [R=404,L]` 404s every query-string / dynamic URL (`?type=…`, `?s=…`). Prefer
  the `advanced-cache.php` DROP-IN path there, or keep serving OFF and run live WP. Also note
  `StaticMirror\run()` re-writes this block on every export — a remote re-export silently
  re-enables it. Full symptom table + recovery: GOTCHAS §static-first-litespeed.
- **Hidden login** = a PHP guard (`init`, pri 1): `/wp-login.php` and `/wp-admin` (no live
  session) return the static 404; the real login is `require`d from a secret slug route
  (`wp_loaded`), and `site_url`/`wp_redirect` filters keep every generated login URL on
  the slug. Defense in depth: even a FORGED logged-in cookie that slips past the drop-in
  still hits the PHP guard (verified: forged cookie + wp-admin → 404).
- ALWAYS ship an escape hatch: an mu-plugin honoring `define('STATIC_MIRROR_OFF', true)`
  in wp-config to disable the guard if the slug is lost; slug recoverable via WP-CLI.
- **Auto-update** (opt-in): re-export in the background when admin content changes,
  DEBOUNCED — a single wp-cron single-event pushed to now+90s, cleared+rescheduled on each
  change, so a burst of saves = one rebuild. Split the handlers: `save_post` gets an
  autosave/revision/draft filter; deletions/terms/menus/acf-options use a plain scheduler
  (post IDs and term IDs share the int space — `get_post_status($termId)` can collide, so
  never status-filter a non-post trigger). Cron fires while an editor works the admin; on a
  low-traffic LIVE site add a real system cron hitting wp-cron.php (the drop-in lets
  wp-cron.php through). The export sets a STATIC_MIRROR_EXPORTING guard so it never marks
  itself dirty.
- Fully portable: crawls every `public` post type + taxonomy, folds any string ACF field
  into the search index — no per-project code.
- **SECURITY — a high-effort code review caught these; every static-export/serving plugin
  must get them right (all verified fixed):**
  1. *Path traversal in the drop-in.* `rawurldecode(REQUEST_URI)` joined onto the export
     dir let `/..%2f..%2fwp-config.php` readfile() your secrets, UNAUTHENTICATED, before
     WP loads. Fix: normalize `.`/`..` segments out, then `realpath()` + require the result
     to sit under `realpath(exportDir)` (strpos root . DIRECTORY_SEPARATOR === 0).
  2. *Forge-proof the editor bypass — NEVER trust a cookie NAME.* Checking
     `strpos($k,'wordpress_logged_in_')===0` means `Cookie: wordpress_logged_in_x=1`
     bypasses the whole static shield into live WP. Can't validate WP's HMAC before WP
     loads, so use a plugin-owned SECRET: a random token set as an httponly `sm_preview`
     cookie only for genuinely `current_user_can` editors, compared with `hash_equals`.
  3. *Loopback re-export freezes.* With serving on, the crawler's own GET gets served the
     OLD static file → every re-export re-captures the frozen copy. Send the same secret
     token as a request HEADER the drop-in honors, so only the crawler bypasses.
  4. *CSS `url()` following can exfiltrate.* `url(../../../wp-config.php)` in any stylesheet
     copied wp-config into the PUBLIC export. Contain: require an asset extension AND
     `realpath` under `realpath(ABSPATH)`; normalize `..` before recursing.
  5. *Client search XSS.* Post titles/urls → `innerHTML` executed `<img onerror>`. Use
     `textContent` for the title and validate the url through `new URL()` (http/https only).
  6. *Login guard edges.* `str_starts_with($uri,'/wp-login.php')` misses `//wp-login.php`
     (normalize leading slashes first); `str_starts_with($uri,'/wp-admin')` 404s a legit
     `/wp-admin-guide/` page (anchor: `=== '/wp-admin' || starts_with '/wp-admin/'`).
  7. *Reader/writer path drift.* Drop-in hardcoded `__DIR__.'/uploads/...'` but export used
     `wp_upload_dir()` — diverge on a moved uploads dir. Embed `var_export(export_dir())`.
  8. *attachment status.* `attachment_updated` never rebuilt — attachments are status
     `inherit`, which the draft-skip list dropped; revisions (also inherit) are already
     filtered by `wp_is_post_revision`, so just don't skip `inherit`.
  9. *Origin leak.* `rewrite()` missed PERCENT-ENCODED origins (`https%3A%2F%2F…`) — add the
     `rawurlencode`/`%2F`/entity variants.
  LESSON: a plugin that serves files before WP loads is a mini web server — treat every
  request-derived path as hostile, and never make a security decision from data the client
  fully controls (a cookie name, a header presence). Run /code-review on anything like it.  Attack-surface battery (all verified):
  wp-login/wp-admin/xmlrpc/wp-json → 404, POST → 404, forged-cookie → 404, secret slug →
  live login form posting back to the slug, public pages → static hit, editor cookie →
  live WP.

**Asset optimization on export (verified 2026-07-18, admin toggle, default ON):**
- Three stages hooked into the existing write points (`copy_asset()` for CSS/JS/SVG/JSON,
  `put()` for HTML/XML/txt) — no new crawl pass:
  1. *CSS* → full minify (strip comments keeping `/*! */`, collapse whitespace, tighten around
     `{}:;,>~+`, drop the last `;` in a block). Safe: CSS has no ASI. (48KB → 8KB gzip.)
  2. *JS* → **conservative, line-safe minify** — strip whole-line `//` and `/* */` comments,
     blank lines, and indentation, but NEVER join tokens across newlines. See GOTCHAS
     §js-minify-asi for why token-joining a generic (un-parsed) script silently breaks it.
  3. *gzip sibling* (`$file.gz`, level 9, skip <512B) for every text asset AND page — a 100%
     lossless transform, so it's the real win even on already-minified Oxygen CSS (~80% off).
- **Delivery = content negotiation in the drop-in**, not blind serving: the serve closure adds
  `Vary: Accept-Encoding`, and only when the request carries `Accept-Encoding: gzip` AND a
  `.gz` sibling exists for a text ext does it swap the body to the `.gz` and send
  `Content-Encoding: gzip` + `Content-Length: filesize(.gz)`. Clients without gzip get the
  identity file untouched. The same `.gz` files are what nginx `gzip_static on` / Netlify /
  Vercel serve automatically, so one export is optimal here OR deployed. (Homepage: 71KB
  identity → 14.7KB on the wire, −79%.)
- Don't minify inline `<style>`/`<script>` in HTML with regex (string/`</script>` hazards) —
  gzip the whole page instead; the linked `.css`/`.js` files are where minification pays.
- VERIFY by curling with and without `-H 'Accept-Encoding: gzip'`: the gzip body must
  `gunzip -c` back to the EXACT identity bytes (proves no double-encoding from a front proxy
  re-gzipping your already-gzipped body — it must honor your `Content-Encoding` and pass through).

## Admin UX standards — featured-image column + card/list view (verified 2026-07-18)
Two small, GENERIC admin conveniences that turn image-driven CPTs (portfolios,
catalogs, logo/brand libraries) from title-only lists into something you can scan
by picture. Both are drop-in and register themselves for the right post types — no
per-type code. Working, de-branded references: `scripts/examples/admin-thumb-columns.php`
and `scripts/examples/admin-card-view.php`.

**1. Featured-image list column (a standard for EVERY thumbnail-supporting type).**
- Target `post_type_supports($pt,'thumbnail')`, NOT `public===true` — a logo/brand CPT
  is frequently non-public yet still wants the column. Exclude `attachment`.
- Wire per type inside `admin_init`: `add_filter("manage_{$pt}_posts_columns", …)` +
  `add_action("manage_{$pt}_posts_custom_column", …, 10, 2)`. Insert the cell right after
  the `cb` checkbox column. Get the current screen's type from `get_current_screen()->post_type`.
- **Render 'medium' (UNCROPPED) + `object-fit:contain`, NOT the cropped 'thumbnail'.**
  The 150×150 hard crop chops wide wordmarks ("The Ritz-Carlton" → "ITZ-CA"); contain keeps
  the whole logo/cover and the tile backdrop fills the rest.
- Backdrop = a light **chequerboard** (two crossed linear-gradients) so pale/transparent
  logos stay visible; frame with `1px solid #c3c4c7` (WP's control grey) + `border-radius:6px`
  so it matches wp-admin. Emit the CSS in `admin_head` gated on `get_current_screen()->base==='edit'`.
- Label via a filter (default "Cover"; a brand type returns "Logo").

**2. Card ⇄ List view toggle (own plugin; remembers the choice).**
- Default target set = every CUSTOM image CPT: `get_post_types(['_builtin'=>false])` ∩
  `post_type_supports('thumbnail')`. Filterable.
- Build the grid SERVER-SIDE from the SAME query the table used — on `edit.php` the list
  table has already replaced `$GLOBALS['wp_query']`, so `$wp_query->posts` is the current
  page/filter/sort/pagination set. Render it in `admin_footer`, then JS moves it in right
  after `.tablenav.top` so the search box, filter dropdowns, status links and PAGINATION all
  keep working (a filter/paging click reloads and re-renders cards).
- Switch views purely with a body class: `admin_body_class` adds `oxy-av--cards|--list` from
  saved user meta (rendered server-side → no flash-of-list). CSS: `body.oxy-av--cards
  .wp-list-table{display:none}` + `.oxy-av-grid{display:grid}`.
- Persist per-USER, per-POST-TYPE in user meta via a nonce-guarded `wp_ajax_` handler; validate
  the incoming post type against the target set before saving.
- Card frame reuses the same contain + chequerboard trick so logos stay whole; whole-card is a
  click-through to Edit (screen-reader `<a>` underneath; real Edit/View/Trash links on hover above).
- Bulk actions/column-sort live in the hidden table — fine; switch to List for those. Keeping the
  top toolbar visible is what preserves search/filter/paging in card mode.
- **Uniform cards** (so the grid reads as a tidy matrix, not a ragged wall): give the image frame a
  FIXED `aspect-ratio` (4:3 vertical / a square left-thumb horizontal) with `object-fit:contain` +
  the chequerboard, and clamp the title to a FIXED height (`-webkit-line-clamp:2` + `height:2.7em`).
  Equal frame + equal title height ⇒ every card is identical. A `vertical|horizontal` orientation is
  just a body class flipping the card's `flex-direction` (+ frame from full-width-4:3 to a fixed
  square on the left) — persist it exactly like the view choice (own meta key, own segment control).
- **Build the toggles as a bespoke segmented control, NOT WP `.button`s** — `.button` +
  `button-primary` inside a rounded group double-borders and looks broken. A plain `<button>` group
  with a shared border, `border-left` dividers and an `.is-active{background:#2271b1;color:#fff}`
  state reads cleanly and matches wp-admin.
- **Fill vs Fit is a real preference, not one answer** — PHOTOS (portfolios, covers) want
  `object-fit:cover` (fill the frame, crop, no wasted space); LOGOS want `object-fit:contain` on a
  chequerboard (whole mark, never cropped). Expose it as a third `fill|fit` segment (body class flips
  `object-fit` + shows the chequerboard/padding only in fit), and set a PER-TYPE default via a
  `oxy_admin_views_default_fit` filter (a logo/brand CPT → `fit`, everything else → `fill`). Don't
  force one globally.
- **`box-sizing:border-box` on the frame is mandatory** — with `padding` + `width:100%` under the
  default content-box, the frame is 24px wider than the card, the card's `overflow:hidden` clips the
  right edge, and a "contained" logo looks cropped (chased this as a phantom object-fit bug). Put
  `box-sizing:border-box` on the card and `* {box-sizing:border-box}` inside it.

## Self-hosting webfonts — kill the FOUC + the cross-origin fetch (verified 2026-07-18)
Oxygen's Global Settings font is delivered as a render-blocking `fonts.googleapis.com` link that
Oxygen prints DIRECTLY into `<head>` (no handle — you cannot `wp_dequeue_style` it; see GOTCHAS
§oxygen-google-fonts). Self-host instead — headings paint on the first frame and the static
export carries no external calls. Pattern (a small project plugin, front end + builder preview):
1. **Grab the woff2 + `@font-face` CSS.** From the Google Fonts CSS API (one variable file per
   subset/style) or `google-webfonts-helper`. Save `assets/fonts/<family>-<subset>.woff2` and an
   `assets/fonts.css` with one `@font-face` per file: `font-family` (the exact name Oxygen uses,
   e.g. `Montserrat`), `font-weight: 200 900` (the variable range), `font-style`, `font-display:
   swap`, `src: url('fonts/<file>.woff2') format('woff2')`, and the `unicode-range` per subset so
   latin-ext / italic only download when a glyph needs them. `url()` is relative to `fonts.css`.
2. **Enqueue the local stylesheet** in place of the Google URL:
   `wp_enqueue_style('site-fonts', plugin_dir_url(__FILE__).'assets/fonts.css', [], null);`
3. **Preload the primary file** at `wp_head` priority 1 (the one weight-range every heading + body
   uses; the others load on demand via `unicode-range`):
   `echo '<link rel="preload" href="'.esc_url($woff2).'" as="font" type="font/woff2" crossorigin>';`
   `crossorigin` is REQUIRED even same-origin — fonts fetch in CORS mode; without it the preload is
   ignored and re-fetched.
4. **Strip Oxygen's own link** (front end only), because it has no handle:
   ```php
   add_action('template_redirect', function () {
       if (is_admin()) return;
       ob_start(fn($h) => (string) preg_replace('#\s*<link\b[^>]*fonts\.googleapis\.com[^>]*>#i', '', $h));
   });
   ```
VERIFY against LIVE WP, not a cached copy: on a static-first / edge-cached host add `?cb=<n>` to
force a fresh render (GOTCHAS §static-first-litespeed), then confirm `grep -c fonts.googleapis`
is 0 and both the preload and `fonts.css` are present. Re-export afterwards so the static copy
bundles the woff2 and contains zero Google calls.

---

## Media & motion recipes (verified on the Boostribe marketing build, 2026-07)

These are the reusable patterns a modern marketing site needs that weren't covered above. All are
hand-authored (native Div/Container wrappers + `oxy_html`/`oxy_js` leaves) so they stay in the tree
and pass validation. Flip layout Divs → Containers first (the `.bde-div` reset).

### Photo / video hero with scrim
```
.phero (position:relative; min-height:76vh; display:flex; center; overflow:hidden)
  <video class="phero__vid" autoplay muted loop playsinline preload="auto"><source src=".mp4"></video>
     — OR — <img class="phero__img" src=".jpg" alt="" aria-hidden="true">   (both: absolute inset:0; object-fit:cover; z:0)
  .phero__scrim  (absolute inset:0; z:1; background:linear-gradient(rgba(20,12,26,.28),rgba(20,12,26,.55)))
  .phero__inner  (position:relative; z:2)   ← wordmark h1 + subtitle + CTA
```
The wordmark is the `<h1>`; give it explicit `color:#fff` (see GOTCHAS §heading-color-reset).

### Hover-play video (cinemagraph — the GIF alternative)
Static poster until hover, then plays; pauses+rewinds on leave. Lighter and cleaner than a GIF
(a GIF can't pause). Use MP4 (no alpha) on a matching solid cell bg; if you need transparency,
src-swap a PNG↔GIF instead. Poster = the video's first frame (`ffmpeg -i x.mp4 -frames:v 1 poster.jpg`).
```html
<video class="team__vid" poster="poster.jpg" muted loop playsinline preload="none"
       aria-label="Fran Pérez"
       onmouseenter="this.play()" onmouseleave="this.pause();this.currentTime=0">
  <source src="fran.mp4" type="video/mp4"></video>
```
CSS: square cell (`aspect-ratio:1/1`) so a 480×480 clip fills without cropping the face.

### Scroll-scrubbed Lottie (scroll-driven animation — the Interactions gap, hand-rolled)
Self-host `lottie-web` + the JSON in uploads. A tall pinned section drives frame = scroll progress.
```
.scroll (height:560vh; position:relative)   →   .sticky (position:sticky; top:0; height:100vh)   →   .mount
```
```js
var a=lottie.loadAnimation({container:mount,renderer:'svg',loop:false,autoplay:false,path:JSON});
function upd(){ var scrollable=wrap.offsetHeight-innerHeight, p=Math.min(1,Math.max(0,-wrap.getBoundingClientRect().top/scrollable));
  a.goToAndStop(p*(a.totalFrames-1),true); }
addEventListener('scroll',()=>requestAnimationFrame(upd),{passive:true});
```
Respect `prefers-reduced-motion` (skip the scrub; show the last frame). Self-contained JSON only —
a `.lottie` with embedded data URIs needs no image sidecars; check `assets[].p` for `data:`.

### Autoplay-once Lottie on load (animated logotype)
`loop:false, autoplay:!reduce`; guard `mount.dataset.done`; reduced-motion → `autoplay:false` +
`a.addEventListener('data_ready',()=>a.goToAndStop(a.totalFrames-1,true))`. Keep an **sr-only `<h1>`**
next to the Lottie so the page still has a real heading for SEO/a11y.

### Custom JS carousel (when Advancedslider golden-fails or is too heavy)
```
.stage  →  button.arrow(prev/next)  +  .viewport(overflow:hidden) → .track(display:flex; transition:transform .5s) → img.slide(flex:0 0 100%)  +  .dots
```
`go(i){ track.style.transform="translateX("+(-i*100)+"%)"; …toggle active dot… }` — a dozen lines of
`oxy_js`. Slides can be pre-designed full-bleed images (one `<img>` each). Verify it starts at 0 on a
fresh load (state can look off after in-page interaction).

### mix-blend duotone (brand-tint any photo, no Photoshop)
```css
.duo{background:var(--c-purple); overflow:hidden}
.duo img{mix-blend-mode:luminosity; opacity:.9}   /* luminosity of photo × solid brand color */
```

### Self-hosting a family that ships only 2 weights (e.g. Book + Black)
Map ALL CSS weights onto the two files with weight-range `@font-face`:
```css
@font-face{font-family:'X';font-weight:400 500;src:url(X-Book.otf) format('opentype')}
@font-face{font-family:'X';font-weight:600 900;src:url(X-Black.otf) format('opentype')}
```
Now `font-weight:700` → Black, body `450` → Book. Preload the two primary files in the head tracking
code. Serve from `uploads/…` and reference by URL (not the media library).

### Reusable, editable "logo cloud" component via ACF (closes the ACF image-repeater gap)
Content-managed logo grid, rendered once, reused on many pages — the ACF **image + repeater loop**
shape (previously an unshaped coverage gap), now verified:
```php
// mu-plugin
add_action('acf/init', function(){
  acf_add_options_page(['page_title'=>'Logos','menu_slug'=>'brand-logos','capability'=>'edit_posts']);
  acf_add_local_field_group(['key'=>'group_logos','title'=>'Logos','fields'=>[[
    'key'=>'field_logos','name'=>'company_logos','type'=>'repeater','layout'=>'block',
    'sub_fields'=>[
      ['key'=>'field_logo','name'=>'logo','type'=>'image','return_format'=>'array'],  // array → ['url'],['alt']
      ['key'=>'field_faded','name'=>'faded','type'=>'true_false','ui'=>1],
    ]]],
    'location'=>[[['param'=>'options_page','operator'=>'==','value'=>'brand-logos']]]]);
});
function render_logos(){
  $rows = function_exists('get_field') ? get_field('company_logos','option') : null;
  if(!$rows){ /* bundled fallback: loop known files */ }
  $o='<div class="logocloud__grid">';
  foreach((array)$rows as $r){ $img=$r['logo']; $url=is_array($img)?$img['url']:wp_get_attachment_url($img);
    $o.='<div class="logocloud__item'.(!empty($r['faded'])?' is-faded':'').'"><img src="'.esc_url($url).'" alt="'.esc_attr($img['alt']??'').'" loading="lazy"></div>'; }
  return $o.'</div>';
}
```
Page node: `oxy_php('<?php if(function_exists("render_logos")) echo render_logos(); ?>', ['logocloud'])`.
**Seed** = for each file `wp_insert_attachment($att,$path)` + `wp_generate_attachment_metadata` +
`update_field('company_logos', array_map(fn($id)=>['logo'=>$id], $ids), 'option')`. Keep a bundled
file-URL fallback so it renders before the repeater is populated. Blessed PhpCode-for-data pattern;
one edit in wp-admin updates every placement. (For text-only clouds prefer native elements; a logo
grid IS data-shaped, so PhpCode is correct here.)

## Nav: auto-hide on scroll + accessible mobile overlay (PhpCode WP-menu nav)
The dynamic PhpCode nav (WP menu → pill links) extends to a production nav pattern:
- **Markup** (one PhpCode node renders both): desktop pill links wrapped in
  `<div class="nav__links">` (`display:contents` so the pill's flex still applies) + a
  `<button class="nav__burger" aria-expanded="false" aria-controls="mnav">` with 3 `<span>` bars,
  plus a separate full-screen `<div class="mnav" id="mnav"><nav>big links</nav></div>`.
- **CSS:** burger `display:none` on desktop; `@media(max-width:860px){.nav__links{display:none}
  .nav__burger{display:flex}}`. Overlay: `position:fixed;inset:0;opacity:0;visibility:hidden;
  pointer-events:none;transition:…` → `.is-open` flips all three (`visibility` keeps closed-menu
  links out of the tab order). `html.menu-open{overflow:hidden}` locks scroll. Burger bars animate
  to an X via `[aria-expanded="true"] span:nth-child(n){transform:…}`.
- **JS:** toggle sets `aria-expanded` + `aria-label` (Abrir/Cerrar), focuses first link on open /
  burger on close, closes on Escape and on link click.
- **Auto-hide:** `.nav{transition:transform .3s}` `.nav--hidden{transform:translateY(-120%)}`;
  scroll listener (passive) hides when `y>lastY+6 && y>140`, shows when `y<lastY-6`; skip while
  the overlay is open. Feels premium, costs ~10 lines.

## Art-directed responsive slider images (`<picture>` in a flex track)
When mobile needs a DIFFERENT crop (not just a smaller file), wrap each slide:
`<picture><source media="(max-width:640px)" srcset=".../mobile/slide.jpg"><img class="slide" …></picture>`.
Two traps: (1) the `<picture>` becomes the flex child — move `flex:0 0 100%` from the img class
to `.track picture{…}`; (2) the aspect-ratio changes per breakpoint
(`.slide{aspect-ratio:16/9}` + `@media(max-width:640px){.slide{aspect-ratio:9/16}}`).
Slider JS that counts `querySelectorAll('.slide')` keeps working (class stays on the img).
Slides missing a mobile export just omit the `<source>` (desktop fallback).

## Portable parallax plugin + native entrance animations (the scroll-effects stack)
**The plugin SHIPS in this repo: `plugins/oxs-parallax/`** — copy to `wp-content/plugins/`, activate,
add the class `oxs-plx` to any element. Full instructions in its `readme.md`; executable test
protocol in its `test.html`.
Verified split (2026-07-21): reveals = NATIVE entrance animations (PROPERTIES §Entrance
animations — builder-editable, deps auto-load per page); continuous parallax = a tiny portable
plugin, since Oxygen has no native equivalent:
- Marker class (`oxs-plx`) + config via CSS custom properties (`--plx-from/--plx-to` LENGTHS)
  — the same channel Oxygen Variables compile to, so builder-side tuning is free.
- Primary engine: `@supports(animation-timeline:view())` keyframes animating the **`translate`
  property** (never `transform` — coexists with existing `rotate()` on decoratives).
- Fallback: `@supports`-gated rAF + IntersectionObserver (cached bounds, `will-change` only
  while in view), writing `el.style.translate` — symmetric semantics with the keyframes.
- `prefers-reduced-motion` disables both. Ship a `test.html` exercising precedence/rotate/
  fallback/reduced-motion. MUST-READ trap: §overflow-hidden-kills-view-timeline.
- **Modes** (v1.2, global option + per-element override): the plugin ships a Settings → Parallax
  radio writing a `<html>` class (`oxs-plx-mode-off|scroll|oneoff`, emitted in an early `<head>`
  script before paint). **off** = default on fresh installs (nothing animates until opted in);
  **scroll** = scrubbed-to-scroll, reverses on scroll-up (CSS `view()` timeline / rAF fallback);
  **one-off** = IntersectionObserver adds `.oxs-plx--in` once → CSS transition reveals from the
  offset then holds (no rewind). Per element, `.oxs-plx--off/--scroll/--oneoff` beats the global
  default. `off` being default means dropping the plugin on a new site changes nothing until an
  admin flips it — the safe portable default. (`--grow` is scroll-only; `--zoom` works in both.)
- **Scale channel** (v1.1): the same keyframes also animate the `scale` property from
  `--plx-scale-from/--plx-scale-to` (unitless, default 1→1 = off). Preset `--zoom` (1.18→1,
  no translate) gives full-bleed hero images/videos a Ken-Burns pull-back. Put the class ON
  the media tag, NEVER its unpositioned wrapper (a transform there becomes the abs-positioned
  child's containing block and collapses inset:0). Chain overflow:clip on the hero.
- **Stagger reveals**: pair with native entrance animations — same type, incremental delay per
  sibling (PROPERTIES §Entrance animations); reverse the index order for a backward wave.
- **CODE-RENDERED items** (PhpCode/HTML loops — logo grids, slider cards) can't ride the native
  per-element runtime. Piggyback the GSAP+ScrollTrigger that Oxygen already INLINES on any page
  using entrance animations: `gsap.from(items,{opacity:0,y:18,stagger:.06,scrollTrigger:{trigger:
  grid,start:'top 85%',once:true}})`. Rules: guard `window.gsap && window.ScrollTrigger` (absent
  on pages without entrance anims → items just render visible — fail-safe by construction, since
  gsap.from means no CSS ever hides them); retry init at window `load` (the inlined deps execute
  late); respect reduced-motion; and if the wrapper node had its own entrance animation, REMOVE
  it (double-animation).
- **Reusable Global Block "Component" (draggable, editable) + word-by-word write-on quote**:
  A Global Block is just a post of type `oxygen_block` whose `_oxygen_data` holds a normal tree
  (same `{tree_json_string}` double-encoding as pages — verified). Create it with `wp_insert_post
  (['post_type'=>'oxygen_block','post_status'=>'publish'])` then `oxy_write_tree($id, ...)`; register
  + promote its classes like any page. It appears in the builder's **Components** panel, is draggable
  onto any page, and every element stays individually editable (`validate-tree.php <id>` → VALID).
  This is the robust "reusable component" path. ⚠ Do NOT hand-author Component *Properties*
  (per-instance text overrides via `ComponentData{componentId,targets,properties}`): the front-end
  override hook (`Breakdance\Components\setCurrentComponent`) is defined but never invoked in the
  render pipeline, so there's nothing to golden-sample and no builder to generate a reference —
  blind authoring hits the io-ts/builder-visibility trap. For distinct-text instances, duplicate the
  block. **Write-on animation** (portable, class-driven, in global JS): split a `.bt-writeon`
  element's text into `.bt-writeon__w` word spans, then a `gsap.timeline({scrollTrigger})` reveals
  words (stagger) → an optional sibling `.bt-writeon__line` (scaleX draw) → optional `.bt-writeon__by`
  author (fade-up). `gsap.from`/`timeline.from` = fail-safe (no gsap → text just shows). Any element
  with these classes animates — build a quote in the builder with them, no code needed.
Build-script helpers: `bt_entrance($node,$type)` sets the native animation property;
`bt_plx($node,$preset)`-style helper appends the `oxs-plx` marker classes — wrap any tree node.
