<?php
declare(strict_types=1);
$pageTitle='Ôn từ sai và hồi tim · Doulingo';$navActive='hearts';
require __DIR__.'/dashboard-shell.php';
?>
<style>
.hp-panel{max-width:850px;margin:24px auto;padding:28px;border:1px solid #dbe9e1;border-radius:24px;background:#fff}.hp-panel label{display:block;margin:20px 0 8px}.hp-panel input{width:100%;box-sizing:border-box;padding:15px;border:1px solid #bed8c9;border-radius:14px;font:inherit}.hp-panel progress{width:100%;accent-color:#19af61}.hp-toolbar{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.hp-words{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;list-style:none;padding:0}.hp-words li{padding:14px;border-radius:14px;background:#eff8f2}.hp-prompt{padding:20px;background:#eff8f2;border-radius:14px;font-size:24px}.hp-panel [hidden]{display:none!important}.hp-panel button:disabled{opacity:.5;cursor:wait}@media(max-width:600px){.hp-panel{padding:18px}.hp-prompt{font-size:20px}}
</style>
<section class="ld-section">
    <span class="ld-eyebrow">GIỮ NHỊP HỌC MỖI NGÀY</span>
    <h1>Ôn từ sai, lấy lại tim</h1>
    <p>Tự hồi 1 tim mỗi 30 phút, tối đa 3 tim. Ôn đúng tối đa 3 từ sai trong một lượt để nhận thêm 1 tim. Trả lời sai ở đây không mất tim.</p>
    <div class="hp-panel" id="hp-app" data-base="<?= $e($b) ?>" data-csrf="<?= $e($csrfToken) ?>">
        <div class="hp-toolbar"><strong id="hp-hearts">Đang tải tim…</strong><span id="hp-timer" role="timer"></span></div>
        <p id="hp-message" role="status" aria-live="polite"></p>
        <section id="hp-overview">
            <h2>Từ cần ôn của bạn</h2><ul id="hp-words" class="hp-words"></ul>
            <button class="ld-btn ld-btn-green" id="hp-start" disabled>Bắt đầu ôn từ sai</button>
        </section>
        <form id="hp-form" hidden>
            <p id="hp-count"></p><progress id="hp-progress" value="0" max="3" aria-label="Tiến độ ôn từ sai"></progress>
            <h2 id="hp-instruction"></h2><p id="hp-prompt" class="hp-prompt"></p>
            <label for="hp-answer">Câu trả lời tiếng Anh</label><input id="hp-answer" autocomplete="off" maxlength="1000" required>
            <button class="ld-btn ld-btn-green" type="submit" id="hp-submit" style="margin-top:16px">Kiểm tra</button>
        </form>
        <section id="hp-result" hidden><h2 id="hp-result-title"></h2><p>Đã lưu vào tài khoản của bạn. Ôn từ sai không cộng XP.</p><a class="ld-btn ld-btn-green" href="<?= $e($b) ?>/learning">Tiếp tục học</a><button class="ld-btn ld-btn-outline" id="hp-more">Xem các từ sai còn lại</button></section>
    </div>
</section>
<script defer src="<?= $e($b) ?>/assets/js/heart-practice.js?v=1"></script>
<?php require __DIR__.'/dashboard-end.php'; ?>
