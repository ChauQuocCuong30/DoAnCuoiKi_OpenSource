(function () {
  'use strict';

  var base = document.body.dataset.base || '';
  var api = base + '/api/v1';

  var nextPage = new URLSearchParams(window.location.search).get('next');
  var resumeGuest = nextPage === 'guest';
  var returnToLearning = nextPage === 'learning';

  function navigate(path, direction) {
    var target = base + path;

    if (window.DLNav) {
      window.DLNav.go(target, direction);
    } else {
      window.location.href = target;
    }
  }

  if (resumeGuest || returnToLearning) {
    document.querySelectorAll('a[href]').forEach(function (link) {
      var url = new URL(link.href, window.location.href);

      if (url.origin !== window.location.origin) {
        return;
      }

      if (
        url.pathname === base + '/login'
        || url.pathname === base + '/register'
      ) {
        url.searchParams.set(
          'next',
          resumeGuest ? 'guest' : 'learning'
        );

        link.href = url.href;
      }
    });
  }

  function hasLevel() {
    try {
      var d = JSON.parse(localStorage.getItem('doulingo_progress'));
      return !!(d && d.level);
    } catch (e) {
      return false;
    }
  }

  function request(method, path, body, csrfToken) {
    var options = {
      method: method,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json'
      }
    };

    if (csrfToken) {
      options.headers['X-CSRF-Token'] = csrfToken;
    }

    if (body !== undefined) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }

    return fetch(api + path, options).then(function (res) {
      return res.text().then(function (text) {
        var json = null;

        try {
          json = text ? JSON.parse(text) : null;
        } catch (e) {
          json = null;
        }

        return {
          ok: res.ok,
          status: res.status,
          json: json
        };
      });
    });
  }

  function showAlert(form, message, ok) {
    var box = form.querySelector('[data-alert]');

    if (!box) {
      return;
    }

    box.textContent = message || '';
    box.hidden = !message;
    box.classList.toggle('ok', !!ok);
  }

  function showErrors(form, result) {
    form.querySelectorAll('[data-error]').forEach(function (el) {
      el.textContent = '';
    });

    var err = result.json && result.json.error
      ? result.json.error
      : null;

    var details = err && err.details ? err.details : {};

    Object.keys(details).forEach(function (field) {
      var el = form.querySelector(
        '[data-error="' + field + '"]'
      );

      if (el) {
        el.textContent = details[field];
      }
    });

    var messages = {
      INVALID_CREDENTIALS: 'Email hoặc mật khẩu không đúng.',
      VALIDATION_FAILED: 'Vui lòng kiểm tra lại thông tin.',
      EMAIL_ALREADY_EXISTS: 'Email này đã được đăng ký.',
      ACCOUNT_LOCKED: 'Tài khoản đã bị khóa.',
      FORBIDDEN: 'Tài khoản không có quyền thực hiện.'
    };

    showAlert(
      form,
      (err && messages[err.code])
        || (err && err.message)
        || 'Có lỗi xảy ra, vui lòng thử lại.',
      false
    );
  }

  function formData(form) {
    var data = {};

    new FormData(form).forEach(function (value, key) {
      data[key] = value;
    });

    return data;
  }

  function submit(form, work) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var button = form.querySelector('button[type="submit"]');

      button.disabled = true;
      showAlert(form, '');

      form.querySelectorAll('[data-error]').forEach(function (el) {
        el.textContent = '';
      });

      work(formData(form)).then(function (result) {
        if (result) {
          showErrors(form, result);
        }
      }).catch(function () {
        showAlert(
          form,
          'Không kết nối được máy chủ.',
          false
        );
      }).then(function () {
        button.disabled = false;
      });
    });
  }

  var login = document.querySelector('[data-form="login"]');

  if (login) {
    submit(login, function (data) {
      return request(
        'POST',
        '/auth/login',
        data
      ).then(function (r) {
        if (r.ok) {
          navigate(
            r.json.data.role === 'admin'
              ? '/admin/words'
              : (resumeGuest ? '/learning/continue?focus=practice' : '/learning')
          );

          return null;
        }

        return r;
      });
    });
  }

  var register = document.querySelector('[data-form="register"]');

  if (register) {
    submit(register, function (data) {
      return request(
        'POST',
        '/auth/register',
        data
      ).then(function (r) {
        if (!r.ok) {
          return r;
        }

        return request('POST', '/auth/login', {
          email: data.email,
          password: data.password
        }).then(function (l) {
          if (l.ok) {
            navigate(
              '/profile?new=1'
                + (
                  resumeGuest
                    ? '&next=guest'
                    : (returnToLearning ? '&next=learning' : '')
                )
            );

            return null;
          }

          navigate(
            '/login'
              + (
                resumeGuest
                  ? '?next=guest'
                  : (returnToLearning ? '?next=learning' : '')
              ),
            'back'
          );

          return null;
        });
      });
    });
  }

  var profile = document.querySelector('[data-form="profile"]');

  if (profile) {
    var logout = document.querySelector('[data-logout]');

    if (resumeGuest) {
      var home = profile.querySelector('.home-btn');
      var savingGuest = false;

      if (home) {
        home.addEventListener('click', function (event) {
          if (
            event.button !== 0
            || event.ctrlKey
            || event.metaKey
            || event.shiftKey
            || event.altKey
          ) {
            return;
          }

          event.preventDefault();

          if (savingGuest) {
            return;
          }

          var csrf = profile.querySelector('[data-guest-csrf]');

          if (!csrf || !csrf.value) {
            showAlert(
              profile,
              'Phiên không hợp lệ. Hãy tải lại trang rồi thử lại.',
              false
            );

            return;
          }

          savingGuest = true;
          home.setAttribute('aria-busy', 'true');

          showAlert(
            profile,
            'Đang lưu kết quả học thử…',
            true
          );

          request(
            'POST',
            '/learning/guest/claim',
            {},
            csrf.value
          ).then(function (r) {
            if (!r.ok) {
              showErrors(profile, r);
              return;
            }

            if (
              !r.json
              || !r.json.data
              || typeof r.json.data.claimed !== 'boolean'
            ) {
              showAlert(
                profile,
                'Chưa xác nhận được kết quả học thử. Hãy thử lại.',
                false
              );

              return;
            }

            navigate('/');
          }).catch(function () {
            showAlert(
              profile,
              'Chưa lưu được kết quả. Bấm Tới trang chủ để thử lại.',
              false
            );
          }).then(function () {
            savingGuest = false;
            home.removeAttribute('aria-busy');
          });
        });
      }
    }

    var selfId = null;

    request('GET', '/users/me').then(function (r) {
      if (!r.ok) {
        navigate('/login', 'back');
        return;
      }

      var user = r.json.data;

      selfId = user.id !== undefined && user.id !== null
        ? String(user.id)
        : null;

      [
        'email',
        'name',
        'daily_goal_xp',
        'streak_goal_days',
        'timezone'
      ].forEach(function (key) {
        var input = profile.elements[key];

        if (input) {
          input.value = user[key];
        }
      });

      var roleLine = document.querySelector('[data-role]');
      var isAdmin = user.role === 'admin';

      roleLine.textContent = 'Vai trò: '
        + (isAdmin ? 'Quản trị viên' : 'Người dùng');

      roleLine.hidden = false;

      profile.hidden = false;
      logout.hidden = false;

      document.querySelector('[data-welcome]').hidden =
        !/[?&]new=1(&|$)/.test(window.location.search);
    });

    submit(profile, function (data) {
      delete data.email;

      var limits = {
        daily_goal_xp: [1, 1000],
        streak_goal_days: [1, 365]
      };

      var invalid = false;

      Object.keys(limits).forEach(function (key) {
        var raw = String(
          data[key] === undefined ? '' : data[key]
        ).trim();

        var msg = '';

        if (!/^[0-9]+$/.test(raw)) {
          msg = 'Chỉ nhập số nguyên (chữ số 0-9).';
        } else {
          var n = Number(raw);

          if (n < limits[key][0] || n > limits[key][1]) {
            msg = 'Giá trị phải từ '
              + limits[key][0]
              + ' đến '
              + limits[key][1]
              + '.';
          } else {
            data[key] = n;
          }
        }

        if (msg) {
          invalid = true;

          var el = profile.querySelector(
            '[data-error="' + key + '"]'
          );

          if (el) {
            el.textContent = msg;
          }
        }
      });

      if (invalid) {
        return Promise.resolve(null);
      }

      return request(
        'PATCH',
        '/users/me',
        data
      ).then(function (r) {
        if (r.ok) {
          showAlert(
            profile,
            'Đã lưu thay đổi.',
            true
          );

          return null;
        }

        return r;
      });
    });

    logout.addEventListener('click', function () {
      if (logout.disabled) {
        return;
      }

      logout.disabled = true;

      request('POST', '/auth/logout').then(function (r) {
        if (!r.ok) {
          showErrors(profile, r);
          return;
        }

        navigate('/login?next=learning', 'back');
      }).catch(function () {
        showAlert(
          profile,
          'Không kết nối được máy chủ. Hãy thử đăng xuất lại.',
          false
        );
      }).then(function () {
        logout.disabled = false;
      });
    });
  }
})();

(function () {
  'use strict';

  var layout = document.querySelector('.auth-layout');
  var stage = document.querySelector('.stage');

  if (
    !layout
    || !stage
    || window.matchMedia('(prefers-reduced-motion: reduce)').matches
  ) {
    return;
  }

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

  if (
    !layout
    || !card
    || window.matchMedia('(prefers-reduced-motion: reduce)').matches
  ) {
    return;
  }

  layout.addEventListener('mousemove', function (event) {
    var x = event.clientX / window.innerWidth - 0.5;
    var y = event.clientY / window.innerHeight - 0.5;

    card.style.setProperty('--cy', (x * 4).toFixed(2) + 'deg');
    card.style.setProperty('--cx', (-y * 4).toFixed(2) + 'deg');
  });
})();