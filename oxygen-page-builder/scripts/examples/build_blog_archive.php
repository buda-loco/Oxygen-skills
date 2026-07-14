<?php
/**
 * P2 / decision 4A: Blog archive.
 * 1. Create/assign a "Blog" posts page  -> gives /blog/ as the blog index URL (page_for_posts).
 * 2. Build an oxygen_template type `post-archives` (callback: (is_archive()||is_home())
 *    && !product/!woo) at priority 30 -> covers blog index + post category/tag/date/author,
 *    never touches product archives (PLP #521) or the static front page (#10, is_home()=false).
 * 3. Dynamic content-hero H1 + post-card grid + pagination + empty state (PhpCode = legit
 *    dynamic case, matching search #570 / PLP loop patterns).
 * 4. Brand CSS for .blog-grid/.post-card appended as an idempotent MARKED block to footer #15.
 * Idempotent: re-running reuses the existing page/template and skips the CSS if present.
 */
require __DIR__ . '/../lib.php';

/* ---- 1. posts page ------------------------------------------------------- */
$blog = get_page_by_path('blog');
if ($blog) { $blogId = $blog->ID; }
else {
    $blogId = wp_insert_post([
        'post_title'  => 'Blog',
        'post_name'   => 'blog',
        'post_status' => 'publish',
        'post_type'   => 'page',
        'post_content'=> '',
    ]);
}
update_option('page_for_posts', $blogId);
if (get_option('show_on_front') !== 'page') { update_option('show_on_front', 'page'); }

/* ---- 2. archive template post -------------------------------------------- */
$existing = get_posts(['post_type'=>'oxygen_template','name'=>'blog-archivo','post_status'=>'any','numberposts'=>1]);
if ($existing) { $tplId = $existing[0]->ID; }
else {
    $tplId = wp_insert_post([
        'post_title'  => 'Blog / Archivo (post-archives)',
        'post_name'   => 'blog-archivo',
        'post_status' => 'publish',
        'post_type'   => 'oxygen_template',
    ]);
}

/* ---- 3. tree ------------------------------------------------------------- */
$HERO = <<<'PHP'
<?php
if ( is_home() ) {
    $t = get_the_title( (int) get_option('page_for_posts') );
    if ( ! $t ) { $t = 'Blog'; }
} else {
    $t = wp_strip_all_tags( get_the_archive_title() );
}
echo '<h1>' . esc_html( $t ) . '</h1>';
PHP;

$LOOP = <<<'PHP'
<?php
echo '<section class="section container blog-archive">';
if ( have_posts() ) {
    echo '<div class="blog-grid">';
    while ( have_posts() ) { the_post();
        $cats  = get_the_category();
        $cat   = $cats ? esc_html( $cats[0]->name ) : '';
        $thumb = has_post_thumbnail()
            ? get_the_post_thumbnail( get_the_ID(), 'large', ['class' => 'post-card__img'] )
            : '<span class="ph">' . esc_html( get_the_title() ) . '</span>';
        $ex = wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_the_excerpt() ) ), 22 );
        echo '<a class="post-card" href="' . esc_url( get_permalink() ) . '">';
        echo   '<div class="post-card__media">' . $thumb . '</div>';
        echo   '<div class="post-card__body">';
        if ( $cat ) { echo '<span class="post-card__cat">' . $cat . '</span>'; }
        echo     '<h3 class="post-card__title">' . esc_html( get_the_title() ) . '</h3>';
        if ( $ex ) { echo '<p class="post-card__excerpt">' . esc_html( $ex ) . '</p>'; }
        echo     '<span class="post-card__date">' . esc_html( get_the_date() ) . '</span>';
        echo   '</div>';
        echo '</a>';
    }
    echo '</div>';
    $pag = paginate_links( ['type' => 'list'] );
    if ( $pag ) { echo '<div class="blog-pagination">' . $pag . '</div>'; }
    wp_reset_postdata();
} else {
    echo '<div class="aux-box"><p>No posts yet.</p></div>';
}
echo '</section>';
PHP;

oxy_write_tree($tplId, [
    oxy_div([ oxy_php($HERO) ], ['content-hero','container']),
    oxy_php($LOOP),
]);
oxy_template_settings($tplId, 'post-archives', 30);

