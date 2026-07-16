<?php
/**
 * lib.php — helpers for programmatic Oxygen 6.1 (Breakdance) tree building.
 *
 * Use from a `wp eval-file` script:   require __DIR__ . '/lib.php';
 * Run scripts with:                   ./wp-eval.sh your-script.php
 *
 * Encodes the verified invariants from the oxygen-page-builder skill:
 *  - eval-file runs in FUNCTION scope → static registries, never `global`
 *  - builder io-ts needs: _nextNodeId, status, unique int ids, _parentId (verified live: missing
 *    _nextNodeId/status = "IO-TS decoding failed"; root.properties [] vs {} both open, {} preferred)
 *  - write via \Breakdance\Data\set_meta + generateCacheForPost (raw update_post_meta does NOT work)
 *  - element content keys differ per element (Button needs link {type AND url}; FAQ renders from
 *    content.settings.items, not .questions; headings = Text + settings.advanced.tag)
 */

// ---------------------------------------------------------------------------
// id / uuid registries (static — survives eval-file's function scope)
// ---------------------------------------------------------------------------

/** Next unique node id. oxy_nid(200) re-seeds the counter (e.g. to continue an existing tree). */
function oxy_nid(int $seed = 0): int {
    static $c = 99;
    if ($seed) { $c = $seed; }
    return ++$c;
}

/** Stable uuid per logical key (so a selector keeps one uuid across a script). */
function oxy_uuid(string $key): string {
    static $m = [];
    if (!isset($m[$key])) { $m[$key] = wp_generate_uuid4(); }
    return $m[$key];
}

// ---------------------------------------------------------------------------
// node factories
// ---------------------------------------------------------------------------

/** Generic node. $properties may be null (valid). */
function oxy_el(string $type, ?array $properties = null, array $children = []): array {
    return ['id' => oxy_nid(), 'data' => ['type' => $type, 'properties' => $properties], 'children' => $children];
}

/**
 * Text element; $tag makes it a real <h1>..<h6>/<p>/<span> (heading rule: never style a
 * RichText wrapper and expect the inner <h1> to change — the class must land ON the tag).
 * $classes = plain class-name strings (settings.advanced.classes, for CSS you wrote yourself);
 * $selectorUuids = selector uuids (meta.classes, for the native class/design-panel system).
 */
function oxy_text(string $text, ?string $tag = null, array $classes = [], array $selectorUuids = []): array {
    $p = ['content' => ['content' => ['text' => $text]]];
    if ($tag)           { $p['settings']['advanced']['tag'] = $tag; }
    if ($classes)       { $p['settings']['advanced']['classes'] = array_values($classes); }
    if ($selectorUuids) { $p['meta']['classes'] = array_values($selectorUuids); }
    return oxy_el('OxygenElements\\Text', $p);
}

/**
 * Real heading (EssentialElements\Heading): text at content.content.text, level at
 * content.content.tags ('h1'..'h6'). Use THIS for headings — oxy_text with
 * settings.advanced.tag='h1' renders the same front-end markup, but the builder
 * labels the node "Text" and shows Text controls, which reads as a bug to anyone
 * editing (no heading-level selector, wrong typography panel).
 */
function oxy_heading(string $text, string $level = 'h2', array $classes = [], array $selectorUuids = []): array {
    $p = ['content' => ['content' => ['text' => $text, 'tags' => $level]]];
    if ($classes)       { $p['settings']['advanced']['classes'] = array_values($classes); }
    if ($selectorUuids) { $p['meta']['classes'] = array_values($selectorUuids); }
    return oxy_el('EssentialElements\\Heading', $p);
}

/** RichText for paragraphs/lists (HTML string). Do NOT put headings you want to restyle in here. */
function oxy_rich(string $html, array $classes = [], array $selectorUuids = []): array {
    $p = ['content' => ['content' => ['text' => $html]]];
    if ($classes)       { $p['settings']['advanced']['classes'] = array_values($classes); }
    if ($selectorUuids) { $p['meta']['classes'] = array_values($selectorUuids); }
    return oxy_el('OxygenElements\\RichText', $p);
}

