/* oxs-parallax runtime — two responsibilities:
 *
 *   1. ONE-OFF mode (all browsers): an IntersectionObserver adds .oxs-plx--in the first time a
 *      one-off element enters the viewport; the CSS transition does the reveal, then it holds.
 *      Scrolling back never rewinds it (we unobserve after the first trigger).
 *
 *   2. SCROLL-DRIVEN fallback (only where CSS scroll timelines are unsupported): the classic
 *      IntersectionObserver + one-rAF engine writes el.style.translate/scale from cached bounds.
 *      On browsers WITH animation-timeline: view(), the CSS handles scroll mode and this engine
 *      stays idle.
 *
 *   mode resolution per element: .oxs-plx--oneoff / .oxs-plx--scroll win; otherwise the global
 *   <html> class (oxs-plx-mode-oneoff → one-off, anything else → scroll).
 *
 *   prefers-reduced-motion: CSS neutralises both modes; the scroll engine also tears down.
 *   Test hook: window.OXS_PLX_FORCE_FALLBACK forces the scroll fallback (test.html only).
 *   Dynamic content: window.oxsPlxRefresh() re-scans both engines.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var native = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('animation-timeline: view()');
  var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

  function modeOf(el) {
    if (el.classList.contains('oxs-plx--off'))    { return 'off'; }
    if (el.classList.contains('oxs-plx--oneoff')) { return 'oneoff'; }
    if (el.classList.contains('oxs-plx--scroll')) { return 'scroll'; }
    if (root.classList.contains('oxs-plx-mode-scroll')) { return 'scroll'; }
    if (root.classList.contains('oxs-plx-mode-oneoff')) { return 'oneoff'; }
    return 'off'; // default when no mode is set on <html>
  }

  /* =========================================================================
     1. ONE-OFF — reveal once on entry (every browser; CSS transition does it)
     ========================================================================= */
  var oneoffIO = null;
  function collectOneOff() {
    if (oneoffIO) { oneoffIO.disconnect(); oneoffIO = null; }
    var els = [];
    var nodes = document.querySelectorAll('.oxs-plx');
    for (var i = 0; i < nodes.length; i++) { if (modeOf(nodes[i]) === 'oneoff') { els.push(nodes[i]); } }
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
      if (modeOf(el) !== 'scroll') { continue; } // one-off handled by engine #1
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

  /* ============================ orchestration ============================ */
  function init() {
    collectOneOff(); // one-off works regardless of reduced-motion (CSS gates the visuals)
    if (!runFallback) { return; }
    if (mq && mq.matches) { teardownScroll(); return; }
    armScroll();
  }

  if (mq && mq.addEventListener) { mq.addEventListener('change', init); }
  window.oxsPlxRefresh = function () {
    collectOneOff();
    if (runFallback) { if (armed) { collectScroll(); } else { init(); } }
  };

  if (document.readyState !== 'loading') { init(); }
  else { document.addEventListener('DOMContentLoaded', init); }
})();
