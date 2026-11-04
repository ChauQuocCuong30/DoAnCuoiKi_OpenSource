<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
(require dirname(__DIR__) . '/routes/web.php')($router);
(require dirname(__DIR__) . '/routes/api.php')($router);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $relative);
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $relative);
} catch (\Throwable $e) {
    echo "<div style='background: #ffe6e6; padding: 20px; border: 1px solid red; font-family: monospace;'>";
    echo "<h3 style='color: red;'>Lỗi PHP Chi Tiết:</h3>";
    echo "<p><b>Thông điệp:</b> " . $e->getMessage() . "</p>";
    echo "<p><b>File:</b> " . $e->getFile() . " (Dòng " . $e->getLine() . ")</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    exit;
}