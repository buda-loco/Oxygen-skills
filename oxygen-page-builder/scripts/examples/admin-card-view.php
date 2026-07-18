<?php
/**
 * Plugin Name:  Oxy Admin Views — Cards ⇄ List
 * Description:  Adds a card-grid view (big featured image) alongside the normal
 *               list table on image-driven post types, with vertical/horizontal
 *               layout and fill/fit image options. Remembers each admin's choices
 *               per post type. Complements ACF + core WordPress.
 * Version:      1.2.0
 * Author:       (your studio)
 * License:      GPL-2.0-or-later
 *
 * Reusable + de-branded: enable it for any set of post types via the
 * `oxy_admin_views_post_types` filter (defaults to every custom, image-driven
 * CPT). No per-type code. Per-type defaults via `oxy_admin_views_default_{key}`.
 * Sibling standard: the featured-image list column (df-design-system/content.php
 * · oxygen skill scripts/examples/admin-thumb-columns.php).
 *
 * @package Oxy_Admin_Views
 */

declare(strict_types=1);

namespace OxyAdminViews;

if (!defined('ABSPATH')) {
    exit;
}

const NONCE = 'oxy_admin_views';

/** Preference keys → [allowed values (default first), user-meta key]. */
const PREFS = [
    'view'   => ['list', 'cards'],       // list table  | card grid
    'orient' => ['vertical', 'horizontal'], // image on top | image on the left
    'fit'    => ['fill', 'fit'],          // cover (crop) | contain (whole image)
];
const META = [
    'view'   => 'oxy_av_view',
    'orient' => 'oxy_av_orient',
    'fit'    => 'oxy_av_fit',
];

/**
 * Post types that get the toggle. Default: every CUSTOM post type that supports
 * a featured image (card view is picture-first, so built-in post/page and
 * attachments are excluded). Filterable.
 */
function types(): array
{
    $t = [];
    foreach (get_post_types(['_builtin' => false], 'objects') as $pt) {
        if ($pt->name !== 'attachment' && post_type_supports($pt->name, 'thumbnail')) {
            $t[] = $pt->name;
        }
    }
    return (array) apply_filters('oxy_admin_views_post_types', $t);
}

/** The post type of the current list screen, or '' if this isn't one of ours. */
function current_pt(): string
{
    if (!function_exists('get_current_screen')) {
        return '';
    }
    $s = get_current_screen();
    if (!$s || $s->base !== 'edit') {
        return '';
    }
    return in_array($s->post_type, types(), true) ? (string) $s->post_type : '';
}

/**
 * Remembered value for a preference on a post type. Falls back to a per-type
 * default (filterable — e.g. a logo CPT defaults 'fit' so logos aren't cropped),
 * then the built-in default.
 */
function pref(string $pt, string $key): string
{
    $allowed = PREFS[$key];
    $default = (string) apply_filters("oxy_admin_views_default_{$key}", $allowed[0], $pt);
    if (!in_array($default, $allowed, true)) {
        $default = $allowed[0];
    }
    $val = (string) get_user_meta(get_current_user_id(), META[$key] . '_' . $pt, true);
    return in_array($val, $allowed, true) ? $val : $default;
}

/** Persist a choice (AJAX, nonce-guarded, validated against types() + PREFS). */
add_action('wp_ajax_' . NONCE, function (): void {
    check_ajax_referer(NONCE);
    $pt  = sanitize_key((string) ($_POST['pt'] ?? ''));
    $key = sanitize_key((string) ($_POST['key'] ?? ''));
    $val = sanitize_key((string) ($_POST['value'] ?? ''));
    if ($pt !== '' && in_array($pt, types(), true)
        && isset(PREFS[$key]) && in_array($val, PREFS[$key], true)) {
        update_user_meta(get_current_user_id(), META[$key] . '_' . $pt, $val);
        wp_send_json_success();
    }
    wp_send_json_error();
});

/** Tag <body> with the active view + orientation + fit so CSS renders flash-free. */
add_filter('admin_body_class', function (string $classes): string {
    $pt = current_pt();
    if ($pt !== '') {
        $classes .= ' oxy-av oxy-av--' . pref($pt, 'view')
                  . ' oxy-av-o--' . pref($pt, 'orient')
                  . ' oxy-av-f--' . pref($pt, 'fit');
    }
    return $classes;
});

