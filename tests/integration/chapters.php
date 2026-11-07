<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Core\{ApiException,Database};
use App\Modules\Learning\Repositories\{ChapterRepository,LearningRepository};
use App\Modules\Learning\Services\{ChapterChallengeService,LearningService,WordArtwork};
use App\Modules\Admin\Services\ContentCsvImporter;
use App\Modules\Vocabulary\Repositories\ContentRepository;
function check(bool $value,string $label): void { if(!$value)throw new RuntimeException($label);echo "PASS: $label\n"; }
function errorIs(callable $work,string $code): void { try{$work();throw new RuntimeException('Expected '.$code);}catch(ApiException $e){check($e->errorCode===$code,$code);} }
function answer(array $q,bool $correct=true): array {
    $a=$q['answer'];
    if(isset($a['correct_option_ids'])) {
        $id=$a['correct_option_ids'][0];
        if(!$correct) foreach($q['content']['options'] as $option)if($option['id']!==$id){$id=$option['id'];break;}
        return ['option_id'=>$id];
    }
    if(isset($a['accepted_sequences'])) return ['token_ids'=>$correct?$a['accepted_sequences'][0]:array_reverse($a['accepted_sequences'][0])];
    return ['text'=>$correct?$a['accepted_answers'][0]:'definitely incorrect'];
}
$db=Database::connection();
check(str_ends_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'_test'),'Disposable database only');
$repo=new LearningRepository($db);$chapters=new ChapterRepository($db);$service=new ChapterChallengeService($chapters,$repo);
$oldTopics=array_column($chapters->rows('SELECT id FROM topics WHERE is_active=1'),'id');
$db->exec('UPDATE topics SET is_active=0');
try {
    $password='chapter-test-password-123';$suffix=bin2hex(random_bytes(5));
    $users=[];
    foreach(['owner','other','browser','threshold'] as $role){
        $email='chapter-'.$role.'-'.$suffix.'@example.test';
        $q=$db->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute(['Chapter '.$role,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $users[$role]=['id'=>(int)$db->lastInsertId(),'email'=>$email];
    }
    $topics=[];$position=(int)$db->query('SELECT COALESCE(MAX(position),0)+1 FROM topics')->fetchColumn();
    $words=[['skill','kỹ năng','/skɪl/'],['water','nước','/ˈwɔːtə/'],['tea','trà','/tiː/'],['coffee','cà phê','/ˈkɒfi/']];
    foreach(['Foundations','Communication'] as $name){
        $q=$db->prepare('INSERT INTO topics(name,position) VALUES(?,?)');$q->execute(['Chapter '.$name.' '.$suffix,$position++]);$topic=(int)$db->lastInsertId();$topics[]=$topic;
        $stream=fopen('php://temp','w+');fputcsv($stream,['english','vietnamese','part_of_speech','ipa','example','example_translation','cefr_level'],',','"','');
        foreach(\App\Shared\Cefr::LEVELS as $band)foreach($words as [$en,$vi,$ipa])fputcsv($stream,[$en,$vi,'noun',$ipa,'I learn '.$en.'.','Tôi học '.$vi.'.',$band],',','"','');
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
        $importer=new ContentCsvImporter(new ContentRepository($db));$draft=$importer->preview($csv,'skip',$topic);$importer->commit($draft['rows'],'skip');
    }
    $all=$repo->getActiveLessons();$first=array_values(array_filter($all,fn($l)=>(int)$l['topic_id']===$topics[0]));$second=array_values(array_filter($all,fn($l)=>(int)$l['topic_id']===$topics[1]));
    $uid=$users['owner']['id'];$states=$chapters->states($uid);
    check($states[$topics[0]]['unlocked']&&!$states[$topics[1]]['unlocked'],'Only the first chapter initially opens');
    check($repo->unlocked($uid,$first[0])&&!$repo->unlocked($uid,$first[1]),'Lessons initially unlock in sequence');
    errorIs(fn()=>(new LearningService($repo))->start($uid,(int)$second[0]['id']),'LESSON_LOCKED');
    $attempt=$service->start($uid,$topics[1]);
    check($attempt['total']===12&&!str_contains(json_encode($attempt),'accepted_answers')&&!str_contains(json_encode($attempt),'correct_option_ids'),'Chapter test freezes 12 private-graded questions');
    check($service->start($uid,$topics[1])['id']===$attempt['id'],'Starting again resumes the same frozen challenge');
    errorIs(fn()=>$service->get($users['other']['id'],$attempt['id']),'ATTEMPT_NOT_FOUND');
    errorIs(fn()=>$service->answer($uid,$attempt['id'],['unlocked'=>true,'correct_count'=>12]),'VALIDATION_FAILED');
    $private=json_decode($chapters->attempt($uid,$attempt['id'])['questions_json'],true);
    foreach($private as $i=>$q){$body=['question_number'=>$i+1,'request_key'=>bin2hex(random_bytes(12)),'answer'=>answer($q,false)];$reply=$service->answer($uid,$attempt['id'],$body);}
    check($reply['attempt']['status']==='failed'&&!$chapters->states($uid)[$topics[1]]['unlocked'],'Failed challenge never unlocks a chapter');
    $attempt=$service->start($uid,$topics[1]);$private=json_decode($chapters->attempt($uid,$attempt['id'])['questions_json'],true);
    foreach($private as $i=>$q){
        $body=['question_number'=>$i+1,'request_key'=>bin2hex(random_bytes(12)),'answer'=>answer($q)];$reply=$service->answer($uid,$attempt['id'],$body);
        if($i===0){$replay=$service->answer($uid,$attempt['id'],$body);check($replay['replayed']&&$replay['attempt']['answered']===1,'A retried answer scores only once');}
    }
    check($reply['attempt']['unlocked']&&$reply['attempt']['status']==='completed','Passing challenge records a chapter unlock');
    foreach($second as $lesson)check($repo->unlocked($uid,$lesson),'Every lesson in the passed chapter opens');
    check(!$repo->unlocked($uid,$first[1]),'Passing one chapter does not open another chapter');
    check(!$chapters->states($users['other']['id'])[$topics[1]]['unlocked'],'Unlocks belong to one account');
    check((new ChapterRepository($db))->states($uid)[$topics[1]]['passed'],'Unlock survives a fresh repository/login');
    check($repo->completedLessons($uid)===[]&&$repo->stats($uid)['xp']===0,'A checkpoint grants no fake lesson completion or XP');
    check((int)$repo->lockUser($uid)['current_hp']===3,'A checkpoint leaves hearts unchanged');
    check(WordArtwork::image(['english'=>'skill'])==='/assets/media/images/vocabulary/skill.svg','Skill has its own illustration');
    foreach([9,10] as $score){
        $threshold=$service->start($users['threshold']['id'],$topics[0]);
        $items=json_decode($chapters->attempt($users['threshold']['id'],$threshold['id'])['questions_json'],true);
        foreach($items as $i=>$q)$result=$service->answer($users['threshold']['id'],$threshold['id'],['question_number'=>$i+1,'request_key'=>bin2hex(random_bytes(12)),'answer'=>answer($q,$i<$score)]);
        check($result['attempt']['correct_count']===$score&&$result['attempt']['unlocked']===($score===10),'80% threshold enforced at '.$score.'/12');
    }

    file_put_contents(getenv('CHAPTER_FIXTURE_FILE')?:sys_get_temp_dir().'/doulingo-chapter-fixture.json',json_encode([
        'email'=>$users['browser']['email'],'password'=>$password,'topics'=>$topics,'lesson'=>(int)$second[5]['id'],'words'=>$words,
    ],JSON_THROW_ON_ERROR));
} finally {
    foreach($oldTopics as $id)$db->prepare('UPDATE topics SET is_active=1 WHERE id=?')->execute([$id]);
}
