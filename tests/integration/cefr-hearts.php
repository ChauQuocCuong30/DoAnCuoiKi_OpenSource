<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Core\{Database,ApiException,Session};
use App\Modules\Admin\Services\ContentCsvImporter;
use App\Modules\Vocabulary\Repositories\ContentRepository;
use App\Modules\Learning\Repositories\LearningRepository;
use App\Modules\Learning\Services\{LearningService,HeartPracticeService,ExerciseService};
use App\Modules\Users\Repositories\UserRepository;
use App\Modules\Auth\Services\AuthService;
use App\Shared\Cefr;
function check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function rows(PDO $db,string $sql,array $args=[]): array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function failCode(callable $fn,string $code): void {try{$fn();throw new RuntimeException('Expected '.$code);}catch(ApiException $e){check($e->errorCode===$code,$code);}}
function bodyFor(PDO $db,array $state,bool $wrong=false): array {
 $q=rows($db,'SELECT snapshot_json FROM session_questions WHERE id=?',[$state['question']['id']])[0];$s=json_decode($q['snapshot_json'],true);$a=$s['answer'];
 if(isset($a['correct_option_ids'])) { $id=$a['correct_option_ids'][0];if($wrong){foreach($s['content']['options']as$o){if($o['id']!==$id){$id=$o['id'];break;}}}$answer=['option_id'=>$id]; }
 elseif(isset($a['accepted_answers']))$answer=['text'=>$wrong?'unrelated incorrect response':$a['accepted_answers'][0]];
 elseif(isset($a['accepted_sequences']))$answer=['token_ids'=>$wrong?array_reverse($a['accepted_sequences'][0]):$a['accepted_sequences'][0]];
 else { $pairs=array_map(static fn($p)=>['left_id'=>$p['left_id'],'right_id'=>$p['right_id']],$a['pairs']);if($wrong){$tmp=$pairs[0]['right_id'];$pairs[0]['right_id']=$pairs[1]['right_id'];$pairs[1]['right_id']=$tmp;}$answer=['pairs'=>$pairs]; }
 return ['question_id'=>$state['question']['id'],'request_key'=>bin2hex(random_bytes(16)),'answer'=>$answer];
}
Session::start();$db=Database::connection();
check(str_ends_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'_test')&&Cefr::installed($db),'Disposable database with migration 006');
$position=(int)$db->query('SELECT COALESCE(MAX(position),0)+1 FROM topics')->fetchColumn();
$db->prepare('INSERT INTO topics(name,position) VALUES(?,?)')->execute(['CEFR integration',$position]);$topic=(int)$db->lastInsertId();
$content=new ContentRepository($db);$importer=new ContentCsvImporter($content);
$csv="english,vietnamese,part_of_speech,example,example_translation,cefr_level\n";
foreach(Cefr::LEVELS as $band)foreach(['alpha','bravo','charlie','delta','echo','foxtrot','golf','hotel']as$i=>$word){$csv.="$word,nghĩa $i,noun,I learn $word.,Tôi học từ $i.,$band\n";}
$draft=$importer->preview($csv,'skip',$topic);check($draft['valid_count']===48,'CSV imports multiple CEFR bands in one topic');
check($importer->commit($draft['rows'],'skip')['imported']===48,'CEFR participates in duplicate identity');
$repo=new LearningRepository($db);$service=new LearningService($repo);
$lessons=array_values(array_filter($repo->getActiveLessons(),static fn($l)=>(int)$l['topic_id']===$topic));
check(count($lessons)===6,'A1–C2 creates six stable lessons');
$grader=new ExerciseService();
foreach($lessons as $i=>$lesson){
 $words=$repo->getWordsByLessonId((int)$lesson['id']);check(count($words)===8&&array_unique(array_column($words,'cefr_level'))===[Cefr::LEVELS[$i]],'Vocabulary isolated to '.Cefr::LEVELS[$i]);
 $types=[];foreach($repo->exercises((int)$lesson['id'])as$exercise){$s=$grader->snapshot($exercise,$words);if($s===null)throw new RuntimeException('Invalid '.$exercise['type'].' schema');$types[$s['type']]=true;if(isset($s['content']['options'])&&count($s['content']['options'])!==4)throw new RuntimeException('Expected four options');}
 check(count($types)>=4,'Several question families in each CEFR band');
}
check($content->listing('words','',1,$topic,'C2')['total']===8,'Admin CEFR filter uses the persisted word band');
$ids=[];$emails=[];$insert=$db->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
foreach(['A','B']as$name){$email='cefr-'.strtolower($name).'-'.bin2hex(random_bytes(4)).'@example.test';$insert->execute(['CEFR '.$name,$email,password_hash('cefr-test-password-123',PASSWORD_DEFAULT)]);$ids[]=(int)$db->lastInsertId();$emails[]=$email;}
[$user,$other]=$ids;$lesson=(int)$lessons[0]['id'];
Session::login($user);$s=$service->start($user,$lesson);$session=$s['id'];$s=$service->answer($user,$session,bodyFor($db,$s))['session'];$question=$s['question'];
// Simulate an older uniform/three-choice session before installing this update.
$rules=json_decode(rows($db,'SELECT rules_json FROM learning_sessions WHERE id=?',[$session])[0]['rules_json'],true);unset($rules['question_bank_version']);
$db->prepare('UPDATE learning_sessions SET rules_json=? WHERE id=?')->execute([json_encode($rules),$session]);
$doneBefore=rows($db,'SELECT id,snapshot_json,resolved_at FROM session_questions WHERE session_id=? AND resolved_at IS NOT NULL',[$session]);
$upgrade=new App\Modules\Learning\Services\PendingLessonUpgrade($db);
check($upgrade->all()>0,'Installer updates untouched questions without restarting the attempt');
check(rows($db,'SELECT id,snapshot_json,resolved_at FROM session_questions WHERE session_id=? AND resolved_at IS NOT NULL',[$session])===$doneBefore,'Answered snapshots and previous result remain unchanged');
check($upgrade->all()===0,'Pending-question installation upgrade is idempotent');
$s=$service->getSession($user,$session);$question=$s['question'];
Session::destroy();Session::login($user);$service=new LearningService(new LearningRepository($db));$resumed=$service->start($user,$lesson);
check($resumed['id']===$session&&$resumed['answered_main']===1&&$resumed['question']===$question,'Logout/login resumes exact DB snapshot and position');
$path=$service->getLearningPathData($repo->lockUser($user));check((int)$path['resumeSession']['id']===$session&&count($path['history'])===1,'Path exposes the most recent unfinished lesson and history');
$separate=$service->start($other,$lesson);check($separate['answered_main']===0&&$separate['id']!==$session,'Another account starts with independent progress');
failCode(fn()=>$service->getSession($other,$session),'SESSION_NOT_FOUND');
for($i=0;$i<3;$i++)$resumed=$service->answer($user,$session,bodyFor($db,$resumed,true))['session'];
check($resumed['current_hp']===0&&$resumed['status']==='failed','Three lesson errors persist zero hearts and end the attempt');
$heart=new HeartPracticeService($db);$overview=$heart->summary($user);check(count($overview['mistakes'])===3&&$overview['current_hp']===0,'Mistakes are saved to the owning account');
check($heart->summary($other)['mistakes']===[],'Mistake queues are isolated between accounts');
$practice=$heart->start($user);$practiceId=$practice['id'];
$bad=bodyFor($db,$practice,true);$r=$heart->answer($user,$practiceId,$bad);
check(!$r['is_correct']&&$r['session']['current_hp']===0&&$r['session']['answered_main']===0,'Wrong heart practice answer costs no heart');
check($heart->answer($user,$practiceId,$bad)['replayed'],'Heart practice request retry is idempotent');
Session::destroy();Session::login($user);
check($heart->start($user)['id']===$practiceId,'Heart practice itself survives logout/login');
while($practice['status']==='in_progress'){$last=bodyFor($db,$practice);$practice=$heart->answer($user,$practiceId,$last)['session'];}
check($practice['hearts_added']===1&&$practice['current_hp']===1,'Correct mistake review earns one heart');
$heart->answer($user,$practiceId,$last);
check(count(rows($db,'SELECT session_id FROM heart_practice_rewards WHERE session_id=?',[$practiceId]))===1&&$heart->summary($user)['current_hp']===1,'Final answer replay cannot award another heart');
failCode(fn()=>$heart->answer($other,$practiceId,$last),'SESSION_NOT_FOUND');
failCode(fn()=>$heart->start($user),'NO_MISTAKES');
check(count(rows($db,'SELECT id FROM xp_transactions WHERE user_id=?',[$user]))===0,'Heart recovery grants no learning XP');
$due=gmdate('Y-m-d H:i:s',time()-61*60);$db->prepare('UPDATE users SET current_hp=0,next_hp_at=? WHERE id=?')->execute([$due,$user]);
Session::destroy();Session::login($user);
$auth=new AuthService(new UserRepository($db));$logged=$auth->login(['email'=>$emails[0],'password'=>'cefr-test-password-123']);
check($logged['current_hp']===3&&$logged['next_hp_at']===null,'Offline time regeneration is restored at login and capped at three hearts');
$again=$service->start($user,$lesson);$again=$service->answer($user,$again['id'],bodyFor($db,$again,true))['session'];
$cap=$heart->start($user);$db->prepare('UPDATE users SET current_hp=3,next_hp_at=NULL WHERE id=?')->execute([$user]);
$cap=$heart->answer($user,$cap['id'],bodyFor($db,$cap))['session'];
check($cap['status']==='completed'&&$cap['hearts_added']===0&&$cap['current_hp']===3,'A timer refill during practice cannot overfill hearts');
echo "CEFR, persisted learning and heart recovery integration complete.\n";
ob_end_flush();
