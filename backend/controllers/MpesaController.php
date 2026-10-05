<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

/**
 * Handles Safaricom C2B confirmation and the simplified development callback
 * shape used by automated tests. Both manual SIM Toolkit payments and Dynamic
 * QR payments can settle through the C2B confirmation flow.
 */
function handleMpesaCallback(): never
{
    $payload = readJsonPayload();
    $transaction = extractMpesaTransaction($payload);

    if ($transaction === null) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Unsupported callback payload'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT ps.*, t.table_number, t.mqtt_topic
             FROM payment_sessions ps
             INNER JOIN tables_pool t ON t.id = ps.table_id
             WHERE ps.account_reference = ?
               AND ps.status = ?
               AND (ps.expires_at IS NULL OR ps.expires_at >= NOW())
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$transaction['account_reference'], 'pending']);
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
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
        );
        $insert->execute([
            $session['id'],
            $session['table_id'],
            $session['payment_method'],
            $transaction['phone_number'],
            $transaction['amount'],
            $transaction['account_reference'],
            null,
            null,
            $transaction['receipt'],
            $transaction['transaction_id'],
            'confirmed',
            json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $paymentId = (int) $pdo->lastInsertId();

        $update = $pdo->prepare(
            'UPDATE payment_sessions SET status = ? WHERE id = ? AND status = ?'
        );
        $update->execute(['completed', $session['id'], 'pending']);

        $game = $pdo->prepare(
            'INSERT INTO games (table_id, payment_id, started_at, status)
             VALUES (?, ?, NOW(), ?)'
        );
        $game->execute([$session['table_id'], $paymentId, 'active']);
        $gameId = (int) $pdo->lastInsertId();

        $command = $pdo->prepare(
            'INSERT INTO mqtt_commands
             (table_id, payment_id, game_id, topic, command, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $command->execute([
            $session['table_id'],
            $paymentId,
            $gameId,
            $session['mqtt_topic'],
            'unlock',
            'queued',
        ]);

        $table = $pdo->prepare(
            'UPDATE tables_pool SET status = ? WHERE id = ? AND status = ?'
        );
        $table->execute(['playing', $session['table_id'], 'payment_pending']);

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
 * Optional C2B external validation endpoint. It only accepts a payment when
 * the reference exists, the session is still pending, and the amount matches.
 */
function handleMpesaValidation(): never
{
    $payload = readJsonPayload();
    $transaction = extractC2bTransaction($payload);

    if ($transaction === null) {
        jsonResponse(['ResultCode' => 'C2B00016', 'ResultDesc' => 'Rejected'], 400);
    }

    $stmt = db()->prepare(
        'SELECT amount FROM payment_sessions
         WHERE account_reference = ? AND status = ?
           AND (expires_at IS NULL OR expires_at >= NOW())
         LIMIT 1'
    );
    $stmt->execute([$transaction['account_reference'], 'pending']);
    $session = $stmt->fetch();

    if (!$session) {
        jsonResponse(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected'], 200);
    }

    if (abs((float) $session['amount'] - $transaction['amount']) > 0.00001) {
        jsonResponse(['ResultCode' => 'C2B00013', 'ResultDesc' => 'Rejected'], 200);
    }

    jsonResponse(['ResultCode' => '0', 'ResultDesc' => 'Accepted'], 200);
}

function readJsonPayload(): array
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON'], 400);
    }

    return $payload;
}

function extractMpesaTransaction(array $payload): ?array
{
    // Development/test shape.
    if (isset($payload['account_reference'], $payload['amount'], $payload['receipt'])) {
        return [
            'account_reference' => trim((string) $payload['account_reference']),
            'amount' => (float) $payload['amount'],
            'receipt' => trim((string) $payload['receipt']),
            'transaction_id' => nullableString($payload['transaction_id'] ?? null),
            'phone_number' => nullableString($payload['phone_number'] ?? null),
        ];
    }

    // Safaricom C2B confirmation payload.
    $c2b = extractC2bTransaction($payload);
    if ($c2b === null) {
        return null;
    }

    return $c2b;
}

function extractC2bTransaction(array $payload): ?array
{
    if (!isset($payload['TransID'], $payload['TransAmount'], $payload['BillRefNumber'])) {
        return null;
    }

    $reference = trim((string) $payload['BillRefNumber']);
    $receipt = trim((string) $payload['TransID']);
    if ($reference === '' || $receipt === '') {
        return null;
    }

    return [
        'account_reference' => $reference,
        'amount' => (float) $payload['TransAmount'],
        'receipt' => $receipt,
        'transaction_id' => $receipt,
        'phone_number' => nullableString($payload['MSISDN'] ?? null),
    ];
}

function nullableString(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}
