<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tra từ dành cho Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5f7fa; color: #223347; font: 16px system-ui, sans-serif; }
        main { max-width: 850px; margin: auto; padding: 28px 16px; }
        h1 { font-size: 28px; } h2 { font-size: 20px; }
        section, article { background: white; border: 1px solid #d8e0e9; border-radius: 12px; padding: 20px; margin: 16px 0; }
        label { display: block; margin: 12px 0; }
        input, select, textarea, button { font: inherit; padding: 11px; border-radius: 7px; }
        input, select, textarea { width: 100%; border: 1px solid #b9c5d4; margin-top: 5px; background: white; }
        textarea { display: block; min-height: 90px; resize: vertical; }
        button { border: 0; background: #16764c; color: white; cursor: pointer; }
        button:disabled { opacity: .6; cursor: not-allowed; }
        small, .muted { color: #617185; } .error { color: #ad2525; }
        #message { white-space: pre-wrap; overflow-wrap: anywhere; }
        audio { display: block; width: 100%; max-width: 330px; margin: 8px 0; }
        article { overflow-wrap: anywhere; } article p { line-height: 1.5; }
        a { color: #1266ac; } #selected { border: 2px solid #16764c; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<main>
    <h1>Tra từ và chọn nghĩa</h1>
    <p class="muted">Chọn đúng nghĩa và cách phát âm trước khi đưa từ vào nội dung học.</p>
    <p id="message" role="status" aria-live="polite"></p>

    <section id="login-panel" <?= $isAdmin ? 'hidden' : '' ?>>
        <h2>Đăng nhập Admin</h2>
        <form id="login-form">
            <label>Email<input name="email" type="email" autocomplete="username" required></label>
            <label>Mật khẩu<input name="password" type="password" autocomplete="current-password" required></label>
            <button type="submit">Đăng nhập</button>
        </form>
    </section>

    <section id="lookup-panel" <?= $isAdmin ? '' : 'hidden' ?>>
        <form id="lookup-form">
            <label>Từ tiếng Anh<input name="word" maxlength="80" placeholder="Ví dụ: milk" required></label>
            <button type="submit">Tra từ</button>
        </form>
        <p id="count" class="muted"></p>

        <section id="selected" hidden>
            <h2>Chuẩn bị từ vựng</h2>
            <p id="selection"></p>
            <form id="prepare-form">
                <label>Chủ đề
                    <select name="topic_id" required>
                        <option value="">-- Chọn chủ đề --</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= htmlspecialchars((string) $topic['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) $topic['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($topics === []): ?>
                    <p class="error">Chưa có chủ đề đang hoạt động. Chưa thể lưu từ.</p>
                <?php endif; ?>
                <label>Nghĩa tiếng Việt<input name="vietnamese" maxlength="255" placeholder="Ví dụ: sữa" required></label>
                <label>Từ loại
                    <select name="part_of_speech" required>
                        <option value="">-- Chọn từ loại --</option>
                        <option value="noun">noun — danh từ</option>
                        <option value="verb">verb — động từ</option>
                        <option value="adjective">adjective — tính từ</option>
                        <option value="adverb">adverb — trạng từ</option>
                        <option value="pronoun">pronoun — đại từ</option>
                        <option value="preposition">preposition — giới từ</option>
                        <option value="conjunction">conjunction — liên từ</option>
                        <option value="interjection">interjection — thán từ</option>
                        <option value="determiner">determiner — từ hạn định</option>
                        <option value="phrase">phrase — cụm từ</option>
                    </select>
                </label>
                <label>Phát âm<select name="phonetic_index"><option value="">Không lưu phát âm</option></select></label>
                <small>Số thứ tự tương ứng với các mục phát âm trong thẻ nghĩa bên dưới.</small>
                <label>Ví dụ tiếng Anh<textarea name="example" maxlength="5000" required></textarea></label>
                <label>Dịch ví dụ — tùy chọn<textarea name="example_translation" maxlength="5000"></textarea></label>
                <label>URL ảnh HTTPS — tùy chọn<input name="image" type="url" maxlength="255"></label>
                <label>Trang nguồn ảnh<input name="image_source" type="url" maxlength="2000"></label>
                <label>Giấy phép/quyền sử dụng ảnh<input name="image_license" maxlength="255"></label>
                <button id="save-button" type="submit" <?= $topics === [] ? 'disabled' : '' ?>>Lưu vào kho từ</button>
            </form>
            <small>Từ chỉ được lưu khi server xác nhận thành công.</small>
        </section>
        <div id="results"></div>
    </section>
</main>
<script>
'use strict';
// LIÊN QUAN CƯỜNG: dùng API Auth/Dictionary, cookie phiên và định dạng Response chung.
const base = <?= json_encode($appBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const hasTopics = <?= $topics !== [] ? 'true' : 'false' ?>;
const $ = selector => document.querySelector(selector);
let selectedCandidate = null;
let lookupToken = null;
let busy = false;

function message(value, error = false) {
    $('#message').textContent = value;
    $('#message').className = error ? 'error' : '';
}
function setBusy(value) {
    busy = value;
    document.querySelectorAll('input, select, textarea, button').forEach(element => {
        element.disabled = value;
    });
    $('#save-button').disabled = value || !hasTopics;
}
function resetSelection() {
    selectedCandidate = null;
    $('#prepare-form').reset();
    $('#prepare-form').elements.namedItem('vietnamese').setCustomValidity('');
    $('#prepare-form').elements.namedItem('phonetic_index').replaceChildren(
        new Option('Không lưu phát âm', '')
    );
    $('#selection').textContent = '';
    $('#selected').hidden = true;
}
function requireLogin() {
    lookupToken = null;
    resetSelection();
    $('#results').replaceChildren();
    $('#count').textContent = '';
    $('#lookup-panel').hidden = true;
    $('#login-panel').hidden = false;
}
async function request(path, body) {
    const options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (body !== undefined) {
        options.method = 'POST';
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    const response = await fetch(base + '/api/v1' + path, options);
    if (response.status === 401 || response.status === 403) requireLogin();
    let payload;
    try { payload = await response.json(); }
    catch { throw new Error('Server chưa trả JSON hợp lệ. Kiểm tra route và log PHP.'); }
    if (!response.ok) {
        throw new Error(payload?.error?.message || 'Không thể xử lý yêu cầu.');
    }
    return payload?.data;
}
function https(value) {
    if (typeof value !== 'string') return null;
    try {
        const url = new URL(value);
        return url.protocol === 'https:' && !url.username && !url.password ? url.href : null;
    } catch { return null; }
}
function text(parent, tag, value) {
    const element = document.createElement(tag);
    element.textContent = value;
    parent.append(element);
    return element;
}
function source(parent, label, value) {
    const url = https(value);
    if (!url) return;
    const link = document.createElement('a');
    link.textContent = label;
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    parent.append(link, document.createTextNode(' '));
}
function selectCandidate(item) {
    if (busy) return;
    resetSelection();
    selectedCandidate = item;
    const form = $('#prepare-form');
    form.elements.namedItem('part_of_speech').value = item.part_of_speech || '';
    form.elements.namedItem('example').value = item.example || '';
    const pronunciation = form.elements.namedItem('phonetic_index');
    (Array.isArray(item.phonetics) ? item.phonetics : []).forEach((phonetic, index) => {
        pronunciation.add(new Option(
            (index + 1) + '. ' + (phonetic.ipa || 'Chưa có IPA') +
            (https(phonetic.audio) ? ' — có âm thanh' : ' — không có âm thanh'),
            String(index)
        ));
    });
    $('#selection').textContent = item.english + ' — ' + item.definition_en;
    $('#selected').hidden = false;
    $('#selected').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function render(candidates) {
    $('#results').replaceChildren();
    resetSelection();
    $('#count').textContent = 'Tìm thấy ' + candidates.length + ' nghĩa.';
    for (const item of candidates) {
        const card = document.createElement('article');
        text(card, 'h2', item.english + ' · ' + (item.part_of_speech || item.source_part_of_speech || 'Chưa rõ từ loại'));
        text(card, 'p', item.definition_en);
        if (item.example) text(card, 'p', 'Ví dụ: ' + item.example);
        const phonetics = Array.isArray(item.phonetics) ? item.phonetics : [];
        phonetics.forEach((phonetic, index) => {
            text(card, 'p', 'Phát âm ' + (index + 1) + ' — IPA: ' + (phonetic.ipa || 'Chưa có'));
            const audioUrl = https(phonetic.audio);
            if (audioUrl) {
                const audio = document.createElement('audio');
                audio.controls = true;
                audio.preload = 'none';
                audio.src = audioUrl;
                card.append(audio);
            }
            source(card, 'Nguồn phát âm', phonetic.source_url);
            if (phonetic.license?.name) text(card, 'small', 'Giấy phép âm thanh: ' + phonetic.license.name + ' ');
            source(card, 'Giấy phép âm thanh', phonetic.license?.url);
        });
        for (const url of (Array.isArray(item.source_urls) ? item.source_urls : [])) {
            source(card, 'Nguồn mục từ', url);
        }
        if (item.license?.name) text(card, 'p', 'Giấy phép: ' + item.license.name);
        source(card, 'Chi tiết giấy phép', item.license?.url);
        const choose = document.createElement('button');
        choose.type = 'button';
        choose.textContent = 'Chọn nghĩa này';
        choose.onclick = () => selectCandidate(item);
        card.append(document.createElement('br'), choose);
        $('#results').append(card);
    }
}
$('#login-form').onsubmit = async event => {
    event.preventDefault();
    if (busy) return;
    const form = event.currentTarget;
    const body = Object.fromEntries(new FormData(form));
    setBusy(true);
    try {
        const user = await request('/auth/login', body);
        if (user?.role !== 'admin') throw new Error('Tài khoản này không có quyền Admin.');
        form.reset();
        window.location.reload();
    } catch (error) { message(error.message, true); }
    finally { setBusy(false); }
};
$('#lookup-form').onsubmit = async event => {
    event.preventDefault();
    if (busy) return;
    const word = event.currentTarget.elements.namedItem('word').value.trim();
    if (!word) { message('Vui lòng nhập từ tiếng Anh.', true); return; }
    lookupToken = null;
    resetSelection();
    $('#results').replaceChildren();
    $('#count').textContent = '';
    setBusy(true);
    message('Đang tra từ...');
    try {
        const data = await request('/admin/dictionary?word=' + encodeURIComponent(word));
        if (!Array.isArray(data?.candidates)) throw new Error('Kết quả tra từ chưa đúng cấu trúc.');
        if (typeof data.lookup_token !== 'string' || !/^[a-f0-9]{64}$/.test(data.lookup_token)) {
            throw new Error('API chưa trả lookup_token hợp lệ. Kiểm tra DictionaryController.');
        }
        lookupToken = data.lookup_token;
        render(data.candidates);
        message(data.candidates.length ? 'Hãy chọn nghĩa phù hợp.' : 'Không có nghĩa phù hợp.');
    } catch (error) { message(error.message, true); }
    finally { setBusy(false); }
};
$('#prepare-form').onsubmit = async event => {
    event.preventDefault();
    if (busy) return;
    if (!selectedCandidate || !lookupToken) { message('Hãy tra từ và chọn nghĩa lại.', true); return; }
    const form = event.currentTarget;
    const vietnamese = form.elements.namedItem('vietnamese');
    vietnamese.setCustomValidity(vietnamese.value.trim() ? '' : 'Vui lòng nhập nghĩa tiếng Việt.');
    if (!form.reportValidity()) return;
    const body = Object.fromEntries(new FormData(form));
    body.lookup_token = lookupToken;
    body.candidate_key = selectedCandidate.candidate_key;
    const english = selectedCandidate.english;
    setBusy(true);
    message('Đang lưu từ...');
    try {
        const data = await request('/admin/dictionary/words', body);
        if (!Number.isInteger(data?.id) || data.id < 1) {
            throw new Error('Phản hồi lưu chưa đúng cấu trúc. Hãy kiểm tra database trước khi thử lại.');
        }
        resetSelection();
        message('Đã lưu ' + english + ' vào kho từ. ID: ' + data.id);
    } catch (error) { message(error.message, true); }
    finally {
        setBusy(false);
        $('#message').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};
$('#prepare-form').elements.namedItem('vietnamese').addEventListener('input', event => {
    event.currentTarget.setCustomValidity('');
});
// LIÊN QUAN DANH: dữ liệu API dùng textContent; không sửa layout/JS màn hình học.
// LIÊN QUAN TUẤN: chỉ tạo nội dung từ vựng, không tự ghi tiến độ/XP/trạng thái SM-2.
</script>
</body>
</html>
