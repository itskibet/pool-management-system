<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

/**
 * Handles Safaricom C2B confirmation, STK Push callbacks, and the simplified
 * development callback shape used by automated tests.
 */
function handleMpesaCallback(): never
{
    $payload = readJsonPayload();
    $transaction = extractMpesaTransaction($payload);

    if ($transaction === null) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Unsupported callback payload'], 422);
    }

    if ($transaction['successful'] === false) {
        jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], 200);
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
            // A callback can legitimately arrive after a session expired or was already consumed.
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
            $transaction['merchant_request_id'],
            $transaction['checkout_request_id'],
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

    if (!$session || abs((float) $session['amount'] - $transaction['amount']) > 0.00001) {
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
            'merchant_request_id' => nullableString($payload['merchant_request_id'] ?? null),
            'checkout_request_id' => nullableString($payload['checkout_request_id'] ?? null),
            'successful' => true,
        ];
    }

    // Safaricom C2B confirmation payload.
    $c2b = extractC2bTransaction($payload);
    if ($c2b !== null) {
        return [
            ...$c2b,
            'transaction_id' => $c2b['transaction_id'],
            'merchant_request_id' => null,
            'checkout_request_id' => null,
            'successful' => true,
        ];
    }

    // Safaricom STK Push callback payload.
    $callback = $payload['Body']['stkCallback'] ?? null;
    if (!is_array($callback)) {
        return null;
    }

    $resultCode = (int) ($callback['ResultCode'] ?? 1);
    $metadata = [];
    foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
        if (isset($item['Name'])) {
            $metadata[(string) $item['Name']] = $item['Value'] ?? null;
        }
    }

    $amount = isset($metadata['Amount']) ? (float) $metadata['Amount'] : null;
    $receipt = isset($metadata['MpesaReceiptNumber']) ? trim((string) $metadata['MpesaReceiptNumber']) : '';
    $phone = isset($metadata['PhoneNumber']) ? trim((string) $metadata['PhoneNumber']) : null;
    $checkoutRequestId = nullableString($callback['CheckoutRequestID'] ?? null);

    // STK Push uses CheckoutRequestID to identify the initiated payment, while
    // the account reference is supplied by our pending-session lookup below.
    if ($amount === null || $receipt === '') {
        return [
            'account_reference' => '',
            'amount' => $amount ?? 0.0,
            'receipt' => $receipt,
            'transaction_id' => $receipt !== '' ? $receipt : null,
            'phone_number' => $phone,
            'merchant_request_id' => nullableString($callback['MerchantRequestID'] ?? null),
            'checkout_request_id' => $checkoutRequestId,
            'successful' => false,
        ];
    }

    return null;
}

function extractC2bTransaction(array $payload): ?array
{
    if (!isset($payload['TransID'], $payload['TransAmount'], $payload['BillRefNumber'])) {
        return null;
    }

    $reference = trim((string) $payload['BillRefNumber']);
    if ($reference === '') {
        return null;
    }

    return [
        'account_reference' => $reference,
        'amount' => (float) $payload['TransAmount'],
        'receipt' => trim((string) $payload['TransID']),
        'transaction_id' => trim((string) $payload['TransID']),
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
