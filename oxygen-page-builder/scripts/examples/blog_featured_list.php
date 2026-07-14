<?php
/**
 * Blog listing (#602) -> vertical list with a FEATURED first post.
 *  1. Rename the #602 loop class post-loop-grid -> post-loop-featured so it no longer shares
 *     styling with the related-posts grid on the single post (#574 keeps post-loop-grid = 3-col).
 *  2. Append the authoritative image + vertical/featured CSS block to footer #15 (idempotent marker).
 *
 * DOM (verified live):
 *   .post-loop-featured(.bde-post-loop) > .bde-loop > article.bde-loop-item > .post-card
 *     > a.post-card__media-link > img.post-card__image
 *     > .post-card__body > a(title>h3.post-card__title) + .post-card__excerpt + a(Read more)
 */
require __DIR__ . '/../lib.php';

/* ---- 1. rename the loop class on #602 -------------------------------------- */
$t = \Breakdance\Data\get_tree(602);
$root = $t['root'];
$done = false;
$walk = function (&$n) use (&$walk, &$done) {
    if (($n['data']['type'] ?? '') === 'OxygenElements\\PostsLoop') {
        $cls =& $n['data']['properties']['settings']['advanced']['classes'];
        $cls = ['post-loop-featured'];
        unset($cls);
        $done = true;
        return;
    }
    foreach ($n['children'] as &$c) { $walk($c); if ($done) return; }
    unset($c);
};
foreach ($root['children'] as &$c) { $walk($c); if ($done) break; }
unset($c);
if (!$done) { fwrite(STDERR, "ERROR: loop node not found in #602\n"); exit(1); }
oxy_write_tree(602, $root['children']);
echo "OK: #602 loop class -> post-loop-featured\n";

/* ---- 2. append CSS to footer #15 ------------------------------------------- */
$MARKER = 'Acme: blog featured list v3';
$CSS = "\n/* === {$MARKER} === */\n"
// generic featured-image (both blog list + related grid)
. ".breakdance .post-card__media-link{display:block;overflow:hidden;background:var(--c-gray-100,#f2f2f2);}\n"
. ".breakdance .post-card__image{display:block;width:100%;height:100%;object-fit:cover;}\n"
// related-posts grid (#574): image sits on top of the vertical card
. ".breakdance .post-loop-grid .post-card{display:flex;flex-direction:column;}\n"
. ".breakdance .post-loop-grid .post-card__media-link{aspect-ratio:16/10;}\n"
// ---- blog listing: vertical stack ----
. ".breakdance .post-loop-featured{display:block!important;}\n"
. ".breakdance .post-loop-featured .bde-loop{display:flex!important;flex-direction:column;gap:var(--sp-6,24px);}\n"
. ".breakdance .post-loop-featured .bde-loop-item{width:100%!important;position:static!important;left:auto!important;top:auto!important;padding:0!important;margin:0!important;}\n"
// base row card (image left, body right)
. ".breakdance .post-loop-featured .post-card{display:grid;grid-template-columns:300px 1fr;gap:0;align-items:stretch;height:auto;position:relative;}\n"
. ".breakdance .post-loop-featured .post-card__media-link{min-height:210px;height:100%;aspect-ratio:auto;}\n"
. ".breakdance .post-loop-featured .post-card__body{justify-content:center;padding:var(--sp-6,24px);}\n"
. ".breakdance .post-loop-featured .post-card__title{font-size:22px;}\n"
// ---- featured first item ----
. ".breakdance .post-loop-featured .bde-loop-item:first-child .post-card{grid-template-columns:1.15fr 1fr;border-color:var(--c-ink,#1f2937);}\n"
. ".breakdance .post-loop-featured .bde-loop-item:first-child .post-card__media-link{min-height:400px;}\n"
. ".breakdance .post-loop-featured .bde-loop-item:first-child .post-card__title{font-size:clamp(26px,3vw,38px);}\n"
. ".breakdance .post-loop-featured .bde-loop-item:first-child .post-card__excerpt{font-size:17px;line-height:1.6;}\n"
. ".breakdance .post-loop-featured .bde-loop-item:first-child .post-card__body{padding:var(--sp-7,32px);}\n"
. ".breakdance .post-loop-featured .bde-loop-item:first-child .post-card::after{content:\"FEATURED\";position:absolute;top:0;left:0;z-index:2;background:var(--c-ink,#1f2937);color:#fff;font-family:var(--font-head,'Oswald',sans-serif);font-size:11px;letter-spacing:.12em;text-transform:uppercase;padding:6px 12px;}\n"
// responsive: stack image over text
. "@media(max-width:781px){\n"
. "  .breakdance .post-loop-featured .post-card,\n"
. "  .breakdance .post-loop-featured .bde-loop-item:first-child .post-card{grid-template-columns:1fr;}\n"
. "  .breakdance .post-loop-featured .post-card__media-link,\n"
. "  .breakdance .post-loop-featured .bde-loop-item:first-child .post-card__media-link{min-height:0;aspect-ratio:16/9;}\n"
. "}\n";

$t2 = \Breakdance\Data\get_tree(15);
$mut = function (&$n) use (&$mut, $MARKER, $CSS) {
    if (($n['data']['type'] ?? '') === 'OxygenElements\\CssCode'
        && isset($n['data']['properties']['content']['content']['css_code'])) {
        $css =& $n['data']['properties']['content']['content']['css_code'];
        if (strpos($css, '.bde-div') !== false && strpos($css, $MARKER) === false) { $css .= $CSS; }
        unset($css);
    }
    foreach ($n['children'] as &$c) { $mut($c); } unset($c);
};
$r2 = $t2['root']; $mut($r2);
oxy_write_tree(15, $r2['children']);
echo "OK: blog featured-list CSS appended to footer #15\n";
