<?php
declare(strict_types=1);
$escape = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$activeAdminPage = 'words';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kệ sách từ vựng · Doulingo Admin</title>
    <?php require __DIR__ . '/_styles.php'; ?>
    <script defer src="<?= $escape($appBase . '/assets/js/admin-books.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin-books.js')) ?>"></script>
</head>
<body data-app-base="<?= $escape($appBase) ?>" data-import-page="0">
<?php require __DIR__ . '/_header.php'; ?>
<main>
    <div id="message" class="notice" role="status" aria-live="polite">Đang tải kệ sách…</div>
    <section id="library-view">
        <div class="hero">
            <p class="eyebrow">THƯ VIỆN TỪ VỰNG / DÀNH CHO ADMIN</p>
            <h1>Mỗi chủ đề,<br>một <em>cuốn sách.</em></h1>
            <p class="hero-copy">Mở sách để quản lý từ vựng. Thêm cả bộ từ mẫu hoặc nhập CSV trong một lần.</p>
            <div class="hero-actions"><button id="open-import" class="button-primary" disabled>＋ Thêm dữ liệu hàng loạt</button><a class="text-link" href="<?= $escape($appBase . '/admin/topics') ?>">Tạo chủ đề mới ↗</a></div>
            <div class="library-stats" aria-label="Thống kê cơ sở dữ liệu"><span><strong id="stat-books">—</strong> chủ đề</span><span><strong id="stat-words">—</strong> từ vựng</span><span><strong id="stat-active">—</strong> từ đang hiện</span></div>
            <div class="hero-stamp" aria-hidden="true"><span>THE WORD<br>COLLECTION</span><b>✳</b><small>HỌC TỪ NHỮNG ĐIỀU NHỎ</small></div>
        </div>
        <div class="shelf-toolbar"><div><p class="eyebrow">BỘ SƯU TẬP</p><h2>Kệ sách của bạn <span id="book-count"></span></h2></div><div class="shelf-filters"><label class="sr-only" for="book-search">Tìm chủ đề</label><input id="book-search" type="search" maxlength="100" placeholder="Tìm một chủ đề…"><label class="sr-only" for="book-status">Trạng thái chủ đề</label><select id="book-status"><option value="all">Tất cả chủ đề</option><option value="1">Đang hiện</option><option value="0">Đã ẩn</option></select></div></div>
        <div id="books" class="books-grid" aria-label="Các cuốn sách chủ đề" aria-busy="true"></div>
        <p id="books-empty" class="empty-state" hidden></p>
    </section>
    <section id="book-view" hidden>
        <button id="back-library" class="back-link">← Trở lại kệ sách</button>
        <div class="book-heading"><div><p class="eyebrow">CUỐN SÁCH CHỦ ĐỀ <span id="topic-number"></span></p><h1 id="topic-title"></h1><p id="topic-description" class="muted"></p><p id="topic-counts"></p></div><div class="book-actions"><button id="book-import" class="button-primary">＋ Nhập từ hàng loạt</button><a id="edit-topic" class="text-link">Sửa chủ đề ↗</a></div></div>
        <p id="topic-hidden" class="notice" hidden>Chủ đề đang ẩn. Bạn có thể xem và sửa từ đã ẩn; mở lại chủ đề trước khi thêm hoặc hiện từ.</p>
        <div class="paper-panel">
            <form id="word-filter" class="word-filter"><label class="sr-only" for="word-search">Tìm từ trong sách</label><label class="sr-only" for="word-level">Mức từ vựng</label><select id="word-level"><option value="">Tất cả mức</option><?php foreach (\App\Shared\Cefr::LEVELS as $level): ?><option value="<?= $level ?>"><?= $level ?></option><?php endforeach; ?></select><input id="word-search" name="q" type="search" maxlength="100" placeholder="Tìm từ tiếng Anh hoặc nghĩa tiếng Việt…"><button class="button-secondary">Tìm kiếm</button><span id="word-total" class="muted"></span></form>
            <div class="table-wrap"><table><thead><tr><th scope="col">Từ vựng</th><th scope="col">Nghĩa tiếng Việt</th><th scope="col">Mức</th><th scope="col">Từ loại</th><th scope="col">Trạng thái</th><th scope="col">Quản lý</th></tr></thead><tbody id="words"></tbody></table></div>
            <div class="pagination"><button id="prev-page" class="button-secondary" disabled>← Trang trước</button><span id="page-info"></span><button id="next-page" class="button-secondary" disabled>Trang sau →</button></div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/_footer.php'; ?>

