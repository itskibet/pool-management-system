<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function listTables(): never
{
    $stmt = db()->prepare(
        'SELECT id, table_number, name, price, status, mqtt_topic, created_at, updated_at
         FROM tables WHERE organization_id = ? ORDER BY id ASC'
    );
    $stmt->execute([currentOrganizationId()]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getTable(int $id): never
{
    $stmt = db()->prepare(
        'SELECT t.id, t.table_number, t.name, t.price, t.status, t.mqtt_topic,
                t.created_at, t.updated_at,
                m.payment_type, m.paybill_number, m.account_prefix
         FROM tables t
         INNER JOIN mpesa_settings m ON m.organization_id = t.organization_id
         WHERE t.id = ? AND t.organization_id = ?'
    );
    $stmt->execute([$id, currentOrganizationId()]);
    $table = $stmt->fetch();

    if (!$table) {
        jsonResponse(['error' => 'Table not found'], 404);
    }

    jsonResponse(['data' => $table]);
}

function createTable(): never
{
    $actor = requireRoles(['owner', 'admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $tableNumber = trim((string) ($input['table_number'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $price = $input['price'] ?? null;

    if ($tableNumber === '' || strlen($tableNumber) > 50) {
        jsonResponse(['error' => 'Table number is required and must not exceed 50 characters'], 422);
    }

    if ($name === '' || strlen($name) > 100) {
        jsonResponse(['error' => 'Table name is required and must not exceed 100 characters'], 422);
    }

    if (!is_numeric($price) || (float) $price <= 0) {
        jsonResponse(['error' => 'Price must be a number greater than 0'], 422);
    }

    $organizationId = (int) $actor['organization_id'];

    $check = db()->prepare(
        'SELECT id FROM tables WHERE organization_id = ? AND table_number = ? LIMIT 1'
    );
    $check->execute([$organizationId, $tableNumber]);

    if ($check->fetchColumn()) {
        jsonResponse(['error' => 'A table with this table number already exists in your organization'], 409);
    }

    $mqttTopic = 'pool/' . $tableNumber . '/command';

    try {
        $stmt = db()->prepare(
            'INSERT INTO tables
                (organization_id, table_number, name, price, status, mqtt_topic)
             VALUES (?, ?, ?, ?, "available", ?)'
        );
        $stmt->execute([
            $organizationId,
            $tableNumber,
            $name,
            number_format((float) $price, 2, '.', ''),
            $mqttTopic
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            jsonResponse(['error' => 'A table with this table number already exists'], 409);
        }
        throw $e;
    }

    $id = (int) db()->lastInsertId();

    $stmt = db()->prepare(
        'SELECT id, table_number, name, price, status, mqtt_topic, created_at, updated_at
         FROM tables WHERE id = ? AND organization_id = ? LIMIT 1'
    );
    $stmt->execute([$id, $organizationId]);

    jsonResponse([
        'message' => 'Table created',
        'data' => $stmt->fetch()
    ], 201);
}
