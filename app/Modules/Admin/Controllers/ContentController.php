<?php
declare(strict_types=1);
namespace App\Modules\Admin\Controllers;
use App\Core\ApiException;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\Auth;

final class ContentController
{
    // LIÊN QUAN CƯỜNG: một lần đăng ký toàn bộ route web của phần 2.
    public static function register(Router $router): void
    {
        $router->get('/admin', [self::class, 'words']);
        $router->get('/admin/topics', [TopicController::class, 'index']);
        $router->post('/admin/topics', [TopicController::class, 'store']);
        $router->get('/admin/words', [self::class, 'words']);
        $router->get('/admin/learners', [LearnerController::class, 'index']);
        $router->post('/admin/learners/{id}/status', [LearnerController::class, 'status']);
        foreach (['dictionary', 'lessons', 'exercises', 'import'] as $legacy) {
            $router->get('/admin/' . $legacy, [self::class, 'legacy']);
        }
    }
    public function legacy(array $params): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        header('Location: ' . $base . (Session::userId() === null ? '/login' : '/admin/words'), true, 303);
    }

    public function words(array $params): void
    {
        $this->page('words');
    }
    private function page(string $section): void
    {
        if (Session::userId() === null) { $this->legacy([]); return; }
        Auth::admin();
        Session::start();
        $appBase = rtrim(
            str_replace(
                '\\',
                '/',
                dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'),
            ),
            '/',
        );
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $view = 'books';
        require dirname(__DIR__, 4) . '/resources/views/admin/' . $view . '.php';
    }
}
