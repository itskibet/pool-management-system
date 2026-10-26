<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
loadEnv(dirname(__DIR__) . '/.env');

$allowedOrigin = env('FRONTEND_URL', 'http://localhost:3000') ?? 'http://localhost:3000';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($requestOrigin !== '' && hash_equals($allowedOrigin, $requestOrigin)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// Browser mutations must originate from the configured frontend. Payment-provider
// callbacks are server-to-server and are excluded from this browser-origin check.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$isProviderCallback = str_contains($requestPath, '/payhero/callback/')
    || str_contains($requestPath, '/mpesa/c2b/');
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !$isProviderCallback) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !hash_equals(rtrim($allowedOrigin, '/'), rtrim($origin, '/'))) {
        http_response_code(403);
        echo json_encode(['error' => 'Request origin is not allowed']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../routes/api.php';
