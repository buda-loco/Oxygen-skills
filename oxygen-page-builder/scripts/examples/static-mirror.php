<?php
/**
 * Plugin Name:  Static Mirror
 * Description:  Exports a fully working static copy of your site — plain HTML and files, no PHP, no database. Deploy the export to your public host and keep WordPress private: the live site becomes un-attackable through WordPress and as fast as the host can serve files.
 * Version:      1.1.0
 * Author:       zstudios
 * Requires PHP: 8.0
 * Text Domain:  static-mirror
 *
 * HOW IT WORKS
 * ------------
 * 1. Collects every public URL (pages, collections, projects, robots.txt,
 *    llms.txt, sitemaps, and the 404 page).
 * 2. Fetches each one over loopback HTTP — so the export is exactly what a
 *    visitor gets, whatever built it (Oxygen, PHP renderers, options).
 * 3. Harvests every local asset the pages reference (images + srcset
 *    variants, video, posters, CSS, JS, favicons) and copies them in.
 * 4. Rewrites your local origin to the production URL you set (or to
 *    root-relative links when none is set).
 * 5. Adds a client-side search fallback (search-index.json + a small
 *    script) so the header search keeps working without a server.
 *
 * Output: wp-content/uploads/static-mirror/latest/ (+ a zip alongside).
 * Deploy that folder to Netlify / Vercel / S3 / any plain web host.
 *
 * Generic by design — reusable on any WordPress site; ships with the
 * oxygen-page-builder skill as an example.
 *
 * @package Static_Mirror
 */

declare(strict_types=1);

namespace StaticMirror;

if (!defined('ABSPATH')) {
    exit;
}

const ASSET_EXT = 'png|jpe?g|webp|avif|svg|gif|ico|css|js|mjs|mp4|webm|mov|woff2?|ttf|otf|json';

function export_dir(): string
{
    $u = wp_upload_dir();
    return $u['basedir'] . '/static-mirror/latest';
}

/** Staging dir for an in-progress export — swapped into latest/ when complete,
 *  so the live mirror is never served half-built. */
function build_dir(): string
{
    $u = wp_upload_dir();
    return $u['basedir'] . '/static-mirror/.building';
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function zip_path(): string
{
    $u = wp_upload_dir();
    return $u['basedir'] . '/static-mirror/static-mirror.zip';
}

/** Production origin to write into the export ('' = root-relative links). */
function target_origin(): string
{
    // Default to the CURRENT origin (absolute), not '' → relative. Relative
    // links break canonical/sitemap/robots, which REQUIRE absolute URLs. Only
    // rewrite the origin when a different production URL is explicitly set.
    $t = rtrim((string) get_option('static_mirror_target', ''), '/');
    return $t !== '' ? $t : rtrim(home_url(), '/');
}

/* ------------------------------------------------------------------ crawl */

function fetch(string $url, bool $remember = false): ?array
{
    // one-shot memo so collect_urls() and the crawl don't fetch the sitemap twice
    static $memo = [];
    if (isset($memo[$url])) {
        $r = $memo[$url];
        unset($memo[$url]);
        return $r;
    }
    // the bypass header makes the drop-in serve LIVE WordPress to the crawler,
    // so a re-export captures fresh content, not the frozen mirror.
    // redirection 0: surface 301/302s so the export preserves them as stubs
    // instead of silently duplicating the destination page under the old URL.
    $r = wp_remote_get($url, ['timeout' => 60, 'sslverify' => false, 'redirection' => 0,
        'headers' => ['X-Static-Bypass' => bypass_token()]]);
    if (is_wp_error($r)) {
        $out = null;
    } else {
        $loc = wp_remote_retrieve_header($r, 'location');
        $out = ['code' => (int) wp_remote_retrieve_response_code($r),
            'body' => (string) wp_remote_retrieve_body($r),
            'location' => (string) (is_array($loc) ? end($loc) : $loc)];
    }
    if ($remember) {
        $memo[$url] = $out;
    }
    return $out;
}

function collect_urls(): array
{
    $urls = [home_url('/')];
    // every PUBLIC post type on the site — portable to any WordPress install.
    $types = get_post_types(['public' => true], 'names');
    unset($types['attachment']);
    $ids = get_posts(['post_type' => array_values($types),
        'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
    foreach ($ids as $id) {
        $urls[] = get_permalink($id);
    }
    // public taxonomy term archives (skip empties)
    foreach (get_taxonomies(['public' => true], 'names') as $tax) {
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
        if (is_wp_error($terms)) {
            continue;
        }
        foreach ($terms as $term) {
            // get_term_link() can itself return WP_Error — a fatal under strict_types
            $link = get_term_link($term);
            if (!is_wp_error($link)) { $urls[] = $link; }
        }
    }
    $urls[] = home_url('/robots.txt');
    $urls[] = home_url('/llms.txt');
    $urls[] = home_url('/wp-sitemap.xml');
    if ($idx = fetch(home_url('/wp-sitemap.xml'), true)) {
        preg_match_all('#<loc>([^<]+)</loc>#', $idx['body'], $m);
        foreach ($m[1] as $loc) {
            // never export the users sitemap — it publishes login names
            if (str_ends_with($loc, '.xml') && !str_contains($loc, 'wp-sitemap-users')) {
                $urls[] = $loc;
            }
        }
    }
    return array_values(array_unique(array_filter($urls)));
}

/** Tiny meta-refresh page standing in for a 301 the static host can't do. */
function redirect_stub(string $to): string
{
    $e = esc_attr($to);
    return "<!doctype html>\n<html><head><meta charset=\"utf-8\">\n"
        . "<meta http-equiv=\"refresh\" content=\"0;url={$e}\">\n"
        . "<link rel=\"canonical\" href=\"{$e}\">\n"
        . "<title>Redirecting\u{2026}</title></head>\n"
        . "<body><a href=\"{$e}\">Continue</a></body></html>\n";
}

/** Internal hrefs/srcs in the exported HTML that resolve to no exported file —
 *  surfaced in the report so a missed asset shows up before a visitor hits it. */
function audit_export(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $t = target_origin();
    $missing = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || !str_ends_with($f->getFilename(), '.html')) {
            continue;
        }
        $html = (string) file_get_contents($f->getPathname());
        $refs = [];
        if (preg_match_all('#(?:href|src)=["\']([^"\']+)["\']#i', $html, $m)) {
            $refs = $m[1];
        }
        if (preg_match_all('#srcset=["\']([^"\']+)["\']#i', $html, $m)) {
            foreach ($m[1] as $set) {
                foreach (explode(',', $set) as $cand) {
                    $u = trim((string) strtok(trim($cand), ' '));
                    if ($u !== '') {
                        $refs[] = $u;
                    }
                }
            }
        }
        foreach (array_unique($refs) as $u) {
            if ($t !== '' && str_starts_with($u, $t)) {
                $u = substr($u, strlen($t)) ?: '/';
            }
            if ($u === '' || $u[0] !== '/' || str_starts_with($u, '//')) {
                continue; // external, #anchor, mailto:, data:, …
            }
            $p = (string) preg_replace('/[?#].*$/', '', $u);
            if ($p === '/' || $p === '') {
                continue;
            }
            $file = $dir . rawurldecode($p);
            if (!is_file($file) && !is_file(rtrim($file, '/') . '/index.html')) {
                $missing[$p] = true;
                if (count($missing) >= 20) {
                    return array_keys($missing); // cap the report
                }
            }
        }
    }
    return array_keys($missing);
}

/** Pagination links (/page/2/ …) found in a fetched page, as absolute URLs. */
function paginated_urls(string $body): array
{
    $out = [];
    foreach (array_unique([home_url(), set_url_scheme(home_url(), 'https'), set_url_scheme(home_url(), 'http')]) as $o) {
        $q = preg_quote($o, '#');
        if (preg_match_all('#href=["\']' . $q . '(/(?:[^"\'<>]*/)?page/\d+/?)["\']#i', $body, $m)) {
            foreach ($m[1] as $p) {
                $out[] = home_url($p);
            }
        }
    }
    return array_values(array_unique($out));
}

/** URL → path inside the export ('' path → index.html, dirs → dir/index.html). */
function rel_path(string $url): string
{
    $p = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    if ($p === '/' || $p === '') {
        return 'index.html';
    }
    if (preg_match('#\.(xml|txt|html|json)$#', $p)) {
        return ltrim($p, '/');
    }
    return trim($p, '/') . '/index.html';
}

function put(string $rel, string $content): void
{
    $file = build_dir() . '/' . $rel; // exports write to staging, never to the live dir
    wp_mkdir_p(dirname($file));
    file_put_contents($file, $content);
    // gzip HTML/XML/JSON/txt pages for optimised delivery (no risky HTML minify)
    if (optimize_enabled() && preg_match('#\.(html|xml|json|txt)$#i', $rel)) {
        gzip_file($file);
    }
}

/** Strip head links that point at endpoints a static host doesn't have
 *  (oEmbed discovery, REST index, shortlink) — they'd 404 and two of them
 *  smuggle the local origin through URL-encoding. */
function clean_head(string $s): string
{
    return (string) preg_replace([
        '#<link rel="alternate"[^>]*oembed[^>]*>\s*#i',
        '#<link rel=[\'"]https://api\.w\.org/[\'"][^>]*>\s*#i',
        '#<link rel=[\'"]shortlink[\'"][^>]*>\s*#i',
        '#<link rel="alternate"[^>]*wp-json[^>]*>\s*#i',
        '#<link[^>]*type=[\'"]application/(?:rss|atom)\+xml[\'"][^>]*>\s*#i', // feeds 404 on a static host
        '#<link rel=[\'"]EditURI[\'"][^>]*>\s*#i',   // RSD → xmlrpc.php, dead on a static host
        '#<link rel=[\'"]pingback[\'"][^>]*>\s*#i',  // pingback → xmlrpc.php, likewise
        '#<meta name="generator"[^>]*>\s*#i', // no reason to advertise the WP version
    ], '', $s);
}

/** Swap the local origin for the target (both plain and JSON-escaped forms). */
function rewrite(string $s): string
{
    $t = target_origin();
    $origins = array_unique([home_url(), set_url_scheme(home_url(), 'https'), set_url_scheme(home_url(), 'http')]);
    foreach ($origins as $o) {
        $s = str_replace([
            $o,                          // plain
            str_replace('/', '\/', $o),  // JSON-escaped
            rawurlencode($o),            // fully percent-encoded (?u=https%3A%2F%2F…)
            str_replace('/', '%2F', $o), // scheme kept, slashes encoded
            htmlspecialchars($o, ENT_QUOTES), // entity-escaped attribute form
        ], [
            $t,
            str_replace('/', '\/', $t),
            $t === '' ? '' : rawurlencode($t),
            $t === '' ? '' : str_replace('/', '%2F', $t),
            htmlspecialchars($t, ENT_QUOTES),
        ], $s);
    }
    return $s;
}

/* ----------------------------------------------------------------- assets */

/** Local asset URLs referenced by a blob of HTML/CSS. Returns url paths. */
function harvest(string $content): array
{
    $paths = [];
    // BOTH schemes (+ the raw home_url): behind an SSL-terminating proxy the
    // loopback crawl reaches the backend over http, so WP emits http:// asset
    // URLs even when home_url() is https — must match those too, exactly like
    // rewrite() does, or their files never get harvested (broken references).
    foreach (array_unique([home_url(), set_url_scheme(home_url(), 'https'), set_url_scheme(home_url(), 'http')]) as $o) {
        $q = preg_quote($o, '#');
        preg_match_all('#' . $q . '(/[^\s"\'()<>\\\\]+?\.(?:' . ASSET_EXT . '))(?:\?[^\s"\'<>]*)?#i', $content, $m);
        $paths = array_merge($paths, $m[1]);
    }
    return array_values(array_unique($paths));
}

/* ------------------------------------------------------------- optimize */

/** Minify + gzip the export? Default ON. */
function optimize_enabled(): bool
{
    return (bool) get_option('static_mirror_optimize', true);
}

/** Full CSS minification — safe (CSS has no ASI; comments are only /* *​/). */
function minify_css(string $css): string
{
    $css = preg_replace('#/\*(?!!).*?\*/#s', '', $css);          // drop comments (keep /*! license */)
    $css = preg_replace('/\s+/', ' ', $css);                     // collapse whitespace
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);     // tighten around punctuation
    $css = str_replace(';}', '}', $css);                          // drop last semicolon in a block
    return trim($css);
}

