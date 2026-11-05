<?php
declare(strict_types=1);

$e = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$b = rtrim((string) ($basePath ?? ''), '/');

$guest = ($isGuest ?? false) === true;
$mode = (string) ($mode ?? 'lesson');
$practiceStudio = ($practiceStudio ?? false) === true;

$backUrl = $b . (
    $practiceStudio ? '/learning/practice' : '/learning'
);

$backLabel = $practiceStudio
    ? 'Chọn bài luyện tập'
    : 'Về lộ trình';

$exploreUrl = $b . '/learning/explore'
    . (
        !empty($lesson['topic_id'])
            ? '?topic=' . (int) $lesson['topic_id']
            : ''
    );

$requestedFocus = $practiceStudio
    ? 'practice'
    : ($_GET['focus'] ?? ($initialFocus ?? 'words'));

$initialFocus = is_string($requestedFocus)
    && in_array($requestedFocus, ['words', 'practice'], true)
        ? $requestedFocus
        : 'words';

$practiceFirst = $initialFocus === 'practice';

$boot = [
    'mode' => $mode,
    'guest' => $guest,
    'base' => $b,
    'csrf' => (string) ($csrfToken ?? ''),
    'words' => $words ?? [],
    'lessonId' => (int) ($lesson['id'] ?? 0),
    'sessionId' => isset($activeSession['id'])
        ? (int) $activeSession['id']
        : null,
    'ready' => !empty($ready),
    'initialFocus' => $initialFocus,
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width,initial-scale=1">

    <title>
        <?= $e($lesson['name'] ?? 'Tiếp tục học') ?> · Doulingo
    </title>

    <link rel="stylesheet"
          href="<?= $e($b) ?>/assets/css/learning-ui.css?v=10">

    <link rel="stylesheet"
          href="<?= $e($b) ?>/assets/css/learning-bright.css?v=1">

    <script src="<?= $e($b) ?>/assets/js/learning-ui.js?v=13"
            defer></script>
</head>

<body class="lu">
<div class="shell">
    <header class="top">
        <a class="brand" href="<?= $e($b) ?>/">
            Doulingo.
        </a>

        <nav aria-label="Điều hướng">
            <a href="<?= $e($b) ?>/learning">Lộ trình</a>

            <a href="<?= $e($b) ?>/learning/progress">
                Tiến độ
            </a>
        </nav>
    </header>

    <main class="player">
    <?php if ($mode === 'continue'): ?>

        <section class="card empty">
            <div class="scene" aria-hidden="true">
                <div class="clay trophy">
                    <b>✦</b>
                    <i></i>
                    <span></span>
                </div>
            </div>

            <h1>Đang lưu kết quả…</h1>

            <p id="lu-claim-note" role="status">
                Chờ một chút nhé.
            </p>

            <div class="actions" style="justify-content:center">
                <button class="btn primary"
                        type="button"
                        id="lu-claim-retry"
                        hidden>
                    Thử lại
                </button>

                <a class="btn" href="<?= $e($b) ?>/learning">
                    Về lộ trình
                </a>
            </div>
        </section>

    <?php else: ?>

        <header class="player-head">
            <a class="btn mini"
               href="<?= $e($backUrl) ?>"
               aria-label="<?= $e($backLabel) ?>">
                ←
            </a>

            <h1>
                <?= $practiceStudio ? 'Luyện tập · ' : '' ?>
                <?= $e($lesson['name'] ?? 'Bài học') ?>
            </h1>

            <span class="hearts" id="lu-hearts"></span>
        </header>

        <progress id="lu-progress"
                  max="100"
                  value="0"
                  aria-label="Tiến độ bài học"></progress>

        <p class="player-count" id="lu-count"></p>

        <section class="card"
                 id="lu-words"
                 <?= $practiceFirst ? 'hidden' : '' ?>>

            <div class="word-grid">
                <div class="word-art">
                    <img id="lu-image" alt="" hidden>
                </div>

                <div>
                    <h2 class="word-title" id="lu-word"></h2>

                    <p class="ipa" id="lu-ipa"></p>

                    <p class="meaning" id="lu-meaning"></p>

                    <button class="btn mini"
                            type="button"
                            id="lu-sound">
                        Nghe phát âm ♪
                    </button>

                    <div class="example" id="lu-example">
                        <strong>Ví dụ</strong>

                        <p id="lu-example-en"></p>

                        <small id="lu-example-vi"></small>
                    </div>
                </div>
            </div>

            <div class="word-actions">
                <button class="btn"
                        type="button"
                        id="lu-prev">
                    ← Từ trước
                </button>

                <button class="btn"
                        type="button"
                        id="lu-next-word">
                    Từ tiếp →
                </button>

                <button class="btn primary"
                        type="button"
                        id="lu-start"
                        disabled>
                    Luyện tập →
                </button>
            </div>
        </section>

        <section class="card question"
                 id="lu-question"
                 <?= $practiceFirst ? '' : 'hidden' ?>>

            <span class="kicker" id="lu-phase"><?= $practiceFirst
                ? 'Luyện tập'
                : '' ?></span>

            <h2 id="lu-instruction"><?= $practiceFirst
                ? 'Đang mở bài tập…'
                : '' ?></h2>

            <p class="prompt" id="lu-prompt"></p>

            <audio id="lu-audio"
                   controls
                   preload="none"
                   hidden></audio>

            <form id="lu-form"
                  <?= $practiceFirst ? 'hidden' : '' ?>>

                <div id="lu-controls"></div>

                <div class="actions">
                    <button class="btn primary"
                            type="submit"
                            id="lu-submit"
                            disabled>
                        Kiểm tra
                    </button>
                </div>
            </form>

            <div class="actions">
                <button class="btn primary"
                        type="button"
                        id="lu-next-question"
                        hidden>
                    Tiếp tục →
                </button>

                <button class="btn"
                        type="button"
                        id="lu-media-retry"
                        hidden>
                    Tải lại tài nguyên
                </button>
            </div>
        </section>

        <section class="card result"
                 id="lu-result"
                 hidden>

            <div class="scene" aria-hidden="true">
                <div class="clay trophy">
                    <b>✦</b>
                    <i></i>
                    <span></span>
                </div>

                <span class="spark">✦</span>
            </div>

            <h2 id="lu-result-title"></h2>

            <p class="result-score" id="lu-score"></p>

            <div class="actions">
                <button class="btn primary"
                        type="button"
                        id="lu-result-next"></button>

                <button class="btn"
                        type="button"
                        id="lu-show-words"
                        <?= $practiceStudio ? 'hidden' : '' ?>>
                    Xem từ
                </button>

                <?php if ($practiceStudio): ?>
                    <a class="btn" href="<?= $e($exploreUrl) ?>">
                        Ôn từ vựng
                    </a>
                <?php endif; ?>

                <a class="btn" href="<?= $e($backUrl) ?>">
                    <?= $e($backLabel) ?>
                </a>
            </div>
        </section>

        <p class="feedback"
           id="lu-feedback"
           role="status"
           aria-live="polite"></p>

        <noscript>
            Bật JavaScript để xem từ và trả lời câu hỏi.
        </noscript>

    <?php endif; ?>
    </main>
</div>

<?php if ($guest && $mode === 'lesson'): ?>

    <dialog id="lu-gate" aria-labelledby="lu-gate-title">
        <div class="scene" aria-hidden="true">
            <div class="clay trophy">
                <b>✦</b>
                <i></i>
                <span></span>
            </div>
        </div>

        <h2 id="lu-gate-title">
            Sẵn sàng cho ải tiếp theo?
        </h2>

        <p>Lưu kết quả để tiếp tục.</p>

        <a class="btn primary"
           href="<?= $e($b) ?>/login?next=guest">
            Đăng nhập
        </a>

        <a class="btn"
           href="<?= $e($b) ?>/register?next=guest">
            Đăng ký
        </a>

        <button class="btn quiet"
                type="button"
                data-later>
            Lúc khác
        </button>
    </dialog>

<?php endif; ?>

<script type="application/json" id="lu-data"><?= json_encode(
    $boot,
    JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR
) ?></script>

</body>
</html>