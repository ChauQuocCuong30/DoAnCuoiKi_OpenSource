(() => {
    'use strict';

    const $ = id => document.getElementById(id);
    const nfc = value => String(value ?? '').normalize('NFC');

    function text(id, value) {
        if ($(id)) {
            $(id).textContent = nfc(value);
        }
    }

    const walker = document.createTreeWalker(
        document.body,
        NodeFilter.SHOW_TEXT
    );

    let node;

    while ((node = walker.nextNode())) {
        if (!['SCRIPT', 'STYLE', 'TEXTAREA'].includes(
            node.parentElement?.tagName
        )) {
            node.nodeValue = nfc(node.nodeValue);
        }
    }

    if (
        !matchMedia('(prefers-reduced-motion: reduce)').matches
        && matchMedia('(hover: hover) and (pointer: fine)').matches
    ) {
        document.querySelectorAll('[data-tilt]').forEach(scene => {
            let frame = 0;
            let rx = 0;
            let ry = 0;

            scene.addEventListener('pointermove', event => {
                const rect = scene.getBoundingClientRect();

                rx = -(
                    (event.clientY - rect.top) / rect.height - .5
                ) * 12;

                ry = (
                    (event.clientX - rect.left) / rect.width - .5
                ) * 16;

                if (!frame) {
                    frame = requestAnimationFrame(() => {
                        scene.style.setProperty('--rx', rx + 'deg');
                        scene.style.setProperty('--ry', ry + 'deg');
                        frame = 0;
                    });
                }
            });

            scene.addEventListener('pointerleave', () => {
                cancelAnimationFrame(frame);
                frame = 0;
                scene.style.setProperty('--rx', '0deg');
                scene.style.setProperty('--ry', '0deg');
            });
        });
    }

    const boot = $('lu-data')
        ? JSON.parse($('lu-data').textContent)
        : null;

    if (!boot) return;

    function gate() {
        if ($('lu-gate') && !$('lu-gate').open) {
            $('lu-gate').showModal();
        }
    }

    document.querySelectorAll('[data-later]').forEach(button => {
        button.addEventListener('click', () => {
            $('lu-gate').close();
        });
    });

    let toastTimer;

    document.querySelectorAll('[data-locked]').forEach(button => {
        button.addEventListener('click', () => {
            if (boot.guest && boot.pendingCompleted) {
                gate();
                return;
            }

            text('lu-toast', 'Hoàn thành ải trước nhé.');
            $('lu-toast').hidden = false;

            clearTimeout(toastTimer);

            toastTimer = setTimeout(() => {
                $('lu-toast').hidden = true;
            }, 4000);
        });
    });

    if (boot.mode === 'map') return;

    async function api(path, body) {
        const response = await fetch(
            boot.base + '/api/v1/learning/' + path,
            {
                method: body === undefined ? 'GET' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': boot.csrf
                },
                body: body === undefined
                    ? undefined
                    : JSON.stringify(body)
            }
        );

        const json = await response.json().catch(() => null);

        if (
            !response.ok
            || !json
            || !Object.hasOwn(json, 'data')
        ) {
            const error = new Error(
                response.status >= 500
                    ? 'Chưa kết nối được máy chủ. Bạn thử lại nhé.'
                    : json?.error?.message
                        || 'Chưa nhận được phản hồi hợp lệ.'
            );

            if (!response.ok) {
                error.status = response.status;
            }

            error.code = json?.error?.code;

            if (error.status === 401) {
                location.assign(
                    boot.base + '/login?next=guest'
                );
            }

            if (error.code === 'TRIAL_SIGNED_IN') {
                location.assign(boot.base + '/learning');
            }

            throw error;
        }

        return json.data;
    }

    if (boot.mode === 'continue') {
        let saving = false;

        async function claim() {
            if (saving) return;

            saving = true;
            $('lu-claim-retry').hidden = true;
            text('lu-claim-note', 'Đang lưu kết quả…');

            try {
                const result = await api('guest/claim', {});

                if (!result.claimed) {
                    text(
                        'lu-claim-note',
                        'Phiên học thử không còn sẵn. ' +
                        'Bạn có thể học tiếp từ lộ trình.'
                    );
                    return;
                }

                location.replace(
                    boot.base + (
                        result.next_lesson_id
                            ? '/learning/lesson/' +
                                result.next_lesson_id
                            : '/learning'
                    )
                );
            } catch (error) {
                text('lu-claim-note', error.message);
                $('lu-claim-retry').hidden = false;
            } finally {
                saving = false;
            }
        }

        $('lu-claim-retry').addEventListener('click', claim);
        claim();
        return;
    }

    if (boot.mode !== 'lesson') return;

    const words = Array.isArray(boot.words) ? boot.words : [];
    const focus = new URLSearchParams(location.search).get('focus');

    let wordIndex = 0;
    let state = null;
    let busy = false;
    let initializing = true;
    let pending = null;
    let chosenTokens = [];
    let mediaFault = false;
    let sound = null;
    let generation = 0;

    function note(message = '', error = false) {
        text('lu-feedback', message);
        $('lu-feedback').classList.toggle('error', error);
    }

    function media(value) {
        if (
            typeof value !== 'string'
            || /[\x00-\x20<>"'\\]/.test(value)
        ) {
            return null;
        }

        if (value.startsWith('/') && !value.startsWith('//')) {
            return boot.base
                && !value.startsWith(boot.base + '/')
                ? boot.base + value
                : value;
        }

        try {
            return new URL(value).protocol === 'https:'
                ? value
                : null;
        } catch {
            return null;
        }
    }

    function stopSound() {
        sound?.pause();
        $('lu-audio').pause();

        if ('speechSynthesis' in window) {
            speechSynthesis.cancel();
        }
    }

    function showWords() {
        stopSound();
        note();

        $('lu-words').hidden = false;
        $('lu-question').hidden = true;
        $('lu-result').hidden = true;

        const word = words[wordIndex];

        text('lu-word', word?.english || 'Chưa có từ vựng');
        text('lu-ipa', word?.ipa || '');
        text('lu-meaning', word?.vietnamese || '');
        text('lu-example-en', word?.example || '');
        text('lu-example-vi', word?.example_translation || '');

        $('lu-example').hidden =
            !word?.example && !word?.example_translation;

        $('lu-prev').disabled = !word || wordIndex === 0;
        $('lu-next-word').disabled =
            !word || wordIndex >= words.length - 1;
        $('lu-sound').disabled = !word;

        const src = media(word?.image);

        $('lu-image').hidden = !src;
        $('lu-image').alt = nfc(word?.english || '');

        if (src) {
            $('lu-image').src = src;
        } else {
            $('lu-image').removeAttribute('src');
        }

        text(
            'lu-count',
            word
                ? 'Từ ' + (wordIndex + 1) + '/' + words.length
                : ''
        );

        $('lu-progress').max = Math.max(1, words.length);
        $('lu-progress').value = word ? wordIndex + 1 : 0;

        text('lu-hearts', '');

        $('lu-start').disabled =
            initializing
            || busy
            || (!boot.ready && !boot.sessionId && !state);

        text(
            'lu-start',
            state ? 'Tiếp tục →' : 'Luyện tập →'
        );
    }

    $('lu-image').addEventListener('error', () => {
        $('lu-image').hidden = true;
    });

    $('lu-prev').addEventListener('click', () => {
        if (wordIndex > 0) {
            wordIndex--;
            showWords();
        }
    });

    $('lu-next-word').addEventListener('click', () => {
        if (wordIndex < words.length - 1) {
            wordIndex++;
            showWords();
        }
    });

    $('lu-sound').addEventListener('click', () => {
        const word = words[wordIndex];

        if (!word) return;

        stopSound();

        const src = media(word.audio);

        if (src) {
            sound = new Audio(src);

            sound.play().catch(() => {
                note('Chưa phát được âm thanh.', true);
            });
        } else if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(
                nfc(word.english)
            );

            utterance.lang = 'en-US';
            speechSynthesis.speak(utterance);
        } else {
            note('Từ này chưa có âm thanh.', true);
        }
    });

    function summary() {
        text(
            'lu-count',
            state.answered_main + '/' + state.total_questions
        );

        text(
            'lu-hearts',
            '♥ ' + state.current_hp + '/' + state.max_hp
        );

        $('lu-progress').max = state.total_questions;
        $('lu-progress').value = state.answered_main;
    }

    function inputAnswer() {
        const q = state.question;
        const host = $('lu-controls');

        if (q.content.options) {
            return {
                option_id:
                    host.querySelector('input:checked')?.value || ''
            };
        }

        if (q.type === 'token_order') {
            return { token_ids: chosenTokens.slice() };
        }

        if (q.type === 'match_pairs') {
            return {
                pairs: [...host.querySelectorAll('select')].map(
                    select => ({
                        left_id: select.dataset.leftId,
                        right_id: select.value
                    })
                )
            };
        }

        return {
            text: host.querySelector('input').value
        };
    }

    function complete() {
        if (!state?.question) return false;

        const answer = inputAnswer();
        const q = state.question;

        if (q.content.options) {
            return Boolean(answer.option_id);
        }

        if (q.type === 'token_order') {
            return answer.token_ids.length === q.content.tokens.length;
        }

        if (q.type === 'match_pairs') {
            return answer.pairs.every(pair => pair.right_id)
                && new Set(
                    answer.pairs.map(pair => pair.right_id)
                ).size === answer.pairs.length;
        }

        return answer.text.trim() !== '';
    }

    function sync() {
        $('lu-submit').disabled =
            busy || mediaFault || (!pending && !complete());
    }

    function brokenMedia(version) {
        if (version !== generation) return;

        mediaFault = true;
        $('lu-media-retry').hidden = false;
        note('Chưa tải được tài nguyên câu hỏi.', true);
        sync();
    }

    function render() {
        if (!state) return;

        stopSound();
        note();
        summary();
        generation++;

        $('lu-words').hidden = true;
        $('lu-next-question').hidden = true;
        $('lu-media-retry').hidden = true;

        const finished = state.status !== 'in_progress';

        $('lu-question').hidden = finished;
        $('lu-result').hidden = !finished;

        if (finished) {
            const passed = state.status === 'completed';

            text(
                'lu-result-title',
                passed ? 'Bạn làm tốt lắm!' : 'Thử lại một chút nhé.'
            );

            text(
                'lu-score',
                state.correct_count + '/' +
                state.total_questions + ' câu đúng' +
                (
                    !boot.guest && passed
                        ? ' · +' + (state.xp_earned || 0) + ' XP'
                        : ''
                )
            );

            text(
                'lu-result-next',
                passed ? 'Ải tiếp theo →' : 'Thử lại'
            );

            return;
        }

        const q = state.question;

        if (!q) {
            note('Chưa có câu hỏi. Bạn tải lại trang nhé.', true);
            return;
        }

        text(
            'lu-phase',
            {
                main: 'Luyện tập',
                mistake_review: 'Sửa câu sai',
                matching: 'Ghép cặp'
            }[q.phase] || 'Luyện tập'
        );

        text(
            'lu-instruction',
            q.content.instruction || 'Trả lời câu hỏi'
        );

        text('lu-prompt', q.content.prompt || '');

        const version = generation;
        const host = $('lu-controls');

        host.replaceChildren();
        host.className = '';

        chosenTokens = [];
        mediaFault = false;

        const audio = $('lu-audio');
        const src = media(q.content.audio);

        audio.hidden = !src;

        if (src) {
            audio.src = src;
            audio.load();
        } else {
            audio.removeAttribute('src');
        }

        $('lu-form').hidden = false;
        text('lu-submit', 'Kiểm tra');

        if (q.content.options) {
            host.className = 'options';

            q.content.options.forEach((option, i) => {
                const label = document.createElement('label');
                label.className = 'option';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'answer';
                radio.value = option.id;

                label.append(radio);

                if (q.type === 'choose_image') {
                    label.classList.add('picture');

                    const image = document.createElement('img');
                    image.alt = 'Hình ' + (i + 1);

                    image.addEventListener('error', () => {
                        brokenMedia(version);
                    });

                    const url = media(option.image);

                    if (url) {
                        image.src = url;
                    } else {
                        brokenMedia(version);
                    }

                    label.append(image);
                } else {
                    const span = document.createElement('span');
                    span.textContent = nfc(option.text);
                    label.append(span);
                }

                host.append(label);
            });
        } else if (q.type === 'token_order') {
            const line = document.createElement('div');
            line.className = 'token-line';

            const bank = document.createElement('div');
            bank.className = 'token-bank';

            q.content.tokens.forEach(token => {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'btn mini';
                button.textContent = nfc(token.text);

                button.addEventListener('click', () => {
                    chosenTokens.push(token.id);
                    button.disabled = true;

                    line.textContent = nfc(
                        chosenTokens.map(id => (
                            q.content.tokens.find(
                                item => item.id === id
                            ).text
                        )).join(' ')
                    );

                    sync();
                });

                bank.append(button);
            });

            const reset = document.createElement('button');
            reset.type = 'button';
            reset.className = 'btn mini';
            reset.textContent = 'Xếp lại';
            reset.addEventListener('click', render);

            bank.append(reset);
            host.append(line, bank);
        } else if (q.type === 'match_pairs') {
            q.content.left.forEach(left => {
                const label = document.createElement('label');
                label.className = 'pair';

                const span = document.createElement('span');
                span.textContent = nfc(left.text);

                const select = document.createElement('select');
                select.dataset.leftId = left.id;

                select.add(new Option('Chọn từ', ''));

                q.content.right.forEach(right => {
                    select.add(
                        new Option(nfc(right.text), right.id)
                    );
                });

                label.append(span, select);
                host.append(label);
            });
        } else {
            const input = document.createElement('input');

            input.type = 'text';
            input.className = 'answer-input';
            input.maxLength = 1000;
            input.autocomplete = 'off';
            input.setAttribute('aria-label', 'Câu trả lời');

            host.append(input);
        }

        sync();
    }

    $('lu-controls').addEventListener('input', sync);
    $('lu-controls').addEventListener('change', sync);

    $('lu-audio').addEventListener('error', () => {
        if (!$('lu-audio').hidden) {
            brokenMedia(generation);
        }
    });

    $('lu-media-retry').addEventListener('click', () => {
        if (!busy && !pending) {
            render();
        }
    });

    async function start(restart = false) {
        if (busy || initializing) return;

        if (state && !restart) {
            render();
            return;
        }

        busy = true;
        $('lu-start').disabled = true;
        $('lu-result-next').disabled = true;

        try {
            state = boot.guest
                ? await api('guest/start', { restart })
                : await api(
                    'lessons/' + boot.lessonId + '/sessions',
                    {}
                );

            pending = null;
            render();
        } catch (error) {
            note(error.message, true);
        } finally {
            busy = false;

            $('lu-start').disabled =
                !boot.ready && !boot.sessionId && !state;

            $('lu-result-next').disabled = false;
            sync();
        }
    }

    $('lu-start').addEventListener('click', () => start());
    $('lu-show-words').addEventListener('click', showWords);

    $('lu-result-next').addEventListener('click', () => {
        if (state?.status === 'completed') {
            if (boot.guest) {
                gate();
            } else {
                location.assign(
                    boot.base + '/learning/launch?mode=words'
                );
            }
        } else {
            start(true);
        }
    });

    $('lu-next-question').addEventListener('click', render);

    $('lu-form').addEventListener('submit', async event => {
        event.preventDefault();

        if (
            busy
            || !state?.question
            || (!pending && (!complete() || mediaFault))
        ) {
            return;
        }

        busy = true;
        $('lu-submit').disabled = true;

        pending ??= {
            question_id: state.question.id,
            request_key: [
                ...crypto.getRandomValues(new Uint8Array(16))
            ].map(number => (
                number.toString(16).padStart(2, '0')
            )).join(''),
            answer: inputAnswer(),
            ...(boot.guest ? { session_token: state.id } : {})
        };

        $('lu-controls')
            .querySelectorAll('input,select,button')
            .forEach(control => {
                control.disabled = true;
            });

        try {
            const result = await api(
                boot.guest
                    ? 'guest/answers'
                    : 'sessions/' + state.id + '/answers',
                pending
            );

            state = result.session;
            pending = null;
            summary();

            if (state.status !== 'in_progress') {
                render();
            } else {
                $('lu-form').hidden = true;
                $('lu-next-question').hidden = false;

                note(
                    result.is_correct
                        ? 'Chính xác!'
                        : 'Chưa đúng. Tiếp tục nhé.'
                );
            }
        } catch (error) {
            if (error.status) {
                pending = null;

                if ([
                    'GUEST_EXPIRED',
                    'GUEST_SESSION_CHANGED'
                ].includes(error.code)) {
                    location.reload();
                    return;
                }

                try {
                    const fresh = await api(
                        boot.guest
                            ? 'guest/session'
                            : 'sessions/' + state.id
                    );

                    if (fresh) {
                        state = fresh;
                        render();
                    }
                } catch {
                    location.reload();
                    return;
                }
            } else {
                text('lu-submit', 'Gửi lại');
            }

            note(error.message, true);
        } finally {
            busy = false;
            sync();
        }
    });

    async function init() {
        showWords();

        try {
            if (boot.guest) {
                state = await api('guest/session');
            } else if (boot.sessionId) {
                state = await api('sessions/' + boot.sessionId);
            }

            initializing = false;

            if (focus === 'words') {
                showWords();
            } else if (state) {
                render();
            } else if (focus === 'practice' && boot.ready) {
                await start();
            }
        } catch (error) {
            note(error.message, true);
        } finally {
            initializing = false;

            $('lu-start').disabled =
                !boot.ready && !boot.sessionId && !state;
        }
    }

    init();
})();