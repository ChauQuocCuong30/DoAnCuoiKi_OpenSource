# Câu hỏi có âm thanh và khảo sát đầu vào

Áp dụng trên main sau PR #27. Cần CSDL đã có migration 006; bổ sung `007_onboarding_survey.sql`. Migration 007 tạo bảng khảo sát theo tài khoản, chạy lại được; không sửa XP, bài đã hoàn thành hoặc lịch SM-2.

## Quy tắc phát âm

| Dạng | Khi vào câu | Khi chọn đáp án |
| --- | --- | --- |
| Xếp câu tiếng Việt từ câu tiếng Anh | Đọc câu tiếng Anh; có nút nghe lại | Không đọc các token tiếng Việt |
| Xếp câu tiếng Anh từ câu tiếng Việt | Không đọc câu tiếng Việt | Đọc token tiếng Anh khi thêm vào câu |
| Chọn nghĩa | Không tự đọc câu hỏi | Chỉ đọc lựa chọn tiếng Anh; không đọc lựa chọn tiếng Việt |
| Điền bằng bàn phím | Không đọc những gì người học đang gõ; không đọc đáp án đang bị khuyết | Nhập và gửi bình thường |
| Chọn hình | Đọc từ tiếng Anh hiển thị | Hình không có văn bản để đọc |
| Bài nghe | Phát file của câu; nghe lại bằng nút loa | Đáp án tiếng Anh được đọc khi chọn |
| Ôn SM-2 | Đọc mặt thẻ tiếng Anh hoặc bài nghe; không đọc mặt câu hỏi tiếng Việt | Lật thẻ có thể nghe câu trả lời tiếng Anh |

Ngôn ngữ được đánh dấu bằng `prompt_lang`, `answer_lang`, `left_lang`, `right_lang` và `lang` của mục, với giá trị `en` hoặc `vi`. Không dùng kiểm tra có dấu tiếng Việt để suy đoán các từ mơ hồ như “con”. Snapshot cũ chưa có metadata được xử lý thận trọng từ nội dung hướng dẫn/kho từ hiện có.

Kho từ có bản dịch ví dụ sẽ sinh thêm xếp câu hai chiều. Chọn token thêm vào cuối; nhấn bất kỳ token đã chọn để gỡ riêng token đó; các token còn lại giữ thứ tự. Token có cùng chữ vẫn là những mục độc lập theo ID.

Trắc nghiệm, sắp xếp và ghép cặp được chấm ở backend. API nộp đáp án trả thêm `feedback` sau khi đã chấm; API lấy câu hỏi vẫn không trả khóa đáp án. Phản hồi gồm nội dung đáp án để học lại, kết quả từng lựa chọn hoặc từng cặp. Phản hồi không phải lệnh cập nhật điểm từ client.

## Thêm file sau này

- Từ: đặt file tại `public/assets/media/audio/milk.mp3` và gắn vào trường `audio` là `/assets/media/audio/milk.mp3`.
- Câu: có thể đặt file tại `public/assets/media/audio/sentences/i-drink-milk.mp3`.
- Ảnh: ví dụ `public/assets/media/images/milk.webp`, trường `image` là `/assets/media/images/milk.webp`.
- Điền nguồn `audio_source`/`image_source` và giấy phép/quyền sử dụng tương ứng. Với file tự sở hữu, nguồn có thể là đường dẫn media cục bộ và giấy phép ghi rõ quyền sở hữu. Không khai giấy phép khi chưa có quyền.
- Đường dẫn cục bộ trong admin phải dưới `/assets/media/`, không có khoảng trắng, dấu `..`, query, đường dẫn mã hóa hoặc phần mở rộng thực thi. Vẫn nhận URL HTTPS theo quy tắc hiện có.

Để gắn âm thanh cho câu mà không sửa dữ liệu chấm bài, thêm vào `public/assets/js/learning-media.js` sau khi file thật đã có:

```js
window.DOULINGO_MEDIA['milk'] = {word:'/assets/media/audio/milk.mp3'};
window.DOULINGO_MEDIA['i drink milk.'] = {example:'/assets/media/audio/sentences/i-drink-milk.mp3'};
```

Key câu phải là đúng câu tiếng Anh, chuyển chữ thường và giữ dấu câu. Map chỉ phục vụ phát âm. Để ngân hàng sinh dạng bài nghe, còn phải gắn đường dẫn `audio` trong bản ghi từ vựng. Để sinh chọn hình 4 đáp án, cần ít nhất 4 hình hợp lệ, khác nhau trong cùng nhóm từ.

