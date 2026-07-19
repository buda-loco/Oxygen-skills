<?php
/**
 * audit-detail.php <postId> — per class-less authored element, print the context needed to find the
 * CSS that styles it "elsewhere": node id, type, render tag, inner content tags (RichText), the
 * nearest ancestor's BEM-base class, and the auto id-token (-<postId>-<nodeId>) that node-level design
 * compiles to. Feed this to a CSS grep: `.<ancestor> <tag>` (descendant), `<tag>` (bare), or the token.
 *
 *   OXY_SITE_PATH=/path/app  scripts/wp-eval.sh scripts/examples/audit-detail.php <postId>
 */
require __DIR__ . '/../lib.php';

$args = $args ?? [];
$pid  = (int) ($args[0] ?? 0);
if (!$pid) { fwrite(STDERR, "usage: audit-detail.php <postId>\n"); exit(1); }
$tree = \Breakdance\Data\get_tree($pid);
if (!$tree || empty($tree['root'])) { fwrite(STDERR, "#{$pid}: no tree\n"); exit(1); }

$bemBase = fn($c) => preg_split('/__|--/', (string) $c)[0];
$n = 0;
$walk = function ($node, $ancestorBase) use (&$walk, &$n, $bemBase, $pid) {
    foreach (($node['children'] ?? []) as $c) {
        $type = $c['data']['type'] ?? '';
        $props = $c['data']['properties'] ?? [];
        $ownBase = '';
        $own = $props['settings']['advanced']['classes'] ?? [];
        if ($own) { $ownBase = $bemBase($own[0]); }
        if (oxy_needs_class($type) && !oxy_node_has_class($c)) {
            $short = str_replace(['OxygenElements\\', 'EssentialElements\\'], '', $type);
            $tag = $props['settings']['advanced']['tag'] ?? ($short === 'Text' ? 'span' : ($short === 'RichText' ? 'div' : ''));
            $inner = '';
            if ($short === 'RichText') {
                $html = (string) ($props['content']['content']['text'] ?? '');
                if (preg_match_all('/<([a-z][a-z0-9]*)\b/i', $html, $m)) { $inner = implode(',', array_values(array_unique(array_slice($m[1], 0, 6)))); }
            }
            printf("#%d %s tag=%s%s ancestor=%s idtoken=-%d-%d\n",
                $c['id'], $short, $tag ?: '-',
                $inner ? " inner={$inner}" : '',
                $ancestorBase !== '' ? ('.' . $ancestorBase) : '(none)',
                $pid, $c['id']);
            $n++;
        }
        $walk($c, $ownBase !== '' ? $ownBase : $ancestorBase);
    }
};
$walk($tree['root'], '');
echo "— {$n} class-less authored element(s) in #{$pid}\n";
