<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../controllers/TableController.php';
require_once __DIR__ . '/../controllers/GameController.php';
require_once __DIR__ . '/../controllers/MpesaController.php';

set_exception_handler(static function (Throwable $e): never {
    error_log((string) $e);
    $debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
    jsonResponse([
        'error' => $debug ? $e->getMessage() : 'Internal server error',
    ], 500);
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

$path = rtrim($path, '/');
$path = preg_replace('#^/index\.php#', '', $path);
$path = preg_replace('#^/api#', '', $path);
$path = $path === '' ? '/' : $path;

$allowedOrigins = array_filter(array_map('trim', explode(',', env('APP_ALLOWED_ORIGINS', '*') ?? '*')));
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowOrigin = in_array('*', $allowedOrigins, true)
    ? '*'
    : (in_array($requestOrigin, $allowedOrigins, true) ? $requestOrigin : '');

if ($allowOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowOrigin);
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    try {
        db()->query('SELECT 1');
        jsonResponse(['name' => 'Pool Management API', 'status' => 'ok', 'version' => '0.5.0', 'database' => 'connected']);
    } catch (Throwable $e) {
        jsonResponse(['name' => 'Pool Management API', 'status' => 'error', 'version' => '0.5.0', 'database' => 'disconnected'], 503);
    }
}

if ($method === 'GET' && $path === '/tables') {
    listTables();
}

if ($method === 'GET' && preg_match('#^/tables/(\d+)$#', $path, $matches)) {
    getTable((int) $matches[1]);
}

if ($method === 'POST' && $path === '/payments') {
    createPendingPayment();
}

if ($method === 'GET' && preg_match('#^/payments/session/([0-9a-fA-F-]{36})$#', $path, $matches)) {
    getPaymentSessionStatus($matches[1]);
}

if ($method === 'POST' && $path === '/mpesa/callback') {
    handleMpesaCallback();
}

if ($method === 'POST' && $path === '/mpesa/validation') {
    handleMpesaValidation();
}

if ($method === 'GET' && $path === '/games') {
    listGames();
}

jsonResponse(['error' => 'Route not found'], 404);