Ưu tiên file được cung cấp. Nếu file chưa có hoặc lỗi tải, dùng giọng tiếng Anh của thiết bị nếu có. Trình duyệt có thể chặn tự phát ở lần đầu; nút loa vẫn cho người học chủ động nghe. Đổi câu, ẩn tab hoặc rời trang sẽ hủy giọng/file cũ; kết quả tải cũ không được phát chen vào câu mới.

## Khảo sát

`/welcome` → `/start`: chào hỏi, lý do học, mức tự đánh giá A1–C2, thời lượng hằng ngày, chọn bắt đầu cơ bản hoặc làm demo, vào bài thật. Sau lượt học có cờ `onboarding=1`, người học chọn mục tiêu streak rồi tạo hồ sơ/đăng nhập.

Mức tự đánh giá không tự mở khóa lộ trình. Demo hiện có vẫn gợi ý A1–B1 và không cộng XP. Khảo sát không tự chứng nhận trình độ và không tạo bài hoàn thành. Kết quả bài, XP và streak lấy từ server; khách được nhắc tạo hồ sơ thay vì hiển thị streak giả.

`GET /api/v1/onboarding` trả `answers`, `signed_in`, `schema_ready`, `learning_url`.

`POST /api/v1/onboarding` yêu cầu header `X-CSRF-Token`; nhận các trường:

- `reason`: `free_time`, `connect`, `career`, `fun`, `study`, `travel`, `other`.
- `level`: `A1`, `A2`, `B1`, `B2`, `C1`, `C2`.
- `minutes`: 5, 10, 15, 20. Mục tiêu XP tương ứng 10, 20, 30, 40; không coi số phút là thời gian đã học.
- `start_mode`: `basic`, `placement`.
- `streak`: 7, 14, 30, 50 ngày mục tiêu.
- `step`: số nguyên 0–7; `finished`: boolean, chỉ hoàn tất khi đủ các lựa chọn.
- `timezone`: tùy chọn, phải thuộc danh sách múi giờ PHP chấp nhận.

Khách lưu bản nháp trong PHP session. Lần đầu đăng nhập tài khoản học viên chưa có khảo sát, bản nháp được gắn vào `user_onboarding` và mục tiêu được cập nhật trong `users`. Không ghi đè sở thích của tài khoản đã có khảo sát bằng bản nháp khách. Khảo sát khi đang đăng nhập được lưu đúng tài khoản hiện tại; đăng xuất không xóa khảo sát đã lưu trong CSDL.

Nếu cần quay lại code trước cập nhật, dùng bản sao mã nguồn trước khi áp dụng. Bảng `user_onboarding` có thể giữ nguyên để bảo toàn khảo sát; không cần xóa lịch sử học để khôi phục giao diện cũ.

## Kiểm tra

```bash
php tests/unit/QuestionSpeechTest.php
php tests/unit/VocabularyQuestionFactoryTest.php
node --check public/assets/js/learning-ui.js
node --check public/assets/js/learning-question-ui.js
node --check public/assets/js/onboarding-survey.js
```

Trên CSDL kiểm thử và sau các fixture của CI: `TEST_BASE_URL=... node tests/e2e/learning-interactions.cjs`. Test phát âm dùng spy để xác nhận văn bản/ngôn ngữ/thời điểm gọi, không xác nhận chất lượng giọng thật của từng máy. Cần nghe thử trên Chrome/Edge của máy chạy XAMPP với giọng tiếng Anh hoặc file ghi âm thật.

Tham khảo: https://developer.mozilla.org/en-US/docs/Web/API/SpeechSynthesis và https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay .

## Giao diện sáng/tối và âm thanh không tải được

Mặc định là nền trắng. Nút cạnh tài khoản chuyển sáng/tối và lưu khóa `doulingo_theme` trong trình duyệt; các trang học, khảo sát, kiểm tra demo, hồ sơ, admin và SM-2 dùng cùng bảng màu. Không gắn lựa chọn giao diện với tiến độ học hoặc xóa nó khi đăng xuất.

Câu `listen_type` (nghe từ) và `listen_select` có phương án bằng chữ được máy chủ tạo từ đúng từ vựng của câu hỏi. Khi file lỗi, giao diện dùng phương án này hoặc cho người học chọn **Làm bằng chữ**. **Thử nghe lại** tải lại câu gốc. Không tự bỏ câu, cộng điểm hay trừ tim khi đổi cách trình bày. Câu trả lời vẫn gửi cùng question ID và được chấm bằng snapshot ban đầu. Các lượt học trước cập nhật cũng nhận được phương án thay thế khi tiếp tục học; snapshot, câu đã làm và lịch sử không bị viết lại. Không đưa accepted_answers/correct_option_ids ra dữ liệu câu hỏi.

