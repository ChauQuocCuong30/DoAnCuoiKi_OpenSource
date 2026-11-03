# Nơi đặt file

| Đường dẫn | Nội dung |
|---|---|
| app/Core | Router, Response, Database và nền tảng dùng chung |
| app/Middleware | Xác thực, phân quyền, giới hạn truy cập |
| app/Shared | Thành phần dùng chung nhiều module, tránh sao chép |
| app/Modules/Auth | Đăng nhập, đăng ký, đăng xuất, phiên |
| app/Modules/Users | Hồ sơ, tài khoản, trạng thái người dùng |
| app/Modules/Vocabulary | Chủ đề, từ, thẻ, nội dung đa phương tiện |
| app/Modules/Learning | Bài học, câu hỏi, đáp án, XP và streak |
| app/Modules/Reviews | SM-2, lịch ôn, lịch sử ôn |
| app/Modules/Notifications | Nhắc học, lịch gửi, chống gửi trùng |
| app/Modules/Admin | Thao tác quản trị, tái sử dụng Service nghiệp vụ |
| bootstrap | Khởi tạo ứng dụng, autoload |
| config | Cấu hình không chứa bí mật |
| routes | Khai báo route web/API khi Router được triển khai |
| database/migrations | Thay đổi schema có đánh số/thời điểm |
| database/seeds | Dữ liệu khởi tạo có thể tái tạo |
| database/samples | CSV mẫu và nguồn/quyền sử dụng, không có dữ liệu cá nhân |
| resources/views | PHP template: layouts, auth, learning, reviews, admin |
| resources/js/api | HTTP client dùng chung |
| resources/js/modules | JavaScript theo tính năng |
| resources/css | CSS nguồn |
| public | Chỉ entrypoint index.php, .htaccess và assets công khai |
| public/assets | Ảnh, âm thanh, CSS/JS được phục vụ; không có file bí mật |
| storage | Log/cache/upload riêng tư, không commit nội dung thực |
| desktop/src | Mã ứng dụng desktop tương lai |
| desktop/resources | Icon và tài nguyên đóng gói desktop |
| tests/unit | Kiểm tra đơn vị: công thức SM-2, ngày ôn… |
| tests/integration | API + database, quyền truy cập, chống ghi trùng |
| tests/e2e | Luồng người dùng trên web/desktop |
| docs | ERD, API, quyết định kỹ thuật, hướng dẫn |
| scripts | Công cụ kiểm tra, nhập dữ liệu, tác vụ CLI |
| .github | CODEOWNERS, mẫu Issue/PR và CI |

Trong mỗi module có Controllers, Requests, Services, Repositories.
Ví dụ: app/Modules/Reviews/Services/Sm2Service.php với namespace App\Modules\Reviews\Services.
File mới phải đúng trách nhiệm, không chỉ đúng tên thư mục.

scripts/check_structure.py kiểm tra vị trí file được Git theo dõi và một số file cấm.
Không chứng minh nội dung đúng trách nhiệm hoặc không có bí mật; nhóm trưởng phải review.
Thêm root file/module mới cần cập nhật policy trong scripts/check_structure.py qua PR.
