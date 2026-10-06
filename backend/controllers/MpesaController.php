<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';

function readMpesaPayload(): array
{
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (is_array($input)) {
        return $input;
    }

    parse_str($raw, $formInput);
    return is_array($formInput) ? $formInput : [];
}

function resolveMpesaPayment(array $input): array
{
    $transactionId = trim((string) ($input['TransID'] ?? ''));
    $amountRaw = $input['TransAmount'] ?? null;
    $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;
    $accountReference = trim((string) ($input['BillRefNumber'] ?? ''));
    $businessShortCode = trim((string) ($input['BusinessShortCode'] ?? ''));

    if ($amount <= 0 || $accountReference === '' || $businessShortCode === '') {
        jsonResponse([
            'ResultCode' => 1,
            'ResultDesc' => 'Missing Paybill, table reference or amount',
        ], 422);
    }

    $settingsStmt = db()->prepare(
        'SELECT organization_id, paybill_number
         FROM mpesa_settings
         WHERE paybill_number = ? AND payment_type = "paybill"
         LIMIT 1'
    );
    $settingsStmt->execute([$businessShortCode]);
    $settings = $settingsStmt->fetch();

    if (!$settings || $settings['paybill_number'] === 'CHANGE_ME') {
        jsonResponse([
            'ResultCode' => 1,
            'ResultDesc' => 'Payment was sent to an unregistered Paybill',
        ], 422);
    }

    $organizationId = (int) $settings['organization_id'];
    $normalizedReference = normalizeTableReference($accountReference);

    $tableStmt = db()->prepare(
        'SELECT id, table_number, price, status, mqtt_topic
         FROM tables
         WHERE organization_id = ?
         ORDER BY id ASC'
    );
    $tableStmt->execute([$organizationId]);

    foreach ($tableStmt->fetchAll() as $table) {
        if (normalizeTableReference((string) $table['table_number']) !== $normalizedReference) {
            continue;
        }

        if (abs($amount - (float) $table['price']) > 0.001) {
            jsonResponse([
                'ResultCode' => 1,
                'ResultDesc' => 'Incorrect payment amount for this table',
            ], 422);
        }

        return [
            'organization_id' => $organizationId,
            'table' => $table,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'account_reference' => $accountReference,
        ];
    }

    jsonResponse([
        'ResultCode' => 1,
        'ResultDesc' => 'Table reference not found',
    ], 422);
}

function mpesaC2BValidation(): never
{
    $input = readMpesaPayload();
    $resolved = resolveMpesaPayment($input);

    if (($resolved['table']['status'] ?? '') !== 'available') {
        jsonResponse([
            'ResultCode' => 1,
            'ResultDesc' => 'Table is not available for a new game',
        ]);
    }

    jsonResponse([
        'ResultCode' => 0,
        'ResultDesc' => 'Accepted',
    ]);
}

function mpesaC2BConfirmation(): never
{
    $input = readMpesaPayload();

    if (!is_array($input) || $input === []) {
        jsonResponse(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback payload'], 400);
    }

    $resolved = resolveMpesaPayment($input);
    $transactionId = trim((string) ($input['TransID'] ?? ''));

    if ($transactionId === '') {
        jsonResponse([
            'ResultCode' => 1,
            'ResultDesc' => 'TransID is required for confirmation',
        ], 422);
    }

    $phoneNumber = trim((string) ($input['MSISDN'] ?? ''));
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $duplicate = $pdo->prepare(
            'SELECT id FROM payments WHERE transaction_id = ? LIMIT 1 FOR UPDATE'
        );
        $duplicate->execute([$transactionId]);

        if ($duplicate->fetch()) {
            $pdo->commit();
            jsonResponse([
                'ResultCode' => 0,
                'ResultDesc' => 'Transaction already processed',
            ]);
        }

        $tableStmt = $pdo->prepare(
            'SELECT id, table_number, price, status, mqtt_topic
             FROM tables
             WHERE id = ? AND organization_id = ?
             FOR UPDATE'
        );
        $tableStmt->execute([
            $resolved['table']['id'],
            $resolved['organization_id'],
        ]);
        $table = $tableStmt->fetch();

        if (!$table) {
            throw new RuntimeException('Table disappeared during payment processing');
        }

        $insert = $pdo->prepare(
            'INSERT INTO payments
             (organization_id, table_id, payment_method, phone_number, amount, account_reference,
              transaction_id, status, paid_at, raw_callback)
             VALUES (?, ?, "paybill", ?, ?, ?, ?, "confirmed", NOW(), ?)'
        );
        $insert->execute([
            $resolved['organization_id'],
            $table['id'],
            $phoneNumber !== '' ? $phoneNumber : null,
            $resolved['amount'],
            $resolved['account_reference'],
            $transactionId,
            json_encode($input, JSON_THROW_ON_ERROR),
        ]);

        $paymentId = (int) $pdo->lastInsertId();
        $gameId = null;

        if ($table['status'] === 'available') {
            $gameInsert = $pdo->prepare(
                'INSERT INTO games
                 (organization_id, table_id, payment_id, started_at, status)
                 VALUES (?, ?, ?, NOW(), "active")'
            );
            $gameInsert->execute([
                $resolved['organization_id'],
                $table['id'],
                $paymentId,
            ]);
            $gameId = (int) $pdo->lastInsertId();

            $tableUpdate = $pdo->prepare(
                'UPDATE tables
                 SET status = "playing"
                 WHERE id = ? AND organization_id = ? AND status = "available"'
            );
            $tableUpdate->execute([
                $table['id'],
                $resolved['organization_id'],
            ]);

            if ($tableUpdate->rowCount() !== 1) {
                throw new RuntimeException('Table state changed while creating the game');
            }

            $commandInsert = $pdo->prepare(
                'INSERT INTO mqtt_commands
                 (organization_id, table_id, payment_id, game_id, topic, command, status)
                 VALUES (?, ?, ?, ?, ?, "unlock", "queued")'
            );
            $commandInsert->execute([
                $resolved['organization_id'],
                $table['id'],
                $paymentId,
                $gameId,
                $table['mqtt_topic'],
            ]);
        }

        $pdo->commit();

        if ($gameId === null) {
            jsonResponse([
                'ResultCode' => 0,
                'ResultDesc' => 'Payment recorded; table was unavailable, so no unlock was queued',
            ]);
        }

        jsonResponse([
            'ResultCode' => 0,
            'ResultDesc' => 'Payment confirmed and game created',
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ((string) $e->getCode() === '23000' && $transactionId !== '') {
            jsonResponse([
                'ResultCode' => 0,
                'ResultDesc' => 'Transaction already processed',
            ]);
        }

        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
