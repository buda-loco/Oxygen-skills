# Exact write-shapes (tree, selectors, Global Settings, templates)

Everything here is verified against the live plugin (v6.1.0). When a shape isn't listed,
DON'T guess: `oxy_golden('<Slug>')` (scripts/lib.php) returns the builder's own
`defaultProperties`/`defaultChildren` for any element — copy and override.

## Page tree (`_oxygen_data`)

Write exactly like the builder's Save (or just use `oxy_write_tree()` which does all of this):
```php
$prefix = \Breakdance\BreakdanceOxygen\Strings\__bdox('_meta_prefix'); // '_oxygen_'
\Breakdance\Data\set_meta($id, $prefix.'data', ['tree_json_string' => $json]); // JSON *string* inside
\Breakdance\Render\generateCacheForPost($id);   // builds render + CSS cache
```
- Key is `_oxygen_data` (oxygen mode), NOT `_breakdance_data`. Raw `update_post_meta` with a PHP array
  does NOT work — must be `set_meta` (it wp_json_encodes + wp_slashes). Renderer reads back via
  `\Breakdance\Data\get_tree($id)` (`false` = key/shape wrong).

Tree JSON (io-ts facts verified live — see GOTCHAS.md §io-ts):
```json
{ "root": { "id": 1, "data": { "type": "root", "properties": {} }, "children": [ <nodes> ] },
  "_nextNodeId": 162,      // REQUIRED: max node id + 1 — missing = "IO-TS decoding failed"
  "status": "exported" }   // REQUIRED
```
Node:
```json
{ "id": 100,
  "data": { "type": "OxygenElements\\Text",
            "properties": { "content": { "content": { "text": "Hello" } },
                            "meta": { "classes": ["<selector-uuid>"] } } },
  "children": [], "_parentId": 1 }
```
- ids unique non-null ints; every non-root node carries `_parentId` = real parent id; `properties: null` valid.

## Element content keys (most used)

