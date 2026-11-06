        </main>
    </div>
</div>

<nav class="ld-bottom-nav" aria-label="Điều hướng trên điện thoại">
    <?php foreach ($navItems as [$key, $url, $glyph, $label]): ?>
        <a
            class="<?= $navActive === $key ? 'is-active' : '' ?>"
            href="<?= $e($b . $url) ?>"
            <?= $navActive === $key ? 'aria-current="page"' : '' ?>
        >
            <?= $icon($glyph) ?>
            <span><?= $e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<dialog
    id="lu-gate"
    class="ld-auth-dialog"
    aria-labelledby="ld-gate-title"
>
    <button
        class="ld-dialog-close"
        type="button"
        data-later
        aria-label="Đóng"
    >
        <?= $icon('close') ?>
    </button>

    <img
        src="<?= $e($b) ?>/assets/images/learning-ui/trophy.png"
        alt=""
        width="94"
        height="94"
    >

    <h2 id="ld-gate-title">
        Giữ lại bước tiến này nhé!
    </h2>

    <p>Đăng nhập để lưu kết quả và mở ải tiếp theo.</p>

    <div class="ld-dialog-actions">
        <a
            class="ld-btn ld-btn-green"
            href="<?= $e($b) ?>/login?next=guest"
        >
            Đăng nhập
        </a>

        <a
            class="ld-btn ld-btn-outline"
            href="<?= $e($b) ?>/register?next=guest"
        >
            Tạo tài khoản
        </a>

        <button
            class="ld-btn ld-btn-text"
            type="button"
            data-later
        >
            Lúc khác
        </button>
    </div>
</dialog>

<div
    id="lu-toast"
    class="ld-toast"
    role="status"
    aria-live="polite"
    hidden
></div>

<script id="lu-data" type="application/json"><?= json_encode([
    'mode' => 'map',
    'guest' => $isGuest,
    'pendingCompleted' => (bool) ($guestCompleted ?? false),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>

</body>
</html>