/** Div wrapper (use Div, not Section, when mirroring a reference stylesheet's DOM). */
function oxy_div(array $children = [], array $classes = [], array $selectorUuids = []): array {
    $p = null;
    if ($classes)       { $p['settings']['advanced']['classes'] = array_values($classes); }
    if ($selectorUuids) { $p['meta']['classes'] = array_values($selectorUuids); }
    return oxy_el('EssentialElements\\Div', $p, $children);
}

/**
 * Button. link MUST have both type AND url — url alone renders a non-navigating <button>.
 * Markup nests: .bde-button > a.bde-button__button > span.button-atom__text; brand CSS must
 * target .bde-button__button (classes on the wrapper don't restyle the inner atom).
 */
function oxy_button(string $text, string $url, array $classes = []): array {
    $p = ['content' => ['content' => ['text' => $text, 'link' => ['type' => 'url', 'url' => $url]]]];
    if ($classes) { $p['settings']['advanced']['classes'] = array_values($classes); }
    return oxy_el('EssentialElements\\Button', $p);
}

/**
 * Link wrapping arbitrary children in an <a> (logo→home, image cards, icon links…).
 * Uses OxygenElements\ContainerLink: href renders from content.content.url, target from
 * open_in_new_tab; the `link` object is set too so the builder link-field populates.
 * ⚠ Do NOT use EssentialElements\WrapperLink — its url key does NOT render an href
 * (outputs href="#" while the classes still apply, so it looks fine but every link is
 * dead). See GOTCHAS.md §wrapper-link-href.
 */
function oxy_link(string $url, array $children, array $classes = [], bool $newTab = false): array {
    $p = ['content' => ['content' => [
        'url' => $url,
        'link' => ['type' => 'url', 'url' => $url],
        'open_in_new_tab' => $newTab,
    ]]];
    if ($classes) { $p['settings']['advanced']['classes'] = array_values($classes); }
    return oxy_el('OxygenElements\\ContainerLink', $p, $children);
}

/**
 * FAQ accordion. $qas = [['q' => 'Question?', 'a' => '<p>Answer html</p>'], ...].
 * Renders from content.settings.items; `questions` is mirrored for the builder control.
 * Style via the element's CSS vars on your wrapper class (--faqBorderColor/--faqBorderWidth/
 * --faqItemVerticalPadding/--faqItemHorizontalPadding) — never touch .bde-faq__answer display.
 */
function oxy_faq(array $qas, array $classes = [], bool $accordion = true, bool $firstOpen = false): array {
    $items = array_map(fn($x) => ['question' => $x['q'], 'answer' => $x['a']], $qas);
    $p = ['content' => ['settings' => [
        'items' => $items, 'questions' => $items,
        'accordion' => $accordion, 'first_tab_opened' => $firstOpen,
    ]]];
    if ($classes) { $p['settings']['advanced']['classes'] = array_values($classes); }
    return oxy_el('EssentialElements\\FrequentlyAskedQuestions', $p);
}

/**
 * Image from the media library. Path is content.image.* (NOT content.content.*) — from the
 * element's contentControls: from, media (wpmedia object), size, alt (enum: from_media_library|
 * custom|decorative; custom text goes in custom_alt), lazy_load. External image: from='url' + url.
 *
 * srcset/sizes are NOT computed at render time: the element's attributes() template prints the
 * PRE-COMPUTED strings at content.image.media.attributes.srcset / .sizes verbatim (the builder UI
 * fills them when an image is picked; scripted trees must do it themselves or the img ships the
 * bare full-size src). This helper populates them via wp_get_attachment_image_srcset()/_sizes().
 *
 * $opts:
 *   lazy          bool   default true. false OMITS the loading attr entirely (eager) — use for
 *                        the LCP/hero image, usually together with fetchpriority => 'high'.
 *   sizes         string override for the sizes attribute (e.g. '100vw', '(min-width: 60rem)
 *                        30rem, 92vw'). Default: WP's '(max-width: Npx) 100vw, Npx'.
 *   fetchpriority string 'high'|'low' — rendered via settings.advanced.attributes (custom
 *                        attributes DO land on the <img>, it is the element's root tag).
 *   dims          bool   default true: emit width/height attributes of the chosen $size (CLS).
 */
