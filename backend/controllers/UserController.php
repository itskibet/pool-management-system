<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function listUsers(): never
{
    $stmt = db()->prepare(
        'SELECT id, name, email, role, active, created_at, updated_at
         FROM users WHERE organization_id = ? ORDER BY id ASC'
    );
    $stmt->execute([currentOrganizationId()]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}
