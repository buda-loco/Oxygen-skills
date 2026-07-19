<?php
/**
 * Plugin Name:  Oxy Admin Views — Cards ⇄ List
 * Description:  Adds a card-grid view (big featured image) alongside the normal
 *               list table on image-driven post types, with vertical/horizontal
 *               layout and fill/fit image options, plus drag-to-reorder (menu_order)
 *               in BOTH views. Remembers each admin's choices per post type.
 *               Complements ACF + core WordPress.
 * Version:      1.3.0
 * Author:       (your studio)
 * License:      GPL-2.0-or-later
 *
 * Reusable + de-branded: enable it for any set of post types via the
 * `oxy_admin_views_post_types` filter (defaults to every custom, image-driven
 * CPT). No per-type code. Per-type defaults via `oxy_admin_views_default_{key}`.
 * Sibling standard: the featured-image list column example
 * (scripts/examples/admin-thumb-columns.php).
 *
 * @package Oxy_Admin_Views
 */

declare(strict_types=1);

namespace OxyAdminViews;

if (!defined('ABSPATH')) {
    exit;
}

const NONCE = 'oxy_admin_views';

/** Preference key → allowed values (default first). User meta is "oxy_av_{key}_{postType}". */
const PREFS = [
    'view'   => ['list', 'cards'],           // list table  | card grid
    'orient' => ['vertical', 'horizontal'],  // image on top | image on the left
    'fit'    => ['fill', 'fit'],             // cover (crop) | contain (whole image)
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
    $val = (string) get_user_meta(get_current_user_id(), "oxy_av_{$key}_{$pt}", true);
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
        update_user_meta(get_current_user_id(), "oxy_av_{$key}_{$pt}", $val);
        wp_send_json_success();
    }
    wp_send_json_error();
});

/* ============================================================================
 * Drag-to-reorder (menu_order) — works in BOTH the list table and the card grid.
 * menu_order is the usual front-end sort key for these hand-curated CPTs, so a
 * drop here just IS the public order — we only write menu_order + flush caches;
 * no front-end code changes. Generic: keys off types(), no per-site specifics.
 *
 *   list <tr id="post-N">  /  card [data-id=N]
 *        │  drag (jQuery UI Sortable — a dedicated handle, so links stay clickable)
 *        ▼  POST admin-ajax { action, _ajax_nonce, pt, ids[] }
 *   nonce? ─no─▶ die    edit_posts? ─no─▶ 403    pt ∈ types()? ─no─▶ 400
 *        │yes                 │yes                      │yes
 *        └──────────┬─────────┴───────────┬────────────┘
 *                   ▼  reorder_apply(pt, ids)
 *      $wpdb UPDATE menu_order = position WHERE ID=id AND post_type=pt ; clean_post_cache(id)
 * ==========================================================================*/

/**
 * Renumber menu_order to match $ids (0..N). Touches ONLY posts of $pt.
 * Direct $wpdb write on purpose: a pure ordering column doesn't need
 * wp_update_post's save_post/revision/listener churn — but because we bypass the
 * ORM we must clean_post_cache() each row by hand. Returns how many ids we saw.
 */
function reorder_apply(string $pt, array $ids): int
{
    global $wpdb;
    $ids = array_values(array_filter(array_map('absint', $ids)));
    foreach ($ids as $position => $id) {
        $wpdb->update($wpdb->posts, ['menu_order' => $position], ['ID' => $id, 'post_type' => $pt]);
        clean_post_cache($id);
    }
    return count($ids);
}

/**
 * The list request is "canonical" (safe to reorder) only when it shows the FULL
 * menu_order list — no search / month / status / taxonomy filter and no other
 * sort column. Dragging a filtered SUBSET would silently renumber the global
 * menu_order wrong, so drag is disabled there. Pure ($_GET-only) so it's unit
 * testable without a screen.
 */
