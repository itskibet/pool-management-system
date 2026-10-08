<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

function requireSystemAdmin(): array
{
    $user = requireAuth();
    if (!(int) ($user['is_system_admin'] ?? 0)) {
        jsonResponse(['error' => 'System administrator permission required'], 403);
    }
    return $user;
}

function listOrganizations(): never
{
    requireSystemAdmin();

    $stmt = db()->query(
        'SELECT o.id, o.name, o.slug, o.created_at,
                m.payment_type, m.paybill_number, m.account_prefix,
                (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id) AS user_count,
                (SELECT COUNT(*) FROM tables t WHERE t.organization_id = o.id) AS table_count
         FROM organizations o
         LEFT JOIN mpesa_settings m ON m.organization_id = o.id
         ORDER BY o.id ASC'
    );

    jsonResponse(['data' => $stmt->fetchAll()]);
}

function createOrganization(): never
{
    $actor = requireSystemAdmin();
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];

    $name = trim((string) ($input['name'] ?? ''));
    $slug = strtolower(trim((string) ($input['slug'] ?? '')));
    $paybill = trim((string) ($input['paybill_number'] ?? ''));
    $accountPrefix = trim((string) ($input['account_prefix'] ?? 'TABLE'));
    $ownerName = trim((string) ($input['owner_name'] ?? ''));
    $ownerEmail = strtolower(trim((string) ($input['owner_email'] ?? '')));
    $ownerPassword = (string) ($input['owner_password'] ?? '');

    if ($name === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        jsonResponse(['error' => 'Provide an organization name and a valid slug'], 422);
    }
    if ($paybill === '' || !preg_match('/^[0-9A-Za-z_-]{3,30}$/', $paybill)) {
        jsonResponse(['error' => 'Provide a valid Paybill number'], 422);
    }
    if ($accountPrefix === '') {
        jsonResponse(['error' => 'Account prefix is required'], 422);
    }
    if ($ownerName === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) || strlen($ownerPassword) < 8) {
        jsonResponse(['error' => 'Provide client owner name, valid email, and password of at least 8 characters'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('INSERT INTO organizations (name, slug) VALUES (?, ?)');
        $stmt->execute([$name, $slug]);
        $organizationId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO mpesa_settings (organization_id, payment_type, paybill_number, account_prefix)
             VALUES (?, "paybill", ?, ?)'
        );
        $stmt->execute([$organizationId, $paybill, $accountPrefix]);

        $stmt = $pdo->prepare(
            'INSERT INTO users (organization_id, name, email, password_hash, role, active, is_system_admin)
             VALUES (?, ?, ?, ?, "owner", 1, 0)'
        );
        $stmt->execute([
            $organizationId,
            $ownerName,
            $ownerEmail,
            password_hash($ownerPassword, PASSWORD_DEFAULT),
        ]);

        $pdo->commit();

        jsonResponse([
            'message' => 'Client organization created',
            'data' => [
                'organization_id' => $organizationId,
                'organization_name' => $name,
                'slug' => $slug,
                'paybill_number' => $paybill,
                'owner_email' => $ownerEmail,
            ],
        ], 201);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            jsonResponse(['error' => 'Organization slug, Paybill number, or owner email already exists'], 409);
        }
        throw $e;
    }
}

function getOrganizationSummary(int $id): never
{
    requireSystemAdmin();

    $stmt = db()->prepare(
        'SELECT o.id, o.name, o.slug, o.created_at,
                m.payment_type, m.paybill_number, m.account_prefix
         FROM organizations o
         LEFT JOIN mpesa_settings m ON m.organization_id = o.id
         WHERE o.id = ?'
    );
    $stmt->execute([$id]);
    $organization = $stmt->fetch();

    if (!$organization) {
        jsonResponse(['error' => 'Organization not found'], 404);
    }

    jsonResponse(['data' => $organization]);
}
