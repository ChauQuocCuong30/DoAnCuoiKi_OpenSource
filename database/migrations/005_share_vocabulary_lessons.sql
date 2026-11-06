-- Enable automatic lessons/questions from the existing admin vocabulary pool.
-- Keep all existing topics, words, IDs, sessions, XP and review history.
CREATE TABLE IF NOT EXISTS learning_topic_catalog (
    topic_id BIGINT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    lesson_id BIGINT UNSIGNED NOT NULL,
    content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    word_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (topic_id,level),
    UNIQUE KEY uq_catalog_lesson (lesson_id),
    CONSTRAINT fk_catalog_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE RESTRICT,
    CONSTRAINT fk_catalog_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE RESTRICT,
    CONSTRAINT ck_catalog_level CHECK (level BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
