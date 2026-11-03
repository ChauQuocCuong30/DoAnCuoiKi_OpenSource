# DOULINGO — 18 bảng vật lý v1.0

Nguồn chính xác là `database/migrations/001_create_tables.sql`. PK/FK/UNIQUE/CHECK đầy đủ nằm trong script. FK mặc định RESTRICT để giữ lịch sử.

Các cột kiểu JSON được MariaDB hiển thị là LONGTEXT là bình thường.

## users

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `name` | VARCHAR(100) |  NOT NULL |
| `email` | VARCHAR(191) |  NOT NULL |
| `password_hash` | VARCHAR(255) |  NOT NULL |
| `role` | VARCHAR(10) |  NOT NULL DEFAULT 'user' |
| `status` | VARCHAR(12) |  NOT NULL DEFAULT 'active' |
| `current_hp` | TINYINT UNSIGNED |  NOT NULL DEFAULT 3 |
| `next_hp_at` | DATETIME |  NULL |
| `daily_goal_xp` | INT UNSIGNED |  NOT NULL DEFAULT 20 |
| `streak_goal_days` | SMALLINT UNSIGNED |  NOT NULL DEFAULT 7 |
| `timezone` | VARCHAR(64) |  NOT NULL DEFAULT 'Asia/Ho_Chi_Minh' |
| `created_at` | DATETIME |  NOT NULL DEFAULT CURRENT_TIMESTAMP |

## topics

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `name` | VARCHAR(150) |  NOT NULL |
| `description` | TEXT |  NULL |
| `image` | VARCHAR(255) |  NULL |
| `position` | INT UNSIGNED |  NOT NULL |
| `is_active` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |

## phonemes

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `symbol` | VARCHAR(24) |  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL |
| `type` | VARCHAR(12) |  NOT NULL |
| `description` | TEXT |  NULL |
| `audio` | VARCHAR(255) |  NULL |
| `position` | INT UNSIGNED |  NOT NULL |

## quest_definitions

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `name` | VARCHAR(150) |  NOT NULL |
| `metric` | VARCHAR(30) |  NOT NULL |
| `target` | INT UNSIGNED |  NOT NULL |
| `xp_reward` | INT UNSIGNED |  NOT NULL DEFAULT 0 |
| `is_active` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |

## words

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `topic_id` | BIGINT UNSIGNED |  NOT NULL |
| `english` | VARCHAR(150) |  NOT NULL |
| `vietnamese` | VARCHAR(255) |  NOT NULL |
| `part_of_speech` | VARCHAR(30) |  NOT NULL |
| `ipa` | VARCHAR(150) |  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL |
| `example` | TEXT |  NULL |
| `example_translation` | TEXT |  NULL |
| `image` | VARCHAR(255) |  NULL |
| `audio` | VARCHAR(255) |  NULL |
| `is_active` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |

Quan hệ:
- `topic_id` → `topics(id)`.

## lessons

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `topic_id` | BIGINT UNSIGNED |  NOT NULL |
| `name` | VARCHAR(150) |  NOT NULL |
| `level` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |
| `position` | INT UNSIGNED |  NOT NULL |
| `question_count` | SMALLINT UNSIGNED |  NOT NULL DEFAULT 10 |
| `pass_percent` | DECIMAL(5,2) |  NOT NULL DEFAULT 70.00 |
| `xp_reward` | INT UNSIGNED |  NOT NULL DEFAULT 10 |
| `is_active` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |

Quan hệ:
- `topic_id` → `topics(id)`.

## word_phonemes

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `position` | SMALLINT UNSIGNED |  NOT NULL |
| `phoneme_id` | BIGINT UNSIGNED |  NOT NULL |
| `is_example` | TINYINT UNSIGNED |  NOT NULL DEFAULT 0 |

Quan hệ:
- `word_id` → `words(id)`.
- `phoneme_id` → `phonemes(id)`.

## lesson_words

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `word_id` | BIGINT UNSIGNED |  NOT NULL |

Quan hệ:
- `lesson_id` → `lessons(id)`.
- `word_id` → `words(id)`.

## exercises

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `lesson_id` | BIGINT UNSIGNED |  NOT NULL |
| `word_id` | BIGINT UNSIGNED |  NULL |
| `type` | VARCHAR(30) |  NOT NULL |
| `difficulty` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |
| `content_json` | JSON |  NOT NULL |
| `answer_json` | JSON |  NOT NULL |
| `is_active` | TINYINT UNSIGNED |  NOT NULL DEFAULT 1 |

