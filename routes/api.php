<?php
declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;
use App\Modules\Vocabulary\Controllers\DictionaryController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Users\Controllers\UsersController;

return static function (Router $router): void {
    $router->get('/api/v1/health', static function (array $params): void {
        Response::data(['status' => 'ok', 'database' => 'not_configured']);
    });

    // Auth
    $router->post('/api/v1/auth/register', [AuthController::class, 'register']);
    $router->post('/api/v1/auth/login', [AuthController::class, 'login']);
    $router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);

    // Users
    $router->get('/api/v1/users/me', [UsersController::class, 'me']);
    $router->patch('/api/v1/users/me', [UsersController::class, 'updateMe']);
    $router->patch('/api/v1/users/{id}/status', [UsersController::class, 'updateStatus']);

    // TOÀN: tra từ để Admin chuẩn bị nội dung học.
    $router->get('/api/v1/admin/dictionary',[DictionaryController::class, 'lookup']);
};