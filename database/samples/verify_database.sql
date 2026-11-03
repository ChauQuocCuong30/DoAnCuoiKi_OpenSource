-- Chi doc. Chon database can kiem tra truoc khi chay.
SET NAMES utf8mb4;
SELECT VERSION() AS database_version, DATABASE() AS current_database;
SELECT COUNT(*) AS table_count_expected_18 FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';
SHOW TABLES;
SELECT 'topics' AS table_name, COUNT(*) AS row_count FROM topics
UNION ALL SELECT 'words',COUNT(*) FROM words
UNION ALL SELECT 'phonemes',COUNT(*) FROM phonemes
UNION ALL SELECT 'lessons',COUNT(*) FROM lessons
UNION ALL SELECT 'exercises',COUNT(*) FROM exercises
UNION ALL SELECT 'quest_definitions',COUNT(*) FROM quest_definitions
UNION ALL SELECT 'users',COUNT(*) FROM users;
SELECT l.id,l.name,l.question_count,
 SUM(CASE WHEN e.type <> 'match_pairs' AND e.is_active=1 THEN 1 ELSE 0 END) AS available_main_questions,
 SUM(CASE WHEN e.type = 'match_pairs' AND e.is_active=1 THEN 1 ELSE 0 END) AS available_matching_questions
 FROM lessons l LEFT JOIN exercises e ON e.lesson_id=l.id GROUP BY l.id,l.name,l.question_count;
SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE
 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
 ORDER BY TABLE_NAME,CONSTRAINT_TYPE;
-- Phai 0: cau hoi co target word khong thuoc bai.
SELECT COUNT(*) AS orphan_exercise_targets_expected_0 FROM exercises e
 LEFT JOIN lesson_words lw ON lw.lesson_id=e.lesson_id AND lw.word_id=e.word_id
 WHERE e.word_id IS NOT NULL AND lw.word_id IS NULL;
-- Phai hien dung Unicode: tieng Viet va IPA.
SELECT english,vietnamese,ipa FROM words ORDER BY id;
SELECT symbol,type FROM phonemes ORDER BY position;
