<?php
/**
 * Append an OxygenElements\Component reference (to $BLOCK) at the end of each target template/page's
 * root children. The global footer (#15) is applied separately, so "end of root" = bottom of page.
 * Idempotent: skips a target that already references $BLOCK.
 *
 * Configure via env: BLOCK=<oxygen_block id> TARGETS="521,522,598"
 */
require __DIR__ . '/../lib.php';

$BLOCK   = (int) (getenv('BLOCK') ?: 0);
$TARGETS = array_filter(array_map('intval', explode(',', getenv('TARGETS') ?: '')));
if (!$BLOCK || !$TARGETS) { fwrite(STDERR, "need BLOCK and TARGETS env\n"); exit(1); }

$hasRef = function ($node, $blockId) use (&$hasRef) {
    if (($node['data']['type'] ?? '') === 'OxygenElements\\Component'
        && (int) ($node['data']['properties']['content']['content']['block']['componentId'] ?? 0) === $blockId) {
        return true;
    }
    foreach (($node['children'] ?? []) as $c) { if ($hasRef($c, $blockId)) return true; }
    return false;
};

foreach ($TARGETS as $pid) {
    $t = \Breakdance\Data\get_tree($pid);
    if (!$t || empty($t['root'])) { echo "SKIP #$pid (no tree)\n"; continue; }
    if ($hasRef($t['root'], $BLOCK)) { echo "SKIP #$pid (already has block $BLOCK)\n"; continue; }

    oxy_nid(oxy_max_id_r($t['root'])); // avoid id collision with existing nodes
    $comp = oxy_el('OxygenElements\\Component', [
        'content' => ['content' => ['block' => ['componentId' => $BLOCK, 'targets' => []]]],
    ]);
    $children = $t['root']['children'];
    $children[] = $comp;

    // inline of oxy_write_tree, but cache-regen is guarded: WC single-product templates (native
    // Product element) fatal during generateCacheForPost outside a product context. The tree meta
    // still persists; the real front-end (with product context) regenerates the cache on next view.
    $root = ['id' => 1, 'data' => ['type' => 'root', 'properties' => new stdClass()], 'children' => $children];
    oxy_wire_parents_r($root, null);
    $tree = ['root' => $root, '_nextNodeId' => oxy_max_id_r($root) + 1, 'status' => 'exported'];
    $json = wp_json_encode($tree);
    $errs = oxy_validate_tree_json($json, true)['errors'];
    if ($errs) { echo "SKIP #$pid (invalid): " . implode('; ', $errs) . "\n"; continue; }
    $prefix = \Breakdance\BreakdanceOxygen\Strings\__bdox('_meta_prefix');
    \Breakdance\Data\set_meta($pid, $prefix . 'data', ['tree_json_string' => $json]);
    try { \Breakdance\Render\generateCacheForPost($pid); echo "OK #$pid <- component $BLOCK (cache regenerated)\n"; }
    catch (\Throwable $e) { echo "OK #$pid <- component $BLOCK (cache deferred: " . $e->getMessage() . ")\n"; }
}
