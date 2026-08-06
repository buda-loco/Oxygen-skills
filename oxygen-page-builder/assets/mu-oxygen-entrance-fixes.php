<?php
/**
 * Plugin Name: Oxygen — entrance animation fixes (a11y, no-JS, ghost inits)
 * Description: Three repairs to Oxygen 6's native entrance animations. Drop in
 * wp-content/mu-plugins/ on any Oxygen site that uses them.
 *
 * COPY THIS FILE per-project; it is generic and carries no brand assumptions.
 * All three issues verified against Oxygen 6.1.0 on 2026-08-06.
 *
 * ── 1. visibility:hidden removes content from the ACCESSIBILITY TREE ─────────
 * `plugin/animations/entrance/css/entrance.css` ships an unconditional
 * `[data-entrance]{visibility:hidden}`. A screen reader cannot reach that
 * content until the VISUAL viewport scrolls it in — on one build, 53% of the
 * page text was absent from the a11y tree at rest. `opacity:0` hides
 * identically while keeping the node exposed (what AOS-style libraries use).
 * The doubled attribute selector is (0,2,0), so it wins over the engine's
 * (0,1,0) regardless of stylesheet order. Once the runtime marks the element
 * (`is-animating` / `is-animated`, entrance.js:14-15) GSAP owns opacity and
 * this override stands down.
 *
 * ── 2. With JavaScript off, nothing ever reveals the content ────────────────
 * Nothing removes the hiding rule, so animated sections stay invisible
 * forever. A <noscript> block restores them.
 *
 * ── 3. Ghost inits crash on Component-rendered nodes ────────────────────────
 * A node inside a placed Component gets its inline
 * `new BreakdanceEntrance('%%SELECTOR%%', …)` emitted TWICE with different
 * instance suffixes (`.oxy-container-72-118-72-1` and `…-2`); only one exists
 * in the DOM, so the other's init() throws
 * `Cannot set properties of null (setting 'bdAnim')` — one uncaught TypeError
 * per animated node, every page load. Reveals still work (each init is its own
 * script) but the console fills with errors and any error-budget monitor
 * screams. Guard the prototype so a selector matching nothing is a no-op.
 *
 * NOT patched here, because the runtime already handles it: reduced motion.
 * entrance.js adds the completed class at init under
 * `prefers-reduced-motion: reduce`, so content shows static.
 *
 * ALSO WORTH KNOWING (a tree-side fix, not a runtime one): set
 * `settings.animations.entrance_animation.advanced.once = true` on every
 * entrance. The default is once:false, which REVERSES the animation when the
 * element scrolls back out — sections re-hide and replay on every pass, and an
 * at-rest render (top of page, how a crawler sees it) shows below-fold text at
 * opacity 0.
 *
 * Pages with no entrance nodes match nothing; cost is zero.
 */

add_action('wp_head', function () {
    echo "<style id='oxy-entrance-a11y'>"
       . "[data-entrance][data-entrance]{visibility:visible;opacity:0}"
       . "[data-entrance][data-entrance].is-animating,"
       . "[data-entrance][data-entrance].is-animated{opacity:1}"
       . "</style>\n"
       . "<noscript><style>[data-entrance][data-entrance]{opacity:1}</style></noscript>\n";
}, 99);

add_action('wp_footer', function () {
    // The class is defined by scripts that may print AFTER this one, so the
    // patch retries at DOMContentLoaded and load. init() only runs from
    // autoload after imagesLoaded, which is later than both — always in time.
    echo "<script>(function(){function p(){var C=window.BreakdanceEntrance;"
       . "if(!C||!C.prototype||C.prototype.__oxyGuarded)"
       . "return!!(C&&C.prototype&&C.prototype.__oxyGuarded);"
       . "var o=C.prototype.init;C.prototype.init=function(){"
       . "if(!document.querySelector(this.selector))return;"
       . "return o.apply(this,arguments);};"
       . "C.prototype.__oxyGuarded=true;return true;}"
       . "if(!p()){document.addEventListener('DOMContentLoaded',p);"
       . "window.addEventListener('load',p);}"
       . "})();</script>\n";
}, 5);
