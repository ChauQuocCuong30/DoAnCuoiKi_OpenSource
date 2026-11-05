<?php declare(strict_types=1); ?>
<header class="site-header">
    <a class="brand" href="<?= $escape($appBase . '/admin/words') ?>"><span class="brand-mark" aria-hidden="true">d.</span> doulingo<span class="brand-tag">QUẢN TRỊ NỘI DUNG</span></a>
    <nav aria-label="Điều hướng quản trị">
        <a <?= $activeAdminPage === 'words' ? 'class="nav-current" aria-current="page"' : '' ?> href="<?= $escape($appBase . '/admin/words') ?>">Kệ sách</a>
        <a <?= $activeAdminPage === 'topics' ? 'class="nav-current" aria-current="page"' : '' ?> href="<?= $escape($appBase . '/admin/topics') ?>">Chủ đề</a>
        <details class="more-nav"><summary>Công cụ khác</summary><div>
            <a href="<?= $escape($appBase . '/admin/lessons') ?>">Bài học</a>
            <a href="<?= $escape($appBase . '/admin/exercises') ?>">Câu hỏi</a>
            <a href="<?= $escape($appBase . '/admin/dictionary') ?>">Tra từ điển</a>
            <a href="<?= $escape($appBase . '/learning') ?>">Trang học tập</a>
        </div></details>
    </nav>
</header>
