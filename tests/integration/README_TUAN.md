# Phần công việc Hà Văn Tuấn - 65134070

- Nhánh làm việc: Tuan.
- Phụ trách: SM-2, ôn tập, nhắc học và kiểm thử liên quan.
- Database: sử dụng schema 18 bảng của nhóm trưởng.
- Database kiểm thử riêng: doulingo_sm2_test.

## 1. Công việc đã viết

| Thư mục | Nội dung |
|---|---|
| app/Modules/Reviews/ | Thuật toán SM-2, trạng thái ôn riêng, danh sách đến hạn, phiên ôn, lưu kết quả, lịch sử |
| app/Modules/Notifications/ | Nhắc học trong ứng dụng, chống nhắc trùng, danh sách thông báo, đánh dấu đã đọc |
| resources/views/reviews/ | Giao diện danh sách ôn, flashcard tự đánh giá, lịch sử và thông báo |
| tests/unit/ | Kiểm thử thuật toán, dữ liệu hỗ trợ và đầu vào |
| tests/integration/ | Kiểm thử database, gửi trùng, nhiều thiết bị và rollback |

Không sửa schema database, Core, Auth hoặc Users.
Đợt tích hợp bổ sung route chung và sửa nhỏ ba file Learning đã có:
ExerciseService.php (snapshot target_word_ids), LearningRepository.php (mode và gọi hook),
LearningService.php (gọi hook trước commit). Cần Danh/Toàn review các điểm nối này.

## 2. Quy tắc SM-2

- EF ban đầu: 2.500; EF tối thiểu: 1.300.
- quality là số nguyên từ 0 đến 5.
- quality < 3: reset repetitions về 0, khoảng ôn 1 ngày.
- Nhớ đúng lần đầu: khoảng ôn 1 ngày.
- Nhớ đúng lần hai: khoảng ôn 6 ngày.
- Từ lần thứ ba: ceil(khoảng ôn cũ × EF cũ).
- Sau đó cập nhật EF theo công thức SM-2.
- Ngày ôn mới tính từ thời điểm thực sự ôn, kể cả ôn trễ.
- Database lưu thời gian UTC.
- Ngày hoạt động và ngày nhắc lấy theo timezone của tài khoản.

Mỗi user/từ/chiều ôn có trạng thái riêng.
Học lại từ đã học không reset lịch cũ.

## 3. Phạm vi luồng ôn hiện tại

V1 dùng flashcard tự đánh giá mức nhớ 0–5.

Client gửi mức nhớ; backend tự tính EF, khoảng ôn và ngày ôn.
Đây chưa phải chức năng chấm đáp án trò chơi tự động.

Không nhận user_id, EF, ngày ôn hoặc điểm XP do client tự chọn.
Không tự cộng XP, thay HP, tiến độ quest hoặc xét streak.
Giữ nguyên các bộ đếm Learning đã ghi.

Hỗ trợ en_vi, vi_en, listening và spelling.
Không khởi tạo thẻ listening khi từ chưa có audio.

## 4. Các API đã đăng ký trong router

Tất cả API nghiệp vụ cần phiên đăng nhập hợp lệ.
Mọi POST cần header X-CSRF-Token khớp token trong phiên.

| Method | Path | Dữ liệu |
|---|---|---|
| GET | /api/v1/reviews/bootstrap | Thông tin user, CSRF, chiều ôn |
| GET | /api/v1/reviews/due | limit, cursor?, direction? |
| GET | /api/v1/reviews/history | limit, before_id? |
| POST | /api/v1/reviews/start | review_state_id, request_key |
| POST | /api/v1/reviews/submit | session_question_id, request_key, quality |
| GET | /api/v1/notifications | limit, before_id? |
| POST | /api/v1/notifications/review-due | {} |
| POST | /api/v1/notifications/{id}/read | {} |

Response thành công: {"data": ...}
Response lỗi: {"error": {"code": "...", "message": "..."}}

