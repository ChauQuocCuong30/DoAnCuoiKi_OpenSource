<?php require __DIR__ . '/_j_head.php'; ?>
<main class="j-wrap center">
    <a class="back" href="<?= $e($base) ?>/start">← Quay lại</a>
    <a class="j-brand" href="<?= $e($base) ?>/welcome">Doulingo</a>
    <p class="j-label">Bước 1</p>
    <h1 class="j-title">Trình độ tiếng Anh<br>của bạn là gì?</h1>
    <p class="j-sub">Chọn một mức để bắt đầu, hoặc để Doulingo kiểm tra giúp bạn.</p>

    <div class="levels">
        <button class="lv" data-level="a1"><span class="tag">A1</span><strong>Mới bắt đầu</strong><em>Từ cơ bản hằng ngày: đồ ăn, gia đình, màu sắc.</em></button>
        <button class="lv" data-level="a2"><span class="tag">A2</span><strong>Cơ bản</strong><em>Du lịch, mua sắm, thời tiết, công việc đơn giản.</em></button>
        <button class="lv" data-level="b1"><span class="tag">B1</span><strong>Trung cấp</strong><em>Từ vựng cho học tập, công việc và trao đổi ý kiến.</em></button>
    </div>

    <a class="test-card" href="<?= $e($base) ?>/placement">
        <div><strong>Chưa chắc? Làm bài kiểm tra nhanh</strong><span>9 câu, khoảng 2 phút. Doulingo gợi ý trình độ phù hợp.</span></div>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
</main>
<?php require __DIR__ . '/_j_foot.php'; ?>
