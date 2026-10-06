<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function getPaymentSettings(): never
{
    $stmt = db()->prepare(
        'SELECT o.id AS organization_id, o.name AS organization_name,
                m.payment_type, m.paybill_number, m.account_prefix
         FROM organizations o
         INNER JOIN mpesa_settings m ON m.organization_id = o.id
         WHERE o.id = ?'
    );
    $stmt->execute([currentOrganizationId()]);
    $settings = $stmt->fetch();

    if (!$settings) {
        jsonResponse(['error' => 'Payment settings are not configured'], 404);
    }

    jsonResponse(['data' => [
        'organization_id' => (int) $settings['organization_id'],
        'organization_name' => $settings['organization_name'],
        'payment_type' => $settings['payment_type'],
        'paybill_number' => $settings['paybill_number'],
        'account_prefix' => $settings['account_prefix'],
    ]]);
}
