'use strict';

// Shared word-card presentation for Learning and Reviews; no grading or scheduling here.
(() => {
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
    const media = value => {
        if (typeof value !== 'string' || /[\s<>"'\\]/.test(value)) return '';
        if (value.startsWith('/') && !value.startsWith('//')) {
            const path = window.TuanReviewConfig?.baseUrl ? new URL(window.TuanReviewConfig.baseUrl, location.origin).pathname.replace(/\/$/, '') : '';
            return path && !value.startsWith(path + '/') ? path + value : value;
        }
        try {
            const url = new URL(value);
            return url.protocol === 'https:' && !url.username && !url.password ? url.href : '';
        } catch { return ''; }
    };
    const render = ({ word, direction = 'en_vi', revealed = true, mode = 'review' }) => {
        const image = media(word.image);
        const audio = media(word.audio);
        if (mode === 'learning') {
            return `<img id="fc-image" class="word-image" alt="Hình minh họa" ${image ? `src="${escape(image)}"` : 'hidden'}>
                <h2 id="fc-word">${escape(word.english)}</h2>
                <p id="fc-pronunciation">${escape(word.ipa)}</p>
                <h3 id="fc-translation">${escape(word.vietnamese)}</h3>
                <p id="fc-example" ${word.example ? '' : 'hidden'}><strong>Ví dụ: </strong><span id="fc-ex-en">${escape(word.example)}</span></p>
                <p id="fc-ex-translation">${escape(word.example_translation)}</p>`;
        }
        const front = direction === 'listening' ? 'Nghe rồi tự nhớ từ'
            : direction === 'en_vi' ? word.english : word.vietnamese;
        const back = direction === 'en_vi' ? word.vietnamese
            : direction === 'listening' ? word.english + ' — ' + word.vietnamese : word.english;
        return `<div class="flashcard">
            ${image && (direction === 'en_vi' || revealed) ? `<img src="${escape(image)}" alt="Hình minh họa">` : ''}
            <h3>${escape(front)}</h3>
            ${(direction === 'en_vi' || direction === 'listening' || revealed) ? `<button type="button" class="audio-button" data-audio="${escape(audio)}" data-speak-word="${escape(word.english)}">♫ Nghe phát âm</button>` : ''}
            ${revealed ? `<div class="back">${escape(back)}</div><p>${escape(word.ipa)}<br>${escape(word.example)}<br>${escape(word.example_translation)}</p>` : ''}
        </div>`;
    };
    window.DoulingoWordCard = { render, media };
    window.DoulingoReviewCard = options => render({ ...options, mode: 'review' });
})();
(() => {
    const bootElement = document.getElementById('learning-data');
    if (!bootElement) return; // Reviews only needs the shared presentation above.
    const boot = JSON.parse(bootElement.textContent);
    const $ = id => document.getElementById(id);
    const words = boot.words;
    let wordIndex = 0;
    let session = null;
    let busy = false;
    let pending = null;
    let selectedTokens = [];

    const media = window.DoulingoWordCard.media;
    function showWord() {
        const word = words[wordIndex];
        if (!word) {
            $('btn-next-word').hidden = true;
            $('btn-listen').hidden = true;
            return;
        }
        $('shared-word-card').innerHTML = window.DoulingoWordCard.render({ word, mode: 'learning' });
        $('btn-next-word').hidden = wordIndex === words.length - 1;
        $('progress').textContent = `Giới thiệu từ ${wordIndex + 1}/${words.length}`;
        $('btn-listen').disabled = !word.audio && !('speechSynthesis' in window);
    }
    $('btn-next-word').addEventListener('click', () => { wordIndex++; showWord(); });
    $('btn-listen').addEventListener('click', () => {
        const word = words[wordIndex];
        const src = media(word?.audio);
        if (src) {
            new Audio(src).play().catch(() => { $('feedback').textContent = 'Chưa thể phát âm thanh.'; });
        } else if (word && 'speechSynthesis' in window) {
            const voice = new SpeechSynthesisUtterance(word.english);
            voice.lang = 'en-US'; window.speechSynthesis.cancel(); window.speechSynthesis.speak(voice);
        }
    });

    async function api(path, body) {
        const response = await fetch(boot.basePath + '/api/v1/learning/' + path, {
            method: body === undefined ? 'GET' : 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': boot.csrfToken },
            body: body === undefined ? undefined : JSON.stringify(body)
        });
        const payload = await response.json();
        if (!response.ok) {
            const error = new Error(payload.error?.message || 'Không thể xử lý yêu cầu.');
            error.status = response.status;
            throw error;
        }
        return payload.data;
    }
    function updateSummary() {
        $('hearts-display').textContent = '❤️'.repeat(session.current_hp) + '🖤'.repeat(session.max_hp - session.current_hp);
        $('hearts-display').setAttribute('aria-label', `${session.current_hp}/${session.max_hp} tim`);
        $('progress').textContent = `Đã trả lời ${session.answered_main}/${session.total_questions} câu chính · ${session.stats.xp} XP · Chuỗi ${session.stats.streak} ngày`;
    }
    function renderSession() {
        $('flashcards').hidden = true;
        $('feedback').textContent = '';
        updateSummary();
        const done = session.status !== 'in_progress';
        $('exercise').hidden = done;
        $('result').hidden = !done;
        if (done) {
            $('result-title').textContent = session.status === 'completed' ? 'Hoàn thành bài học!' : 'Lượt học chưa đạt';
            $('result-score').textContent = `${session.correct_count}/${session.total_questions} câu chính đúng · +${session.xp_earned} XP`;
            if (session.status === 'completed' && session.review_initialization) {
                const report = session.review_initialization;
                $('result-score').textContent += report.legacy_questions
                    ? ' · Lượt cũ thiếu dữ liệu từ; học lượt mới để ghi lịch ôn.'
                    : ' · Đã lưu lịch ôn cá nhân, từ mới hẹn sau một ngày.';
            }
            return;
        }
        const question = session.question;
        if (!question) return;
        $('phase-label').textContent = { main: 'Bài tập', mistake_review: 'Sửa câu sai — không trừ tim', matching: 'Ghép cặp để hoàn tất bài' }[question.phase];
        $('instruction').textContent = question.content.instruction;
        $('prompt').textContent = question.content.prompt;
        const audio = media(question.content.audio);
        $('question-audio').hidden = !audio;
        $('question-audio').pause();
        if (audio) $('question-audio').src = audio; else $('question-audio').removeAttribute('src');
        const controls = $('answer-controls');
        controls.replaceChildren();
        selectedTokens = [];
        $('answer-form').hidden = false;
        $('btn-next-question').hidden = true;
        $('btn-submit').disabled = false;
        $('btn-submit').textContent = 'Kiểm tra';
        if (question.content.options) {
            controls.className = 'answer-options';
            question.content.options.forEach((option, index) => {
                const label = document.createElement('label'); label.className = 'answer-option';
                const input = document.createElement('input');
                input.type = 'radio'; input.name = 'option'; input.value = option.id; input.required = true;
                label.append(input);
                if (question.type === 'choose_image') {
                    const image = document.createElement('img'); image.src = media(option.image); image.alt = `Hình ${index + 1}`;
                    label.append(image);
                } else {
                    const text = document.createElement('span'); text.textContent = option.text; label.append(text);
                }
                controls.append(label);
            });
        } else if (question.type === 'token_order') {
            const sequence = document.createElement('p'); sequence.id = 'token-sequence'; controls.append(sequence);
            question.content.tokens.forEach(token => {
                const button = document.createElement('button'); button.type = 'button'; button.textContent = token.text;
                button.addEventListener('click', () => {
                    selectedTokens.push(token.id); button.disabled = true;
                    sequence.textContent = selectedTokens.map(id => question.content.tokens.find(t => t.id === id).text).join(' ');
                }); controls.append(button);
            });
            const clear = document.createElement('button'); clear.type = 'button'; clear.textContent = 'Xếp lại';
            clear.addEventListener('click', renderSession); controls.append(clear);
        } else if (question.type === 'match_pairs') {
            question.content.left.forEach(left => {
                const label = document.createElement('label'); label.className = 'pair-row';
                const text = document.createElement('span'); text.textContent = left.text;
                const select = document.createElement('select'); select.dataset.leftId = left.id; select.required = true;
                const empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Chọn từ tương ứng'; select.append(empty);
                question.content.right.forEach(right => {
                    const option = document.createElement('option'); option.value = right.id; option.textContent = right.text; select.append(option);
                }); label.append(text, select); controls.append(label);
            });
        } else {
            const input = document.createElement('input'); input.type = 'text'; input.name = 'text'; input.required = true;
            input.maxLength = 1000; input.autocomplete = 'off'; input.setAttribute('aria-label', 'Câu trả lời'); controls.append(input);
        }
    }
    function readAnswer() {
        const type = session.question.type;
        if (session.question.content.options) return { option_id: $('answer-form').elements.option.value };
        if (type === 'token_order') return { token_ids: selectedTokens.slice() };
        if (type === 'match_pairs') return { pairs: Array.from($('answer-controls').querySelectorAll('select')).map(select => ({ left_id: select.dataset.leftId, right_id: select.value })) };
        return { text: $('answer-form').elements.text.value };
    }
    $('btn-start').addEventListener('click', async () => {
        if (busy) return;
        busy = true; $('btn-start').disabled = true;
        try {
            session = boot.sessionId ? await api(`sessions/${boot.sessionId}`) : await api(`lessons/${boot.lessonId}/sessions`, {});
            renderSession();
        } catch (error) { $('feedback').textContent = error.message; }
        finally { busy = false; $('btn-start').disabled = false; }
    });
    $('answer-form').addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        busy = true; $('btn-submit').disabled = true;
        // A lost response reuses the exact body/key. Controls stay frozen until success or a definite rejection.
        pending ??= { question_id: session.question.id, request_key: crypto.randomUUID?.() || Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join(''), answer: readAnswer() };
        const frozen = Array.from($('answer-controls').querySelectorAll('input,button,select'));
        frozen.forEach(control => { control.disabled = true; });
        try {
            const result = await api(`sessions/${session.id}/answers`, pending);
            session = result.session; pending = null; updateSummary();
            $('feedback').textContent = result.is_correct ? 'Chính xác!' : 'Chưa chính xác. Hãy tiếp tục hoặc sửa lại khi đến phần sửa sai.';
            $('answer-form').hidden = true; $('btn-next-question').hidden = false;
        } catch (error) {
            $('feedback').textContent = error.message;
            if (error.status) {
                pending = null;
                try { session = await api(`sessions/${session.id}`); renderSession(); $('feedback').textContent = error.message; }
                catch { frozen.forEach(control => { control.disabled = false; }); }
            } else { $('btn-submit').textContent = 'Gửi lại câu trả lời'; }
            $('btn-submit').disabled = false;
        } finally { busy = false; }
    });
    $('btn-next-question').addEventListener('click', renderSession);
    showWord();
})();
