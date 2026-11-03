-- Du lieu mau noi dung. Khong tao tai khoan/mac dinh mat khau.
-- Chi import mot lan vao schema rong. Khong INSERT IGNORE/REPLACE.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
START TRANSACTION;


INSERT INTO topics (id,name,description,position) VALUES (1,'Đồ uống và giao tiếp','Bài mẫu để kiểm tra học từ qua trò chơi.',1);

INSERT INTO phonemes (id,symbol,type,description,position) VALUES
(1,'m','consonant','Âm m',1),
(2,'ɪ','vowel','Âm ɪ',2),
(3,'l','consonant','Âm l',3),
(4,'k','consonant','Âm k',4),
(5,'t','consonant','Âm t',5),
(6,'iː','vowel','Âm iː',6);

INSERT INTO words (id,topic_id,english,vietnamese,part_of_speech,ipa,example,example_translation,image,audio) VALUES
(1,1,'milk','sữa','noun','mɪlk','I drink milk.','Tôi uống sữa.',NULL,NULL),
(2,1,'tea','trà','noun','tiː','Tea with milk.','Trà với sữa.',NULL,NULL),
(3,1,'coffee','cà phê','noun',NULL,'Coffee with milk.','Cà phê với sữa.',NULL,NULL),
(4,1,'sugar','đường','noun',NULL,'Tea with sugar.','Trà với đường.',NULL,NULL),
(5,1,'hello','xin chào','interjection',NULL,'Hello, Anna!','Xin chào Anna!',NULL,NULL);

INSERT INTO word_phonemes (word_id,position,phoneme_id,is_example) VALUES (1,1,1,1),(1,2,2,1),(1,3,3,1),(1,4,4,1),(2,1,5,1),(2,2,6,1);

INSERT INTO lessons (id,topic_id,name,level,position,question_count,pass_percent,xp_reward) VALUES (1,1,'Đồ uống — cấp 1',1,1,10,70.00,10),(2,1,'Đồ uống — cấp 2',2,2,10,70.00,15);

INSERT INTO lesson_words (lesson_id,word_id) VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(2,3),(2,4),(2,5);

INSERT INTO quest_definitions (id,name,metric,target,xp_reward) VALUES (1,'Hoàn thành 1 bài học','lessons_completed',1,5),(2,'Ôn lại 5 từ','words_reviewed',5,5),(3,'Kiếm 20 XP từ hoạt động học','xp_earned',20,5);

