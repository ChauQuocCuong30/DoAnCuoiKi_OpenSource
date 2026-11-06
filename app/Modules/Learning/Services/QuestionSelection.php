<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;
final class QuestionSelection
{
    /** Rotate exercise families, use each target once before repeating a word. */
    public static function main(array $bank,int $total,array $avoidTargets=[]): array
    {
        shuffle($bank);$remaining=$bank;$picked=[];$seenWords=array_fill_keys($avoidTargets,true);$typeCounts=[];
        while(count($picked)<$total && $remaining!==[]) {
            $best=null;$bestScore=PHP_INT_MAX;
            foreach($remaining as $i=>$item) {
                $s=$item['snapshot'];$word=(int)($s['target_word_ids'][0]??0);
                $score=(isset($seenWords[$word])?1000:0)+100*($typeCounts[$s['type']]??0);
                if($score<$bestScore) { $bestScore=$score;$best=$i; }
            }
            $item=$remaining[$best];unset($remaining[$best]);$picked[]=$item;
            $s=$item['snapshot'];$seenWords[(int)($s['target_word_ids'][0]??0)]=true;$typeCounts[$s['type']]=1+($typeCounts[$s['type']]??0);
        }
        return $picked;
    }
}
