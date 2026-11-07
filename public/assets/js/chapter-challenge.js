(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const base = document.body.dataset.base;
    let attempt = null, ui = null, busy = false, pending = null, freshResult = false;
    async function api(path, body) {
        const response = await fetch(base + '/api/v1/learning/' + path, {
            method: body === undefined ? 'GET' : 'POST',
            credentials: 'same-origin', cache: 'no-store',
            headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-Token':document.body.dataset.csrf},
            ...(body === undefined ? {} : {body:JSON.stringify(body)})
        });
        const payload = await response.json();
        if (!response.ok || payload.error) {
            const error = new Error(payload.error?.message || 'Chưa thực hiện được yêu cầu.');
            error.status = response.status;
            throw error;
        }
        if (!payload.data) throw new Error('Máy chủ chưa trả dữ liệu hợp lệ. Hãy thử lại.');
        return payload.data;
    }
    function sync() {
        $('cc-submit').disabled = busy || !ui?.complete();
        $('cc-start').disabled = busy;
        $('cc-retry').disabled = busy;
    }
    function render() {
        ui?.destroy(); ui = null;
        $('lu-audio').pause();
        const done = attempt.status !== 'in_progress';
        $('cc-intro').hidden = true;
        $('cc-test').hidden = done;
        $('cc-result').hidden = !done;
        if (done) {
            const passed = attempt.unlocked === true;
            const required = Math.ceil(attempt.total * attempt.pass_percent / 100);
            $('cc-result-title').textContent = passed ? 'Đã mở toàn bộ chương!' : 'Chưa đạt lần này';
            $('cc-score').textContent = `Đúng ${attempt.correct_count}/${attempt.total} câu · Cần ít nhất ${required} câu đúng để đạt ${attempt.pass_percent}%.`;
            $('cc-result-correct').textContent = `${attempt.correct_count}/${attempt.total}`;
            $('cc-result-accuracy').textContent = Math.round(100 * attempt.correct_count / Math.max(1, attempt.total)) + '%';
            $('cc-result-required').textContent = attempt.pass_percent + '%';
            $('cc-result-note').textContent = passed
                ? 'Bạn đã mở tất cả bài học của chương. Tiếp tục học để tích lũy XP và giữ streak nhé!'
                : 'Bạn có thể ôn từ và thử lại. Tiến độ học trước đó vẫn được giữ nguyên.';
            $('cc-retry').hidden = passed;
            window.DLRewards?.outcome($('cc-result'), passed, {key:'chapter:' + attempt.id, fresh:freshResult});
            freshResult = false;
            $('cc-result').scrollIntoView({block:'start'});
            return;
        }
        const q = attempt.question;
        $('cc-progress').max = attempt.total;
        $('cc-progress').value = attempt.answered;
        $('cc-count').textContent = `${q.number}/${attempt.total}`;
        $('lu-instruction').textContent = q.content.instruction || 'Trả lời câu hỏi';
        $('lu-prompt').textContent = q.content.prompt || '';
        const source = q.content.audio;
        if (source) $('lu-audio').src = source.startsWith('/') && !source.startsWith(base + '/') ? base + source : source;
        else $('lu-audio').removeAttribute('src');
        $('lu-answer-feedback').hidden = true;
        $('cc-feedback-actions').hidden = true;
        $('cc-feedback-actions').classList.remove('is-correct', 'is-wrong');
        $('cc-next').hidden = true;
        $('cc-check-actions').hidden = false;
        $('cc-submit').textContent = 'Kiểm tra';
        ui = window.DLQuestionUI.mount({question:q, host:$('lu-controls'), base, onChange:sync});
        sync();
    }
    async function start() {
        if (busy) return;
        busy = true; sync(); $('cc-error').textContent = '';
        window.DLFeedbackSound?.unlock();
        try {
            attempt = await api('chapters/' + document.body.dataset.topic + '/challenge', {});
            const url = new URL(location.href);
            url.searchParams.set('attempt', attempt.id);
            history.replaceState(null, '', url);
            pending = null; freshResult = false; render();
        } catch (error) { $('cc-error').textContent = error.message; }
        finally { busy = false; sync(); }
    }
    $('cc-start').onclick = start;
    $('cc-retry').onclick = start;
    $('cc-next').onclick = () => { if (!busy) render(); };
    $('cc-form').onsubmit = async event => {
        event.preventDefault();
        if (busy || (!pending && !ui?.complete())) return;
        busy = true; sync(); $('cc-error').textContent = '';
        window.DLFeedbackSound?.unlock();
        pending ??= {
            question_number:attempt.question.number,
            request_key:crypto.randomUUID?.() || Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2,'0')).join(''),
            answer:ui.read()
        };
        $('lu-controls').querySelectorAll('input,button,select').forEach(el => el.disabled = true);
        $('cc-submit').textContent = 'Đang kiểm tra…';
        try {
            const key = pending.request_key;
            const result = await api('chapter-attempts/' + attempt.id + '/answers', pending);
            pending = null; attempt = result.attempt;
            freshResult = attempt.status !== 'in_progress';
            const detail = result.feedback || {};
            ui.feedback(result.is_correct, detail);
            if (result.is_correct && detail.correct_text) {
                const answer = document.createElement('p');
                answer.textContent = 'Đáp án: ' + detail.correct_text;
                $('lu-answer-feedback').querySelector('.dq-feedback-copy')?.append(answer);
            }
            $('lu-audio').pause(); window.DLAudio?.stop();
            window.DLFeedbackSound?.play(result.is_correct, {key:'chapter-answer:' + attempt.id + ':' + key});
            $('cc-progress').value = attempt.answered;
            $('cc-check-actions').hidden = true;
            $('cc-feedback-actions').hidden = false;
            $('cc-next').hidden = false;
            $('cc-next').textContent = freshResult ? 'Xem kết quả →' : 'Câu tiếp theo →';
            $('cc-next').focus({preventScroll:true});
        } catch (error) {
            $('cc-error').textContent = error.message;
            if (error.status) {
                pending = null;
                try { attempt = await api('chapter-attempts/' + attempt.id); render(); }
                catch (reloadError) { $('cc-error').textContent = reloadError.message; }
            } else {
                $('cc-submit').textContent = 'Gửi lại đáp án';
            }
        } finally { busy = false; sync(); }
    };
    const existing = new URLSearchParams(location.search).get('attempt');
    if (existing && /^\d+$/.test(existing)) {
        busy = true; sync();
        api('chapter-attempts/' + existing).then(data => {
            if (String(data.topic_id) !== document.body.dataset.topic) throw new Error('Lượt kiểm tra thuộc chương khác.');
            attempt = data; render();
        }).catch(error => $('cc-error').textContent = error.message)
          .finally(() => { busy = false; sync(); });
    }
})();
