<?php
declare(strict_types=1);
namespace App\Modules\Learning\Controllers;

use App\Core\{ApiException,Controller,Database,Request,Response,Session};
use App\Middleware\Auth;
use App\Modules\Learning\Repositories\{ChapterRepository,LearningRepository};
use App\Modules\Learning\Services\ChapterChallengeService;

final class ChapterController extends Controller
{
    private function service(): ChapterChallengeService
    {
        $db=Database::connection(); return new ChapterChallengeService(new ChapterRepository($db),new LearningRepository($db));
    }
    private function id(array $params): int
    {
        $id=filter_var($params['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new ApiException('VALIDATION_FAILED','ID không hợp lệ.',422);
        return $id;
    }
    public function page(array $params): void
    {
        $user=Auth::user(); $id=$this->id($params);
        $query=Database::connection()->prepare('SELECT * FROM topics WHERE id=? AND is_active=1');$query->execute([$id]);$topic=$query->fetch();
        if (!$topic) throw new ApiException('NOT_FOUND','Không tìm thấy chương.',404);
        $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'')),'/');
        $this->render('learning/challenge',['topic'=>$topic,'basePath'=>$base,'csrfToken'=>Session::csrfToken(),'isGuest'=>false]);
    }
    public function start(array $params): void
    {
        $user=Auth::user();Session::verifyCsrf();Response::data($this->service()->start((int)$user['id'],$this->id($params)));
    }
    public function get(array $params): void
    {
        $user=Auth::user();Response::data($this->service()->get((int)$user['id'],$this->id($params)));
    }
    public function answer(array $params): void
    {
        $user=Auth::user();Session::verifyCsrf();Response::data($this->service()->answer((int)$user['id'],$this->id($params),Request::json()));
    }
}
