# DOULINGO — Database v1.0 (18 bảng)

Mục tiêu: MariaDB 10.4.32 trên XAMPP. InnoDB, utf8mb4_unicode_ci; IPA sử dụng utf8mb4_bin.
Đây là schema vật lý đầu tiên thay cho ERD 113 bảng. Không dùng bản 113 bảng để import.

## 1. Cài lần đầu bằng phpMyAdmin (khuyên dùng)
1. Bật Apache và MySQL trên XAMPP. Mở http://localhost/phpmyadmin.
2. Tại cấp máy chủ, tab SQL, chạy:

```sql
CREATE DATABASE IF NOT EXISTS doulingo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Chọn database `doulingo` ở bên trái. Phải rỗng: không có bảng. Nếu có bảng, dừng và kiểm tra, không xóa dữ liệu để chạy lại.
4. Tab Import/Nhập → chọn `database/samples/doulingo_initial.sql` → UTF-8 → SQL → Go/Thực hiện.
5. Chạy `database/samples/verify_database.sql` trong database đó.
6. Kết quả: 18 bảng; 1 chủ đề; 5 từ; 6 âm vị; 2 bài; 22 câu hỏi; 3 mẫu nhiệm vụ; 0 tài khoản.

File initial gồm migration + seed. KHÔNG chạy thêm hai file thành phần sau khi đã import initial.
Tất cả script cố ý không có USE/CREATE DATABASE/DROP: phải chọn đúng database trong giao diện. Có thể kiểm tra `SELECT DATABASE();`.

## 2. Cài bằng file thành phần (cách thay thế)
Chọn database rỗng rồi import tuần tự:
1. `database/migrations/001_create_tables.sql`
2. `database/seeds/001_demo_data.sql`
3. Chạy bản chỉ đọc `database/samples/verify_database.sql`

Không dùng cùng lúc cách 1 và cách 2. Không chạy lại schema vào database đã có bảng.
DDL MariaDB không rollback toàn bộ một file; nếu import lỗi giữa chừng có thể đã có vài bảng. Không tiếp tục ghi đè. Ghi lại lỗi rồi kiểm tra trên một database thử rỗng mới.
Seed dùng transaction; nếu lỗi phải kiểm tra/rollback giao dịch trên kết nối đó, không mặc định coi dữ liệu đã nhập đầy đủ.

## 3. Có gì trong dữ liệu mẫu?
- Đồ uống/giao tiếp: milk, tea, coffee, sugar, hello.
- Hai chuỗi âm hoàn chỉnh cho milk và tea; chưa nhập IPA cho các từ còn lại để tránh trộn giọng.
- Mỗi bài có 10 câu chính và 1 câu nối 5 cặp (tổng 22 câu).
- Dạng mẫu: chọn nghĩa, dịch văn bản, điền từ, ghép token, nối cặp.
- Ảnh/audio NULL vì chưa có tài nguyên được xác nhận quyền sử dụng. Chưa sinh câu hỏi yêu cầu ảnh/audio.
- Không có tài khoản admin/user, mật khẩu mặc định hoặc tiến độ giả. Sau khi làm đăng ký, dùng password_hash()/password_verify() trong PHP. Cấp admin cho tài khoản do nhóm trưởng tạo bằng ID chính xác, không tạo mật khẩu rõ trong SQL.
- Database chỉ lưu dữ liệu, chưa tự đăng nhập/chấm bài/trừ tim/tính SM-2/gửi nhắc học. Các thao tác đó cần PHP.

## 4. Quy tắc code bắt buộc
- Kết nối PDO charset=utf8mb4, ERRMODE_EXCEPTION, emulated prepares=false, session time_zone='+00:00'; giữ SQL strict mode.
- Email trim/lowercase trước lưu. `role`: admin/user; `status`: active/locked/deactivated.
- Tim chung tài khoản, default 3; không reset mỗi lần mở bài. Thời gian hồi tim chưa chốt, đặt hằng cấu hình PHP. Đọc current_hp/next_hp_at bằng SELECT ... FOR UPDATE trước cập nhật. Tim đầy thì next_hp_at=NULL theo service.
- learning_sessions.mode: lesson/sm2/heart_practice. lesson_id bắt buộc khi mode=lesson; có thể NULL cho ôn hỗn hợp.
- session_questions.phase: main/mistake_review/matching. Nguồn câu sửa sai bắt buộc ở cùng session bằng FK ghép. Service tiếp tục kiểm tra nguồn là câu main đã sai, không trỏ vào chính nó, không tạo chu trình và giữ đúng exercise/snapshot.
- Giữ snapshot_json bất biến: chứa nội dung, đáp án và thứ tự câu đã sinh. Tách view-model an toàn trước trả client: không lộ answer_json, cặp chuẩn hoặc cờ đúng.
- Exercises có word_id thì FK ghép buộc từ thuộc lesson_words. Với câu nhiều từ (word_id NULL), service xác minh target_word_ids trong JSON là các từ thực, thuộc bài. JSON_VALID chỉ bảo đảm cú pháp, không bảo đảm cấu trúc hoặc ý nghĩa.
- Lần đầu của câu main mới tính correct_count; total_questions là số câu main đã chốt. Đạt khi correct_count*100 > total_questions*pass_percent. Không làm tròn; 7/10 không đạt, 8/10 đạt. pass_percent dưới 100 vì so sánh >100 không thể đạt.
- Còn tim: sửa sai cuối phần chính; đề xuất không trừ tim trong sửa sai. Sửa đúng không đổi điểm main. Đạt ngưỡng mới mở matching; hoàn thành các phần bắt buộc mới status=completed.
- API POST trả lời: request_key do client tạo mỗi thao tác, retry dùng lại key. Kiểm tra chủ sở hữu; duplicate trả kết quả cũ. Trong cùng transaction: khóa trạng thái, ghi submission, cập nhật HP và số câu đúng.
- Hoàn thành/thưởng: khóa session hoặc user_quest; chỉ chuyển trạng thái một lần; xp_transactions.event_key định danh nguồn (vd lesson:123:complete; quest:456:claim). XP và cập nhật nhiệm vụ/ngày học trong cùng transaction. Thưởng quest không tự tăng metric xp_earned để tránh nhiệm vụ kích hoạt vòng thưởng.
- review state UNIQUE(user_id,word_id,direction). first_learned_at khác NULL là đã học; next_review_at là lịch ôn. EF=2.5 ban đầu, tối thiểu1.3. quality0–5. Cần chốt quy tắc quy đổi điểm và cách tổng hợp trong một lượt; không tăng repetitions liên tục chỉ vì sửa sai ngay sau khi hiện đáp án.
- review_events phải cùng người với submission; word/direction phải đúng mục tiêu câu. FK không tự kiểm tra quyền/chủ sở hữu này.
- Chống cập nhật SM-2 trùng theo một lượt/thẻ bằng khóa state và kiểm tra bằng chứng đã xử lý trong session; UNIQUE(state,submission) chỉ chặn cùng submission, không chặn mọi lần sửa sai khác submission.
- user_daily_activity.activity_date là ngày tại users.timezone, không phải DATE(UTC timestamp) một cách máy móc. Tính streak từ các ngày streak_qualified=1. Cố định chính sách đổi múi giờ để tránh đếm đôi.
- user_quests UNIQUE(user,quest,date). Không sửa target/xp_reward của quest_definitions đã được giao; tạo mẫu mới rồi ẩn mẫu cũ nếu đổi luật.
- xp_transactions.user_id phải cùng người với session_id nếu có (service). reward_claimed_at chỉ đặt khi đủ target và thưởng thành công.
- notifications chỉ là thông báo trong app. sent_at là thời điểm được phát/hiển thị khả dụng theo quy ước nhóm; scheduler PHP phải tạo nhắc đúng hạn và dedupe_key. Email/push và trạng thái nhiều kênh chưa có trong 18 bảng.
- Không có bảng user_sessions: đăng nhập web dùng PHP session; cơ chế token desktop cần thiết kế riêng khi triển khai.

## 5. Đưa lên GitHub
Chép thư mục database/ và docs/ trong gói vào C:\xampp\htdocs\doulingo. Không copy file ZIP hoặc thư mục bọc gói vào repo.
Nếu schema/seed cùng tên đã có nội dung, review/backup trước khi thay.
CMD, chạy từng lệnh:

```cmd
cd C:\xampp\htdocs\doulingo
git switch main
git pull --ff-only origin main
git switch -c feature/database-initial
git add database/migrations/001_create_tables.sql database/seeds/001_demo_data.sql
git add database/samples/doulingo_initial.sql database/samples/verify_database.sql
git add docs/DATABASE.md docs/DATABASE_SCHEMA.md docs/DATABASE_TEST_RESULTS.md
git commit -m "feat: khoi tao database MariaDB 18 bang"
git push -u origin feature/database-initial
```

Nếu branch đã tồn tại thì dùng `git switch feature/database-initial`. Tạo PR vào main; xử lý checks/review theo quy tắc repo. Không push --force để bỏ qua lỗi.

## 6. Thành viên cài và cập nhật
- Cùng phiên bản XAMPP/MariaDB là thuận tiện nhất. Thành viên pull/clone source rồi làm cách 1 trên database local rỗng của mình.
- Không chia sẻ thư mục C:\xampp\mysql\data; không commit file dữ liệu của server hoặc thông tin .env thật.
- Git đồng bộ script, không tự đồng bộ database.
- Sau khi bản001 đã dùng chung: không sửa migration/seed001 để yêu cầu mọi người import lại. Thêm 002_....sql và ghi thứ tự áp dụng. Không import initial lên database đã có dữ liệu học.
- Khi xuất từ phpMyAdmin: chỉ cấu trúc + dữ liệu nội dung mẫu, không dữ liệu tài khoản/lịch sử cá nhân. initial hiện tại đã là bản gốc cho nhóm, không cần export lại để dùng lần đầu.

## 7. Tham khảo kỹ thuật
- MariaDB JSON: https://mariadb.com/docs/server/reference/data-types/string-data-types/json
- MariaDB constraints: https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint
- phpMyAdmin import/export: https://docs.phpmyadmin.net/en/latest/import_export.html
