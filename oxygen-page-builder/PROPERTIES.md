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
  - ⚠ **`opacity` scales ONLY when it matches `^\d+$`.** `selectors.twig` branches: a bare run of
    digits (int OR numeric string) is treated as a 0–100 percentage and emitted as `value / 100`;
    anything else — decimals, `var()` — is emitted VERBATIM (the else branch is commented
    `{# css variables #}`). **oxy_probe'd on 6.1.1, one probe per process:**

    | written | emitted | |
    |---|---|---|
    | `100` | `opacity:1` | fully opaque |
    | `50` / `'50'` | `opacity:0.5` | int and numeric-string both scale |
    | `0.5` / `'0.5'` | `opacity:0.5` | decimal passes through — also correct |
    | `'var(--fade)'` | `opacity:var(--fade)` | passes through |
    | **`1`** | **`opacity:0.01`** | **the trap** |

    So the danger is NOT the decimal form (that works). It is writing `1` meaning "fully opaque" and
    getting **1%** — an element that vanishes while the property looks perfectly set in the panel and
    in the compiled CSS. Fully opaque is `100`. 6.2-beta2's "normalize imported CSS opacity to the
    builder's 0-100 percentage format" presumably folds the verbatim branch into the scaled one;
    re-probe on 6.2 before relying on decimals continuing to pass through.
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

## Dynamic data across a RELATIONSHIP (ACF post_object → related post's image)
Oxygen ships no native hop for a related post's FEATURED IMAGE: `AcfPostField extends PostField`
reaches a related post but its handler only understands `post_field` (post_title, post_date,
post_terms, `custom_field`+`custom_field_key`, or any WP_Post property) — and a featured image is
not one; `PostFeaturedImageURL` resolves through `get_the_ID()`, i.e. the current loop post.
Hence the old "one PhpCode leaf per relationship" workaround.

**Register a field instead** — then any native element binds to it like a built-in, and the card
keeps zero code nodes (rule 2). Copyable implementation:
`assets/mu-oxygen-dynamic-related.php`. Shape:
```php
add_action('wp_loaded', function () {                       // fields collect on wp_loaded
    if (!function_exists('\Breakdance\DynamicData\registerField')) return;   // guard: Oxygen off
    \Breakdance\DynamicData\registerField(new class extends \Breakdance\DynamicData\StringField {
        public function label()       { return 'Related featured image (URL)'; }
        public function category()    { return 'Relation'; }
        public function slug()        { return 'related_featured_image_url'; }
        public function returnTypes() { return ['url']; }
        public function controls()    { return [\Breakdance\Elements\control('relation_field',
            'ACF field name', ['type'=>'text','layout'=>'vertical'])]; }
        public function handler($attributes): \Breakdance\DynamicData\StringData { /* … */ }
    });
}, 20);
```
Use: `[breakdance_dynamic field='related_featured_image_url' relation_field='company_brand']`.
⚠ Test it with `\Breakdance\DynamicData\renderDynamicShortcodes($str)` — **`do_shortcode()` does
NOT resolve these**; Oxygen has its own parser, and a `do_shortcode` test returns the raw string and
looks like the field failed to register.
⚠ ACF returns an id, a `WP_Post`, or an array of either depending on `return_format`/`multiple` —
accept all three in the handler.

### ⚠ A bound alt that can be empty must go through `advanced.attributes`
`content.image.custom_alt_when_from_url` bound to a relationship resolves to `''` whenever the
relation is unset, and the renderer **omits an empty alt entirely** — shipping an alt-less `<img>`
that fails any a11y audit. A literal attribute is always written, so put the binding there:
```php
'settings' => ['advanced' => ['attributes' => [[
    'name' => 'alt', 'value' => "[breakdance_dynamic field='related_post_title' …]"]]]]
```
Empty then lands as `alt=""` (correct for the decorative fallback) and a real value still renders.
The renderer resolves `[breakdance_dynamic` inside ANY string property, attributes included.
Related: an Image bound to an empty URL does not render nothing — the placeholder layer swaps in an
inline-SVG **data: URI**, so hide it with `img[src^="data:image"]{display:none}` rather than
`img[src=""]`, which never matches.

