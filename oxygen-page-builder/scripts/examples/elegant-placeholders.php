<?php
/**
 * Plugin Name:  Elegant Placeholders
 * Description:  Generates refined SVG placeholder images at any size — warm paper tones, hairline rule, small-caps label. Use the ep_attachment() helper to create media-library placeholders, or the /?ep=WxH&label=… endpoint for ad-hoc ones.
 * Version:      1.0.0
 * Author:       zstudios
 * Requires PHP: 8.0
 *
 * Generic by design — no project-specific colors or names. Palette and type
 * are filterable ('ep_palette', 'ep_font'). Ships with the oxygen-page-builder
 * skill as a reusable template.
 *
 * @package Elegant_Placeholders
 */

declare(strict_types=1);

namespace ElegantPlaceholders;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The SVG itself. Deterministic per label (tint picked by hash), so the same
 * placeholder always looks the same.
 *
 * $opts: label (string) · sublabel (string) · tone (0-3 palette override)
 */
function ep_svg(int $w, int $h, array $opts = []): string
{
    $label    = trim((string) ($opts['label'] ?? ''));
    $sublabel = trim((string) ($opts['sublabel'] ?? ''));
    $palette  = apply_filters('ep_palette', [
        ['#ece8df', '#5a554d'], // warm paper / soft ink
        ['#e7e3da', '#57524a'],
        ['#e4dfd2', '#544f47'],
        ['#eae6de', '#5d584f'],
    ]);
    $tone = isset($opts['tone'])
        ? ((int) $opts['tone']) % count($palette)
        : (crc32($label ?: "{$w}x{$h}") % count($palette));
    [$bg, $ink] = $palette[$tone];
    $font = apply_filters('ep_font', "'Montserrat', 'Helvetica Neue', system-ui, sans-serif");

    $inset  = max(12, round(min($w, $h) * 0.03));
    $mid    = $h / 2;
    $fsMain = max(11, round(min($w, $h) * 0.045));
    $fsDims = max(9,  round($fsMain * 0.62));
    $fsSub  = max(8,  round($fsMain * 0.5));

    $lines  = [];
    $y      = $mid - ($sublabel ? $fsMain * 0.55 : $fsMain * 0.1);
    if ($label !== '') {
        $lines[] = sprintf(
            '<text x="50%%" y="%d" text-anchor="middle" font-family="%s" font-size="%d" font-weight="600" letter-spacing="%.1f" fill="%s">%s</text>',
            $y, $font, $fsMain, $fsMain * 0.18, $ink, esc_html(mb_strtoupper($label))
        );
        $y += $fsMain * 1.5;
    }
    $lines[] = sprintf(
        '<text x="50%%" y="%d" text-anchor="middle" font-family="%s" font-size="%d" font-weight="400" letter-spacing="%.1f" fill="%s" opacity="0.65">%d × %d</text>',
        $y, $font, $fsDims, $fsDims * 0.15, $ink, $w, $h
    );
    if ($sublabel !== '') {
        $y += $fsDims * 1.9;
        $lines[] = sprintf(
            '<text x="50%%" y="%d" text-anchor="middle" font-family="%s" font-size="%d" font-weight="500" letter-spacing="%.1f" fill="%s" opacity="0.5">%s</text>',
            $y, $font, $fsSub, $fsSub * 0.14, $ink, esc_html(mb_strtoupper($sublabel))
        );
    }

    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s">'
        . '<rect width="%1$d" height="%2$d" fill="%4$s"/>'
        . '<line x1="0" y1="0" x2="%1$d" y2="%2$d" stroke="%5$s" stroke-opacity="0.05"/>'
        . '<line x1="%1$d" y1="0" x2="0" y2="%2$d" stroke="%5$s" stroke-opacity="0.05"/>'
        . '<rect x="%6$d" y="%6$d" width="%7$d" height="%8$d" fill="none" stroke="%5$s" stroke-opacity="0.18"/>'
        . '%9$s</svg>',
        $w, $h,
        esc_attr($label !== '' ? "Placeholder: $label" : 'Placeholder'),
        $bg, $ink, $inset, $w - 2 * $inset, $h - 2 * $inset,
        implode('', $lines)
    );
}

/**
 * Create (or reuse) a media-library attachment for a placeholder.
 * Idempotent per (w, h, label) via the _ep_key meta. Returns attachment ID.
 */
function ep_attachment(int $w, int $h, array $opts = []): int
{
    $label = (string) ($opts['label'] ?? '');
    $key   = sanitize_title("ep-{$w}x{$h}-" . ($label ?: 'blank'));
    $ex = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1,
        'fields' => 'ids', 'meta_key' => '_ep_key', 'meta_value' => $key]);
    if ($ex) {
        return (int) $ex[0];
    }

    $svg  = ep_svg($w, $h, $opts);
    // WP disallows the svg mime by default — allow it ONLY for this write.
    $allow = static fn(array $m): array => $m + ['svg' => 'image/svg+xml'];
    add_filter('upload_mimes', $allow);
    $up = wp_upload_bits($key . '.svg', null, $svg);
    remove_filter('upload_mimes', $allow);
    if (!empty($up['error'])) {
        return 0;
    }
    $id = wp_insert_attachment([
        'post_title'     => $label !== '' ? "Placeholder — $label" : "Placeholder {$w}×{$h}",
        'post_mime_type' => 'image/svg+xml',
        'post_status'    => 'inherit',
        'post_excerpt'   => (string) ($opts['caption'] ?? ''),
    ], $up['file']);
    if (!$id || is_wp_error($id)) {
        return 0;
    }
    update_post_meta($id, '_ep_key', $key);
    update_post_meta($id, '_wp_attachment_image_alt', (string) ($opts['alt'] ?? ($label !== '' ? "$label placeholder" : 'Placeholder image')));
    // SVGs get no intermediate sizes; store the intended dimensions so
    // wp_get_attachment_image emits width/height (CLS).
    wp_update_attachment_metadata($id, ['width' => $w, 'height' => $h, 'file' => _wp_relative_upload_path($up['file'])]);
    return (int) $id;
}

/**
 * Ad-hoc endpoint: /?ep=1200x800&label=Hero&sublabel=Placeholder
 * Handy while designing in the builder — no file, no attachment.
 */
function endpoint(): void
{
    if (empty($_GET['ep'])) {
        return;
    }
    if (!preg_match('/^(\d{2,4})x(\d{2,4})$/', (string) $_GET['ep'], $m)) {
        return;
    }
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo ep_svg((int) $m[1], (int) $m[2], [
        'label'    => (string) ($_GET['label'] ?? ''),
        'sublabel' => (string) ($_GET['sublabel'] ?? ''),
    ]);
    exit;
}
add_action('init', __NAMESPACE__ . '\\endpoint');
