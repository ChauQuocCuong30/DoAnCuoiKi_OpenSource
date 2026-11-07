-- Run after migrations 001-007. Keeps all lessons, scores and review history.
CREATE TABLE IF NOT EXISTS chapter_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'in_progress',
    current_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    correct_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    questions_json JSON NOT NULL,
    submissions_json JSON NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    PRIMARY KEY(id),
    KEY ix_chapter_attempt_owner(user_id,topic_id,status),
    CONSTRAINT fk_chapter_attempt_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_chapter_attempt_topic FOREIGN KEY(topic_id) REFERENCES topics(id) ON DELETE RESTRICT,
    CONSTRAINT ck_chapter_attempt_status CHECK(status IN ('in_progress','completed','failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_chapter_passes (
    user_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    attempt_id BIGINT UNSIGNED NOT NULL,
    passed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id,topic_id),
    CONSTRAINT fk_chapter_pass_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_chapter_pass_topic FOREIGN KEY(topic_id) REFERENCES topics(id) ON DELETE RESTRICT,
    CONSTRAINT fk_chapter_pass_attempt FOREIGN KEY(attempt_id) REFERENCES chapter_attempts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
