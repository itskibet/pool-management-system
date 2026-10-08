<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

require_once __DIR__ . '/../vendor/autoload.php';

const DEFAULT_MQTT_PORT = 8883;
const MQTT_QOS = 1;

function fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, '[MQTT WORKER] ' . $message . PHP_EOL);
    exit($exitCode);
}

$host = trim((string) env('MQTT_HOST', ''));
$port = (int) env('MQTT_PORT', (string) DEFAULT_MQTT_PORT);
$username = env('MQTT_USERNAME');
$password = env('MQTT_PASSWORD');
$clientId = trim((string) env('MQTT_CLIENT_ID', 'pool-management-worker'));

if ($host === '') {
    fail('MQTT_HOST is not configured. Nothing was published.');
}

if ($port < 1 || $port > 65535) {
    fail('MQTT_PORT must be between 1 and 65535.');
}

if ($clientId === '') {
    fail('MQTT_CLIENT_ID must not be empty.');
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id, organization_id, table_id, payment_id, game_id, topic, command
     FROM mqtt_commands
     WHERE status = "queued"
     ORDER BY id ASC
     LIMIT 1'
);
$stmt->execute();

$command = $stmt->fetch();

if (!$command) {
    echo '[MQTT WORKER] No queued commands.' . PHP_EOL;
    exit(0);
}

$commandId = (int) $command['id'];
$topic = trim((string) $command['topic']);
$action = strtolower(trim((string) $command['command']));

if ($topic === '') {
    $update = $pdo->prepare(
        'UPDATE mqtt_commands SET status = "failed" WHERE id = ? AND status = "queued"'
    );
    $update->execute([$commandId]);
    fail("Command #{$commandId} has an empty MQTT topic.");
}

if (!in_array($action, ['unlock', 'lock'], true)) {
    $update = $pdo->prepare(
        'UPDATE mqtt_commands SET status = "failed" WHERE id = ? AND status = "queued"'
    );
    $update->execute([$commandId]);
    fail("Command #{$commandId} has an invalid action.");
}

$payload = json_encode([
    'command' => strtoupper($action),
    'command_id' => $commandId,
    'organization_id' => (int) $command['organization_id'],
    'table_id' => (int) $command['table_id'],
    'payment_id' => $command['payment_id'] !== null ? (int) $command['payment_id'] : null,
    'game_id' => $command['game_id'] !== null ? (int) $command['game_id'] : null,
    'timestamp' => gmdate('c'),
], JSON_THROW_ON_ERROR);

$settings = new ConnectionSettings();

if ($username !== null && $username !== '') {
    $settings = $settings->setUsername($username);
}

if ($password !== null && $password !== '') {
    $settings = $settings->setPassword($password);
}

$settings = $settings
    ->setUseTls(true)
    ->setTlsVerifyPeer(true)
    ->setTlsVerifyPeerName(true)
    ->setConnectTimeout(10)
    ->setSocketTimeout(10)
    ->setKeepAliveInterval(30);

$client = new MqttClient(
    $host,
    $port,
    $clientId,
    MqttClient::MQTT_3_1_1
);

try {
    $client->connect($settings, true);
    $client->publish($topic, $payload, MQTT_QOS, false);
    $client->disconnect();

    $update = $pdo->prepare(
        'UPDATE mqtt_commands
         SET status = "published", published_at = NOW()
         WHERE id = ? AND status = "queued"'
    );
    $update->execute([$commandId]);

    echo sprintf(
        "[MQTT WORKER] Published command #%d: %s -> %s" . PHP_EOL,
        $commandId,
        strtoupper($action),
        $topic
    );
    exit(0);
} catch (Throwable $e) {
    try {
        $client->disconnect();
    } catch (Throwable) {
        // Ignore disconnect failures after the primary MQTT error.
    }

    $update = $pdo->prepare(
        'UPDATE mqtt_commands SET status = "failed" WHERE id = ? AND status = "queued"'
    );
    $update->execute([$commandId]);

    fail("Command #{$commandId} failed: " . $e->getMessage());
}
