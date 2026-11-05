# Kệ sách từ vựng cho Admin

Trang chính: `/admin/words` (hoặc `/admin`). Mỗi chủ đề hiện có là một cuốn sách;
số từ được đếm trực tiếp từ `words`, gồm cả từ đang ẩn. Mở sách để tìm, phân trang,
sửa hoặc ẩn/hiện từ. Bố cục nền kem, chữ serif và bìa tông đất được lấy cảm hứng từ
[Tana Elephant](https://motionsites.ai/?prompt=tana-elephant-sections).

## Cập nhật database hiện có bằng phpMyAdmin

1. Sao lưu database trước khi cập nhật.
2. Chọn đúng database của dự án trong phpMyAdmin → Import.
3. Chạy **`database/migrations/002_ensure_word_sources.sql`**. File chỉ bổ sung bảng
   lưu nguồn từ khi thiếu; có thể chạy nếu trước đây đã import `database/samples/002_word_sources.sql`.
4. Mở `/admin/words` sau khi đăng nhập bằng tài khoản admin hiện có.

**Không import lại `001_create_tables.sql`, `doulingo_initial.sql` hoặc seed 001 vào
database đang sử dụng.** Thay đổi này không cần sửa hay xóa dữ liệu cũ.
Không tạo tài khoản/mật khẩu admin mặc định.

## Thêm dữ liệu mẫu

1. Tạo chủ đề bằng liên kết **Tạo chủ đề mới** nếu chủ đề chưa có.
2. Mở đúng sách → **Nhập từ hàng loạt** → **Bộ từ mẫu có sẵn**.
3. Chọn bộ phù hợp → **Kiểm tra & xem trước** → **Nhập vào sách**.

Có **200 từ / 10 bộ**, mỗi bộ 20 từ. Mỗi từ đã có nghĩa tiếng Việt, từ loại,
ví dụ tiếng Anh và bản dịch ví dụ. Bộ mẫu nằm tại
`database/samples/vocabulary_samples.json`; không phải tra API hoặc duyệt từng nghĩa.
Đây là dữ liệu học tập minh họa do dự án biên soạn, không lấy từ Duolingo.
Không kèm ảnh/âm thanh hay tự suy đoán phiên âm.

| Bộ mẫu | Số từ |
| --- | ---: |
| Đồ uống và giao tiếp | 20 |
| Thức ăn | 20 |
| Động vật | 20 |
| Gia đình và con người | 20 |
| Trường học | 20 |
| Du lịch và di chuyển | 20 |
| Nhà cửa và đồ dùng | 20 |
| Hoạt động hằng ngày | 20 |
| Thời tiết và thiên nhiên | 20 |
| Công nghệ cơ bản | 20 |

## Nhập file CSV

Từ hộp nhập, chọn **File CSV của bạn**; có nút tải mẫu tương thích Excel UTF-8 BOM.
CSV tối đa **1 MiB / 1.000 từ** một đợt. Chọn sách ở trên để tự gán chủ đề.

Các cột bắt buộc: `english,vietnamese,part_of_speech,example`.
Các cột tùy chọn: `topic_id,ipa,example_translation,image,audio,image_source,image_license,audio_source,audio_license`.
Thứ tự cột có thể thay đổi; không lặp tên cột. Dùng dấu phẩy; trường chứa dấu phẩy,
ngoặc kép hoặc xuống dòng phải được đặt trong ngoặc kép theo CSV thông thường.

CSV 13 cột cũ vẫn dùng được. Nếu CSV có `topic_id`, giá trị phải khớp sách đang chọn.
API cũ vẫn hỗ trợ CSV nhiều chủ đề khi không truyền sách đích.
Ảnh/âm thanh tiếp tục yêu cầu URL HTTPS, nguồn và quyền sử dụng.

Mặc định **Bỏ qua từ trùng**: so sánh chủ đề + tiếng Anh + từ loại + nghĩa tiếng Việt,
kể cả từ ẩn. Không ghi đè hoặc hiện lại bản ghi cũ. Bỏ chọn để từ chối cả đợt nếu trùng.
Nếu có dòng lỗi, không có từ nào được nhập. Bản xem trước lưu trên server trong phiên
admin, hết hạn sau 15 phút. Đổi dữ liệu/chủ đề phải xem trước lại. Khi nhập, khóa chủ đề,
kiểm tra trùng lại và ghi cả đợt trong một transaction; gửi lại cùng token trả kết quả cũ.

## Phạm vi và luồng cũ

- Không cần xóa file nào. Tra từ điển vẫn ở `/admin/dictionary` như công cụ tùy chọn.
- `/admin/import` mở hộp nhập mới. Bài học/câu hỏi vẫn dùng controller và giao diện cũ.
- Không thay thuật toán học, chấm điểm, tim, XP, streak, SM-2 hoặc tài khoản.
- Từ mới không tự gắn vào bài học và không tự sinh câu hỏi. Tiếp tục gắn theo luồng
  quản lý bài học/câu hỏi hiện có khi muốn đưa từ vào phần học.
- Thao tác loại bỏ từ trên giao diện là **Ẩn**, giữ ID, liên kết bài học và lịch sử.
  Quy tắc bảo vệ từ đã có lịch sử học vẫn áp dụng khi sửa nghĩa.
- Quyền admin, phiên đăng nhập và CSRF vẫn bắt buộc.

## Danh sách file

| Loại | File | Mục đích |
| --- | --- | --- |
| Sửa | `routes/web.php`, `routes/api.php` | Đăng ký controller admin vốn đã có; giữ route học/tài khoản |
| Sửa | `app/Modules/Admin/Controllers/ContentController.php` | Trang kệ sách cho words/import; thêm `/admin` |
| Sửa | `app/Modules/Admin/Controllers/ContentApiController.php` | API sách, bộ mẫu, tải CSV, xem trước/nhập |
| Sửa | `app/Modules/Admin/Services/ContentCsvImporter.php` | CSV gọn, chọn sách, bỏ qua trùng và kiểm tra lại lúc ghi |
| Sửa | `app/Modules/Vocabulary/Repositories/ContentRepository.php` | Truy vấn thống kê sách; giữ các quy tắc ghi cũ |
| Sửa | `resources/views/admin/topics.php` | Liên kết quay về kệ sách |
| Sửa | `.github/workflows/ci.yml` | Chạy thêm kiểm thử admin |
| Thêm | `app/Modules/Admin/Services/VocabularySamples.php` | Đọc và kiểm tra bộ mẫu nội bộ |
| Thêm | `database/migrations/002_ensure_word_sources.sql` | Bổ sung bảng metadata nếu chưa có |
| Thêm | `database/samples/vocabulary_samples.json` | 200 mục từ mẫu song ngữ |
| Thêm | `database/samples/vocabulary_quick_template.csv` | CSV mẫu đơn giản |
| Thêm | `resources/views/admin/books.php` | Giao diện kệ sách, sách và biểu mẫu |
| Thêm | `public/assets/css/admin-books.css`, `public/assets/js/admin-books.js` | Giao diện và tương tác admin riêng |
| Thêm | `tests/integration/admin-books.php`, `tests/e2e/admin-books.cjs` | Kiểm thử database và trình duyệt |
| Thêm | `docs/ADMIN_VOCABULARY.md` | Hướng dẫn này |
| Xóa | Không có | Giữ các chức năng hiện có |

## Kiểm tra

Chỉ chạy các bài tích hợp trên database riêng có tên kết thúc bằng `_test`, đã chạy
migration 001, seed 001, migration 002. Không chạy trên dữ liệu thật.

```bash
python scripts/check_structure.py
git ls-files -z -- '*.php' | xargs -0 -r -n 1 php -l
php tests/unit/learning.php
php tests/integration/learning.php
php tests/integration/admin-books.php
node --check public/assets/js/admin-books.js
# Khởi động PHP bằng cùng cấu hình DB test trước khi chạy trình duyệt:
# php -S 127.0.0.1:8085 -t public
TEST_BASE_URL=http://127.0.0.1:8085 node tests/e2e/learning.cjs
TEST_BASE_URL=http://127.0.0.1:8085 node tests/e2e/admin-books.cjs
```

Trình duyệt cần Playwright/Chromium như job CI hiện có; không thêm thư viện frontend
vào ứng dụng. Bài tích hợp admin hoàn tác dữ liệu kiểm tra và tạo tài khoản/chủ đề
fixture chỉ trong DB `_test`; fixture được dùng một lần bởi bài trình duyệt kế tiếp.
