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
