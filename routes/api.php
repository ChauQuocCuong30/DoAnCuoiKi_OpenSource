<?php
declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;
use App\Modules\Admin\Controllers\ContentApiController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Users\Controllers\UsersController;
use App\Modules\Learning\Controllers\LearningController;
use App\Modules\Learning\Controllers\GuestLearningController;

return static function (Router $router): void {
    ContentApiController::register($router);

    $router->get(
        '/api/v1/health',
        static function (array $params): void {
            Response::data([
                'status' => 'ok',
                'database' => 'not_configured',
            ]);
        }
    );

    $router->post(
        '/api/v1/learning/lessons/{id}/sessions',
        [LearningController::class, 'start']
    );
    $router->get(
        '/api/v1/learning/sessions/{id}',
        [LearningController::class, 'session']
    );
    $router->post(
        '/api/v1/learning/sessions/{id}/answers',
        [LearningController::class, 'answer']
    );

    $router->post(
        '/api/v1/learning/guest/start',
        [GuestLearningController::class, 'start']
    );
    $router->get(
        '/api/v1/learning/guest/session',
        [GuestLearningController::class, 'state']
    );
    $router->post(
        '/api/v1/learning/guest/answers',
        [GuestLearningController::class, 'answer']
    );
    $router->post(
        '/api/v1/learning/guest/claim',
        [GuestLearningController::class, 'claim']
    );

    $router->post(
        '/api/v1/auth/register',
        [AuthController::class, 'register']
    );
    $router->post(
        '/api/v1/auth/login',
        [AuthController::class, 'login']
    );
    $router->post(
        '/api/v1/auth/logout',
        [AuthController::class, 'logout']
    );

    $router->get(
        '/api/v1/users/me',
        [UsersController::class, 'me']
    );
    $router->patch(
        '/api/v1/users/me',
        [UsersController::class, 'updateMe']
    );
    $router->patch(
        '/api/v1/users/{id}/status',
        [UsersController::class, 'updateStatus']
    );
};