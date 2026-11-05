<?php
declare(strict_types=1);

use App\Modules\Learning\Services\ExerciseService;

$b = rtrim((string) ($basePath ?? ''), '/');

$e = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$isGuest = (bool) ($isGuest ?? false);
$questions = $questions ?? [];

$media = static function (mixed $value) use ($b): ?string {
    $path = ExerciseService::media($value);

    if ($path === null || str_starts_with($path, 'https://')) {
        return $path;
    }

    if ($b !== '' && !str_starts_with($path, $b . '/')) {
        $path = $b . $path;
    }

    $legacy = $b . '/media/';

    return str_starts_with($path, $legacy)
        ? $b . '/assets/media/' . substr($path, strlen($legacy))
        : $path;
};

$renderAnswer = static function (
    array $items,
    string $label
) use ($media, $e): void {
    foreach ($items as $item) {
        if (array_key_exists('image', $item)) {
            $src = $media($item['image']);

            if ($src !== null) {
                echo '<img class="answer-picture"'
                    . ' src="' . $e($src) . '"'
                    . ' alt="' . $e($label) . '"'
                    . ' loading="lazy">';
            } else {
                echo '<p>Ảnh hiện không còn sẵn.</p>';
            }
        } else {
            echo '<p>' . $e($item['text'] ?? '') . '</p>';
        }
    }
};
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="robots" content="noindex">

    <title>Kết quả bài học — Doulingo</title>

    <link
        rel="stylesheet"
        href="<?= $e($b) ?>/assets/css/learning-ui.css?v=10"
    >

    <link
        rel="stylesheet"
        href="<?= $e($b) ?>/assets/css/learning-bright.css?v=12"
    >

    <link
        rel="stylesheet"
        href="<?= $e($b) ?>/assets/css/learning-result.css?v=1"
    >
</head>

