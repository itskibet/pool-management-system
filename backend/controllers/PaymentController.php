<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function listPayments(): never
{
    $stmt = db()->prepare(
        'SELECT p.id, p.table_id, t.table_number, t.name AS table_name,
                p.payment_method, p.phone_number, p.amount, p.account_reference,
                p.status, p.mpesa_receipt, p.transaction_id, p.created_at, p.paid_at
         FROM payments p
         INNER JOIN tables t ON t.id = p.table_id
         WHERE p.organization_id = ?
         ORDER BY p.id DESC'
    );
    $stmt->execute([currentOrganizationId()]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function createPendingPayment(): never
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        jsonResponse(['error' => 'Request body must be valid JSON'], 400);
    }

    $tableId = filter_var($input['table_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$tableId) {
        jsonResponse(['error' => 'table_id is required'], 422);
    }

    $stmt = db()->prepare(
        'SELECT id, table_number, price, status
         FROM tables WHERE id = ? AND organization_id = ?'
    );
    $stmt->execute([$tableId, currentOrganizationId()]);
    $table = $stmt->fetch();

    if (!$table) {
        jsonResponse(['error' => 'Table not found'], 404);
    }

    if ($table['status'] !== 'available') {
        jsonResponse(['error' => 'Table is not available for a new payment'], 409);
    }

    $reference = $table['table_number'];

    $stmt = db()->prepare(
        'INSERT INTO payments
         (organization_id, table_id, payment_method, amount, account_reference, status)
         VALUES (?, ?, "paybill", ?, ?, "pending")'
    );
    $stmt->execute([
        currentOrganizationId(),
        $table['id'],
        $table['price'],
        $reference,
    ]);

    jsonResponse([
        'message' => 'Paybill payment recorded as pending for verification',
        'data' => [
            'id' => (int) db()->lastInsertId(),
            'table_id' => (int) $table['id'],
            'table_number' => $table['table_number'],
            'amount' => (float) $table['price'],
            'payment_method' => 'paybill',
            'account_reference' => $reference,
            'status' => 'pending',
        ],
    ], 201);
}

function confirmPayment(int $id): never
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        jsonResponse(['error' => 'Request body must be valid JSON'], 400);
    }

    $receipt = trim((string) ($input['mpesa_receipt'] ?? ''));
    $transactionId = trim((string) ($input['transaction_id'] ?? ''));

    if ($receipt === '' && $transactionId === '') {
        jsonResponse(['error' => 'mpesa_receipt or transaction_id is required'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.table_id, p.amount, p.account_reference, p.status AS payment_status,
                    t.table_number, t.mqtt_topic, t.status AS table_status
             FROM payments p
             INNER JOIN tables t ON t.id = p.table_id
             WHERE p.id = ? AND p.organization_id = ?
             FOR UPDATE'
        );
        $stmt->execute([$id, currentOrganizationId()]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Payment not found'], 404);
        }

        if ($payment['payment_status'] !== 'pending') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Payment is not pending'], 409);
        }

        if ($payment['table_status'] !== 'available') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Table is not available for this payment'], 409);
        }

        $updatePayment = $pdo->prepare(
            'UPDATE payments
             SET status = "confirmed",
                 mpesa_receipt = ?,
                 transaction_id = ?,
                 paid_at = NOW()
             WHERE id = ? AND organization_id = ?'
        );
        $updatePayment->execute([
            $receipt !== '' ? $receipt : null,
            $transactionId !== '' ? $transactionId : null,
            $id,
            currentOrganizationId(),
        ]);

        $createGame = $pdo->prepare(
            'INSERT INTO games
             (organization_id, table_id, payment_id, started_at, status)
             VALUES (?, ?, ?, NOW(), "active")'
        );
        $createGame->execute([
            currentOrganizationId(),
            $payment['table_id'],
            $id,
        ]);
        $gameId = (int) $pdo->lastInsertId();

        $updateTable = $pdo->prepare(
            'UPDATE tables
             SET status = "playing"
             WHERE id = ? AND organization_id = ?'
        );
        $updateTable->execute([
            $payment['table_id'],
            currentOrganizationId(),
        ]);

        $queueCommand = $pdo->prepare(
            'INSERT INTO mqtt_commands
             (organization_id, table_id, payment_id, game_id, topic, command, status)
             VALUES (?, ?, ?, ?, ?, "unlock", "queued")'
        );
        $queueCommand->execute([
            currentOrganizationId(),
            $payment['table_id'],
            $id,
            $gameId,
            $payment['mqtt_topic'],
        ]);
        $mqttCommandId = (int) $pdo->lastInsertId();

        $pdo->commit();

        jsonResponse([
            'message' => 'Payment confirmed; game started and unlock command queued',
            'data' => [
                'payment_id' => $id,
                'payment_status' => 'confirmed',
                'game_id' => $gameId,
                'game_status' => 'active',
                'table_id' => (int) $payment['table_id'],
                'table_number' => $payment['table_number'],
                'table_status' => 'playing',
                'mqtt_command_id' => $mqttCommandId,
                'mqtt_command' => 'unlock',
                'mqtt_status' => 'queued',
            ],
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