<dialog id="import-dialog" aria-labelledby="import-title">
    <div class="dialog-heading"><div><p class="eyebrow">XÂY THÊM NHỮNG TRANG SÁCH</p><h2 id="import-title">Thêm dữ liệu hàng loạt</h2></div><button type="button" data-close="import-dialog" class="icon-button" aria-label="Đóng nhập dữ liệu">×</button></div>
    <form id="import-form">
        <label>Sách nhận từ<select id="import-topic" name="topic_id" required></select></label>
        <p id="no-import-topics" class="notice" hidden>Chưa có chủ đề đang hiện. <a href="<?= $escape($appBase . '/admin/topics') ?>">Tạo hoặc mở lại chủ đề</a> để nhập từ.</p>
        <label>Mức mặc định (khi CSV chưa có cột cefr_level)<select id="import-level" name="cefr_level"><?php foreach (\App\Shared\Cefr::LEVELS as $level): ?><option value="<?= $level ?>"><?= $level ?></option><?php endforeach; ?></select></label><fieldset class="import-choice"><legend>Nguồn dữ liệu</legend><label><input type="radio" name="source" value="sample" checked> Bộ từ mẫu có sẵn</label><label><input type="radio" name="source" value="csv"> File CSV của bạn</label></fieldset>
        <div id="sample-fields"><label>Bộ từ mẫu<select id="sample-key" name="sample_key"></select></label><p class="muted">Có sẵn nghĩa tiếng Việt và ví dụ song ngữ. Nhập cả bộ, không cần tra từng từ.</p></div>
        <div id="csv-fields" hidden><label>File CSV UTF-8<input id="csv-file" type="file" name="csv" accept=".csv,text/csv"></label><p class="muted">Tối đa 1 MiB / 1.000 từ. Cần 4 cột: english, vietnamese, part_of_speech, example. Thêm cột cefr_level (A1, A2, B1, B2, C1, C2) để nhập nhiều mức trong cùng file. Chọn sách ở trên để tự gán chủ đề.</p><a class="text-link" href="<?= $escape($appBase . '/api/v1/admin/content/csv/template') ?>">↓ Tải file CSV mẫu</a></div>
        <label class="check-label"><input id="skip-duplicates" type="checkbox" checked> Bỏ qua từ trùng, giữ nguyên dữ liệu đã có</label>
        <p class="muted">Từ trùng được xác định theo chủ đề, mức CEFR, từ tiếng Anh, từ loại và nghĩa tiếng Việt; bao gồm cả từ đã ẩn.</p>
        <button id="preview-import" class="button-primary" type="submit">Kiểm tra &amp; xem trước →</button>
    </form>
    <p id="import-message" role="status" aria-live="polite"></p>
    <div id="preview-panel" hidden><div id="preview-summary" class="preview-summary"></div><ul id="preview-errors" class="errors"></ul><div class="table-wrap"><table><thead><tr><th scope="col">Từ</th><th scope="col">Nghĩa</th><th scope="col">Ví dụ</th></tr></thead><tbody id="preview-rows"></tbody></table></div><p class="muted">Hiển thị tối đa 20 từ mới. Xác nhận để nhập toàn bộ từ hợp lệ; bản xem trước có hiệu lực 15 phút.</p></div>
    <div class="dialog-actions"><button type="button" data-close="import-dialog" class="button-secondary">Đóng</button><button id="commit-import" class="button-primary" type="button" disabled>Nhập vào sách</button></div>
</dialog>

<dialog id="word-dialog" aria-labelledby="word-title">
    <div class="dialog-heading"><h2 id="word-title">Sửa từ vựng</h2><button type="button" data-close="word-dialog" class="icon-button" aria-label="Đóng sửa từ">×</button></div>
    <form id="word-form">
        <input type="hidden" name="id"><input type="hidden" name="topic_id">
        <div class="form-grid"><label>Từ tiếng Anh<input name="english" maxlength="150" required></label><label>Nghĩa tiếng Việt<input name="vietnamese" maxlength="255" required></label><label>Từ loại<select name="part_of_speech" required></select></label><label>Mức CEFR<select name="cefr_level"><?php foreach (\App\Shared\Cefr::LEVELS as $level): ?><option value="<?= $level ?>"><?= $level ?></option><?php endforeach; ?></select></label><label>Phiên âm IPA<input name="ipa" maxlength="150"></label></div>
        <label>Ví dụ tiếng Anh<textarea name="example" maxlength="5000" required></textarea></label><label>Dịch ví dụ<textarea name="example_translation" maxlength="5000"></textarea></label>
        <details class="media-fields"><summary>Ảnh &amp; âm thanh</summary><div class="form-grid"><?php foreach (['image' => 'Ảnh', 'audio' => 'Âm thanh'] as $field => $label): ?><label>Tệp <?= $label ?> (HTTPS hoặc /assets/media/...)<input name="<?= $field ?>" type="text" inputmode="url" maxlength="255" placeholder="/assets/media/..."></label><label>Nguồn <?= $label ?> (HTTPS hoặc tệp tự sở hữu)<input name="<?= $field ?>_source" type="text" inputmode="url" maxlength="2000"></label><label>Quyền sử dụng <?= $label ?><input name="<?= $field ?>_license" maxlength="255"></label><?php endforeach; ?></div></details>
        <label>Trạng thái<select name="is_active"><option value="1">Hiện</option><option value="0">Ẩn</option></select></label>
        <p class="muted">Từ đang hiện trong sách tự tạo bài tập ở đúng mức A1–C2 trong chủ đề. Cần ít nhất 2 từ ở một mức để bắt đầu; cần 4 từ/nghĩa khác nhau để có câu trắc nghiệm 4 lựa chọn. Ẩn từ giữ lại liên kết và lịch sử học.</p>
        <p id="word-message" role="status" aria-live="polite"></p><div class="dialog-actions"><button type="button" data-close="word-dialog" class="button-secondary">Hủy</button><button id="save-word" class="button-primary">Lưu từ vựng</button></div>
    </form>
</dialog>
</body>
</html>
