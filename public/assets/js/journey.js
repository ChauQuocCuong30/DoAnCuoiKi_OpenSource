(function () {
  'use strict';

  var page = document.body.dataset.page;
  var base = document.body.dataset.base || '';
  var VOCAB = window.DOULINGO_VOCAB || {};
  var LEVELS = window.DOULINGO_LEVELS || {};
  var KEY = 'doulingo_progress';

  /* ---------- Lưu tiến độ (trình duyệt) ---------- */
  function load() {
    try { var d = JSON.parse(localStorage.getItem(KEY)); if (d && typeof d === 'object') { return d; } } catch (e) { /* bỏ qua */ }
    return { level: null, learned: { a1: [], a2: [], b1: [] }, xp: 0, streak: 0, last: null };
  }
  function save(d) { try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) { /* bỏ qua */ } }
  function today() { return new Date().toISOString().slice(0, 10); }

  /* ---------- Tiện ích ---------- */
  function shuffle(a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = (Math.random() * (i + 1)) | 0; var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function $(id) { return document.getElementById(id); }
  function el(html) { var d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstChild; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function speak(text) {
    if (!('speechSynthesis' in window)) { return; }
    var u = new SpeechSynthesisUtterance(text); u.lang = 'en-US'; u.rate = 0.9;
    window.speechSynthesis.cancel(); window.speechSynthesis.speak(u);
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
        (window.DLNav ? DLNav.go(base + '/lesson') : (window.location.href = base + '/lesson'));
      });
    });
  }

  /* ---------- Bài kiểm tra trình độ ---------- */
  if (page === 'placement') {
    var stage = $('stage'), bar = $('bar'), count = $('count');
    var questions = [];
    ['a1', 'a2', 'b1'].forEach(function (lv) {
      shuffle(VOCAB[lv]).slice(0, 3).forEach(function (w) { questions.push({ lv: lv, word: w, opts: options(w, lv, 'vi') }); });
    });
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
      var d = load(); d.level = lv; save(d);
      var resultHtml = '<div class="result"><div class="badge">' + LEVELS[lv].tag + '</div>' +
        '<h2>Trình độ gợi ý: ' + LEVELS[lv].name + '</h2>' +
        '<p>Bạn trả lời đúng ' + total + '/' + questions.length + ' câu. Doulingo sẽ bắt đầu bằng bộ từ vựng ' + LEVELS[lv].tag + '.</p>' +
        '<div class="stats"><div class="stat"><b>' + correct.a1 + '/3</b><span>A1</span></div><div class="stat"><b>' + correct.a2 + '/3</b><span>A2</span></div><div class="stat"><b>' + correct.b1 + '/3</b><span>B1</span></div></div>' +
        '<div class="row"><button class="btn solid" id="goOn" type="button">Tiếp tục</button><a class="btn line" href="' + base + '/level">Chọn lại trình độ</a></div></div>';
      var promptHtml = '<div class="prompt">' +
        '<svg class="art" viewBox="0 0 190 190" aria-hidden="true"><circle cx="95" cy="95" r="90" fill="#d8efc4"/><rect x="55" y="38" width="80" height="114" rx="14" fill="#f6fbe9" stroke="#123524" stroke-width="3"/>' +
        '<circle cx="95" cy="82" r="18" fill="#3f7d4f"/><path d="M95 92V72M95 80c-6 0-9-3-10-8 6 0 9 3 10 8zM95 77c5 0 8-3 9-7-5 0-8 2-9 7z" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' +
        '<rect x="70" y="118" width="50" height="8" rx="4" fill="#b8862b"/><rect x="76" y="134" width="38" height="6" rx="3" fill="#cfe0bd"/>' +
        '<circle cx="46" cy="52" r="6" fill="#f0c24a"/><circle cx="148" cy="66" r="5" fill="#8cc572"/><circle cx="142" cy="140" r="7" fill="#f0c24a"/></svg>' +
        '<h2>Đã đến lúc tạo hồ sơ!</h2><p>Tạo hồ sơ để lưu trình độ ' + LEVELS[lv].tag + ' và tiến độ học của bạn.</p>' +
        '<a class="big go" href="' + base + '/register">Tạo hồ sơ</a><a class="big later" href="' + base + '/">Lúc khác</a>' +
        '<a class="back-link" href="#" id="backRes">← Xem lại kết quả</a></div>';
      var showResult = function () {
        stage.innerHTML = resultHtml;
        $('goOn').onclick = function () {
          stage.innerHTML = promptHtml;
          $('backRes').onclick = function (e) { e.preventDefault(); showResult(); };
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

  /* ---------- Bài học theo trình độ ---------- */
  if (page === 'lesson') {
    var data = load(), level = data.level;
    if (!level || !VOCAB[level]) { (window.DLNav ? DLNav.go(base + '/level', 'back') : (window.location.href = base + '/level')); return; }

    var stageL = $('stage'), barL = $('bar'), heartsEl = $('hearts'), foot = $('foot'), fb = $('fb'), act = $('act');
    var learned = (data.learned && data.learned[level]) || [];
    var fresh = VOCAB[level].filter(function (w) { return learned.indexOf(w.en) < 0; });
    var pick = (fresh.length ? fresh : VOCAB[level]).slice(0, 4);
    if (pick.length < 4) { pick = pick.concat(shuffle(VOCAB[level].filter(function (w) { return pick.indexOf(w) < 0; })).slice(0, 4 - pick.length)); }

    // Dựng các bước: mỗi nhóm 4 từ = giới thiệu 4 thẻ + 8 câu luyện
    var steps = [];
    for (var g = 0; g < pick.length; g += 4) {
      var grp = pick.slice(g, g + 4);
      grp.forEach(function (w) { steps.push({ type: 'intro', word: w }); });
      var kinds = ['meaning', 'english', 'listen'], qs = [];
      grp.forEach(function (w, i) {
        qs.push({ type: kinds[i % 3], word: w });
      });
      shuffle(qs).forEach(function (q) { steps.push(q); });
    }
    var total = steps.length, done = 0, hearts = 5, right = 0, wrongCount = 0, firstRight = 0, firstWrong = 0, selected = null, checked = false, current = null;

    var setProgress = function () { barL.style.width = (done / total * 100) + '%'; };
    var setFoot = function (state, title, text, label, enabled) {
      foot.className = 'j-foot' + (state ? ' ' + state : '');
      fb.innerHTML = title ? '<strong>' + esc(title) + '</strong><span>' + esc(text || '') + '</span>' : '';
      act.textContent = label; act.disabled = !enabled;
    };

    var renderStep = function () {
      if (!steps.length) { return finish(true); }
      current = steps[0]; selected = null; checked = false; setProgress();
      var w = current.word;
      if (current.type === 'intro') {
        stageL.innerHTML = '<span class="q-kind">Từ mới</span><h1 class="q-title">Làm quen với từ này</h1>' +
          '<div class="fcard"><div class="en">' + esc(w.en) + '</div><button class="speak" id="sp" type="button" aria-label="Nghe phát âm">' + speakerSvg + '</button>' +
          '<div class="vi">' + esc(w.vi) + '</div><div class="ex">“' + esc(w.ex) + '”</div><div class="exvi">' + esc(w.exVi) + '</div></div>';
        $('sp').onclick = function () { speak(w.en); };
        speak(w.en);
        setFoot('', '', '', 'Tiếp tục', true);
        return;
      }
      var title, kind, field, ans, listenBtn = '';
      if (current.type === 'meaning') { kind = 'Chọn nghĩa'; title = '“' + w.en + '” nghĩa là gì?'; field = 'vi'; ans = w.vi; }
      else if (current.type === 'english') { kind = 'Chọn từ'; title = 'Từ nào có nghĩa là “' + w.vi + '”?'; field = 'en'; ans = w.en; }
      else { kind = 'Nghe rồi chọn'; title = 'Bạn nghe thấy từ nào?'; field = 'en'; ans = w.en; listenBtn = '<button class="speak" id="sp" type="button" aria-label="Nghe">' + speakerSvg + '</button>'; }
      current.ans = ans;
      stageL.innerHTML = '<span class="q-kind">' + kind + '</span><h1 class="q-title">' + esc(title) + '</h1>' + listenBtn + '<div class="opts" id="opts"></div>';
      if (listenBtn) { $('sp').onclick = function () { speak(w.en); }; speak(w.en); }
      options(w, level, field).forEach(function (text, i) {
        var b = el('<button class="opt" type="button"><span class="n">' + (i + 1) + '</span><span>' + esc(text) + '</span></button>');
        b.onclick = function () { if (checked) { return; } selected = text; document.querySelectorAll('.opt').forEach(function (x) { x.classList.remove('sel'); }); b.classList.add('sel'); act.disabled = false; };
        $('opts').appendChild(b);
      });
      setFoot('', '', '', 'Kiểm tra', false);
    };

    var onAct = function () {
      if (current.type === 'intro') { steps.shift(); done++; return renderStep(); }
      if (!checked) {
        checked = true;
        var ok = selected === current.ans;
        document.querySelectorAll('.opt').forEach(function (b) {
          var t = b.querySelector('span:last-child').textContent;
          if (t === current.ans) { b.classList.add('right'); } else if (b.classList.contains('sel')) { b.classList.add('wrong'); } else { b.classList.add('dim'); }
        });
        if (ok) { right++; if (!current.retry) { firstRight++; } setFoot('ok', 'Chính xác!', current.word.en + ' = ' + current.word.vi, 'Tiếp tục', true); }
        else {
          hearts--; wrongCount++; if (!current.retry) { firstWrong++; } heartsEl.textContent = hearts;
          if (!current.retry) { var again = { type: current.type, word: current.word, retry: true }; steps.push(again); total++; }
          setFoot('bad', 'Chưa đúng', 'Đáp án: ' + current.ans, 'Tiếp tục', true);
        }
        current.ok = ok; return;
      }
      steps.shift(); done++;
      if (hearts <= 0) { return finish(false); }
      renderStep();
    };
    act.onclick = onAct;
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !act.disabled) { onAct(); return; }
      var n = parseInt(e.key, 10);
      if (n >= 1 && n <= 4 && !checked) { var bs = document.querySelectorAll('.opt'); if (bs[n - 1]) { bs[n - 1].click(); } }
    });

    var finish = function (win) {
      barL.style.width = '100%';
      foot.style.display = 'none';
      var d = load(); var xp = right * 10;
      if (win) {
        d.learned = d.learned || { a1: [], a2: [], b1: [] };
        d.learned[level] = d.learned[level] || [];
        pick.forEach(function (w) { if (d.learned[level].indexOf(w.en) < 0) { d.learned[level].push(w.en); } });
        d.xp = (d.xp || 0) + xp;
        var t = today();
        if (d.last !== t) {
          var y = new Date(); y.setDate(y.getDate() - 1);
          d.streak = d.last === y.toISOString().slice(0, 10) ? (d.streak || 0) + 1 : 1; d.last = t;
        }
        save(d);
      }
      var totalQ = firstRight + firstWrong;
      var resultHtml = '<div class="result"><div class="badge">' + LEVELS[level].tag + '</div>' +
        '<h2>' + (win ? 'Kết quả kiểm tra' : 'Hết tim rồi') + '</h2>' +
        '<p>Bạn trả lời đúng ' + firstRight + '/' + totalQ + ' câu ở trình độ ' + LEVELS[level].name + ' (' + LEVELS[level].tag + ').</p>' +
        '<div class="stats"><div class="stat"><b>' + firstRight + '</b><span>Câu đúng</span></div><div class="stat"><b>' + firstWrong + '</b><span>Câu sai</span></div>' +
        '<div class="stat"><b>' + LEVELS[level].tag + '</b><span>Trình độ</span></div></div>' +
        '<div class="row"><button class="btn solid" id="goOn" type="button">Tiếp tục</button><a class="btn line" href="' + base + '/level">Chọn lại trình độ</a></div></div>';
      var promptHtml = '<div class="prompt">' +
        '<svg class="art" viewBox="0 0 190 190" aria-hidden="true"><circle cx="95" cy="95" r="90" fill="#d8efc4"/><rect x="55" y="38" width="80" height="114" rx="14" fill="#f6fbe9" stroke="#123524" stroke-width="3"/>' +
        '<circle cx="95" cy="82" r="18" fill="#3f7d4f"/><path d="M95 92V72M95 80c-6 0-9-3-10-8 6 0 9 3 10 8zM95 77c5 0 8-3 9-7-5 0-8 2-9 7z" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' +
        '<rect x="70" y="118" width="50" height="8" rx="4" fill="#b8862b"/><rect x="76" y="134" width="38" height="6" rx="3" fill="#cfe0bd"/>' +
        '<circle cx="46" cy="52" r="6" fill="#f0c24a"/><circle cx="148" cy="66" r="5" fill="#8cc572"/><circle cx="142" cy="140" r="7" fill="#f0c24a"/></svg>' +
        '<h2>Đã đến lúc tạo hồ sơ!</h2><p>Tạo hồ sơ để lưu trình độ ' + LEVELS[level].tag + ' và tiến độ học của bạn.</p>' +
        '<a class="big go" href="' + base + '/register">Tạo hồ sơ</a><a class="big later" href="' + base + '/">Lúc khác</a>' +
        '<a class="back-link" href="#" id="backRes">← Xem lại kết quả</a></div>';
      var showResult = function () {
        stageL.innerHTML = resultHtml;
        $('goOn').onclick = function () {
          // Đã đăng nhập thì vào thẳng trang chủ, chưa đăng nhập thì mời tạo hồ sơ
          fetch(base + '/api/v1/users/me', { credentials: 'same-origin' }).then(function (r) {
            if (r.ok) { (window.DLNav ? DLNav.go(base + '/') : (window.location.href = base + '/')); return; }
            stageL.innerHTML = promptHtml;
            $('backRes').onclick = function (e) { e.preventDefault(); showResult(); };
          }).catch(function () {
            stageL.innerHTML = promptHtml;
            $('backRes').onclick = function (e) { e.preventDefault(); showResult(); };
          });
        };
      };
      showResult();
    };
    renderStep();
  }
})();
