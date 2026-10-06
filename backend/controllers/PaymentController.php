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
