<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

/**
 * Luồng làm quen: chào mừng -> chọn trình độ / test nhanh -> học từ vựng.
 * Chỉ trả về trang HTML; dữ liệu từ vựng tạm thời nằm ở public/assets/js/vocab-data.js.
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
        $this->render('lesson', 'Học từ vựng');
    }

    private function render(string $view, string $title): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $page = $view;
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__, 4) . '/resources/views/auth/' . $view . '.php';
    }
}