Quan hệ:
- `lesson_id` → `lessons(id)`.
- `lesson_id,word_id` → `lesson_words(lesson_id,word_id)`.

## learning_sessions

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `user_id` | BIGINT UNSIGNED |  NOT NULL |
| `lesson_id` | BIGINT UNSIGNED |  NULL |
| `mode` | VARCHAR(20) |  NOT NULL DEFAULT 'lesson' |
| `status` | VARCHAR(20) |  NOT NULL DEFAULT 'in_progress' |
| `total_questions` | SMALLINT UNSIGNED |  NOT NULL DEFAULT 0 |
| `correct_count` | SMALLINT UNSIGNED |  NOT NULL DEFAULT 0 |
| `rules_json` | JSON |  NOT NULL |
| `started_at` | DATETIME |  NOT NULL DEFAULT CURRENT_TIMESTAMP |
| `finished_at` | DATETIME |  NULL |

Quan hệ:
- `user_id` → `users(id)`.
- `lesson_id` → `lessons(id)`.

## session_questions

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `session_id` | BIGINT UNSIGNED |  NOT NULL |
| `exercise_id` | BIGINT UNSIGNED |  NOT NULL |
| `phase` | VARCHAR(20) |  NOT NULL DEFAULT 'main' |
| `position` | SMALLINT UNSIGNED |  NOT NULL |
| `snapshot_json` | JSON |  NOT NULL |
| `source_question_id` | BIGINT UNSIGNED |  NULL |
| `resolved_at` | DATETIME |  NULL |

Quan hệ:
- `session_id` → `learning_sessions(id)`.
- `exercise_id` → `exercises(id)`.
- `session_id,source_question_id` → `session_questions(session_id,id)`.

## answer_submissions

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `session_question_id` | BIGINT UNSIGNED |  NOT NULL |
| `request_key` | VARCHAR(100) |  CHARACTER SET ascii COLLATE ascii_bin NOT NULL |
| `attempt_no` | SMALLINT UNSIGNED |  NOT NULL |
| `answer_json` | JSON |  NOT NULL |
| `is_correct` | TINYINT UNSIGNED |  NOT NULL |
| `hint_used` | TINYINT UNSIGNED |  NOT NULL DEFAULT 0 |
| `answered_at` | DATETIME |  NOT NULL DEFAULT CURRENT_TIMESTAMP |

Quan hệ:
- `session_question_id` → `session_questions(id)`.

## user_review_states

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `user_id` | BIGINT UNSIGNED |  NOT NULL |
| `word_id` | BIGINT UNSIGNED |  NOT NULL |
| `direction` | VARCHAR(20) |  NOT NULL |
| `first_learned_at` | DATETIME |  NULL |
| `ease_factor` | DECIMAL(8,3) |  NOT NULL DEFAULT 2.500 |
| `interval_days` | INT UNSIGNED |  NOT NULL DEFAULT 0 |
| `repetitions` | INT UNSIGNED |  NOT NULL DEFAULT 0 |
| `last_reviewed_at` | DATETIME |  NULL |
| `next_review_at` | DATETIME |  NULL |

Quan hệ:
- `user_id` → `users(id)`.
- `word_id` → `words(id)`.

## review_events

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `review_state_id` | BIGINT UNSIGNED |  NOT NULL |
| `submission_id` | BIGINT UNSIGNED |  NOT NULL |
| `quality` | TINYINT UNSIGNED |  NOT NULL |
| `before_json` | JSON |  NOT NULL |
| `after_json` | JSON |  NOT NULL |
| `reviewed_at` | DATETIME |  NOT NULL DEFAULT CURRENT_TIMESTAMP |

Quan hệ:
- `review_state_id` → `user_review_states(id)`.
- `submission_id` → `answer_submissions(id)`.

## xp_transactions

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `user_id` | BIGINT UNSIGNED |  NOT NULL |
| `session_id` | BIGINT UNSIGNED |  NULL |
| `amount` | INT UNSIGNED |  NOT NULL |
| `reason` | VARCHAR(30) |  NOT NULL |
| `event_key` | VARCHAR(150) |  CHARACTER SET ascii COLLATE ascii_bin NOT NULL |
| `created_at` | DATETIME |  NOT NULL DEFAULT CURRENT_TIMESTAMP |

Quan hệ:
- `user_id` → `users(id)`.
- `session_id` → `learning_sessions(id)`.

