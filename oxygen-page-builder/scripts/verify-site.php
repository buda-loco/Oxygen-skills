<?php
/**
 * Server-side half of the verify battery (see verify-site.sh). Read-only.
 *
 * Emits machine-readable `KEY=value` lines the shell wrapper parses, so the
 * two halves stay decoupled and this file can also be run alone.
 *
 * Args (via wp eval-file, so they arrive in $args):
 *   [ids…]  post ids to validate; omit for "every post carrying _oxygen_data"
 */

require __DIR__ . '/lib.php';

$ids = array_values(array_filter(array_map('intval', $args ?? [])));
if (!$ids) {
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col(
        "SELECT m.post_id FROM {$wpdb->postmeta} m
         JOIN {$wpdb->posts} p ON p.ID = m.post_id
         WHERE m.meta_key = '_oxygen_data'
           AND p.post_type != 'revision'
           AND p.post_status IN ('publish','draft')
         ORDER BY p.post_type, p.ID"));
}

// ── selector store health ---------------------------------------------------
$sv = oxy_validate_selectors();
echo 'SELECTOR_ERRORS=' . count($sv['errors']) . "\n";
foreach ($sv['errors'] as $e) echo "  selector: $e\n";

// ── panel-expressibility lint ----------------------------------------------
$raw = get_option('oxygen_oxy_selectors_json_string');
$sels = json_decode($raw, true);
if (is_string($sels)) $sels = json_decode($sels, true);
$sels = $sels ?: [];
$findCss = function (array $a) use (&$findCss): string {
    $css = '';
    foreach ($a as $k => $v) {
        if (is_array($v)) $css .= $findCss($v);
        elseif ($k === 'custom_css' && is_string($v)) $css .= "\n" . $v;
    }
    return $css;
};
$lint = 0;
foreach ($sels as $s) {
    $css = $findCss($s['properties'] ?? []);
    if (trim($css) === '') continue;
    foreach (oxy_panel_lint($s['name'] ?? '?', $css) as $w) { $lint++; echo "  lint: $w\n"; }
}
echo "PANEL_LINT=$lint\n";

// ── per-tree io-ts invariants ----------------------------------------------
// Mirrors validate-tree.php's core checks; kept inline so one process covers
// every tree instead of forking a wp-cli run per id.
$treeBad = [];
foreach ($ids as $id) {
    $meta = get_post_meta($id, '_oxygen_data', true);
    if (!$meta) { $treeBad[$id] = 'no _oxygen_data'; continue; }
    $outer = json_decode($meta, true);
    $tree  = json_decode($outer['tree_json_string'] ?? '', true);
    if (!is_array($tree) || !isset($tree['root'])) { $treeBad[$id] = 'undecodable tree'; continue; }
    foreach (['_nextNodeId', 'status'] as $k) {
        if (!array_key_exists($k, $tree)) { $treeBad[$id] = "missing $k (builder IO-TS fail)"; continue 2; }
    }
    // `unit: ""` is legal ONLY under line_height; anywhere else the builder's
    // enum rejects it and the page won't open.
    $walk = function ($n, $path = '') use (&$walk, &$treeBad, $id) {
        if (isset($n['unit']) && $n['unit'] === '' && !str_contains($path, 'line_height')) {
            $treeBad[$id] = 'empty unit outside line_height at ' . ($path ?: 'root');
        }
        foreach ($n as $k => $v) if (is_array($v)) $walk($v, "$path.$k");
    };
    $walk($tree['root']);
}
echo 'TREES_CHECKED=' . count($ids) . "\n";
echo 'TREES_INVALID=' . count($treeBad) . "\n";
foreach ($treeBad as $id => $why) echo "  tree #$id: $why\n";

// ── the ids a browser pass should open in the builder ----------------------
echo 'BUILDER_IDS=' . implode(',', $ids) . "\n";

// ── public URLs worth fetching ---------------------------------------------
$urls = [];
foreach ($ids as $id) {
    $p = get_post($id);
    if ($p && $p->post_type === 'page' && $p->post_status === 'publish') {
        $urls[] = get_permalink($id);
    }
}
foreach (get_posts(['post_type' => 'any', 'numberposts' => 3, 'post_status' => 'publish',
                    'post_type__not_in' => ['page']]) as $p) {
    if (is_post_type_viewable($p->post_type)) $urls[] = get_permalink($p->ID);
}
$urls = array_values(array_unique($urls));
echo 'URLS=' . implode(' ', $urls) . "\n";

// ── admin login link for the builder pass ----------------------------------
// The agent-connector ability mints one; fall back to nothing if absent.
$login = '';
if (function_exists('wp_get_current_user')) {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if ($admins && class_exists('\WP_REST_Request')) {
        $login = ''; // wrapper mints it via MCP/CLI; nothing to do server-side
    }
}
echo "OK\n";
