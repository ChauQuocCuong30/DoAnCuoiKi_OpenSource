-- DOULINGO: MariaDB 10.4.32 / InnoDB / utf8mb4
-- Ban khoi tao 1.0: 18 bang. Chi import vao database rong.
-- Khong DROP TABLE, khong tat FOREIGN_KEY_CHECKS; loi trung bang thi dung va kiem tra.
-- Chon dung database trong phpMyAdmin truoc khi import.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
-- 01. Tai khoan, phan quyen va 3 tim
CREATE TABLE `users` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'user',
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  current_hp TINYINT UNSIGNED NOT NULL DEFAULT 3,
  next_hp_at DATETIME NULL,
  daily_goal_xp INT UNSIGNED NOT NULL DEFAULT 20,
  streak_goal_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT ck_users_role CHECK (role IN ('admin','user')),
  CONSTRAINT ck_users_status CHECK (status IN ('active','locked','deactivated')),
  CONSTRAINT ck_users_hp CHECK (current_hp BETWEEN 0 AND 3),
  CONSTRAINT ck_users_goal CHECK (daily_goal_xp > 0 AND streak_goal_days > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 02. Chu de
CREATE TABLE `topics` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  position INT UNSIGNED NOT NULL,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
PRIMARY KEY (id),
  UNIQUE KEY uq_topics_position (position),
  CONSTRAINT ck_topics_position CHECK (position > 0),
  CONSTRAINT ck_topics_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 03. Nguyen am/phu am, mot giong phat am cho ban dau
CREATE TABLE `phonemes` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  symbol VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  type VARCHAR(12) NOT NULL,
  description TEXT NULL,
  audio VARCHAR(255) NULL,
  position INT UNSIGNED NOT NULL,
PRIMARY KEY (id),
  UNIQUE KEY uq_phonemes_symbol (symbol),
  UNIQUE KEY uq_phonemes_position (position),
  CONSTRAINT ck_phonemes_type CHECK (type IN ('vowel','consonant')),
  CONSTRAINT ck_phonemes_position CHECK (position > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 04. Mau nhiem vu hang ngay
CREATE TABLE `quest_definitions` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  metric VARCHAR(30) NOT NULL,
  target INT UNSIGNED NOT NULL,
  xp_reward INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
PRIMARY KEY (id),
  CONSTRAINT ck_quests_metric CHECK (metric IN ('xp_earned','lessons_completed','words_reviewed')),
  CONSTRAINT ck_quests_target CHECK (target > 0),
  CONSTRAINT ck_quests_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 05. Moi dong la mot nghia/tu loai duoc day
CREATE TABLE `words` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id BIGINT UNSIGNED NOT NULL,
  english VARCHAR(150) NOT NULL,
  vietnamese VARCHAR(255) NOT NULL,
  part_of_speech VARCHAR(30) NOT NULL,
  ipa VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  example TEXT NULL,
  example_translation TEXT NULL,
  image VARCHAR(255) NULL,
  audio VARCHAR(255) NULL,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
PRIMARY KEY (id),
  KEY idx_words_english (english),
  KEY idx_words_topic_active (topic_id,is_active),
  CONSTRAINT fk_words_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_words_active CHECK (is_active IN (0,1)),
  CONSTRAINT ck_words_text CHECK (CHAR_LENGTH(TRIM(english)) > 0 AND CHAR_LENGTH(TRIM(vietnamese)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 06. Bai hoc, moi dong la mot cap bai
CREATE TABLE `lessons` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  position INT UNSIGNED NOT NULL,
  question_count SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  pass_percent DECIMAL(5,2) NOT NULL DEFAULT 70.00,
  xp_reward INT UNSIGNED NOT NULL DEFAULT 10,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
PRIMARY KEY (id),
  UNIQUE KEY uq_lessons_topic_position (topic_id,position),
  CONSTRAINT fk_lessons_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_lessons_level CHECK (level BETWEEN 1 AND 5),
  CONSTRAINT ck_lessons_counts CHECK (position > 0 AND question_count > 0),
  CONSTRAINT ck_lessons_pass CHECK (pass_percent >= 0 AND pass_percent < 100),
  CONSTRAINT ck_lessons_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 07. Chuoi am co thu tu trong mot tu
CREATE TABLE `word_phonemes` (
word_id BIGINT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  phoneme_id BIGINT UNSIGNED NOT NULL,
  is_example TINYINT UNSIGNED NOT NULL DEFAULT 0,
PRIMARY KEY (word_id,position),
  KEY idx_word_phonemes_sound (phoneme_id,is_example),
  CONSTRAINT fk_word_phonemes_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_word_phonemes_sound FOREIGN KEY (phoneme_id) REFERENCES phonemes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_word_phonemes_position CHECK (position > 0),
  CONSTRAINT ck_word_phonemes_example CHECK (is_example IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 08. Nhieu tu trong mot bai, mot tu dung trong nhieu bai
CREATE TABLE `lesson_words` (
lesson_id BIGINT UNSIGNED NOT NULL,
  word_id BIGINT UNSIGNED NOT NULL,
PRIMARY KEY (lesson_id,word_id),
  CONSTRAINT fk_lesson_words_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_lesson_words_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 09. Cau hoi nhieu dang, dap an chi doc tai backend
CREATE TABLE `exercises` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id BIGINT UNSIGNED NOT NULL,
  word_id BIGINT UNSIGNED NULL,
  type VARCHAR(30) NOT NULL,
  difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
  content_json JSON NOT NULL,
  answer_json JSON NOT NULL,
  is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
PRIMARY KEY (id),
  KEY idx_exercises_select (lesson_id,is_active,difficulty,type),
  CONSTRAINT fk_exercises_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_exercises_target FOREIGN KEY (lesson_id,word_id) REFERENCES lesson_words(lesson_id,word_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_exercises_type CHECK (type IN ('choose_meaning','choose_image','fill_blank','token_order','listen_select','listen_type','match_pairs','translate_text','sound_discrimination')),
  CONSTRAINT ck_exercises_difficulty CHECK (difficulty BETWEEN 1 AND 5),
  CONSTRAINT ck_exercises_content CHECK (JSON_VALID(content_json) AND JSON_TYPE(content_json) = 'OBJECT'),
  CONSTRAINT ck_exercises_answer CHECK (JSON_VALID(answer_json) AND JSON_TYPE(answer_json) = 'OBJECT'),
  CONSTRAINT ck_exercises_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Luot hoc va quy tac duoc chup lai
CREATE TABLE `learning_sessions` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  lesson_id BIGINT UNSIGNED NULL,
  mode VARCHAR(20) NOT NULL DEFAULT 'lesson',
  status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
  total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  rules_json JSON NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
PRIMARY KEY (id),
  KEY idx_sessions_user_status (user_id,status,started_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_sessions_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_sessions_mode CHECK (mode IN ('lesson','sm2','heart_practice')),
  CONSTRAINT ck_sessions_status CHECK (status IN ('in_progress','completed','failed','abandoned')),
  CONSTRAINT ck_sessions_lesson CHECK (mode <> 'lesson' OR lesson_id IS NOT NULL),
  CONSTRAINT ck_sessions_score CHECK (correct_count <= total_questions),
  CONSTRAINT ck_sessions_rules CHECK (JSON_VALID(rules_json) AND JSON_TYPE(rules_json) = 'OBJECT'),
  CONSTRAINT ck_sessions_time CHECK (finished_at IS NULL OR finished_at >= started_at),
  CONSTRAINT ck_sessions_completion CHECK ((status = 'in_progress' AND finished_at IS NULL) OR (status <> 'in_progress' AND finished_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Cau da random, nguon cau sua sai cung luot
CREATE TABLE `session_questions` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  exercise_id BIGINT UNSIGNED NOT NULL,
  phase VARCHAR(20) NOT NULL DEFAULT 'main',
  position SMALLINT UNSIGNED NOT NULL,
  snapshot_json JSON NOT NULL,
  source_question_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
PRIMARY KEY (id),
  UNIQUE KEY uq_session_question_identity (session_id,id),
  UNIQUE KEY uq_session_question_position (session_id,phase,position),
  UNIQUE KEY uq_session_question_source (session_id,phase,source_question_id),
  CONSTRAINT fk_questions_session FOREIGN KEY (session_id) REFERENCES learning_sessions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_questions_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_questions_source FOREIGN KEY (session_id,source_question_id) REFERENCES session_questions(session_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_questions_phase CHECK (phase IN ('main','mistake_review','matching')),
  CONSTRAINT ck_questions_position CHECK (position > 0),
  CONSTRAINT ck_questions_snapshot CHECK (JSON_VALID(snapshot_json) AND JSON_TYPE(snapshot_json) = 'OBJECT'),
  CONSTRAINT ck_questions_source_phase CHECK ((phase = 'mistake_review' AND source_question_id IS NOT NULL) OR (phase <> 'mistake_review' AND source_question_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Moi lan tra loi, giu nguyen lich su sai
CREATE TABLE `answer_submissions` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_question_id BIGINT UNSIGNED NOT NULL,
  request_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  answer_json JSON NOT NULL,
  is_correct TINYINT UNSIGNED NOT NULL,
  hint_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
  answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
  UNIQUE KEY uq_submissions_request (request_key),
  UNIQUE KEY uq_submissions_attempt (session_question_id,attempt_no),
  CONSTRAINT fk_submissions_question FOREIGN KEY (session_question_id) REFERENCES session_questions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_submissions_attempt CHECK (attempt_no > 0),
  CONSTRAINT ck_submissions_booleans CHECK (is_correct IN (0,1) AND hint_used IN (0,1)),
  CONSTRAINT ck_submissions_answer CHECK (JSON_VALID(answer_json) AND JSON_TYPE(answer_json) = 'OBJECT')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Tu da hoc va trang thai SM-2 rieng tung nguoi/chieu
CREATE TABLE `user_review_states` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  word_id BIGINT UNSIGNED NOT NULL,
  direction VARCHAR(20) NOT NULL,
  first_learned_at DATETIME NULL,
  ease_factor DECIMAL(8,3) NOT NULL DEFAULT 2.500,
  interval_days INT UNSIGNED NOT NULL DEFAULT 0,
  repetitions INT UNSIGNED NOT NULL DEFAULT 0,
  last_reviewed_at DATETIME NULL,
  next_review_at DATETIME NULL,
PRIMARY KEY (id),
  UNIQUE KEY uq_review_state_target (user_id,word_id,direction),
  KEY idx_review_states_due (user_id,next_review_at),
  CONSTRAINT fk_review_states_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_review_states_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_review_states_direction CHECK (direction IN ('en_vi','vi_en','listening','spelling')),
  CONSTRAINT ck_review_states_ease CHECK (ease_factor >= 1.300),
  CONSTRAINT ck_review_states_first CHECK (first_learned_at IS NOT NULL OR (last_reviewed_at IS NULL AND next_review_at IS NULL AND repetitions = 0 AND interval_days = 0)),
  CONSTRAINT ck_review_states_last CHECK (last_reviewed_at IS NULL OR last_reviewed_at >= first_learned_at),
  CONSTRAINT ck_review_states_next CHECK (next_review_at IS NULL OR (first_learned_at IS NOT NULL AND next_review_at >= first_learned_at AND (last_reviewed_at IS NULL OR next_review_at >= last_reviewed_at)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Bang chung on va trang thai truoc/sau SM-2
CREATE TABLE `review_events` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  review_state_id BIGINT UNSIGNED NOT NULL,
  submission_id BIGINT UNSIGNED NOT NULL,
  quality TINYINT UNSIGNED NOT NULL,
  before_json JSON NOT NULL,
  after_json JSON NOT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
  UNIQUE KEY uq_review_events_evidence (review_state_id,submission_id),
  KEY idx_review_events_time (review_state_id,reviewed_at),
  CONSTRAINT fk_review_events_state FOREIGN KEY (review_state_id) REFERENCES user_review_states(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_review_events_submission FOREIGN KEY (submission_id) REFERENCES answer_submissions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_review_events_quality CHECK (quality BETWEEN 0 AND 5),
  CONSTRAINT ck_review_events_before CHECK (JSON_VALID(before_json) AND JSON_TYPE(before_json) = 'OBJECT'),
  CONSTRAINT ck_review_events_after CHECK (JSON_VALID(after_json) AND JSON_TYPE(after_json) = 'OBJECT')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. XP thuong, event_key ngan cong trung
CREATE TABLE `xp_transactions` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED NULL,
  amount INT UNSIGNED NOT NULL,
  reason VARCHAR(30) NOT NULL,
  event_key VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
  UNIQUE KEY uq_xp_event (event_key),
  KEY idx_xp_user_time (user_id,created_at),
  CONSTRAINT fk_xp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_xp_session FOREIGN KEY (session_id) REFERENCES learning_sessions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_xp_amount CHECK (amount > 0),
  CONSTRAINT ck_xp_reason CHECK (reason IN ('lesson_completed','quest_claimed','review_completed','bonus'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Ngay hoc dia phuong de tinh streak va lich thang
CREATE TABLE `user_daily_activity` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  activity_date DATE NOT NULL,
  xp_total INT UNSIGNED NOT NULL DEFAULT 0,
  completed_lessons INT UNSIGNED NOT NULL DEFAULT 0,
  streak_qualified TINYINT UNSIGNED NOT NULL DEFAULT 0,
PRIMARY KEY (id),
  UNIQUE KEY uq_daily_activity (user_id,activity_date),
  CONSTRAINT fk_daily_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_daily_streak CHECK (streak_qualified IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Nhiem vu giao cho user theo ngay
CREATE TABLE `user_quests` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  quest_id BIGINT UNSIGNED NOT NULL,
  quest_date DATE NOT NULL,
  progress INT UNSIGNED NOT NULL DEFAULT 0,
  reward_claimed_at DATETIME NULL,
PRIMARY KEY (id),
  UNIQUE KEY uq_user_quests_daily (user_id,quest_id,quest_date),
  CONSTRAINT fk_user_quests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_user_quests_definition FOREIGN KEY (quest_id) REFERENCES quest_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Nhac hoc trong ung dung
CREATE TABLE `notifications` (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(30) NOT NULL,
  message TEXT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  read_at DATETIME NULL,
  dedupe_key VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
PRIMARY KEY (id),
  UNIQUE KEY uq_notifications_dedupe (dedupe_key),
  KEY idx_notifications_inbox (user_id,read_at,scheduled_at),
  KEY idx_notifications_pending (sent_at,scheduled_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_notifications_type CHECK (type IN ('review_due','daily_reminder','quest_completed','system'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
