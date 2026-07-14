<?php
/**
 * Add a clickable dynamic Featured Image to the post-card component (#628), as the FIRST child
 * of .post-card (#103), before .post-card__body. Uses the CAPTURED dynamic shapes:
 *   - WrapperLink  content.content.link  {type:url, url:[shortcode], dynamicMeta:{...}}
 *   - Image        content.image         {from:url, url:[shortcode], url_dynamic_meta:{...}}
 * Field: post_featured_image_url (returnTypes ['url']).
 */
require __DIR__ . '/../lib.php';

$POST_ID = 628;
$t = \Breakdance\Data\get_tree($POST_ID);
$root = $t['root'];

// seed the id counter past the current max so new nodes don't collide with #100..#105
$max = oxy_max_id_r($root);
oxy_nid($max);

$permaMeta = ['field' => 'post_permalink', 'shortcode' => "[breakdance_dynamic field='post_permalink']", 'attributes' => []];
$imgMeta   = ['field' => 'post_featured_image_url', 'shortcode' => "[breakdance_dynamic field='post_featured_image_url']", 'attributes' => []];

$image = oxy_el('OxygenElements\\Image', [
    'content'  => ['image' => [
        'from'             => 'url',
        'url'              => "[breakdance_dynamic field='post_featured_image_url']",
        'url_dynamic_meta' => $imgMeta,
        'lazy_load'        => true,
        'alt'              => 'custom',
        'custom_alt'       => "[breakdance_dynamic field='post_title']",
    ]],
    'settings' => ['advanced' => ['classes' => ['post-card__image']]],
]);

$mediaLink = oxy_el('EssentialElements\\WrapperLink', [
    'content'  => ['content' => ['link' => [
        'type'        => 'url',
        'url'         => "[breakdance_dynamic field='post_permalink']",
        'dynamicMeta' => $permaMeta,
    ]]],
    'settings' => ['advanced' => ['classes' => ['post-card__media-link']]],
], [$image]);

// insert as first child of #103 (.post-card)
$inserted = false;
$walk = function (&$n) use (&$walk, $mediaLink, &$inserted) {
    if (($n['id'] ?? null) === 103) {
        array_unshift($n['children'], $mediaLink);
        $inserted = true;
        return;
    }
    foreach ($n['children'] as &$c) { $walk($c); if ($inserted) return; }
    unset($c);
};
foreach ($root['children'] as &$c) { $walk($c); if ($inserted) break; }
unset($c);

if (!$inserted) { fwrite(STDERR, "ERROR: node #103 not found\n"); exit(1); }

oxy_write_tree($POST_ID, $root['children']);
echo "OK: featured image + media link added to #628 (image id {$image['id']}, link id {$mediaLink['id']}).\n";
