<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

/**
 * Daraja callback boundary.
 *
 * Provider-specific authentication/signature and the exact C2B/STK mapping
 * should be completed when the client's production integration is selected.
 * This handler deliberately confirms a payment only after matching an existing
 * pending session and the expected amount/reference.
 */
function handleMpesaCallback(): never
{
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON'], 400);
    }

    $transaction = extractMpesaTransaction($payload);

    if ($transaction === null) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Unsupported callback payload'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT ps.*, t.table_number, t.mqtt_topic
         FROM payment_sessions ps
         INNER JOIN tables_pool t ON t.id = ps.table_id
         WHERE ps.account_reference = ? AND ps.status = "pending"
         LIMIT 1'
    );
    $stmt->execute([$transaction['account_reference']]);
    $session = $stmt->fetch();

    if (!$session) {
        // A callback for an unknown/expired reference must not unlock anything.
        jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted; no matching pending session'], 200);
    }

    if (abs((float) $session['amount'] - (float) $transaction['amount']) > 0.00001) {
        jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted; amount mismatch recorded'], 200);
    }

    if ($transaction['receipt'] === '') {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Missing transaction receipt'], 422);
    }

    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare(
            'INSERT INTO payments
             (session_id, table_id, payment_method, phone_number, amount, account_reference,
              mpesa_receipt, transaction_id, status, paid_at, raw_callback)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "confirmed", NOW(), ?)'
        );
        $insert->execute([
            $session['id'],
            $session['table_id'],
            $session['payment_method'],
            $transaction['phone_number'],
            $transaction['amount'],
            $transaction['account_reference'],
            $transaction['receipt'],
            $transaction['transaction_id'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $paymentId = (int) $pdo->lastInsertId();

        $update = $pdo->prepare(
            'UPDATE payment_sessions SET status = "completed" WHERE id = ? AND status = "pending"'
        );
        $update->execute([$session['id']]);

        $game = $pdo->prepare(
            'INSERT INTO games (table_id, payment_id, started_at, status)
             VALUES (?, ?, NOW(), "active")'
        );
        $game->execute([$session['table_id'], $paymentId]);
        $gameId = (int) $pdo->lastInsertId();

        $command = $pdo->prepare(
            'INSERT INTO mqtt_commands
             (table_id, payment_id, game_id, topic, command, status)
             VALUES (?, ?, ?, ?, "unlock", "queued")'
        );
        $command->execute([
            $session['table_id'],
            $paymentId,
            $gameId,
            $session['mqtt_topic'],
        ]);

        $table = $pdo->prepare(
            'UPDATE tables_pool SET status = "playing" WHERE id = ?'
        );
        $table->execute([$session['table_id']]);

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();

        // Duplicate receipt/callback is intentionally idempotent: do not create
        // another game or unlock command when the provider retries a callback.
        if ((string) $e->getCode() === '23000') {
            jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Already processed'], 200);
        }

        throw $e;
    }

    jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], 200);
}

function extractMpesaTransaction(array $payload): ?array
{
    // Internal normalized callback shape used by development/mock tests.
    if (isset($payload['account_reference'], $payload['amount'], $payload['receipt'])) {
        return [
            'account_reference' => trim((string) $payload['account_reference']),
            'amount' => (float) $payload['amount'],
            'receipt' => trim((string) $payload['receipt']),
            'transaction_id' => isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            'phone_number' => isset($payload['phone_number']) ? (string) $payload['phone_number'] : null,
        ];
    }

    return null;
}