function is_canonical_request(string $pt): bool
{
    if (!empty($_GET['s']) || !empty($_GET['m'])) {
        return false;
    }
    $orderby = (string) ($_GET['orderby'] ?? '');
    if ($orderby !== '' && $orderby !== 'menu_order') {
        return false;
    }
    $status = (string) ($_GET['post_status'] ?? '');
    if ($status !== '' && $status !== 'all') {
        return false;
    }
    foreach (get_object_taxonomies($pt) as $tax) {
        if (!empty($_GET[$tax])) {
            return false;
        }
    }
    return true;
}

/** Drag allowed = our screen + editor caps + canonical (unfiltered) request. */
function reorderable(): bool
{
    $pt = current_pt();
    if ($pt === '') {
        return false;
    }
    $obj = get_post_type_object($pt);
    if (!$obj || !current_user_can($obj->cap->edit_posts ?? 'edit_posts')) {
        return false;
    }
    return is_canonical_request($pt);
}

/**
 * Make our admin lists the canonical, reorderable view: sort by menu_order and
 * show every post (these curated CPTs are small) so a drag can reach anywhere.
 * Respects a user-chosen sort column — drag is simply off in that case.
 *   is_admin ∧ main_query ∧ post_type ∈ types() ∧ orderby ∈ {'', menu_order}
 *        └─▶ orderby=menu_order ASC, posts_per_page=-1
 */
add_action('pre_get_posts', function (\WP_Query $q): void {
    if (!is_admin() || !$q->is_main_query()) {
        return;
    }
    $pt = $q->get('post_type');
    if (!is_string($pt) || $pt === '' || !in_array($pt, types(), true)) {
        return;
    }
    $orderby = $q->get('orderby');
    if ($orderby === '' || $orderby === 'menu_order') {
        $q->set('orderby', 'menu_order');
        $q->set('order', 'ASC');
        $q->set('posts_per_page', -1);
    }
});

/** Persist a drag (nonce + editor capability + type guarded). */
add_action('wp_ajax_' . NONCE . '_reorder', function (): void {
    check_ajax_referer(NONCE);
    $pt = sanitize_key((string) ($_POST['pt'] ?? ''));
    if ($pt === '' || !in_array($pt, types(), true)) {
        wp_send_json_error(['message' => 'bad post type'], 400);
    }
    $obj = get_post_type_object($pt);
    if (!$obj || !current_user_can($obj->cap->edit_posts ?? 'edit_posts')) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }
    $ids = (array) ($_POST['ids'] ?? []);
    if (!$ids) {
        wp_send_json_error(['message' => 'no ids'], 400);
    }
    wp_send_json_success(['updated' => reorder_apply($pt, $ids)]);
});

/** Tag <body> with the active view + orientation + fit so CSS renders flash-free. */
add_filter('admin_body_class', function (string $classes): string {
    $pt = current_pt();
    if ($pt !== '') {
        $classes .= ' oxy-av oxy-av--' . pref($pt, 'view')
                  . ' oxy-av-o--' . pref($pt, 'orient')
                  . ' oxy-av-f--' . pref($pt, 'fit')
                  . (reorderable() ? ' oxy-av--reorderable' : '');
    }
    return $classes;
});

