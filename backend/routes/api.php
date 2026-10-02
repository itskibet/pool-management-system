<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../controllers/TableController.php';
require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../controllers/GameController.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

$path = rtrim($path, '/');
$path = preg_replace('#^/index\.php#', '', $path);
$path = preg_replace('#^/api#', '', $path);
$path = $path === '' ? '/' : $path;

if ($method === 'GET' && $path === '/health') {
    try {
        db()->query('SELECT 1');
        jsonResponse([
            'name' => 'Pool Management API',
            'status' => 'ok',
            'version' => '0.2.0',
            'database' => 'connected',
        ]);
    } catch (Throwable $e) {
        jsonResponse([
            'name' => 'Pool Management API',
            'status' => 'error',
            'version' => '0.2.0',
            'database' => 'disconnected',
        ], 503);
    }
}

if ($method === 'GET' && $path === '/tables') {
    listTables();
}

if ($method === 'GET' && preg_match('#^/tables/(\d+)$#', $path, $matches)) {
    getTable((int) $matches[1]);
}

if ($method === 'GET' && $path === '/payments') {
    listPayments();
}

if ($method === 'POST' && $path === '/payments') {
    createPendingPayment();
}

if ($method === 'GET' && $path === '/games') {
    listGames();
}

jsonResponse(['error' => 'Route not found'], 404);