| Element | Key(s) |
|---|---|
| `OxygenElements\Text` | `content.content.text`; tag via `settings.advanced.tag` (span,p,h1–h6,li,blockquote — any string is emitted). Renders `<tag class="oxy-text …">` |
| `OxygenElements\RichText` | `content.content.text` (HTML). Renders inside a wrapper div — never for headings you'll restyle |
| `OxygenElements\Image` | `content.image.*` (NOT `content.content.*`): `from:"media_library"` + `media = {id, url, sizes:{full:{url}}}` + `size:"full"` + `alt` (ENUM `from_media_library`\|`custom`\|`decorative`; custom text → `custom_alt`) + `lazy_load:true`; external = `from:"url"` + `url` (+ `alt_when_from_url`). **srcset/sizes**: renderer prints the PRE-COMPUTED strings `media.attributes.srcset` / `media.attributes.sizes` verbatim — scripted trees must set them (`wp_get_attachment_image_srcset()/_sizes()`) or the img ships bare full-size src (see GOTCHAS §image-srcset). `lazy_load:false` omits `loading` entirely (eager). `settings.advanced.attributes` land on the `<img>` itself → use for `width`/`height`/`fetchpriority` |
| `EssentialElements\Button` | text `content.content.text`; link `content.content.link = {type:'url', url:'…'}` — BOTH keys or it renders a non-navigating `<button>` (macro: `isButton = url empty AND type not in [lightbox,contact,action]`). Renders `.bde-button > a.button-atom.button-atom--primary.bde-button__button > span.button-atom__text` |
| `EssentialElements\FrequentlyAskedQuestions` | `content.settings.items = [{question, answer:"<p>…</p>", button?}]` (mirror to `settings.questions` for the builder control); `settings.accordion` bool, `settings.first_tab_opened` bool. Renders `.bde-frequently-asked-questions > .bde-faq__item > button.bde-faq__question(.bde-faq__title,.bde-faq__icon) > .bde-faq__answer > .bde-faq__answer-content` |
| `OxygenElements\Html5Video` | self-hosted `<video>`. `content.content.video_file_url = {url}` (add `id` for a media-library file); booleans `content.content.{autoplay, loop, muted, controls, plays_inline}` (→ `playsinline`). **No poster control**, but `<video>` is the element's root tag, so a custom attribute works: `settings.advanced.attributes = [{name:'poster', value:URL}]` (verified rendering; poster also persists for prefers-reduced-motion visitors whose video never plays). htmlTag is `video`; empty `html.twig` (attrs built in PHP). `defaultProperties:false` → build the node directly. (The `EssentialElements\Video` element is oembed/YouTube-oriented — use Html5Video for self-hosted mp4.) |
| `OxygenElements\ContainerLink` | wraps `%%CHILDREN%%` in `<a>`. href ← `content.content.url` (string); target ← `content.content.open_in_new_tab` (bool). Set `content.content.link={type:'url',url}` too so the builder link-field populates. **Use this, not `WrapperLink`** (GOTCHAS §wrapper-link-href) |
| `OxygenElements\PhpCode` | `content.content.php_code` — **MUST start with `<?php`** or it's printed as literal text (GOTCHAS §phpcode-open-tag). Runs on the front-end request in full WP/WC context |
| `OxygenElements\HtmlCode` | `content.content.html_code` |
| `OxygenElements\CssCode` | `content.content.css_code` — compiled+minified into `uploads/oxygen/css/post-<id>.css` (linked file, not inline) |
| `OxygenElements\JavaScriptCode` | `content.content.javascript_code` — wrapped in DOMContentLoaded, before `</body>` |
| `EssentialElements\Div` | renders `<div class="bde-div … your-classes">` (see GOTCHAS.md §bde-div cascade) |
| `EssentialElements\Advancedslide` | background image NATIVELY in `properties.design.background.layers.breakpoint_base = [{type:'image', image:{id,url,sizes:{full:{url}}}, background_size:'cover', background_position:{x:50,y:50}}]` — renders on inner `.advanced-slider__slide` (see RECIPES.md §slider) |
| `EssentialElements\Advancedslider` | Swiper options NATIVELY in `properties.design.slider.settings` (verified against `breakdance-swiper.js`, which receives this object verbatim): `autoplay:"enabled"` and `infinite:"enabled"` are **STRINGS not booleans**; `speed:{number:800}` (transition ms) and `autoplay_settings.speed:{number:5500}` (delay ms) are `{number}` values; `autoplay_settings.{pause_on_hover,stop_on_interaction}` booleans; `effect:"slide"\|"fade"\|"coverflow"\|"flip"`; pagination type in `design.slider.pagination.type`. Engine auto-disables autoplay under `prefers-reduced-motion` AND in the builder — no custom JS needed. Also native: `design.slider.arrows.{disable,color,size,overlay,custom_icons}`, `design.slider.settings.advanced.{slides_per_view,between_slides,one_per_view_at,…}`, `design.container.height` (`fit-content`\|`viewport`\|`custom`+`custom_height`) — CSS used for the hero's arrow/dash styling by choice, but these exist for panel-editable variants |
| `EssentialElements\FormBuilder` | see RECIPES.md §forms — copy the golden shape, override `content.form.fields[]`, messages, `content.actions.email.emails[0].to` |
| `OxygenElements\PostsLoop` | **golden-sampled 2026-07-17 (6.1.0) + verified against ssr.php.** Query: `content.query.query = {active:'custom', custom:{source:'post_types', postTypes:['df_collection',…], postsPerPage:8, orderBy:'date'\|'menu_order'\|…, order:'DESC', conditions:[[[]]], offset:null, ignoreStickyPosts:false, ignoreCurrentPost:false, date:'all', acfField:null, metaboxField:null}, text:'post_type=post' (active:'text' mode), php:'return […];' (active:'php' mode)}`. **Per-item card = a referenced GLOBAL BLOCK**, not children: `content.repeated_block.global_block = <oxygen_block ID>` (rendered per post wrapped in `content.repeated_block.tag` default `article`; the_post() is set so dynamic bindings inside the card resolve per-post). Extras: `repeated_block.advanced.when_empty` (fallback block id), `.advanced.alternates`/`.static_items` = `[{repeat, global_block, position, frequency}]`. Element has NO defaultChildren; `defaultProperties` only carries accordion-icon cruft (safe to omit). Pagination `content.pagination`, filter bar `content.filter_bar` (unshaped — golden-sample if needed). **Output markup** (verified): `.bde-post-loop > .bde-loop > article.bde-loop-item > <card block>` — flex/grid parents styled for direct children need `display:contents` on all three wrappers (§code-node-wrapper family). **php query mode is the meta-filter workhorse**: `{active:'php', php:"return ['post_type'=>…, 'meta_key'=>'featured', 'meta_value'=>'1'];"}` — full WP_Query args, still builder-editable. **Two more verified facts (2026-07-17)**: on any archive (search included) a loop with NO `content.query` key consumes the MAIN query (`query-control.php: isAnyArchive() && !props → $wp_query->query_vars`) — that IS the search-results mechanism; and `repeated_block.advanced.when_empty = <block ID>` renders the fallback block on zero results (verified live) |
| **Dynamic data binding** (any text-bearing element) | **golden-sampled 2026-07-17.** `content.content.text = "[breakdance_dynamic field='acf_field_<ACF_FIELD_KEY>']"` plus the builder-UI mirror `content.content.text_dynamic_meta = {field:'acf_field_<KEY>', shortcode:'<same shortcode>', attributes:[]}`. ACF fields use the `acf_field_` prefix + the field KEY (`field_df_col_place`), NOT the field name. Core fields use the plain ids the skill already documents (`[breakdance_dynamic field='…']`). Resolves per-post inside a PostsLoop card block. **The shortcode resolves in NON-text string props too** (verified live): ContainerLink `content.content.url` → per-post permalinks; Image url-mode `content.image.url` + `custom_alt_when_from_url` → per-post cover/alt (`post_permalink`, `post_featured_image_url` full-size, no srcset in url mode). One dynamic card block = fully dynamic link+image+text |

