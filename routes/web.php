<?php
declare(strict_types=1);

use App\Core\Router;
use App\Modules\Reviews\Controllers\ReviewHttpController;

return static function (Router $router): void {
    $router->get('/reviews', [ReviewHttpController::class, 'index']);
};