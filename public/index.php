<?php
declare(strict_types=1);

use App\Core\Router;

require dirname(__DIR__) . '/bootstrap/app.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$relative = substr((string) $path, strlen($base));

if ($relative === '' || $relative === '/') {
    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/resources/views/home.php';
    exit;
}

$router = new Router();
(require dirname(__DIR__) . '/routes/api.php')($router);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $relative);