/** jQuery UI Sortable (bundled in wp-admin) powers the drag on our screens. */
add_action('admin_enqueue_scripts', function (): void {
    if (current_pt() !== '') {
        wp_enqueue_script('jquery-ui-sortable');
    }
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

    /* -- drag-to-reorder (list rows + cards) -- */
    .oxy-av-drag{cursor:grab;color:#a7aaad;display:inline-flex;vertical-align:middle;margin-right:6px}
    .oxy-av-drag:active{cursor:grabbing}
    .oxy-av-drag:hover{color:#50575e}
    .oxy-av-drag .dashicons{font-size:18px;width:18px;height:18px;line-height:18px}
    /* card handle pinned above the full-card overlay link so the card is draggable */
    .oxy-av-card .oxy-av-drag{position:absolute;top:6px;left:6px;z-index:3;margin:0;
      width:26px;height:26px;align-items:center;justify-content:center;
      background:rgba(255,255,255,.92);border:1px solid #dcdcde;border-radius:5px;
      box-shadow:0 1px 2px rgba(0,0,0,.08)}
    .oxy-av-placeholder{outline:2px dashed #2271b1;outline-offset:-2px;background:#f0f6fc!important}
    tr.oxy-av-placeholder > *{visibility:hidden}
    .ui-sortable-helper{box-shadow:0 8px 24px rgba(0,0,0,.18)}
    .oxy-av-saving{opacity:.55;pointer-events:none;transition:opacity .1s}
    .oxy-av-reorder-hint{color:#646970;font-size:12px;font-style:italic;margin:8px 0 0}
    body:not(.oxy-av--reorderable) .oxy-av-drag{display:none}
    </style>';
});

/** Render the card grid from the CURRENT list query + wire the switches. */
add_action('admin_footer', function (): void {
    $pt = current_pt();
    if ($pt === '') {
        return;
    }

    // Only the card view needs the grid; skip the per-post thumbnail work
    // entirely in list view (the common default). Switching view reloads, so
    // the grid is always server-rendered when it's actually shown.
    $grid = '';
    if (pref($pt, 'view') === 'cards') {
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

        echo '<div class="oxy-av-card" role="listitem" data-id="' . $id . '">';
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
    }

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
        var body = document.body;

        // inject the (cards-only) grid right after the top toolbar — keeps the
        // search box, filters and pagination usable in card mode
        var top = form.querySelector('.tablenav.top');
        if (top && C.grid) {
            var holder = document.createElement('div');
            holder.innerHTML = C.grid;
            top.insertAdjacentElement('afterend', holder.firstElementChild);
        }

        function save(key, value) {
            var fd = new FormData();
            fd.append('action', C.action);
            fd.append('_ajax_nonce', C.nonce);
            fd.append('pt', C.pt);
            fd.append('key', key);
            fd.append('value', value);
            return fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: fd });
        }

        // A segmented control whose options each map to body class `prefix + value`.
        // opts = [[value, dashicon, label], …]. `reload` switches (view) re-render
        // server-side after saving; the others just restyle the cards in place.
        function makeSwitch(cls, key, prefix, opts, reload) {
            var sw = document.createElement('span');
            sw.className = 'oxy-av-switch ' + cls;
            opts.forEach(function (o) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'oxy-av-seg';
                b.dataset.v = o[0];
                b.title = o[2];
                b.setAttribute('aria-label', o[2]);
                b.classList.toggle('is-active', body.classList.contains(prefix + o[0]));
                b.innerHTML = '<span class="dashicons ' + o[1] + '"></span>';
                sw.appendChild(b);
            });
            sw.addEventListener('click', function (e) {
                var b = e.target.closest('.oxy-av-seg');
                if (!b || b.classList.contains('is-active')) { return; }
                var v = b.dataset.v;
                if (reload) { save(key, v).then(function () { location.reload(); }); return; }
                opts.forEach(function (o) { body.classList.toggle(prefix + o[0], o[0] === v); });
                sw.querySelectorAll('.oxy-av-seg').forEach(function (x) {
                    x.classList.toggle('is-active', x.dataset.v === v);
                });
                save(key, v);
            });
            return sw;
        }

        var switches = [
            makeSwitch('oxy-av-switch--view', 'view', 'oxy-av--', [
                ['list',  'dashicons-list-view', C.i18n.list],
                ['cards', 'dashicons-grid-view', C.i18n.cards]
            ], true),
            makeSwitch('oxy-av-switch--orient oxy-av-switch--card-only', 'orient', 'oxy-av-o--', [
                ['vertical',   'dashicons-grid-view', C.i18n.vertical],
                ['horizontal', 'dashicons-menu-alt',  C.i18n.horizontal]
            ], false),
            makeSwitch('oxy-av-switch--fit oxy-av-switch--card-only', 'fit', 'oxy-av-f--', [
                ['fill', 'dashicons-fullscreen-alt',      C.i18n.fill],
                ['fit',  'dashicons-fullscreen-exit-alt', C.i18n.fit]
            ], false)
        ];

        var h1 = document.querySelector('.wrap .wp-heading-inline');
        if (h1) {
            switches.reverse().forEach(function (sw) { h1.insertAdjacentElement('afterend', sw); });
        }
    })();
    </script>
    <?php
});