File âm thanh thật vẫn cần được nhập đúng URL HTTPS hoặc đường dẫn `/assets/media/...` trong kho từ. Nếu máy chưa có file, phương án bằng chữ giúp hoàn thành bài; nó không tạo ra bản thu âm. TTS của thiết bị dùng cho các câu có văn bản tiếng Anh công khai, không hiển thị đáp án của câu nghe để gọi TTS. Trình duyệt chặn tự phát thì nút loa cho phép nghe lại bằng thao tác người dùng.

Phần ghép luồng đã giữ PR #28: chọn mức chạy bài demo đúng mức, đóng demo quay về trang trước, tiếp tục demo thông thường giữ luồng tài khoản của Cường. Demo được mở từ khảo sát (`onboarding=1`) quay về bước chuẩn bị của khảo sát.

Kiểm tra bổ sung sau migration 007:

```bash
php tests/unit/QuestionSpeechTest.php
php tests/integration/media-fallback.php
TEST_BASE_URL=http://127.0.0.1:8085 NODE_PATH=/tmp/learning-playwright/node_modules node tests/e2e/theme-media.cjs
```

Hai bài kiểm tra sau chỉ chạy trên CSDL riêng có tên kết thúc `_test`, đã seed dữ liệu và fixture admin; không chạy fixture này trên dữ liệu sử dụng thật.

## Hoàn thiện tương phản, hiệu ứng và nhịp chuyển câu

Vùng xếp từ có nền trong suốt theo trang; nút từ và hộp tạo hồ sơ dùng màu theo chế độ hiện tại. Chữ thông thường, trạng thái nút chưa đủ đáp án, nội dung phản hồi và hộp tạo hồ sơ được kiểm tra tỷ lệ tương phản ít nhất 4.5:1 ở cả sáng/tối.

`learning-feedback-sound.js` tổng hợp hai hiệu ứng ngắn bằng Web Audio: chuông đi lên khi đúng, hai nốt thấp khi sai. Không cần file MP3 ngoài. Giao diện mở AudioContext trong thao tác người dùng, chỉ phát sau phản hồi chấm bài của máy chủ, không phát khi mới chọn đáp án hoặc khi lỗi mạng. Khóa request giúp không lặp hiệu ứng do gửi lại; thiếu hỗ trợ âm thanh không ngăn việc chấm bài. Không thay đổi đáp án, điểm, tim hoặc SM-2.

Phản hồi nằm cùng thanh Tiếp tục ở đáy trang. Câu cuối vẫn hiện phản hồi trước khi người học chọn xem kết quả. Chuyển câu gồm khoảng 120ms rời câu cũ và 180ms vào câu mới; giọng đọc tự động chờ 180ms để nội dung xuất hiện. Gỡ từ bất kỳ giữ thứ tự phần còn lại; chọn lại thêm vào cuối. Giảm chuyển động trong cài đặt thiết bị tắt các hiệu ứng di chuyển.

Quy tắc đọc:

- `fill_blank` và `translate_text`: không tự đọc, không có nút loa; việc gõ đáp án im lặng.
- `listen_type`/`listen_select`: vẫn nghe để làm bài; có nghe lại và nghe chậm 0.75×.
- Viết lại bằng tiếng Việt: đọc câu tiếng Anh, không đọc các từ tiếng Việt.
- Viết lại bằng tiếng Anh: đọc từ tiếng Anh khi chọn từ danh sách.
- Chọn nghĩa: không tự đọc đề; chọn đáp án tiếng Anh thì đọc, tiếng Việt thì im lặng.

Giữ ưu tiên file ghi âm do admin cung cấp rồi mới dùng TTS cho văn bản tiếng Anh công khai. Giọng TTS phụ thuộc thiết bị, không phải bản thu lấy từ video tham khảo. Video được dùng để đối chiếu cách chuyển bước, đặt phản hồi và nhịp tương tác.

Kiểm thử thêm sau các fixture trước:

```bash
php tests/integration/media-fallback.php --short
TEST_BASE_URL=http://127.0.0.1:8085 NODE_PATH=/tmp/learning-playwright/node_modules node tests/e2e/lesson-polish.cjs
```

Bài kiểm tra đo tín hiệu thực của Web Audio trong Chromium, giữ phản hồi mạng để kiểm tra thời điểm phát, thử gửi lại khi mạng lỗi, kiểm tra thứ tự từ, tắt đọc câu bàn phím, nghe chậm, hủy câu đọc cũ và giảm chuyển động. Fixture `--short` chỉ thay lượt học giả lập vừa tạo trong CSDL `_test`, không chạy trên dữ liệu thật.
