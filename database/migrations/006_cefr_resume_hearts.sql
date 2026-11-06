-- Upgrade existing data in place. Existing words default to A1; IDs/history remain intact.

SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='words' AND column_name='cefr_level')=0,
    'ALTER TABLE words ADD COLUMN cefr_level VARCHAR(2) NOT NULL DEFAULT ''A1''' , 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='learning_sessions' AND column_name='last_seen_at')=0,
    'ALTER TABLE learning_sessions ADD COLUMN last_seen_at DATETIME NULL', 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;

SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='lessons' AND constraint_name='ck_lessons_level')>0,
    CONCAT('ALTER TABLE lessons ', IF(LOCATE('MariaDB',VERSION())>0,'DROP CONSTRAINT ','DROP CHECK '),'ck_lessons_level'), 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;
ALTER TABLE lessons ADD CONSTRAINT ck_lessons_level CHECK (level BETWEEN 1 AND 6);

SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='exercises' AND constraint_name='ck_exercises_difficulty')>0,
    CONCAT('ALTER TABLE exercises ', IF(LOCATE('MariaDB',VERSION())>0,'DROP CONSTRAINT ','DROP CHECK '),'ck_exercises_difficulty'), 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;
ALTER TABLE exercises ADD CONSTRAINT ck_exercises_difficulty CHECK (difficulty BETWEEN 1 AND 6);

SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='learning_topic_catalog' AND constraint_name='ck_catalog_level')>0,
    CONCAT('ALTER TABLE learning_topic_catalog ', IF(LOCATE('MariaDB',VERSION())>0,'DROP CONSTRAINT ','DROP CHECK '),'ck_catalog_level'), 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;
ALTER TABLE learning_topic_catalog ADD CONSTRAINT ck_catalog_level CHECK (level BETWEEN 1 AND 6);

CREATE TABLE IF NOT EXISTS learning_mistakes (
    user_id BIGINT UNSIGNED NOT NULL,
    word_id BIGINT UNSIGNED NOT NULL,
    last_question_id BIGINT UNSIGNED NOT NULL,
    wrong_count INT UNSIGNED NOT NULL DEFAULT 1,
    last_wrong_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY(user_id,word_id),
    CONSTRAINT fk_mistake_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mistake_word FOREIGN KEY(word_id) REFERENCES words(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mistake_question FOREIGN KEY(last_question_id) REFERENCES session_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS heart_practice_items (
    session_id BIGINT UNSIGNED NOT NULL,
    word_id BIGINT UNSIGNED NOT NULL,
    mistake_question_id BIGINT UNSIGNED NOT NULL,
    practice_question_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(session_id,word_id),
    UNIQUE KEY uq_heart_question(practice_question_id),
    CONSTRAINT fk_hp_session FOREIGN KEY(session_id) REFERENCES learning_sessions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hp_word FOREIGN KEY(word_id) REFERENCES words(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hp_mistake_question FOREIGN KEY(mistake_question_id) REFERENCES session_questions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hp_question FOREIGN KEY(practice_question_id) REFERENCES session_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS heart_practice_rewards (
    session_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    hearts_added TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(session_id),
    CONSTRAINT fk_hp_reward_session FOREIGN KEY(session_id) REFERENCES learning_sessions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hp_reward_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT ck_hp_reward CHECK(hearts_added BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Record mistakes from existing lesson history, including failures before this update.
INSERT INTO learning_mistakes(user_id,word_id,last_question_id,wrong_count,last_wrong_at)
SELECT s.user_id,e.word_id,MAX(q.id),COUNT(DISTINCT q.id),MAX(s.started_at)
FROM answer_submissions a JOIN session_questions q ON q.id=a.session_question_id
JOIN learning_sessions s ON s.id=q.session_id JOIN exercises e ON e.id=q.exercise_id
WHERE s.mode='lesson' AND q.phase='main' AND a.attempt_no=1 AND a.is_correct=0 AND e.word_id IS NOT NULL
GROUP BY s.user_id,e.word_id
ON DUPLICATE KEY UPDATE user_id=learning_mistakes.user_id;

-- Validate manual SQL imports too; protect lookup performance for larger vocabulary pools.
SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='words' AND constraint_name='ck_words_cefr')=0,
    'ALTER TABLE words ADD CONSTRAINT ck_words_cefr CHECK (cefr_level IN (''A1'',''A2'',''B1'',''B2'',''C1'',''C2''))', 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;
SET @upgrade_sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='words' AND index_name='idx_words_topic_cefr')=0,
    'CREATE INDEX idx_words_topic_cefr ON words(topic_id,cefr_level,is_active)', 'SELECT 1');
PREPARE upgrade_stmt FROM @upgrade_sql; EXECUTE upgrade_stmt; DEALLOCATE PREPARE upgrade_stmt;
