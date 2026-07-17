<?php
/**
 * Plugin Name:  Static Mirror
 * Description:  Exports a fully working static copy of your site — plain HTML and files, no PHP, no database. Deploy the export to your public host and keep WordPress private: the live site becomes un-attackable through WordPress and as fast as the host can serve files.
 * Version:      1.0.0
 * Author:       zstudios
 * Requires PHP: 8.0
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

function zip_path(): string
{
    $u = wp_upload_dir();
    return $u['basedir'] . '/static-mirror/static-mirror.zip';
}

/** Production origin to write into the export ('' = root-relative links). */
function target_origin(): string
{
    return rtrim((string) get_option('static_mirror_target', ''), '/');
}

/* ------------------------------------------------------------------ crawl */

function fetch(string $url): ?array
{
    // the bypass header makes the drop-in serve LIVE WordPress to the crawler,
    // so a re-export captures fresh content, not the frozen mirror.
    $r = wp_remote_get($url, ['timeout' => 60, 'sslverify' => false,
        'headers' => ['X-Static-Bypass' => bypass_token()]]);
    if (is_wp_error($r)) {
        return null;
    }
    return ['code' => (int) wp_remote_retrieve_response_code($r), 'body' => (string) wp_remote_retrieve_body($r)];
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
        foreach (get_terms(['taxonomy' => $tax, 'hide_empty' => true]) as $term) {
            if (!is_wp_error($term)) { $urls[] = get_term_link($term); }
        }
    }
    $urls[] = home_url('/robots.txt');
    $urls[] = home_url('/llms.txt');
    $urls[] = home_url('/wp-sitemap.xml');
    if ($idx = fetch(home_url('/wp-sitemap.xml'))) {
        preg_match_all('#<loc>([^<]+)</loc>#', $idx['body'], $m);
        foreach ($m[1] as $loc) {
            if (str_ends_with($loc, '.xml')) {
                $urls[] = $loc;
            }
        }
    }
    return array_values(array_unique(array_filter($urls)));
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
    $file = export_dir() . '/' . $rel;
    wp_mkdir_p(dirname($file));
    file_put_contents($file, $content);
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
    foreach (array_unique([home_url(), set_url_scheme(home_url(), 'https')]) as $o) {
        $q = preg_quote($o, '#');
        preg_match_all('#' . $q . '(/[^\s"\'()<>\\\\]+?\.(?:' . ASSET_EXT . '))(?:\?[^\s"\'<>]*)?#i', $content, $m);
        $paths = array_merge($paths, $m[1]);
    }
    return array_values(array_unique($paths));
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
    $dest = export_dir() . '/' . ltrim($urlPath, '/');
    wp_mkdir_p(dirname($dest));
    if (preg_match('#\.(css|js|mjs|json|svg)$#i', $urlPath)) {
        $text = (string) file_get_contents($realSrc);
        file_put_contents($dest, rewrite($text));
        // CSS may reference further files (fonts, images) — depth-1 follow.
        if (str_ends_with(strtolower($urlPath), '.css')) {
            foreach (harvest($text) as $p) {
                copy_asset($p, $done);
            }
            if (preg_match_all('#url\(\s*[\'"]?(?!data:|https?:|//)([^\'")]+)#i', $text, $m)) {
                foreach ($m[1] as $relRef) {
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
            'text'  => mb_strtolower($p->post_title . ' ' . wp_strip_all_tags($p->post_content) . ' ' . $extra),
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------- run */

function run(): array
{
    $t0 = microtime(true);
    $dir = export_dir();
    // clean slate
    if (is_dir($dir)) {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
    }
    wp_mkdir_p($dir);

    $report = ['pages' => 0, 'assets' => 0, 'errors' => []];
    $done = [];

    foreach (collect_urls() as $url) {
        $r = fetch($url);
        if (!$r || $r['code'] !== 200) {
            $report['errors'][] = $url . ' (' . ($r['code'] ?? 'no response') . ')';
            continue;
        }
        $body = $r['body'];
        foreach (harvest($body) as $p) {
            copy_asset($p, $done);
        }
        $out = rewrite(clean_head($body));
        if (str_contains($out, '</body>')) {
            $out = str_replace('</body>', '<script src="/static-search.js" defer></script></body>', $out);
        }
        put(rel_path($url), $out);
        $report['pages']++;
    }

    // the styled 404 page → 404.html (Netlify picks it up automatically)
    if (($r = fetch(home_url('/static-mirror-404-probe/'))) && $r['code'] === 404 && $r['body'] !== '') {
        put('404.html', str_replace('</body>', '<script src="/static-search.js" defer></script></body>', rewrite($r['body'])));
    }

    // client-side search
    put('search-index.json', (string) wp_json_encode(search_index(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $js = file_get_contents(__DIR__ . '/static-search.js');
    if ($js !== false) {
        put('static-search.js', $js);
    }

    // Apache niceties: styled 404 + long-lived asset caching
    put('.htaccess', "ErrorDocument 404 /404.html\n"
        . "<IfModule mod_expires.c>\nExpiresActive On\n"
        . "ExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\n"
        . "ExpiresByType image/webp \"access plus 1 year\"\nExpiresByType image/avif \"access plus 1 year\"\n"
        . "ExpiresByType image/svg+xml \"access plus 1 year\"\nExpiresByType video/mp4 \"access plus 1 year\"\n"
        . "ExpiresByType text/css \"access plus 1 week\"\nExpiresByType application/javascript \"access plus 1 week\"\n"
        . "</IfModule>\n");

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
    update_option('static_mirror_last', ['time' => time(), 'report' => $report], false);
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
                ? 'Auto-update is ON — the public copy rebuilds itself about ' . AUTO_DELAY . 's after you change content.'
                : 'Auto-update is OFF — publish manually with the button.';
        } else {
            update_option('static_mirror_target', esc_url_raw((string) ($_POST['static_mirror_target'] ?? '')), false);
            $report = run();
        }
    }
    if (isset($_POST['static_mirror_serve']) && check_admin_referer('static_mirror_serving')) {
        $slug = sanitize_title((string) ($_POST['static_mirror_slug'] ?? ''));
        if ($_POST['static_mirror_serve'] === 'off') {
            serving_disable();
            $serving_msg = 'Static-first serving is OFF — WordPress answers every request again.';
        } elseif ($slug === '') {
            $serving_msg = 'Pick a secret login slug first.';
        } else {
            update_option('static_mirror_slug', $slug, false);
            $r = serving_enable($slug);
            $serving_msg = isset($r['error']) ? $r['error']
                : 'Static-first serving is ON (' . implode(', ', $r['ok']) . '). Your login now lives at /' . $slug . '/ — bookmark it.';
        }
    }
    $last     = get_option('static_mirror_last');
    $autoLast = get_option('static_mirror_auto_last');
    $target   = target_origin();
    $protected = dropin_is_ours();
    $everExported = is_array($last) || is_array($autoLast);
    $lastTime = is_array($autoLast) ? (int) $autoLast['time'] : (is_array($last) ? (int) $last['time'] : 0);
    $lastPages = $report ? (int) $report['pages']
        : (is_array($autoLast) ? (int) $autoLast['pages'] : (is_array($last) ? (int) $last['report']['pages'] : 0));
    $dirty = (int) get_option('static_mirror_dirty_since');
    ?>
    <div class="wrap" style="max-width:820px">
        <h1>Static Mirror</h1>

        <?php if ($serving_msg) : ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html($serving_msg); ?></p></div><?php endif; ?>
        <?php if ($report && $report['errors']) : ?><div class="notice notice-warning"><p>Some URLs were skipped: <?php echo esc_html(implode(', ', $report['errors'])); ?></p></div><?php endif; ?>

        <!-- status banner -->
        <div style="padding:18px 20px;border-radius:8px;margin:16px 0;background:<?php echo $protected ? '#eef7ee' : '#eef3fb'; ?>;border:1px solid <?php echo $protected ? '#bcdcbc' : '#c3d4ee'; ?>">
            <p style="margin:0;font-size:15px">
                <span style="font-size:20px;vertical-align:-2px"><?php echo $protected ? '🛡️' : '🌐'; ?></span>
                &nbsp;Your site is currently
                <strong><?php echo $protected ? 'PROTECTED' : 'LIVE WORDPRESS'; ?></strong>
                — <?php echo $protected
                    ? 'visitors are served the fast static copy; WordPress is hidden.'
                    : 'every visit runs WordPress. Publish a static copy below to speed it up and lock it down.'; ?>
            </p>
        </div>

        <!-- STEP 1: publish the copy -->
        <div style="border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:16px">
            <h2 style="margin-top:0">1 &nbsp;Publish the public copy</h2>
            <p>Takes a snapshot of every page as plain files. Do this whenever you want the public
               site to reflect your latest changes.</p>
            <form method="post">
                <?php wp_nonce_field('static_mirror'); ?>
                <p>
                    <button class="button button-primary button-hero" name="static_mirror_run" value="1">
                        <?php echo $everExported ? 'Update the public copy' : 'Publish the public copy'; ?>
                    </button>
                    <?php if ($lastTime) : ?>
                        <span style="margin-left:10px;color:#646970">
                            Last updated <?php echo esc_html(human_time_diff($lastTime) . ' ago'); ?> · <?php echo (int) $lastPages; ?> pages
                            <?php if ($dirty) : ?><strong style="color:#b26a00"> · changes waiting to publish</strong><?php endif; ?>
                        </span>
                    <?php endif; ?>
                </p>
                <p style="margin-bottom:0">
                    <label>
                        <input type="checkbox" name="static_mirror_auto_toggle_cb"
                               onchange="this.form.static_mirror_auto_toggle.value=this.checked?'on':'off';this.form.submit()"
                               <?php checked(auto_enabled()); ?>>
                        Keep the public copy updated automatically when I change content
                    </label>
                    <input type="hidden" name="static_mirror_auto_toggle" value="<?php echo auto_enabled() ? 'on' : 'off'; ?>">
                </p>
            </form>
        </div>

        <!-- STEP 2: protect (advanced, collapsible) -->
        <div style="border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:16px">
            <h2 style="margin-top:0">2 &nbsp;Protect the site <span style="font-weight:400;color:#646970;font-size:13px">— optional, recommended for launch</span></h2>
            <p>Serve the static copy to the world and hide WordPress behind a private login address.
               Bots that probe <code>wp-login.php</code> just get a “not found”.
               <strong>You’ll keep seeing the live editor while you’re signed in.</strong></p>
            <form method="post">
                <?php wp_nonce_field('static_mirror_serving'); ?>
                <p>
                    <label for="sm_slug"><strong>Your private login address</strong></label><br>
                    <span style="color:#646970"><?php echo esc_html(home_url('/')); ?></span><input type="text" id="sm_slug" name="static_mirror_slug"
                           value="<?php echo esc_attr(login_slug()); ?>" placeholder="a-secret-word" style="width:16em">/
                    <br><span class="description">Pick something private and memorable. You’ll sign in here from now on — bookmark it.</span>
                </p>
                <p>
                    <?php if ($protected) : ?>
                        <button class="button" name="static_mirror_serve" value="off">Turn protection off</button>
                        <span style="margin-left:8px;color:#1a7f37">Protection is ON · login at
                            <code>/<?php echo esc_html(login_slug()); ?>/</code></span>
                    <?php else : ?>
                        <button class="button button-primary" name="static_mirror_serve" value="on"
                            <?php echo $everExported ? '' : 'disabled title="Publish the copy first (step 1)"'; ?>>Turn protection on</button>
                        <?php if (!$everExported) : ?><span style="margin-left:8px;color:#b26a00">Publish the copy first ↑</span><?php endif; ?>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- advanced / technical, folded away -->
        <details style="margin-top:8px">
            <summary style="cursor:pointer;color:#646970">Advanced &amp; deployment details</summary>
            <div style="padding:14px 4px">
                <form method="post" style="margin-bottom:14px">
                    <?php wp_nonce_field('static_mirror'); ?>
                    <label for="sm_target"><strong>Production URL</strong> (set before a launch export)</label><br>
                    <input type="url" id="sm_target" name="static_mirror_target" class="regular-text"
                           value="<?php echo esc_attr($target); ?>" placeholder="https://daianafernandez.com">
                    <button class="button" name="static_mirror_run" value="1">Save &amp; export</button>
                    <p class="description">Empty = relative links (fine for a same-server setup). Set your real domain to bake it into canonicals, social tags and the sitemap.</p>
                </form>
                <p style="color:#646970;font-size:13px;line-height:1.7">
                    Export folder: <code>uploads/static-mirror/latest/</code> · zip alongside it.<br>
                    Deploy that folder to any static host (Netlify/S3), <em>or</em> use “Protect” above to serve it here.<br>
                    Protection status: drop-in <?php echo $protected ? 'installed' : 'not installed'; ?> ·
                    Apache rules at <code>uploads/static-mirror/htaccess-rules.txt</code>.<br>
                    Locked out? Add <code>define('STATIC_MIRROR_OFF', true);</code> to wp-config.php (bundled mu-plugin).
                    Recover the slug: <code>wp option get static_mirror_slug</code>.<br>
                    Auto-update uses WordPress cron (fires as you work; add a real cron on a quiet live site).
                </p>
            </div>
        </details>
    </div>
    <?php
}

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

\$smServe = static function (string \$f, int \$code = 200): void {
    \$mime = [
        'html' => 'text/html; charset=utf-8', 'css' => 'text/css', 'js' => 'application/javascript',
        'json' => 'application/json', 'xml' => 'application/xml', 'txt' => 'text/plain; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'woff2' => 'font/woff2', 'woff' => 'font/woff',
    ][strtolower(pathinfo(\$f, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    http_response_code(\$code);
    header('Content-Type: ' . \$mime);
    header('X-Static-Mirror: hit');
    readfile(\$f);
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
        return ['error' => 'Another plugin already owns advanced-cache.php — remove it first.'];
    }
    file_put_contents(dropin_path(), dropin_source($slug));
    $out[] = 'drop-in written';
    // WP_CACHE must be on for WordPress to load the drop-in
    $cfg = ABSPATH . 'wp-config.php';
    $c = (string) file_get_contents($cfg);
    // real define check — a substring test false-positives on salt strings
    if (!preg_match('/define\s*\(\s*[\'"]WP_CACHE[\'"]/', $c)) {
        $c = (string) preg_replace('/^<\?php\s*/', "<?php\ndefine( 'WP_CACHE', true ); // Static Mirror\n", $c, 1);
        file_put_contents($cfg, $c);
        $out[] = 'WP_CACHE enabled';
    }
    // Apache rules: always write the file; also splice into .htaccess (harmless on nginx)
    $u = wp_upload_dir();
    file_put_contents($u['basedir'] . '/static-mirror/htaccess-rules.txt', htaccess_rules($slug));
    $ht = ABSPATH . '.htaccess';
    if (file_exists($ht) && is_writable($ht)) {
        $h = (string) file_get_contents($ht);
        $h = (string) preg_replace('/# BEGIN Static Mirror.*?# END Static Mirror\n?/s', '', $h);
        file_put_contents($ht, htaccess_rules($slug) . "\n" . $h);
        $out[] = '.htaccess block placed on top';
    }
    return ['ok' => $out];
}

function serving_disable(): void
{
    if (dropin_is_ours()) {
        unlink(dropin_path());
    }
    $ht = ABSPATH . '.htaccess';
    if (file_exists($ht) && is_writable($ht)) {
        $h = (string) file_get_contents($ht);
        file_put_contents($ht, (string) preg_replace('/# BEGIN Static Mirror.*?# END Static Mirror\n?/s', '', $h));
    }
}

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
    if (login_slug() === '' || apply_filters('static_mirror_disable_guard', false)) {
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
