<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$relative = substr((string) $path, strlen($base));
if ($relative === '/api/v1/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => ['status' => 'ok', 'database' => 'not_configured']], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($relative === '' || $relative === '/') {
    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/resources/views/home.php';
    exit;
}
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => ['code' => 'NOT_FOUND', 'message' => 'Route not found']]);
