(function () {
  'use strict';

  var base = document.body.dataset.base || '';
  var api = base + '/api/v1';

  // Đã chọn trình độ ở bước làm quen thì đăng ký xong học luôn
  function hasLevel() {
    try { var d = JSON.parse(localStorage.getItem('doulingo_progress')); return !!(d && d.level); } catch (e) { return false; }
  }

  function request(method, path, body) {
    var options = { method: method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (body !== undefined) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    return fetch(api + path, options).then(function (res) {
      return res.text().then(function (text) {
        var json = null;
        try { json = text ? JSON.parse(text) : null; } catch (e) { json = null; }
        return { ok: res.ok, status: res.status, json: json };
      });
    });
  }

  function showAlert(form, message, ok) {
    var box = form.querySelector('[data-alert]');
    if (!box) { return; }
    box.textContent = message || '';
    box.hidden = !message;
    box.classList.toggle('ok', !!ok);
  }

  function showErrors(form, result) {
    form.querySelectorAll('[data-error]').forEach(function (el) { el.textContent = ''; });
    var err = result.json && result.json.error ? result.json.error : null;
    var details = err && err.details ? err.details : {};
    Object.keys(details).forEach(function (field) {
      var el = form.querySelector('[data-error="' + field + '"]');
      if (el) { el.textContent = details[field]; }
    });
    var messages = {
      INVALID_CREDENTIALS: 'Email hoặc mật khẩu không đúng.',
      VALIDATION_FAILED: 'Vui lòng kiểm tra lại thông tin.',
      EMAIL_ALREADY_EXISTS: 'Email này đã được đăng ký.',
      ACCOUNT_LOCKED: 'Tài khoản đã bị khóa.',
      FORBIDDEN: 'Tài khoản không có quyền thực hiện.'
    };
    showAlert(form, (err && messages[err.code]) || (err && err.message) || 'Có lỗi xảy ra, vui lòng thử lại.', false);
  }

  function formData(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  }

  function submit(form, work) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      showAlert(form, '');
      form.querySelectorAll('[data-error]').forEach(function (el) { el.textContent = ''; });
      work(formData(form)).then(function (result) {
        if (result) { showErrors(form, result); }
      }).catch(function () {
        showAlert(form, 'Không kết nối được máy chủ.', false);
      }).then(function () { button.disabled = false; });
    });
  }

  var login = document.querySelector('[data-form="login"]');
  if (login) {
    submit(login, function (data) {
      return request('POST', '/auth/login', data).then(function (r) {
        if (r.ok) { window.location.href = base + '/profile'; return null; }
        return r;
      });
    });
  }

  var register = document.querySelector('[data-form="register"]');
  if (register) {
    submit(register, function (data) {
      return request('POST', '/auth/register', data).then(function (r) {
        if (!r.ok) { return r; }
        return request('POST', '/auth/login', { email: data.email, password: data.password }).then(function (l) {
          if (l.ok) { window.location.href = base + (hasLevel() ? '/lesson' : '/profile'); return null; }
          window.location.href = base + '/login';
          return null;
        });
      });
    });
  }

  var profile = document.querySelector('[data-form="profile"]');
  if (profile) {
    var logout = document.querySelector('[data-logout]');

    request('GET', '/users/me').then(function (r) {
      if (!r.ok) { window.location.href = base + '/login'; return; }
      var user = r.json.data;
      ['email', 'name', 'daily_goal_xp', 'streak_goal_days', 'timezone'].forEach(function (key) {
        var input = profile.elements[key];
        if (input) { input.value = user[key]; }
      });
      profile.hidden = false;
      logout.hidden = false;
    });

    submit(profile, function (data) {
      delete data.email;
      data.daily_goal_xp = parseInt(data.daily_goal_xp, 10);
      data.streak_goal_days = parseInt(data.streak_goal_days, 10);
      return request('PATCH', '/users/me', data).then(function (r) {
        if (r.ok) { showAlert(profile, 'Đã lưu thay đổi.', true); return null; }
        return r;
      });
    });

    logout.addEventListener('click', function () {
      request('POST', '/auth/logout').then(function () { window.location.href = base + '/login'; });
    });
  }
})();

(function () {
  'use strict';
  var layout = document.querySelector('.auth-layout');
  var stage = document.querySelector('.stage');
  if (!layout || !stage || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
  layout.addEventListener('mousemove', function (event) {
    var x = event.clientX / window.innerWidth - 0.5;
    var y = event.clientY / window.innerHeight - 0.5;
    stage.style.setProperty('--ry', (x * 30).toFixed(1) + 'deg');
    stage.style.setProperty('--rx', (8 - y * 20).toFixed(1) + 'deg');
  });
})();

(function () {
  'use strict';
  var layout = document.querySelector('.auth-layout');
  var card = document.querySelector('.auth-card');
  if (!layout || !card || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
  layout.addEventListener('mousemove', function (event) {
    var x = event.clientX / window.innerWidth - 0.5;
    var y = event.clientY / window.innerHeight - 0.5;
    card.style.setProperty('--cy', (x * 4).toFixed(2) + 'deg');
    card.style.setProperty('--cx', (-y * 4).toFixed(2) + 'deg');
  });
})();
