<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\{ApiException, Database};
use App\Modules\Admin\Requests\ContentInput;
use App\Modules\Admin\Services\{ContentCsvImporter, VocabularySamples};
use App\Modules\Vocabulary\Repositories\ContentRepository;

function check(bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS: $name\n";
}
function fails(callable $work, string $code = 'VALIDATION_FAILED'): void {
    try { $work(); throw new RuntimeException('Expected ' . $code); }
    catch (ApiException $e) { check($e->errorCode === $code, $code . ': ' . $e->getMessage()); }
}
$db = Database::connection();
if (!str_ends_with((string) $db->query('SELECT DATABASE()')->fetchColumn(), '_test')) {
    throw new RuntimeException('Use a disposable *_test database with migrations 001 and 002, seed 001.');
}
$repo = new ContentRepository($db);
$importer = new ContentCsvImporter($repo);
$samples = new VocabularySamples();
$before = $db->query('SELECT COUNT(*) FROM words')->fetchColumn();
$db->beginTransaction();
try {
    $position = (int) $db->query('SELECT COALESCE(MAX(position),0)+1 FROM topics')->fetchColumn();
    $repo->execute('INSERT INTO topics(name,position) VALUES(?,?)', ['Import transaction test', $position]);
    $topic = (int) $db->lastInsertId();
    $repo->execute('INSERT INTO topics(name,position,is_active) VALUES(?,?,0)', ['Hidden import test', $position + 1]);
    $hidden = (int) $db->lastInsertId();
    check(count($samples->catalog()) === 10 && array_sum(array_column($samples->catalog(), 'count')) === 200, '10 packs / 200 bilingual entries');
    foreach ($samples->catalog() as $pack) {
        $preview = $importer->preview($samples->csv($pack['key'], $topic), 'skip', $topic);
        check($preview['valid_count'] === 20 && !$preview['errors'], 'Valid bundled pack: ' . $pack['key']);
    }
    fails(fn () => $samples->csv('../not-a-pack', $topic), 'SAMPLE_NOT_FOUND');
    $preview = $importer->preview($samples->csv('drinks', $topic), 'skip', $topic);
    $result = $importer->commit($preview['rows'], 'skip');
    check($result['imported'] === 20 && $result['skipped'] === 0, 'One transaction imports a complete pack');
    $repeat = $importer->preview($samples->csv('drinks', $topic), 'skip', $topic);
    check($repeat['valid_count'] === 0 && $repeat['skipped_count'] === 20 && !$repeat['errors'], 'Reimport skips all existing words');
    $source = $repo->rows('SELECT metadata_json FROM word_sources WHERE word_id=?', [$result['word_ids'][0]]);
    check(count($source) === 1, 'Source metadata persisted');
    $books = array_column($repo->books(), null, 'id');
    check((int) $books[$topic]['word_count'] === 20 && (int) $books[$hidden]['word_count'] === 0, 'Books include empty and hidden topics with real counts');
    $repo->execute('UPDATE words SET is_active=0 WHERE id=?', [$result['word_ids'][0]]);
    $repeat = $importer->preview($samples->csv('drinks', $topic), 'skip', $topic);
    check($repeat['skipped_count'] === 20 && (int) $repo->get('words', $result['word_ids'][0])['is_active'] === 0, 'Hidden duplicates are skipped without restoring them');
    $books = array_column($repo->books(), null, 'id');
    check((int) $books[$topic]['active_word_count'] === 19, 'Active/hidden counts remain accurate');
    $header = "english,vietnamese,part_of_speech,example\n";
    $line = "apple,quả táo,noun,I eat an apple.\n";
    $double = $importer->preview("\xEF\xBB\xBF" . $header . $line . $line, 'skip', $topic);
    check($double['valid_count'] === 1 && $double['skipped_count'] === 1, 'BOM, minimal CSV and duplicate rows');
    $strict = $importer->preview($header . $line . $line, 'error', $topic);
    check(count($strict['errors']) === 1 && $strict['rows'] === [], 'Strict duplicate mode still rejects entire preview');
    $bad = $importer->preview($header . $line . "orange,,noun,An orange.\n", 'skip', $topic);
    check(count($bad['errors']) === 1 && $bad['valid_count'] === 0 && $bad['rows'] === [], 'Bad translations never produce a partial import draft');
    $mismatch = $importer->preview("topic_id," . $header . "$hidden," . $line, 'skip', $topic);
    check(count($mismatch['errors']) === 1, 'Reject a CSV mapped to the wrong book');
    $missing = $importer->preview($header . $line, 'skip', $hidden);
    check(count($missing['errors']) === 1, 'Hidden topics reject new data');
    fails(fn () => $importer->preview($header . $line)); // no selected topic or topic_id column
    fails(fn () => $importer->preview("english,english\na,b\n", 'skip', $topic));
    fails(fn () => $importer->preview($header . '"unterminated', 'skip', $topic));
    fails(fn () => $importer->preview($header . "bad\xFF", 'skip', $topic));
    fails(fn () => $importer->preview($header . str_repeat($line, 1001), 'skip', $topic));
    fails(fn () => $importer->preview(str_repeat('x', 1048577), 'skip', $topic), 'CSV_TOO_LARGE');
    fails(fn () => $importer->preview($header . $line, 'overwrite', $topic));
    $multi = $importer->preview($header . "pear,quả lê,noun,\"A pear,\nnot an apple.\"\n", 'skip', $topic);
    check($multi['valid_count'] === 1 && str_contains($multi['rows'][0]['word']['example'], "\n"), 'Quoted CSV commas and multiline fields');
    $ordered = $importer->preview("example,part_of_speech,vietnamese,english\nA pear.,noun,quả lê,pear\n", 'skip', $topic);
    check($ordered['valid_count'] === 1, 'CSV columns may be reordered');
    // A different admin commits a row after this preview; the locked commit must skip it.
    $race = $importer->preview($header . $line, 'skip', $topic);
    $repo->saveWord(null, $race['rows'][0]['word'], $race['rows'][0]['source']);
    check($importer->commit($race['rows'], 'skip')['skipped'] === 1, 'Recheck duplicates after acquiring topic locks');
    // Force a failure after the first insert. The outer batch must undo that insert too.
    $new = $importer->preview($header . "peach,quả đào,noun,A peach.\n", 'skip', $topic)['rows'][0];
    $count = $db->query('SELECT COUNT(*) FROM words')->fetchColumn();
    fails(fn () => $importer->commit([$new, $race['rows'][0]], 'error'), 'WORD_EXISTS');
    check($db->query('SELECT COUNT(*) FROM words')->fetchColumn() === $count, 'Failure rolls back the entire batch');
    $repo->execute('UPDATE topics SET is_active=0 WHERE id=?', [$topic]);
    fails(fn () => $importer->commit([$new], 'skip'), 'INVALID_TOPIC');
    $repo->execute('UPDATE topics SET is_active=1 WHERE id=?', [$topic]);
    // Existing learning-history protection remains enforced by saveWord.
    $repo->execute('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)', ['History fixture', 'admin-history@example.test', password_hash('test-only-123', PASSWORD_DEFAULT)]);
    $user = (int) $db->lastInsertId();
    $wordId = $result['word_ids'][1];
    $repo->execute("INSERT INTO user_review_states(user_id,word_id,direction) VALUES(?,?,'en_vi')", [$user, $wordId]);
    $existing = $repo->get('words', $wordId);
    $data = ContentInput::word(array_replace($existing, ['vietnamese' => 'nghĩa khác']));
    fails(fn () => $repo->saveWord($wordId, $data['word'], $data['source']), 'WORD_HAS_HISTORY');
    $data = ContentInput::word(array_replace($existing, ['is_active' => 0]));
    $repo->saveWord($wordId, $data['word'], $data['source']);
    check((int) $repo->rows('SELECT COUNT(*) n FROM user_review_states WHERE word_id=?', [$wordId])[0]['n'] === 1, 'Hide keeps the word ID and SM-2 state');
} finally {
    $db->rollBack();
}
check($db->query('SELECT COUNT(*) FROM words')->fetchColumn() === $before, 'Test data rolled back, existing vocabulary untouched');

// Dedicated HTTP/browser test fixtures. This script refuses non-test databases above.
$insert = $db->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');
foreach (['admin', 'user'] as $role) {
    $insert->execute(['Admin book test ' . $role, 'books-' . $role . '@example.test', password_hash('books-test-password-123', PASSWORD_DEFAULT), $role]);
}
$position = (int) $db->query('SELECT COALESCE(MAX(position),0)+1 FROM topics')->fetchColumn();
$repo->execute('INSERT INTO topics(name,description,position) VALUES(?,?,?)', ['Admin book test', 'Vocabulary fixture', $position]);
$repo->execute('INSERT INTO topics(name,position,is_active) VALUES(?,?,0)', ['Hidden admin book', $position + 1]);
$repo->execute('INSERT INTO topics(name,position) VALUES(?,?)', ['<img src=x onerror="window.adminXss=1">', $position + 2]);
echo "PASS: Isolated admin/browser fixtures ready\n";