/**
 * Drag wiring — runs at priority 11 so the card grid (injected by the priority-10
 * footer script above) already exists. Same handler drives both containers: the
 * list <tbody id="the-list"> of <tr>, and the <div id="oxy-av-grid"> of cards.
 * A dedicated grip handle initiates the drag, so row/card links stay clickable
 * (the card's whole surface is an overlay <a>, so it MUST have a handle).
 */
add_action('admin_footer', function (): void {
    if (current_pt() === '') {
        return;
    }
    if (!reorderable()) {
        // On our screen but filtered/sorted: say why drag is off, so it doesn't look broken.
        $hint = esc_js(__('Reordering is paused while the list is filtered, searched or sorted — clear those to drag.', 'oxy-admin-views'));
        echo "<script>(function(){var h=document.querySelector('.wrap .wp-heading-inline');"
           . "if(!h||document.querySelector('.oxy-av-reorder-hint'))return;var p=document.createElement('p');"
           . "p.className='oxy-av-reorder-hint';p.textContent='{$hint}';h.parentNode.insertBefore(p,h.nextSibling);})();</script>";
        return;
    }
    $cfg = [
        'pt'     => current_pt(),
        'ajax'   => admin_url('admin-ajax.php'),
        'nonce'  => wp_create_nonce(NONCE),
        'action' => NONCE . '_reorder',
        'drag'   => __('Drag to reorder', 'oxy-admin-views'),
    ];
    ?>
    <script>
    jQuery(function ($) {
        var C = <?php echo wp_json_encode($cfg); ?>;

        function idOf(el) {
            if (el.dataset && el.dataset.id) { return parseInt(el.dataset.id, 10); }
            var m = /(?:^|\s)post-(\d+)/.exec(el.id || '');
            return m ? parseInt(m[1], 10) : 0;
        }
        function addHandle(el, host) {
            if (el.querySelector('.oxy-av-drag')) { return; }
            var h = document.createElement('span');
            h.className = 'oxy-av-drag';
            h.title = C.drag;
            h.setAttribute('aria-hidden', 'true');
            h.innerHTML = '<span class="dashicons dashicons-menu"></span>';
            host.insertBefore(h, host.firstChild);
        }
        function persist($c, itemSel) {
            var ids = $c.children(itemSel).map(function () { return idOf(this); }).get()
                        .filter(function (n) { return n > 0; });
            if (!ids.length) { return; }
            var fd = new FormData();
            fd.append('action', C.action);
            fd.append('_ajax_nonce', C.nonce);
            fd.append('pt', C.pt);
            ids.forEach(function (id) { fd.append('ids[]', id); });
            $c.addClass('oxy-av-saving');
            fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function (r) { return r.json(); })
                .then(function () { $c.removeClass('oxy-av-saving'); })
                .catch(function () { location.reload(); }); // fall back to a truthful view
        }

        var base = { handle: '.oxy-av-drag', opacity: 0.85,
                     placeholder: 'oxy-av-placeholder', forcePlaceholderSize: true };

        // LIST — grip in the title cell; the row's Edit/View links keep working.
        var $list = $('#the-list');
        if ($list.children('tr').length) {
            $list.children('tr').each(function () {
                var cell = this.querySelector('td.column-title, td.title')
                        || this.querySelector('td:not(.check-column):not(.hidden)');
                if (cell) { addHandle(this, cell); }
            });
            $list.sortable($.extend({}, base, {
                items: '> tr', axis: 'y',
                helper: function (e, tr) { // keep cell widths while dragging a row
                    var $orig = tr.children();
                    var $clone = tr.clone();
                    $clone.children().each(function (i) { $(this).width($orig.eq(i).width()); });
                    return $clone;
                },
                update: function () { persist($list, '> tr'); }
            }));
        }

        // CARDS — grip pinned above the full-card overlay link.
        var $grid = $('#oxy-av-grid');
        if ($grid.length) {
            $grid.children('.oxy-av-card').each(function () { addHandle(this, this); });
            $grid.sortable($.extend({}, base, {
                items: '> .oxy-av-card',
                update: function () { persist($grid, '> .oxy-av-card'); }
            }));
        }
    });
    </script>
    <?php
}, 11);