/** Styles for the segmented controls + card grid (only on our list screens). */
add_action('admin_head', function (): void {
    if (current_pt() === '') {
        return;
    }
    echo '<style>
    /* -- segmented controls (clean, no WP .button chrome) -- */
    .oxy-av-switch{display:inline-flex;margin-left:10px;vertical-align:middle;
      border:1px solid #c3c4c7;border-radius:6px;overflow:hidden;background:#fff;
      box-shadow:0 1px 0 rgba(0,0,0,.04)}
    .oxy-av-seg{appearance:none;margin:0;border:0;border-left:1px solid #dcdcde;cursor:pointer;
      display:inline-flex;align-items:center;justify-content:center;width:40px;height:32px;
      background:#fff;color:#50575e;transition:background .1s,color .1s}
    .oxy-av-seg:first-child{border-left:0}
    .oxy-av-seg:hover{background:#f6f7f7;color:#1d2327}
    .oxy-av-seg:focus-visible{outline:2px solid #2271b1;outline-offset:-2px}
    .oxy-av-seg.is-active{background:#2271b1;color:#fff}
    .oxy-av-seg .dashicons{font-size:18px;width:18px;height:18px;line-height:18px}
    body:not(.oxy-av--cards) .oxy-av-switch--card-only{display:none} /* orientation/fit: cards only */

    /* -- grid (cards mode hides the table + its bottom toolbar; top stays for paging) -- */
    .oxy-av-grid{display:none;gap:18px;margin:16px 0 24px;align-items:stretch}
    body.oxy-av--cards .oxy-av-grid{display:grid}
    body.oxy-av--cards .wp-list-table,
    body.oxy-av--cards .tablenav.bottom{display:none}
    .oxy-av-empty{grid-column:1/-1;color:#646970;padding:28px;text-align:center;
      border:1px dashed #c3c4c7;border-radius:8px}

    /* -- card (uniform size; frame fixed proportion, body fixed height) -- */
    .oxy-av-card{box-sizing:border-box;position:relative;background:#fff;border:1px solid #c3c4c7;
      border-radius:8px;overflow:hidden;display:flex;box-shadow:0 1px 2px rgba(0,0,0,.06);
      transition:box-shadow .12s,border-color .12s}
    .oxy-av-card *{box-sizing:border-box}
    .oxy-av-card:hover{border-color:#8c8f94;box-shadow:0 3px 8px rgba(0,0,0,.10)}
    .oxy-av-card__frame{flex:none;display:flex;align-items:center;justify-content:center;
      overflow:hidden;background:#f0f0f1}
    .oxy-av-card__frame img{width:100%;height:100%;display:block}
    .oxy-av-card__none{color:#a7aaad;font-size:12px}
    .oxy-av-card__body{flex:1 1 auto;min-width:0;padding:11px 13px 12px;
      display:flex;flex-direction:column}
    .oxy-av-card__title{font-size:13px;font-weight:600;line-height:1.35;margin:0 0 6px;
      height:2.7em;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;
      -webkit-box-orient:vertical}
    .oxy-av-card__title a{text-decoration:none}
    .oxy-av-card__meta{display:flex;align-items:center;gap:8px;color:#646970;font-size:12px;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .oxy-av-badge{display:inline-block;font-size:11px;padding:1px 8px;border-radius:10px;
      background:#e7e7ea;color:#3c434a}
    .oxy-av-badge--draft{background:#fcf0d3;color:#8a6d1a}
    .oxy-av-badge--pending{background:#e0ecf5;color:#215d8a}
    .oxy-av-badge--private,.oxy-av-badge--future{background:#e6e6fa;color:#4a3f8a}
    .oxy-av-card__link{position:absolute;inset:0;z-index:1;text-indent:-9999px;overflow:hidden}
    .oxy-av-card__title a,.oxy-av-card__actions a{position:relative;z-index:2}
    .oxy-av-card__actions{margin-top:auto;padding-top:8px;font-size:12px;opacity:0;
      transition:opacity .12s}
    .oxy-av-card:hover .oxy-av-card__actions,
    .oxy-av-card:focus-within .oxy-av-card__actions{opacity:1}
    .oxy-av-card__actions a{color:#2271b1;text-decoration:none}
    .oxy-av-card__actions .trash a{color:#b32d2e}
    .oxy-av-card__actions span[aria-hidden]{color:#c3c4c7;margin:0 3px}

    /* -- FILL (cover): image fills the frame edge-to-edge, cropped, no wasted space -- */
    body.oxy-av-f--fill .oxy-av-card__frame img{object-fit:cover}
    /* -- FIT (contain): whole image on a chequerboard so pale/transparent logos show -- */
    body.oxy-av-f--fit .oxy-av-card__frame{padding:12px;background-color:#eceff1;
      background-image:linear-gradient(45deg,#dce1e5 25%,transparent 25%),
        linear-gradient(-45deg,#dce1e5 25%,transparent 25%),
        linear-gradient(45deg,transparent 75%,#dce1e5 75%),
        linear-gradient(-45deg,transparent 75%,#dce1e5 75%);
      background-size:18px 18px;background-position:0 0,0 9px,9px -9px,-9px 0}
    body.oxy-av-f--fit .oxy-av-card__frame img{object-fit:contain}

    /* -- VERTICAL: image on top (fixed 3:2), text below -- */
    body.oxy-av-o--vertical .oxy-av-grid{grid-template-columns:repeat(auto-fill,minmax(210px,1fr))}
    body.oxy-av-o--vertical .oxy-av-card{flex-direction:column}
    body.oxy-av-o--vertical .oxy-av-card__frame{width:100%;aspect-ratio:3/2;
      border-bottom:1px solid #dcdcde}

    /* -- HORIZONTAL: square image on the left (fixed), text on the right -- */
    body.oxy-av-o--horizontal .oxy-av-grid{grid-template-columns:repeat(auto-fill,minmax(330px,1fr))}
    body.oxy-av-o--horizontal .oxy-av-card{flex-direction:row}
    body.oxy-av-o--horizontal .oxy-av-card__frame{width:132px;height:132px;
      border-right:1px solid #dcdcde}
    body.oxy-av-o--horizontal .oxy-av-card__body{justify-content:center}
    body.oxy-av-o--horizontal .oxy-av-card__actions{margin-top:8px}
    </style>';
});

/** Render the card grid from the CURRENT list query + wire the switches. */
add_action('admin_footer', function (): void {
    $pt = current_pt();
    if ($pt === '') {
        return;
    }

    global $wp_query;
    $posts = $wp_query->posts ?? [];

    ob_start();
    echo '<div id="oxy-av-grid" class="oxy-av-grid" role="list">';
    if (!$posts) {
        echo '<p class="oxy-av-empty">' . esc_html__('Nothing to show here yet.', 'oxy-admin-views') . '</p>';
    }
    foreach ($posts as $p) {
        $id     = (int) $p->ID;
        $edit   = get_edit_post_link($id);
        $title  = get_the_title($id) ?: __('(no title)', 'oxy-admin-views');
        $status = get_post_status($id);
        // 'large' so FILL/cover stays crisp when the frame is wide
        $img    = get_the_post_thumbnail($id, 'large', ['alt' => '']);

        echo '<div class="oxy-av-card" role="listitem">';
        if ($edit) {
            echo '<a class="oxy-av-card__link" href="' . esc_url($edit) . '" tabindex="-1" aria-hidden="true">'
               . esc_html($title) . '</a>';
        }
        echo '<div class="oxy-av-card__frame">'
           . ($img ?: '<span class="oxy-av-card__none">' . esc_html__('No image', 'oxy-admin-views') . '</span>')
           . '</div>';

        echo '<div class="oxy-av-card__body">';
        echo '<div class="oxy-av-card__title">'
           . ($edit ? '<a href="' . esc_url($edit) . '">' . esc_html($title) . '</a>' : esc_html($title))
           . '</div>';

        echo '<div class="oxy-av-card__meta">';
        if ($status !== 'publish') {
            $obj = get_post_status_object($status);
            echo '<span class="oxy-av-badge oxy-av-badge--' . esc_attr($status) . '">'
               . esc_html($obj->label ?? ucfirst($status)) . '</span>';
        }
        echo '<span>' . esc_html(get_the_date('', $id)) . '</span>';
        echo '</div>';

        echo '<div class="oxy-av-card__actions">';
        $acts = [];
        if ($edit) {
            $acts[] = '<span class="edit"><a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'oxy-admin-views') . '</a></span>';
        }
        if ($status === 'publish' && ($view = get_permalink($id))) {
            $acts[] = '<span class="view"><a href="' . esc_url($view) . '" target="_blank" rel="noopener">' . esc_html__('View', 'oxy-admin-views') . '</a></span>';
        }
        if (current_user_can('delete_post', $id) && ($trash = get_delete_post_link($id))) {
            $acts[] = '<span class="trash"><a href="' . esc_url($trash) . '">' . esc_html__('Trash', 'oxy-admin-views') . '</a></span>';
        }
        echo implode('<span aria-hidden="true">·</span>', $acts);
        echo '</div>';

        echo '</div>'; // body
        echo '</div>'; // card
    }
    echo '</div>';
    $grid = ob_get_clean();

    $cfg = [
        'pt'     => $pt,
        'ajax'   => admin_url('admin-ajax.php'),
        'nonce'  => wp_create_nonce(NONCE),
        'action' => NONCE,
        'grid'   => $grid,
        'i18n'   => [
            'list'       => __('List view', 'oxy-admin-views'),
            'cards'      => __('Card view', 'oxy-admin-views'),
            'vertical'   => __('Vertical cards', 'oxy-admin-views'),
            'horizontal' => __('Horizontal cards', 'oxy-admin-views'),
            'fill'       => __('Fill frame (crop image)', 'oxy-admin-views'),
            'fit'        => __('Fit whole image', 'oxy-admin-views'),
        ],
    ];
    ?>
    <script>
    (function () {
        var C = <?php echo wp_json_encode($cfg); ?>;
        var form = document.getElementById('posts-filter');
        if (!form) { return; }

        var top = form.querySelector('.tablenav.top');
        var holder = document.createElement('div');
        holder.innerHTML = C.grid;
        var grid = holder.firstElementChild;
        if (top && grid) { top.insertAdjacentElement('afterend', grid); }

        var body = document.body;
        function save(key, value) {
            var fd = new FormData();
            fd.append('action', C.action);
            fd.append('_ajax_nonce', C.nonce);
            fd.append('pt', C.pt);
            fd.append('key', key);
            fd.append('value', value);
            fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: fd });
        }

        // opts = [[value, dashicon, label], …]; onSet flips body classes, isActive reads them
        function makeSwitch(cls, key, opts, isActive, onSet) {
            var sw = document.createElement('span');
            sw.className = 'oxy-av-switch ' + cls;
            opts.forEach(function (o) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'oxy-av-seg';
                b.dataset.v = o[0];
                b.title = o[2];
                b.setAttribute('aria-label', o[2]);
                b.innerHTML = '<span class="dashicons ' + o[1] + '"></span>';
                sw.appendChild(b);
            });
            function sync() {
                sw.querySelectorAll('.oxy-av-seg').forEach(function (b) {
                    b.classList.toggle('is-active', isActive(b.dataset.v));
                });
            }
            sync();
            sw.addEventListener('click', function (e) {
                var b = e.target.closest('.oxy-av-seg');
                if (!b) { return; }
                onSet(b.dataset.v);
                sync();
                save(key, b.dataset.v);
            });
            return { el: sw, sync: sync };
        }

        var orientSw, fitSw;
        var viewSw = makeSwitch('oxy-av-switch--view', 'view', [
            ['list',  'dashicons-list-view', C.i18n.list],
            ['cards', 'dashicons-grid-view', C.i18n.cards]
        ], function (v) { return body.classList.contains('oxy-av--' + v); }, function (v) {
            body.classList.toggle('oxy-av--cards', v === 'cards');
            body.classList.toggle('oxy-av--list', v !== 'cards');
            orientSw.sync(); fitSw.sync();
        });

        orientSw = makeSwitch('oxy-av-switch--orient oxy-av-switch--card-only', 'orient', [
            ['vertical',   'dashicons-grid-view', C.i18n.vertical],
            ['horizontal', 'dashicons-menu-alt',  C.i18n.horizontal]
        ], function (v) { return body.classList.contains('oxy-av-o--' + v); }, function (v) {
            body.classList.toggle('oxy-av-o--vertical', v === 'vertical');
            body.classList.toggle('oxy-av-o--horizontal', v === 'horizontal');
        });

        fitSw = makeSwitch('oxy-av-switch--fit oxy-av-switch--card-only', 'fit', [
            ['fill', 'dashicons-fullscreen-alt',      C.i18n.fill],
            ['fit',  'dashicons-fullscreen-exit-alt', C.i18n.fit]
        ], function (v) { return body.classList.contains('oxy-av-f--' + v); }, function (v) {
            body.classList.toggle('oxy-av-f--fill', v === 'fill');
            body.classList.toggle('oxy-av-f--fit', v === 'fit');
        });

        var h1 = document.querySelector('.wrap .wp-heading-inline');
        if (h1) {
            h1.insertAdjacentElement('afterend', fitSw.el);
            h1.insertAdjacentElement('afterend', orientSw.el);
            h1.insertAdjacentElement('afterend', viewSw.el);
        }
    })();
    </script>
    <?php
});
