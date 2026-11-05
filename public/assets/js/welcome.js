(function () {
  'use strict';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Tách chữ: mỗi từ là khối không ngắt, mỗi ký tự là một span có chỉ số --i
  function split(el) {
    var idx = 0;
    function walk(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (child) {
        if (child.nodeType === 3) {
          var frag = document.createDocumentFragment();
          child.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) { return; }
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(' ')); return; }
            var w = document.createElement('span'); w.className = 'w'; w.setAttribute('aria-hidden', 'true');
            Array.from(part).forEach(function (ch) {
              var s = document.createElement('span'); s.className = 'ch'; s.textContent = ch;
              s.style.setProperty('--i', idx++); w.appendChild(s);
            });
            frag.appendChild(w);
          });
          node.replaceChild(frag, child);
        } else if (child.nodeType === 1 && child.tagName !== 'BR') {
          if (child.classList.contains('pp')) { idx += 2; return; }
          walk(child);
        }
      });
    }
    var label = el.getAttribute('aria-label') || el.textContent.replace(/\s+/g, ' ').trim();
    if (!el.hasAttribute('aria-label')) { el.setAttribute('aria-label', label); }
    walk(el);
  }
  document.querySelectorAll('[data-split]').forEach(split);

  // Vòng tick quay của nút play
  var ticks = document.getElementById('ticks');
  if (ticks) {
    for (var i = 0; i < 48; i++) {
      var a = (i / 48) * Math.PI * 2, l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      l.setAttribute('x1', (42 + Math.cos(a) * 34).toFixed(2)); l.setAttribute('y1', (42 + Math.sin(a) * 34).toFixed(2));
      l.setAttribute('x2', (42 + Math.cos(a) * 40).toFixed(2)); l.setAttribute('y2', (42 + Math.sin(a) * 40).toFixed(2));
      ticks.appendChild(l);
    }
  }

  // Hiện khi cuộn tới
  var revealEls = document.querySelectorAll('.reveal');
  if (reduced || !('IntersectionObserver' in window)) {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.3 });
    revealEls.forEach(function (el) { io.observe(el); });
  }

  // Hiệu ứng theo cuộn: hero tan vào nền giấy, ảnh chân dung trôi nhẹ
  var stage = document.getElementById('stage'), melt = document.getElementById('melt');
  var pimg = document.getElementById('pimg'), portrait = document.getElementById('portrait');
  var ticking = false;
  function onScroll() {
    var y = window.scrollY || 0, vh = window.innerHeight;
    if (stage) {
      var k = Math.min(y / (vh * 0.6), 1);
      stage.style.setProperty('--drift', (y * 0.32).toFixed(1) + 'px');
      stage.style.setProperty('--blur', (k * 6).toFixed(2) + 'px');
      melt.style.opacity = Math.min(k * 1.4, 1).toFixed(3);
      melt.style.height = (34 + k * 66).toFixed(1) + '%';
    }
    if (pimg && portrait) {
      var r = portrait.getBoundingClientRect();
      var p = (r.top + r.height / 2 - vh / 2) / vh; // -1..1
      pimg.style.setProperty('--py', (Math.max(-1, Math.min(1, p)) * 6).toFixed(2) + '%');
    }
    ticking = false;
  }
  if (!reduced) {
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
    onScroll();
    // Thẻ 3D nghiêng theo chuột
    var fan = document.getElementById('fan');
    window.addEventListener('mousemove', function (e) {
      var x = e.clientX / window.innerWidth - 0.5, yy = e.clientY / window.innerHeight - 0.5;
      fan.style.setProperty('--fy', (x * 14).toFixed(2) + 'deg');
      fan.style.setProperty('--fx', (-yy * 8).toFixed(2) + 'deg');
    });
  }
})();
