<?php
$reviewApiBase = $reviewApiBase ?? rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')),
    '/.'
);

$basePath = $reviewApiBase;
$navActive = 'reviews';
$pageTitle = 'Ôn tập từ vựng — Doulingo';

require dirname(__DIR__) . '/learning/dashboard-shell.php';
require __DIR__ . '/styles.php';
?>

<script>
window.TuanReviewConfig = {
    baseUrl: <?= json_encode(
        $reviewApiBase,
        JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR
    ) ?>
};
</script>

<section class="review-shell" aria-labelledby="review-title">
    <header class="review-hero">
        <div>
            <p class="eyebrow">ÔN TẬP TỪ VỰNG</p>

            <h1 id="review-title">
                Ôn một chút.<br>
                Nhớ thêm nhiều.
            </h1>

            <p class="hero-description">
                Gặp lại những từ đã học, tự kiểm tra trí nhớ
                và xây dựng vốn từ bền vững mỗi ngày.
            </p>

            <p id="review-profile" class="muted">
                Đang kiểm tra phiên đăng nhập…
            </p>

            <div class="hero-links">
                <a
                    class="button"
                    href="<?= $e($reviewApiBase) ?>/learning"
                >
                    Học bài mới
                    <?= $icon('arrow') ?>
                </a>

                <a
                    class="button secondary"
                    href="<?= $e($reviewApiBase) ?>/learning/progress"
                >
                    Xem tiến độ
                </a>
            </div>
        </div>

        <div class="review-hero-icon" aria-hidden="true">
            <?= $icon('book') ?>
        </div>
    </header>

    <section class="review-benefits" aria-label="Cách ôn tập">
        <div class="benefit-card">
            <span class="benefit-number">01</span>
            <div>
                <strong>Đúng thời điểm</strong>
                <p>Ưu tiên những thẻ đã đến hạn.</p>
            </div>
        </div>

        <div class="benefit-card">
            <span class="benefit-number">02</span>
            <div>
                <strong>Nhớ theo cách của bạn</strong>
                <p>Tự đánh giá sau khi lật thẻ.</p>
            </div>
        </div>

        <div class="benefit-card">
            <span class="benefit-number">03</span>
            <div>
                <strong>Theo dõi từng lần ôn</strong>
                <p>Xem lịch sử và lịch hẹn tiếp theo.</p>
            </div>
        </div>
    </section>

    <div
        id="review-message"
        class="message"
        role="status"
        hidden
    ></div>

    <div class="review-workspace">
        <div class="review-content">
            <nav class="review-tabs" aria-label="Các phần ôn tập">
                <button type="button" data-tab="due" class="active">
                    Thẻ đến hạn
                    <b id="review-count">0</b>
                </button>

                <button type="button" data-tab="history">
                    Lịch sử ôn
                </button>

                <button type="button" data-tab="notifications">
                    Nhắc học
                </button>
            </nav>

            <section
                id="review-toolbar"
                class="toolbar"
                aria-label="Lọc lịch ôn"
            >
                <label for="review-direction">
                    Chiều ôn

                    <select id="review-direction">
                        <option value="">Tất cả</option>
                        <option value="en_vi">Anh → Việt</option>
                        <option value="vi_en">Việt → Anh</option>
                        <option value="listening">Nghe</option>
                        <option value="spelling">Cách viết</option>
                    </select>
                </label>

                <button
                    type="button"
                    class="button secondary"
                    data-action="reload"
                >
                    Tải lại
                </button>
            </section>

            <section
                id="review-app"
                aria-live="polite"
                aria-label="Nội dung ôn tập"
            >
                <div class="panel empty">
                    Đang tải dữ liệu…
                </div>
            </section>
        </div>

        <aside class="review-guide" aria-label="Hướng dẫn ôn tập">
            <div class="guide-card">
                <p class="guide-kicker">BẮT ĐẦU ÔN TẬP</p>
                <h2>Ôn một thẻ thế nào?</h2>

                <ol class="guide-steps">
                    <li>Nhìn từ hoặc nghe phát âm.</li>
                    <li>Tự nhớ đáp án trước khi lật thẻ.</li>
                    <li>Lật thẻ, đối chiếu và chọn mức nhớ.</li>
                </ol>
            </div>

            <div class="guide-card">
                <p class="guide-kicker">HIỂU MỨC NHỚ CỦA BẠN</p>
                <h2>Chọn mức nào?</h2>

                <div class="memory-level">
                    <span class="level-badge level-low">0–2</span>
                    <div>
                        <strong>Chưa nhớ được</strong>
                        <p>Cần xem đáp án để nhớ lại.</p>
                    </div>
                </div>

                <div class="memory-level">
                    <span class="level-badge">3</span>
                    <div>
                        <strong>Nhớ nhưng còn khó</strong>
                        <p>Mất nhiều thời gian để nhớ ra.</p>
                    </div>
                </div>

                <div class="memory-level">
                    <span class="level-badge">4–5</span>
                    <div>
                        <strong>Nhớ được hoặc nhớ dễ</strong>
                        <p>Tự nhớ ra trước khi xem đáp án.</p>
                    </div>
                </div>

                <p class="guide-note">
                    Chọn đúng cảm nhận của bạn.
                    Quên một từ cũng là một phần của việc học.
                </p>
            </div>
        </aside>
    </div>

    <footer>
        Một lần ôn nhỏ hôm nay, một vốn từ vững hơn ngày mai.
    </footer>
</section>

<script src="<?= $e($reviewApiBase) ?>/assets/js/learning.js"></script>
<script src="<?= $e($reviewApiBase) ?>/assets/js/learning-media.js?v=1"></script>
<script src="<?= $e($reviewApiBase) ?>/assets/js/learning-audio.js?v=2"></script>

<?php
require __DIR__ . '/script.php';
require dirname(__DIR__) . '/learning/dashboard-end.php';
?>