Các ID trong JSON phải là số nguyên.
request_key là UUID; start và submit dùng hai key riêng.
Khi retry phải giữ nguyên key và nội dung thao tác.

## 5. Điểm nối với Toàn

Sử dụng PDO và Auth chung của dự án:

```php
use App\Modules\Reviews\Services\ReviewModuleFactory;
use App\Modules\Reviews\Controllers\ReviewController;

$services = ReviewModuleFactory::fromPdo($pdo);
$controller = new ReviewController($services);
```

Factory được gọi ngoài transaction.
PDO cần kết nối MySQL/MariaDB với charset utf8mb4.

Router truyền method, đường dẫn API đã bỏ base path, query,
JSON body, user ID lấy từ Auth và CSRF vào:

```php
$reply = $controller->dispatch(
    $method,
    $path,
    $query,
    $jsonBody,
    $authenticatedUserId,
    $sessionCsrfToken,
    $requestCsrfHeader
);
```

Router xuất HTTP status, headers và JSON body từ $reply.
Router kiểm tra JSON hợp lệ, giới hạn kích thước body và xử lý lỗi 500 chung.
Không lấy authenticatedUserId từ query hoặc JSON của client.

Đăng ký GET /reviews sau Auth và render:

```php
$reviewApiBase = '/doulingo/public';
require $projectRoot . '/resources/views/reviews/index.php';
```

Apache trên máy Tuấn dùng cổng 8081.
Cổng Apache không thay thế cổng kết nối MySQL.

routes/web.php đã đăng ký /reviews và giữ các trang Auth/Learning trên main.
routes/api.php giữ API học, bổ sung Reviews và gọi ContentApiController::register.
ContentController::register nối các trang quản trị đã có; không viết lại nghiệp vụ Cường.

## 6. Điểm nối với Danh — đã bổ sung vào code

Luồng: snapshot từ phía server → hoàn thành bài → thưởng XP →
LessonReviewHook::afterSessionCompleted(PDO, userId, sessionId) → commit.

- ExerciseService giữ target_word_ids ở cấp riêng của snapshot, không đưa vào content công khai.
- LearningRepository chỉ đọc session mode=lesson; không nhận session SM-2.
- Hook xác minh chủ sở hữu, mode, trạng thái completed và toàn bộ câu đã xử lý.
- Chỉ lấy từ trong snapshot của câu đã học; không đọc lại bài tập hiện tại sau khi Admin sửa.
- Mặc định tạo chiều en_vi, hẹn ôn lần đầu sau 24 giờ từ finished_at UTC.
- Học lại không đặt lại lịch cũ; retry câu cuối không thưởng hoặc tạo lịch lần nữa.
- Lỗi ghi lịch làm rollback câu trả lời cuối, kết quả bài và XP cùng transaction.
- Không gọi ReviewModuleFactory trong transaction; hook dùng chính PDO của Learning.
- Session cũ thiếu target_word_ids được phép hoàn thành và báo legacy_questions trong
  session.review_initialization. Không suy đoán từ từ nội dung hiện tại.
  Học một lượt mới sau cập nhật để ghi nhận chính xác các từ còn thiếu lịch.
- Báo cáo khởi tạo được lưu vào rules_json.review_initialization để có thể đối chiếu.

Flashcard Reviews vẫn là tự đánh giá 0–5, chưa phải bộ trò chơi chấm đáp án của Danh.
Điểm mở rộng window.DoulingoReviewCard vẫn cần Danh cung cấp renderer chung nếu muốn
tái sử dụng cùng thành phần trình bày. Không coi điểm mở rộng này là đã kết nối.

## 7. Nhắc học

- Chỉ nhắc trong ứng dụng.
- Tạo thông báo khi mở tab Nhắc học và có thẻ đến hạn.
- Mỗi user/ngày địa phương tối đa một thông báo review_due.
- sent_at nghĩa là đã công bố vào hộp thông báo.
- Chưa gửi email và chưa cài tác vụ chạy nền.
- Chỉ user sở hữu được đánh dấu thông báo đã đọc.

