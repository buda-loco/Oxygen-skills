<?php
/**
 * Plugin Name: Oxygen — dynamic data: related-post fields (ACF relationship hop)
 * COPY THIS FILE per-project; it is generic (no brand assumptions).
 * Description: Adds dynamic-data fields that read a value from a post one
 * relationship hop away, so a card can show a RELATED post's featured image
 * and title without a PhpCode node.
 *
 * WHY THIS EXISTS
 * Oxygen's own ACF post-object provider (`AcfPostField extends PostField`) can
 * reach a related post's `post_title`, dates, terms and meta — but its handler
 * only understands `post_field`, and the featured image is not a WP_Post
 * property. `PostFeaturedImageURL` resolves via `get_the_ID()`, i.e. the
 * CURRENT loop post, with no way to point it elsewhere. So a testimonial that
 * wants its company's logo (testimonial → ACF post_object → brand → featured
 * image) had no native path and needed a PhpCode leaf.
 *
 * Registering a field is the native-shaped fix: the Image element then binds to
 * it exactly like any built-in dynamic field, and the design stays in the
 * builder's hands (project rule 2 — the user must be able to click and edit it).
 *
 * FIELDS
 *   related_featured_image_url  → the related post's featured image URL
 *   related_post_title          → the related post's title
 * Both take a `relation_field` attribute: the ACF field NAME on the current
 * post holding the related post (an id, a WP_Post, or an array of either).
 *
 * USE
 *   [breakdance_dynamic field='related_featured_image_url' relation_field='company_brand']
 * or via the skill's helper: oxy_dyn_related('company_brand', 'image').
 *
 * TIMING: fields are collected on `wp_loaded` (dynamic-data.php:160), so
 * registering at wp_loaded:20 lands after the controller exists. Guarded on the
 * class so a deactivated Oxygen can't fatal the site.
 */

add_action('wp_loaded', function () {
    if (!function_exists('\Breakdance\DynamicData\registerField')) return;
    if (!class_exists('\Breakdance\DynamicData\StringField')) return;
    if (!function_exists('get_field')) return;   // ACF

    /**
     * Resolve the related post id from an ACF field on the current post.
     * ACF returns an id, a WP_Post, or an array of either depending on the
     * field's return_format and multiple flag — accept all three.
     */
    if (!function_exists('oxy_related_post_id')) {
        function oxy_related_post_id($attributes) {
            $name = is_array($attributes) ? ($attributes['relation_field'] ?? '') : '';
            if (!$name) return 0;
            $value = get_field($name, get_the_ID());
            if (is_array($value)) $value = reset($value);
            if ($value instanceof \WP_Post) return (int) $value->ID;
            return (int) $value;
        }
    }

    $control = [
        \Breakdance\Elements\control('relation_field', 'ACF relationship field (name)', [
            'type' => 'text', 'layout' => 'vertical',
        ]),
    ];

    // ── related featured image URL ─────────────────────────────────────────
    $img = new class($control) extends \Breakdance\DynamicData\StringField {
        private $c;
        public function __construct($c) { $this->c = $c; }
        public function label()    { return 'Imagen destacada de la relación (URL)'; }
        public function category() { return 'Relación'; }
        public function slug()     { return 'related_featured_image_url'; }
        public function controls() { return $this->c; }
        public function returnTypes() { return ['url']; }
        public function handler($attributes): \Breakdance\DynamicData\StringData {
            $id = oxy_related_post_id($attributes);
            if (!$id) return \Breakdance\DynamicData\StringData::emptyString();
            $url = wp_get_attachment_image_url(get_post_thumbnail_id($id), 'full');
            return \Breakdance\DynamicData\StringData::fromString((string) ($url ?: ''));
        }
    };

    // ── related post title ─────────────────────────────────────────────────
    $title = new class($control) extends \Breakdance\DynamicData\StringField {
        private $c;
        public function __construct($c) { $this->c = $c; }
        public function label()    { return 'Título de la relación'; }
        public function category() { return 'Relación'; }
        public function slug()     { return 'related_post_title'; }
        public function controls() { return $this->c; }
        public function handler($attributes): \Breakdance\DynamicData\StringData {
            $id = oxy_related_post_id($attributes);
            if (!$id) return \Breakdance\DynamicData\StringData::emptyString();
            return \Breakdance\DynamicData\StringData::fromString(get_the_title($id));
        }
    };

    \Breakdance\DynamicData\registerField($img);
    \Breakdance\DynamicData\registerField($title);
}, 20);
