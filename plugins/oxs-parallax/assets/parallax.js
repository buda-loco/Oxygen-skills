/* oxs-parallax runtime. Scroll-mode effects run as pure CSS on the compositor (view() timeline);
 * this script only covers what CSS can't do on its own:
 *
 *   1. One-off PARALLAX  — IntersectionObserver adds .oxs-plx--in on first entry; CSS transition
 *                          does the move, then holds (unobserved, so it never rewinds).
 *   2. Scroll-mode PARALLAX fallback — where view() is unsupported, an IO + one-rAF loop writes
 *                          el.style.translate/scale from cached bounds. Idle when view() exists.
 *   3. REVEALS — arms one-off / unsupported reveal elements (.oxs-reveal--armed → --in). Scroll-mode
 *                reveals with view() support are pure CSS and need nothing here.
 *
 * Mode per element (see modeFor): a --off/--oneoff/--scroll class wins, else the global <html>
 * class (oxs-plx-mode-off|scroll|oneoff), else off.
 * prefers-reduced-motion: CSS neutralises everything; the parallax fallback also tears down.
 * Test hook: window.OXS_PLX_FORCE_FALLBACK. Dynamic content: window.oxsPlxRefresh().
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var native = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('animation-timeline: view()');
  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

  // Resolve the effective mode for an element. `kind` is 'oxs-plx' (parallax) or 'oxs-reveal'
  // (reveals): a per-element --off/--oneoff/--scroll class wins, else the global <html> mode,
  // else 'off' (the default when no mode is set).
  function modeFor(el, kind) {
    if (el.classList.contains(kind + '--off'))    { return 'off'; }
    if (el.classList.contains(kind + '--oneoff')) { return 'oneoff'; }
    if (el.classList.contains(kind + '--scroll')) { return 'scroll'; }
    if (root.classList.contains('oxs-plx-mode-scroll')) { return 'scroll'; }
    if (root.classList.contains('oxs-plx-mode-oneoff')) { return 'oneoff'; }
    return 'off';
  }

  /* =========================================================================
     1. ONE-OFF — reveal once on entry (every browser; CSS transition does it)
     ========================================================================= */
  var oneoffIO = null;
  function collectOneOff() {
    if (oneoffIO) { oneoffIO.disconnect(); oneoffIO = null; }
    var els = [];
    var nodes = document.querySelectorAll('.oxs-plx');
    for (var i = 0; i < nodes.length; i++) { if (modeFor(nodes[i],'oxs-plx') === 'oneoff') { els.push(nodes[i]); } }
    if (!els.length) { return; }
    if (!('IntersectionObserver' in window)) {
      for (var j = 0; j < els.length; j++) { els[j].classList.add('oxs-plx--in'); }
      return;
    }
    oneoffIO = new IntersectionObserver(function (entries) {
      for (var k = 0; k < entries.length; k++) {
        if (entries[k].isIntersecting) {
          entries[k].target.classList.add('oxs-plx--in');
          oneoffIO.unobserve(entries[k].target); // fire once — never rewinds
        }
      }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.01 });
    for (var m = 0; m < els.length; m++) { oneoffIO.observe(els[m]); }
  }

  /* =========================================================================
     2. SCROLL-DRIVEN fallback — only when CSS scroll timelines are unsupported
     ========================================================================= */
  var items = [], active = [], raf = 0, io = null, resizeT = 0, armed = false;
  var runFallback = !native || window.OXS_PLX_FORCE_FALLBACK;

  function readVar(el, name, fallback) {
    var v = parseFloat(getComputedStyle(el).getPropertyValue(name));
    return isNaN(v) ? fallback : v;
  }

  function collectScroll() {
    if (io) { io.disconnect(); }
    io = new IntersectionObserver(onIntersect, { rootMargin: '80px 0px' });
    items = []; active = [];
    var nodes = document.querySelectorAll('.oxs-plx');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (modeFor(el,'oxs-plx') !== 'scroll') { continue; } // one-off handled by engine #1
      el.style.translate = ''; el.style.scale = '';
      var r = el.getBoundingClientRect();
      items.push({
        el: el,
        docTop: r.top + window.scrollY,
        h: r.height || 1,
        from: readVar(el, '--plx-from', 40),
        to: readVar(el, '--plx-to', -40),
        sFrom: readVar(el, '--plx-scale-from', 1),
        sTo: readVar(el, '--plx-scale-to', 1)
      });
      io.observe(el);
    }
    schedule();
  }

  function onIntersect(entries) {
    for (var i = 0; i < entries.length; i++) {
      var e = entries[i], it = null, j;
      for (j = 0; j < items.length; j++) { if (items[j].el === e.target) { it = items[j]; break; } }
      if (!it) { continue; }
      var idx = active.indexOf(it);
      if (e.isIntersecting && idx === -1) { active.push(it); it.el.style.willChange = 'translate'; }
      else if (!e.isIntersecting && idx !== -1) { active.splice(idx, 1); it.el.style.willChange = ''; }
    }
    schedule();
  }

  function tick() {
    raf = 0;
    var y = window.scrollY, vh = window.innerHeight;
    for (var i = 0; i < active.length; i++) {
      var it = active[i];
      var p = (y + vh - it.docTop) / (vh + it.h);
      if (p < 0) { p = 0; } else if (p > 1) { p = 1; }
      it.el.style.translate = '0 ' + (it.from + (it.to - it.from) * p).toFixed(1) + 'px';
      if (it.sFrom !== 1 || it.sTo !== 1) {
        it.el.style.scale = (it.sFrom + (it.sTo - it.sFrom) * p).toFixed(3);
      }
    }
  }

  function schedule() { if (!raf && active.length) { raf = requestAnimationFrame(tick); } }
  function onResize() { clearTimeout(resizeT); resizeT = setTimeout(collectScroll, 180); }

  function teardownScroll() {
    if (io) { io.disconnect(); io = null; }
    if (raf) { cancelAnimationFrame(raf); raf = 0; }
    window.removeEventListener('scroll', schedule);
    window.removeEventListener('resize', onResize);
    for (var i = 0; i < items.length; i++) {
      items[i].el.style.translate = ''; items[i].el.style.scale = ''; items[i].el.style.willChange = '';
    }
    items = []; active = []; armed = false;
  }

  function armScroll() {
    if (armed) { return; }
    armed = true;
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });
    collectScroll();
  }

  /* =========================================================================
     3. REVEALS — arm one-off / fallback reveal elements. Scroll-mode reveals on
        browsers with view() support are pure CSS and need no JS here.
     ========================================================================= */
  var revealIO = null;
  function collectReveals() {
    if (revealIO) { revealIO.disconnect(); revealIO = null; }
    var jsEls = [];
    var nodes = document.querySelectorAll('.oxs-reveal');
    for (var i = 0; i < nodes.length; i++) {
      var m = modeFor(nodes[i],'oxs-reveal');
      if (m === 'off') { continue; }                                 // visible, no reveal
      if (m === 'scroll' && native && !window.OXS_PLX_FORCE_FALLBACK) { continue; } // pure CSS
      jsEls.push(nodes[i]);                                          // one-off or unsupported → JS
    }
    if (!jsEls.length) { return; }
    for (var a = 0; a < jsEls.length; a++) { jsEls[a].classList.add('oxs-reveal--armed'); } // hide
    if (!('IntersectionObserver' in window)) {
      for (var b = 0; b < jsEls.length; b++) { jsEls[b].classList.add('oxs-reveal--in'); }
      return;
    }
    revealIO = new IntersectionObserver(function (entries) {
      for (var e = 0; e < entries.length; e++) {
        if (entries[e].isIntersecting) {
          entries[e].target.classList.add('oxs-reveal--in');
          revealIO.unobserve(entries[e].target); // once — one-off never rewinds
        }
      }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.01 });
    for (var o = 0; o < jsEls.length; o++) { revealIO.observe(jsEls[o]); }
  }

  /* ============================ orchestration ============================ */
  function init() {
    collectOneOff();  // one-off parallax
    collectReveals(); // reveals (one-off / fallback arming)
    if (!runFallback) { return; }
    if (mq && mq.matches) { teardownScroll(); return; }
    armScroll();
  }

  if (mq && mq.addEventListener) { mq.addEventListener('change', init); }
  window.oxsPlxRefresh = function () {
    collectOneOff();
    collectReveals();
    if (runFallback) { if (armed) { collectScroll(); } else { init(); } }
  };

  if (document.readyState !== 'loading') { init(); }
  else { document.addEventListener('DOMContentLoaded', init); }
})();
