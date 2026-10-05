<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

function listTables(): never
{
    $rows = db()->query(
        'SELECT id, table_number, name, price, status, mqtt_topic, created_at, updated_at
         FROM tables_pool ORDER BY id ASC'
    )->fetchAll();

    jsonResponse(['data' => $rows]);
}

function getTable(int $id): never
{
    $stmt = db()->prepare(
        'SELECT id, table_number, name, price, status, mqtt_topic, created_at, updated_at
         FROM tables_pool WHERE id = ?'
    );
    $stmt->execute([$id]);
    $table = $stmt->fetch();

    if (!$table) {
        jsonResponse(['error' => 'Table not found'], 404);
    }

    jsonResponse(['data' => $table]);
}