function oxy_image(int $attachmentId, string $size = 'full', array $classes = [], ?string $customAlt = null, array $opts = []): array {
    $url = wp_get_attachment_url($attachmentId) ?: '';
    $w = $h = 0; $sizeUrl = $url;
    if ($src = wp_get_attachment_image_src($attachmentId, $size)) { [$sizeUrl, $w, $h] = $src; }
    $img = [
        'from'      => 'media_library',
        'media'     => ['id' => $attachmentId, 'url' => $url,
                        'sizes' => ['full' => ['url' => $url], $size => ['url' => $sizeUrl]]],
        'size'      => $size,
        'alt'       => $customAlt !== null ? 'custom' : 'from_media_library',
        'lazy_load' => $opts['lazy'] ?? true,
    ];
    if ($srcset = wp_get_attachment_image_srcset($attachmentId, $size)) {
        $img['media']['attributes'] = [
            'srcset' => $srcset,
            'sizes'  => $opts['sizes'] ?? (wp_get_attachment_image_sizes($attachmentId, $size) ?: ''),
        ];
    }
    if ($customAlt !== null) { $img['custom_alt'] = $customAlt; }
    $p = ['content' => ['image' => $img]];
    if ($classes) { $p['settings']['advanced']['classes'] = array_values($classes); }
    $attrs = [];
    if (($opts['dims'] ?? true) && $w && $h) {
        $attrs[] = ['name' => 'width',  'value' => (string) $w];
        $attrs[] = ['name' => 'height', 'value' => (string) $h];
    }
    if (!empty($opts['fetchpriority'])) { $attrs[] = ['name' => 'fetchpriority', 'value' => $opts['fetchpriority']]; }
    if ($attrs) { $p['settings']['advanced']['attributes'] = $attrs; }
    return oxy_el('OxygenElements\\Image', $p);
}

/**
 * Self-hosted video (OxygenElements\Html5Video) — defaults to a muted autoplay
 * background loop. The element has NO poster control, but <video> is its root
 * tag, so a custom attribute lands on it (verified; same mechanism as
 * width/height on Image). A poster paints before the video buffers and stays
 * up for prefers-reduced-motion visitors whose video never plays.
 *
 * $opts: autoplay/loop/muted/plays_inline/controls (bools, defaults suit a
 *        background loop) | poster (URL string).
 */
function oxy_video(string $url, int $attachmentId = 0, array $opts = []): array {
    $vf = $attachmentId ? ['id' => $attachmentId, 'url' => $url] : ['url' => $url];
    $p = ['content' => ['content' => [
        'video_file_url' => $vf,
        'autoplay'     => $opts['autoplay']     ?? true,
        'loop'         => $opts['loop']         ?? true,
        'muted'        => $opts['muted']        ?? true,
        'plays_inline' => $opts['plays_inline'] ?? true,
        'controls'     => $opts['controls']     ?? false,
    ]]];
    if (!empty($opts['poster'])) {
        $p['settings']['advanced']['attributes'] = [['name' => 'poster', 'value' => $opts['poster']]];
    }
    return oxy_el('OxygenElements\\Html5Video', $p);
}

/** Code elements (escape hatches — prefer native elements; see skill rules). */
function oxy_css(string $css): array  { return oxy_el('OxygenElements\\CssCode', ['content' => ['content' => ['css_code' => $css]]]); }
function oxy_js(string $js): array    { return oxy_el('OxygenElements\\JavaScriptCode', ['content' => ['content' => ['javascript_code' => $js]]]); }
function oxy_html(string $html): array{ return oxy_el('OxygenElements\\HtmlCode', ['content' => ['content' => ['html_code' => $html]]]); }
function oxy_php(string $php): array  { return oxy_el('OxygenElements\\PhpCode', ['content' => ['content' => ['php_code' => $php]]]); }

