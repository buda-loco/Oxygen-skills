<?php
/**
 * Admin featured-image columns — an Oxygen/ACF WORKFLOW STANDARD.
 *
 * Drop this in an mu-plugin (or your project plugin). EVERY post type that
 * supports a featured image gets a square thumbnail column in its list table,
 * so site admins can scan posts by picture — covers, logos, product shots —
 * instead of by title alone. It is generic: register a new image-driven CPT
 * and its column appears automatically, no per-type code.
 *
 * Why these choices (all deliberate):
 *  - Target = post_type_supports('thumbnail'), NOT public==true. A logo CPT is
 *    often non-public (e.g. a brand/logo CPT) yet still needs the column. Attachments excluded.
 *  - Source = 'medium' (UNCROPPED) + object-fit:contain. A cropped 'thumbnail'
 *    (150×150 hard crop) chops wide wordmarks ("The Ritz-Carlton" → "ITZ-CA").
 *    contain keeps the whole logo/cover; the tile backdrop fills the rest.
 *  - Chequerboard/tint backdrop + thin #c3c4c7 border (WordPress control grey) +
 *    6px radius: pale or transparent logos stay visible and it matches wp-admin.
 *  - Label defaults to "Cover"; filter it per type (brands → "Logo").
 */
declare(strict_types=1);

namespace OxyAdminThumb;

if (!defined('ABSPATH')) {
    exit;
}

/** Every post type that supports a featured image (attachments excluded). */
function types(): array
{
    $t = [];
    foreach (get_post_types([], 'names') as $pt) {
        if ($pt !== 'attachment' && post_type_supports($pt, 'thumbnail')) {
            $t[] = $pt;
        }
    }
    return (array) apply_filters('oxy_admin_thumb_types', $t);
}

/** Column header — default "Cover"; filter per type (e.g. a brand CPT → "Logo"). */
function label(string $pt): string
{
    return (string) apply_filters('oxy_admin_thumb_label', 'Cover', $pt);
}

/** Wire the column onto every qualifying post type (once, in admin). */
add_action('admin_init', function (): void {
    foreach (types() as $pt) {
        add_filter("manage_{$pt}_posts_columns", __NAMESPACE__ . '\\add_col');
        add_action("manage_{$pt}_posts_custom_column", __NAMESPACE__ . '\\render', 10, 2);
    }
});

/** Insert the image column right after the checkbox, before the title. */
function add_col(array $cols): array
{
    $pt  = get_current_screen()->post_type ?? '';
    $out = [];
    foreach ($cols as $key => $val) {
        $out[$key] = $val;
        if ($key === 'cb') {
            $out['oxy_thumb'] = label($pt);
        }
    }
    if (!isset($out['oxy_thumb'])) { // no cb column on this screen — prepend
        $out = ['oxy_thumb' => label($pt)] + $out;
    }
    return $out;
}

/** Render the square thumbnail (or a placeholder) for one row. */
function render(string $col, int $id): void
{
    if ($col !== 'oxy_thumb') {
        return;
    }
    $tid = get_post_thumbnail_id($id);
    if (!$tid) {
        echo '<span class="oxy-thumb oxy-thumb--empty" aria-hidden="true">—</span>';
        return;
    }
    echo '<span class="oxy-thumb">'
       . wp_get_attachment_image($tid, 'medium', false, [
            'class'   => 'oxy-thumb__img',
            'loading' => 'lazy',
            'alt'     => '',
         ])
       . '</span>';
}

/** Column styling: a square tile framed to match wp-admin, with a chequerboard
 *  backdrop so pale/transparent logos stay visible. Only on list screens. */
add_action('admin_head', function (): void {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'edit') {
        return;
    }
    echo '<style>
    .column-oxy_thumb{width:92px}
    .oxy-thumb{box-sizing:border-box;display:inline-block;width:72px;height:72px;padding:4px;
      border:1px solid #c3c4c7;border-radius:6px;overflow:hidden;background-color:#eceff1;
      background-image:linear-gradient(45deg,#dce1e5 25%,transparent 25%),
        linear-gradient(-45deg,#dce1e5 25%,transparent 25%),
        linear-gradient(45deg,transparent 75%,#dce1e5 75%),
        linear-gradient(-45deg,transparent 75%,#dce1e5 75%);
      background-size:16px 16px;background-position:0 0,0 8px,8px -8px,-8px 0;
      box-shadow:0 1px 2px rgba(0,0,0,.06)}
    .oxy-thumb__img{width:100%;height:100%;object-fit:contain;display:block}
    .oxy-thumb--empty{display:inline-flex;align-items:center;justify-content:center;
      background:#f6f7f7;background-image:none;color:#a7aaad;font-size:18px}
    </style>';
});

/* Per-type label override example (a brand's featured image IS its logo):
add_filter('oxy_admin_thumb_label', function (string $label, string $pt): string {
    return $pt === 'brand' ? 'Logo' : $label;
}, 10, 2);
*/
