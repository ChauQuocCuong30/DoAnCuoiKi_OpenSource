(function () {
  'use strict';
  var KEY = 'dl_dir', root = document.documentElement, LEAVE = 260;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function get() { try { return sessionStorage.getItem(KEY); } catch (e) { return null; } }
  function set(v) { try { sessionStorage.setItem(KEY, v); } catch (e) {} }
  function del() { try { sessionStorage.removeItem(KEY); } catch (e) {} }
  var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
  var dir = nav.type === 'back_forward' ? 'back' : (get() || 'fade');
  del();
  root.setAttribute('data-dl-dir', dir);
  function isBack(a) {
    if (a.hasAttribute('data-dl-back')) { return true; }
    if (a.classList.contains('back-link') || a.classList.contains('x') || a.classList.contains('back')) { return true; }
    return /^\s*[←‹«]/.test(a.textContent || '');
  }
  function go(url, d) {
    if (reduce) { window.location.href = url; return; }
    d = d || 'fwd';
    set(d);
    root.setAttribute('data-dl-leave', d);
    if (document.body) { document.body.classList.add('dl-leave'); }
    setTimeout(function () { window.location.href = url; }, LEAVE);
  }
  window.DLNav = { go: go };
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download')) { return; }
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) { return; }
    var u; try { u = new URL(a.href, location.href); } catch (err) { return; }
    if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search)) { return; }
    e.preventDefault();
    go(u.href, isBack(a) ? 'back' : 'fwd');
  });
  window.addEventListener('pageshow', function (e) {
    if (e.persisted && document.body) {
      document.body.classList.remove('dl-leave');
      root.setAttribute('data-dl-dir', 'back');
    }
  });
})();
