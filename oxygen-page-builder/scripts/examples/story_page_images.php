<?php
/**
 * EXAMPLE — swap text placeholder boxes (.ph) for real, builder-editable Image elements.
 *
 * Scenario: a brand-story page (#123) was scaffolded with 5 styled placeholder Divs (.ph) — one
 * full-bleed hero background + four 4:3 feature-row media slots. Once imagery exists (att 648-652),
 * replace each placeholder node in place with a native Image element, then append the fit/overlay/
 * hover CSS to the global stylesheet (a CssCode node in the footer template, #15 here).
 *
 * Demonstrates: targeted node replacement by id, oxy_image(), id-collision seeding, and the
 * idempotent MARKED-css-block pattern. All post/attachment ids are illustrative.
 *   #209 (.ph--dark, hero bg) -> att 648  .story-hero__img
 *   #219/#225/#231/#237 (.ph) -> att 649-652  .feature-media__img
 */
require __DIR__ . '/../lib.php';

$MAP = [
    209 => [648, 'story-hero__img', 'Hero — brand story'],
    219 => [649, 'feature-media__img', 'Feature 1'],
    225 => [650, 'feature-media__img', 'Feature 2'],
    231 => [651, 'feature-media__img', 'Feature 3'],
    237 => [652, 'feature-media__img', 'Feature 4'],
];

$t = \Breakdance\Data\get_tree(123);
$root = $t['root'];
oxy_nid(oxy_max_id_r($root));   // seed past existing ids or new nodes collide

$replaced = 0;
$walk = function (&$node) use (&$walk, $MAP, &$replaced) {
    if (!empty($node['children'])) {
        foreach ($node['children'] as $i => &$child) {
            $cid = $child['id'] ?? null;
            if ($cid !== null && isset($MAP[$cid])) {
                [$att, $class, $alt] = $MAP[$cid];
                $child = oxy_image($att, 'full', [$class], $alt);
                $replaced++;
            } else {
                $walk($child);
            }
        }
        unset($child);
    }
};
$walk($root);

if ($replaced !== count($MAP)) { fwrite(STDERR, "WARN: replaced $replaced of " . count($MAP) . " placeholders\n"); }
oxy_write_tree(123, $root['children']);
echo "OK: replaced $replaced placeholders with images on #123\n";

/* fit + overlay + hover CSS — appended once (marker-guarded) to the global stylesheet */
$MARKER = 'EXAMPLE: story page images';
$CSS = "\n/* === {$MARKER} === */\n"
. ".breakdance .story-hero__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;}\n"
. ".breakdance .story-hero::after{content:\"\";position:absolute;inset:0;z-index:1;background:linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.6));}\n"
. ".breakdance .feature-media{overflow:hidden;}\n"
. ".breakdance .feature-media__img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s cubic-bezier(.2,.6,.2,1);}\n"
. ".breakdance .feature-row:hover .feature-media__img{transform:scale(1.05);}\n";
$f = \Breakdance\Data\get_tree(15);
$mut = function (&$n) use (&$mut, $MARKER, $CSS) {
    if (($n['data']['type'] ?? '') === 'OxygenElements\\CssCode'
        && isset($n['data']['properties']['content']['content']['css_code'])) {
        $css =& $n['data']['properties']['content']['content']['css_code'];
        if (strpos($css, '.bde-div') !== false && strpos($css, $MARKER) === false) { $css .= $CSS; }
        unset($css);
    }
    foreach ($n['children'] as &$c) { $mut($c); } unset($c);
};
$r = $f['root']; $mut($r);
oxy_write_tree(15, $r['children']);
echo "OK: story-page image CSS added\n";
