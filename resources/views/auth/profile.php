<?php
$guestClaimCsrf = \App\Core\Session::csrfToken();
require __DIR__ . '/_header.php';
?>

<div class="alert ok" data-welcome hidden>
    Chào mừng bạn! Hãy kiểm tra lại thông tin và điền mục tiêu học
    của bạn, sau đó bấm Lưu thay đổi.
</div>

<p class="role-line" data-role hidden></p>

<form data-form="profile" novalidate hidden>
    <input
        type="hidden"
        data-guest-csrf
        value="<?= $e($guestClaimCsrf) ?>"
    >

    <div class="alert" data-alert hidden></div>

    <label>
        Email
        <input type="email" name="email" disabled>
    </label>

    <label>
        Họ tên
        <input type="text" name="name" required>
        <small data-error="name"></small>
    </label>

    <label>
        Mục tiêu XP mỗi ngày
        <input
            type="number"
            name="daily_goal_xp"
            min="1"
            max="1000"
            required
        >
        <small data-error="daily_goal_xp"></small>
    </label>

    <label>
        Mục tiêu chuỗi ngày học (streak)
        <input
            type="number"
            name="streak_goal_days"
            min="1"
            max="365"
            required
        >
        <small data-error="streak_goal_days"></small>
    </label>

    <label>
        Múi giờ
        <select name="timezone">
            <option value="Asia/Ho_Chi_Minh">
                Việt Nam (Asia/Ho_Chi_Minh)
            </option>
            <option value="Asia/Bangkok">
                Bangkok (Asia/Bangkok)
            </option>
            <option value="Asia/Singapore">
                Singapore (Asia/Singapore)
            </option>
            <option value="Asia/Tokyo">
                Tokyo (Asia/Tokyo)
            </option>
            <option value="UTC">UTC</option>
        </select>
        <small data-error="timezone"></small>
    </label>

    <button type="submit">Lưu thay đổi</button>

    <a class="home-btn" href="<?= $e($base) ?>/">
        Tới trang chủ
    </a>
</form>

<section class="admin-box" data-admin hidden>
    <h2>Quản lý tài khoản</h2>

    <div class="alert" data-alert hidden></div>

    <label>
        ID người dùng
        <input
            type="text"
            data-admin-id
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="10"
            autocomplete="off"
        >
    </label>

    <label>
        Trạng thái
        <select data-admin-status>
            <option value="active">Hoạt động</option>
            <option value="locked">Khóa</option>
        </select>
    </label>

    <button type="button" data-admin-submit>
        Cập nhật trạng thái
    </button>
</section>

<button type="button" class="secondary" data-logout hidden>
    Đăng xuất
</button>

<?php require __DIR__ . '/_footer.php'; ?>