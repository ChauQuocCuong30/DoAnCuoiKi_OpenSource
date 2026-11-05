<?php
declare(strict_types=1);
$escape = static fn($v): string => htmlspecialchars(
    (string) $v,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);
$titles = [
    'words' => 'Quản lý từ vựng',
    'lessons' => 'Quản lý bài học',
    'exercises' => 'Quản lý câu hỏi',
    'import' => 'Nhập CSV',
];
?>
<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $escape(
    $titles[$section],
) ?></title>
<style>
* {
    box-sizing: border-box;
}
body {
    margin: 0;
    font:
        16px system-ui,
        sans-serif;
    background: #f5f7fa;
    color: #223347;
}
main {
    max-width: 1120px;
    margin: auto;
    padding: 24px 16px;
}
nav {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}
a {
    color: #1266ac;
}
section {
    padding: 20px;
    background: white;
    border: 1px solid #d8e0e9;
    border-radius: 12px;
    margin: 20px 0;
}
label {
    display: block;
    margin: 12px 0;
}
input,
select,
textarea,
button {
    font: inherit;
    padding: 10px;
    border-radius: 6px;
}
input,
select,
textarea {
    width: 100%;
    border: 1px solid #b9c5d4;
    background: white;
}
textarea {
    min-height: 95px;
}
textarea.json {
    min-height: 240px;
    font: 14px monospace;
}
button {
    border: 0;
    background: #16764c;
    color: white;
    cursor: pointer;
    margin: 4px;
}
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.error {
    color: #ad2525;
}
.muted {
    color: #617185;
}
.table-wrap {
    overflow: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
td,
th {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: left;
    overflow-wrap: anywhere;
}
pre {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
#message {
    white-space: pre-wrap;
}
[hidden] {
    display: none !important;
}
</style></head><body><main>
<h1><?= $escape($titles[$section]) ?></h1>
<nav>
<?php foreach (
    [
        'dictionary' => 'Tra API',
        'topics' => 'Chủ đề',
        'words' => 'Từ vựng',
        'lessons' => 'Bài học',
        'exercises' => 'Câu hỏi',
        'import' => 'CSV',
    ]
    as $path => $title
): ?>
<a href="<?= $escape($appBase . '/admin/' . $path) ?>"><?= $escape(
    $title,
) ?></a>
<?php endforeach; ?>
</nav>
<p id="message" role="status" aria-live="polite"></p>
<p class="muted">Chỉ Admin được thay đổi nội dung. Không xóa cứng từ đã học. Sau khi ẩn nội dung, bài học liên quan có thể chưa đủ câu hỏi.</p>
<?php if ($section === 'import'): ?>
<section>
<h2>1. Kiểm tra CSV</h2>
<p>UTF-8, dấu phân cách phẩy; tối đa 1 MiB / 1000 từ mỗi đợt. Có thể chia 10.000 từ thành 10 đợt. Nhập CSV không gọi API từ điển cho từng từ.</p>
<p>Header bắt buộc đúng thứ tự:</p><pre id="csv-header"></pre>
<form id="csv-form"><label>File CSV<input type="file" name="csv" accept=".csv,text/csv" required></label><button>Kiểm tra / Xem trước</button></form>
<h2>2. Duyệt và nhập</h2><pre id="csv-result"></pre><button id="commit" type="button" disabled>Nhập toàn bộ dữ liệu hợp lệ</button>
<p class="muted">Có bất kỳ dòng lỗi thì không nhập. Bản xem trước có hiệu lực 15 phút; khi xác nhận, server kiểm tra lại và lưu toàn bộ trong một transaction.</p>
</section>
<?php else: ?>
<section>
<h2>Danh sách</h2>
<form id="filter-form"><label>Tìm kiếm<input name="q" maxlength="100" placeholder="<?= $section ===
'exercises'
    ? 'Tên dạng câu hỏi, ví dụ choose_meaning'
    : 'Tên hoặc từ cần tìm' ?>"></label>
<label><?= $section === 'exercises'
    ? 'ID bài học'
    : 'ID chủ đề' ?> — để trống xem tất cả<input name="parent_id" type="number" min="1" step="1"></label><button>Tìm kiếm</button></form>
<p id="pagination"></p><div class="table-wrap"><table><thead id="table-head"></thead><tbody id="table-body"></tbody></table></div>
<button id="previous" type="button">Trang trước</button><button id="next" type="button">Trang sau</button>
</section>
<section id="editor"><h2 id="editor-title">Thêm nội dung</h2>
<p class="muted" id="editor-help"></p>
<form id="edit-form"><input name="id" type="hidden"><div id="fields"></div><button>Lưu nội dung</button><button type="button" id="new">Tạo mới / Hủy sửa</button></form>
<?php if (
    $section === 'exercises'
): ?><button id="template" type="button">Nạp mẫu JSON theo dạng đã chọn</button><p class="muted">Mẫu dùng ID từ ví dụ 1; sửa target_word_ids theo từ đã gắn trong bài. content_json chỉ chứa đề; answer_json chỉ dành cho backend.</p><?php endif; ?>
</section>
<?php endif; ?>
</main><script>
'use strict';
// LIÊN QUAN CƯỜNG: tất cả ghi dữ liệu đi qua Auth::admin và X-CSRF-Token.
const base = <?= json_encode(
    $appBase,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>;
const section = <?= json_encode($section) ?>;
const $ = (s) => document.querySelector(s);
let csrf = null,
    busy = false,
    page = 1,
    pages = 1,
    previewToken = null;
const fields = {
    words: [
        ['topic_id', 'ID chủ đề', 'number'],
        ['english', 'Từ tiếng Anh'],
        ['vietnamese', 'Nghĩa tiếng Việt'],
        ['part_of_speech', 'Từ loại', 'pos'],
        ['ipa', 'IPA — tùy chọn'],
        ['example', 'Ví dụ tiếng Anh', 'textarea'],
        ['example_translation', 'Dịch ví dụ — tùy chọn', 'textarea'],
        ['image', 'URL ảnh HTTPS — tùy chọn', 'url'],
        ['image_source', 'Trang nguồn ảnh', 'url'],
        ['image_license', 'Giấy phép/quyền sử dụng ảnh'],
        ['audio', 'URL âm thanh HTTPS — tùy chọn', 'url'],
        ['audio_source', 'Trang nguồn âm thanh', 'url'],
        ['audio_license', 'Giấy phép/quyền sử dụng âm thanh'],
        ['is_active', 'Trạng thái', 'active'],
    ],
    lessons: [
        ['topic_id', 'ID chủ đề', 'number'],
        ['name', 'Tên bài học'],
        ['level', 'Cấp độ nội bộ 1–5', 'number'],
        ['position', 'Thứ tự trong chủ đề', 'number'],
        ['question_count', 'Số câu chính (1–100, không tính match_pairs)', 'number'],
        ['pass_percent', 'Ngưỡng: tỷ lệ đúng phải lớn hơn giá trị này', 'decimal'],
        ['xp_reward', 'XP thưởng (0–10000)', 'zero'],
        ['word_ids', 'ID từ gắn vào bài, cách nhau bằng dấu phẩy'],
        ['is_active', 'Trạng thái', 'active'],
    ],
    exercises: [
        ['lesson_id', 'ID bài học', 'number'],
        ['word_id', 'ID từ chính — để trống cho câu nhiều từ', 'number'],
        ['type', 'Dạng câu hỏi', 'types'],
        ['difficulty', 'Độ khó 1–5', 'number'],
        ['content_json', 'Đề bài — JSON', 'json'],
        ['answer_json', 'Đáp án backend — JSON', 'json'],
        ['is_active', 'Trạng thái', 'active'],
    ],
};
const optional = new Set([
    'ipa',
    'example_translation',
    'image',
    'image_source',
    'image_license',
    'audio',
    'audio_source',
    'audio_license',
    'word_id',
    'word_ids',
]);
const defaults = {
    words: { is_active: '1' },
    lessons: {
        level: '1',
        question_count: '10',
        pass_percent: '70',
        xp_reward: '10',
        is_active: '0',
    },
    exercises: { difficulty: '1', type: 'choose_meaning', is_active: '0' },
};
function message(v, error = false) {
    $('#message').textContent = v;
    $('#message').className = error ? 'error' : '';
}
function lock(value) {
    busy = value;
    document
        .querySelectorAll('button,input,textarea,select')
        .forEach((e) => (e.disabled = value));
    if (section === 'import') $('#commit').disabled = value || !previewToken;
}
async function request(path, body) {
    const options = {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    };
    if (body !== undefined) {
        options.method = 'POST';
        options.headers['X-CSRF-Token'] = csrf || '';
        if (body instanceof FormData) options.body = body;
        else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
    }
    const response = await fetch(base + '/api/v1' + path, options);
    let data;
    try {
        data = await response.json();
    } catch {
        throw Error('Server chưa trả JSON. Kiểm tra route và log PHP.');
    }
    if (!response.ok)
        throw Error(data?.error?.message || 'Không thể xử lý yêu cầu.');
    return data.data;
}
function addText(parent, tag, value) {
    const el = document.createElement(tag);
    el.textContent = String(value ?? '');
    parent.append(el);
    return el;
}
function buildFields(boot) {
    for (const [name, label, type = 'text'] of fields[section]) {
        const wrapper = document.createElement('label');
        wrapper.append(document.createTextNode(label));
        let input;
        if (['pos', 'types', 'active'].includes(type)) {
            input = document.createElement('select');
            const options =
                type === 'active'
                    ? [
                          ['0', 'Ẩn / Bản nháp'],
                          ['1', 'Hiện'],
                      ]
                    : (type === 'pos'
                          ? boot.part_of_speech
                          : boot.exercise_types
                      ).map((x) => [x, x]);
            options.forEach(([v, t]) => input.add(new Option(t, v)));
        } else {
            input = document.createElement(
                type === 'textarea' || type === 'json' ? 'textarea' : 'input',
            );
            if (input.tagName === 'INPUT') {
                input.type = ['number', 'zero', 'decimal'].includes(type)
                    ? 'number'
                    : type;
                if (['number', 'zero', 'decimal'].includes(type)) {
                    input.min = type === 'number' ? '1' : '0';
                    input.step = type === 'decimal' ? '0.01' : '1';
                }
            }
            if (type === 'json') input.className = 'json';
            input.maxLength =
                type === 'json' ? 30000 : type === 'textarea' ? 5000 : 2000;
        }
        input.name = name;
        input.required = !optional.has(name);
        wrapper.append(input);
        $('#fields').append(wrapper);
    }
    $('#editor-help').textContent =
        section === 'words'
            ? 'Ảnh/âm thanh cần nguồn và quyền sử dụng. Từ có lịch sử không được đổi sang nghĩa khác; tạo từ mới khi cần.'
            : section === 'lessons'
              ? 'Tạo bài ở trạng thái Ẩn, gắn ID từ cùng chủ đề, thêm câu hỏi rồi chuyển Hiện. Hiện bài cần đủ câu chính; match_pairs là vòng ghép cặp riêng.'
              : 'Gắn từ vào bài học trước khi tạo câu hỏi. Câu hỏi đã có lịch sử chỉ được đổi ẩn/hiện; tạo câu mới để thay đề hoặc đáp án.';
    reset();
}
function reset() {
    const form = $('#edit-form');
    form.reset();
    form.elements.namedItem('id').value = '';
    for (const [k, v] of Object.entries(defaults[section]))
        form.elements.namedItem(k).value = v;
    $('#editor-title').textContent = 'Thêm nội dung';
}
async function list() {
    const filter = $('#filter-form');
    const values = {
        q: filter.elements.namedItem('q').value,
        parent_id: filter.elements.namedItem('parent_id').value,
        page: String(page),
    };
    const data = await request(
        '/admin/content/' + section + '?' + new URLSearchParams(values),
    );
    page = data.page;
    pages = data.pages;
    $('#pagination').textContent =
        'Tổng ' + data.total + ' — Trang ' + page + '/' + pages;
    $('#table-head').replaceChildren();
    $('#table-body').replaceChildren();
    const columns =
        section === 'words'
            ? [
                  'id',
                  'topic_id',
                  'english',
                  'vietnamese',
                  'part_of_speech',
                  'is_active',
              ]
            : section === 'lessons'
              ? [
                    'id',
                    'topic_id',
                    'name',
                    'level',
                    'position',
                    'question_count',
                    'is_active',
                ]
              : [
                    'id',
                    'lesson_id',
                    'word_id',
                    'type',
                    'difficulty',
                    'is_active',
                ];
    const header = document.createElement('tr');
    columns.forEach((k) => addText(header, 'th', k));
    addText(header, 'th', 'Thao tác');
    $('#table-head').append(header);
    for (const row of data.items) {
        const tr = document.createElement('tr');
        columns.forEach((k) =>
            addText(
                tr,
                'td',
                k === 'is_active'
                    ? Number(row[k]) === 1
                        ? 'Hiện'
                        : 'Ẩn'
                    : row[k],
            ),
        );
        const td = document.createElement('td');
        const button = addText(td, 'button', 'Sửa / Ẩn / Hiện');
        button.type = 'button';
        button.onclick = () =>
            run(async () => {
                const item = await request(
                    '/admin/content/' + section + '/' + row.id,
                );
                reset();
                for (const [k, v] of Object.entries(item)) {
                    const field = $('#edit-form').elements.namedItem(k);
                    if (field)
                        field.value = Array.isArray(v)
                            ? v.join(',')
                            : (v ?? '');
                }
                $('#editor-title').textContent = 'Sửa #' + row.id;
                $('#editor').scrollIntoView({ behavior: 'smooth' });
            });
        tr.append(td);
        $('#table-body').append(tr);
    }
    if (!data.items.length) {
        const tr = document.createElement('tr');
        const td = addText(tr, 'td', 'Không có dữ liệu phù hợp.');
        td.colSpan = columns.length + 1;
        $('#table-body').append(tr);
    }
}
async function run(work) {
    if (busy) return;
    lock(true);
    try {
        await work();
    } catch (e) {
        message(e.message, true);
    } finally {
        lock(false);
    }
}
function sample() {
    const f = $('#edit-form');
    const type = f.elements.namedItem('type').value;
    const word = Number(f.elements.namedItem('word_id').value) || 1;
    const c = {
        schema_version: 1,
        instruction: 'Thay hướng dẫn phù hợp',
        prompt: 'Thay câu hỏi',
        target_word_ids: [word],
    };
    let a;
    if (
        [
            'choose_meaning',
            'choose_image',
            'listen_select',
            'sound_discrimination',
        ].includes(type)
    ) {
        c.options = [
            { id: 'a', text: 'Lựa chọn A' },
            { id: 'b', text: 'Lựa chọn B' },
        ];
        if (type === 'choose_image') c.options.forEach((x) => (x.image = ''));
        if (['listen_select', 'sound_discrimination'].includes(type))
            c.audio = '';
        a = { correct_option_ids: ['a'] };
    } else if (type === 'token_order') {
        delete c.prompt;
        c.tokens = [
            { id: 't1', text: 'I' },
            { id: 't2', text: 'learn' },
        ];
        a = { accepted_sequences: [['t1', 't2']] };
    } else if (type === 'match_pairs') {
        const second = word + 1;
        c.target_word_ids = [word, second];
        delete c.prompt;
        c.left = [
            { id: 'L1', text: 'Nghĩa 1' },
            { id: 'L2', text: 'Nghĩa 2' },
        ];
        c.right = [
            { id: 'R1', text: 'Từ 1' },
            { id: 'R2', text: 'Từ 2' },
        ];
        a = {
            pairs: [
                { left_id: 'L1', right_id: 'R1', word_id: word },
                { left_id: 'L2', right_id: 'R2', word_id: second },
            ],
        };
    } else {
        if (type === 'fill_blank') c.prompt = 'Điền từ ____.';
        if (type === 'listen_type') c.audio = '';
        a = {
            accepted_answers: ['Thay đáp án'],
            normalization: { trim: true, case_sensitive: false },
        };
    }
    f.elements.namedItem('content_json').value = JSON.stringify(c, null, 2);
    f.elements.namedItem('answer_json').value = JSON.stringify(a, null, 2);
}
(async () => {
    lock(true);
    try {
        const boot = await request('/admin/content/bootstrap');
        csrf = boot.csrf_token;
        if (section === 'import') {
            $('#csv-header').textContent = boot.csv_headers.join(',');
        } else {
            buildFields(boot);
            await list();
        }
        message('Sẵn sàng.');
    } catch (e) {
        message(
            e.message +
                ' Nếu chưa đăng nhập, mở trang Tra API để đăng nhập Admin.',
            true,
        );
    } finally {
        lock(false);
    }
})();
if (section === 'import') {
    $('#csv-form').onsubmit = (e) => {
        e.preventDefault();
        if (busy) return;
        const body = new FormData(e.currentTarget);
        previewToken = null;
        $('#csv-result').textContent = '';
        run(async () => {
            const data = await request('/admin/content/csv/preview', body);
            previewToken = data.preview_token;
            $('#csv-result').textContent = JSON.stringify(data, null, 2);
            message(
                data.errors.length
                    ? 'CSV có lỗi, chưa nhập dữ liệu.'
                    : 'Đã kiểm tra ' +
                          data.valid_count +
                          ' từ. Hãy duyệt bản xem trước.',
                data.errors.length > 0,
            );
        });
    };
    $('#csv-form').elements.namedItem('csv').onchange = () => {
        previewToken = null;
        $('#commit').disabled = true;
        $('#csv-result').textContent = '';
    };
    $('#commit').onclick = () => {
        if (!previewToken) return;
        run(async () => {
            const data = await request('/admin/content/csv/commit', {
                preview_token: previewToken,
            });
            previewToken = null;
            $('#csv-result').textContent = JSON.stringify(data, null, 2);
            message('Đã nhập ' + data.imported + ' từ.');
        });
    };
} else {
    $('#filter-form').onsubmit = (e) => {
        e.preventDefault();
        run(async () => {
            page = 1;
            await list();
        });
    };
    $('#previous').onclick = () =>
        run(async () => {
            page = Math.max(1, page - 1);
            await list();
        });
    $('#next').onclick = () =>
        run(async () => {
            page = Math.min(pages, page + 1);
            await list();
        });
    $('#new').onclick = () => {
        if (!busy) reset();
    };
    $('#edit-form').onsubmit = (e) => {
        e.preventDefault();
        if (busy) return;
        const body = Object.fromEntries(new FormData(e.currentTarget));
        run(async () => {
            const result = await request('/admin/content/' + section, body);
            await list();
            message('Đã lưu ID ' + result.id);
            $('#edit-form').elements.namedItem('id').value = result.id;
        });
    };
    if (section === 'exercises')
        $('#template').onclick = () => {
            if (!busy) sample();
        };
}
// LIÊN QUAN DANH/TUẤN: UI quản trị độc lập, không tính điểm hoặc ghi lịch ôn.
</script></body></html>
