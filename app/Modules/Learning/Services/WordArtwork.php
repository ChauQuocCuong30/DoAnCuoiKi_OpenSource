<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;

/** Prefer supplied images, then exact filenames and a small set of known concept illustrations. */
final class WordArtwork
{
    public static function image(array $word): ?string
    {
        $image=ExerciseService::media($word['image']??null);
        if ($image!==null && !preg_match('~^https://(?:www\.)?thiings\.co/things/~i',$image)) return $image;
        $key=strtolower(trim((string)($word['english']??'')));
        if (!preg_match('/^[a-z]+(?:[ -][a-z]+)*$/D',$key)) return null;
        $aliases=['skills'=>'skill','communicate'=>'communication','conversation'=>'communication','work'=>'job','business'=>'job',
            'class'=>'school','classroom'=>'school',
            'goal'=>'practice','teamwork'=>'team','meeting'=>'team','task'=>'project','assignment'=>'project','deadline'=>'schedule'];
        $key=$aliases[$key]??$key;
        $root=dirname(__DIR__,4).'/public';
        foreach (['/assets/media/images/'.str_replace(' ','-',$key).'.png','/assets/media/images/vocabulary/'.str_replace(' ','-',$key).'.svg'] as $path) {
            if (is_file($root.$path)) return $path;
        }
        return null;
    }
    public static function decorate(array $words): array
    {
        foreach ($words as &$word) $word['image']=self::image($word);
        unset($word); return $words;
    }
}
