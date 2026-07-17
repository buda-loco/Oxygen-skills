/* Static Mirror — client-side search fallback.
   Only shipped inside the static export (injected before </body>). Hooks the
   header search overlay (.sdlg, built by site.js): instead of submitting to
   WordPress (which doesn't exist on the static host), it loads
   /search-index.json once and renders matching links inside the overlay. */
(function () {
  var ready = function (f) {
    if (document.readyState !== 'loading') f();
    else document.addEventListener('DOMContentLoaded', f);
  };
  ready(function () {
    var dlg = document.querySelector('.sdlg');
    if (!dlg) return;
    var form = dlg.querySelector('form');
    var input = dlg.querySelector('input[type="search"]');
    if (!form || !input) return;

    var list = document.createElement('div');
    list.className = 'sdlg__results';
    form.insertAdjacentElement('afterend', list);

    var idx = null;
    var load = function () {
      if (idx) return Promise.resolve(idx);
      return fetch('/search-index.json')
        .then(function (r) { return r.json(); })
        .then(function (d) { idx = d; return d; });
    };
    // only same-origin / relative destinations — never javascript: etc.
    var safeUrl = function (u) {
      try {
        var url = new URL(u, location.origin);
        return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : '#';
      } catch (e) { return '#'; }
    };
    var render = function () {
      var q = input.value.trim().toLowerCase();
      if (!q) { list.textContent = ''; return; }
      load().then(function (d) {
        var hits = d.filter(function (e) { return e.text.indexOf(q) > -1; }).slice(0, 8);
        list.textContent = '';
        if (!hits.length) {
          var none = document.createElement('p');
          none.className = 'sdlg__none';
          none.textContent = 'Nothing found — try another word.';
          list.appendChild(none);
          return;
        }
        hits.forEach(function (e) {
          var a = document.createElement('a');   // textContent + safeUrl = no HTML/JS injection
          a.href = safeUrl(e.url);
          a.textContent = e.title;
          list.appendChild(a);
        });
      });
    };
    form.addEventListener('submit', function (e) { e.preventDefault(); render(); });
    input.addEventListener('input', render);
  });
})();
