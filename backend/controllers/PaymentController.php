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
            INNER JOIN tables t ON t.id = p.table_id
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
    $method = $input['payment_method'] ?? null;
    $phone = isset($input['phone_number']) ? trim((string) $input['phone_number']) : null;

    if (!$tableId || !in_array($method, ['stk_push', 'paybill'], true)) {
        jsonResponse(['error' => 'table_id and a valid payment_method are required'], 422);
    }

    $stmt = db()->prepare('SELECT id, table_number, price FROM tables WHERE id = ?');
    $stmt->execute([$tableId]);
    $table = $stmt->fetch();

    if (!$table) {
        jsonResponse(['error' => 'Table not found'], 404);
    }

    if ($method === 'stk_push' && ($phone === null || $phone === '')) {
        jsonResponse(['error' => 'phone_number is required for stk_push'], 422);
    }

    $reference = 'TABLE' . str_pad((string) $table['id'], 2, '0', STR_PAD_LEFT) . '-' . date('YmdHis');

    $stmt = db()->prepare(
        'INSERT INTO payments (table_id, payment_method, phone_number, amount, account_reference, status)
         VALUES (?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([
        $table['id'],
        $method,
        $phone,
        $table['price'],
        $reference,
    ]);

    jsonResponse([
        'message' => 'Payment created in pending state',
        'data' => [
            'id' => (int) db()->lastInsertId(),
            'table_id' => (int) $table['id'],
            'table_number' => $table['table_number'],
            'amount' => (float) $table['price'],
            'payment_method' => $method,
            'account_reference' => $reference,
            'status' => 'pending',
        ],
    ], 201);
}
