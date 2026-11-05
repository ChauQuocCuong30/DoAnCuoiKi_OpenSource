-- Bổ sung trên database hiện có, chạy một lần.
-- LIÊN QUAN CƯỜNG:
-- Khi tích hợp, đưa SQL này vào chuỗi migration chung.
-- Không sửa hoặc import lại SQL khởi tạo.

CREATE TABLE word_sources (
    word_id BIGINT UNSIGNED NOT NULL,
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (word_id),

    CONSTRAINT fk_word_sources_word
        FOREIGN KEY (word_id)
        REFERENCES words(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT ck_word_sources_json
        CHECK (
            JSON_VALID(metadata_json)
            AND JSON_TYPE(metadata_json) = 'OBJECT'
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;