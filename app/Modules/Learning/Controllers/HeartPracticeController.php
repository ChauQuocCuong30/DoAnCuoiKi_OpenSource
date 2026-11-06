<?php
declare(strict_types=1);
namespace App\Modules\Learning\Controllers;
use App\Core\{ApiException,Controller,Database,Request,Response,Session};
use App\Middleware\Auth;
use App\Modules\Learning\Services\HeartPracticeService;
final class HeartPracticeController extends Controller
{
    private function service(): HeartPracticeService { return new HeartPracticeService(Database::connection()); }
    public function page(array $params=[]): void
    {
        if(Session::userId()===null) { header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/login?next=learning',true,303);return; }
        Auth::user();
        $this->render('learning/hearts',['basePath'=>rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/'),'csrfToken'=>Session::csrfToken(),'isGuest'=>false]);
    }
    public function summary(array $params=[]): void { $user=Auth::user();header('Cache-Control: no-store');Response::data($this->service()->summary((int)$user['id'])); }
    public function start(array $params=[]): void { $user=Auth::user();Session::verifyCsrf();Response::data($this->service()->start((int)$user['id'])); }
    public function answer(array $params): void
    {
        $user=Auth::user();Session::verifyCsrf();$id=filter_var($params['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id) { throw new ApiException('VALIDATION_FAILED','ID không hợp lệ.',422); }
        Response::data($this->service()->answer((int)$user['id'],(int)$id,Request::json()));
    }
}