/* ---- 4. brand CSS in footer #15 (idempotent marked block) ---------------- */
$MARKER = 'Acme: blog archive';
$CSS = "\n/* === {$MARKER} === */\n"
. ".breakdance .blog-archive{padding-block:var(--sp-8,48px);}\n"
. ".breakdance .blog-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--sp-6,24px);}\n"
. ".breakdance .post-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--c-gray-200,#e5e5e5);color:var(--c-ink,#1f2937);text-decoration:none;transition:border-color .2s var(--ease,ease);}\n"
. ".breakdance .post-card:hover{border-color:var(--c-accent,#0f766e);}\n"
. ".breakdance .post-card__media{aspect-ratio:16/10;background:var(--c-gray-100,#f2f2f2);display:flex;align-items:center;justify-content:center;overflow:hidden;}\n"
. ".breakdance .post-card__media img{width:100%;height:100%;object-fit:cover;}\n"
. ".breakdance .post-card__media .ph{padding:var(--sp-4,16px);font-family:var(--font-head,'Oswald');text-transform:uppercase;color:var(--c-gray-400,#9a9a9a);text-align:center;font-size:var(--fs-sm,14px);letter-spacing:.04em;}\n"
. ".breakdance .post-card__body{padding:var(--sp-5,20px);display:flex;flex-direction:column;gap:var(--sp-2,8px);flex:1;}\n"
. ".breakdance .post-card__cat{font-family:var(--font-head,'Oswald');font-size:var(--fs-2xs,11px);letter-spacing:var(--ls-wide,.08em);text-transform:uppercase;color:var(--c-accent-dark,#115e59);}\n"
. ".breakdance .post-card__title{font-family:var(--font-head,'Oswald');font-size:var(--fs-lg,20px);text-transform:uppercase;letter-spacing:.02em;margin:0;line-height:1.15;}\n"
. ".breakdance .post-card__excerpt{font-size:var(--fs-sm,14px);color:var(--c-gray-700,#555);margin:0;flex:1;}\n"
. ".breakdance .post-card__date{font-size:var(--fs-2xs,11px);letter-spacing:.05em;text-transform:uppercase;color:var(--c-gray-400,#9a9a9a);margin-top:var(--sp-2,8px);}\n"
. ".breakdance .blog-pagination{margin-top:var(--sp-8,48px);}\n"
. ".breakdance .blog-pagination ul{display:flex;gap:8px;list-style:none;padding:0;justify-content:center;flex-wrap:wrap;}\n"
. ".breakdance .blog-pagination a,.breakdance .blog-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 10px;border:1px solid var(--c-gray-300,#ccc);font-family:var(--font-head,'Oswald');text-decoration:none;color:var(--c-ink,#1f2937);}\n"
. ".breakdance .blog-pagination .current{background:var(--c-accent,#0f766e);border-color:var(--c-accent,#0f766e);}\n"
. "@media(max-width:1024px){.breakdance .blog-grid{grid-template-columns:repeat(2,1fr);}}\n"
. "@media(max-width:640px){.breakdance .blog-grid{grid-template-columns:1fr;}}\n";

$tree = \Breakdance\Data\get_tree(15);
$cssHit = false; $cssAlready = false;
$mut = function (&$n) use (&$mut, &$cssHit, &$cssAlready, $MARKER, $CSS) {
    if (($n['data']['type'] ?? '') === 'OxygenElements\\CssCode'
        && isset($n['data']['properties']['content']['content']['css_code'])) {
        $css =& $n['data']['properties']['content']['content']['css_code'];
        if (strpos($css, '.bde-div') !== false) {
            $cssHit = true;
            if (strpos($css, $MARKER) !== false) { $cssAlready = true; }
            else { $css .= $CSS; }
        }
        unset($css);
    }
    foreach ($n['children'] as &$c) { $mut($c); } unset($c);
};
$root = $tree['root']; $mut($root);
if ($cssHit && !$cssAlready) { oxy_write_tree(15, $root['children']); }

printf("OK: blog page #%d (/%s/), archive template #%d (post-archives, prio 30). CSS: %s.\n",
    $blogId, get_post_field('post_name', $blogId), $tplId,
    $cssAlready ? 'already present' : ($cssHit ? 'appended' : 'CSS NODE NOT FOUND'));
