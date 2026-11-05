<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

final class AuthPageController
{
    public function welcome(array $params): void
    {
        $this->render('welcome', 'Học từ vựng mỗi ngày');
    }

    public function login(array $params): void
    {
        $this->render('login', 'Đăng nhập');
    }

    public function register(array $params): void
    {
        $this->render('register', 'Đăng ký');
    }

    public function profile(array $params): void
    {
        $this->render('profile', 'Hồ sơ của tôi');
    }

    private function render(string $view, string $title): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 4) . '/resources/views/auth/' . $view . '.php';
    }
}