## user_daily_activity

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `user_id` | BIGINT UNSIGNED |  NOT NULL |
| `activity_date` | DATE |  NOT NULL |
| `xp_total` | INT UNSIGNED |  NOT NULL DEFAULT 0 |
| `completed_lessons` | INT UNSIGNED |  NOT NULL DEFAULT 0 |
| `streak_qualified` | TINYINT UNSIGNED |  NOT NULL DEFAULT 0 |

Quan hệ:
- `user_id` → `users(id)`.

## user_quests

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `user_id` | BIGINT UNSIGNED |  NOT NULL |
| `quest_id` | BIGINT UNSIGNED |  NOT NULL |
| `quest_date` | DATE |  NOT NULL |
| `progress` | INT UNSIGNED |  NOT NULL DEFAULT 0 |
| `reward_claimed_at` | DATETIME |  NULL |

Quan hệ:
- `user_id` → `users(id)`.
- `quest_id` → `quest_definitions(id)`.

## notifications

| Cột | Kiểu | Định nghĩa |
|---|---|---|
| `user_id` | BIGINT UNSIGNED |  NOT NULL |
| `type` | VARCHAR(30) |  NOT NULL |
| `message` | TEXT |  NOT NULL |
| `scheduled_at` | DATETIME |  NOT NULL |
| `sent_at` | DATETIME |  NULL |
| `read_at` | DATETIME |  NULL |
| `dedupe_key` | VARCHAR(150) |  CHARACTER SET ascii COLLATE ascii_bin NOT NULL |

Quan hệ:
- `user_id` → `users(id)`.

## Các chi tiết đã cụ thể hóa từ bản logic
- exercises.word_id được kiểm soát qua FK ghép đến lesson_words để bảo đảm từ thuộc đúng bài.
- session_questions.source_question_id có FK ghép để buộc nguồn cùng session; chống tự trỏ/nguồn không phải câu main bằng service.
- pass_percent chấp nhận 0 đến dưới100 vì quy tắc là > ngưỡng, không phải >=.
- mode, phase, type, role/status và direction có CHECK theo danh mục đã mô tả.
- Chỉ có 18 bảng: chưa có trigger, stored procedure hoặc bảng migration phụ. Constraint dữ liệu có ở SQL; các quy tắc nghiệp vụ cần PHP.
- learning_sessions.total_questions và correct_count chỉ phản ánh phần main. Sửa sai/nối cặp không làm thay đổi mẫu số.
- Các từ có nhiều nghĩa có thể có nhiều dòng words khác nhau; không đặt UNIQUE(english).

## Hợp đồng JSON câu hỏi (schema_version=1)
- choose_meaning/choose_image/listen_select: content.options có ID công khai; answer.correct_option_ids là ID đúng.
- translate_text/fill_blank/listen_type: answer.accepted_answers là mảng đáp án chấp nhận; normalization xác định trim và case_sensitive.
- token_order: content.tokens chứa ID từng thẻ kể cả khi cùng chữ; answer.accepted_sequences chứa các thứ tự ID hợp lệ.
- match_pairs: content.left/right có các ID không mang ánh xạ; answer.pairs lưu left_id/right_id/word_id. Shuffle hai cột độc lập; không lộ ánh xạ cho client.
- Mọi dạng có content.target_word_ids; kiểm tra chúng thuộc lesson_words trước xuất bản.
- choose_image cần image thực; dạng nghe cần audio thực. Không bật dạng thiếu tài nguyên.
- snapshot_json chụp type/content/answer và thứ tự hiển thị tại lúc bắt đầu; backend chỉ trả type/content không có khóa đáp án.
- answer_submissions.answer_json lưu lựa chọn/chuỗi/pairs mà client gửi; backend tính is_correct.

## Query tham khảo (không thay thế service)
```sql
-- Những từ user đã hoàn thành học ít nhất một chiều:
SELECT DISTINCT w.id,w.english,w.vietnamese
FROM user_review_states s JOIN words w ON w.id=s.word_id
WHERE s.user_id=1 AND s.first_learned_at IS NOT NULL;

-- Thẻ đến hạn tại thời điểm UTC:
SELECT s.*,w.english,w.vietnamese
FROM user_review_states s JOIN words w ON w.id=s.word_id
WHERE s.user_id=1 AND s.first_learned_at IS NOT NULL
  AND s.next_review_at <= UTC_TIMESTAMP()
ORDER BY s.next_review_at;
```
