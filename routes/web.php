<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Admin\Controllers\ContentController;
use App\Modules\Auth\Controllers\AuthPageController;
use App\Modules\Auth\Controllers\OnboardingController;
use App\Modules\Learning\Controllers\GuestLearningController;
use App\Modules\Learning\Controllers\LearningStudioController;

return static function (Router $router): void {
    ContentController::register($router);

    $router->get(
        '/login',
        [AuthPageController::class, 'login']
    );
    $router->get(
        '/register',
        [AuthPageController::class, 'register']
    );
    $router->get(
        '/profile',
        [AuthPageController::class, 'profile']
    );
    $router->get(
        '/welcome',
        [AuthPageController::class, 'welcome']
    );
    $router->get(
        '/start',
        [OnboardingController::class, 'start']
    );
    $router->get(
        '/level',
        [OnboardingController::class, 'level']
    );
    $router->get(
        '/placement',
        [OnboardingController::class, 'placement']
    );
    $router->get(
        '/lesson',
        [OnboardingController::class, 'lesson']
    );

    $router->get(
        '/learning/explore',
        [LearningStudioController::class, 'explore']
    );
    $router->get(
        '/learning/practice',
        [LearningStudioController::class, 'practice']
    );
    $router->get(
        '/learning/launch',
        [LearningStudioController::class, 'launch']
    );
    $router->get(
        '/learning/progress',
        [GuestLearningController::class, 'progress']
    );
    $router->get(
        '/learning/continue',
        [GuestLearningController::class, 'resume']
    );
    $router->get(
        '/learning',
        [GuestLearningController::class, 'index']
    );
    $router->get(
        '/learning/lesson/{id}',
        [GuestLearningController::class, 'lesson']
    );
};