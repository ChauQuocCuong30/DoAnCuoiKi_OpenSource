<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Modules\Learning\Services\{VocabularyQuestionFactory, ExerciseService};
$words=[];
foreach(range(1,12) as $i) {
    $words[]=['id'=>$i,'english'=>'word'.$i,'vietnamese'=>'nghĩa '.$i,
        'example'=>'I learn word'.$i.'.','example_translation'=>'Tôi học từ '.$i.'.',
        'audio'=>'/assets/audio/test-'.$i.'.mp3','image'=>'/assets/images/test-'.$i.'.png'];
}
$factory=new VocabularyQuestionFactory(); $grader=new ExerciseService(); $types=[];
foreach(range(1,6) as $level) {
    foreach($factory->build($words,$level) as $row) {
        $snapshot=$grader->snapshot($row+['content_json'=>json_encode($row['content']),'answer_json'=>json_encode($row['answer'])],$words);
        if($snapshot===null) { throw new RuntimeException('Invalid '.$row['type'].' at level '.$level); }
        $types[$row['type']]=true;
    }
}
foreach(['choose_meaning','choose_image','fill_blank','listen_select','listen_type','token_order','match_pairs'] as $type) {
    if(!isset($types[$type])) { throw new RuntimeException('Missing '.$type); }
}
$plain=array_map(static fn($w)=>array_replace($w,['audio'=>null,'image'=>null,'example'=>null,'example_translation'=>null]),$words);
foreach(range(1,6) as $level) {
    $rows=$factory->build($plain,$level);
    if(count(array_filter($rows,static fn($r)=>$r['type']!=='match_pairs'))<24) { throw new RuntimeException('Missing media blocked a word'); }
    foreach($rows as $row) {
        if($grader->snapshot($row+['content_json'=>json_encode($row['content']),'answer_json'=>json_encode($row['answer'])],$plain)===null) { throw new RuntimeException('Invalid plain fallback'); }
    }
}
if($factory->build([$words[0]],1)!==[]) { throw new RuntimeException('Single word topic should not start'); }
$small=$factory->build(array_slice($plain,0,3),1);
foreach($small as $row) {
    if(isset($row['content']['options'])) { throw new RuntimeException('Less than four unique words must use a text exercise'); }
}
foreach(range(1,6) as $level) {
    $bank=[];
    foreach($factory->build($words,$level) as $row) {
        if(isset($row['content']['options']) && count($row['content']['options'])!==4) { throw new RuntimeException('MCQ needs exactly four choices'); }
        if($row['type']!=='match_pairs') { $bank[]=['snapshot'=>$grader->snapshot($row+['content_json'=>json_encode($row['content']),'answer_json'=>json_encode($row['answer'])],$words)]; }
    }
    $picked=App\Modules\Learning\Services\QuestionSelection::main($bank,10);
    if(count(array_unique(array_map(static fn($q)=>$q['snapshot']['target_word_ids'][0],$picked)))!==10) { throw new RuntimeException('A word repeated too early'); }
    if(count(array_unique(array_map(static fn($q)=>$q['snapshot']['type'],$picked)))<3) { throw new RuntimeException('Question types not mixed'); }
}
echo "PASS: all 6 CEFR levels produce valid PR question schemas, media-free fallback and empty topic guard\n";
