<?php
/**
 * Panel-coverage audit — how much design lives in panels vs raw CSS, and how
 * native each page's composition is. Read-only.
 *
 * Answers two questions the "design options in panels, not custom code" rule
 * raises on any build:
 *
 *   1. Which registered selectors carry `custom_css`, and how much? (Raw CSS
 *      is invisible to the design panel's discrete controls — it should be the
 *      exception: pseudo-states, third-party inner markup, scroll-timeline
 *      vars.) Ranked by weight so the biggest offenders surface first.
 *
 *   2. Per page/template/block tree: an element census — native elements vs
 *      code nodes (CssCode/JsCode/PhpCode/HtmlCode) vs custom elements — plus
 *      how many nodes use native entrance animations.
 *
 * Shapes worth knowing (both burned an audit before being written down):
 *   - Selector custom CSS lives at properties.breakpoint_base.custom_css.custom_css
 *     (and sibling breakpoint_* groups). Grepping s['custom_css'] finds nothing.
 *   - `_oxygen_data` post meta is {tree_json_string: "<json>"} — decode the meta,
 *     then decode tree_json_string, then walk ['root']['children'].
 *
 * Usage:  scripts/wp-eval.sh audit-panel-coverage.php
 */

// ---------- 1. selector custom_css ranking -------------------------------
$raw = get_option('oxygen_oxy_selectors_json_string');
$sels = json_decode($raw, true);
if (is_string($sels)) $sels = json_decode($sels, true);   // may be double-encoded
$sels = $sels ?: [];

$findCss = function ($arr) use (&$findCss) {
    $len = 0;
    foreach ($arr as $k => $v) {
        if (is_array($v)) $len += $findCss($v);
        elseif ($k === 'custom_css' && is_string($v)) $len += strlen(trim($v));
    }
    return $len;
};

$heavy = []; $total = 0;
foreach ($sels as $s) {
    $len = $findCss($s['properties'] ?? []);
    if ($len) { $heavy[$s['name']] = $len; $total += $len; }
}
arsort($heavy);
printf("selectors: %d | with custom_css: %d | custom_css total: %d chars\n",
    count($sels), count($heavy), $total);
foreach (array_slice($heavy, 0, 20, true) as $n => $l) printf("  %-34s %5d\n", $n, $l);

// ---------- 2. tree census ------------------------------------------------
const CODE_TYPES = ['CssCode', 'JavaScriptCode', 'PhpCode', 'HtmlCode', 'CodeBlock'];

function fb_audit_tree(int $postId): array {
    $meta = get_post_meta($postId, '_oxygen_data', true);
    if (!$meta) return [];
    $outer = json_decode($meta, true);
    $tree = json_decode($outer['tree_json_string'] ?? '', true);
    $c = [];
    $walk = function ($n) use (&$walk, &$c) {
        $ty = $n['data']['type'] ?? null;
        if ($ty) {
            $short = preg_replace('/^(EssentialElements|OxygenElements)\\\\/', '', $ty);
            $c[$short] = ($c[$short] ?? 0) + 1;
            if (!empty($n['data']['properties']['settings']['animations']['entrance_animation']))
                $c['(entrance-animated)'] = ($c['(entrance-animated)'] ?? 0) + 1;
        }
        foreach ($n['children'] ?? [] as $ch) $walk($ch);
    };
    foreach ($tree['root']['children'] ?? [] as $ch) $walk($ch);
    return $c;
}

echo "\nper-tree census (pages, templates, blocks):\n";
$posts = get_posts([
    'post_type'   => ['page', 'oxygen_template', 'oxygen_header', 'oxygen_footer', 'oxygen_block'],
    'numberposts' => -1, 'post_status' => ['publish', 'draft'],
    'meta_key'    => '_oxygen_data',
]);
foreach ($posts as $p) {
    $c = fb_audit_tree($p->ID);
    if (!$c) continue;
    ksort($c);
    $code = array_sum(array_intersect_key($c, array_flip(CODE_TYPES)));
    $parts = [];
    foreach ($c as $ty => $n) $parts[] = "$ty:$n";
    printf("  %-28s [%s] %s%s\n", $p->post_name, $p->post_type,
        implode(' ', $parts), $code ? "  ⚠ $code code node(s)" : '');
}
