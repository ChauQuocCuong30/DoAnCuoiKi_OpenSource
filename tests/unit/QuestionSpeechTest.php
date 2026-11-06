<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Modules\Learning\Services\{ExerciseService,VocabularyQuestionFactory};
use App\Modules\Admin\Requests\ContentInput;
$words=[];foreach(['cat','dog','bird','fish'] as $i=>$en)$words[]=['id'=>$i+1,'english'=>$en,'vietnamese'=>['mèo','chó','chim','cá'][$i],'example'=>'I like the '.$en.'.','example_translation'=>'Tôi thích con '.$en.'.','audio'=>null,'image'=>null];
$grader=new ExerciseService();
foreach(range(1,6) as $level){
 $directions=[];
 foreach((new VocabularyQuestionFactory())->build($words,$level) as $row){
  $snapshot=$grader->snapshot($row+['content_json'=>json_encode($row['content']),'answer_json'=>json_encode($row['answer'])],$words);
  if($snapshot===null)throw new RuntimeException('Invalid snapshot');
  if(str_contains(json_encode($snapshot['content']),'accepted_sequences'))throw new RuntimeException('Private answer exposed');
  if($row['type']==='token_order'){
   $lang=$snapshot['content']['answer_lang'];$directions[$lang]=true;
   if($snapshot['content']['prompt_lang']=== $lang)throw new RuntimeException('Wrong prompt language');
   $input=$grader->normalize($snapshot,['token_ids'=>$row['answer']['accepted_sequences'][0]]);
   if(!$grader->grade($snapshot,$input))throw new RuntimeException('Token grading failed');
   if(empty($grader->feedback($snapshot,$input)['correct_text']))throw new RuntimeException('Missing server correction');
  }
 }
 if(count($directions)!==2)throw new RuntimeException('Both sentence directions required in every level');
}
$word=['topic_id'=>1,'english'=>'cat','vietnamese'=>'mèo','part_of_speech'=>'noun','example'=>'A cat.','audio'=>'/assets/media/audio/cat.mp3','audio_source'=>'/assets/media/audio/cat.mp3','audio_license'=>'Owner-provided test recording'];
if(ContentInput::word($word)['word']['audio']!==$word['audio'])throw new RuntimeException('Safe local media rejected');
foreach(['/assets/media/../private.mp3','//example.test/cat.mp3','/assets/media/audio/a.php','/assets/media/audio/a%2fsecret.mp3'] as $bad){
 try{ContentInput::word(array_replace($word,['audio'=>$bad]));throw new RuntimeException('Unsafe local media accepted');}catch(App\Core\ApiException $e){if($e->status!==422)throw $e;}
}
echo "PASS: bilingual sentence banks in six levels, safe public language metadata, server corrections and local media validation\n";
// An audio outage changes only presentation: text is graded against the original private snapshot.
$target=$words[0];
foreach(['listen_type','listen_select'] as $type){
 $snapshot=['type'=>$type,'target_word_ids'=>[(int)$target['id']],
  'content'=>['audio'=>'/assets/media/audio/not-uploaded.mp3','prompt'=>'','options'=>[
   ['id'=>'a','text'=>$target['vietnamese']],['id'=>'b','text'=>'sai'],['id'=>'c','text'=>'khác'],['id'=>'d','text'=>'không đúng']]],
  'answer'=>$type==='listen_type'?['accepted_answers'=>[$target['english']],'normalization'=>['trim'=>true,'case_sensitive'=>false]]:['correct_option_ids'=>['a']]];
 if($type==='listen_type')unset($snapshot['content']['options']);
 $before=json_encode($snapshot);$public=$grader->publicContent($snapshot,$words);
 if(empty($public['media_fallback'])||json_encode($snapshot)!==$before)throw new RuntimeException('Fallback must preserve private history');
 if(str_contains(json_encode($public),'accepted_answers')||str_contains(json_encode($public),'correct_option_ids'))throw new RuntimeException('Private answers exposed');
 $right=$type==='listen_type'?['text'=>$target['english']]:['option_id'=>'a'];
 $wrong=$type==='listen_type'?['text'=>'incorrect']:['option_id'=>'b'];
 if(!$grader->grade($snapshot,$grader->normalize($snapshot,$right))||$grader->grade($snapshot,$grader->normalize($snapshot,$wrong)))throw new RuntimeException('Fallback bypassed grading');
}
echo "PASS: missing audio has a text equivalent; private snapshots and correct/wrong grading are preserved\n";
