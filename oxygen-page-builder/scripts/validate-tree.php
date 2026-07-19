<?php
/**
 * validate-tree.php — check a post's stored Oxygen tree against the builder's io-ts
 * requirements + known element traps, and optionally fetch the front-end.
 *
 * Usage:
 *   ./wp-eval.sh validate-tree.php <postId>          # validate stored tree
 *   ./wp-eval.sh validate-tree.php <postId> fetch    # + fetch permalink, report status/size/h1
 * (positional "fetch", not --fetch: wp-cli swallows unknown --flags itself)
 *
 * Run this after ANY programmatic tree write, before calling the work done.
 * (It cannot run the builder's actual io-ts — final proof is opening
 *  http://example.local?oxygen=builder&id=<postId> without a decoding error.)
 */

require __DIR__ . '/lib.php';

$postId = isset($args[0]) ? (int) $args[0] : 0;
if (!$postId) { echo "usage: validate-tree.php <postId> [--fetch]\n"; exit(1); }

$post = get_post($postId);
if (!$post) { echo "FAIL: post $postId does not exist\n"; exit(1); }
echo "Post $postId: \"{$post->post_title}\" ({$post->post_type}, {$post->post_status})\n";

// stored value is a JSON string {"tree_json_string":"{...}"} — unwrap without losing {} vs []
$raw = get_post_meta($postId, '_oxygen_data', true);
if (!$raw) { echo "FAIL: no _oxygen_data meta on post $postId\n"; exit(1); }
$wrapper = is_string($raw) ? json_decode($raw, true) : (array) $raw;
$treeJson = $wrapper['tree_json_string'] ?? null;
if (!is_string($treeJson)) { echo "FAIL: _oxygen_data has no tree_json_string (wrong write path? must use \\Breakdance\\Data\\set_meta)\n"; exit(1); }

$res = oxy_validate_tree_json($treeJson, true);
foreach ($res['errors']   as $e) { echo "ERROR:   $e\n"; }
foreach ($res['warnings'] as $w) { echo "warning: $w\n"; }

// the selectors STORE can break the builder site-wide independently of any tree
$selRes = oxy_validate_selectors();
foreach ($selRes['errors'] as $e) { echo "ERROR:   selectors store: $e\n"; $res['errors'][] = "selectors: $e"; }

// engine must be able to read it back
$tree = \Breakdance\Data\get_tree($postId);
if ($tree === false) { echo "ERROR:   \\Breakdance\\Data\\get_tree() returned false — renderer cannot read this tree\n"; $res['errors'][] = 'get_tree false'; }
else {
    $count = 0;
    $walk = function ($n) use (&$walk, &$count) { $count++; foreach (($n['children'] ?? []) as $c) { $walk($c); } };
    $walk($tree['root'] ?? []);
    echo "tree OK for renderer: " . ($count - 1) . " nodes\n";
}

if (in_array('fetch', $args, true)) {
    $url = get_permalink($postId);
    if (!$url) { echo "fetch: no permalink (templates aren't public — preview by copying the tree onto a throwaway page)\n"; }
    else {
        $r = wp_remote_get($url, ['timeout' => 20, 'sslverify' => false]);
        if (is_wp_error($r)) { echo "fetch FAIL: " . $r->get_error_message() . "\n"; }
        else {
            $code = wp_remote_retrieve_response_code($r);
            $body = (string) wp_remote_retrieve_body($r);
            $h1 = preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $body, $m) ? trim(wp_strip_all_tags($m[1])) : '(none)';
            echo "fetch: HTTP $code, " . strlen($body) . " bytes, first <h1>: $h1\n";
            if ($code !== 200 || strlen($body) < 2000) { echo "ERROR:   front-end looks broken (non-200 or suspiciously small)\n"; $res['errors'][] = 'fetch'; }
        }
    }
}

echo $res['errors'] ? "RESULT: INVALID (" . count($res['errors']) . " error(s))\n" : "RESULT: VALID (io-ts invariants + trap checks pass)\n";
echo "final proof: open http://example.local?oxygen=builder&id=$postId in the builder\n";
exit($res['errors'] ? 2 : 0);
