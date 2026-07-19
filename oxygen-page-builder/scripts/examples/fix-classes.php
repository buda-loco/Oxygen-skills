<?php
/**
 * fix-classes.php — remediate PROJECT RULE §4 on ONE post: give every class-less authored element a
 * BEM/brand class. Companion to the read-only audit-classes.php.
 *
 *   scripts/wp-eval.sh scripts/examples/fix-classes.php <postId>           # DRY-RUN (default): proposals only
 *   scripts/wp-eval.sh scripts/examples/fix-classes.php <postId> apply     # write the classes
 *
 * How a class is derived (heuristic — review the dry-run before apply):
 *   block = nearest ancestor's authored class, reduced to its BEM base (drops __element / --modifier).
 *   role  = from the element type/tag (h1-6→title, p→text, Image→image, Button→btn, link→link, …).
 *   class = "{block}__{role}"  (duplicates within one parent get -2, -3 so distinct siblings differ).
 *
 * SAFE: adding a class is idempotent and does NOT change rendering — existing descendant-selector CSS
 * (".prose p") keeps matching. It only makes the element selectable/editable and gives its styling a
 * home. MIGRATING that CSS onto the new class (so design lives ON the class, §4) is a follow-up you do
 * in the builder or a scoped CSS pass — this tool intentionally does not rewrite your stylesheet.
 */
require __DIR__ . '/../lib.php';

$args  = $args ?? [];
$pid   = (int) ($args[0] ?? 0);
$apply = in_array('apply', $args, true) || in_array('--apply', $args, true);  // 'apply' positional (WP-CLI eats --flags)
if (!$pid) { fwrite(STDERR, "usage: fix-classes.php <postId> [apply]\n"); exit(1); }

$p = get_post($pid);
if (!$p) { fwrite(STDERR, "no such post #{$pid}\n"); exit(1); }
$tree = \Breakdance\Data\get_tree($pid);
if (!$tree || empty($tree['root'])) { fwrite(STDERR, "#{$pid} has no Oxygen tree\n"); exit(1); }

$bemBase = fn($class) => preg_split('/__|--/', (string) $class)[0];
$roleFor = function ($node) {
    $short = str_replace(['OxygenElements\\', 'EssentialElements\\'], '', $node['data']['type'] ?? '');
    switch ($short) {
        case 'Text':
            $tag = $node['data']['properties']['settings']['advanced']['tag'] ?? 'span';
            if (preg_match('/^h[1-6]$/', $tag)) return 'title';
            if ($tag === 'li') return 'item';
            if ($tag === 'blockquote') return 'quote';
            return 'text';
        case 'RichText':   return 'text';
        case 'Image':      return 'image';
        case 'Button':     return 'btn';
        case 'WrapperLink':
        case 'ContainerLink':
        case 'TextLink':   return 'link';
        case 'Icon':       return 'icon';
        case 'Html5Video': return 'video';
        case 'Div':        return 'inner';
        case 'Section':    return 'section';
        default:           return 'el';
    }
};

$proposals = [];
$walk = function (&$node, $blockBase) use (&$walk, &$proposals, $apply, $bemBase, $roleFor) {
    $used = [];
    foreach ($node['children'] as &$child) {
        $type = $child['data']['type'] ?? '';
        if ($type !== 'root' && oxy_needs_class($type) && !oxy_node_has_class($child)) {
            $base = $blockBase !== '' ? $blockBase : 'block';
            $cls  = $base . '__' . $roleFor($child);
            if (isset($used[$cls])) { $cls .= '-' . (++$used[$cls]); } else { $used[$cls] = 1; }
            $short = str_replace(['OxygenElements\\', 'EssentialElements\\'], '', $type);
            $txt   = trim(mb_substr(strip_tags((string) ($child['data']['properties']['content']['content']['text'] ?? '')), 0, 30));
            $proposals[] = ['id' => $child['id'], 'label' => "{$short}" . ($txt ? " \"$txt\"" : ''), 'class' => $cls];
            if ($apply) { oxy_ensure_class($child, $cls); }
        }
        $own = oxy_node_classes($child);
        $walk($child, $own ? $bemBase($own[0]) : $blockBase);
    }
    unset($child);
};
$root = $tree['root'];
$walk($root, '');

echo ($apply ? "APPLY" : "DRY-RUN") . " — #{$pid} \"{$p->post_title}\" — " . count($proposals) . " class-less element(s)\n";
foreach ($proposals as $x) { printf("  #%-5s %-40s ->  .%s\n", $x['id'], mb_substr($x['label'], 0, 40), $x['class']); }

if (!$proposals) { echo "Nothing to fix — every authored element already has a class. ✓\n"; return; }

if (!$apply) {
    echo "\nRe-run with `apply` to write these classes. (Review names first — they're heuristic.)\n";
    echo "Reminder: this only ADDS classes (safe). Move each rule off its descendant selector onto the\n";
    echo "new class afterward so design lives ON the element's class (RULE §4).\n";
    return;
}

// guarded write: oxy_write_tree throws "Tree invalid" BEFORE writing meta; a WC-template cache fatal
// throws AFTER meta is written (see GOTCHAS §generateCacheForPost). Distinguish by message.
try {
    oxy_write_tree($pid, $root['children']);
    echo "\nOK: wrote " . count($proposals) . " classes + regenerated cache.\n";
} catch (\Throwable $e) {
    if (strpos($e->getMessage(), 'Tree invalid') !== false) { fwrite(STDERR, "NOT written (invalid tree): {$e->getMessage()}\n"); exit(1); }
    echo "\nOK: wrote " . count($proposals) . " classes; cache regen deferred ({$e->getMessage()}) — front-end rebuilds on next view.\n";
}
echo "Next: open ?oxygen=builder&id={$pid} to confirm, then migrate CSS onto the new classes (§4).\n";