INSERT INTO exercises (id,lesson_id,word_id,type,difficulty,content_json,answer_json,is_active) VALUES
(1,1,1,'choose_meaning',1,'{"schema_version":1,"instruction":"Chọn từ tiếng Anh đúng","prompt":"sữa","options":[{"id":"a","text":"milk"},{"id":"b","text":"tea"},{"id":"c","text":"coffee"}],"target_word_ids":[1]}','{"correct_option_ids":["a"]}',1),
(6,1,1,'translate_text',1,'{"schema_version":1,"instruction":"Viết từ tiếng Anh","prompt":"sữa","target_word_ids":[1]}','{"accepted_answers":["milk"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(2,1,2,'choose_meaning',1,'{"schema_version":1,"instruction":"Chọn từ tiếng Anh đúng","prompt":"trà","options":[{"id":"a","text":"tea"},{"id":"b","text":"coffee"},{"id":"c","text":"sugar"}],"target_word_ids":[2]}','{"correct_option_ids":["a"]}',1),
(7,1,2,'translate_text',1,'{"schema_version":1,"instruction":"Viết từ tiếng Anh","prompt":"trà","target_word_ids":[2]}','{"accepted_answers":["tea"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(3,1,3,'choose_meaning',1,'{"schema_version":1,"instruction":"Chọn từ tiếng Anh đúng","prompt":"cà phê","options":[{"id":"a","text":"coffee"},{"id":"b","text":"sugar"},{"id":"c","text":"hello"}],"target_word_ids":[3]}','{"correct_option_ids":["a"]}',1),
(8,1,3,'translate_text',1,'{"schema_version":1,"instruction":"Viết từ tiếng Anh","prompt":"cà phê","target_word_ids":[3]}','{"accepted_answers":["coffee"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(4,1,4,'choose_meaning',1,'{"schema_version":1,"instruction":"Chọn từ tiếng Anh đúng","prompt":"đường","options":[{"id":"a","text":"sugar"},{"id":"b","text":"hello"},{"id":"c","text":"milk"}],"target_word_ids":[4]}','{"correct_option_ids":["a"]}',1),
(9,1,4,'translate_text',1,'{"schema_version":1,"instruction":"Viết từ tiếng Anh","prompt":"đường","target_word_ids":[4]}','{"accepted_answers":["sugar"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(5,1,5,'choose_meaning',1,'{"schema_version":1,"instruction":"Chọn từ tiếng Anh đúng","prompt":"xin chào","options":[{"id":"a","text":"hello"},{"id":"b","text":"milk"},{"id":"c","text":"tea"}],"target_word_ids":[5]}','{"correct_option_ids":["a"]}',1),
(10,1,5,'translate_text',1,'{"schema_version":1,"instruction":"Viết từ tiếng Anh","prompt":"xin chào","target_word_ids":[5]}','{"accepted_answers":["hello"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(11,1,NULL,'match_pairs',1,'{"schema_version":1,"instruction":"Chọn cặp từ","left":[{"id":"L1","text":"sữa"},{"id":"L2","text":"trà"},{"id":"L3","text":"cà phê"},{"id":"L4","text":"đường"},{"id":"L5","text":"xin chào"}],"right":[{"id":"R5","text":"hello"},{"id":"R4","text":"sugar"},{"id":"R3","text":"coffee"},{"id":"R2","text":"tea"},{"id":"R1","text":"milk"}],"target_word_ids":[1,2,3,4,5]}','{"pairs":[{"left_id":"L1","right_id":"R1","word_id":1},{"left_id":"L2","right_id":"R2","word_id":2},{"left_id":"L3","right_id":"R3","word_id":3},{"left_id":"L4","right_id":"R4","word_id":4},{"left_id":"L5","right_id":"R5","word_id":5}]}',1),
(12,2,NULL,'token_order',2,'{"schema_version":1,"instruction":"Sắp xếp thành câu","tokens":[{"id":"t1","text":"with"},{"id":"t2","text":"Tea"},{"id":"t3","text":"milk"}],"target_word_ids":[1,2]}','{"accepted_sequences":[["t2","t1","t3"]]}',1),
(13,2,1,'fill_blank',2,'{"schema_version":1,"instruction":"Điền từ còn thiếu","prompt":"I drink ____.","target_word_ids":[1]}','{"accepted_answers":["milk"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(14,2,1,'translate_text',2,'{"schema_version":1,"instruction":"Viết nghĩa tiếng Việt","prompt":"milk","target_word_ids":[1]}','{"accepted_answers":["sữa"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(15,2,2,'translate_text',2,'{"schema_version":1,"instruction":"Viết nghĩa tiếng Việt","prompt":"tea","target_word_ids":[2]}','{"accepted_answers":["trà"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(16,2,3,'translate_text',2,'{"schema_version":1,"instruction":"Viết nghĩa tiếng Việt","prompt":"coffee","target_word_ids":[3]}','{"accepted_answers":["cà phê"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(17,2,4,'translate_text',2,'{"schema_version":1,"instruction":"Viết nghĩa tiếng Việt","prompt":"sugar","target_word_ids":[4]}','{"accepted_answers":["đường"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(18,2,5,'translate_text',2,'{"schema_version":1,"instruction":"Viết nghĩa tiếng Việt","prompt":"hello","target_word_ids":[5]}','{"accepted_answers":["xin chào"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(19,2,1,'fill_blank',2,'{"schema_version":1,"instruction":"Điền từ tiếng Anh phù hợp","prompt":"sữa = ____","target_word_ids":[1]}','{"accepted_answers":["milk"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(20,2,2,'fill_blank',2,'{"schema_version":1,"instruction":"Điền từ tiếng Anh phù hợp","prompt":"trà = ____","target_word_ids":[2]}','{"accepted_answers":["tea"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(21,2,3,'fill_blank',2,'{"schema_version":1,"instruction":"Điền từ tiếng Anh phù hợp","prompt":"cà phê = ____","target_word_ids":[3]}','{"accepted_answers":["coffee"],"normalization":{"trim":true,"case_sensitive":false}}',1),
(22,2,NULL,'match_pairs',2,'{"schema_version":1,"instruction":"Chọn cặp từ","left":[{"id":"L1","text":"sữa"},{"id":"L2","text":"trà"},{"id":"L3","text":"cà phê"},{"id":"L4","text":"đường"},{"id":"L5","text":"xin chào"}],"right":[{"id":"R5","text":"hello"},{"id":"R4","text":"sugar"},{"id":"R3","text":"coffee"},{"id":"R2","text":"tea"},{"id":"R1","text":"milk"}],"target_word_ids":[1,2,3,4,5]}','{"pairs":[{"left_id":"L1","right_id":"R1","word_id":1},{"left_id":"L2","right_id":"R2","word_id":2},{"left_id":"L3","right_id":"R3","word_id":3},{"left_id":"L4","right_id":"R4","word_id":4},{"left_id":"L5","right_id":"R5","word_id":5}]}',1);

COMMIT;

-- Anh/audio de NULL: chua cung cap tai nguyen co quyen su dung.

-- Them audio/anh truoc khi bat choose_image/listen_select/listen_type/sound_discrimination.
