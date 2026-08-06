<?php
/**
 * Panel-expressibility audit of a LIVE site's registered selectors. Read-only.
 *
 * Runs oxy_panel_lint() (lib.php) over every saved selector's custom_css and
 * prints what should move to property groups. The write-time arm of the same
 * check fires inside oxy_selector() on every build run; this is the audit arm
 * for CSS that's already in the database.
 *
 * A selector whose custom CSS is all states/third-party/exempt prints nothing.
 * Debt is a per-selector warning with the fix named. Silence a legitimate
 * block by adding `/*panel-exempt: <reason>* /` inside it and re-running the
 * owning build script.
 *
 * Usage:  scripts/wp-eval.sh examples/lint-panel-css.php
 */

require __DIR__ . '/../lib.php';

$raw = get_option('oxygen_oxy_selectors_json_string');
$sels = json_decode($raw, true);
if (is_string($sels)) $sels = json_decode($sels, true);
$sels = $sels ?: [];

$findCss = function (array $arr) use (&$findCss): string {
    $css = '';
    foreach ($arr as $k => $v) {
        if (is_array($v)) $css .= $findCss($v);
        elseif ($k === 'custom_css' && is_string($v)) $css .= "\n" . $v;
    }
    return $css;
};

$total = 0; $dirty = 0;
foreach ($sels as $s) {
    $css = $findCss($s['properties'] ?? []);
    if (trim($css) === '') continue;
    $warnings = oxy_panel_lint($s['name'] ?? '?', $css);
    if (!$warnings) continue;
    $dirty++;
    foreach ($warnings as $w) { $total++; echo "⚠ $w\n"; }
}
printf("\n%d selector(s) with panel-expressible custom CSS, %d finding(s), of %d registered.\n",
    $dirty, $total, count($sels));
echo $total ? "Move each to its property group, or justify it in-place with /*panel-exempt: reason*/.\n"
            : "Clean — every custom_css block is states, third-party markup, or explicitly exempted.\n";
