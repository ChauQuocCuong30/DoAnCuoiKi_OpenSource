<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Learning\Controllers\LearningController;

return static function (Router $router): void {
    $router->get('/login', [\App\Modules\Auth\Controllers\AuthPageController::class, 'login']);
    $router->get('/register', [\App\Modules\Auth\Controllers\AuthPageController::class, 'register']);
    $router->get('/profile', [\App\Modules\Auth\Controllers\AuthPageController::class, 'profile']);
    $router->get('/welcome', [\App\Modules\Auth\Controllers\AuthPageController::class, 'welcome']);
    $router->get('/start', [\App\Modules\Auth\Controllers\OnboardingController::class, 'start']);
    $router->get('/level', [\App\Modules\Auth\Controllers\OnboardingController::class, 'level']);
    $router->get('/placement', [\App\Modules\Auth\Controllers\OnboardingController::class, 'placement']);
    $router->get('/lesson', [\App\Modules\Auth\Controllers\OnboardingController::class, 'lesson']);

    $router->get('/learning', [LearningController::class, 'index']);
    $router->get('/learning/lesson/{id}', [LearningController::class, 'lesson']);
    // Existing content module routes (Cuong).
    \App\Modules\Admin\Controllers\ContentController::register($router);
    $router->get('/reviews', [
        \App\Modules\Reviews\Controllers\ReviewHttpController::class, 'index',
    ]);
};
