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
        $router->get('/admin/dictionary', [
            DictionaryPageController::class,
            'index',
        ]);
        $router->get('/admin/topics', [TopicController::class, 'index']);
        $router->post('/admin/topics', [TopicController::class, 'store']);
        foreach (['words', 'lessons', 'exercises', 'import'] as $section) {
            $router->get('/admin/' . $section, [
                self::class,
                $section === 'import' ? 'importPage' : $section,
            ]);
        }
    }
    public function words(array $params): void
    {
        $this->page('words');
    }
    public function lessons(array $params): void
    {
        $this->page('lessons');
    }
    public function exercises(array $params): void
    {
        $this->page('exercises');
    }
    public function importPage(array $params): void
    {
        $this->page('import');
    }
    private function page(string $section): void
    {
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
        $view = in_array($section, ['words', 'import'], true) ? 'books' : 'content';
        require dirname(__DIR__, 4) . '/resources/views/admin/' . $view . '.php';
    }
}
