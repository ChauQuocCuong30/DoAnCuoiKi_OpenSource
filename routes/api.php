<?php
declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;
use App\Modules\Auth\Controllers\AuthController;

return static function (Router $router): void {
    $router->get('/api/v1/health', static function (array $params): void {
        Response::data(['status' => 'ok', 'database' => 'not_configured']);
    });

    // Auth
    $router->post('/api/v1/auth/register', [AuthController::class, 'register']);
    $router->post('/api/v1/auth/login', [AuthController::class, 'login']);
    $router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);
};