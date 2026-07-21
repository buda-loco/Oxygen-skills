<?php
/**
 * Plugin Name: Oxygen Skills Parallax
 * Description: Portable scroll-linked parallax. Mark any element with the class <code>oxs-plx</code>; configure via CSS custom properties <code>--plx-from</code>/<code>--plx-to</code> (inline style, element class, or Oxygen Variables — they compile to the same custom properties). CSS scroll-driven animations on modern browsers (compositor, zero JS); tiny rAF fallback elsewhere. Respects prefers-reduced-motion.
 * Version: 1.1.1
 * Author: zstudios
 * License: GPL-2.0-or-later
 *
 * ENGINE SELECTION (decided per browser, not per element):
 *
 *   page load
 *      │
 *      ├─ @supports (animation-timeline: view())  ──yes──▶ parallax.css keyframes
 *      │                                                    (compositor; parallax.js
 *      │                                                     returns immediately)
 *      └─ no ──▶ parallax.js: IntersectionObserver gates
 *                visible .oxs-plx els → one rAF writes
 *                el.style.translate from cached bounds
 *
 *   both engines: @media (prefers-reduced-motion: reduce) → OFF
 *
 * No options page by design: all configuration is CSS custom properties, so it
 * travels with the markup/stylesheet and works identically on any site.
 */
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', function () {
    $base = plugin_dir_url(__FILE__);
    $ver  = '1.1.1';
    wp_enqueue_style('oxs-parallax', $base . 'assets/parallax.css', [], $ver);
    wp_enqueue_script('oxs-parallax', $base . 'assets/parallax.js', [], $ver, ['in_footer' => true, 'strategy' => 'defer']);
});
