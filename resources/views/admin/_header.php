<?php
declare(strict_types=1);
?>
<header class="site-header">
    <a class="brand" href="<?= $escape($appBase . '/admin/words') ?>"><span class="brand-mark" aria-hidden="true">d.</span> doulingo<span class="brand-tag">QUẢN TRỊ NỘI DUNG</span></a>
    <nav aria-label="Điều hướng quản trị">
        <?php foreach (['words'=>'Kệ sách','topics'=>'Chủ đề','learners'=>'Học viên'] as $adminPath=>$adminLabel): ?>
        <a <?= $activeAdminPage === $adminPath ? 'class="nav-current" aria-current="page"' : '' ?> href="<?= $escape($appBase . '/admin/' . $adminPath) ?>"><?= $escape($adminLabel) ?></a>
        <?php endforeach; ?>
        <details class="more-nav"><summary>Tài khoản</summary><div>
            <a href="<?= $escape($appBase . '/profile') ?>">Hồ sơ của tôi</a>
            <a href="<?= $escape($appBase . '/learning') ?>">Học thử</a>
            <a href="<?= $escape($appBase . '/welcome') ?>">Trang chủ</a>
        </div></details>
    </nav>
</header>
