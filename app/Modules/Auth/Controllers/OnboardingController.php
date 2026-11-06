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
        $this->render('start', 'Chào mừng');
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
