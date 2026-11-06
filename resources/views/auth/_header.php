<?php
/** @var string $title */
/** @var string $base */

$e = static fn (string $v): string => htmlspecialchars(
    $v,
    ENT_QUOTES,
    'UTF-8'
);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= $e($title) ?> - Doulingo</title>

    <link
        rel="stylesheet"
        href="<?= $e($base) ?>/assets/css/auth.css"
    >

    <link
        rel="stylesheet"
        href="<?= $e($base) ?>/assets/css/transition.css"
    >

    <script src="<?= $e($base) ?>/assets/js/transition.js"></script>
</head>

<body data-base="<?= $e($base) ?>">
<div class="auth-layout">
    <aside class="brand-panel">
        <div class="logo">
            <svg
                width="40"
                height="40"
                viewBox="0 0 40 40"
                aria-hidden="true"
            >
                <rect
                    width="40"
                    height="40"
                    rx="11"
                    class="lg-bg"
                />

                <path
                    d="M13 10h8a10 10 0 0 1 0 20h-8z"
                    fill="none"
                    class="lg-d"
                    stroke-width="4"
                    stroke-linejoin="round"
                />
            </svg>

            <span>Doulingo</span>
        </div>

        <h2>Học từ vựng mỗi ngày, nhớ lâu hơn.</h2>

        <ul>
            <li>Flashcard có hình, nghĩa, ví dụ và phát âm</li>
            <li>Ôn tập đúng lúc theo lịch cá nhân</li>
            <li>Theo dõi XP và chuỗi ngày học</li>
        </ul>

        <div class="scene" aria-hidden="true">
            <div class="stage">
                <div class="cube">
                    <i>D</i>
                    <i>D</i>
                    <i>D</i>
                    <i>D</i>
                    <i>D</i>
                    <i>D</i>
                </div>

                <div
                    class="flash"
                    style="--x:-95px;--y:-40px;--z:20px;--r:-8deg;--d:0s"
                >
                    <div class="flip">
                        <div class="face front">apple</div>
                        <div class="face back">quả táo</div>
                    </div>
                </div>

                <div
                    class="flash"
                    style="--x:70px;--y:10px;--z:70px;--r:6deg;--d:-2s"
                >
                    <div class="flip">
                        <div class="face front">travel</div>
                        <div class="face back">du lịch</div>
                    </div>
                </div>

                <div
                    class="flash"
                    style="--x:-30px;--y:75px;--z:-20px;--r:-3deg;--d:-4s"
                >
                    <div class="flip">
                        <div class="face front">friend</div>
                        <div class="face back">bạn bè</div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <main class="auth-card">
        <?php if (
            in_array(
                $view ?? '',
                ['login', 'register'],
                true
            )
        ): ?>
            <a
                class="back-welcome"
                href="<?= $e($base) ?>/learning"
            >
                ← Quay về
            </a>
        <?php endif; ?>

        <div class="logo logo-mobile">
            <svg
                width="32"
                height="32"
                viewBox="0 0 40 40"
                aria-hidden="true"
            >
                <rect
                    width="40"
                    height="40"
                    rx="11"
                    class="lg-bg"
                />

                <path
                    d="M13 10h8a10 10 0 0 1 0 20h-8z"
                    fill="none"
                    class="lg-d"
                    stroke-width="4"
                    stroke-linejoin="round"
                />
            </svg>

            <span>Doulingo</span>
        </div>

        <h1><?= $e($title) ?></h1>