<?php
declare(strict_types=1);
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$b = rtrim((string)$basePath, '/');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Học vượt · <?= $e($topic['name']) ?></title>
    <link rel="stylesheet" href="<?= $e($b) ?>/assets/css/learning-ui.css?v=12">
    <link rel="stylesheet" href="<?= $e($b) ?>/assets/css/learning-interactions.css?v=3">
    <?php $base=$b; require dirname(__DIR__).'/layouts/theme-head.php'; ?>
    <link rel="stylesheet" href="<?= $e($b) ?>/assets/css/chapter-path.css?v=1">
    <link rel="stylesheet" href="<?= $e($b) ?>/assets/css/learning-rewards.css?v=1">
    <script defer src="<?= $e($b) ?>/assets/js/learning-audio.js?v=2"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-question-ui.js?v=3"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-feedback-sound.js?v=1"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-rewards.js?v=1"></script>
    <script defer src="<?= $e($b) ?>/assets/js/chapter-challenge.js?v=2"></script>
</head>
<body class="lu dq-lesson chapter-check" data-base="<?= $e($b) ?>" data-topic="<?= (int)$topic['id'] ?>" data-csrf="<?= $e($csrfToken) ?>">
<div class="shell">
    <header class="top">
        <a class="brand" href="<?= $e($b) ?>/">Doulingo.</a>
        <nav aria-label="Điều hướng chính">
            <a href="<?= $e($b) ?>/learning">Lộ trình</a>
            <a href="<?= $e($b) ?>/learning/progress">Tiến độ</a>
        </nav>
        <div class="theme-account-actions">
            <?php require dirname(__DIR__).'/layouts/theme-toggle.php'; ?>
            <a class="theme-account-link" href="<?= $e($b) ?>/profile">Tài khoản</a>
        </div>
    </header>
    <main>
        <section id="cc-intro" class="card cc-view">
            <span class="cc-eyebrow">HỌC VƯỢT CHƯƠNG</span>
            <h1><?= $e($topic['name']) ?></h1>
            <p>Trả lời tối đa 12 câu từ các bài trong chương. Đạt ít nhất 80% để mở toàn bộ bài của chương này.</p>
            <p>Kết quả lưu vào tài khoản. Bài kiểm tra không cộng XP, không trừ tim và không thay lịch ôn hiện tại.</p>
            <button id="cc-start" class="btn primary" type="button">Bắt đầu kiểm tra</button>
        </section>
        <section id="cc-test" class="cc-view" hidden>
            <div class="cc-progress">
                <progress id="cc-progress" max="12" value="0" aria-label="Tiến độ kiểm tra"></progress>
                <span id="cc-count"></span>
            </div>
            <section id="lu-question" class="question">
                <p class="kicker">Kiểm tra kiến thức chương</p>
                <h2 id="lu-instruction" tabindex="-1"></h2>
                <div class="dq-prompt-row">
                    <div class="dq-speech-bubble">
                        <button id="lu-question-sound" class="dq-speaker" type="button" aria-label="Nghe lại tiếng Anh" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15 8a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/></svg>
                        </button>
                        <p id="lu-prompt" class="prompt"></p>
                    </div>
                </div>
                <p id="lu-audio-note" class="dq-audio-note" role="status"></p>
                <audio id="lu-audio" preload="none"></audio>
                <form id="cc-form">
                    <div id="lu-controls"></div>
                    <div id="cc-check-actions" class="actions">
                        <button id="cc-submit" class="btn primary" type="submit">Kiểm tra</button>
                    </div>
                </form>
                <div id="cc-feedback-actions" class="actions dq-next-actions" hidden>
                    <div id="lu-answer-feedback" role="status" aria-live="polite" hidden></div>
                    <button id="cc-next" class="btn primary" type="button" hidden>Câu tiếp theo →</button>
                </div>
            </section>
        </section>
        <section id="cc-result" class="card cc-view" aria-labelledby="cc-result-title" hidden>
            <div class="scene" aria-hidden="true"><div class="clay trophy"><b>✦</b><i></i><span></span></div><span class="spark">✦</span></div>
            <span class="cc-eyebrow">KẾT QUẢ HỌC VƯỢT</span>
            <h1 id="cc-result-title"></h1>
            <p id="cc-score"></p>
            <div class="cc-metrics">
                <div><strong id="cc-result-correct"></strong><span>Câu đúng</span></div>
                <div><strong id="cc-result-accuracy"></strong><span>Tỷ lệ đúng</span></div>
                <div><strong id="cc-result-required"></strong><span>Cần đạt</span></div>
            </div>
            <p id="cc-result-note"></p>
            <div class="cc-result-actions">
                <a class="btn primary" href="<?= $e($b) ?>/learning">Về lộ trình</a>
                <button id="cc-retry" class="btn" type="button">Thử lại</button>
            </div>
        </section>
        <p id="cc-error" role="alert"></p>
    </main>
</div>
</body>
</html>