// ---------------------------------------------------------------------------
// golden shapes (never hand-guess a complex element)
// ---------------------------------------------------------------------------

/**
 * Deep-copied defaultProperties/defaultChildren for an element slug, exactly as the builder
 * inserts it. ALWAYS deep-copied so per-form/per-instance mutation can't leak between nodes.
 * Returns ['properties' => array|null, 'children' => array].
 */
function oxy_golden(string $slug): array {
    static $all = null;
    if ($all === null) { $all = \Breakdance\Elements\get_elements_for_builder(); }
    foreach ($all as $e) {
        $e = (array) $e;
        if (($e['slug'] ?? '') === $slug) {
            return json_decode(json_encode([
                'properties' => $e['defaultProperties'] ?? null,
                'children'   => $e['defaultChildren'] ?? [],
            ]), true);
        }
    }
    throw new RuntimeException("oxy_golden: unknown element slug '$slug'");
}

/** All registered element slugs (for validation). */
function oxy_known_slugs(): array {
    static $slugs = null;
    if ($slugs === null) {
        $slugs = [];
        foreach (\Breakdance\Elements\get_elements_for_builder() as $e) {
            $e = (array) $e;
            if (!empty($e['slug'])) { $slugs[$e['slug']] = true; }
        }
    }
    return $slugs;
}

/**
 * Build a ready-to-inject TREE NODE for a native composite element from its golden.
 * oxy_golden() returns defaultChildren in ELEMENT-DEFINITION format ({slug,defaultProperties,
 * defaultChildren}), NOT tree-node format ({id,data:{type,properties},children}) — so they can't be
 * injected directly. This converts the whole element (self + nested defaultChildren) into a valid
 * node with fresh ids. Use for Tabs/Slider/Accordion/Product Builder/Menu Builder composites, then
 * override the content you care about on the returned node.
 *   $tabs = oxy_element_tree('EssentialElements\\AdvancedTabs');
 *   $tabs['data']['properties']['content']['content']['tabs'] = [...];
 */
function oxy_element_tree(string $slug): array {
    $g = oxy_golden($slug);
    return oxy_def_to_node(['slug' => $slug, 'defaultProperties' => $g['properties'], 'defaultChildren' => $g['children']]);
}

/** (internal) recursively convert an element-definition to a tree node with fresh ids. */
function oxy_def_to_node($def): array {
    $def   = (array) $def;
    $slug  = $def['slug'] ?? ($def['data']['type'] ?? null);
    $props = $def['defaultProperties'] ?? ($def['data']['properties'] ?? null);
    if (!is_array($props) || !$props) { $props = null; } // false/[] → null (element takes null)
    $node  = oxy_el($slug, $props);
    foreach (($def['defaultChildren'] ?? $def['children'] ?? []) as $child) {
        $node['children'][] = oxy_def_to_node($child);
    }
    return $node;
}

// ---------------------------------------------------------------------------
// tree write (wires parents, computes _nextNodeId, validates, saves, regenerates cache)
// ---------------------------------------------------------------------------

/**
 * Write $children as post $postId's Oxygen tree. Throws on validation errors.
 * NOTE: this REPLACES the whole tree — it overwrites manual builder edits on that post.
 * To keep existing nodes (e.g. the footer's global CssCode/JsCode), read the current tree
 * first (\Breakdance\Data\get_tree($postId)) and re-inject those nodes into $children.
 */
function oxy_write_tree(int $postId, array $children): void {
    $root = ['id' => 1, 'data' => ['type' => 'root', 'properties' => new stdClass()], 'children' => $children];
    oxy_wire_parents_r($root, null);
    $tree = ['root' => $root, '_nextNodeId' => oxy_max_id_r($root) + 1, 'status' => 'exported'];

    $json   = wp_json_encode($tree);
    $errors = oxy_validate_tree_json($json, true)['errors'];
    if ($errors) { throw new RuntimeException("Tree invalid, NOT written:\n - " . implode("\n - ", $errors)); }

    $prefix = \Breakdance\BreakdanceOxygen\Strings\__bdox('_meta_prefix'); // '_oxygen_'
    \Breakdance\Data\set_meta($postId, $prefix . 'data', ['tree_json_string' => $json]);
    \Breakdance\Render\generateCacheForPost($postId);
}

