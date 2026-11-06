// Doulingo learning UI — version 14
(() => {
    'use strict';

    function bootPage() {
        if (window.__doulingoLearningUiLoaded === '14') return;
        window.__doulingoLearningUiLoaded = '14';

        const $ = id => document.getElementById(id);
        const nfc = value => String(value ?? '').normalize('NFC');

        const text = (id, value) => {
            if ($(id)) {
                $(id).textContent = nfc(value);
            }
        };

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
                        (event.clientY - rect.top) / rect.height - 0.5
                    ) * 12;

                    ry = (
                        (event.clientX - rect.left) / rect.width - 0.5
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

        if (!$('lu-data')) return;

        let boot;

        try {
            boot = JSON.parse($('lu-data').textContent);
        } catch {
            text(
                'lu-feedback',
                'Chưa đọc được dữ liệu bài học. Bạn tải lại trang nhé.'
            );
            return;
        }

        function gate() {
            const dialog = $('lu-gate');

            if (dialog && !dialog.open) {
                dialog.showModal();
            }
        }

        document.querySelectorAll('[data-later]').forEach(button => {
            button.addEventListener('click', () => {
                $('lu-gate')?.close();
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

                if ($('lu-toast')) {
                    $('lu-toast').hidden = false;
                }

                clearTimeout(toastTimer);

                toastTimer = setTimeout(() => {
                    if ($('lu-toast')) {
                        $('lu-toast').hidden = true;
                    }
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

                if ($('lu-claim-retry')) {
                    $('lu-claim-retry').hidden = true;
                }

                text('lu-claim-note', 'Đang lưu kết quả…');

                try {
                    const result = await api('guest/claim', {});

                    if (!result.claimed) {
                        text(
                            'lu-claim-note',
                            'Phiên học thử không còn sẵn. '
                            + 'Bạn có thể học tiếp từ lộ trình.'
                        );
                        return;
                    }

                    const requested = new URLSearchParams(
                        location.search
                    ).get('focus');

                    const entry = requested === 'words'
                        ? 'words'
                        : 'practice';

                    location.replace(
                        boot.base + (
                            result.next_lesson_id
                                ? '/learning/lesson/'
                                    + result.next_lesson_id
                                    + '?focus=' + entry
                                : '/learning/launch?mode=' + entry
                        )
                    );
                } catch (error) {
                    text('lu-claim-note', error.message);

                    if ($('lu-claim-retry')) {
                        $('lu-claim-retry').hidden = false;
                    }
                } finally {
                    saving = false;
                }
            }

            $('lu-claim-retry')?.addEventListener('click', claim);
            claim();

            return;
        }

        if (
            !['lesson', 'words', 'practice'].includes(boot.mode)
            && !($('lu-words') && $('lu-question'))
        ) {
            return;
        }

        const requested = new URLSearchParams(
            location.search
        ).get('focus');

        const entryMode = ['words', 'practice'].includes(requested)
            ? requested
            : (
                boot.initialFocus === 'practice'
                    ? 'practice'
                    : 'words'
            );

        const required = [
            'lu-words',
            'lu-question',
            'lu-result',
            'lu-form',
            'lu-controls',
            'lu-submit',
            'lu-image',
            'lu-audio',
            'lu-start',
            'lu-next-question',
            'lu-result-next',
            'lu-show-words',
            'lu-progress',
            'lu-feedback',
            'lu-media-retry',
            'lu-prev',
            'lu-next-word',
            'lu-sound',
            'lu-example'
        ];

        if (required.some(id => !$(id))) {
            if (entryMode === 'practice') {
                if ($('lu-words')) {
                    $('lu-words').hidden = true;
                    $('lu-words').style.display = 'none';
                }

                if ($('lu-result')) {
                    $('lu-result').hidden = true;
                    $('lu-result').style.display = 'none';
                }

                if ($('lu-question')) {
                    $('lu-question').hidden = false;
                    $('lu-question').style.display = '';
                }

                if ($('lu-form')) {
                    $('lu-form').hidden = true;
                }

                text(
                    'lu-instruction',
                    'Giao diện bài tập chưa tải đầy đủ.'
                );
            }

            text(
                'lu-feedback',
                'Giao diện bài học chưa tải đầy đủ. '
                + 'Bạn tải lại trang nhé.'
            );

            return;
        }

        const words = Array.isArray(boot.words)
            ? boot.words
            : [];

        let wordIndex = 0;
        let state = null;
        let busy = false;
        let initializing = true;

        let pending = null;
        let chosenTokens = [];
        let mediaFault = false;
        let generation = 0;

        let sound = null;
        let speechVersion = 0;
        let currentUtterance = null;
        let englishVoices = [];

        const synth = 'speechSynthesis' in window
            ? window.speechSynthesis
            : null;

        function note(message = '', error = false) {
            text('lu-feedback', message);

            $('lu-feedback').classList.toggle(
                'error',
                error
            );
        }

        function panel(id) {
            ['lu-words', 'lu-question', 'lu-result'].forEach(name => {
                const element = $(name);

                element.hidden = name !== id;
                element.style.display = name === id
                    ? ''
                    : 'none';
            });
        }

        function media(value) {
            if (
                typeof value !== 'string'
                || /[\x00-\x20<>"'\\]/.test(value)
            ) {
                return null;
            }

            if (value.startsWith('/') && !value.startsWith('//')) {
                const base = String(boot.base ?? '')
                    .replace(/\/+$/, '');

                let path = base && !value.startsWith(base + '/')
                    ? base + value
                    : value;

                const legacy = base + '/media/';

                if (path.startsWith(legacy)) {
                    path = base
                        + '/assets/media/'
                        + path.slice(legacy.length);
                }

                return path;
            }

            try {
                return new URL(value).protocol === 'https:'
                    ? value
                    : null;
            } catch {
                return null;
            }
        }

        function loadVoices() {
            englishVoices = synth
                ? synth.getVoices().filter(
                    voice => /^en(?:[-_]|$)/i.test(voice.lang)
                )
                : [];
        }

        loadVoices();

        synth?.addEventListener(
            'voiceschanged',
            loadVoices
        );

        function stopSound() {
            speechVersion++;

            if (sound) {
                sound.pause();
                sound.removeAttribute('src');
                sound.load();
                sound = null;
            }

            $('lu-audio').pause();
            synth?.cancel();

            currentUtterance = null;
        }

        function playbackCurrent(version) {
            return version === speechVersion
                && !$('lu-words').hidden
                && document.visibilityState !== 'hidden';
        }

        function speakEnglish(word, version) {
            if (!playbackCurrent(version)) return;

            const english = nfc(word?.english).trim();

            if (!english) return;

            if (
                !synth
                || !('SpeechSynthesisUtterance' in window)
            ) {
                note(
                    'Từ này cần bổ sung file âm thanh.',
                    true
                );
                return;
            }

            loadVoices();

            const voice = englishVoices.find(
                item => /^en[-_]US$/i.test(item.lang)
            ) || englishVoices.find(
                item => /^en[-_]GB$/i.test(item.lang)
            ) || englishVoices[0];

            if (!voice && synth.getVoices().length > 0) {
                note(
                    'Thiết bị chưa có giọng tiếng Anh cho từ này.',
                    true
                );
                return;
            }

            const utterance = new SpeechSynthesisUtterance(
                english
            );

            currentUtterance = utterance;
            utterance.lang = voice?.lang || 'en-US';

            if (voice) {
                utterance.voice = voice;
            }

            utterance.rate = 0.9;
            utterance.pitch = 1;
            utterance.volume = 1;

            utterance.onerror = event => {
                if (
                    !playbackCurrent(version)
                    || ['canceled', 'interrupted'].includes(event.error)
                ) {
                    return;
                }

                note(
                    event.error === 'not-allowed'
                        ? 'Bấm “Nghe lại” để bật âm thanh.'
                        : 'Chưa đọc được từ. Bạn bấm “Nghe lại” nhé.',
                    event.error !== 'not-allowed'
                );
            };

            try {
                synth.resume();
                synth.speak(utterance);
            } catch {
                if (playbackCurrent(version)) {
                    note('Bấm “Nghe lại” để bật âm thanh.');
                }
            }
        }

        function playWord(word) {
            if (!word || !nfc(word.english).trim()) return;

            stopSound();

            const version = speechVersion;
            const src = media(word.audio);

            text('lu-sound', 'Nghe lại ♪');

            if (!src) {
                speakEnglish(word, version);
                return;
            }

            let fallbackStarted = false;

            const fallback = () => {
                if (
                    fallbackStarted
                    || !playbackCurrent(version)
                ) {
                    return;
                }

                fallbackStarted = true;
                sound?.pause();

                speakEnglish(word, version);
            };

            const audio = new Audio(src);

            sound = audio;
            audio.preload = 'auto';

            audio.addEventListener(
                'error',
                fallback,
                {once: true}
            );

            audio.play().catch(error => {
                if (!playbackCurrent(version)) return;

                if (error.name === 'NotAllowedError') {
                    fallbackStarted = true;

                    note(
                        'Bấm “Nghe lại” để bật âm thanh.'
                    );
                } else if (error.name !== 'AbortError') {
                    fallback();
                }
            });
        }

        const image = $('lu-image');
        const art = image.closest('.word-art');

        if (art?.querySelector('.scene')) {
            art.querySelector('.scene').hidden = true;
        }

        const imagePlaceholder = document.createElement('p');

        imagePlaceholder.className = 'word-media-empty';
        imagePlaceholder.textContent = 'Chưa có hình minh họa';

        art?.append(imagePlaceholder);

        image.addEventListener('error', () => {
            image.hidden = true;
            imagePlaceholder.hidden = false;
        });

        function showWords(autoRead = true) {
            stopSound();
            note();
            panel('lu-words');

            const word = words[wordIndex];

            text('lu-word', word?.english || 'Chưa có từ vựng');
            text('lu-ipa', word?.ipa || '');
            text('lu-meaning', word?.vietnamese || '');
            text('lu-example-en', word?.example || '');
            text('lu-example-vi', word?.example_translation || '');

            $('lu-example').hidden =
                !word?.example && !word?.example_translation;

            $('lu-prev').disabled =
                !word || wordIndex === 0;

            $('lu-next-word').disabled =
                !word || wordIndex >= words.length - 1;

            $('lu-sound').disabled = !word;

            text('lu-sound', 'Nghe lại ♪');

            const src = media(word?.image);

            image.alt = nfc(word?.english);
            image.hidden = !src;
            imagePlaceholder.hidden = Boolean(src);

            if (src) {
                image.src = src;
            } else {
                image.removeAttribute('src');
            }

            text(
                'lu-count',
                word
                    ? 'Từ ' + (wordIndex + 1) + '/' + words.length
                    : ''
            );

            text('lu-hearts', '');

            $('lu-progress').max = Math.max(
                1,
                words.length
            );

            $('lu-progress').value =
                word ? wordIndex + 1 : 0;

            $('lu-progress').dataset.phase = 'words';

            $('lu-progress').setAttribute(
                'aria-label',
                'Tiến độ xem từ'
            );

            $('lu-progress').removeAttribute(
                'aria-valuetext'
            );

            $('lu-start').disabled =
                initializing
                || busy
                || (!word && !state);

            text(
                'lu-start',
                state ? 'Tiếp tục →' : 'Luyện tập →'
            );

            if (autoRead && word) {
                playWord(word);
            }
        }

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
            note();
            playWord(words[wordIndex]);
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                stopSound();
            }
        });

        window.addEventListener('pagehide', stopSound);

        function countLabel(session) {
            if (session.status === 'completed') {
                return 'Hoàn thành bài học';
            }

            if (session.status === 'failed') {
                return 'Lượt học đã kết thúc';
            }

            const base = session.answered_main
                + '/' + session.total_questions
                + ' câu chính';

            if (session.question?.phase === 'mistake_review') {
                return base + ' · Đang sửa câu sai';
            }

            if (session.question?.phase === 'matching') {
                return base + ' · Hoàn tất phần ghép cặp';
            }

            return base;
        }

        function summary() {
            text('lu-count', countLabel(state));

            text(
                'lu-hearts',
                state.status === 'in_progress'
                    ? '♥ ' + state.current_hp + '/' + state.max_hp
                    : ''
            );

            $('lu-progress').max = Math.max(
                1,
                state.total_questions
            );

            $('lu-progress').value = state.answered_main;

            $('lu-progress').dataset.phase =
                state.question?.phase || state.status;

            $('lu-progress').setAttribute(
                'aria-label',
                'Tiến độ câu chính'
            );

            $('lu-progress').setAttribute(
                'aria-valuetext',
                countLabel(state)
            );
        }

        function checkLesson(session) {
            if (!session) return null;

            const actual = Number(session.lesson_id);
            const expected = Number(boot.lessonId);

            if (
                !Number.isInteger(expected)
                || expected < 1
            ) {
                throw new Error(
                    'Trang chưa nhận được ID bài học hợp lệ.'
                );
            }

            if (
                !Number.isInteger(actual)
                || actual < 1
            ) {
                throw new Error(
                    'Máy chủ chưa trả ID bài học hợp lệ.'
                );
            }

            if (actual !== expected) {
                throw new Error(
                    'Phiên học đang thuộc bài khác. '
                    + 'Bạn tải lại trang nhé.'
                );
            }

            return session;
        }

        function inputAnswer() {
            const question = state.question;
            const host = $('lu-controls');

            if (question.content.options) {
                return {
                    option_id:
                        host.querySelector('input:checked')?.value
                        || ''
                };
            }

            if (question.type === 'token_order') {
                return {
                    token_ids: chosenTokens.slice()
                };
            }

            if (question.type === 'match_pairs') {
                return {
                    pairs: [
                        ...host.querySelectorAll('select')
                    ].map(select => ({
                        left_id: select.dataset.leftId,
                        right_id: select.value
                    }))
                };
            }

            return {
                text: host.querySelector('input')?.value || ''
            };
        }

        function complete() {
            if (!state?.question) return false;

            const answer = inputAnswer();
            const question = state.question;

            if (question.content.options) {
                return Boolean(answer.option_id);
            }

            if (question.type === 'token_order') {
                return answer.token_ids.length
                    === question.content.tokens.length;
            }

            if (question.type === 'match_pairs') {
                return answer.pairs.every(
                    pair => pair.right_id
                ) && new Set(
                    answer.pairs.map(pair => pair.right_id)
                ).size === answer.pairs.length;
            }

            return answer.text.trim() !== '';
        }

        function sync() {
            $('lu-submit').disabled =
                busy
                || mediaFault
                || (!pending && !complete());
        }

        function brokenMedia(version) {
            if (version !== generation) return;

            mediaFault = true;
            $('lu-media-retry').hidden = false;

            note(
                'Chưa tải được tài nguyên câu hỏi.',
                true
            );

            sync();
        }

        function render() {
            if (!state) return;

            stopSound();
            note();
            summary();

            generation++;

            $('lu-next-question').hidden = true;
            $('lu-media-retry').hidden = true;

            if ($('lu-load-retry')) {
                $('lu-load-retry').hidden = true;
            }

            text('lu-next-question', 'Tiếp tục →');

            if (state.status !== 'in_progress') {
                panel('lu-result');

                const passed =
                    state.status === 'completed';

                text(
                    'lu-result-title',
                    passed
                        ? 'Bạn làm tốt lắm!'
                        : 'Thử lại một chút nhé.'
                );

                text(
                    'lu-score',
                    state.correct_count
                        + '/' + state.total_questions
                        + ' câu đúng'
                        + ' · '
                        + Math.max(
                            0,
                            state.total_questions
                                - state.correct_count
                        )
                        + ' câu sai'
                        + (
                            !boot.guest && passed
                                ? ' · +'
                                    + (state.xp_earned || 0)
                                    + ' XP'
                                : ''
                        )
                );

                text(
                    'lu-result-next',
                    passed ? 'Tiếp tục →' : 'Thử lại'
                );

                return;
            }

            panel('lu-question');

            const question = state.question;

            if (!question) {
                practiceStatus(
                    'Chưa nhận được câu hỏi.',
                    true
                );
                return;
            }

            text(
                'lu-phase',
                {
                    main: 'Luyện tập',
                    mistake_review: 'Sửa câu sai · Không trừ tim',
                    matching: 'Ghép cặp'
                }[question.phase] || 'Luyện tập'
            );

            text(
                'lu-instruction',
                question.content.instruction
                    || 'Trả lời câu hỏi'
            );

            text(
                'lu-prompt',
                question.content.prompt || ''
            );

            const version = generation;
            const host = $('lu-controls');

            host.replaceChildren();
            host.className = '';

            chosenTokens = [];
            mediaFault = false;

            const audio = $('lu-audio');
            const src = media(question.content.audio);

            audio.hidden = !src;

            audio.onerror = () => {
                if (!audio.hidden) {
                    brokenMedia(version);
                }
            };

            if (src) {
                audio.src = src;
                audio.load();
                audio.play().catch(() => {});
            } else {
                audio.removeAttribute('src');
            }

            $('lu-form').hidden = false;
            text('lu-submit', 'Kiểm tra');

            if (question.content.options) {
                host.className = 'options';

                question.content.options.forEach((option, index) => {
                    const label = document.createElement('label');
                    label.className = 'option';

                    const radio = document.createElement('input');

                    radio.type = 'radio';
                    radio.name = 'answer';
                    radio.value = option.id;

                    label.append(radio);

                    if (question.type === 'choose_image') {
                        label.classList.add('picture');

                        const optionImage =
                            document.createElement('img');

                        optionImage.alt = 'Hình ' + (index + 1);

                        optionImage.addEventListener(
                            'error',
                            () => brokenMedia(version)
                        );

                        const url = media(option.image);

                        if (url) {
                            optionImage.src = url;
                        } else {
                            brokenMedia(version);
                        }

                        label.append(optionImage);
                    } else {
                        const span =
                            document.createElement('span');

                        span.textContent = nfc(option.text);
                        label.append(span);
                    }

                    host.append(label);
                });
            } else if (question.type === 'token_order') {
                const line = document.createElement('div');
                line.className = 'token-line';

                const bank = document.createElement('div');
                bank.className = 'token-bank';

                question.content.tokens.forEach(token => {
                    const button =
                        document.createElement('button');

                    button.type = 'button';
                    button.className = 'btn mini';
                    button.textContent = nfc(token.text);

                    button.addEventListener('click', () => {
                        chosenTokens.push(token.id);
                        button.disabled = true;

                        line.textContent = nfc(
                            chosenTokens.map(id =>
                                question.content.tokens.find(
                                    item => item.id === id
                                ).text
                            ).join(' ')
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
            } else if (question.type === 'match_pairs') {
                question.content.left.forEach(left => {
                    const label =
                        document.createElement('label');

                    label.className = 'pair';

                    const span =
                        document.createElement('span');

                    span.textContent = nfc(left.text);

                    const select =
                        document.createElement('select');

                    select.dataset.leftId = left.id;
                    select.add(new Option('Chọn từ', ''));

                    question.content.right.forEach(right => {
                        select.add(
                            new Option(
                                nfc(right.text),
                                right.id
                            )
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

                input.setAttribute(
                    'aria-label',
                    'Câu trả lời'
                );

                host.append(input);
            }

            sync();
        }

        function practiceStatus(message, retry = false) {
            stopSound();
            panel('lu-question');

            $('lu-form').hidden = true;
            $('lu-next-question').hidden = true;
            $('lu-media-retry').hidden = true;
            $('lu-audio').hidden = true;

            text('lu-phase', 'Luyện tập');
            text('lu-instruction', message);
            text('lu-prompt', '');

            let button = $('lu-load-retry');

            if (!button) {
                button = document.createElement('button');

                button.id = 'lu-load-retry';
                button.type = 'button';
                button.className = 'btn primary';
                button.textContent = 'Tải lại bài tập';

                button.addEventListener('click', () => {
                    location.reload();
                });

                $('lu-question').append(button);
            }

            button.hidden = !retry;
        }

        async function start(restart = false) {
            if (busy || initializing) return;

            if (state && !restart) {
                render();
                return;
            }

            stopSound();

            busy = true;
            $('lu-start').disabled = true;
            $('lu-result-next').disabled = true;

            try {
                state = checkLesson(
                    boot.guest
                        ? await api(
                            'guest/start',
                            {restart}
                        )
                        : await api(
                            'lessons/'
                                + boot.lessonId
                                + '/sessions',
                            {}
                        )
                );

                if (!state) {
                    throw new Error(
                        'Máy chủ chưa tạo được phiên học.'
                    );
                }

                pending = null;
                render();
            } catch (error) {
                state = null;

                practiceStatus(
                    'Chưa mở được bài tập.',
                    true
                );

                note(error.message, true);
            } finally {
                busy = false;

                $('lu-start').disabled =
                    !words.length && !state;

                $('lu-result-next').disabled = false;

                sync();
            }
        }

        $('lu-start').addEventListener('click', () => {
            start();
        });

        $('lu-show-words').addEventListener('click', () => {
            showWords();
        });

        $('lu-controls').addEventListener('input', sync);
        $('lu-controls').addEventListener('change', sync);

        $('lu-media-retry').addEventListener('click', () => {
            if (!busy && !pending) {
                render();
            }
        });

        $('lu-next-question').addEventListener(
            'click',
            render
        );

        $('lu-result-next').addEventListener('click', () => {
            if (state?.status === 'completed') {
                if (boot.guest) {
                    gate();
                } else {
                    location.assign(
                        boot.base
                        + '/learning/launch?mode=practice'
                    );
                }
            } else {
                start(true);
            }
        });

        $('lu-form').addEventListener('submit', async event => {
            event.preventDefault();

            if (
                busy
                || !state?.question
                || (
                    !pending
                    && (!complete() || mediaFault)
                )
            ) {
                return;
            }

            busy = true;
            $('lu-submit').disabled = true;

            pending ??= {
                question_id: state.question.id,

                request_key: [
                    ...crypto.getRandomValues(
                        new Uint8Array(16)
                    )
                ].map(
                    value => value
                        .toString(16)
                        .padStart(2, '0')
                ).join(''),

                answer: inputAnswer(),

                ...(boot.guest
                    ? {session_token: state.id}
                    : {})
            };

            $('lu-controls')
                .querySelectorAll('input,select,button')
                .forEach(control => {
                    control.disabled = true;
                });

            try {
                const answeredId = pending.question_id;

                const result = await api(
                    boot.guest
                        ? 'guest/answers'
                        : 'sessions/'
                            + state.id
                            + '/answers',
                    pending
                );

                state = checkLesson(result.session);
                pending = null;

                summary();

                if (state.status !== 'in_progress') {
                    render();
                } else {
                    $('lu-audio').pause();

                    $('lu-form').hidden = true;
                    $('lu-next-question').hidden = false;

                    const repeat =
                        state.question?.id === answeredId;

                    text(
                        'lu-next-question',
                        repeat
                            ? 'Thử lại câu này'
                            : 'Tiếp tục →'
                    );

                    note(
                        result.is_correct
                            ? 'Chính xác!'
                            : (
                                repeat
                                    ? 'Chưa đúng. Bạn thử lại câu này nhé.'
                                    : 'Chưa đúng. Tiếp tục nhé.'
                            )
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
                        const fresh = checkLesson(
                            await api(
                                boot.guest
                                    ? 'guest/session'
                                    : 'sessions/' + state.id
                            )
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

        if (!boot.guest && boot.heartRecovery) {
            let heartPollAt = 0;
            const pollHearts = async () => {
                if (busy || Date.now() - heartPollAt < 25000) return;
                heartPollAt = Date.now();
                try {
                    const fresh = await api('hearts');
                    if (state) {
                        state.current_hp = fresh.current_hp;
                        state.next_hp_at = fresh.next_hp_at;
                        summary();
                    }
                } catch { /* Normal question requests still refresh hearts on the server. */ }
            };
            setInterval(pollHearts, 30000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) pollHearts(); });
        }

        async function init() {
            if (entryMode === 'practice') {
                practiceStatus('Đang mở bài tập…');
            } else {
                showWords(false);
            }

            try {
                if (boot.guest) {
                    state = checkLesson(
                        await api('guest/session')
                    );
                } else if (boot.sessionId) {
                    state = checkLesson(
                        await api(
                            'sessions/' + boot.sessionId
                        )
                    );
                }

                initializing = false;

                if (entryMode === 'words') {
                    showWords(true);
                    return;
                }

                if (state) {
                    render();
                } else {
                    await start();
                }
            } catch (error) {
                if (entryMode === 'practice') {
                    practiceStatus(
                        'Chưa mở được bài tập.',
                        true
                    );
                }

                note(error.message, true);
            } finally {
                initializing = false;

                $('lu-start').disabled =
                    !words.length && !state;
            }
        }

        init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            bootPage,
            {once: true}
        );
    } else {
        bootPage();
    }
})();