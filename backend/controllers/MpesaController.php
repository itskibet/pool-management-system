<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function mpesaC2BConfirmation(): never
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON'], 400);
    }

    $transactionId = trim((string) ($input['TransID'] ?? ''));
    $amount = (float) ($input['TransAmount'] ?? 0);
    $accountReference = trim((string) ($input['BillRefNumber'] ?? ''));
    $businessShortCode = trim((string) ($input['BusinessShortCode'] ?? ''));
    $phoneNumber = trim((string) ($input['MSISDN'] ?? ''));
    $transactionTime = trim((string) ($input['TransTime'] ?? ''));

    if ($transactionId === '' || $amount <= 0 || $accountReference === '') {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Missing required transaction fields'], 422);
    }

    $organizationId = currentOrganizationId();

    $settingsStmt = db()->prepare(
        'SELECT paybill_number FROM mpesa_settings WHERE organization_id = ? LIMIT 1'
    );
    $settingsStmt->execute([$organizationId]);
    $settings = $settingsStmt->fetch();

    if (!$settings || $settings['paybill_number'] === 'CHANGE_ME') {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Paybill is not configured'], 422);
    }

    if ($businessShortCode !== '' && $businessShortCode !== $settings['paybill_number']) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Payment was sent to an unexpected Paybill'], 422);
    }

    $duplicateStmt = db()->prepare(
        'SELECT id FROM payments WHERE transaction_id = ? LIMIT 1'
    );
    $duplicateStmt->execute([$transactionId]);

    if ($duplicateStmt->fetch()) {
        jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Transaction already processed']);
    }

    $normalizedReference = normalizeTableReference($accountReference);

    $tableStmt = db()->prepare(
        'SELECT id, table_number, price, status, mqtt_topic
         FROM tables
         WHERE organization_id = ?
         ORDER BY id ASC'
    );
    $tableStmt->execute([$organizationId]);
    $tables = $tableStmt->fetchAll();

    $table = null;
    foreach ($tables as $candidate) {
        if (normalizeTableReference((string) $candidate['table_number']) === $normalizedReference) {
            $table = $candidate;
            break;
        }
    }

    if (!$table) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Table reference not found'], 422);
    }

    $expectedAmount = (float) $table['price'];
    if (abs($amount - $expectedAmount) > 0.001) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Incorrect payment amount for this table'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare(
            'INSERT INTO payments
             (organization_id, table_id, payment_method, phone_number, amount, account_reference,
              transaction_id, status, paid_at, raw_callback)
             VALUES (?, ?, "paybill", ?, ?, ?, ?, "confirmed", NOW(), ?)'
        );
        $insert->execute([
            $organizationId,
            $table['id'],
            $phoneNumber !== '' ? $phoneNumber : null,
            $amount,
            $accountReference,
            $transactionId,
            json_encode($input, JSON_THROW_ON_ERROR),
        ]);

        $paymentId = (int) $pdo->lastInsertId();

        if ($table['status'] === 'available' || $table['status'] === 'payment_pending') {
            $gameInsert = $pdo->prepare(
                'INSERT INTO games
                 (organization_id, table_id, payment_id, started_at, status)
                 VALUES (?, ?, ?, NOW(), "active")'
            );
            $gameInsert->execute([$organizationId, $table['id'], $paymentId]);
            $gameId = (int) $pdo->lastInsertId();

            $tableUpdate = $pdo->prepare(
                'UPDATE tables SET status = "playing" WHERE id = ? AND organization_id = ?'
            );
            $tableUpdate->execute([$table['id'], $organizationId]);

            $commandInsert = $pdo->prepare(
                'INSERT INTO mqtt_commands
                 (organization_id, table_id, payment_id, game_id, topic, command, status)
                 VALUES (?, ?, ?, ?, ?, "unlock", "queued")'
            );
            $commandInsert->execute([
                $organizationId,
                $table['id'],
                $paymentId,
                $gameId,
                $table['mqtt_topic'],
            ]);
        }

        $pdo->commit();
        jsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Payment confirmed and processed']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
