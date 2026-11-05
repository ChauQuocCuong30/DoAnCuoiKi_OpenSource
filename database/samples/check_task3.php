<?php
declare(strict_types=1);
// Chỉ chạy CLI. Tất cả dữ liệu thử được rollback.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
use App\Core\ApiException;
use App\Core\Database;
use App\Modules\Admin\Requests\ContentInput as Input;
use App\Modules\Vocabulary\Repositories\ContentRepository;
$count = 0; $db = null;
function check(bool $ok, string $label): void {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
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
    $db->beginTransaction(); $r = new ContentRepository($db);
    $position=(int)$r->rows('SELECT COALESCE(MAX(position),0)+1 n FROM topics')[0]['n'];
    $r->execute('INSERT INTO topics(name,position) VALUES(?,?)',['QA task 3',$position]);$topic=(int)$db->lastInsertId();
    $words=[];
    foreach(['qa alpha','qa beta'] as $english){
        $input=Input::word(['topic_id'=>$topic,'english'=>$english,'vietnamese'=>'Nghĩa '.$english,'part_of_speech'=>'noun','example'=>'An example.','is_active'=>1]);
        $words[]=$r->saveWord(null,$input['word'],$input['source']);
    }
    [$w1,$w2]=$words;
    $body=['topic_id'=>$topic,'name'=>'QA lesson','level'=>1,'position'=>1,'question_count'=>1,'pass_percent'=>'70','xp_reward'=>10,'word_ids'=>$words,'is_active'=>0];
    $lesson=Input::lesson($body);$id=$r->saveLesson(null,$lesson);
    check($r->get('lessons',$id)['word_ids']===$words,'create draft lesson and assign words');
    rejects(fn()=>$r->saveLesson(null,$lesson),409,'reject duplicate lesson position');
    rejects(fn()=>Input::lesson(array_replace($body,['pass_percent'=>'100'])),422,'validate pass percentage');
    rejects(fn()=>Input::lesson(array_replace($body,['level'=>6])),422,'validate level range');
    rejects(fn()=>$r->saveLesson($id,array_replace($lesson,['is_active'=>1])),422,'cannot publish without enough valid questions');
    $r->execute('INSERT INTO topics(name,position) VALUES(?,?)',['Other QA topic',$position+1]);$otherTopic=(int)$db->lastInsertId();
    $cross=Input::word(['topic_id'=>$otherTopic,'english'=>'qa outside','vietnamese'=>'Ngoài chủ đề','part_of_speech'=>'noun','example'=>'Another example.']);
    $outside=$r->saveWord(null,$cross['word'],$cross['source']);
    rejects(fn()=>$r->saveLesson($id,array_replace($lesson,['word_ids'=>[$outside]])),422,'lesson words must belong to same topic');
    $examples=json_decode(file_get_contents(__DIR__.'/exercise_examples.json'),true,32,JSON_THROW_ON_ERROR);
    $questions=[];$inputs=[];
    foreach($examples as $type=>$example){
        $example['lesson_id']=$id;
        $example['word_id']=$example['word_id']===null?null:$w1;
        $example['is_active']=1;
        $example['content_json']['target_word_ids']=array_map(static fn($v)=>$v===1?$w1:$w2,$example['content_json']['target_word_ids']);
        if(isset($example['answer_json']['pairs']))foreach($example['answer_json']['pairs']as &$pair)$pair['word_id']=$pair['word_id']===1?$w1:$w2;
        unset($pair);
        $inputs[$type]=$example;
        $questions[$type]=$r->saveExercise(null,Input::exercise($example));
        check($questions[$type]>0,'validate and save '.$type);
    }
    $bad=$inputs['choose_meaning'];$bad['answer_json']['correct_option_ids']=['not-an-option'];
    rejects(fn()=>Input::exercise($bad),422,'answer must exist in options');
    $bad=$inputs['choose_meaning'];$bad['content_json']['answer_json']=['secret'];
    rejects(fn()=>Input::exercise($bad),422,'reject answers embedded in question content');
    $bad=$inputs['fill_blank'];$bad['content_json']['prompt']='No blank';
    rejects(fn()=>Input::exercise($bad),422,'fill blank needs blank marker');
    $bad=$inputs['listen_type'];unset($bad['content_json']['audio']);
    rejects(fn()=>Input::exercise($bad),422,'listening needs audio');
    $bad=$inputs['token_order'];$bad['answer_json']['accepted_sequences']=[['t1','t1','t3']];
    rejects(fn()=>Input::exercise($bad),422,'token answer must be a permutation');
    $bad=$inputs['translate_text'];$bad['word_id']=$outside;$bad['content_json']['target_word_ids']=[$outside];
    rejects(fn()=>$r->saveExercise(null,Input::exercise($bad)),422,'question target must be assigned to lesson');
    rejects(fn()=>$r->saveLesson($id,array_replace($lesson,['word_ids'=>[$w1]])),409,'cannot unlink word still referenced by question');
    $r->saveLesson($id,array_replace($lesson,['is_active'=>1]));
    check((int)$r->get('lessons',$id)['is_active']===1,'publish ready lesson');
    $changed=$inputs['choose_meaning'];$changed['content_json']['prompt']='Updated prompt';
    $r->saveExercise($questions['choose_meaning'],Input::exercise($changed));
    check(json_decode($r->get('exercises',$questions['choose_meaning'])['content_json'],true)['prompt']==='Updated prompt','edit unused question keeps ID');
    $r->execute('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',['QA',bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$user=(int)$db->lastInsertId();
    $r->execute('INSERT INTO learning_sessions(user_id,lesson_id,rules_json) VALUES(?,?,?)',[$user,$id,'{}']);$session=(int)$db->lastInsertId();
    $r->execute('INSERT INTO session_questions(session_id,exercise_id,position,snapshot_json) VALUES(?,?,1,?)',[$session,$questions['choose_meaning'],'{}']);
    $bad=$changed;$bad['content_json']['prompt']='Different prompt';
    rejects(fn()=>$r->saveExercise($questions['choose_meaning'],Input::exercise($bad)),409,'protect used question content and answer');
    $changed['is_active']=0;$r->saveExercise($questions['choose_meaning'],Input::exercise($changed));
    check((int)$r->get('exercises',$questions['choose_meaning'])['is_active']===0,'hide used question without deleting it');
    check(count($r->rows('SELECT id FROM session_questions WHERE exercise_id=?',[$questions['choose_meaning']]))===1,'question history remains linked');
    $r->saveLesson($id,array_replace($lesson,['is_active'=>0]));
    check((int)$r->get('lessons',$id)['is_active']===0,'hide lesson without deleting words or questions');
    $db->rollBack();check(!$r->rows('SELECT id FROM topics WHERE id=?',[$topic]),'all fixtures rolled back');
    echo 'RESULT: '.$count.' task 3 checks passed.'.PHP_EOL;
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
