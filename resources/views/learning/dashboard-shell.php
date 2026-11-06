<?php
declare(strict_types=1);

use App\Core\Session;

$b = rtrim(
    (string) (
        $basePath
        ?? str_replace(
            '\\',
            '/',
            dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')
        )
    ),
    '/'
);

$e = static fn (mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$isGuest = (bool) (
    $isGuest ?? (Session::userId() === null)
);

$navActive = $navActive ?? 'home';
$pageTitle = $pageTitle ?? 'Doulingo';

$navTitles = [
    'home' => 'Tổng quan',
    'path' => 'Lộ trình',
    'progress' => 'Tiến độ',
    'explore' => 'Khám phá từ mới',
    'practice' => 'Luyện tập',
];

$navItems = [
    ['home', '/', 'home', 'Trang chủ'],
    ['path', '/learning', 'map', 'Lộ trình học'],
    ['progress', '/learning/progress', 'chart', 'Tiến độ'],
    ['placement', '/placement', 'chart', 'Kiểm tra trình độ demo'],
    ...($isGuest ? [] : [['hearts', '/learning/hearts', 'heart', 'Ôn từ sai · Hồi tim']]),
];

// Render admin navigation only for the current server-verified administrator.
header('Cache-Control: no-store');
if (Session::userId() !== null) {
    try {
        $account = \App\Middleware\Auth::user();
        if ($account['role'] === 'admin') {
            $navItems[] = ['admin', '/admin/words', 'book', 'Trang quản trị'];
        }
    } catch (\App\Core\ApiException $error) {
        if (!in_array($error->status, [401, 403], true)) { throw $error; }
        $isGuest = true;
    }
}

$icon = static function (string $name): string {
    $paths = [
        'heart' => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z',
        'home' =>
            'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
        'map' =>
            'm3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16',
        'chart' =>
            'M4 3v17h17M8 15v-4m5 4V7m5 8V4',
        'book' =>
            'M12 5c-3-2-6-2-9-1v15c3-1 6-1 9 1m0-15c3-2 6-2 9-1v15c-3-1-6-1-9 1Zm0 0v15',
        'headphones' =>
            'M4 14v-3a8 8 0 0 1 16 0v3M4 12H3v7h4v-7Zm16 0h1v7h-4v-7Z',
        'trophy' =>
            'M8 3h8v6a4 4 0 0 1-8 0ZM8 5H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4m-4 1v5m-4 3h8m-6-3h4',
        'arrow' =>
            'M4 12h15m-6-6 6 6-6 6',
        'back' =>
            'M20 12H5m7-7-7 7 7 7',
        'check' =>
            'm5 12 4 4L19 6',
        'lock' =>
            'M7 10V7a5 5 0 0 1 10 0v3M5 10h14v11H5Zm7 4v3',
        'user' =>
            'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2',
        'bolt' =>
            'm13 2-9 12h7l-1 8 10-13h-7Z',
        'flame' =>
            'M13 3c1 5-5 5-4 10 2-1 3-2 4-4 4 3 6 5 6 8a7 7 0 0 1-14 0c0-5 3-8 8-14Z',
        'image' =>
            'M3 3h18v18H3ZM3 17l6-6 4 4 3-3 5 5M8 7h.01',
        'target' =>
            'M21 12a9 9 0 1 1-9-9m5 9a5 5 0 1 1-5-5m0 5 9-9m-4 0h4v4',
        'close' =>
            'm6 6 12 12M18 6 6 18',
    ];

    return '<svg class="ld-icon" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="1.8"'
        . ' stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true"><path d="'
        . ($paths[$name] ?? $paths['book'])
        . '"/></svg>';
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

    <meta
        name="description"
        content="Học từ vựng qua flashcard, hình ảnh và âm thanh cùng Doulingo."
    >

    <title><?= $e($pageTitle) ?></title>

    <link
        rel="stylesheet"
        href="<?= $e($b) ?>/assets/css/learning-dashboard.css?v=2"
    >

    <style>
        .ld .ld-shortcuts {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ld .ld-sidebar-foot {
            margin-top: auto;
        }

        .ld .ld-back-row {
            display: flex;
            margin-bottom: 18px;
        }

        .ld .ld-back-link {
            min-height: 44px;
            padding: 10px 16px;
            gap: 8px;
        }

        @media (max-width: 680px) {
            .ld .ld-shortcuts {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script
        src="<?= $e($b) ?>/assets/js/learning-ui.js?v=14"
        defer
    ></script>
<?php require dirname(__DIR__) . '/layouts/theme-head.php'; ?></head>

<body class="ld">
<a class="ld-skip" href="#main-content">
    Đến nội dung chính
</a>

<div class="ld-app">
    <aside class="ld-sidebar">
        <a
            class="ld-brand"
            href="<?= $e($b) ?>/"
            aria-label="Doulingo — Trang chủ"
        >
            <span class="ld-mark">D</span>

            <span>
                Doulingo<span class="ld-brand-dot">.</span>
            </span>
        </a>

        <p class="ld-nav-label">
            KHÔNG GIAN CỦA BẠN
        </p>

        <nav class="ld-nav" aria-label="Điều hướng chính">
            <?php foreach (
                $navItems as [$key, $url, $glyph, $label]
            ): ?>
                <a
                    class="<?= $navActive === $key
                        ? 'is-active'
                        : '' ?>"
                    href="<?= $e($b . $url) ?>"
                    <?= $navActive === $key
                        ? 'aria-current="page"'
                        : '' ?>
                >
                    <?= $icon($glyph) ?>

                    <span><?= $e($label) ?></span>

                    <?php if ($navActive === $key): ?>
                        <i></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <small class="ld-sidebar-foot">
            Doulingo · Học theo nhịp của bạn
        </small>
    </aside>

    <div class="ld-workspace">
        <header class="ld-topbar">
            <a
                class="ld-mobile-brand"
                href="<?= $e($b) ?>/"
            >
                <span class="ld-mark">D</span>
                <strong>Doulingo.</strong>
            </a>

            <div class="ld-breadcrumb">
                <span>Không gian học tập</span>
                <span>/</span>

                <strong>
                    <?= $e($navTitles[$navActive] ?? 'Học tập') ?>
                </strong>
            </div>

            <div class="theme-account-actions">
            <?php require dirname(__DIR__) . "/layouts/theme-toggle.php"; ?>
            <a
                class="ld-account"
                href="<?= $e($b) ?><?= $isGuest ? '/login' : '/profile' ?>"
            >
                <span><?= $icon('user') ?></span>

                <?= $isGuest ? 'Đăng nhập' : 'Tài khoản' ?>
            </a>
            </div>
        </header>

        <main class="ld-content" id="main-content">
            <?php if (
                in_array(
                    $navActive,
                    ['explore', 'practice'],
                    true
                )
            ): ?>
                <div class="ld-back-row">
                    <a
                        class="ld-btn ld-btn-outline ld-back-link"
                        href="<?= $e($b) ?>/"
                        data-studio-back
                    >
                        <?= $icon('back') ?>
                        Quay về
                    </a>
                </div>

                <script>
                (() => {
                    'use strict';

                    const link = document.querySelector(
                        '[data-studio-back]'
                    );

                    if (!link || !document.referrer) {
                        return;
                    }

                    let previous;
                    let root;

                    try {
                        previous = new URL(document.referrer);
                        root = new URL(link.href);
                    } catch {
                        return;
                    }

                    if (previous.origin !== root.origin) {
                        return;
                    }

                    const base = root.pathname.replace(/\/+$/, '');

                    if (
                        base
                        && previous.pathname !== base
                        && !previous.pathname.startsWith(base + '/')
                    ) {
                        return;
                    }

                    const path = previous.pathname.slice(
                        base.length
                    ) || '/';

                    if (
                        path !== '/'
                        && path !== '/reviews'
                        && !/^\/learning(?:\/|$)/.test(path)
                    ) {
                        return;
                    }

                    if (
                        /^\/learning\/(?:continue|launch)(?:\/|$)/
                            .test(path)
                    ) {
                        return;
                    }

                    if (previous.href === window.location.href) {
                        return;
                    }

                    link.href = previous.href;
                })();
                </script>
            <?php endif; ?>