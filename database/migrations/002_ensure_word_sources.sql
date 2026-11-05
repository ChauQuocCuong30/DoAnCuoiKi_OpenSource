-- Chạy sau 001 trên DB hiện có; tương thích nếu đã chạy samples/002_word_sources.sql.
-- Không import lại schema/seed cũ, không thay đổi words hoặc lịch sử học.
CREATE TABLE IF NOT EXISTS word_sources (
    word_id BIGINT UNSIGNED NOT NULL,
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (word_id),
    CONSTRAINT fk_word_sources_word FOREIGN KEY (word_id) REFERENCES words(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_word_sources_json CHECK (JSON_VALID(metadata_json) AND JSON_TYPE(metadata_json) = 'OBJECT')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
