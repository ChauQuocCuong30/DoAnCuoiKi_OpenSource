-- Questionnaire only: no XP, completed lessons or automatic CEFR unlocks.
CREATE TABLE IF NOT EXISTS user_onboarding (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    answers_json LONGTEXT NOT NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_onboarding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT ck_onboarding_json CHECK (JSON_VALID(answers_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