/** (internal) stamp _parentId on every non-root node. */
function oxy_wire_parents_r(array &$node, ?int $parentId): void {
    if ($parentId !== null) { $node['_parentId'] = $parentId; }
    foreach ($node['children'] as &$child) { oxy_wire_parents_r($child, $node['id']); }
    unset($child);
}

/** (internal) max node id in a subtree. */
function oxy_max_id_r(array $node): int {
    $max = (int) $node['id'];
    foreach ($node['children'] as $child) { $max = max($max, oxy_max_id_r($child)); }
    return $max;
}

// ---------------------------------------------------------------------------
// validation (mirrors the builder's io-ts requirements + known element traps)
// ---------------------------------------------------------------------------

/**
 * Validate a tree JSON string. Returns ['errors' => [...], 'warnings' => [...]].
 * errors  = will fail in the builder (io-ts) or silently not render — do not write.
 * warnings = known traps worth double-checking.
 * $checkSlugs requires WP loaded (uses get_elements_for_builder()).
 */
function oxy_validate_tree_json(string $json, bool $checkSlugs = false): array {
    $errors = []; $warnings = [];
    $t = json_decode($json); // object mode — keeps the {} vs [] distinction io-ts cares about
    if (!is_object($t))                     { return ['errors' => ['not valid JSON'], 'warnings' => []]; }
    if (!isset($t->root))                   { $errors[] = 'missing root'; }
    if (!isset($t->_nextNodeId) || !is_int($t->_nextNodeId)) { $errors[] = 'missing/non-int _nextNodeId (io-ts failure #1)'; }
    if (!isset($t->status) || !is_string($t->status))        { $errors[] = 'missing status (e.g. "exported")'; }
    if (isset($t->root)) {
        $r = $t->root;
        if (($r->data->type ?? '') !== 'root')       { $errors[] = 'root.data.type must be "root"'; }
        if (!is_object($r->data->properties ?? null)) {
            // verified live 2026-07-11: [] opens fine in builder 6.1.0 (home #10, footer #15, and
            // Breakdance's own fallbacks use it) — but {} is what the builder itself saves, so flag it
            $warnings[] = 'root.data.properties is [] not {} — builder accepts both; {} is the builder-native shape';
        }

        $ids = []; $slugs = $checkSlugs ? oxy_known_slugs() : null;
        $walk = function ($n, $parentId) use (&$walk, &$ids, &$errors, &$warnings, $slugs) {
            $id = $n->id ?? null;
            if (!is_int($id))            { $errors[] = 'node id missing/non-int' . ($parentId !== null ? " (under $parentId)" : ''); }
            elseif (isset($ids[$id]))    { $errors[] = "duplicate node id $id"; }
            else                         { $ids[$id] = true; }
            if ($parentId !== null && (($n->_parentId ?? null) !== $parentId)) {
                $errors[] = "node $id: _parentId should be $parentId, got " . var_export($n->_parentId ?? null, true);
            }
            $type = $n->data->type ?? '';
            if ($parentId !== null) {
                if ($slugs !== null && $type !== 'root' && !isset($slugs[$type])) { $errors[] = "node $id: unknown element slug '$type'"; }
                // element-specific traps (verified on this site)
                if ($type === 'EssentialElements\\Button') {
                    $link = $n->data->properties->content->content->link ?? null;
                    if ($link !== null && (empty($link->type) || !isset($link->url))) {
                        $warnings[] = "node $id Button: link needs BOTH type AND url or it renders a non-navigating <button>";
                    }
                }
                if ($type === 'EssentialElements\\FrequentlyAskedQuestions') {
                    if (empty($n->data->properties->content->settings->items)) {
                        $errors[] = "node $id FAQ: content.settings.items is empty — element renders EMPTY (questions[] alone is builder-side only)";
                    }
                }
                if ($type === 'OxygenElements\\RichText') {
                    $txt = $n->data->properties->content->content->text ?? '';
                    if (preg_match('/<h[1-6][\s>]/i', (string) $txt)) {
                        $warnings[] = "node $id RichText contains <h1>-<h6>: classes on the wrapper won't restyle them — use Text + settings.advanced.tag per heading";
                    }
                }
                if ($type === 'EssentialElements\\FormBuilder') {
                    $emails = $n->data->properties->content->actions->email->emails ?? [];
                    foreach ((array) $emails as $i => $em) {
                        if (($em->to ?? false) === false) { $warnings[] = "node $id FormBuilder: email[$i].to is false — submissions email goes NOWHERE until set"; }
                    }
                }
            }
            foreach ($n->children ?? [] as $c) { $walk($c, $id); }
        };
        $walk($r, null);

        $max = empty($ids) ? 0 : max(array_keys($ids));
        if (isset($t->_nextNodeId) && is_int($t->_nextNodeId) && $t->_nextNodeId <= $max) {
            $errors[] = "_nextNodeId ({$t->_nextNodeId}) must be > max node id ($max)";
        }
        // meta.classes uuids must exist in the global selectors option
        $sel = json_decode((string) get_option('oxygen_oxy_selectors_json_string'), true);
        $known = [];
        foreach (($sel['selectors'] ?? []) as $s) { $known[$s['id'] ?? ''] = true; }
        $walk2 = function ($n) use (&$walk2, &$warnings, $known) {
            foreach (($n->data->properties->meta->classes ?? []) as $uuid) {
                if (!isset($known[$uuid])) { $warnings[] = "node {$n->id}: meta.classes uuid '$uuid' not found in saved selectors (class won't emit)"; }
            }
            foreach ($n->children ?? [] as $c) { $walk2($c); }
        };
        if (function_exists('get_option')) { $walk2($r); }
    }
    return ['errors' => $errors, 'warnings' => $warnings];
}

