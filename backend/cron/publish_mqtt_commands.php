<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/MqttPublisher.php';

$pdo = db();

// Recover commands that were claimed by a worker which stopped unexpectedly.
$pdo->exec(
    "UPDATE mqtt_commands
     SET status = 'queued'
     WHERE status = 'processing'
       AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
);

$claim = $pdo->prepare(
    'UPDATE mqtt_commands
     SET status = ?
     WHERE id = (
       SELECT id FROM (
         SELECT id FROM mqtt_commands WHERE status = ? ORDER BY id ASC LIMIT 1
       ) AS candidate
     )'
);

$published = 0;
$failed = 0;

for ($attempt = 0; $attempt < 20; $attempt++) {
    $claim->execute(['processing', 'queued']);
    if ($claim->rowCount() !== 1) {
        break;
    }

    $command = $pdo->query(
        "SELECT id, topic, command, payment_id, game_id
         FROM mqtt_commands WHERE status = 'processing' ORDER BY id ASC LIMIT 1"
    )->fetch();

    if (!$command) {
        break;
    }

    $payload = json_encode([
        'command' => $command['command'],
        'payment_id' => $command['payment_id'] !== null ? (int) $command['payment_id'] : null,
        'game_id' => $command['game_id'] !== null ? (int) $command['game_id'] : null,
        'issued_at' => gmdate('c'),
    ], JSON_THROW_ON_ERROR);

    try {
        publishMqttCommand($command['topic'], $payload);
        $stmt = $pdo->prepare(
            'UPDATE mqtt_commands SET status = ?, published_at = NOW() WHERE id = ? AND status = ?'
        );
        $stmt->execute(['published', $command['id'], 'processing']);
        $published++;
    } catch (Throwable $e) {
        $stmt = $pdo->prepare(
            'UPDATE mqtt_commands SET status = ? WHERE id = ? AND status = ?'
        );
        $stmt->execute(['failed', $command['id'], 'processing']);
        $failed++;
        error_log('MQTT publish failed: ' . $e->getMessage());
    }
}

echo sprintf("MQTT worker complete: %d published, %d failed\n", $published, $failed);
