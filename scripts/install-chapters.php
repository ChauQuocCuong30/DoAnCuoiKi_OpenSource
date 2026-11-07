<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit('CLI only.'); }
require dirname(__DIR__).'/bootstrap/app.php';
$db=\App\Core\Database::connection();
if (!\App\Shared\Cefr::installed($db)) throw new RuntimeException('Cần migration 006 trước khi cài chương học.');
$db->query('SELECT lesson_id FROM learning_topic_catalog LIMIT 1');
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/008_chapter_challenges.sql');
if ($sql===false) throw new RuntimeException('Không đọc được migration 008.');
$db->exec($sql);
echo "PASS: chapter tables installed; existing words, sessions, XP and reviews retained.\n";
