<?php
declare(strict_types=1);
namespace App\Modules\Admin\Controllers;

use App\Core\{ApiException, Database, Session};
use App\Middleware\Auth;

final class LearnerController
{
    public function index(array $params): void
    {
        if (Session::userId() === null) { header('Location: ' . $this->base() . '/login', true, 303); return; }
        Auth::admin();
        $db = Database::connection();
        $search = trim((string) ($_GET['q'] ?? ''));
        $search = mb_substr($search, 0, 100);
        $status = in_array($_GET['status'] ?? '', ['active', 'locked', 'deactivated'], true) ? $_GET['status'] : '';
        $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
        $where = "role = 'user'"; $values = [];
        if ($search !== '') { $where .= ' AND (name LIKE ? OR email LIKE ?)'; $values = ['%' . $search . '%', '%' . $search . '%']; }
        if ($status !== '') { $where .= ' AND status = ?'; $values[] = $status; }
        $count = $db->prepare('SELECT COUNT(*) FROM users WHERE ' . $where); $count->execute($values);
        $total = (int) $count->fetchColumn(); $pages = max(1, (int) ceil($total / 20)); $page = min($page, $pages);
        $query = $db->prepare('SELECT id,name,email,status,created_at FROM users WHERE ' . $where . ' ORDER BY id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
        $query->execute($values); $learners = $query->fetchAll();
        $csrf = Session::csrfToken(); $appBase = $this->base();
        $notice = $_SESSION['learner_notice'] ?? ''; unset($_SESSION['learner_notice']);
        header('Cache-Control: no-store'); header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 4) . '/resources/views/admin/learners.php';
    }

    public function status(array $params): void
    {
        Auth::admin();
        if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals(Session::csrfToken(), $_POST['csrf_token'])) {
            throw new ApiException('CSRF_FAILED', 'Phiên không hợp lệ. Hãy tải lại trang.', 403);
        }
        $id = filter_var($params['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $status = $_POST['status'] ?? null;
        if (!$id || !in_array($status, ['active', 'locked'], true)) { throw new ApiException('VALIDATION_FAILED', 'Trạng thái không hợp lệ.', 422); }
        // A learner management action can never lock an admin, even with a forged ID.
        $db = Database::connection();
        $update = $db->prepare("UPDATE users SET status=? WHERE id=? AND role='user' AND status IN ('active','locked')");
        $update->execute([$status, $id]);
        $_SESSION['learner_notice'] = $update->rowCount() ? 'Đã cập nhật tài khoản học viên.' : 'Tài khoản không thay đổi hoặc không còn khả dụng.';
        header('Location: ' . $this->base() . '/admin/learners', true, 303);
    }

    private function base(): string
    {
        return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    }
}
