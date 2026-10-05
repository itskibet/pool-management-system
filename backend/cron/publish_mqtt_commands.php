<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/MqttPublisher.php';

$pdo = db();
$lock = (int) $pdo->query("SELECT GET_LOCK('pool_management_mqtt_worker', 5)")->fetchColumn();

if ($lock !== 1) {
    throw new RuntimeException('Another MQTT worker is already running');
}

try {
    // Recover commands claimed by a worker that stopped unexpectedly.
    $pdo->exec(
        "UPDATE mqtt_commands
         SET status = 'queued'
         WHERE status = 'processing'
           AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );

    $published = 0;
    $failed = 0;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $command = $pdo->query(
            "SELECT id, topic, command, payment_id, game_id
             FROM mqtt_commands WHERE status = 'queued' ORDER BY id ASC LIMIT 1"
        )->fetch();

        if (!$command) {
            break;
        }

        $claim = $pdo->prepare(
            'UPDATE mqtt_commands SET status = ? WHERE id = ? AND status = ?'
        );
        $claim->execute(['processing', $command['id'], 'queued']);

        if ($claim->rowCount() !== 1) {
            continue;
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
} finally {
    $pdo->query("SELECT RELEASE_LOCK('pool_management_mqtt_worker')");
}