/** Conservative JS minification — line-safe so Automatic-Semicolon-Insertion
 *  can never break: strip whole-line comments + blank lines + indentation,
 *  but NEVER join tokens across newlines or touch string/regex bodies. */
function minify_js(string $js): string
{
    $out = [];
    $inBlock = false;
    foreach (explode("\n", $js) as $line) {
        $t = trim($line);
        if ($inBlock) {                                          // inside a /* … */ that spans lines
            if (($pos = strpos($t, '*/')) !== false) { $t = trim(substr($t, $pos + 2)); $inBlock = false; }
            else { continue; }
        }
        if ($t === '') { continue; }
        if (str_starts_with($t, '//')) { continue; }             // whole-line // comment
        if (str_starts_with($t, '/*')) {                          // whole-line block comment
            if (strpos($t, '*/') === false) { $inBlock = true; continue; }
            $t = trim(preg_replace('#^/\*.*?\*/#s', '', $t));
            if ($t === '') { continue; }
        }
        $out[] = $t;                                              // keep the line + its newline (ASI-safe)
    }
    return implode("\n", $out);
}

/** Write a gzip sibling ($file.gz) — a static host with gzip_static /
 *  precompressed support serves it transparently; 100% safe (no transform). */
function gzip_file(string $file): void
{
    if (!function_exists('gzencode') || !is_file($file)) {
        return;
    }
    $data = (string) file_get_contents($file);
    if (strlen($data) < 512) {                                   // not worth it for tiny files
        return;
    }
    if (($gz = gzencode($data, 9)) !== false) {
        file_put_contents($file . '.gz', $gz);
    }
}

/** Optimize a just-written text file in place, then gzip it. */
function optimize_file(string $file, string $ext): void
{
    if (!optimize_enabled()) {
        return;
    }
    if ($ext === 'css' || $ext === 'js') {
        $text = (string) file_get_contents($file);
        $min  = $ext === 'css' ? minify_css($text) : minify_js($text);
        if ($min !== '' && strlen($min) < strlen($text)) {
            file_put_contents($file, $min);
        }
    }
    gzip_file($file);
}

/** Copy one asset by URL path; text assets are rewritten and re-harvested. */
function copy_asset(string $urlPath, array &$done): void
{
    if (isset($done[$urlPath])) {
        return;
    }
    $done[$urlPath] = true;
    // Only copy real files that resolve INSIDE the web root and carry an asset
    // extension — a css url(../../wp-config.php) can never smuggle a secret out.
    if (!preg_match('#\.(?:' . ASSET_EXT . ')$#i', $urlPath)) {
        return;
    }
    $src = ABSPATH . ltrim($urlPath, '/');
    $realRoot = realpath(ABSPATH);
    $realSrc  = realpath($src);
    if ($realSrc === false || $realRoot === false
        || strpos($realSrc, $realRoot . DIRECTORY_SEPARATOR) !== 0
        || is_dir($realSrc)) {
        return;
    }
    $dest = build_dir() . '/' . ltrim($urlPath, '/');
    wp_mkdir_p(dirname($dest));
    if (preg_match('#\.(css|js|mjs|json|svg)$#i', $urlPath)) {
        $text = (string) file_get_contents($realSrc);
        file_put_contents($dest, rewrite($text));
        $ext = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
        optimize_file($dest, $ext === 'mjs' ? 'js' : $ext);
        // CSS may reference further files (fonts, images) — depth-1 follow.
        if (str_ends_with(strtolower($urlPath), '.css')) {
            foreach (harvest($text) as $p) {
                copy_asset($p, $done);
            }
            $relRefs = [];
            if (preg_match_all('#url\(\s*[\'"]?(?!data:|https?:|//)([^\'")]+)#i', $text, $m)) {
                $relRefs = $m[1];
            }
            // @import "foo.css" — no url() wrapper
            if (preg_match_all('#@import\s+[\'"](?!data:|https?:|//)([^\'"]+)[\'"]#i', $text, $m)) {
                $relRefs = array_merge($relRefs, $m[1]);
            }
            if ($relRefs) {
                foreach ($relRefs as $relRef) {
                    // normalize ./ and ../ so a ref cannot climb out of the tree;
                    // copy_asset re-checks containment + extension anyway.
                    $joined = dirname($urlPath) . '/' . preg_replace('#\?.*$#', '', $relRef);
                    $segs = [];
                    foreach (explode('/', $joined) as $seg) {
                        if ($seg === '' || $seg === '.') { continue; }
                        if ($seg === '..') { array_pop($segs); continue; }
                        $segs[] = $seg;
                    }
                    copy_asset('/' . implode('/', $segs), $done);
                }
            }
        }
    } else {
        copy($realSrc, $dest);
    }
}

