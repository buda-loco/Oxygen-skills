/* oxs-parallax — FALLBACK engine only. On browsers with CSS scroll-driven
 * animations this file returns immediately (the CSS in parallax.css runs on
 * the compositor and this script does zero work).
 *
 *   collect(): translate reset → measure once → cache {docTop, h, from, to}
 *        │                (no getBoundingClientRect inside the frame loop)
 *        ▼
 *   IntersectionObserver ──▶ active set (+ will-change on enter, − on exit)
 *        ▼
 *   scroll/resize (passive) ──▶ ONE rAF ──▶ p = (scrollY+vh − docTop)/(vh+h)
 *                                           el.style.translate = lerp(from,to,p)
 *
 * prefers-reduced-motion: engine tears down (and re-arms on change).
 * Dynamic content: window.oxsPlxRefresh() re-collects (deliberately NO
 * MutationObserver — decorative fx don't warrant one).
 * Test hook: window.OXS_PLX_FORCE_FALLBACK forces this engine (test.html only).
 */
(function () {
  'use strict';

  var native = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('animation-timeline: view()');
  if (native && !window.OXS_PLX_FORCE_FALLBACK) { return; }

  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var items = [];   // all .oxs-plx: {el, docTop, h, from, to}
  var active = [];  // subset currently intersecting
  var raf = 0, io = null, resizeT = 0, armed = false;

  function readVar(el, name, fallback) {
    var v = parseFloat(getComputedStyle(el).getPropertyValue(name));
    return isNaN(v) ? fallback : v; // px assumed (documented units rule)
  }

  function collect() {
    if (io) { io.disconnect(); }
    io = new IntersectionObserver(onIntersect, { rootMargin: '80px 0px' });
    items = [];
    active = [];
    var nodes = document.querySelectorAll('.oxs-plx');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      el.style.translate = ''; el.style.scale = ''; // measure the UNtransformed position
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
      if (e.isIntersecting && idx === -1) {
        active.push(it);
        it.el.style.willChange = 'translate';
      } else if (!e.isIntersecting && idx !== -1) {
        active.splice(idx, 1);
        it.el.style.willChange = ''; // never leave layers promoted off-screen
      }
    }
    schedule();
  }

  function tick() {
    raf = 0;
    var y = window.scrollY, vh = window.innerHeight;
    for (var i = 0; i < active.length; i++) {
      var it = active[i];
      var p = (y + vh - it.docTop) / (vh + it.h); // 0 = entering bottom, 1 = leaving top
      if (p < 0) { p = 0; } else if (p > 1) { p = 1; }
      it.el.style.translate = '0 ' + (it.from + (it.to - it.from) * p).toFixed(1) + 'px';
      if (it.sFrom !== 1 || it.sTo !== 1) {
        it.el.style.scale = (it.sFrom + (it.sTo - it.sFrom) * p).toFixed(3);
      }
    }
  }

  function schedule() { if (!raf && active.length) { raf = requestAnimationFrame(tick); } }

  function onResize() {
    clearTimeout(resizeT);
    resizeT = setTimeout(collect, 180);
  }

  function teardown() {
    if (io) { io.disconnect(); io = null; }
    if (raf) { cancelAnimationFrame(raf); raf = 0; }
    window.removeEventListener('scroll', schedule);
    window.removeEventListener('resize', onResize);
    for (var i = 0; i < items.length; i++) {
      items[i].el.style.translate = '';
      items[i].el.style.scale = '';
      items[i].el.style.willChange = '';
    }
    items = []; active = []; armed = false;
  }

  function arm() {
    if (armed) { return; }
    armed = true;
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });
    collect();
  }

  function init() {
    if (mq && mq.matches) { teardown(); return; } // reduced motion → static
    arm();
  }

  if (mq && mq.addEventListener) { mq.addEventListener('change', init); }
  window.oxsPlxRefresh = function () { if (armed) { collect(); } else { init(); } };

  if (document.readyState !== 'loading') { init(); }
  else { document.addEventListener('DOMContentLoaded', init); }
})();
