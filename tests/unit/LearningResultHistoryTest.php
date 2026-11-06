<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Modules\Learning\Controllers\LearningResultController;
$controller=new LearningResultController();$method=new ReflectionMethod($controller,'prepareQuestions');
$snapshot=['type'=>'translate_text','content'=>['instruction'=>'Viết từ tiếng Anh','prompt'=>'quả táo'],
    'answer'=>['accepted_answers'=>['apple']],'target_word_ids'=>[1]];
$row=['position'=>1,'exercise_id'=>999,'snapshot_json'=>json_encode($snapshot),'first_answer_json'=>'{"text":"apple"}','first_is_correct'=>1];
$result=$method->invoke($controller,[$row],[['id'=>1]]);
if(!$result[0]['available'] || $result[0]['given'][0]['text']!=='apple' || $result[0]['expected'][0]['text']!=='apple') {throw new RuntimeException('Retired generated exercises must keep their completed history');}
if($method->invoke($controller,[$row],[])[0]['available']) {throw new RuntimeException('Hidden vocabulary must remain unavailable');}
$row['snapshot_json']='not json';
if($method->invoke($controller,[$row],[['id'=>1]])[0]['available']) {throw new RuntimeException('Malformed stored snapshots must not render');}
echo "PASS: completed results survive question-bank retirement; hidden and invalid content remain excluded\n";
