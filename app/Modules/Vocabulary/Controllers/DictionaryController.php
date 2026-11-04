<?php
declare(strict_types=1);

namespace App\Modules\Vocabulary\Controllers;

use App\Core\ApiException;
use App\Core\Response;
use App\Middleware\Auth;
use App\Modules\Vocabulary\Services\DictionaryService;
use App\Modules\Vocabulary\Services\DictionaryNormalizer;

final class DictionaryController
{
    /**
     * GET /api/v1/admin/dictionary?word=milk
     * Chỉ Admin đang hoạt động được truy cập.
     *
     * LIÊN QUAN CƯỜNG:
     * Cần đăng ký endpoint trong routes/api.php.
     */
    public function lookup(array $params): void
    {
        // Dùng xác thực chung của Cường.
        // Chặn người không có quyền trước khi gọi API.
        Auth::admin();

        $word = $_GET['word'] ?? null;

        if (!is_string($word)) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Tham số word phải là chuỗi tiếng Anh.',
                422
            );
        }

        // Lấy dữ liệu từ API từ điển.
        $lookup = (new DictionaryService())->lookup($word);

        // Chuyển thành danh sách từng nghĩa.
        $data = (new DictionaryNormalizer())->normalize($lookup);

        // LIÊN QUAN DANH:
        // Kết quả này dành cho Admin chọn nội dung.
        // Chưa dùng trực tiếp để tạo hoặc chấm bài học.

        // LIÊN QUAN TUẤN:
        // Chưa lưu từ hoặc thay đổi lịch ôn SM-2.

        header('Cache-Control: no-store');

        // Trả JSON theo định dạng chung: {"data": ...}
        Response::data($data);
    }
}