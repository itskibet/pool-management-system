<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

function cleanupExpiredPaymentSessions(): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT ps.id, ps.table_id
             FROM payment_sessions ps
             WHERE ps.status = ?
               AND ps.expires_at IS NOT NULL
               AND ps.expires_at < NOW()
             FOR UPDATE'
        );
        $stmt->execute(['pending']);
        $sessions = $stmt->fetchAll();

        if ($sessions === []) {
            $pdo->commit();
            return;
        }

        $expire = $pdo->prepare(
            'UPDATE payment_sessions SET status = ? WHERE id = ? AND status = ?'
        );
        $release = $pdo->prepare(
            'UPDATE tables_pool SET status = ? WHERE id = ? AND status = ?'
        );
        $hasPending = $pdo->prepare(
            'SELECT COUNT(*) FROM payment_sessions
             WHERE table_id = ? AND status = ?
               AND (expires_at IS NULL OR expires_at >= NOW())'
        );

        foreach ($sessions as $session) {
            $expire->execute(['expired', $session['id'], 'pending']);
            $hasPending->execute([$session['table_id'], 'pending']);

            if ((int) $hasPending->fetchColumn() === 0) {
                $release->execute(['available', $session['table_id'], 'payment_pending']);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function listPayments(): never
{
    jsonResponse(['error' => 'Payment listing requires staff authentication'], 401);
}

function createPendingPayment(): never
{
    cleanupExpiredPaymentSessions();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        jsonResponse(['error' => 'Request body must be valid JSON'], 400);
    }

    $tableId = filter_var($input['table_id'] ?? null, FILTER_VALIDATE_INT);
    $method = strtoupper(trim((string) ($input['payment_method'] ?? '')));
    $phone = isset($input['phone_number']) ? trim((string) $input['phone_number']) : null;

    if (!$tableId || !in_array($method, ['QR', 'MANUAL_MPESA'], true)) {
        jsonResponse(['error' => 'table_id and a valid payment_method are required'], 422);
    }

    if ($phone !== null && $phone !== '') {
        $normalizedPhone = preg_replace('/[\s-]/', '', $phone);
        if (!is_string($normalizedPhone) || !preg_match('/^\+?\d{9,15}$/', $normalizedPhone)) {
            jsonResponse(['error' => 'phone_number must be a valid international phone number'], 422);
        }
        $phone = $normalizedPhone;
    } else {
        $phone = null;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, table_number, name, price, status
             FROM tables_pool WHERE id = ? FOR UPDATE'
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
        $reference = generatePaymentReference((string) $table['table_number']);

        $stmt = $pdo->prepare(
            'INSERT INTO payment_sessions
             (id, table_id, amount, payment_method, phone_number, account_reference, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        );
        $stmt->execute([
            $sessionId, $table['id'], $table['price'], $method,
            $phone, $reference, 'pending',
        ]);

        $stmt = $pdo->prepare(
            'UPDATE tables_pool SET status = ? WHERE id = ? AND status = ?'
        );
        $stmt->execute(['payment_pending', $table['id'], 'available']);

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
            'table_name' => $table['name'],
            'amount' => (float) $table['price'],
            'payment_method' => $method,
            'account_reference' => $reference,
            'paybill' => trim((string) env('MPESA_SHORTCODE', '')),
            'status' => 'pending',
            'expires_at_minutes' => 15,
        ],
    ], 201);
}

function getPaymentSessionStatus(string $sessionId): never
{
    cleanupExpiredPaymentSessions();

    if (!preg_match('/^[0-9a-fA-F-]{36}$/', $sessionId)) {
        jsonResponse(['error' => 'Invalid payment session'], 422);
    }

    $stmt = db()->prepare(
        'SELECT ps.id, ps.amount, ps.payment_method, ps.account_reference,
                ps.status, ps.expires_at, t.table_number, t.status AS table_status,
                p.mpesa_receipt, g.id AS game_id, g.status AS game_status
         FROM payment_sessions ps
         INNER JOIN tables_pool t ON t.id = ps.table_id
         LEFT JOIN payments p ON p.session_id = ps.id AND p.status = ?
         LEFT JOIN games g ON g.payment_id = p.id
         WHERE ps.id = ? LIMIT 1'
    );
    $stmt->execute(['confirmed', $sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        jsonResponse(['error' => 'Payment session not found'], 404);
    }

    jsonResponse([
        'data' => [
            'session_id' => $session['id'],
            'table_number' => $session['table_number'],
            'amount' => (float) $session['amount'],
            'payment_method' => $session['payment_method'],
            'account_reference' => $session['account_reference'],
            'status' => $session['status'],
            'table_status' => $session['table_status'],
            'expires_at' => $session['expires_at'],
            'mpesa_receipt' => $session['mpesa_receipt'],
            'game_id' => $session['game_id'] !== null ? (int) $session['game_id'] : null,
            'game_status' => $session['game_status'],
        ],
    ]);
}

function generatePaymentReference(string $tableNumber): string
{
    $tableNumber = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $tableNumber));
    if ($tableNumber === '') {
        throw new RuntimeException('Invalid table number');
    }

    $reference = $tableNumber . '-' . strtoupper(bin2hex(random_bytes(4)));

    if (strlen($reference) > 20) {
        throw new RuntimeException('Table number is too long for an M-Pesa account reference');
    }

    return $reference;
}

function generateUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
