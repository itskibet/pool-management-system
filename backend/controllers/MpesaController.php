<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

/**
 * Normalized M-Pesa callback boundary.
 *
 * Provider-specific Daraja STK/C2B payload mapping is kept here so the rest of
 * the application only deals with a verified internal transaction shape.
 * Production credentials are never read from source control.
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
    $pdo->beginTransaction();

    try {
        // Lock the pending session so two callbacks cannot create two games.
        $stmt = $pdo->prepare(
            'SELECT ps.*, t.table_number, t.mqtt_topic
             FROM payment_sessions ps
             INNER JOIN tables_pool t ON t.id = ps.table_id
             WHERE ps.account_reference = ?
               AND ps.status = "pending"
               AND (ps.expires_at IS NULL OR ps.expires_at >= NOW())
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$transaction['account_reference']]);
        $session = $stmt->fetch();

        if (!$session) {
            $pdo->rollBack();
            jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted; no matching pending session'], 200);
        }

        if (abs((float) $session['amount'] - $transaction['amount']) > 0.00001) {
            $pdo->rollBack();
            jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted; amount mismatch rejected'], 200);
        }

        if ($transaction['receipt'] === '') {
            $pdo->rollBack();
            jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Missing transaction receipt'], 422);
        }

        // A unique M-Pesa receipt makes retries idempotent at the database level.
        $existing = $pdo->prepare('SELECT id FROM payments WHERE mpesa_receipt = ? LIMIT 1 FOR UPDATE');
        $existing->execute([$transaction['receipt']]);
        if ($existing->fetch()) {
            $pdo->rollBack();
            jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Already processed'], 200);
        }

        $insert = $pdo->prepare(
            'INSERT INTO payments
             (session_id, table_id, payment_method, phone_number, amount, account_reference,
              merchant_request_id, checkout_request_id, mpesa_receipt, transaction_id,
              status, paid_at, raw_callback)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "confirmed", NOW(), ?)'
        );
        $insert->execute([
            $session['id'],
            $session['table_id'],
            $session['payment_method'],
            $transaction['phone_number'],
            $transaction['amount'],
            $transaction['account_reference'],
            $transaction['merchant_request_id'],
            $transaction['checkout_request_id'],
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
            'UPDATE tables_pool SET status = "playing" WHERE id = ? AND status = "payment_pending"'
        );
        $table->execute([$session['table_id']]);

        if ($table->rowCount() !== 1) {
            throw new RuntimeException('Table was not in payment_pending state');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], 200);
}

/**
 * Accepts the normalized development/test payload. Daraja-specific STK/C2B
 * extraction should be added here once the client's exact product is selected.
 */
function extractMpesaTransaction(array $payload): ?array
{
    if (!isset($payload['account_reference'], $payload['amount'], $payload['receipt'])) {
        return null;
    }

    return [
        'account_reference' => trim((string) $payload['account_reference']),
        'amount' => (float) $payload['amount'],
        'receipt' => trim((string) $payload['receipt']),
        'transaction_id' => isset($payload['transaction_id']) ? trim((string) $payload['transaction_id']) : null,
        'phone_number' => isset($payload['phone_number']) ? trim((string) $payload['phone_number']) : null,
        'merchant_request_id' => isset($payload['merchant_request_id']) ? trim((string) $payload['merchant_request_id']) : null,
        'checkout_request_id' => isset($payload['checkout_request_id']) ? trim((string) $payload['checkout_request_id']) : null,
    ];
}
