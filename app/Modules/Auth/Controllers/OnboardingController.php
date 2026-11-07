<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

/**
 * Luồng làm quen: chào mừng -> chọn trình độ / test nhanh -> học từ vựng.
 * Kiểm tra trình độ demo dùng dữ liệu minh họa; học thật chuyển sang PR #26.
 */
final class OnboardingController
{
    public function start(array $params): void
    {
        $this->render('survey', 'Bắt đầu học');
    }

    public function answers(array $params): void
    {
        $user=\App\Core\Session::userId()===null?null:\App\Middleware\Auth::user();
        $db=\App\Core\Database::connection();
        $service=new \App\Modules\Auth\Services\OnboardingService($db);
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            \App\Core\Session::verifyCsrf();
            $answers=$service->save($user===null?null:(int)$user['id'],\App\Core\Request::json());
        } else $answers=$service->read($user===null?null:(int)$user['id']);
        $repo=new \App\Modules\Learning\Repositories\LearningRepository($db);
        $first=(new \App\Modules\Learning\Services\GuestLearningService($repo,new \App\Modules\Learning\Repositories\GuestClaimRepository($db)))->firstLesson();
        $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'')),'/');
        \App\Core\Response::data(['answers'=>$answers,'signed_in'=>$user!==null,'schema_ready'=>$service->ready(),
            'learning_url'=>$base.($first?'/learning/lesson/'.(int)$first['id'].'?focus=practice&onboarding=1':'/learning')]);
    }

    public function level(array $params): void
    {
        $this->render('level', 'Chọn trình độ');
    }

    public function placement(array $params): void
    {
        $this->render('placement', 'Kiểm tra trình độ');
    }

    public function lesson(array $params): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        header('Location: ' . $base . '/learning', true, 303);
    }

    private function render(string $view, string $title): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $page = $view;
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 4) . '/resources/views/auth/' . $view . '.php';
    }
}
