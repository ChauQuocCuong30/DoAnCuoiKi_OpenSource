<?php
declare(strict_types=1);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageLink = static fn (int $page): string => $appBase . '/admin/topics?' . http_build_query(['q' => $search, 'page' => $page]);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý chủ đề</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f5f7fa;color:#223347;font:16px system-ui,sans-serif}
        main{max-width:1000px;margin:auto;padding:24px 16px}section{background:white;border:1px solid #d8e0e9;border-radius:12px;padding:20px;margin:20px 0}
        label{display:block;margin:12px 0}input,textarea,select,button{font:inherit;padding:10px;border-radius:6px}
        input,textarea,select{width:100%;border:1px solid #b9c5d4}textarea{min-height:90px}
        button{border:0;background:#16764c;color:white;cursor:pointer}a{color:#1266ac}.error{color:#ad2525}.success{color:#16764c}
        .table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #ddd;padding:12px;overflow-wrap:anywhere}
        nav{display:flex;gap:18px;flex-wrap:wrap;margin:16px 0}small{color:#617185}
    </style>
</head>
<body>
<main>
    <h1>Quản lý chủ đề</h1>
    <nav>
        <a href="<?= $escape($appBase . '/admin/dictionary') ?>">Tra và thêm từ</a>
        <a href="<?= $escape($appBase . '/admin/topics') ?>">Tạo chủ đề mới</a>
    </nav>
    <?php if ($notice !== ''): ?><p class="success" role="status"><?= $escape($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <section>
        <h2><?= $form['id'] === '' ? 'Thêm chủ đề' : 'Sửa chủ đề #' . $escape($form['id']) ?></h2>
        <form method="post" action="<?= $escape($appBase . '/admin/topics') ?>">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
            <input type="hidden" name="id" value="<?= $escape($form['id']) ?>">
            <label>Tên chủ đề<input name="name" maxlength="150" value="<?= $escape($form['name']) ?>" required></label>
            <label>Mô tả<textarea name="description" maxlength="5000"><?= $escape($form['description']) ?></textarea></label>
            <label>URL ảnh HTTPS — tùy chọn<input name="image" type="url" maxlength="255" value="<?= $escape($form['image']) ?>"></label>
            <label>Thứ tự<input name="position" type="number" min="1" max="2147483647" step="1" value="<?= $escape($form['position']) ?>" required></label>
            <small>Thứ tự phải khác chủ đề hiện có. Muốn đổi chỗ, dùng một thứ tự trống làm bước trung gian.</small>
            <label>Trạng thái
                <select name="is_active">
                    <option value="1" <?= (string) $form['is_active'] === '1' ? 'selected' : '' ?>>Hiện</option>
                    <option value="0" <?= (string) $form['is_active'] === '0' ? 'selected' : '' ?>>Ẩn</option>
                </select>
            </label>
            <button type="submit">Lưu chủ đề</button>
        </form>
        <p><small>Ẩn chủ đề giữ nguyên từ vựng, bài học và lịch sử học đã liên kết.</small></p>
    </section>
    <section>
        <h2>Danh sách chủ đề</h2>
        <form method="get" action="<?= $escape($appBase . '/admin/topics') ?>">
            <label>Tìm theo tên<input name="q" maxlength="100" value="<?= $escape($search) ?>"></label>
            <button type="submit">Tìm kiếm</button>
        </form>
        <p>Tổng: <?= $escape($listing['total']) ?> chủ đề — Trang <?= $escape($listing['page']) ?>/<?= $escape($listing['pages']) ?></p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Tên</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($listing['items'] as $topic): ?>
                    <tr>
                        <td><?= $escape($topic['id']) ?></td>
                        <td><?= $escape($topic['name']) ?></td>
                        <td><?= $escape($topic['position']) ?></td>
                        <td><?= (int) $topic['is_active'] === 1 ? 'Hiện' : 'Ẩn' ?></td>
                        <td><a href="<?= $escape($appBase . '/admin/topics?edit=' . $topic['id']) ?>">Sửa / Ẩn / Hiện</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($listing['items'] === []): ?><tr><td colspan="5">Không có chủ đề phù hợp.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav>
            <?php if ($listing['page'] > 1): ?><a href="<?= $escape($pageLink($listing['page'] - 1)) ?>">Trang trước</a><?php endif; ?>
            <?php if ($listing['page'] < $listing['pages']): ?><a href="<?= $escape($pageLink($listing['page'] + 1)) ?>">Trang sau</a><?php endif; ?>
        </nav>
    </section>
</main>
</body>
</html>
