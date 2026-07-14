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
markup; drop the reference CSS into ONE `CssCode` node and JS into ONE `JavaScriptCode` node in a
GLOBAL template (we use footer #15) so they apply site-wide.
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
