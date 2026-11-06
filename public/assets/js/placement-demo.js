(function () {
  'use strict';

  var page = document.body.dataset.page;
  var base = document.body.dataset.base || '';
  var VOCAB = window.DOULINGO_VOCAB || {};
  var LEVELS = window.DOULINGO_LEVELS || {};
  var KEY = 'doulingo_placement_demo_v1';

  /* ---------- Lưu tiến độ (trình duyệt) ---------- */
  function load() {
    try { var d = JSON.parse(localStorage.getItem(KEY)); if (d && typeof d === 'object') { return d; } } catch (e) { /* bỏ qua */ }
    return { level: null, learned: { a1: [], a2: [], b1: [] }, xp: 0, streak: 0, last: null };
  }
  function save(d) { try { localStorage.setItem(KEY, JSON.stringify({ level: d.level })); } catch (e) { /* bỏ qua */ } }
  function today() { return new Date().toISOString().slice(0, 10); }

  /* ---------- Tiện ích ---------- */
  function shuffle(a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = (Math.random() * (i + 1)) | 0; var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function $(id) { return document.getElementById(id); }
  function el(html) { var d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstChild; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function speak(text) {
    window.DLAudio?.configure({base:base}); window.DLAudio?.speak(text,{lang:'en'});
  }
  var speakerSvg = '<svg viewBox="0 0 24 24"><path d="M4 9.5v5h3.5L12 18V6L7.5 9.5Z"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/></svg>';

  // 3 đáp án sai + 1 đúng, cùng trình độ
  function options(word, level, field) {
    var pool = VOCAB[level].filter(function (w) { return w[field] !== word[field]; });
    return shuffle(shuffle(pool).slice(0, 3).concat([word])).map(function (w) { return w[field]; });
  }

  /* ---------- Trang chọn trình độ ---------- */
  if (page === 'level') {
    var cur = load().level;
    document.querySelectorAll('.lv').forEach(function (b) {
      if (b.dataset.level === cur) { b.classList.add('cur'); }
      b.addEventListener('click', function () {
        var d = load(); d.level = b.dataset.level; save(d);
        var to = base + '/placement?level=' + encodeURIComponent(b.dataset.level);
        (window.DLNav ? DLNav.go(to) : (window.location.href = to));
      });
    });
  }

  /* ---------- Bài kiểm tra trình độ ---------- */
  if (page === 'placement') {
    var stage = $('stage'), bar = $('bar'), count = $('count');
    var questions = [];
    var onlyLevel = new URLSearchParams(window.location.search).get('level');
    if (!VOCAB[onlyLevel]) { onlyLevel = null; }
    if (onlyLevel) {
      shuffle(VOCAB[onlyLevel]).slice(0, 6).forEach(function (w) { questions.push({ lv: onlyLevel, word: w, opts: options(w, onlyLevel, 'vi') }); });
    } else {
      ['a1', 'a2', 'b1'].forEach(function (lv) {
        shuffle(VOCAB[lv]).slice(0, 3).forEach(function (w) { questions.push({ lv: lv, word: w, opts: options(w, lv, 'vi') }); });
      });
    }
    // Nút X: quay về trang vừa đứng trước, mặc định là /level
    var closeBtn = document.querySelector('.j-top .x');
    if (closeBtn && document.referrer) {
      try {
        var ref = new URL(document.referrer);
        if (ref.origin === window.location.origin && ref.pathname.replace(/\/$/, '') !== window.location.pathname.replace(/\/$/, '')) { closeBtn.href = ref.href; }
      } catch (e) { /* giữ /level */ }
    }
    var qi = 0, correct = { a1: 0, a2: 0, b1: 0 }, locked = false;

    var renderQ = function () {
      var q = questions[qi]; locked = false;
      bar.style.width = (qi / questions.length * 100) + '%';
      count.textContent = (qi + 1) + '/' + questions.length;
      stage.innerHTML = '<span class="q-kind">Kiểm tra trình độ</span>' +
        '<h1 class="q-title">“' + esc(q.word.en) + '” nghĩa là gì?</h1>' +
        '<button class="speak" id="sp" type="button" aria-label="Nghe phát âm">' + speakerSvg + '</button>' +
        '<div class="opts" id="opts"></div><button class="skip" id="dk" type="button">Mình chưa biết từ này</button>';
      $('sp').onclick = function () { speak(q.word.en); };
      q.opts.forEach(function (text, i) {
        var b = el('<button class="opt" type="button"><span class="n">' + (i + 1) + '</span><span>' + esc(text) + '</span></button>');
        b.onclick = function () { answer(text === q.word.vi, b); };
        $('opts').appendChild(b);
      });
      $('dk').onclick = function () { answer(false, null); };
    };
    var answer = function (ok, btn) {
      if (locked) { return; } locked = true;
      var q = questions[qi];
      if (ok) { correct[q.lv]++; }
      document.querySelectorAll('.opt').forEach(function (b) {
        var txt = b.querySelector('span:last-child').textContent;
        if (txt === q.word.vi) { b.classList.add('right'); } else if (b === btn) { b.classList.add('wrong'); } else { b.classList.add('dim'); }
      });
      setTimeout(function () { qi++; if (qi < questions.length) { renderQ(); } else { finish(); } }, 900);
    };
    var finish = function () {
      bar.style.width = '100%';
      var lv = 'a1';
      if (correct.b1 >= 2 && correct.a2 >= 2) { lv = 'b1'; }
      else if (correct.a2 >= 2 || (correct.a1 >= 3 && correct.a2 >= 1)) { lv = 'a2'; }
      var total = correct.a1 + correct.a2 + correct.b1;
      if (onlyLevel) { lv = onlyLevel; }
      var d = load(); d.level = lv; save(d);
      var wrong = questions.length - total;
      var resultHtml = '<div class="result"><div class="badge">' + LEVELS[lv].tag + '</div>' +
        '<h2>Kết quả kiểm tra</h2>' +
        '<p>Bạn trả lời đúng ' + total + '/' + questions.length + ' câu' + (onlyLevel ? ' ở trình độ ' + LEVELS[lv].name + ' (' + LEVELS[lv].tag + ').' : '. Trình độ gợi ý: ' + LEVELS[lv].name + ' (' + LEVELS[lv].tag + ').') + '</p>' +
        '<div class="stats"><div class="stat"><b>' + total + '</b><span>Câu đúng</span></div><div class="stat"><b>' + wrong + '</b><span>Câu sai</span></div><div class="stat"><b>' + LEVELS[lv].tag + '</b><span>Trình độ</span></div></div>' +
        '<div class="row"><button class="btn solid" id="goOn" type="button">Tiếp tục</button><a class="btn line" href="' + base + '/level">Chọn lại trình độ</a></div></div>';
      var promptHtml = '<div class="prompt">' +
        '<svg class="art" viewBox="0 0 190 190" aria-hidden="true"><circle cx="95" cy="95" r="90" fill="#d8efc4"/><rect x="55" y="38" width="80" height="114" rx="14" fill="#f6fbe9" stroke="#123524" stroke-width="3"/>' +
        '<circle cx="95" cy="82" r="18" fill="#3f7d4f"/><path d="M95 92V72M95 80c-6 0-9-3-10-8 6 0 9 3 10 8zM95 77c5 0 8-3 9-7-5 0-8 2-9 7z" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' +
        '<rect x="70" y="118" width="50" height="8" rx="4" fill="#b8862b"/><rect x="76" y="134" width="38" height="6" rx="3" fill="#cfe0bd"/>' +
        '<circle cx="46" cy="52" r="6" fill="#f0c24a"/><circle cx="148" cy="66" r="5" fill="#8cc572"/><circle cx="142" cy="140" r="7" fill="#f0c24a"/></svg>' +
        '<h2>Đã đến lúc tạo hồ sơ!</h2><p>Tạo hồ sơ để lưu trình độ ' + LEVELS[lv].tag + ' và tiến độ học của bạn.</p>' +
        '<a class="big go" href="' + base + '/register?next=learning">Tạo hồ sơ</a><a class="big later" href="' + base + '/">Lúc khác</a>' +
        '<a class="back-link" href="#" id="backRes">← Xem lại kết quả</a></div>';
      var showResult, showPrompt = function () {
        stage.innerHTML = promptHtml;
        $('backRes').onclick = function (e) { e.preventDefault(); showResult(); };
      };
      showResult = function () {
        stage.innerHTML = resultHtml;
        $('goOn').onclick = function () {
          if (new URLSearchParams(location.search).get('onboarding') === '1') { location.assign(base + '/start?step=ready'); return; }
          fetch(base + '/api/v1/users/me', { credentials: 'same-origin' }).then(function (r) { if (r.ok) { window.location.href = base + '/'; } else { showPrompt(); } }).catch(showPrompt);
        };
      };
      showResult();
    };
    document.addEventListener('keydown', function (e) {
      var n = parseInt(e.key, 10);
      if (n >= 1 && n <= 4) { var bs = document.querySelectorAll('.opt'); if (bs[n - 1]) { bs[n - 1].click(); } }
    });
    renderQ();
  }

})();