/* ----------------------------------------------------------------- search */

/** Client-side search index: everything the server search would find. */
function search_index(): array
{
    $out = [];
    $types = get_post_types(['public' => true], 'names');
    unset($types['attachment']);
    $posts = get_posts(['post_type' => array_values($types),
        'post_status' => 'publish', 'posts_per_page' => -1]);
    foreach ($posts as $p) {
        if ($p->post_password !== '') {
            continue; // never leak password-protected content into the public index
        }
        // fold any plain-text/textarea/wysiwyg ACF field values into the index.
        $extra = '';
        if (function_exists('get_fields') && ($fields = get_fields($p->ID))) {
            array_walk_recursive($fields, function ($v) use (&$extra) {
                if (is_string($v) && strlen($v) < 5000) { $extra .= ' ' . wp_strip_all_tags($v); }
            });
        }
        $out[] = [
            'title' => $p->post_title,
            'url'   => rewrite(get_permalink($p)) ?: '/',
            // accent-folded so "fernandez" matches "Fernández"; ttext lets the
            // client rank title hits above body hits
            'ttext' => mb_strtolower(remove_accents($p->post_title)),
            'text'  => mb_strtolower(remove_accents($p->post_title . ' ' . wp_strip_all_tags($p->post_content) . ' ' . $extra)),
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------- run */

function run(): array
{
    // single-flight: a manual export and the cron export must not interleave
    if (get_transient('static_mirror_running')) {
        return ['pages' => 0, 'assets' => 0, 'seconds' => 0, 'dir' => export_dir(),
            'errors' => [__('an export is already running — try again shortly', 'static-mirror')]];
    }
    set_transient('static_mirror_running', 1, 15 * MINUTE_IN_SECONDS);
    try {
        return run_export();
    } finally {
        delete_transient('static_mirror_running');
    }
}

function run_export(): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    wp_raise_memory_limit('admin');
    $t0 = microtime(true);
    $dir = export_dir();
    // build into staging, swap into latest/ at the end — the live mirror is
    // never served half-built, and a failed export keeps the previous copy
    rrmdir(build_dir());
    wp_mkdir_p(build_dir());

    $report = ['pages' => 0, 'assets' => 0, 'errors' => [], 'redirects' => 0];
    $done = [];
    $redirects = [];

    $queue = collect_urls();
    $seen  = [];
    while ($queue) {
        $url = array_shift($queue);
        $key = rel_path($url);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $r = fetch($url);
        // preserve redirects as meta-refresh stubs + a Netlify _redirects entry
        if ($r && in_array($r['code'], [301, 302, 303, 307, 308], true) && $r['location'] !== '') {
            $loc = str_starts_with($r['location'], '/') ? home_url($r['location']) : $r['location'];
            $isInternal = false;
            foreach (array_unique([home_url(), set_url_scheme(home_url(), 'https'), set_url_scheme(home_url(), 'http')]) as $o) {
                if (str_starts_with($loc, $o)) {
                    $isInternal = true;
                    break;
                }
            }
            if ($isInternal && !isset($seen[rel_path($loc)])) {
                $queue[] = $loc; // export the destination too
            }
            $to = rewrite($loc) ?: '/';
            put($key, redirect_stub($to));
            $redirects[] = ((string) (parse_url($url, PHP_URL_PATH) ?: '/')) . ' ' . $to . ' 301';
            $report['redirects']++;
            continue;
        }
        if (!$r || $r['code'] !== 200) {
            $report['errors'][] = $url . ' (' . ($r['code'] ?? 'no response') . ')';
            continue;
        }
        $body = $r['body'];
        // follow archive pagination (/page/2/ …) — those links 404 if not exported
        foreach (paginated_urls($body) as $p) {
            if (!isset($seen[rel_path($p)])) {
                $queue[] = $p;
            }
        }
        foreach (harvest($body) as $p) {
            copy_asset($p, $done);
        }
        $out = rewrite(clean_head($body));
        if (str_ends_with($key, '.xml')) {
            // belt-and-braces: drop any users-sitemap entry from the index
            $out = (string) preg_replace('#<sitemap>\s*<loc>[^<]*wp-sitemap-users[^<]*</loc>.*?</sitemap>\s*#s', '', $out);
        }
        if (str_contains($out, '</body>')) {
            $out = str_replace('</body>', '<script src="/static-search.js" defer></script></body>', $out);
        }
        put(rel_path($url), $out);
        $report['pages']++;
    }

    // the styled 404 page → 404.html (Netlify picks it up automatically).
    // Same treatment as any page: harvest its assets (the 404 template has its
    // own Oxygen CSS) and scrub its head, or it ships broken references.
    if (($r = fetch(home_url('/static-mirror-404-probe/'))) && $r['code'] === 404 && $r['body'] !== '') {
        foreach (harvest($r['body']) as $p) {
            copy_asset($p, $done);
        }
        $out404 = rewrite(clean_head($r['body']));
        if (str_contains($out404, '</body>')) {
            $out404 = str_replace('</body>', '<script src="/static-search.js" defer></script></body>', $out404);
        }
        put('404.html', $out404);
    }

    // client-side search
    put('search-index.json', (string) wp_json_encode(search_index(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $js = file_get_contents(__DIR__ . '/static-search.js');
    if ($js !== false) {
        put('static-search.js', $js);
    }

    // Netlify-style redirect map (harmless on other hosts)
    if ($redirects) {
        put('_redirects', implode("\n", $redirects) . "\n");
    }

    // Apache niceties: styled 404 + long-lived asset caching + nosniff
    put('.htaccess', "ErrorDocument 404 /404.html\n"
        . "<IfModule mod_expires.c>\nExpiresActive On\n"
        . "ExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\n"
        . "ExpiresByType image/webp \"access plus 1 year\"\nExpiresByType image/avif \"access plus 1 year\"\n"
        . "ExpiresByType image/svg+xml \"access plus 1 year\"\nExpiresByType video/mp4 \"access plus 1 year\"\n"
        . "ExpiresByType text/css \"access plus 1 week\"\nExpiresByType application/javascript \"access plus 1 week\"\n"
        . "</IfModule>\n"
        . "<IfModule mod_headers.c>\nHeader set X-Content-Type-Options \"nosniff\"\n</IfModule>\n");

    // atomic swap: retire the previous copy only once the new one is complete
    $old = dirname($dir) . '/.old';
    rrmdir($old);
    if (is_dir($dir)) {
        rename($dir, $old);
    }
    if (!rename(build_dir(), $dir)) {
        if (is_dir($old)) {
            rename($old, $dir); // keep serving the previous copy
        }
        $report['errors'][] = __('could not activate the new export — previous copy kept', 'static-mirror');
    }
    rrmdir($old);

    // self-audit: internal links/assets in the export that resolve to nothing
    $report['missing'] = audit_export($dir);

    $report['assets'] = count(array_filter($done, fn($v) => $v === true));
    $report['seconds'] = round(microtime(true) - $t0, 1);

    // zip for one-click deploys
    if (class_exists('ZipArchive')) {
        $zip = new \ZipArchive();
        if ($zip->open(zip_path(), \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($dir) + 1));
                }
            }
            $zip->close();
            $report['zip'] = zip_path();
        }
    }
    $report['dir'] = $dir;
    // optional deploy hook (e.g. a Netlify/Vercel build hook) after a clean export
    $hook = (string) get_option('static_mirror_deploy_hook', '');
    if ($hook !== '' && !$report['errors']) {
        wp_remote_post($hook, ['timeout' => 15, 'blocking' => false]);
        $report['deploy_hook'] = 'pinged';
    }
    update_option('static_mirror_last', ['time' => time(), 'report' => $report], false);
    // background exports have no screen to report to — surface problems as an
    // admin notice; a clean run clears it
    $problems = $report['errors'];
    if ($report['missing']) {
        $problems[] = sprintf(__('%d broken internal reference(s) in the export', 'static-mirror'), count($report['missing']));
    }
    update_option('static_mirror_last_errors', $problems, false);
    return $report;
}

/* -------------------------------------------------------------- admin UI */

function admin_menu(): void
{
    add_options_page('Static Mirror', 'Static Mirror', 'manage_options', 'static-mirror', __NAMESPACE__ . '\\admin_page');
}
add_action('admin_menu', __NAMESPACE__ . '\\admin_menu');

function admin_page(): void
{
    $report = null;
    $serving_msg = null;
    // Step-1 form (nonce 'static_mirror') carries BOTH the export button and the
    // auto-update checkbox. Verify the nonce once, then branch on which control fired.
    if ((isset($_POST['static_mirror_run']) || isset($_POST['static_mirror_auto_toggle']))
        && check_admin_referer('static_mirror')) {
        if (isset($_POST['static_mirror_auto_toggle']) && !isset($_POST['static_mirror_run'])) {
            update_option('static_mirror_auto', $_POST['static_mirror_auto_toggle'] === 'on', false);
            $serving_msg = auto_enabled()
                ? sprintf(__('Auto-update is ON — the public copy rebuilds itself about %ds after you change content.', 'static-mirror'), AUTO_DELAY)
                : __('Auto-update is OFF — publish manually with the button.', 'static-mirror');
        } else {
            // only the Advanced form carries these fields — a plain "Publish"
            // click must not wipe the saved values
            if (isset($_POST['static_mirror_target'])) {
                update_option('static_mirror_target', esc_url_raw((string) $_POST['static_mirror_target']), false);
            }
            if (isset($_POST['static_mirror_deploy_hook'])) {
                update_option('static_mirror_deploy_hook', esc_url_raw((string) $_POST['static_mirror_deploy_hook']), false);
            }
            if (isset($_POST['static_mirror_optimize'])) {
                update_option('static_mirror_optimize', $_POST['static_mirror_optimize'] === 'on', false);
            }
            $report = run();
        }
    }
    if (isset($_POST['static_mirror_regen']) && check_admin_referer('static_mirror_regen')) {
        delete_option('static_mirror_bypass_token');
        bypass_token(); // mints a fresh one
        if (dropin_is_ours() && login_slug() !== '') {
            serving_enable(login_slug()); // re-embed the new token in drop-in + rules
        }
        $serving_msg = __('Bypass token regenerated — editors get a fresh preview cookie on their next page load.', 'static-mirror');
    }
    if (isset($_POST['static_mirror_serve']) && check_admin_referer('static_mirror_serving')) {
        $slug = sanitize_title((string) ($_POST['static_mirror_slug'] ?? ''));
        if ($_POST['static_mirror_serve'] === 'off') {
            serving_disable();
            $serving_msg = __('Static-first serving is OFF — WordPress answers every request again and wp-login.php works normally.', 'static-mirror');
        } elseif ($slug === '') {
            $serving_msg = __('Pick a secret login slug first.', 'static-mirror');
        } else {
            update_option('static_mirror_slug', $slug, false);
            $r = serving_enable($slug);
            $serving_msg = isset($r['error']) ? $r['error']
                : sprintf(__('Static-first serving is ON (%1$s). Your login now lives at /%2$s/ — bookmark it.', 'static-mirror'), implode(', ', $r['ok']), $slug);
        }
    }
    $last     = get_option('static_mirror_last');
    $autoLast = get_option('static_mirror_auto_last');
    $target   = target_origin();
    $protected = dropin_is_ours();
    $everExported = is_array($last) || is_array($autoLast);
    // a manual export writes static_mirror_last, auto-update writes
    // static_mirror_auto_last — show whichever actually ran most recently
    $manualTime = is_array($last) ? (int) $last['time'] : 0;
    $autoTime   = is_array($autoLast) ? (int) $autoLast['time'] : 0;
    $lastTime   = max($manualTime, $autoTime);
    $lastPages  = $report ? (int) $report['pages']
        : ($autoTime >= $manualTime
            ? (is_array($autoLast) ? (int) $autoLast['pages'] : 0)
            : (int) $last['report']['pages']);
    $dirty = (int) get_option('static_mirror_dirty_since');
    ?>
    <div class="wrap" style="max-width:820px">
        <h1>Static Mirror</h1>

        <?php if ($serving_msg) : ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html($serving_msg); ?></p></div><?php endif; ?>
        <?php if ($report && $report['errors']) : ?><div class="notice notice-warning"><p><?php printf(esc_html__('Some URLs were skipped: %s', 'static-mirror'), esc_html(implode(', ', $report['errors']))); ?></p></div><?php endif; ?>
        <?php if ($report && !empty($report['missing'])) : ?><div class="notice notice-warning"><p><?php printf(esc_html__('The export references files it does not contain (check these): %s', 'static-mirror'), esc_html(implode(', ', $report['missing']))); ?></p></div><?php endif; ?>

        <!-- status banner -->
        <div style="padding:18px 20px;border-radius:8px;margin:16px 0;background:<?php echo $protected ? '#eef7ee' : '#eef3fb'; ?>;border:1px solid <?php echo $protected ? '#bcdcbc' : '#c3d4ee'; ?>">
            <p style="margin:0;font-size:15px">
                <span style="font-size:20px;vertical-align:-2px"><?php echo $protected ? '🛡️' : '🌐'; ?></span>
                &nbsp;<?php echo esc_html__('Your site is currently', 'static-mirror'); ?>
                <strong><?php echo $protected ? esc_html__('PROTECTED', 'static-mirror') : esc_html__('LIVE WORDPRESS', 'static-mirror'); ?></strong>
                — <?php echo $protected
                    ? esc_html__('visitors are served the fast static copy; WordPress is hidden.', 'static-mirror')
                    : esc_html__('every visit runs WordPress. Publish a static copy below to speed it up and lock it down.', 'static-mirror'); ?>
            </p>
        </div>

        <!-- STEP 1: publish the copy -->
        <div style="border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:16px">
            <h2 style="margin-top:0">1 &nbsp;<?php echo esc_html__('Publish the public copy', 'static-mirror'); ?></h2>
            <p><?php echo esc_html__('Takes a snapshot of every page as plain files. Do this whenever you want the public site to reflect your latest changes.', 'static-mirror'); ?></p>
            <form method="post" onsubmit="var b=this.querySelector('button[name=static_mirror_run]');if(b){setTimeout(function(){b.disabled=true;b.textContent='<?php echo esc_js(__('Publishing…', 'static-mirror')); ?>';},0);}">
                <?php wp_nonce_field('static_mirror'); ?>
                <p>
                    <button class="button button-primary button-hero" name="static_mirror_run" value="1">
                        <?php echo $everExported ? esc_html__('Update the public copy', 'static-mirror') : esc_html__('Publish the public copy', 'static-mirror'); ?>
                    </button>
                    <?php if ($lastTime) : ?>
                        <span style="margin-left:10px;color:#646970">
                            <?php printf(esc_html__('Last updated %1$s ago · %2$d pages', 'static-mirror'), esc_html(human_time_diff($lastTime)), (int) $lastPages); ?>
                            <?php if ($dirty) : ?><strong style="color:#b26a00"> · <?php echo esc_html__('changes waiting to publish', 'static-mirror'); ?></strong><?php endif; ?>
                        </span>
                    <?php endif; ?>
                </p>
                <p style="margin-bottom:0">
                    <label>
                        <input type="checkbox" name="static_mirror_auto_toggle_cb"
                               onchange="this.form.static_mirror_auto_toggle.value=this.checked?'on':'off';this.form.submit()"
                               <?php checked(auto_enabled()); ?>>
                        <?php echo esc_html__('Keep the public copy updated automatically when I change content', 'static-mirror'); ?>
                    </label>
                    <input type="hidden" name="static_mirror_auto_toggle" value="<?php echo auto_enabled() ? 'on' : 'off'; ?>">
                </p>
            </form>
        </div>

        <!-- STEP 2: protect (advanced, collapsible) -->
        <div style="border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:16px">
            <h2 style="margin-top:0">2 &nbsp;<?php echo esc_html__('Protect the site', 'static-mirror'); ?> <span style="font-weight:400;color:#646970;font-size:13px">— <?php echo esc_html__('optional, recommended for launch', 'static-mirror'); ?></span></h2>
            <p><?php echo wp_kses_post(__('Serve the static copy to the world and hide WordPress behind a private login address. Bots that probe <code>wp-login.php</code> just get a “not found”. <strong>You’ll keep seeing the live editor while you’re signed in.</strong>', 'static-mirror')); ?></p>
            <form method="post">
                <?php wp_nonce_field('static_mirror_serving'); ?>
                <p>
                    <label for="sm_slug"><strong><?php echo esc_html__('Your private login address', 'static-mirror'); ?></strong></label><br>
                    <span style="color:#646970"><?php echo esc_html(home_url('/')); ?></span><input type="text" id="sm_slug" name="static_mirror_slug"
                           value="<?php echo esc_attr(login_slug()); ?>" placeholder="a-secret-word" style="width:16em">/
                    <br><span class="description"><?php echo esc_html__('Pick something private and memorable. You’ll sign in here from now on — bookmark it.', 'static-mirror'); ?></span>
                </p>
                <p>
                    <?php if ($protected) : ?>
                        <button class="button" name="static_mirror_serve" value="off"><?php echo esc_html__('Turn protection off', 'static-mirror'); ?></button>
                        <span style="margin-left:8px;color:#1a7f37"><?php echo esc_html__('Protection is ON · login at', 'static-mirror'); ?>
                            <code>/<?php echo esc_html(login_slug()); ?>/</code></span>
                    <?php else : ?>
                        <button class="button button-primary" name="static_mirror_serve" value="on"
                            <?php echo $everExported ? '' : 'disabled title="' . esc_attr__('Publish the copy first (step 1)', 'static-mirror') . '"'; ?>><?php echo esc_html__('Turn protection on', 'static-mirror'); ?></button>
                        <?php if (!$everExported) : ?><span style="margin-left:8px;color:#b26a00"><?php echo esc_html__('Publish the copy first ↑', 'static-mirror'); ?></span><?php endif; ?>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- advanced / technical, folded away -->
        <details style="margin-top:8px">
            <summary style="cursor:pointer;color:#646970"><?php echo esc_html__('Advanced & deployment details', 'static-mirror'); ?></summary>
            <div style="padding:14px 4px">
                <form method="post" style="margin-bottom:14px">
                    <?php wp_nonce_field('static_mirror'); ?>
                    <label for="sm_target"><strong><?php echo esc_html__('Production URL', 'static-mirror'); ?></strong> <?php echo esc_html__('(set before a launch export)', 'static-mirror'); ?></label><br>
                    <input type="url" id="sm_target" name="static_mirror_target" class="regular-text"
                           value="<?php echo esc_attr($target); ?>" placeholder="https://daianafernandez.com">
                    <button class="button" name="static_mirror_run" value="1"><?php echo esc_html__('Save & export', 'static-mirror'); ?></button>
                    <p class="description"><?php echo esc_html__('Empty = relative links (fine for a same-server setup). Set your real domain to bake it into canonicals, social tags and the sitemap.', 'static-mirror'); ?></p>
                    <label for="sm_hook"><strong><?php echo esc_html__('Deploy hook URL', 'static-mirror'); ?></strong> <?php echo esc_html__('(optional)', 'static-mirror'); ?></label><br>
                    <input type="url" id="sm_hook" name="static_mirror_deploy_hook" class="regular-text"
                           value="<?php echo esc_attr((string) get_option('static_mirror_deploy_hook', '')); ?>" placeholder="https://api.netlify.com/build_hooks/…">
                    <p class="description"><?php echo esc_html__('Pinged (POST) after every clean export — point it at a Netlify/Vercel build hook to auto-deploy the copy.', 'static-mirror'); ?></p>
                    <label style="display:inline-flex;align-items:center;gap:7px;margin-top:4px">
                        <input type="hidden" name="static_mirror_optimize" value="off">
                        <input type="checkbox" name="static_mirror_optimize" value="on" <?php checked(optimize_enabled()); ?>>
                        <strong><?php echo esc_html__('Optimise assets', 'static-mirror'); ?></strong>
                    </label>
                    <p class="description"><?php echo esc_html__('Minify CSS & JS and write gzip copies of every page, script and stylesheet — the drop-in serves the compressed version to browsers that accept it. Leave on for the fastest delivery.', 'static-mirror'); ?></p>
                </form>
                <form method="post" style="margin-bottom:14px">
                    <?php wp_nonce_field('static_mirror_regen'); ?>
                    <button class="button" name="static_mirror_regen" value="1"><?php echo esc_html__('Regenerate bypass token', 'static-mirror'); ?></button>
                    <p class="description"><?php echo esc_html__('Invalidates the old editor/crawler token everywhere and rewrites the drop-in if protection is on. Use it if you suspect the token leaked.', 'static-mirror'); ?></p>
                </form>
                <p style="color:#646970;font-size:13px;line-height:1.7">
                    <?php echo wp_kses_post(__('Export folder: <code>uploads/static-mirror/latest/</code> · zip alongside it.', 'static-mirror')); ?><br>
                    <?php echo wp_kses_post(__('Deploy that folder to any static host (Netlify/S3), <em>or</em> use “Protect” above to serve it here.', 'static-mirror')); ?><br>
                    <?php printf(esc_html__('Protection status: drop-in %s', 'static-mirror'), $protected ? esc_html__('installed', 'static-mirror') : esc_html__('not installed', 'static-mirror')); ?> ·
                    <?php echo wp_kses_post(__('Apache rules at <code>uploads/static-mirror/htaccess-rules.php</code> (open in a file editor and copy the comment block; the PHP guard keeps it un-downloadable; deleted when protection is off).', 'static-mirror')); ?><br>
                    <?php echo wp_kses_post(__('Locked out? Add <code>define(\'STATIC_MIRROR_OFF\', true);</code> to wp-config.php (bundled mu-plugin) — it also switches the drop-in off. On Apache, additionally remove the “# BEGIN Static Mirror” block from .htaccess.', 'static-mirror')); ?>
                    <?php echo wp_kses_post(__('Recover the slug: <code>wp option get static_mirror_slug</code>.', 'static-mirror')); ?><br>
                    <?php echo wp_kses_post(__('Auto-update uses WordPress cron (fires as you work). On a quiet live site, schedule <code>wp static-mirror export</code> in a real cron instead.', 'static-mirror')); ?>
                </p>
            </div>
        </details>
    </div>
    <?php
}

/** Background exports have no screen — surface the last run's problems to
 *  admins as a notice; a clean export clears the option. */
function failure_notice(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $errs = get_option('static_mirror_last_errors');
    if (!is_array($errs) || !$errs) {
        return;
    }
    echo '<div class="notice notice-warning"><p>'
        . sprintf(esc_html__('Static Mirror: the last export had problems — %s', 'static-mirror'),
            esc_html(implode(' · ', array_slice($errs, 0, 5))))
        . '</p></div>';
}
add_action('admin_notices', __NAMESPACE__ . '\\failure_notice');

/* ============================================================= HARDENING
 * The users sitemap and author archives publish login names — this site has
 * no use for either, and the export must never contain them. The secret door
 * gets a per-IP lockout: obscurity is not a rate limit.
 * ======================================================================= */

add_filter('wp_sitemaps_add_provider', function ($provider, string $name) {
    return $name === 'users' ? false : $provider;
}, 10, 2);

function block_author_archives(): void
{
    if (is_author()) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
    }
}
add_action('template_redirect', __NAMESPACE__ . '\\block_author_archives');

const LOCK_TRIES  = 5;
const LOCK_WINDOW = 15 * MINUTE_IN_SECONDS;

/** REMOTE_ADDR only — X-Forwarded-For is attacker-controlled. */
function throttle_key(): string
{
    return 'sm_lock_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function login_failed(): void
{
    set_transient(throttle_key(), (int) get_transient(throttle_key()) + 1, LOCK_WINDOW);
}
add_action('wp_login_failed', __NAMESPACE__ . '\\login_failed');

function login_succeeded(): void
{
    delete_transient(throttle_key());
}
add_action('wp_login', __NAMESPACE__ . '\\login_succeeded');

/* ================================================================ SERVING
 * Static-first on the SAME server: a tiny advanced-cache.php drop-in runs
 * before WordPress connects to the database and serves the mirror for every
 * public request (hits AND misses — misses get the static 404). WordPress
 * only wakes up for: the secret login slug, logged-in visitors (so you
 * preview live edits while the world sees the mirror), and wp-cron.
 * A PHP layer hides the login: wp-login.php and wp-admin return the static
 * 404 unless you come through your secret slug — even if someone forges a
 * logged-in cookie to slip past the drop-in (defense in depth).
 * For Apache/LiteSpeed hosts an .htaccess ruleset is generated too — there
 * the public path never even starts PHP.
 * ======================================================================= */

function login_slug(): string
{
    return sanitize_title((string) get_option('static_mirror_slug', ''));
}

function dropin_path(): string
{
    return WP_CONTENT_DIR . '/advanced-cache.php';
}

function dropin_is_ours(): bool
{
    return file_exists(dropin_path()) && str_contains((string) file_get_contents(dropin_path()), 'Static Mirror drop-in');
}

/** Forge-proof secret shared between the drop-in, the editor preview cookie,
 *  and the export crawler. Generated once, regenerable. */
function bypass_token(): string
{
    $t = (string) get_option('static_mirror_bypass_token', '');
    if ($t === '') {
        $t = wp_generate_password(48, false);
        update_option('static_mirror_bypass_token', $t, false);
    }
    return $t;
}

/** Set the editor preview cookie for a genuinely logged-in manager, so the
 *  drop-in shows them live WordPress. The cookie value is the secret token —
 *  it cannot be forged without reading the DB. */
function set_preview_cookie(): void
{
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        return;
    }
    if (($_COOKIE['sm_preview'] ?? '') !== bypass_token()) {
        $secure = is_ssl();
        setcookie('sm_preview', bypass_token(), [
            'expires'  => time() + 2 * DAY_IN_SECONDS,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
add_action('init', __NAMESPACE__ . '\\set_preview_cookie', 5);

function dropin_source(string $slug): string
{
    $dir   = var_export(export_dir(), true);          // real absolute export path (fixes reader/writer mismatch)
    $token = var_export(bypass_token(), true);        // forge-proof editor/export bypass
    return <<<PHP
<?php
/* Static Mirror drop-in — serves the static export before WordPress touches
   the database. Managed by the Static Mirror plugin; regenerate from its
   settings page after changing the login slug or bypass token. */
if (defined('WP_CLI') && WP_CLI) { return; }
/* wp-config escape hatch: define('STATIC_MIRROR_OFF', true) hands every
   request back to live WordPress (wp-config runs before this drop-in). */
if (defined('STATIC_MIRROR_OFF') && STATIC_MIRROR_OFF) { return; }
\$smSlug  = '{$slug}';
\$smDir   = {$dir};
\$smToken = {$token};
\$smUri   = (string) (strtok(\$_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
\$smMeth  = \$_SERVER['REQUEST_METHOD'] ?? 'GET';

/* Bypass to live WordPress ONLY on a forge-proof match:
   - the editor preview cookie (a random token the plugin sets after login), or
   - the export crawler's secret header (its loopback fetch, so re-exports see
     live WP not the frozen copy).
   A bare `wordpress_logged_in_*` cookie is NOT trusted — anyone can send one. */
\$smHdr = \$_SERVER['HTTP_X_STATIC_BYPASS'] ?? '';
\$smCk  = \$_COOKIE['sm_preview'] ?? '';
if (\$smToken !== '' && (
        (\$smHdr !== '' && hash_equals(\$smToken, (string) \$smHdr)) ||
        (\$smCk  !== '' && hash_equals(\$smToken, (string) \$smCk))
   )) { return; }
if (\$smSlug !== '' && (\$smUri === "/\$smSlug" || \$smUri === "/\$smSlug/")) { return; }
if (\$smUri === '/wp-cron.php') { return; }

\$smServe = static function (string \$f, int \$code = 200) use (\$smMeth): void {
    \$ext  = strtolower(pathinfo(\$f, PATHINFO_EXTENSION));
    \$mime = [
        'html' => 'text/html; charset=utf-8', 'css' => 'text/css', 'js' => 'application/javascript',
        'json' => 'application/json', 'xml' => 'application/xml', 'txt' => 'text/plain; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'woff2' => 'font/woff2', 'woff' => 'font/woff',
    ][\$ext] ?? 'application/octet-stream';
    /* pages stay fresh-ish (5 min), assets are immutable for a year; 304 on
       If-Modified-Since so repeat visits cost a header, not a body */
    if (\$code === 200) {
        \$page  = in_array(\$ext, ['html', 'xml', 'txt', 'json'], true);
        \$mtime = (int) filemtime(\$f);
        header('Cache-Control: ' . (\$page ? 'max-age=300' : 'max-age=31536000, immutable'));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', \$mtime) . ' GMT');
        \$ims = (string) (\$_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
        if (\$ims !== '' && strtotime(\$ims) >= \$mtime) {
            http_response_code(304);
            header('X-Static-Mirror: hit');
            exit;
        }
    }
    http_response_code(\$code);
    header('Content-Type: ' . \$mime);
    header('X-Content-Type-Options: nosniff');
    header('X-Static-Mirror: hit');
    /* content-negotiated gzip: if the client accepts it and a precompressed
       sibling exists for a text type, ship the smaller body untouched */
    \$smBody = \$f;
    \$smText = in_array(\$ext, ['html', 'css', 'js', 'json', 'xml', 'txt', 'svg'], true);
    if (\$smText) { header('Vary: Accept-Encoding'); }
    \$smAE = strtolower((string) (\$_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
    if (\$smText && strpos(\$smAE, 'gzip') !== false && is_file(\$f . '.gz')) {
        \$smBody = \$f . '.gz';
        header('Content-Encoding: gzip');
    }
    header('Content-Length: ' . (string) filesize(\$smBody));
    if (\$smMeth !== 'HEAD') { readfile(\$smBody); }
    exit;
};
\$smNotFound = static function () use (\$smDir, \$smServe): void {
    if (is_file(\$smDir . '/404.html')) { \$smServe(\$smDir . '/404.html', 404); }
    http_response_code(404);
    exit;
};

if (!in_array(\$smMeth, ['GET', 'HEAD'], true)) { \$smNotFound(); }

/* Resolve the request to a file INSIDE the export dir — reject any traversal.
   Decode, normalize away ./ and ../ segments, then require the real path to
   live under the export root. */
\$smRoot = realpath(\$smDir);
\$smNorm = static function (string \$uri): string {
    \$p = rawurldecode(\$uri);
    \$p = str_replace('\\\\', '/', \$p);
    \$segs = [];
    foreach (explode('/', \$p) as \$seg) {
        if (\$seg === '' || \$seg === '.') { continue; }
        if (\$seg === '..') { array_pop(\$segs); continue; }
        \$segs[] = \$seg;
    }
    return implode('/', \$segs);
};
\$smContained = static function (?string \$root, string \$file): bool {
    if (\$root === false || \$root === null) { return false; }
    \$real = realpath(\$file);
    return \$real !== false && (\$real === \$root || strpos(\$real, \$root . DIRECTORY_SEPARATOR) === 0);
};
\$smRel  = \$smNorm(\$smUri);
\$smFile = \$smRel === '' ? \$smDir . '/index.html' : \$smDir . '/' . \$smRel;
if (\$smRel !== '' && is_file(\$smFile) && \$smContained(\$smRoot, \$smFile)) { \$smServe(\$smFile); }
\$smIdx = (\$smRel === '' ? \$smDir : \$smDir . '/' . \$smRel) . '/index.html';
if (is_file(\$smIdx) && \$smContained(\$smRoot, \$smIdx)) { \$smServe(\$smIdx); }
\$smNotFound();
PHP;
}

/** The Apache/LiteSpeed ruleset (zero-PHP public path on hosts that honor it). */
function htaccess_rules(string $slug): string
{
    $m = 'wp-content/uploads/static-mirror/latest';
    $tok = bypass_token();
    // Apache resolves %2e/%2f in the filesystem map; -f on the mapped path
    // can't escape the export dir, and we only ever map GET/HEAD. The bypass
    // is the forge-proof preview cookie, NOT a bare logged-in cookie.
    return "# BEGIN Static Mirror (keep ABOVE the WordPress block)\n"
        . "<IfModule mod_rewrite.c>\nRewriteEngine On\n"
        . "RewriteRule ^xmlrpc\\.php$ - [F,L]\n"
        . "RewriteCond %{REQUEST_URI} ^/{$slug}/?$ [OR]\n"
        . "RewriteCond %{REQUEST_URI} ^/wp-cron\\.php$ [OR]\n"
        . "RewriteCond %{HTTP:X-Static-Bypass} ^{$tok}$ [OR]\n"
        . "RewriteCond %{HTTP_COOKIE} (^|;\\ )sm_preview={$tok}(;|$)\n"
        . "RewriteRule ^ - [S=5]\n"
        . "RewriteCond %{REQUEST_METHOD} !^(GET|HEAD)$\n"
        . "RewriteRule ^ - [R=404,L]\n"
        . "RewriteCond %{DOCUMENT_ROOT}/{$m}%{REQUEST_URI} -f\n"
        . "RewriteRule ^(.*)$ {$m}/$1 [L]\n"
        . "RewriteCond %{DOCUMENT_ROOT}/{$m}%{REQUEST_URI}/index.html -f\n"
        . "RewriteRule ^(.*?)/?$ {$m}/$1/index.html [L]\n"
        . "RewriteRule ^$ {$m}/index.html [L]\n"
        . "RewriteRule ^ - [R=404,L]\n"
        . "</IfModule>\n"
        . "ErrorDocument 404 /{$m}/404.html\n"
        . "# END Static Mirror\n";
}

function serving_enable(string $slug): array
{
    $out = [];
    if (file_exists(dropin_path()) && !dropin_is_ours()) {
        return ['error' => __('Another plugin already owns advanced-cache.php — remove it first.', 'static-mirror')];
    }
    if (file_put_contents(dropin_path(), dropin_source($slug)) === false) {
        return ['error' => sprintf(__('Could not write %s — check file permissions.', 'static-mirror'), dropin_path())];
    }
    $out[] = __('drop-in written', 'static-mirror');
    // WP_CACHE must be on for WordPress to load the drop-in — and we must
    // VERIFY it landed, or we'd report protection that never takes effect
    $cfg = ABSPATH . 'wp-config.php';
    $c = (string) file_get_contents($cfg);
    // real define check — a substring test false-positives on salt strings
    if (!preg_match('/define\s*\(\s*[\'"]WP_CACHE[\'"]/', $c)) {
        $new = (string) preg_replace('/^(\xEF\xBB\xBF)?<\?php\s*/', "$1<?php\ndefine( 'WP_CACHE', true ); // Static Mirror\n", $c, 1);
        $ok  = $new !== $c && is_writable($cfg) && file_put_contents($cfg, $new) !== false
            && preg_match('/define\s*\(\s*[\'"]WP_CACHE[\'"]/', (string) file_get_contents($cfg));
        if (!$ok) {
            unlink(dropin_path());
            return ['error' => __("Could not enable WP_CACHE in wp-config.php — add define( 'WP_CACHE', true ); to it manually, then turn protection on again.", 'static-mirror')];
        }
        $out[] = __('WP_CACHE enabled', 'static-mirror');
    }
    // Apache rules: always write the file; also splice into .htaccess (harmless on nginx).
    // The rules embed the bypass token + slug, so the file is a PHP guard that exits
    // on web requests (works on nginx too, where .htaccess denies do nothing) — open
    // it in a file editor and copy the rules from the comment block.
    $u = wp_upload_dir();
    $smDir = $u['basedir'] . '/static-mirror';
    wp_mkdir_p($smDir);
    file_put_contents($smDir . '/.htaccess',
        "<Files \"htaccess-rules.php\">\nRequire all denied\n</Files>\n"
        . "<Files \"static-mirror.zip\">\nRequire all denied\n</Files>\n");
    file_put_contents($smDir . '/htaccess-rules.php',
        "<?php http_response_code(404); exit; // secrets below — never served over the web\n"
        . "/* Copy everything between BEGIN and END into your site's .htaccess:\n\n"
        . htaccess_rules($slug)
        . "\n*/\n");
    // remove the old world-readable variant if a previous version left one behind
    if (file_exists($smDir . '/htaccess-rules.txt')) {
        unlink($smDir . '/htaccess-rules.txt');
    }
    $ht = ABSPATH . '.htaccess';
    if (file_exists($ht) && is_writable($ht)) {
        $h = (string) file_get_contents($ht);
        $h = (string) preg_replace('/# BEGIN Static Mirror.*?# END Static Mirror\n?/s', '', $h);
        if (file_put_contents($ht, htaccess_rules($slug) . "\n" . $h) !== false) {
            $out[] = __('.htaccess block placed on top', 'static-mirror');
        }
    }
    return ['ok' => $out];
}

function serving_disable(): void
{
    if (dropin_is_ours()) {
        unlink(dropin_path());
    }
    // the rules file holds the bypass token + secret slug — remove it (and the
    // old .txt variant from a previous version) when protection goes off
    $u = wp_upload_dir();
    foreach (['htaccess-rules.php', 'htaccess-rules.txt'] as $f) {
        $rules = $u['basedir'] . '/static-mirror/' . $f;
        if (file_exists($rules)) {
            unlink($rules);
        }
    }
    $ht = ABSPATH . '.htaccess';
    if (file_exists($ht) && is_writable($ht)) {
        $h = (string) file_get_contents($ht);
        file_put_contents($ht, (string) preg_replace('/# BEGIN Static Mirror.*?# END Static Mirror\n?/s', '', $h));
    }
}

// deactivating (or a fatal auto-deactivating) the plugin removes secret_door() —
// the drop-in MUST go with it or the site becomes unreachable, login included
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\serving_disable');

function uninstall_cleanup(): void
{
    serving_disable();
    wp_clear_scheduled_hook(AUTO_HOOK);
    foreach (['static_mirror_target', 'static_mirror_last', 'static_mirror_auto',
              'static_mirror_auto_last', 'static_mirror_slug', 'static_mirror_bypass_token',
              'static_mirror_dirty_since', 'static_mirror_deploy_hook', 'static_mirror_last_errors',
              'static_mirror_optimize'] as $o) {
        delete_option($o);
    }
    // the export folder + zip are left in uploads on purpose — they may be the
    // deployed site; delete uploads/static-mirror/ by hand when done with them
}
register_uninstall_hook(__FILE__, __NAMESPACE__ . '\\uninstall_cleanup');

/* ------------------------------------------------- hidden login (PHP layer) */

function is_secret_request(): bool
{
    $slug = login_slug();
    if ($slug === '') {
        return false;
    }
    $uri = (string) (strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
    return $uri === "/{$slug}" || $uri === "/{$slug}/";
}

function static_404(): void
{
    $f = export_dir() . '/404.html';
    http_response_code(404);
    if (is_file($f)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($f);
    }
    exit;
}

function guard(): void
{
    // the hidden login is part of "protection" — when the drop-in is gone
    // (protection off), wp-login.php must answer normally again
    if (login_slug() === '' || !dropin_is_ours() || apply_filters('static_mirror_disable_guard', false)) {
        return;
    }
    // normalize: collapse repeated leading slashes so //wp-login.php can't slip past
    $uri = (string) (strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
    $path = '/' . ltrim($uri, '/');
    // direct wp-login.php: static 404, always — the door is the slug
    if ((str_starts_with($path, '/wp-login.php')) && !defined('STATIC_MIRROR_DOOR')) {
        static_404();
    }
    // wp-admin (exactly, or the /wp-admin/ dir) without a real session → static 404.
    // Anchored so a page slug like "wp-admin-guide" is NOT caught.
    if (($path === '/wp-admin' || str_starts_with($path, '/wp-admin/'))
        && !is_user_logged_in() && !(defined('DOING_AJAX') && DOING_AJAX)) {
        static_404();
    }
}
add_action('init', __NAMESPACE__ . '\\guard', 1);

function secret_door(): void
{
    if (!is_secret_request()) {
        return;
    }
    if ((int) get_transient(throttle_key()) >= LOCK_TRIES) {
        wp_die(
            esc_html__('Too many failed sign-in attempts — try again in 15 minutes.', 'static-mirror'),
            esc_html__('Locked', 'static-mirror'),
            ['response' => 429]
        );
    }
    define('STATIC_MIRROR_DOOR', true);
    global $pagenow, $error, $interim_login, $action, $user_login;
    $pagenow = 'wp-login.php';
    require_once ABSPATH . 'wp-login.php';
    exit;
}
add_action('wp_loaded', __NAMESPACE__ . '\\secret_door');

/** Keep every core-generated login/logout URL pointing at the slug. */
function filter_login_url(string $url): string
{
    $slug = login_slug();
    if ($slug === '' || !str_contains($url, 'wp-login.php')) {
        return $url;
    }
    $q = (string) parse_url($url, PHP_URL_QUERY);
    return home_url("/{$slug}/") . ($q ? "?{$q}" : '');
}
add_filter('site_url', __NAMESPACE__ . '\\filter_login_url', 10, 1);
add_filter('network_site_url', __NAMESPACE__ . '\\filter_login_url', 10, 1);
add_filter('wp_redirect', __NAMESPACE__ . '\\filter_login_url', 10, 1);

/* ============================================================= AUTO-UPDATE
 * Re-export in the BACKGROUND when admin content changes. Debounced: a burst
 * of saves collapses into ONE rebuild a short delay after the LAST change
 * (wp-cron single event). Runs only when you turn it on. Content edits stay
 * instant — the export happens out of band.
 * ======================================================================= */

const AUTO_HOOK  = 'static_mirror_auto_export';
const AUTO_DELAY = 90; // seconds after the last change

function auto_enabled(): bool
{
    return (bool) get_option('static_mirror_auto', false);
}

/** Debounced schedule: push the single rebuild event to now+DELAY. */
function mark_dirty(): void
{
    if (!auto_enabled() || (defined('STATIC_MIRROR_EXPORTING') && STATIC_MIRROR_EXPORTING)) {
        return;
    }
    wp_clear_scheduled_hook(AUTO_HOOK);
    wp_schedule_single_event(time() + AUTO_DELAY, AUTO_HOOK);
    update_option('static_mirror_dirty_since', time(), false);
}

/** save_post handler — skip autosave/revision/draft noise, then schedule.
 *  NOTE: 'inherit' is NOT skipped — attachments use it, and a replaced image
 *  must rebuild the mirror. Revisions (also 'inherit') are already filtered by
 *  wp_is_post_revision() above. */
function mark_dirty_post($postId): void
{
    if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
        return;
    }
    // builder templates, nav items etc. never appear in the export — skip them
    $type = get_post_type((int) $postId);
    if ($type && !is_post_type_viewable($type)) {
        return;
    }
    if (in_array(get_post_status((int) $postId), ['auto-draft', 'draft', 'pending'], true)) {
        return;
    }
    mark_dirty();
}

function auto_run(): void
{
    if (!auto_enabled()) {
        return;
    }
    if (!defined('STATIC_MIRROR_EXPORTING')) {
        define('STATIC_MIRROR_EXPORTING', true);
    }
    $report = run();
    update_option('static_mirror_dirty_since', 0, false);
    update_option('static_mirror_auto_last', ['time' => time(), 'pages' => (int) $report['pages']], false);
}
add_action(AUTO_HOOK, __NAMESPACE__ . '\\auto_run');

/* --------------------------------------------------------------- WP-CLI */

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('static-mirror export', function (): void {
        $r = run();
        if ($r['errors']) {
            \WP_CLI::warning('Skipped: ' . implode(', ', $r['errors']));
        }
        if (!empty($r['missing'])) {
            \WP_CLI::warning('Broken internal references: ' . implode(', ', $r['missing']));
        }
        \WP_CLI::success(sprintf('%d pages, %d assets in %ss → %s', $r['pages'], $r['assets'], $r['seconds'], $r['dir']));
    });
    \WP_CLI::add_command('static-mirror protect', function (array $args, array $assoc): void {
        $state = $args[0] ?? '';
        if ($state === 'off') {
            serving_disable();
            \WP_CLI::success('Protection OFF — WordPress answers every request again.');
            return;
        }
        if ($state !== 'on') {
            \WP_CLI::error('Usage: wp static-mirror protect <on|off> [--slug=<slug>]');
        }
        $slug = sanitize_title((string) ($assoc['slug'] ?? login_slug()));
        if ($slug === '') {
            \WP_CLI::error('No login slug saved — pass --slug=your-secret-word.');
        }
        update_option('static_mirror_slug', $slug, false);
        $r = serving_enable($slug);
        if (isset($r['error'])) {
            \WP_CLI::error($r['error']);
        }
        \WP_CLI::success('Protection ON (' . implode(', ', $r['ok']) . ') — login at /' . $slug . '/');
    });
    \WP_CLI::add_command('static-mirror status', function (): void {
        \WP_CLI::line('Protection:  ' . (dropin_is_ours() ? 'ON (login at /' . login_slug() . '/)' : 'OFF'));
        $last = get_option('static_mirror_last');
        \WP_CLI::line('Last export: ' . (is_array($last)
            ? gmdate('Y-m-d H:i', (int) $last['time']) . ' UTC · ' . (int) $last['report']['pages'] . ' pages'
            : 'never'));
        \WP_CLI::line('Auto-update: ' . (auto_enabled() ? 'ON' : 'OFF'));
    });
}

/** The change signals that should refresh the mirror. */
function register_auto_hooks(): void
{
    // post saves get the autosave/revision/draft filter
    add_action('save_post', __NAMESPACE__ . '\\mark_dirty_post', 99);
    add_action('attachment_updated', __NAMESPACE__ . '\\mark_dirty_post', 99);
    // deletions + site-wide changes always rebuild (no post-status ambiguity)
    foreach (['deleted_post', 'trashed_post', 'untrashed_post', 'acf/save_post',
              'created_term', 'edited_term', 'delete_term', 'wp_update_nav_menu',
              'customize_save_after'] as $h) {
        add_action($h, __NAMESPACE__ . '\\mark_dirty', 99);
    }
}
add_action('init', __NAMESPACE__ . '\\register_auto_hooks', 20);
