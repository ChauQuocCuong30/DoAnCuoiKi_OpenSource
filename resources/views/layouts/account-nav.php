<?php
declare(strict_types=1);
header('Cache-Control: no-store');
$navBase = $base ?? $basePath ?? $reviewApiBase ?? '';
$navUser = null;
if (\App\Core\Session::userId() !== null) {
    try { $navUser = \App\Middleware\Auth::user(); }
    catch (\App\Core\ApiException $exception) {
        if (!in_array($exception->status, [401, 403], true)) { throw $exception; }
    }
}
$navEscape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $navEscape($navBase) ?>/assets/css/account-nav.css?v=2">
<nav class="account-navigation" aria-label="Tài khoản">
    <details>
        <summary><?= $navUser === null ? 'Tài khoản' : $navEscape($navUser['name']) ?></summary>
        <div class="account-navigation-menu">
            <?php if ($navUser !== null): ?>
                <?php if ($navUser['role'] === 'admin'): ?>
                    <a href="<?= $navEscape($navBase) ?>/admin/words">Về trang quản trị</a>
                <?php endif; ?>
                <a href="<?= $navEscape($navBase) ?>/profile">Hồ sơ của tôi</a>
                <a href="<?= $navEscape($navBase) ?>/learning">Lộ trình học</a>
                <a href="<?= $navEscape($navBase) ?>/reviews">Ôn tập SM-2</a>
            <?php else: ?>
                <a href="<?= $navEscape($navBase) ?>/login">Đăng nhập</a>
                <a href="<?= $navEscape($navBase) ?>/register">Đăng ký</a>
            <?php endif; ?>
            <a href="<?= $navEscape($navBase) ?>/welcome">Trang chủ</a>
        </div>
    </details>
</nav>
