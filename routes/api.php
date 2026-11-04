<?php
declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/api/v1/health', static function (array $params): void {
        Response::data(['status' => 'ok', 'database' => 'not_configured']);
    });
};