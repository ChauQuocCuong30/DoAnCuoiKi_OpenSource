<?php
declare(strict_types=1);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$activeAdminPage = 'learners';
$labels = ['active' => 'Hoạt động', 'locked' => 'Đã khóa', 'deactivated' => 'Đã ngừng'];
$pageLink = static fn (int $number): string => $appBase . '/admin/learners?' . http_build_query(['q'=>$search,'status'=>$status,'page'=>$number]);
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tài khoản học viên · Doulingo</title>
<?php require __DIR__ . '/_styles.php'; ?></head><body class="admin-tools-page">
<?php require __DIR__ . '/_header.php'; ?>
<main><header class="admin-page-heading"><p class="eyebrow">QUẢN TRỊ / HỌC VIÊN</p><h1>Tài khoản học viên</h1><p class="hero-copy">Tìm học viên, khóa hoặc mở lại tài khoản. Mã tài khoản được hệ thống cấp tự động khi đăng ký.</p></header>
<?php if ($notice !== ''): ?><p class="notice" role="status"><?= $escape($notice) ?></p><?php endif; ?>
<section class="paper-panel"><form class="learner-filter" method="get"><label>Tên hoặc email<input type="search" name="q" maxlength="100" value="<?= $escape($search) ?>"></label><label>Trạng thái<select name="status"><option value="">Tất cả</option><?php foreach ($labels as $key=>$label): ?><option value="<?= $key ?>" <?= $status===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="button-primary">Tìm học viên</button></form>
<p class="muted"><?= $total ?> học viên</p><div class="table-wrap"><table><thead><tr><th>Mã tự động</th><th>Họ tên</th><th>Email</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($learners as $learner): ?><tr><td><?= $escape(sprintf('HV%06d', (int)$learner['id'])) ?></td><td><?= $escape($learner['name']) ?></td><td><?= $escape($learner['email']) ?></td><td><?= $escape($labels[$learner['status']] ?? $learner['status']) ?></td><td><?php if (in_array($learner['status'], ['active','locked'], true)): ?><form method="post" action="<?= $escape($appBase . '/admin/learners/' . $learner['id'] . '/status') ?>"><input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>"><input type="hidden" name="status" value="<?= $learner['status'] === 'active' ? 'locked' : 'active' ?>"><button class="button-secondary"><?= $learner['status'] === 'active' ? 'Khóa tài khoản' : 'Mở lại tài khoản' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if ($learners===[]): ?><tr><td colspan="5">Không có học viên phù hợp.</td></tr><?php endif; ?></tbody></table></div>
<nav class="pagination" aria-label="Phân trang học viên"><span><?php if ($page>1): ?><a class="button-secondary" href="<?= $escape($pageLink($page-1)) ?>">← Trang trước</a><?php endif; ?></span><span>Trang <?= $page ?> / <?= $pages ?></span><span><?php if ($page<$pages): ?><a class="button-secondary" href="<?= $escape($pageLink($page+1)) ?>">Trang sau →</a><?php endif; ?></span></nav></section></main>
<?php require __DIR__ . '/_footer.php'; ?></body></html>
