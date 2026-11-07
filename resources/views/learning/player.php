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
    'heartRecovery' => !$guest && \App\Shared\Cefr::installed(\App\Core\Database::connection()),
    'initialFocus' => $initialFocus,
    'onboarding' => ($_GET['onboarding'] ?? '') === '1',
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
          href="<?= $e($b) ?>/assets/css/learning-ui.css?v=12">

    <link rel="stylesheet" href="<?= $e($b) ?>/assets/css/learning-interactions.css?v=3">
    <script defer src="<?= $e($b) ?>/assets/js/learning-media.js?v=1"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-audio.js?v=2"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-question-ui.js?v=3"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-feedback-sound.js?v=1"></script>
    <script defer src="<?= $e($b) ?>/assets/js/learning-ui.js?v=17"></script>
<?php require dirname(__DIR__) . '/layouts/theme-head.php'; ?><link rel="stylesheet" href="<?= $e($b) ?>/assets/css/chapter-path.css?v=1"></head>

<body class="lu dq-lesson">
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
            <?php if (!$guest): ?><a href="<?= $e($b) ?>/learning/hearts">Ôn từ sai · Hồi tim</a><?php endif; ?>
        </nav>
        <div class="theme-account-actions">
            <?php require dirname(__DIR__) . "/layouts/theme-toggle.php"; ?>
            <a class="theme-account-link" href="<?= $e($b) ?>/profile">Tài khoản</a>
        </div>
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

            <a class="hearts" id="lu-hearts" <?= $guest ? 'aria-label="Tim học thử"' : 'href="' . $e($b) . '/learning/hearts" aria-label="Xem tim và ôn từ sai"' ?>></a>
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

            <h2 tabindex="-1" id="lu-instruction"><?= $practiceFirst
                ? 'Đang mở bài tập…'
                : '' ?></h2>

            <div class="dq-prompt-row"><img src="<?= $e($b) ?>/assets/images/learning-ui/notebook.png" width="88" height="88" alt="">
                <div class="dq-speech-bubble"><button type="button" id="lu-question-sound" class="dq-speaker" aria-label="Nghe lại tiếng Anh" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15 8a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/></svg></button><button type="button" id="lu-question-slow" class="dq-speaker dq-speaker-slow" aria-label="Nghe chậm tiếng Anh" hidden>0.75×</button><p class="prompt" id="lu-prompt"></p></div>
            </div><p id="lu-audio-note" class="dq-audio-note" role="status"></p>

            <audio id="lu-audio"
                   controls
                   preload="none"
                   hidden></audio>

            <form id="lu-form"
                  <?= $practiceFirst ? 'hidden' : '' ?>>

                <div id="lu-controls"></div>

                <div class="actions">
                <button class="btn" type="button" id="lu-use-text" hidden>Làm bằng chữ</button>
                <button class="btn"
                        type="button"
                        id="lu-media-retry"
                        hidden>
                    Tải lại tài nguyên
                </button>
                    <button class="btn primary"
                            type="submit"
                            id="lu-submit"
                            disabled>
                        Kiểm tra
                    </button>
                </div>
            </form>

            <div class="actions dq-next-actions">
                <div id="lu-answer-feedback" class="dq-feedback" role="status" aria-live="polite" hidden></div>
                <button class="btn primary"
                        type="button"
                        id="lu-next-question"
                        hidden>
                    Tiếp tục →
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
            <div class="dq-result-stats"><div><span>ĐIỂM KINH NGHIỆM</span><strong id="lu-result-xp"></strong></div><div><span>TỶ LỆ ĐÚNG</span><strong id="lu-result-accuracy"></strong></div></div>
            <p id="lu-result-streak"></p>

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

                <?php if (!$guest): ?>
                    <a class="btn" href="<?= $e($b) ?>/learning/hearts">Ôn từ sai để hồi tim</a>
                    <a class="btn" href="<?= $e($b) ?>/reviews">
                        Ôn tập theo lịch
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