Two ways to attach classes to a node:
- **`properties.meta.classes`** = selector **UUIDs** → renderer emits each selector's `name`
  (native selector/design-panel system; `getElementOxySelectors`).
- **`properties.settings.advanced.classes`** = plain class-name **strings**, emitted verbatim
  (for CSS you wrote yourself, e.g. the embedded reference stylesheet; `getAppliedClassNames`).

## Selectors ("classes", global styling)

Persist like the builder (or `oxy_save_selectors()` which MERGES by name instead of replacing all):
```php
\Breakdance\BreakdanceOxygen\Selectors\saveSelectors(
    json_encode(['selectors' => $selectors, 'collections' => ['Default']])
); // persists oxygen_oxy_selectors_json_string + revision + generateCacheForGlobalSettings()
\Breakdance\Render\generateCacheForPost($id); // then rebuild each affected page
```
Selector object:
```json
{ "id": "<uuid>", "name": "hero-title",   // NO leading dot
  "properties": { "breakpoint_base": { <groups> } },
  "children": [], "locked": false, "collection": "Default", "type": "class" }
```

### Property groups (under `breakpoint_base`)
Authoritative compiler: `plugin/breakdance-oxygen/selectors.twig` — emits every property then strips
empties, so only set what you want. Numeric values are `{number,unit,style}` (`style` = the CSS string,
e.g. `"44px"`); colors/keywords are plain strings; `font_weight` is a plain number.

- **typography**: `color`, `font_family` (FONT SLUG — see Global Settings), `font_weight`, `font_size{}`,
  `line_height{}` (unitless → `{"number":1.5,"unit":"","style":"1.5"}`), `letter_spacing{}` (negatives ok),
  `text_align`, `text_transform`, `style.text_decoration`, `style.font_style`
- **background**: `background_color` (string); `backgrounds[]` layers (order = top→bottom, each may
  have `"disabled":true`):
  - color overlay `{"type":"color_overlay","color":"rgba(0,0,0,.5)"}`
  - gradient `{"type":"gradient","gradient":{"value":"linear-gradient(…)"}}` (minimal)
  - image `{"type":"image","image":{"id":123,"url":"…","sizes":{"full":{"url":"…"}}},"background_size":"cover","background_position":{"x":50,"y":50}}`
    (minimal = `image.url`; x/y plain numbers = %; external: `image.{id:-1,type:"external_image",url}`)
- **spacing**: `spacing.spacing.padding.{top,right,bottom,left}{}`, `.margin.{...}{}`
- **borders**: `border_radius.all{}` (or `editMode:"custom"` + per-corner); per-side
  `borders.borders.left = {width{}, style:"solid", color:"#0f766e"}` (an accent section bar)
- **layout**:
  - `display`: raw CSS keyword — `"flex"|"grid"|"block"|"none"|"inline-flex"|"inline-grid"|"inline-block"|"inline"|"contents"`
  - `flex_direction`: the CSS `flex-flow` SHORTHAND — `"row"`, `"column"`, `"row-reverse wrap"`, …
  - `flex_align.{primary_axis,cross_axis}`: primary→justify-content, cross→align-items —
    SHORT box-alignment keywords (`"start"|"center"|"end"|"space-between"|"space-around"|"stretch"`), NOT flex-start/flex-end.
    (Grid: `grid_align.{…}` + `grid_justify_content`, same keywords.)
  - `gap` (flex AND grid): `{"row":{n,u,style},"column":{n,u,style}}`
  - `grid` simple N-col: `display:"grid"` + `grid.simple_grid_template_columns` = **plain number** → `repeat(N,1fr)`;
    `simple_grid_template_rows` plain number. (Advanced: `grid.enable_advanced_mode=true` + `grid_template_columns[].size.style`.)