// ---------------------------------------------------------------------------
// selectors (global classes) + templates
// ---------------------------------------------------------------------------

/** Selector ("class") object; $groups go under properties.breakpoint_base (see PROPERTIES.md). */
function oxy_selector(string $name, array $groups): array {
    return ['id' => oxy_uuid("sel:$name"), 'name' => ltrim($name, '.'), 'type' => 'class',
            'collection' => 'Default', 'children' => [], 'locked' => false,
            'properties' => ['breakpoint_base' => $groups]];
}

/**
 * MERGE new selectors into the saved set (replaces same-name entries) and recompile.
 * Follow with generateCacheForPost($pageId) for each affected page.
 */
function oxy_save_selectors(array $newSelectors): void {
    $cur = json_decode((string) get_option('oxygen_oxy_selectors_json_string'), true) ?: [];
    $selectors = $cur['selectors'] ?? [];
    $byName = [];
    foreach ($selectors as $i => $s) { $byName[$s['name'] ?? ''] = $i; }
    foreach ($newSelectors as $s) {
        if (isset($byName[$s['name']])) { $selectors[$byName[$s['name']]] = $s; }
        else                            { $selectors[] = $s; }
    }
    \Breakdance\BreakdanceOxygen\Selectors\saveSelectors(
        json_encode(['selectors' => $selectors, 'collections' => $cur['collections'] ?? ['Default']])
    );
}

/**
 * Write an oxygen_template's location settings — MUST be a JSON STRING (an array becomes
 * "Array" → template silently ignored). Keep design-library templates disabled:true.
 */
function oxy_template_settings(int $templateId, string $type, int $priority = 30, bool $disabled = false, array $ruleGroups = []): void {
    $s = ['type' => $type, 'ruleGroups' => $ruleGroups, 'priority' => $priority];
    if ($disabled) { $s['disabled'] = true; }
    \Breakdance\Data\set_meta($templateId, \Breakdance\BreakdanceOxygen\Strings\__bdox('_meta_prefix') . 'template_settings', json_encode($s));
}
