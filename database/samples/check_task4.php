<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/bootstrap/app.php';
use App\Core\ApiException;
use App\Core\Database;
use App\Modules\Admin\Requests\ContentInput as Input;
use App\Modules\Admin\Services\ContentCsvImporter;
use App\Modules\Vocabulary\Repositories\ContentRepository;
$count=0;$db=null;
function check(bool $ok,string $label):void{global$count;if(!$ok)throw new RuntimeException('FAIL '.$label);$count++;echo'PASS '.$label.PHP_EOL;}
function rejects(callable $work,int $status,string $label):void{try{$work();}catch(ApiException $e){check($e->status===$status,$label);return;}throw new RuntimeException('FAIL expected rejection: '.$label);}
function csv(array $rows):string{$f=fopen('php://temp','w+');fputcsv($f,ContentCsvImporter::HEADERS,',','"','');foreach($rows as$row)fputcsv($f,$row,',','"','');rewind($f);$csv=stream_get_contents($f);fclose($f);return$csv;}
try{
$dsn=getenv('PART2_TEST_DSN');$db=$dsn?new PDO($dsn,getenv('PART2_TEST_USER')?:'root',getenv('PART2_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]):Database::connection();
$db->query('SELECT word_id FROM word_sources LIMIT 1');$db->beginTransaction();$r=new ContentRepository($db);$import=new ContentCsvImporter($r);
$position=(int)$r->rows('SELECT COALESCE(MAX(position),0)+1 n FROM topics')[0]['n'];$r->execute('INSERT INTO topics(name,position) VALUES(?,?)',['QA CSV',$position]);$topic=(int)$db->lastInsertId();
$row=[(string)$topic,'qa csv apple','táo kiểm thử','noun','','An apple.','','','','','','',''];
$before=(int)$r->rows('SELECT COUNT(*) n FROM words')[0]['n'];$preview=$import->preview("\xEF\xBB\xBF".csv([$row]));
check($preview['valid_count']===1&&$preview['errors']===[],'UTF-8 BOM preview');check((int)$r->rows('SELECT COUNT(*) n FROM words')[0]['n']===$before,'preview does not write database');
$quoted=$row;$quoted[5]="An example, with \"quotes\"\nand a second line.";$p=$import->preview(csv([$quoted]));check($p['rows'][0]['word']['example']===$quoted[5],'quoted commas, quotes and multiline fields');
rejects(fn()=>$import->preview('wrong,header'),422,'reject missing or reordered headers');
rejects(fn()=>$import->preview(str_replace(',',';',csv([$row]))),422,'reject wrong delimiter');
rejects(fn()=>$import->preview("\xff"),422,'reject invalid UTF-8');
rejects(fn()=>$import->preview(str_repeat('a',1048577)),413,'enforce 1 MiB limit');
rejects(fn()=>$import->preview(implode(',',ContentCsvImporter::HEADERS)."\n\"unclosed"),422,'reject malformed CSV quoting');
rejects(fn()=>$import->preview(csv([])),422,'reject empty dataset');
$bad=$row;array_pop($bad);$p=$import->preview(csv([$bad]));check($p['errors'][0]['record']===2&&$p['rows']===[],'report wrong column count at record 2');
$bad=$row;$bad[2]='';$p=$import->preview(csv([$row,$bad]));check($p['rows']===[]&&$p['valid_count']===0,'one invalid record blocks entire file');
$p=$import->preview(csv([$row,$row]));check(count($p['errors'])===1,'detect duplicate inside file');
$r->execute('UPDATE topics SET is_active=0 WHERE id=?',[$topic]);$p=$import->preview(csv([$row]));check(count($p['errors'])===1,'reject hidden topic');$r->execute('UPDATE topics SET is_active=1 WHERE id=?',[$topic]);
$bad=$row;$bad[7]='https://example.invalid/image.png';$p=$import->preview(csv([$bad]));check(count($p['errors'])===1,'media requires source and license');
$second=$row;$second[1]='qa csv second';$p=$import->preview(csv([$row,$second]));$r->saveWord(null,$p['rows'][1]['word'],$p['rows'][1]['source']);
rejects(fn()=>$import->commit($p['rows']),409,'recheck duplicate introduced after preview');check(!$r->rows('SELECT id FROM words WHERE topic_id=? AND english=?',[$topic,$row[1]]),'failed commit rolls back earlier rows');
$result=$import->commit($preview['rows']);$wordId=$result['word_ids'][0];$w=$r->get('words',$wordId);check($result['imported']===1&&$w['vietnamese']===$row[2],'commit uses same words schema as Admin');
$meta=json_decode($r->rows('SELECT metadata_json FROM word_sources WHERE word_id=?',[$wordId])[0]['metadata_json'],true);check($meta['provider']==='csv_admin','CSV provenance saved');
$p=$import->preview(csv([$row]));check(count($p['errors'])===1,'existing database word rejected');
$w['is_active']=0;$data=Input::word($w);$r->saveWord($wordId,$data['word'],$data['source']);$p=$import->preview(csv([$row]));check(count($p['errors'])===1,'hidden database word also rejected');
// Regression: matching is supplemental in Danh PR #20, not a main question.
$lesson=Input::lesson(['topic_id'=>$topic,'name'=>'QA matching only','level'=>1,'position'=>1,'question_count'=>1,'pass_percent'=>70,'xp_reward'=>10,'word_ids'=>[$wordId],'is_active'=>0]);$lessonId=$r->saveLesson(null,$lesson);
check($r->mainExercises($lessonId)===[],'main question counter starts empty');
$rows=[];for($i=0;$i<1001;$i++){$x=$row;$x[1]='qa limit '.strtr((string)$i,'0123456789','abcdefghij');$rows[]=$x;}
rejects(fn()=>$import->preview(csv($rows)),422,'enforce 1000 record limit');
$db->rollBack();check(!$r->rows('SELECT id FROM topics WHERE id=?',[$topic]),'all fixtures rolled back');echo'RESULT: '.$count.' task 4 checks passed.'.PHP_EOL;
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
