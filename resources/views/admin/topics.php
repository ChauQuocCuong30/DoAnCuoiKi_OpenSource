<?php
declare(strict_types=1);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageLink = static fn (int $page): string => $appBase . '/admin/topics?' . http_build_query(['q' => $search, 'page' => $page]);
$topicCode = static fn ($id): string => sprintf('CD%06d', (int) $id);
$editing = preg_match('/^[1-9][0-9]*$/D', (string) $form['id']) === 1;
$activeAdminPage = 'topics';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#eee7d8">
    <title>Quản lý chủ đề · Doulingo Admin</title>
    <link rel="stylesheet" href="<?= $escape($appBase . '/assets/css/admin-books.css') ?>">
    <link rel="stylesheet" href="<?= $escape($appBase . '/assets/css/admin-topics.css') ?>">
</head>
<body class="topics-page">
<?php require __DIR__ . '/_header.php'; ?>
<main>
    <a class="back-link" href="<?= $escape($appBase . '/admin/words') ?>">← Trở lại kệ sách</a>
    <header class="topics-heading">
        <div><p class="eyebrow">THƯ VIỆN / QUẢN LÝ CHỦ ĐỀ</p><h1>Chủ đề nhỏ,<br>một <em>thế giới lớn.</em></h1><p class="hero-copy">Tạo chủ đề để bắt đầu một cuốn sách. Mã và vị trí trên kệ sẽ được sắp xếp tự động.</p></div>
        <div class="topics-count"><strong><?= $escape($listing['total']) ?></strong><span><?= $search === '' ? 'chủ đề trong thư viện' : 'chủ đề phù hợp' ?></span></div>
    </header>
    <?php if ($notice !== ''): ?><p class="notice" role="status"><?= $escape($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice is-error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <div class="topics-workspace">
        <section class="paper-panel topic-editor" aria-labelledby="topic-form-title">
            <div class="panel-heading"><div><p class="eyebrow"><?= $editing ? 'CHĂM CHÚT CUỐN SÁCH' : 'THÊM MỘT CUỐN SÁCH' ?></p><h2 id="topic-form-title"><?= $editing ? 'Chỉnh sửa chủ đề' : 'Tạo chủ đề mới' ?></h2></div><span class="badge">Mã tự động</span></div>
            <form id="topic-form" method="post" action="<?= $escape($appBase . '/admin/topics') ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                <input type="hidden" name="id" value="<?= $escape($form['id']) ?>">
                <div class="topic-identity"><div><span>Mã chủ đề</span><output id="topic-code"><?= $editing ? $escape($topicCode($form['id'])) : 'Tự động khi lưu' ?></output></div><div><span>Vị trí trên kệ</span><output id="topic-position"><?= $editing && $form['position'] !== '' ? 'Tập ' . $escape(str_pad((string) $form['position'], 2, '0', STR_PAD_LEFT)) : 'Tự xếp cuối kệ' ?></output></div></div>
                <label for="topic-name">Tên chủ đề<input id="topic-name" name="name" maxlength="150" value="<?= $escape($form['name']) ?>" placeholder="Ví dụ: Thế giới hoang dã" required></label>
                <label for="topic-description">Mô tả <span class="field-optional">· tùy chọn</span><textarea id="topic-description" name="description" maxlength="5000" placeholder="Những điều người học sẽ khám phá trong cuốn sách này…"><?= $escape($form['description']) ?></textarea></label>
                <div class="form-grid"><label for="topic-image">Ảnh chủ đề <span class="field-optional">· tùy chọn</span><input id="topic-image" name="image" type="url" maxlength="255" value="<?= $escape($form['image']) ?>" placeholder="https://…"><small class="muted">Dùng đường dẫn ảnh HTTPS.</small></label><label for="topic-status">Trạng thái<select id="topic-status" name="is_active"><option value="1" <?= (string) $form['is_active'] === '1' ? 'selected' : '' ?>>Hiện</option><option value="0" <?= (string) $form['is_active'] === '0' ? 'selected' : '' ?>>Ẩn</option></select></label></div>
                <p class="topic-help">Chủ đề mới được đặt ở cuối kệ. Khi chỉnh sửa, mã và vị trí hiện tại được giữ nguyên.</p>
                <div class="topic-form-actions"><button class="button-primary" type="submit"><?= $editing ? 'Lưu thay đổi' : 'Tạo chủ đề' ?> →</button><?php if ($editing): ?><a class="text-link" href="<?= $escape($appBase . '/admin/topics') ?>">Tạo chủ đề khác</a><?php endif; ?></div>
            </form>
        </section>
        <aside class="topic-aside">
            <p class="eyebrow">MỖI CHỦ ĐỀ LÀ MỘT CUỐN SÁCH</p>
            <div class="topic-book-preview" aria-hidden="true"><span class="book-edition">DOULINGO / THE WORD COLLECTION</span><span class="book-ornament">✳</span><h3><?= $escape($form['name'] !== '' ? $form['name'] : 'Những trang sách mới') ?></h3><span class="book-cover-footer"><?= $editing ? $escape($topicCode($form['id'])) : 'MỘT CHỦ ĐỀ. NHIỀU KHÁM PHÁ.' ?></span></div>
            <p class="muted">Ẩn chủ đề giữ nguyên từ vựng, bài học và lịch sử học đã liên kết.</p>
            <?php if ($editing): ?><a class="text-link" href="<?= $escape($appBase . '/admin/words?topic_id=' . $form['id']) ?>">Mở cuốn sách này ↗</a><?php endif; ?>
        </aside>
    </div>
    <section id="topic-list" class="paper-panel topic-list" aria-labelledby="topic-list-title">
        <div class="panel-heading"><div><p class="eyebrow">BỘ SƯU TẬP</p><h2 id="topic-list-title">Các chủ đề của bạn</h2></div><a class="text-link" href="<?= $escape($appBase . '/admin/topics') ?>">＋ Tạo chủ đề mới</a></div>
        <form class="topics-search" method="get" action="<?= $escape($appBase . '/admin/topics') ?>"><label class="sr-only" for="topic-search">Tìm theo tên chủ đề</label><input id="topic-search" name="q" type="search" maxlength="100" value="<?= $escape($search) ?>" placeholder="Tìm theo tên chủ đề…"><button class="button-secondary" type="submit">Tìm kiếm</button><?php if ($search !== ''): ?><a class="text-link" href="<?= $escape($appBase . '/admin/topics') ?>">Xóa bộ lọc</a><?php endif; ?><span class="muted"><?= $escape($listing['total']) ?> chủ đề</span></form>
        <div class="table-wrap"><table><thead><tr><th scope="col">Mã chủ đề</th><th scope="col">Tên chủ đề</th><th scope="col">Vị trí trên kệ</th><th scope="col">Trạng thái</th><th scope="col">Quản lý</th></tr></thead><tbody>
        <?php foreach ($listing['items'] as $topic): ?>
            <tr data-topic-id="<?= $escape($topic['id']) ?>"><td><span class="topic-code"><?= $escape($topicCode($topic['id'])) ?></span></td><td class="topic-name-cell"><?= $escape($topic['name']) ?></td><td>Tập <?= $escape(str_pad((string) $topic['position'], 2, '0', STR_PAD_LEFT)) ?></td><td><span class="badge<?= (int) $topic['is_active'] === 1 ? ' active' : '' ?>"><?= (int) $topic['is_active'] === 1 ? 'Hiện' : 'Ẩn' ?></span></td><td><div class="topic-row-actions"><a class="button-secondary" href="<?= $escape($appBase . '/admin/topics?edit=' . $topic['id']) ?>" aria-label="Sửa chủ đề <?= $escape($topic['name']) ?>">Sửa</a><a class="text-link" href="<?= $escape($appBase . '/admin/words?topic_id=' . $topic['id']) ?>">Mở sách ↗</a></div></td></tr>
        <?php endforeach; ?>
        <?php if ($listing['items'] === []): ?><tr><td colspan="5" class="topics-empty"><?= $search === '' ? 'Chưa có chủ đề. Tạo cuốn sách đầu tiên ở biểu mẫu phía trên.' : 'Không có chủ đề phù hợp.' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <nav class="pagination" aria-label="Phân trang chủ đề"><div><?php if ($listing['page'] > 1): ?><a class="button-secondary" href="<?= $escape($pageLink($listing['page'] - 1)) ?>">← Trang trước</a><?php endif; ?></div><span>Trang <?= $escape($listing['page']) ?> / <?= $escape($listing['pages']) ?></span><div><?php if ($listing['page'] < $listing['pages']): ?><a class="button-secondary" href="<?= $escape($pageLink($listing['page'] + 1)) ?>">Trang sau →</a><?php endif; ?></div></nav>
    </section>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
</body>
</html>