## 8. Kiểm thử trên XAMPP

Chạy tại thư mục gốc dự án:

```powershell
& C:\xampp\php\php.exe tests\unit\Sm2SchedulerTest.php
& C:\xampp\php\php.exe tests\unit\ReviewSupportTest.php
& C:\xampp\php\php.exe tests\unit\ReviewRequestTest.php
```

Số kiểm tra mong đợi lần lượt: 112, 17, 21.
Có thể chạy thêm tests/unit/learning.php để kiểm tra hồi quy phần của Danh.

Đã tạo DB thử doulingo_sm2_test và nhập
database/samples/doulingo_initial.sql một lần.

Cấu hình terminal:

```powershell
$env:SM2_TEST_DB_HOST = '127.0.0.1'
$env:SM2_TEST_DB_PORT = '3306'
$env:SM2_TEST_DB_NAME = 'doulingo_sm2_test'
$env:SM2_TEST_DB_USER = 'root'
$env:SM2_TEST_DB_PASSWORD = ''
```

Điều chỉnh cổng/tài khoản theo MySQL thực tế.
Không ghi mật khẩu thật vào file được commit.

Chạy kiểm thử database:

```powershell
& C:\xampp\php\php.exe tests\integration\ReviewDatabaseTest.php
& C:\xampp\php\php.exe tests\integration\NotificationDatabaseTest.php
```

Kết quả Reviews đã xác nhận trên máy Tuấn:

Integration checks=25 skips=0 result=PASS. (Kết quả trước đợt tích hợp.)

ReviewDatabaseTest.php hiện thêm kiểm tra Learning thật: snapshot riêng, đổi nội dung giữa
lượt học, tạo lịch +1 ngày, retry, học lại, sai mode, học thất bại, quyền sở hữu, rollback
XP/kết quả/câu trả lời khi ghi lịch lỗi và snapshot cũ. Vì đã thêm ca kiểm tra, tổng mới
lớn hơn 25. Cần result=PASS và skips=0 để xác nhận đủ các ca lỗi/concurrency.

Kết quả Notifications mong đợi:

Notification checks=11 result=PASS

Cần lưu kết quả thực tế của từng lần chạy vào mô tả PR.
Kiểm thử database chưa thay thế việc kiểm thử toàn luồng web
với Auth, Router và Learning thật.

## 9. Trạng thái kiểm tra đợt tích hợp

Code được chuẩn bị từ main b7485f5d9a369e7869154c6ef094f63940c4f3be
và Tuan 9f5a03881218b01bc8a8105f400c425695708e9d.
Phiên hỗ trợ này không có môi trường chạy PHP/MySQL.
Chưa xác nhận PASS cho code đợt tích hợp mới; không dùng kết quả cũ thay cho chạy lại.

Sau khi pull nhánh Tuan, chạy ba unit Reviews, unit Learning và hai integration Reviews/
Notifications trong database riêng kết thúc _sm2_test.
Không chạy tests/integration/learning.php của Danh trên DB đang chứa tài khoản:
file đó yêu cầu database thử riêng hoàn toàn mới và có thể chỉnh seed để thử nghiệm.

Kiểm tra web: /login → /learning → hoàn thành một lượt mới → /reviews.
Lịch đầu tiên hẹn ngày kế tiếp, nên danh sách đến hạn có thể vẫn là 0.
Đối chiếu user_review_states của đúng tài khoản hoặc chờ đến hạn;
không chỉnh ngày trên database dùng chung chỉ để làm demo.
Không có migration mới, không xóa hay import lại database đang sử dụng.

## 10. PR và nhánh nộp bài

Dùng nhánh Tuan và cập nhật PR #19 hiện có cho toàn bộ phần Reviews/Notifications.
Tuan-chay-thu chỉ là bản chạy thử cũ; không tạo thêm PR từ nhánh đó.
Nhóm trưởng và Danh review điểm nối Learning; nhóm trưởng merge PR vào main.
