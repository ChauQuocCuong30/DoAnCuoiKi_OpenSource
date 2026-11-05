<?php
declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\ApiException;
use App\Middleware\Auth;
use App\Modules\Vocabulary\Repositories\TopicRepository;

final class DictionaryPageController
{
    // LIÊN QUAN CƯỜNG: GET /admin/dictionary cần được nối trong routes/web.php.
    public function index(array $params): void
    {
        $isAdmin = false;
        try {
            Auth::admin();
            $isAdmin = true;
        } catch (ApiException $e) {
            // Khách được xem form đăng nhập; các lỗi 403/DB vẫn do Router xử lý.
            if ($e->status !== 401) {
                throw $e;
            }
        }

        $topics = $isAdmin ? (new TopicRepository())->activeForSelection() : [];

        $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        // LIÊN QUAN DANH: view độc lập; chưa thay layout/CSS/JS dùng chung.
        require dirname(__DIR__, 4) . '/resources/views/admin/dictionary.php';
    }
}
