CREATE TABLE IF NOT EXISTS learning_guest_claims (
    token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    lesson_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (token),

    KEY idx_guest_claims_user (user_id),
    KEY idx_guest_claims_lesson (lesson_id),
    UNIQUE KEY uq_guest_claims_session (session_id),

    CONSTRAINT fk_guest_claims_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT fk_guest_claims_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT fk_guest_claims_session
        FOREIGN KEY (session_id) REFERENCES learning_sessions(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;