- **size**: `width{}`, `max_width{}`, `min_height{}`, `height{}`;
  `aspect_ratio` = raw ratio string WITH spaces (`"16 / 9"`, `"1"`) or `"custom"` +
  `aspect_ratio_custom={"width":16,"height":9}` (plain numbers); `object_fit` = `"fill"|"contain"|"cover"|"none"|"scale-down"`
- **effects**: `opacity`, `box_shadow[]`, `transition[]`, `cursor`
- **position**: `position` (keyword), `top{}`/`right{}`/`bottom{}`/`left{}` offsets, `z_index` (plain
  number) — verified live (absolute-cover overlays, z-stacking from the selector)
- **custom_css**: `custom_css.custom_css` = raw CSS string with `:selector` placeholders, emitted
  VERBATIM after the class rule — pseudo-elements, `:hover`, descendant rules, inline `@media` all
  work (GOTCHAS §specificity ladder for when you NEED it and which prefix wins)

Worked example — compiles to `.btn{background:#0f766e;color:#000;text-transform:uppercase;letter-spacing:2px;padding:14px 30px;border-radius:0}`:
```json
{ "id":"…", "name":"btn", "type":"class", "collection":"Default", "children":[], "locked":false,
  "properties":{ "breakpoint_base":{
    "background":{"background_color":"#0f766e"},
    "typography":{"color":"#000","font_weight":500,"text_transform":"uppercase","text_align":"center",
                  "letter_spacing":{"number":2,"unit":"px","style":"2px"}},
    "spacing":{"spacing":{"padding":{"top":{"number":14,"unit":"px","style":"14px"},
      "bottom":{"number":14,"unit":"px","style":"14px"},"left":{"number":30,"unit":"px","style":"30px"},
      "right":{"number":30,"unit":"px","style":"30px"}}}},
    "borders":{"border_radius":{"all":{"number":0,"unit":"px","style":"0px"}}} }}}
```

> Node-level design (`node.data.properties.design.<group>`) uses the SAME group shapes but is
> inconsistent about the `breakpoint_base` wrapper (Section padding wraps it; Column
> `design.size.width = {unit:"%",number:50,style:"50%"}` doesn't). Prefer SELECTORS (always
> `breakpoint_base`) — uniform and verified. Exception: Advancedslide background layers (above).

### Responsive breakpoints (per-breakpoint overrides)
Built-in breakpoint ids — verified from plugin source (`plugin/config/builtin-breakpoints.php`,
v6.1.0); Oxygen is **desktop-first** (base = no media query, others compile to `max-width`):

| id | Builder label | max-width |
|---|---|---|
| `breakpoint_base` | Desktop | — (base) |
| `breakpoint_tablet_landscape` | Tablet Landscape | 1119px |
| `breakpoint_tablet_portrait` | Tablet Portrait | 1023px |
| `breakpoint_phone_landscape` | Phone Landscape | 767px |
| `breakpoint_phone_portrait` | Phone Portrait | 479px |

Per-breakpoint values sit as **sibling keys of `breakpoint_base`** in the same object — e.g. element
defaults use `slides_per_view: {breakpoint_base: 4}` and options like
`one_per_view_at: 'breakpoint_phone_landscape'`. For selector property groups the same pattern applies
(`properties: {breakpoint_base: {...}, breakpoint_phone_landscape: {...}}`) — **verified live at scale
2026-07-20** (grid column counts, gaps, min-heights compiled into the right max-width queries during a
full component-library conversion; write them via `oxy_selector(...,$breakpoints)`). Custom breakpoints
can be added in the builder's preferences; ids follow the same `breakpoint_*` convention.

## Global Settings (brand: colors, fonts, section width)

Option `oxygen_global_settings_json_string` is **double-encoded** — write like the builder:
```php
$s = ['settings' => [ /* colors, typography, containers */ ], 'builderPrefix' => ''];
\Breakdance\Data\set_global_option('global_settings_json_string', wp_json_encode($s));
\Breakdance\Render\generateCacheForGlobalSettings();
```
- **Reading it back** (audits/scripts): `get_option('oxygen_global_settings_json_string')` returns the
  outer JSON string — `json_decode` **TWICE** to reach `settings` (one decode yields a *string*, not the
  object). Decoding once and testing `['settings']` gives a false "empty / not configured" (burned us
  2026-07-14 — the palette *is* configured).
- **Palette swatches** (show in every picker): `settings.colors.palette.colors[]` =
  `{id:uuid, cssVariableName:"bde-color-accent" (no --), label:"Accent", value:"#0f766e"}` → emits
  `--bde-color-accent`; reference from any color property as the string `"var(--bde-color-accent)"`.
  Also `settings.colors.{brand,headings,text}`.
