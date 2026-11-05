<?php require __DIR__ . '/_header.php'; ?>
<form data-form="login" novalidate>
    <div class="alert" data-alert hidden></div>
    <label>Email
        <input type="email" name="email" autocomplete="email" required>
        <small data-error="email"></small>
    </label>
    <label>Mật khẩu
        <input type="password" name="password" autocomplete="current-password" required>
        <small data-error="password"></small>
    </label>
    <button type="submit">Đăng nhập</button>
</form>
<p class="switch">Chưa có tài khoản? <a href="<?= $e($base) ?>/register">Đăng ký</a></p>
<?php require __DIR__ . '/_footer.php'; ?>
