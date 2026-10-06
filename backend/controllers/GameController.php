<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function listGames(): never
{
    $stmt = db()->prepare(
        'SELECT g.id, g.table_id, t.table_number, t.name AS table_name,
                g.payment_id, g.started_at, g.ended_at, g.duration_seconds,
                g.status, g.created_at
         FROM games g
         INNER JOIN tables t ON t.id = g.table_id
         WHERE g.organization_id = ?
         ORDER BY g.id DESC'
    );
    $stmt->execute([currentOrganizationId()]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function endGame(int $id): never
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT g.id, g.table_id, g.status, t.mqtt_topic
             FROM games g
             INNER JOIN tables t ON t.id = g.table_id
             WHERE g.id = ? AND g.organization_id = ?
             FOR UPDATE'
        );
        $stmt->execute([$id, currentOrganizationId()]);
        $game = $stmt->fetch();

        if (!$game) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Game not found'], 404);
        }

        if ($game['status'] !== 'active') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Game is not active'], 409);
        }

        $durationExpression = 'TIMESTAMPDIFF(SECOND, started_at, NOW())';
        $updateGame = $pdo->prepare(
            'UPDATE games
             SET status = "completed", ended_at = NOW(), duration_seconds = ' . $durationExpression . '
             WHERE id = ?'
        );
        $updateGame->execute([$id]);

        $updateTable = $pdo->prepare(
            'UPDATE tables SET status = "available" WHERE id = ? AND organization_id = ?'
        );
        $updateTable->execute([$game['table_id'], currentOrganizationId()]);

        $command = $pdo->prepare(
            'INSERT INTO mqtt_commands
             (organization_id, table_id, game_id, topic, command, status)
             VALUES (?, ?, ?, ?, "lock", "queued")'
        );
        $command->execute([
            currentOrganizationId(),
            $game['table_id'],
            $id,
            $game['mqtt_topic'],
        ]);

        $pdo->commit();
        jsonResponse([
            'message' => 'Game ended; table is ready for the next paid session',
            'data' => ['game_id' => $id, 'status' => 'completed'],
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
