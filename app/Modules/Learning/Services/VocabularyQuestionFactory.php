<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;

/** All CEFR bands support the same exercise families; vocabulary determines the band. */
final class VocabularyQuestionFactory
{
    private function id(): string { return bin2hex(random_bytes(8)); }
    private function row(array $word,int $level,string $type,array $content,array $answer): array
    {
        return ['word_id'=>(int)$word['id'],'difficulty'=>$level,'type'=>$type,
            'content'=>['schema_version'=>1,'target_word_ids'=>[(int)$word['id']]]+$content,'answer'=>$answer];
    }
    private function options(array $word,array $pool,string $field): ?array
    {
        $correct=$this->id();$items=[['id'=>$correct,($field==='image'?'image':'text')=>$word[$field]]];
        $seen=[mb_strtolower(trim($word[$field]))=>true];
        shuffle($pool);
        foreach($pool as $other) {
            $value=trim((string)($other[$field]??''));$key=mb_strtolower($value);
            if($value==='' || isset($seen[$key]) || mb_strtolower($other['english'])===mb_strtolower($word['english'])
                || mb_strtolower(trim($other['vietnamese']))===mb_strtolower(trim($word['vietnamese']))) { continue; }
            if($field==='image' && ExerciseService::media($value)===null) { continue; }
            $seen[$key]=true;$items[]=['id'=>$this->id(),($field==='image'?'image':'text')=>$value];
            if(count($items)===4) { shuffle($items);return [$items,$correct]; }
        }
        // Never fabricate distractors or emit a two/three-option multiple choice question.
        return null;
    }
    public function build(array $words,int $level): array
    {
        if(count($words)<2) { return []; }
        $bank=[];$accepted=[];
        foreach($words as $w) { $accepted[mb_strtolower(trim($w['vietnamese']))][]=$w['english']; }
        foreach($words as $word) {
            $textAnswer=['accepted_answers'=>array_values(array_unique($accepted[mb_strtolower(trim($word['vietnamese']))])),
                'normalization'=>['trim'=>true,'case_sensitive'=>false]];
            $bank[]=$this->row($word,$level,'translate_text',['instruction'=>'Viết từ tiếng Anh','prompt'=>$word['vietnamese']],$textAnswer);
            $pattern='/(?<![\pL\pN])'.preg_quote($word['english'],'/').'(?![\pL\pN])/iu';
            $example=(string)($word['example']??'');
            if($example!=='' && preg_match($pattern,$example)) {
                $prompt=preg_replace($pattern,'____',$example);
            } else {
                $prompt=mb_substr($word['english'],0,1).' '.str_repeat('_',max(1,mb_strlen($word['english'])-1));
            }
            $bank[]=$this->row($word,$level,'fill_blank',['instruction'=>'Điền từ tiếng Anh: '.$word['vietnamese'],'prompt'=>$prompt],
                ['accepted_answers'=>[$word['english']],'normalization'=>['trim'=>true,'case_sensitive'=>false]]);
            foreach(['vietnamese','english'] as $field) {
                $choice=$this->options($word,$words,$field);
                if($choice!==null) {
                    [$options,$correct]=$choice;
                    $bank[]=$this->row($word,$level,'choose_meaning',[
                        'instruction'=>$field==='english'?'Chọn từ tiếng Anh':'Chọn nghĩa tiếng Việt',
                        'prompt'=>$word[$field==='english'?'vietnamese':'english'],'options'=>$options],['correct_option_ids'=>[$correct]]);
                }
            }
            if(ExerciseService::media($word['image']??null)!==null && ($choice=$this->options($word,$words,'image'))!==null) {
                [$options,$correct]=$choice;
                $bank[]=$this->row($word,$level,'choose_image',['instruction'=>'Chọn hình phù hợp','prompt'=>$word['english'],'options'=>$options],['correct_option_ids'=>[$correct]]);
            }
            if(ExerciseService::media($word['audio']??null)!==null) {
                $bank[]=$this->row($word,$level,'listen_type',['instruction'=>'Nghe và viết từ tiếng Anh','audio'=>$word['audio']],
                    ['accepted_answers'=>[$word['english']],'normalization'=>['trim'=>true,'case_sensitive'=>false]]);
                if(($choice=$this->options($word,$words,'vietnamese'))!==null) {
                    [$options,$correct]=$choice;
                    $bank[]=$this->row($word,$level,'listen_select',['instruction'=>'Nghe và chọn nghĩa','audio'=>$word['audio'],'options'=>$options],['correct_option_ids'=>[$correct]]);
                }
            }
            $tokens=preg_split('/\s+/u',trim($example),-1,PREG_SPLIT_NO_EMPTY);
            if(!empty($word['example_translation']) && count($tokens)>=2 && count($tokens)<=20) {
                $items=[];foreach($tokens as $token) { $items[]=['id'=>$this->id(),'text'=>$token]; }
                $bank[]=$this->row($word,$level,'token_order',['instruction'=>'Sắp xếp câu ví dụ','prompt'=>$word['example_translation'],'tokens'=>$items],
                    ['accepted_sequences'=>[array_column($items,'id')]]);
            }
        }
        $unique=[];$seenEn=$seenVi=[];
        $shuffled=$words;shuffle($shuffled);
        foreach($shuffled as $w) {
            $en=mb_strtolower(trim($w['english']));$vi=mb_strtolower(trim($w['vietnamese']));
            if(isset($seenEn[$en])||isset($seenVi[$vi])) { continue; }
            $seenEn[$en]=$seenVi[$vi]=true;$unique[]=$w;
        }
        foreach(array_chunk($unique,4) as $group) {
            if(count($group)<2) { continue; }
            $left=$right=$pairs=$targets=[];
            foreach($group as $w) {
                $l=$this->id();$r=$this->id();$id=(int)$w['id'];$targets[]=$id;
                $left[]=['id'=>$l,'text'=>$w['english']];$right[]=['id'=>$r,'text'=>$w['vietnamese']];$pairs[]=['left_id'=>$l,'right_id'=>$r,'word_id'=>$id];
            }
            $bank[]=['word_id'=>$targets[0],'type'=>'match_pairs','difficulty'=>$level,
                'content'=>['schema_version'=>1,'instruction'=>'Ghép từ với nghĩa','target_word_ids'=>$targets,'left'=>$left,'right'=>$right], 'answer'=>['pairs'=>$pairs]];
        }
        return $bank;
    }
}
