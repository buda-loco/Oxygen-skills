<?php
/**
 * Plugin Name: Oxygen Skills Parallax
 * Description: Portable scroll-linked parallax. Mark any element with the class <code>oxs-plx</code>; configure via CSS custom properties <code>--plx-from</code>/<code>--plx-to</code>/<code>--plx-scale-from</code>/<code>--plx-scale-to</code> (inline style, element class, or Oxygen Variables). Two modes, switchable site-wide under Settings → Parallax: <strong>scroll-driven</strong> (scrubbed to scroll, reverses on scroll-up) and <strong>one-off</strong> (reveals in once, then holds). Per-element override via <code>oxs-plx--scroll</code> / <code>oxs-plx--oneoff</code>. Respects prefers-reduced-motion.
 * Version: 1.3.1
 * Author: zstudios
 * License: GPL-2.0-or-later
 *
 * MODE + ENGINE SELECTION:
 *
 *   global default (Settings → Parallax) → <html class="oxs-plx-mode-{scroll|oneoff}">
 *   per element: .oxs-plx--scroll / .oxs-plx--oneoff  (wins over the global default)
 *
 *   scroll-driven ─┬─ @supports(animation-timeline:view()) ─▶ parallax.css (compositor)
 *                  └─ else ─────────────────────────────────▶ parallax.js rAF fallback
 *   one-off ───────── parallax.js IntersectionObserver adds .oxs-plx--in once ─▶ CSS transition
 *
 *   both: @media (prefers-reduced-motion: reduce) → OFF
 */
if (!defined('ABSPATH')) { exit; }

const OXS_PLX_VER = '1.3.1';

/* ---- assets ---- */
add_action('wp_enqueue_scripts', function () {
    $base = plugin_dir_url(__FILE__);
    wp_enqueue_style('oxs-parallax', $base . 'assets/parallax.css', [], OXS_PLX_VER);
    wp_enqueue_script('oxs-parallax', $base . 'assets/parallax.js', [], OXS_PLX_VER, ['in_footer' => true, 'strategy' => 'defer']);
});

/* ---- global mode → <html> class (emitted first in <head>, before body paint). Default is OFF:
        a fresh install ships with parallax disabled until an admin opts in under Settings. ---- */
function oxs_plx_mode() {
    $m = get_option('oxs_plx_mode', 'off');
    return in_array($m, ['scroll', 'oneoff', 'off'], true) ? $m : 'off';
}
add_action('wp_head', function () {
    echo "<script>document.documentElement.classList.add('oxs-plx-mode-" . oxs_plx_mode() . "');</script>\n";
}, 0);

/* ---- Settings → Parallax : one global radio ---- */
add_action('admin_init', function () {
    register_setting('oxs_plx', 'oxs_plx_mode', [
        'type' => 'string',
        'sanitize_callback' => function ($v) { return in_array($v, ['scroll', 'oneoff', 'off'], true) ? $v : 'off'; },
        'default' => 'off',
    ]);
});
add_action('admin_menu', function () {
    add_options_page('Parallax', 'Parallax', 'manage_options', 'oxs-parallax', 'oxs_plx_settings_page');
});
function oxs_plx_settings_page() {
    if (!current_user_can('manage_options')) { return; }
    $mode = oxs_plx_mode();
    ?>
    <div class="wrap">
      <h1>Oxygen Skills Parallax</h1>
      <form method="post" action="options.php">
        <?php settings_fields('oxs_plx'); ?>
        <p>Site-wide default motion for every <code>oxs-plx</code> element. Override per element with the class <code>oxs-plx--off</code>, <code>oxs-plx--scroll</code>, or <code>oxs-plx--oneoff</code>.</p>
        <table class="form-table" role="presentation"><tbody>
          <tr>
            <th scope="row">Default mode</th>
            <td>
              <fieldset>
                <label style="display:block;margin-bottom:8px">
                  <input type="radio" name="oxs_plx_mode" value="off" <?php checked($mode, 'off'); ?>>
                  <strong>Off</strong> — parallax disabled everywhere (default). Elements stay static unless a class opts them in.
                </label>
                <label style="display:block;margin-bottom:8px">
                  <input type="radio" name="oxs_plx_mode" value="scroll" <?php checked($mode, 'scroll'); ?>>
                  <strong>Scroll-driven</strong> — motion is scrubbed to scroll position and <em>reverses</em> when you scroll back up.
                </label>
                <label style="display:block">
                  <input type="radio" name="oxs_plx_mode" value="oneoff" <?php checked($mode, 'oneoff'); ?>>
                  <strong>One-off</strong> — each element reveals in <em>once</em> when it enters the viewport, then holds (no rewind).
                </label>
              </fieldset>
            </td>
          </tr>
        </tbody></table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
}
