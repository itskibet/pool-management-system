<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

function listGames(): never
{
    $sql = 'SELECT g.id, g.table_id, t.table_number, t.name AS table_name,
                   g.payment_id, g.started_at, g.ended_at, g.duration_seconds,
                   g.status, g.created_at
            FROM games g
            INNER JOIN tables t ON t.id = g.table_id
            ORDER BY g.id DESC';

    jsonResponse(['data' => db()->query($sql)->fetchAll()]);
}