- **Fonts**: bundled Google list is pre-registered. `settings.typography.heading_font`/`body_font` =
  the **slug** (`gfont-` + lowercased family, non-alphanumerics stripped: Inter → `gfont-inter`,
  Oswald → `gfont-oswald`). `process_font(slug)` resolves the family AND
  auto-enqueues the Google `<link>`. Class `typography.font_family` also takes the SLUG.
  `base_size{}` + `ratio` (number) drive the h1–h6 scale; optional per-heading override:
  `settings.typography.advanced.headings.h1.typography.custom.customTypography.fontSize.style`.
- **Sections**: `settings.containers.sections.{container_width,horizontal_padding,vertical_padding}`
  (each `{number,unit,style}`), `settings.containers.column_gap` — the native way to cap content width
  (inner `.section-container` uses `--bde-section-width`; content aligns LEFT by default).

### Site-wide raw head/footer code
`\Breakdance\Data\set_global_option('breakdance_settings_tracking_code_header', $rawHtml)` → verbatim
into `<head>` on every page; `…_tracking_code_footer` → before `</body>`. Read back with
`get_global_option` (stored as `oxygen_<field>`). Good for a global font `<link>` / `:root` var
override / analytics; the Global Settings font system supersedes it for fonts.

## Templates (`oxygen_template` posts)

- Post type `oxygen_template`, `post_status publish`. Shows in Templates admin
  (`admin.php?page=oxygen_template`; siblings: `oxygen_header`, `oxygen_footer`, `oxygen_popup`).
- Location meta `_oxygen_template_settings` — **a JSON STRING, not an array** (resolver does
  `json_decode((string) get_meta(…))`; an array becomes `"Array"` → null → template silently ignored;
  unlike `_oxygen_data` which IS array-shaped). Use `oxy_template_settings()` from lib.php.
- **Type slugs** (`plugin/themeless/rules/`): a single of ANY public post type uses **that post type's
  own slug** as the type (callback `is_singular($slug)`) — `product` (PDP), **`post` (single blog post)**,
  `page` (any single page). `all-singles` = `is_singular()` (any — too broad, avoid). Archives:
  `all-product-archives` (`is_shop() || is_product_taxonomy()`), `all-archives`. Others: `everywhere`,
  `search` (`is_search`), `404` (`is_404`), `front-page` (`is_front_page`), `specific-product-archive`.
  Empty `ruleGroups` = applies whenever the callback is true. **Priority > 20 beats the built-in fallbacks.**
  - A post-type-specific single template (`post`) NEVER matches other post types (a `post` template can't
    hijack `page` content) — the safe way to template blog posts without touching content pages.
  - WC system pages (cart/checkout/account) are better handled as **per-page trees**, not templates
    (RECIPES §WC system pages) — no priority/hijack risk. Coverage checklist: RECIPES §Site template-coverage.
- **Inert design-library template**: settings `{"type":"everywhere","ruleGroups":[],"priority":N,"disabled":true}` —
  `doesTemplateApply()` returns false immediately when `disabled === true`, so it's listed +
  builder-editable but never applies. ⚠ Enabling `type:everywhere` + empty ruleGroups would hijack
  EVERY page. Templates have no public URL (404) — preview by copying the tree onto a throwaway page.
- Convert a page: `wp_update_post(['ID'=>$id,'post_type'=>'oxygen_template'])` + set settings meta.

## Variables (Oxygen 6 Variables feature) — GOLDEN-SAMPLED 2026-07-19
Stored in option **`oxygen_variables_json_string`** — a SINGLE-encoded JSON array (NOT double-encoded
like global settings). Collections list in `oxygen_variables_collections_json_string` (e.g. `["Collection 1"]`).
Each variable:
```json
{ "id":"<uuid>", "type":"unit", "label":"sp-5", "cssVariableName":"sp-5",
  "collection":"Collection 1", "value":{"number":20,"unit":"px","style":"20px"} }
```
- `cssVariableName` emits `--<name>` (no leading `--`). `type`: `unit` (value `{number,unit,style}`),
  also `color`/`number`/`fontfamily`/`imageurl` (shapes not yet captured — golden-sample before use).
- Write: `update_option('oxygen_variables_json_string', wp_json_encode($arr))` then
  `\Breakdance\Render\generateCacheForGlobalSettings()`. Emits a `:root{--name:val}` block in the compiled CSS.
- ⚠ Still to capture: how a SELECTOR/element design property REFERENCES a variable (the property-group
  value shape when a control is bound to a variable). Golden-sample an element with a spacing bound to a
  variable, then read its `design`/selector property group.
