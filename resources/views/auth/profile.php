<?php require __DIR__ . '/_header.php'; ?>
<form data-form="profile" novalidate hidden>
    <div class="alert" data-alert hidden></div>
    <label>Email
        <input type="email" name="email" disabled>
    </label>
    <label>Họ tên
        <input type="text" name="name" required>
        <small data-error="name"></small>
    </label>
    <label>Mục tiêu XP mỗi ngày
        <input type="number" name="daily_goal_xp" min="1" max="1000" required>
        <small data-error="daily_goal_xp"></small>
    </label>
    <label>Mục tiêu chuỗi ngày học (streak)
        <input type="number" name="streak_goal_days" min="1" max="365" required>
        <small data-error="streak_goal_days"></small>
    </label>
    <label>Múi giờ
        <select name="timezone">
            <option value="Asia/Ho_Chi_Minh">Việt Nam (Asia/Ho_Chi_Minh)</option>
            <option value="Asia/Bangkok">Bangkok (Asia/Bangkok)</option>
            <option value="Asia/Singapore">Singapore (Asia/Singapore)</option>
            <option value="Asia/Tokyo">Tokyo (Asia/Tokyo)</option>
            <option value="UTC">UTC</option>
        </select>
        <small data-error="timezone"></small>
    </label>
    <button type="submit">Lưu thay đổi</button>
</form>
<button type="button" class="secondary" data-logout hidden>Đăng xuất</button>
<?php require __DIR__ . '/_footer.php'; ?>
