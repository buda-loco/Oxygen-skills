<?php
/**
 * Reusable "Latest from the blog" component (oxygen_block): a native Repeated Block (PostsLoop)
 * showing the 3 latest posts via component #628 (post-card, with clickable featured image), plus a
 * heading and a "Ver todas las notas" CTA. Meant to be dropped at the bottom of PDP/PLP (and any
 * page) via an OxygenElements\Component reference to drive internal traffic to the blog.
 *
 * Prints the new component id on the last line (BLOCK_ID=NNN) for the placement script.
 */
require __DIR__ . '/../lib.php';

// idempotent: reuse if it already exists
$existing = get_posts([
    'post_type'   => 'oxygen_block',
    'title'       => 'Componente · Latest from the blog',
    'post_status' => 'publish',
    'numberposts' => 1,
    'fields'      => 'ids',
]);
$blockId = $existing ? (int) $existing[0]
    : (int) wp_insert_post(['post_type' => 'oxygen_block', 'post_status' => 'publish',
        'post_title' => 'Componente · Latest from the blog']);

$loop = oxy_el('OxygenElements\\PostsLoop', [
    'content' => [
        'query' => ['query' => [
            'active' => 'custom',
            'text'   => 'post_type=post',
            'custom' => [
                'source' => 'post_types', 'postsPerPage' => 3, 'conditions' => [[[]]],
                'totalPosts' => null, 'ignoreStickyPosts' => false, 'ignoreCurrentPost' => false,
                'postTypes' => ['post'], 'orderBy' => 'date', 'order' => 'DESC', 'date' => 'all',
                'beforeDate' => null, 'afterDate' => null, 'offset' => null,
                'acfField' => null, 'metaboxField' => null,
            ],
            'php' => "return ['post_type' => 'post'];",
        ]],
        'pagination'     => ['pagination' => 'none'],
        'filter_bar'     => ['enable' => false, 'all_filter' => true, 'hide_uncategorized' => true],
        'repeated_block' => ['global_block' => 628, 'advanced' => ['alternates' => []]],
    ],
    'settings' => ['advanced' => ['classes' => ['post-loop-grid']]],
]);

$tree = [
    oxy_div([
        oxy_div([ oxy_text('FROM THE BLOG', 'h2') ], ['section-heading']),
        $loop,
        oxy_div([ oxy_button('VIEW ALL POSTS', '/blog/', ['btn', 'btn--accent']) ], ['latest-posts__foot']),
    ], ['container', 'section', 'latest-posts']),
];

oxy_write_tree($blockId, $tree);

/* scoped CSS (grid itself reuses the existing .post-loop-grid rules) */
$MARKER = 'Acme: latest posts block';
$CSS = "\n/* === {$MARKER} === */\n"
. ".breakdance .latest-posts{border-top:1px solid var(--c-gray-200,#e5e5e5);}\n"
. ".breakdance .latest-posts .section-heading{text-align:center;margin-bottom:var(--sp-8,32px);}\n"
. ".breakdance .latest-posts__foot{text-align:center;margin-top:var(--sp-8,32px);}\n";
$t = \Breakdance\Data\get_tree(15);
$mut = function (&$n) use (&$mut, $MARKER, $CSS) {
    if (($n['data']['type'] ?? '') === 'OxygenElements\\CssCode'
        && isset($n['data']['properties']['content']['content']['css_code'])) {
        $css =& $n['data']['properties']['content']['content']['css_code'];
        if (strpos($css, '.bde-div') !== false && strpos($css, $MARKER) === false) { $css .= $CSS; }
        unset($css);
    }
    foreach ($n['children'] as &$c) { $mut($c); } unset($c);
};
$r = $t['root']; $mut($r);
oxy_write_tree(15, $r['children']);

echo "OK: latest-posts block built.\nBLOCK_ID={$blockId}\n";
