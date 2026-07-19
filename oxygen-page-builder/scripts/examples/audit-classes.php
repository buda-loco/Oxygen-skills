<?php
/**
 * audit-classes.php — READ-ONLY audit for PROJECT RULE §4 (every authored element carries its class).
 *
 * Walks every _oxygen_data tree and lists authored elements (see oxy_needs_class in lib.php) that have
 * NO class — neither settings.advanced.classes (plain) nor meta.classes (native selector). Those only
 * get Oxygen's auto bde-/oxy- id-classes, so they can't be selected/styled cleanly in the builder and
 * tend to be styled "at a distance" (descendant selectors like ".prose p"). Also flags node-level
 * design present without a native selector (styling that compiles onto the auto id-class).
 *
 * Native widgets, Component refs, composite children, and code nodes are class-optional (excluded).
 * Run:  OXY_SITE_PATH=/path/to/site/app  scripts/wp-eval.sh scripts/examples/audit-classes.php
 */
require __DIR__ . '/../lib.php';

global $wpdb;
$ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_oxygen_data' ORDER BY post_id");

$grand_classless = 0; $grand_nodedesign = 0; $grand_nodes = 0; $posts_with_issues = 0;

foreach ($ids as $pid) {
    $p = get_post($pid);
    if (!$p || $p->post_type === 'revision') continue;
    try { $tree = @\Breakdance\Data\get_tree($pid); }
    catch (\Throwable $e) { echo "── #{$pid} \"{$p->post_title}\" — SKIPPED ({$e->getMessage()})\n"; continue; }
    if (!$tree || empty($tree['root'])) continue;

    $classless = []; $nodedesign = []; $count = 0;
    $walk = function ($n) use (&$walk, &$classless, &$nodedesign, &$count) {
        $type = $n['data']['type'] ?? '?';
        if ($type !== 'root' && oxy_needs_class($type)) {
            $count++;
            $short = str_replace(['OxygenElements\\', 'EssentialElements\\'], '', $type);
            if (!oxy_node_has_class($n)) {
                $txt = $n['data']['properties']['content']['content']['text'] ?? '';
                $txt = trim(mb_substr(strip_tags(is_string($txt) ? $txt : ''), 0, 32));
                $classless[] = "#{$n['id']} {$short}" . ($txt ? " \"$txt\"" : '');
            }
            $props = $n['data']['properties'] ?? [];
            if (!empty($props['design']) && empty($props['meta']['classes'])) {
                $nodedesign[] = "#{$n['id']} {$short}";
            }
        }
        foreach (($n['children'] ?? []) as $c) $walk($c);
    };
    $walk($tree['root']);

    $grand_nodes += $count;
    if ($classless || $nodedesign) {
        $posts_with_issues++;
        $grand_classless += count($classless);
        $grand_nodedesign += count($nodedesign);
        echo "── #{$pid} \"{$p->post_title}\" ({$p->post_type})\n";
        if ($classless)  { echo "   CLASS-LESS (" . count($classless) . "): " . implode(' | ', array_slice($classless, 0, 12)) . (count($classless) > 12 ? " …+" . (count($classless) - 12) : '') . "\n"; }
        if ($nodedesign) { echo "   design-on-node, no selector (" . count($nodedesign) . "): " . implode(' | ', array_slice($nodedesign, 0, 8)) . "\n"; }
    }
}

echo "\n==================== SUMMARY ====================\n";
echo "posts with issues:         {$posts_with_issues}\n";
echo "authored nodes scanned:    {$grand_nodes}\n";
echo "CLASS-LESS elements:       {$grand_classless}\n";
echo "design-on-node (no class): {$grand_nodedesign}\n";
echo "\nFix with: scripts/examples/fix-classes.php <postId>          (dry-run — shows proposed classes)\n";
echo "          scripts/examples/fix-classes.php <postId> apply      (writes them)\n";