## Interactions (click / hover / scroll triggers → class, show/hide, …) — VERIFIED 2026-08-06
The builder-editable alternative to a hand-rolled JS node. Lives on ANY element at
`settings.interactions.interactions` (an array of rows), renders as a `data-interactions` JSON
attribute, and the runtime (`breakdance-interactions@1`) is injected only on pages that use one.
Click-toggles-a-class verified end-to-end in a real browser (off → on → off).

```php
$node['data']['properties']['settings']['interactions']['interactions'] = [[
  'trigger' => 'click',                     // ROW target = where the LISTENER attaches;
                                            // omit for "this element" (the common case)
  'actions' => [[
    'name'         => 'toggle_class',       // ACTION target = what the action AFFECTS
    'css_class'    => 'is-open',
    'target'       => 'custom',             // 'custom' | 'target' | omit = the triggering element
    'css_selector' => '.my-panel',
  ]],
]];
```
⚠ **The two `target`/`css_selector` pairs are different things and swapping them fails silently** —
a row-level `target:'custom'` attaches the listener to the *other* element, so nothing responds to
the click. Resolver: `target==='custom'` → query `css_selector`, `'target'` → the trigger's element,
anything else → the element itself.

**Triggers** (`plugin/interactions/triggers/`): `click`, `mouse_enter`, `mouse_leave`,
`mouse_leave_window`, `mouse_move_in_viewport`, `scroll_into_view`, `scroll_out_of_view`,
`page_loaded`, `page_scrolled`, `key_down`, `key_up`, `form_submit`, `tab_change`, `slider_change`,
`visibility_change`, `dropdown_menu_opens`, `mobile_menu_opens`.
**Actions** (`plugin/interactions/actions/`, names read off the runtime's actionMap):
`add_class`, `remove_class`, `toggle_class`, `show_element`, `hide_element`, `toggle_element`,
`set_attribute`, `remove_attribute`, `toggle_attribute`, `set_variable`, `scroll_to`, `focus`,
`blur`, `start_animation`, `control_popup`, `control_slider`, `javascript_function`
(`js_function_name`, looked up on `window`).
Per-action option keys come from each action's `controls()` (e.g. `css_class` for the class actions).
`action.advanced.disable_at = ['phone_portrait', …]` skips the action at those breakpoints.
The selector supports `{index}` / `{parent_index}` templating for loop contexts.
**Prefer this over a JavaScriptCode node** for show/hide, tabs, toggles and reveals: the user can
edit the whole behaviour in the builder panel, which a code node never allows (project rule 2).

## Component Properties (per-instance overrides) — VERIFIED WORKING 2026-08-06
**Supersedes the old "unusable, duplicate the block instead" note.** Two instances of one
`oxygen_block` CAN render different content in Oxygen 6.1.0. It takes BOTH halves — the override is
gated on the block declaring the property editable, which is why a targets-only attempt renders the
base text on every instance and looks broken:

**1. In the BLOCK's tree** — mark the property editable on the node that owns it:
```php
$node['data']['properties']['meta']['component']['editableProperties'] = [
  ['propertyKey' => 'title', 'controlPath' => 'content.content.text',
   'label' => 'Título', 'enabled' => true],   // enabled defaults TRUE when the key is absent
];
```
**2. On each PLACED instance** — point a target at that node id and supply the value:
```php
oxy_el('OxygenElements\Component', ['content' => ['content' => ['block' => [
  'componentId' => $blockId,
  'targets'     => [['nodeId' => $nodeId, 'propertyKey' => 'title',
                     'controlPath' => 'content.content.text']],
  'properties'  => ['title' => 'INSTANCE ONE'],
]]]])
```
`nodeId` is the id the WRITER assigned — read it back from the block's `_oxygen_data`
(`$tree['root']['children'][N]['id']`) after `oxy_write_tree()`; it is not knowable in advance.
`controlPath` is the same dot-path the design panel writes (`content.content.text`,
`content.image.media`, …) and is applied with `assignArrayByPath`, so any depth works.
Render path: `Component/ssr.php` → `ComponentInputValueHolder::setCurrentComponent()` →
`breakdance_before_render_node` filter → `replaceNodePropertiesWithEditedPropertiesFromComponent`
(`plugin/breakdance-oxygen/components.php`). The stack is push/pop, so nested components work.
**What this retires:** page-specific duplicate components purely because one string differs.

### Global Settings → Buttons — VERIFIED write-shape (round-trip + live build 2026-08-06)
`settings.buttons.{primary,secondary}` styles EVERY `Button` element of that style site-wide
(rung 1 — prefer this over per-class `custom_css` button generators when the design fits its keys).
Consumed by `global-styles/buttons/global-buttons.css.twig` via the same `atomV1ButtonButton` macro
the Button element uses; also `settings.buttons.button_presets.button_presets[]` (named presets).
All keys below emission-verified in `global-settings.css` (values may be `var(--…)` or `color-mix(…)`
strings — the macro emits them verbatim, so palette vars keep colours single-source):
```php
'settings' => ['buttons' => ['primary' => [
  'background'       => 'var(--bde-color-accent)',   // → --bde-button-primary-background-color
  'background_hover' => 'color-mix(in srgb, var(--bde-color-accent) 90%, #111)',
  'typography'       => [
    'color'      => '#111',        // flat — sets text-color AND text-color-hover (kills the
                                   // engine's `#ffffff` hover default at the source)
    // ⚠ everything ELSE is NOT flat: the atom macro hands this to the shared ELEMENT
    // typography macro, so it nests like per-heading overrides (camelCase):
    'typography' => ['custom' => ['customTypography' => [
      'fontSize'   => ['number'=>0.875,'unit'=>'rem','style'=>'0.875rem'],
      'fontWeight' => 900,
      'advanced'   => ['letterSpacing' => ['number'=>0.08,'unit'=>'em','style'=>'0.08em'],
                       'textTransform' => 'uppercase'],
    ]]],
  ],
  // ⚠ padding only emits when size.size == 'custom', nested at size.padding.*
  'size' => ['size' => 'custom', 'padding' => ['top'=>[…],'right'=>[…],'bottom'=>[…],'left'=>[…]]],
  'corner_radius' => ['number'=>999,'unit'=>'px','style'=>'999px'],  // NOT `corners` (enum square|round)
  // emitted UNCONDITIONALLY into --bde-transition-duration — set it or ship `: ;`
  'effects' => ['transition_duration' => ['number'=>240,'unit'=>'ms','style'=>'240ms']],
]]]
```
Per-node style pick: `design.button.style = 'primary'|'secondary'|'custom'|'text'` on the Button
element (default primary) → renders `button-atom--<style>` (render-verified). Other macro-read keys,
still unsampled: `color`/`color_hover` (outline mode), `outline`, `no_fill_on_hover`,
`effects.{shadow,shadow_hover,scale_on_hover}`, `size.override_width`, `icon.*`. What stays custom
CSS regardless: focus rings, inset edges, translateY lifts, reduced-motion opt-outs — no controls.
⚠ Verify emission against WHITESPACE-NORMALISED CSS (`preg_replace('/\s+/','',$css)`) — the compiled
file is minified, so needles containing `: ` silently miss. And don't use palette colours as
sentinels; `--bde-color-*` swatch lines match them even when your write failed.

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

## Entrance animations (native AOS) — VERIFIED write-shape (2026-07-21)
Per-element, applies to ANY element; builder panel "Entrance Animation". Minimal payload
(io-ts VALID, front-end verified animating):
```php
$node['data']['properties']['settings']['animations']['entrance_animation'] =
    ['animation_type' => 'slideUp'];
