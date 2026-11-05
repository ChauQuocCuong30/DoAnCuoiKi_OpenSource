<?php
declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\ApiException;
use App\Core\Session;
use App\Modules\Admin\Services\AdminCsrf;
use App\Middleware\Auth;
use App\Modules\Vocabulary\Repositories\TopicRepository;

final class TopicController
{
    public function index(array $params): void
    {
        Auth::admin();
        $id = $_GET['edit'] ?? '';
        $form = $id === '' ? null : (new TopicRepository())->find($this->positive($id));
        $this->render($form);
    }

    public function store(array $params): void
    {
        // LIÊN QUAN CƯỜNG: dùng Auth/Session chung, không tự quản lý đăng nhập.
        Auth::admin();
        Session::start();
        AdminCsrf::verify($_POST['csrf_token'] ?? null);
        $form = [];
        try {
            foreach (['id', 'name', 'description', 'image', 'is_active'] as $key) {
                $value = $_POST[$key] ?? '';
                if (!is_string($value)) {
                    throw new ApiException('VALIDATION_FAILED', 'Dữ liệu biểu mẫu không hợp lệ.', 422);
                }
                $form[$key] = trim($value);
            }
            $id = $form['id'] === '' ? null : $this->positive($form['id']);
            if ($id !== null) {
                $form['position'] = (new TopicRepository())->find($id)['position'];
            }
            if ($form['name'] === '' || mb_strlen($form['name'], 'UTF-8') > 150
                || mb_strlen($form['description'], 'UTF-8') > 5000) {
                throw new ApiException('VALIDATION_FAILED', 'Tên bắt buộc, tối đa 150 ký tự; mô tả tối đa 5000 ký tự.', 422);
            }
            if (!in_array($form['is_active'], ['0', '1'], true)) {
                throw new ApiException('VALIDATION_FAILED', 'Trạng thái không hợp lệ.', 422);
            }
            $image = $form['image'];
            if ($image !== '' && (strlen($image) > 255
                || !filter_var($image, FILTER_VALIDATE_URL)
                || parse_url($image, PHP_URL_SCHEME) !== 'https'
                || parse_url($image, PHP_URL_USER) !== null
                || parse_url($image, PHP_URL_PASS) !== null)) {
                throw new ApiException('VALIDATION_FAILED', 'Ảnh cần URL HTTPS hợp lệ, tối đa 255 ký tự.', 422);
            }
            $id = (new TopicRepository())->save($id, [
                'name' => $form['name'], 'description' => $form['description'] === '' ? null : $form['description'],
                'image' => $image ?: null,
                'is_active' => (int) $form['is_active'],
            ]);
            $_SESSION['admin_topics_notice'] = 'Đã lưu chủ đề ' . sprintf('CD%06d', $id) . '.';
            header('Location: ' . $this->base() . '/admin/topics?edit=' . $id, true, 303);
            exit;
        } catch (ApiException $e) {
            $this->render($form, $e->getMessage(), $e->status);
        }
    }

    private function positive(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/D', $value)) {
            throw new ApiException('VALIDATION_FAILED', 'ID/trang phải là số nguyên dương.', 422);
        }
        return (int) $value;
    }

    private function base(): string
    {
        return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    }

    private function render(?array $form = null, string $error = '', int $status = 200): void
    {
        Session::start();
        $csrf = AdminCsrf::token();
        $notice = $_SESSION['admin_topics_notice'] ?? '';
        unset($_SESSION['admin_topics_notice']);
        $search = $_GET['q'] ?? '';
        $page = $_GET['page'] ?? '1';
        if (!is_string($search) || mb_strlen($search, 'UTF-8') > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Từ tìm kiếm tối đa 100 ký tự.', 422);
        }
        $search = trim($search);
        $listing = (new TopicRepository())->paginate($search, $this->positive($page));
        $form = array_replace(['id' => '', 'name' => '', 'description' => '',
            'image' => '', 'position' => '', 'is_active' => '1'], $form ?? []);
        $appBase = $this->base();
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        require dirname(__DIR__, 4) . '/resources/views/admin/topics.php';
    }
}
