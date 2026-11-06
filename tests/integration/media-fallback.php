<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Core\Database;
use App\Modules\Learning\Repositories\LearningRepository;
use App\Modules\Learning\Services\{LearningService,ExerciseService};
$db=Database::connection();
if(!str_ends_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Requires an isolated _test database');
$repo=new LearningRepository($db);$grader=new ExerciseService();$lesson=null;
foreach($repo->getActiveLessons() as $candidate){if((int)$candidate['level']===1&&count($repo->getWordsByLessonId((int)$candidate['id']))>=4){$lesson=$candidate;break;}}
if($lesson===null)throw new RuntimeException('Seed four A1 words first');
$words=$repo->getWordsByLessonId((int)$lesson['id']);$target=$words[0];
$email='media-'.bin2hex(random_bytes(5)).'@example.test';$password='media-fallback-test-123';
$db->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)')->execute(['Media learner',$email,password_hash($password,PASSWORD_DEFAULT)]);$user=(int)$db->lastInsertId();
$state=(new LearningService($repo))->start($user,(int)$lesson['id']);$session=$state['id'];$questions=$repo->questions($session);
if(in_array('--short',$argv,true)){
 $keep=[(int)$questions[0]['id'],(int)$questions[1]['id']];
 $db->prepare('DELETE FROM session_questions WHERE session_id=? AND id NOT IN (?,?)')->execute([$session,...$keep]);
 $db->prepare("UPDATE learning_sessions SET total_questions=2,rules_json=JSON_SET(rules_json,'$.matching_required',false) WHERE id=?")->execute([$session]);
}
$private=[];
foreach(['listen_type','listen_select'] as $i=>$type){
 $options=[];foreach(array_slice($words,0,4) as $n=>$word)$options[]=['id'=>'choice-'.$n,'text'=>$word['vietnamese']];
 $snapshot=['type'=>$type,'target_word_ids'=>[(int)$target['id']],
  'content'=>['instruction'=>$type==='listen_type'?'Nghe và viết từ tiếng Anh':'Nghe và chọn nghĩa','prompt'=>'','audio'=>'/assets/media/audio/missing-test-recording.mp3'],
  'answer'=>$type==='listen_type'?['accepted_answers'=>[$target['english']],'normalization'=>['trim'=>true,'case_sensitive'=>false]]:['correct_option_ids'=>['choice-0']]];
 if($type==='listen_select')$snapshot['content']['options']=$options;
 $json=json_encode($snapshot,JSON_THROW_ON_ERROR);$private[]=$json;
 $db->prepare('UPDATE session_questions SET snapshot_json=? WHERE id=?')->execute([$json,$questions[$i]['id']]);
}
$path=getenv('MEDIA_FIXTURE_FILE')?:sys_get_temp_dir().'/doulingo-media-fixture.json';
file_put_contents($path,json_encode(['email'=>$email,'password'=>$password,'session'=>$session,'lesson'=>(int)$lesson['id'],
 'english'=>$target['english'],'vietnamese'=>$target['vietnamese'],'question_ids'=>array_map(static fn($q)=>(int)$q['id'],array_slice($questions,0,2))],JSON_THROW_ON_ERROR));
// A resume enriches only public presentation, including sessions created before this update.
$again=(new LearningService($repo))->getSession($user,$session);
if(empty($again['question']['content']['media_fallback'])||$again['current_hp']!==3||$again['answered_main']!==0)throw new RuntimeException('Resumed audio question cannot recover');
foreach(array_slice($repo->questions($session),0,2) as $i=>$q)if($q['snapshot_json']!==$private[$i])throw new RuntimeException('Presentation changed saved history');
echo "PASS: old in-progress audio sessions resume with a text equivalent and unchanged snapshot/hearts/progress\n";
