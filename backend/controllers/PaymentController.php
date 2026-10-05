<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

function listPayments(): never
{
    $sql = 'SELECT p.id, p.table_id, t.table_number, t.name AS table_name,
                   p.payment_method, p.phone_number, p.amount, p.account_reference,
                   p.status, p.mpesa_receipt, p.created_at, p.paid_at
            FROM payments p
            INNER JOIN tables_pool t ON t.id = p.table_id
            ORDER BY p.id DESC';

    jsonResponse(['data' => db()->query($sql)->fetchAll()]);
}

function createPendingPayment(): never
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        jsonResponse(['error' => 'Request body must be valid JSON'], 400);
    }

    $tableId = filter_var($input['table_id'] ?? null, FILTER_VALIDATE_INT);
    $method = strtoupper(trim((string) ($input['payment_method'] ?? '')));
    $phone = isset($input['phone_number']) ? trim((string) $input['phone_number']) : null;

    if (!$tableId || !in_array($method, ['QR', 'MANUAL_MPESA', 'ASSISTED'], true)) {
        jsonResponse(['error' => 'table_id and a valid payment_method are required'], 422);
    }

    if ($phone !== null && $phone !== '' && !preg_match('/^\+?\d{9,15}$/', preg_replace('/[\s-]/', '', $phone))) {
        jsonResponse(['error' => 'phone_number must be a valid international phone number'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, table_number, price, status FROM tables_pool WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$tableId]);
        $table = $stmt->fetch();

        if (!$table) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Table not found'], 404);
        }

        if ($table['status'] !== 'available') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Table is not available for a new payment session'], 409);
        }

        $sessionId = generateUuidV4();
        $reference = sprintf(
            '%s-%s-%s',
            env('MPESA_ACCOUNT_REFERENCE_PREFIX', 'TABLE'),
            $table['table_number'],
            strtoupper(bin2hex(random_bytes(4)))
        );

        $stmt = $pdo->prepare(
            'INSERT INTO payment_sessions
             (id, table_id, amount, payment_method, phone_number, account_reference, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, "pending", DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        );
        $stmt->execute([$sessionId, $table['id'], $table['price'], $method, $phone, $reference]);

        $stmt = $pdo->prepare(
            'UPDATE tables_pool SET status = "payment_pending" WHERE id = ? AND status = "available"'
        );
        $stmt->execute([$table['id']]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Table became unavailable while creating payment session');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    jsonResponse([
        'message' => 'Payment session created',
        'data' => [
            'session_id' => $sessionId,
            'table_id' => (int) $table['id'],
            'table_number' => $table['table_number'],
            'amount' => (float) $table['price'],
            'payment_method' => $method,
            'account_reference' => $reference,
            'status' => 'pending',
            'expires_at_minutes' => 15,
        ],
    ], 201);
}

function generateUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
