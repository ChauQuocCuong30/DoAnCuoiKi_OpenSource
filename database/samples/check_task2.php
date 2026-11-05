<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
use App\Core\ApiException;
use App\Core\Database;
use App\Modules\Admin\Requests\ContentInput as Input;
use App\Modules\Vocabulary\Repositories\ContentRepository;
$count = 0; $db = null;
function check(bool $ok, string $label): void {
    global $count; if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $count++; echo 'PASS ' . $label . PHP_EOL;
}
function rejects(callable $work, int $status, string $label): void {
    try { $work(); } catch (ApiException $e) { check($e->status === $status, $label); return; }
    throw new RuntimeException('FAIL expected rejection: ' . $label);
}
try {
    $dsn = getenv('PART2_TEST_DSN');
    $db = $dsn ? new PDO($dsn, getenv('PART2_TEST_USER') ?: 'root', getenv('PART2_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]) : Database::connection();
    $db->query('SELECT word_id FROM word_sources LIMIT 1');
    $db->beginTransaction(); $repo = new ContentRepository($db);
    $position = (int)$repo->rows('SELECT COALESCE(MAX(position),0)+1 n FROM topics')[0]['n'];
    $repo->execute('INSERT INTO topics(name,position,is_active) VALUES(?,?,1)', ['QA task 2', $position]);
    $topic = (int)$db->lastInsertId();
    $body = ['topic_id'=>$topic,'english'=>'qa vocabulary','vietnamese'=>'từ kiểm thử','part_of_speech'=>'noun','example'=>'This is a test.','is_active'=>1];
    $data = Input::word($body); $word=$data['word']; $source=$data['source'];
    $id = $repo->saveWord(null,$word,$source);
    check($repo->get('words',$id)['english']==='qa vocabulary','create word');
    check($repo->listing('words','kiểm thử',1,$topic)['total']===1,'search and topic filter');
    rejects(fn()=>$repo->saveWord(null,$word,$source),409,'reject duplicate');
    rejects(fn()=>Input::word(array_replace($body,['vietnamese'=>'   '])),422,'reject blank meaning');
    rejects(fn()=>Input::word(array_replace($body,['part_of_speech'=>'invalid'])),422,'validate part of speech');
    rejects(fn()=>Input::word(array_replace($body,['image'=>'http://example.invalid/a.png'])),422,'reject unsafe media URL');
    rejects(fn()=>Input::word(array_replace($body,['image'=>'https://example.invalid/a.png'])),422,'require media provenance');
    $word['example']='Updated example.';$repo->saveWord($id,$word,$source);
    check($repo->get('words',$id)['example']==='Updated example.','update keeps word ID');
    $word['is_active']=0;$repo->saveWord($id,$word,$source);
    rejects(fn()=>$repo->saveWord(null,$word,$source),409,'hidden words prevent duplicates');
    $word['is_active']=1;$repo->saveWord($id,$word,$source);
    check((int)$repo->get('words',$id)['is_active']===1,'restore word');
    $repo->execute('UPDATE topics SET is_active=0 WHERE id=?',[$topic]);
    rejects(fn()=>$repo->saveWord(null,$word,$source),422,'cannot add active word to hidden topic');
    $repo->execute('UPDATE topics SET is_active=1 WHERE id=?',[$topic]);
    $repo->execute('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',['QA',bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $user=(int)$db->lastInsertId();
    $repo->execute('INSERT INTO user_review_states(user_id,word_id,direction) VALUES(?,?,?)',[$user,$id,'en_vi']);
    $changed=$word;$changed['vietnamese']='nghĩa khác';
    rejects(fn()=>$repo->saveWord($id,$changed,$source),409,'protect meaning with history');
    $word['is_active']=0;$repo->saveWord($id,$word,$source);
    check(count($repo->rows('SELECT id FROM user_review_states WHERE word_id=?',[$id]))===1,'hide preserves learning history');
    check(count($repo->rows('SELECT word_id FROM word_sources WHERE word_id=?',[$id]))===1,'source metadata retained');
    $db->rollBack();check(!$repo->rows('SELECT id FROM topics WHERE id=?',[$topic]),'all fixtures rolled back');
    echo 'RESULT: '.$count.' task 2 checks passed.'.PHP_EOL;
} catch (Throwable $e) {
    if($db instanceof PDO && $db->inTransaction())$db->rollBack();
    fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);
}
