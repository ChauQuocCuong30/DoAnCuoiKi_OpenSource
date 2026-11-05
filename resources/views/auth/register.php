<?php require __DIR__ . '/_header.php'; ?>
<form data-form="register" novalidate>
    <div class="alert" data-alert hidden></div>
    <label>Họ tên
        <input type="text" name="name" autocomplete="name" required>
        <small data-error="name"></small>
    </label>
    <label>Email
        <input type="email" name="email" autocomplete="email" required>
        <small data-error="email"></small>
    </label>
    <label>Mật khẩu
        <input type="password" name="password" autocomplete="new-password" required>
        <small data-error="password"></small>
    </label>
    <button type="submit">Tạo tài khoản</button>
</form>
<p class="switch">Đã có tài khoản? <a href="<?= $e($base) ?>/login">Đăng nhập</a></p>
<?php require __DIR__ . '/_footer.php'; ?>