```
`animation_type` ∈ `fade | slideUp | slideDown | slideLeft | slideRight | flipUp | flipDown |
flipLeft | flipRight | zoomIn | zoomOut`. Renders `data-entrance="<type>"`; Oxygen auto-injects
GSAP + ScrollTrigger + `entrance.js/css` + an inline `new BreakdanceEntrance(sel, cfg)` ONLY on
pages that use it (dependency-conditional; scripts are INLINED — don't grep for `<script src>`).
**`delay`, `duration` AND `advanced.ease` write-VERIFIED** (io-ts VALID + runtime consumes them):
`'delay'/'duration' => ['number'=>700,'unit'=>'ms','style'=>'700ms']`; `'advanced' => ['ease'=>'expo.out']`
(ease = plain GSAP string: linear, expo.in/out/inOut, power1-4.in/out/inOut, back.*, elastic.*, bounce.* —
see entrance/constants.php EASING_TYPES). **The runtime DEEP-merges options over its defaults**
(BreakdanceFrontend.utils.mergeObjects), so a partial `advanced` object is safe — distance/offset
defaults survive. **`advanced.once` — VERIFIED (2026-08-06), and you almost always want it `true`:**
`'advanced' => ['once' => true]`. The default is `once:false`, which REVERSES the entrance when the
element scrolls back out (`goToBeginningOnReverse`) — sections re-hide and replay on every pass, and
an at-rest render (top of page, exactly how a crawler or screenshot sees it) shows below-fold text
at opacity 0. impeccable's detector flagged 53% of a page's text as content-hidden-at-rest from this
alone. `disable_at`/`distance` remain write-unsampled — set those in the builder
(source: `oxygen/plugin/animations/entrance/{control,attributes,dependencies}.php`).
**Stagger pattern**: nearby siblings (columns/cards/bubbles) get the same `animation_type` with
incremental delays (e.g. 0/120/240ms); reverse the index for a backward wave. Verified at scale.
**Reduced motion is handled by the runtime** (read from `entrance/js/entrance.js` 2026-08-06): under
`prefers-reduced-motion: reduce`, `init()` adds the completed class and returns — content is fully
visible, nothing animates, no per-element opt-out needed. Also note `autoload()` waits for
`imagesLoaded` before initing — an entrance on an above-the-fold hero delays its paint until images
resolve, so keep entrances BELOW the fold and leave the LCP element unanimated.
**Three more runtime facts that bite (verified on a live build 2026-08-06):**
- `entrance.css` hides pending elements with an UNCONDITIONAL `[data-entrance]{visibility:hidden}`,
  which **removes them from the accessibility tree** — a screen reader can't reach the content until
  the visual viewport scrolls it in, and with JS off it never appears at all. Fix with a small
  override (doubled attribute selector `[data-entrance][data-entrance]` = (0,2,0) beats the engine
  regardless of print order): `{visibility:visible;opacity:0}` + `.is-animating,.is-animated{opacity:1}`
  + a `<noscript>` `{opacity:1}` block. State classes: `is-animating`/`is-animated` (entrance.js:14-15).
- The inline init is `new BreakdanceEntrance('%%SELECTOR%%', …)` and the class resolves it via
  `document.querySelector` — **only the FIRST match animates**. An entrance on a loop-rendered
  global-block card fires on instance 1 and leaves the rest hidden-then-static; put it on the loop's
  WRAPPER instead.
- When grepping rendered HTML for `data-entrance` values, remember types are camelCase (`slideUp`) —
  a `[a-z]*` character class silently drops every slide/flip/zoom match.
- **Ghost inits on Component-rendered nodes (engine artifact, 2026-08-06):** a node inside a placed
  Component gets its `new BreakdanceEntrance('%%SELECTOR%%',…)` inline init emitted TWICE with
  different instance suffixes (`.oxy-container-72-118-72-1` and `…-2`); only one exists in the DOM,
  so the other's `init()` throws `Cannot set properties of null (setting 'bdAnim')` — one uncaught
  TypeError per animated node, every page load. Reveals still work (each init is its own script),
  but the console noise is real. Guard via mu-plugin: patch `BreakdanceEntrance.prototype.init` to
  no-op when `document.querySelector(this.selector)` is null — and register the patch retrying at
  DOMContentLoaded/load, because the class is defined by scripts that may print AFTER wp_footer
  output (init only runs post-imagesLoaded, so the retry is always in time).
- **Detectors/screenshots catch entrances mid-animation:** a contrast scan that screenshots during
  the stagger reads transient opacity as low contrast ("opacity stack" findings). Judge contrast at
  rest; with `advanced.once=true` the at-rest state after any scroll is the final one.

## Custom element plugin — VERIFIED registration (live build, 2026-08-06)
A real element in the builder's **+** panel (own category, own controls) — the packaging for
interactive/data-driven widgets a Component can't parameterise (workflow step 0). One small plugin:
```php
add_action('plugins_loaded', function () {
    \Breakdance\Elements\registerCategory('my-brand', 'My Brand');
    \Breakdance\ElementStudio\registerSaveLocation(
        \Breakdance\Util\getDirectoryPathRelativeToPluginFolder(__DIR__) . '/elements',
        'MyBrand', 'element', 'My Brand', false, true /* excludeFromElementStudio */
    );
}, 5);
```
Three things that must be right or it SILENTLY never loads:
- **Priority < 10.** Oxygen scans registered directories on `breakdance_loaded`, fired from
  `plugins_loaded` at 10. Register later → no error, no element.
- **The glob is `<dir>/*/*.php`** — one folder per element, nothing else in it; every file found is
  `require`d except `*ssr.php`.
- **`excludeFromElementStudio = true` for hand-written files** — opening the element in Element
  Studio regenerates them (ssr.php included).
Element design lives in its `default.css`, scoped `.breakdance .my-el*` = (0,2,0) — beats the theme
and the engine's `.breakdance img{height:auto}`. A wrong element-class slug in a tree makes
`oxy_write_tree` throw AND `wp eval-file` exit 255 with no message — run builds through a runner
that catches `\Throwable`.

## Template condition ruleGroups — VERIFIED 2026-08-07 (closes a coverage gap)
Previously listed under "known coverage gaps". The shape, read off
`themeless/request.php::doesRuleApply()` and confirmed live:

```php
oxy_template_settings($id, 'post-type-archive', 30, false, [[
    ['ruleSlug' => 'post-type-archive', 'operand' => 'is', 'value' => ['zs_project']],
]]);
```

- ⚠ The key is **`ruleSlug`** — NOT `conditionSlug`. The condition *registers* itself under
  `slug`/`conditionSlug`, but the evaluator reads `$rule['ruleSlug']` and returns false for
  anything else. Get this wrong and the rule group silently evaluates false, so the template
  applies **nowhere** (or, with an empty `ruleGroups`, **everywhere**) — both fail quietly.
- Nesting is **rule groups OR'd, rules within a group AND'd**
  (`doesTemplateApply` → `in_array(true, …)`; `doesRuleGroupApply` → `!in_array(false, …)`).
- `count($ruleGroups) < 1` returns **true** — an empty array means "applies whenever the type
  callback is true", which for `post-type-archive` is *every* archive on the site.
- Operands are the literal strings in `themeless/rules/constants.php`: `'is'`, `'is not'`,
  `'is one of'`, `'is all of'`, `'is none of'`, `'is before'`, `'is after'`, …
- Archive type slugs (`themeless/rules/archive/`): `post-type-archive`, `taxonomy-archive`,
  `all-archives`, `post-archives`, `author-archive`, `date-archive`, plus the WC set.
  Priorities in `constants.php`: catch-all 1, all-archive/all-single 10, specific 20.

**Verify targeting, don't assume it.** After writing, curl the intended URL *and* a URL that
should NOT match, and grep for a class only that template emits.

---

# Shapes verified on the Bold & Groovy build (2026-08-07, session 2)

## `OxygenElements\TermLoopBuilder` — the term-side twin of PostsLoop

Not previously shaped. Same `repeated_block` contract as `PostsLoop`; the query differs.

```php
oxy_el('OxygenElements\\TermLoopBuilder', ['content' => [
    'query' => [
        'load_terms_by_query' => true,      // ⚠ MUST be true or `term_query` is ignored
        'hide_empty'          => false,
        'term_query'          => "return ['taxonomy'=>'services','parent'=>0,…];",
    ],
    'repeated_block' => [
        'global_block' => 1001,             // an oxygen_block ID
        'tag'          => 'div',            // article | section | div
        'advanced'     => ['when_empty' => 1002],
    ],
]]);
```

- With `load_terms_by_query` **unset**, the element falls back to the panel dropdowns
  (`content.query.taxonomy`, `.limit`, `.hide_empty`) and **silently ignores the PHP**.
- `term_query` returns `get_terms()` args (not `WP_Query` args).
- Inside the repeated block, the Term dynamic fields resolve per term:
  `term_name`, `term_id`, `term_description`, `term_permalink`, `term_count`,
  `term_custom_field` (takes a `key` attribute).
- ⚠ **Its wrapper class is `bde-term-loop`, not `bde-post-loop`.** Any `display:contents`
  unwrap rule must list both or a term loop lands in one grid cell while a post loop behaves.

## `OxygenElements\DynamicDataLoop` — CANNOT read a repeater on a term

`ssr.php` resolves rows with `$postId = $isOption ? 'option' : get_the_ID()`. There is no term
branch, so an ACF repeater stored on a **term** is unreachable by this element. Don't burn time
shaping it for taxonomy work.

**What to do instead:** ACF flattens repeater rows into ordinary meta
(`process_0_title`, `process_0_description`, `process_1_title`, …), and on a term those are
plain **termmeta**. So a fixed number of rows can be read with ordinary
`term_custom_field` bindings, each row a real selectable node in the builder:

```php
byg_dyn_key_text('term_custom_field', "process_{$i}_title", 'h3', ['step__title']);
```

Cap the ACF repeater at the same number the design shows, and collapse unused rows with
`.step:has(.step__title:empty){display:none}`.

## `EssentialElements\SearchForm`

```php
oxy_el('EssentialElements\\SearchForm', [
    'content'  => ['form' => ['placeholder' => 'Search…']],
    'design'   => ['form' => [
        'style'          => 'classic',      // 'classic' | 'full-screen' (DEFAULT is full-screen)
        'classic_styles' => ['icon_button' => ['type' => 'text', 'text' => 'Search']],
    ]],
    'settings' => ['advanced' => ['classes' => ['sr-form']]],
]);
```

`classic` renders a real `<form role="search" method="get">` with a labelled input
(`.search-form__field`, `name="s"`) and a submit `<button>` — accessible without hand-written
markup. Rendered classes to brand: `.search-form__container`, `.search-form__field`,
`.search-form__button`.

⚠ **The template hardcodes `value=""`**, so the field cannot echo the current query. Nothing in
the panel changes that; prefill it from `?s=` in JS.

## Template types and conditions used for taxonomy + search

**`taxonomy-archive`** — the `taxonomy` condition's `value` entries are **JSON-encoded
strings**, not slugs:

```php
// one specific term
json_encode(['taxonomySlug' => 'services', 'termId' => 12])
// every term in the taxonomy
json_encode(['allInTax' => 'services'])
```

- Operands are only `is` / `is not` (`rules/archive/taxonomy.php`).
- ⚠ **`is not` with SEVERAL values is useless.** The callback `array_map`s over the values and
  returns `in_array(true, $results)` — so "is not Design **or** is not Video" is true on every
  term, including Design. To serve a subset differently, use **two templates separated by
  priority**, not a negation: the narrow one at a higher priority, the broad one below.

**`search`** — needs **no** rule groups; the type's own callback is `isSearch()`.

```php
oxy_template_settings($id, 'search', 30, false, []);
```

## Binding a value a class cannot carry — the `data-*` pattern

`settings.advanced.classes` is a **static array**; there is no way to bind a class name to a
dynamic field. An **attribute** can be bound, and the renderer resolves `[breakdance_dynamic …]`
inside any string property:

```php
$node['data']['properties']['settings']['advanced']['attributes'] = [
    ['name' => 'data-band', 'value' => oxy_dyn('byg_term_band')],
];
```

Then key the CSS off the attribute (`.card[data-band="c2"]{--band-1:…}`). This is how a loop
gives each item a **different** design variant — `:nth-child()` also works but hardcodes
position, whereas a bound attribute survives reordering and new terms.

⚠ **`oxy_link()` takes four parameters and has no attributes argument.** PHP silently ignores
extra arguments to a userland function, so passing attributes as a fifth sets **nothing**, with
no error. Build the node, then write the attribute onto it.