<body class="lu result-page">
<div class="shell">

    <header class="top">
        <a class="brand" href="<?= $e($b) ?>/">
            <span class="brand-mark" aria-hidden="true">D</span>
            <span>Doulingo.</span>
        </a>

        <nav aria-label="Điều hướng chính">
            <a href="<?= $e($b) ?>/learning">
                Lộ trình
            </a>

            <a href="<?= $e($b) ?>/learning/progress">
                Tiến độ
            </a>

            <a href="<?= $e($b) ?>/<?= $isGuest ? 'login' : 'profile' ?>">
                <?= $isGuest ? 'Đăng nhập' : 'Tài khoản' ?>
            </a>
        </nav>
    </header>

    <main class="review-main">
        <?php if (!empty($loadError)): ?>

            <section class="review-card">
                <h1>Chưa mở được kết quả</h1>

                <p><?= $e($loadError) ?></p>

                <?php if (!empty($errorReference)): ?>
                    <p class="review-muted">
                        <?= $e($errorReference) ?>
                    </p>
                <?php endif; ?>

                <a
                    class="btn primary"
                    href="<?= $e($b) ?>/learning"
                >
                    Về lộ trình
                </a>
            </section>

        <?php else: ?>

            <header class="review-heading">
                <a
                    class="btn mini"
                    href="<?= $e($b) ?>/learning"
                    aria-label="Về lộ trình"
                >
                    ←
                </a>

                <div>
                    <p class="review-eyebrow">
                        <?= $e($topicName ?? '') ?>
                    </p>

                    <h1><?= $e($lesson['name']) ?></h1>
                </div>
            </header>

            <section
                class="review-card"
                aria-labelledby="review-title"
            >
                <img
                    class="review-trophy"
                    src="<?= $e($b) ?>/assets/images/learning-ui/trophy.png"
                    alt=""
                    width="100"
                    height="100"
                >

                <h2 id="review-title">
                    Bạn đã hoàn thành ải này!
                </h2>

                <p class="review-muted">
                    <?= $isGuest
                        ? 'Kết quả học thử'
                        : 'Lượt hoàn thành gần nhất' ?>

                    <?php if ($result['finished_label'] !== ''): ?>
                        · <?= $e($result['finished_label']) ?>
                    <?php endif; ?>
                </p>

                <div class="review-metrics <?= $isGuest ? 'guest-metrics' : '' ?>">
                    <div class="review-metric correct">
                        <strong>
                            <?= (int) $result['correct'] ?>
                        </strong>

                        <span>Câu đúng</span>
                    </div>

                    <div class="review-metric wrong">
                        <strong>
                            <?= (int) $result['wrong'] ?>
                        </strong>

                        <span>Câu sai</span>
                    </div>

                    <div class="review-metric">
                        <strong>
                            <?= (int) $result['accuracy'] ?>%
                        </strong>

                        <span>Chính xác</span>
                    </div>

                    <?php if (!$isGuest): ?>
                        <div class="review-metric xp">
                            <strong>
                                <?= (int) $result['xp'] ?>
                            </strong>

                            <span>XP đã nhận</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="review-actions">
                    <a
                        class="btn primary"
                        href="<?= $e($b) ?>/learning/lesson/<?= (int) $lesson['id'] ?>?focus=words"
                    >
                        Ôn từ vựng
                    </a>

                    <?php if ($isGuest): ?>
                        <a
                            class="btn"
                            href="<?= $e($b) ?>/login?next=guest"
                        >
                            Đăng nhập để lưu
                        </a>
                    <?php else: ?>
                        <a
                            class="btn"
                            href="<?= $e($b) ?>/learning/lesson/<?= (int) $lesson['id'] ?>?focus=practice&amp;retry=1"
                        >
                            Làm lại ải
                        </a>
                    <?php endif; ?>

                    <a
                        class="btn"
                        href="<?= $e($b) ?>/learning"
                    >
                        Về lộ trình
                    </a>
                </div>
            </section>

            <section
                class="review-questions"
                aria-labelledby="answers-title"
            >
                <h2 id="answers-title">
                    Xem lại câu trả lời
                </h2>

                <p class="review-muted review-hint">
                    Kết quả tính theo lần trả lời đầu tiên của
                    <?= (int) $result['total'] ?> câu chính.
                </p>

                <?php foreach ($questions as $question): ?>
                    <?php if (!$question['available']): ?>

                        <article class="review-question unavailable">
                            <p>
                                Câu <?= (int) $question['position'] ?>
                                · Nội dung hiện không còn hiển thị.
                            </p>
                        </article>

                    <?php else: ?>

                        <details
                            class="review-question <?= $question['correct'] === false ? 'was-wrong' : '' ?>"
                            <?= $question['correct'] === false ? 'open' : '' ?>
                        >
                            <summary>
                                <span class="review-number">
                                    <?= (int) $question['position'] ?>
                                </span>

                                <span class="review-question-title">
                                    <?= $e(
                                        $question['prompt'] !== ''
                                            ? $question['prompt']
                                            : $question['instruction']
                                    ) ?>
                                </span>

                                <span class="review-badge <?= $question['correct'] === false ? 'wrong' : '' ?>">
                                    <?= $question['correct'] === null
                                        ? 'Thiếu dữ liệu'
                                        : ($question['correct'] ? 'Đúng' : 'Sai') ?>
                                </span>
                            </summary>

                            <div class="review-question-body">
                                <p class="review-muted">
                                    <?= $e($question['instruction']) ?>
                                </p>

                                <?php
                                $audio = $media($question['audio']);
                                ?>

                                <?php if ($audio !== null): ?>
                                    <audio
                                        controls
                                        preload="none"
                                        src="<?= $e($audio) ?>"
                                        aria-label="Nghe lại câu hỏi"
                                    ></audio>
                                <?php endif; ?>

                                <div class="review-answer-grid">
                                    <div class="review-answer">
                                        <h3>Bạn đã trả lời</h3>

                                        <?php
                                        $renderAnswer(
                                            $question['given'],
                                            'Ảnh bạn đã chọn'
                                        );
                                        ?>
                                    </div>

                                    <div class="review-answer expected">
                                        <h3>Đáp án</h3>

                                        <?php
                                        $renderAnswer(
                                            $question['expected'],
                                            'Ảnh đáp án đúng'
                                        );
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </details>

                    <?php endif; ?>
                <?php endforeach; ?>
            </section>

        <?php endif; ?>
    </main>

</div>
</body>
</html>