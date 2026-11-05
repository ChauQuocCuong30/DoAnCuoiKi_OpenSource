<?php
declare(strict_types=1);
namespace App\Modules\Admin\Services;
use App\Core\ApiException;
use App\Core\Session;

final class AdminCsrf
{
    public static function token(): string
    {
        // LIÊN QUAN CƯỜNG/DANH: cùng khóa phiên csrf_token như PR #20.
        // Khi PR #20 đã ghép, sử dụng phương thức Session chung.
        if (method_exists(Session::class, 'csrfToken')) return Session::csrfToken();
        Session::start();
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }
    public static function verify(mixed $token): void
    {
        if (!is_string($token) || !hash_equals(self::token(), $token)) {
            throw new ApiException('CSRF_FAILED', 'Phiên biểu mẫu hết hạn. Hãy tải lại trang.', 403);
        }
    }
}
