<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/organization.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function payHeroClient(): Client
{
    $baseUrl = rtrim((string) env('PAYHERO_BASE_URL', 'https://backend.payhero.co.ke'), '/');
    return new Client(['base_uri' => $baseUrl, 'timeout' => 20, 'connect_timeout' => 10, 'http_errors' => false]);
}

function payHeroAuth(): array
{
    $username = trim((string) env('PAYHERO_API_USERNAME', ''));
    $password = (string) env('PAYHERO_API_PASSWORD', '');
    if ($username === '' || $password === '') {
        jsonResponse(['error' => 'PayHero API credentials are not configured'], 503);
    }
    return [$username, $password];
}

function readJsonInput(): array
{
    $input = json_decode((string) file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function initiatePayHeroStkPush(): never
{
    $input = readJsonInput();
    $tableId = filter_var($input['table_id'] ?? null, FILTER_VALIDATE_INT);
    $phoneNumber = trim((string) ($input['phone_number'] ?? ''));
    $customerName = trim((string) ($input['customer_name'] ?? ''));

    if (!$tableId) jsonResponse(['error' => 'table_id is required'], 422);
    if ($phoneNumber === '') jsonResponse(['error' => 'phone_number is required'], 422);

    $channelId = filter_var(env('PAYHERO_CHANNEL_ID'), FILTER_VALIDATE_INT);
    $callbackUrl = trim((string) env('PAYHERO_CALLBACK_URL', ''));
    if (!$channelId) jsonResponse(['error' => 'PAYHERO_CHANNEL_ID is not configured'], 503);
    if ($callbackUrl === '') jsonResponse(['error' => 'PAYHERO_CALLBACK_URL is not configured'], 503);

    [$username, $password] = payHeroAuth();
    $organizationId = currentOrganizationId();
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $tableStmt = $pdo->prepare('SELECT id, table_number, price, status FROM tables WHERE id = ? AND organization_id = ? FOR UPDATE');
        $tableStmt->execute([$tableId, $organizationId]);
        $table = $tableStmt->fetch();

        if (!$table) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Table not found'], 404);
        }
        if ($table['status'] !== 'available') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Table is not available for a new payment'], 409);
        }

        $externalReference = sprintf('POOL-%d-T%d-%s', $organizationId, $tableId, strtoupper(bin2hex(random_bytes(5))));
        $insert = $pdo->prepare('INSERT INTO payments (organization_id, table_id, payment_method, phone_number, amount, account_reference, external_reference, status) VALUES (?, ?, "stk_push", ?, ?, ?, ?, "pending")');
        $insert->execute([$organizationId, $table['id'], $phoneNumber, $table['price'], $table['table_number'], $externalReference]);
        $paymentId = (int) $pdo->lastInsertId();

        $payload = [
            'amount' => (int) round((float) $table['price']),
            'phone_number' => $phoneNumber,
            'channel_id' => $channelId,
            'provider' => 'm-pesa',
            'external_reference' => $externalReference,
            'callback_url' => $callbackUrl,
        ];
        if ($customerName !== '') $payload['customer_name'] = $customerName;

        try {
            $response = payHeroClient()->post('/api/v2/payments', [
                'auth' => [$username, $password],
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Unable to connect to PayHero', 'data' => ['payment_id' => $paymentId, 'external_reference' => $externalReference]], 502);
        }

        $statusCode = $response->getStatusCode();
        $responseBody = json_decode((string) $response->getBody(), true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($responseBody) || ($responseBody['success'] ?? false) !== true) {
            $failed = $pdo->prepare('UPDATE payments SET status = "failed", raw_callback = ? WHERE id = ? AND status = "pending"');
            $failed->execute([json_encode(['type' => 'payhero_create_error', 'http_status' => $statusCode, 'response' => $responseBody], JSON_THROW_ON_ERROR), $paymentId]);
            $pdo->commit();
            jsonResponse(['error' => 'PayHero did not accept the payment request', 'data' => ['payment_id' => $paymentId, 'external_reference' => $externalReference]], 502);
        }

        $checkoutRequestId = trim((string) ($responseBody['CheckoutRequestID'] ?? ''));
        $merchantReference = trim((string) ($responseBody['reference'] ?? ''));
        $update = $pdo->prepare('UPDATE payments SET checkout_request_id = ?, merchant_request_id = ? WHERE id = ? AND status = "pending"');
        $update->execute([$checkoutRequestId !== '' ? $checkoutRequestId : null, $merchantReference !== '' ? $merchantReference : null, $paymentId]);
        $pdo->commit();

        jsonResponse(['message' => 'PayHero payment request accepted; waiting for callback', 'data' => ['payment_id' => $paymentId, 'table_id' => (int) $table['id'], 'table_number' => $table['table_number'], 'amount' => (float) $table['price'], 'external_reference' => $externalReference, 'status' => 'pending', 'payhero_reference' => $merchantReference !== '' ? $merchantReference : null, 'checkout_request_id' => $checkoutRequestId !== '' ? $checkoutRequestId : null]], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function payHeroCallback(): never
{
    $input = readJsonInput();
    if ($input === []) jsonResponse(['error' => 'Invalid PayHero callback payload'], 400);

    $externalReference = trim((string) ($input['external_reference'] ?? ''));
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
    if ($externalReference === '' || $status === '' || $amount <= 0) jsonResponse(['error' => 'PayHero callback is missing required fields'], 422);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $paymentStmt = $pdo->prepare('SELECT p.id, p.organization_id, p.table_id, p.amount, p.status, p.external_reference, t.table_number, t.status AS table_status, t.mqtt_topic FROM payments p INNER JOIN tables t ON t.id = p.table_id WHERE p.external_reference = ? LIMIT 1 FOR UPDATE');
        $paymentStmt->execute([$externalReference]);
        $payment = $paymentStmt->fetch();
        if (!$payment) { $pdo->rollBack(); jsonResponse(['error' => 'Payment reference not found'], 404); }
        if (abs($amount - (float) $payment['amount']) > 0.001) { $pdo->rollBack(); jsonResponse(['error' => 'Callback amount does not match the payment'], 422); }
        if ($payment['status'] === 'confirmed') { $pdo->commit(); jsonResponse(['success' => true, 'message' => 'Payment already processed']); }

        $transactionId = trim((string) ($input['transaction_id'] ?? ''));
        $providerReference = trim((string) ($input['provider_reference'] ?? ''));
        $merchantReference = trim((string) ($input['reference'] ?? ''));
        $resolvedTransactionId = $transactionId !== '' ? $transactionId : ($providerReference !== '' ? $providerReference : $merchantReference);

        if ($status !== 'success') {
            $failed = $pdo->prepare('UPDATE payments SET status = "failed", raw_callback = ? WHERE id = ? AND status = "pending"');
            $failed->execute([json_encode($input, JSON_THROW_ON_ERROR), $payment['id']]);
            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Failed payment recorded']);
        }
        if ($resolvedTransactionId === '') throw new RuntimeException('Successful PayHero callback has no transaction reference');
        if ($payment['table_status'] !== 'available') { $pdo->rollBack(); jsonResponse(['error' => 'Payment succeeded but the table is no longer available', 'payment_id' => (int) $payment['id']], 409); }

        $duplicateStmt = $pdo->prepare('SELECT id FROM payments WHERE transaction_id = ? AND id <> ? LIMIT 1 FOR UPDATE');
        $duplicateStmt->execute([$resolvedTransactionId, $payment['id']]);
        if ($duplicateStmt->fetch()) { $pdo->rollBack(); jsonResponse(['error' => 'PayHero transaction was already processed'], 409); }

        $transactionDate = trim((string) ($input['transaction_date'] ?? ''));
        $timestamp = $transactionDate !== '' ? strtotime($transactionDate) : false;
        $paidAt = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
        $updatePayment = $pdo->prepare('UPDATE payments SET status = "confirmed", transaction_id = ?, mpesa_receipt = ?, paid_at = COALESCE(?, NOW()), raw_callback = ? WHERE id = ? AND status = "pending"');
        $updatePayment->execute([$resolvedTransactionId, $providerReference !== '' ? $providerReference : null, $paidAt, json_encode($input, JSON_THROW_ON_ERROR), $payment['id']]);

        $gameInsert = $pdo->prepare('INSERT INTO games (organization_id, table_id, payment_id, started_at, status) VALUES (?, ?, ?, NOW(), "active")');
        $gameInsert->execute([$payment['organization_id'], $payment['table_id'], $payment['id']]);
        $gameId = (int) $pdo->lastInsertId();

        $tableUpdate = $pdo->prepare('UPDATE tables SET status = "playing" WHERE id = ? AND organization_id = ? AND status = "available"');
        $tableUpdate->execute([$payment['table_id'], $payment['organization_id']]);
        if ($tableUpdate->rowCount() !== 1) throw new RuntimeException('Table state changed while confirming PayHero payment');

        $commandInsert = $pdo->prepare('INSERT INTO mqtt_commands (organization_id, table_id, payment_id, game_id, topic, command, status) VALUES (?, ?, ?, ?, ?, "unlock", "queued")');
        $commandInsert->execute([$payment['organization_id'], $payment['table_id'], $payment['id'], $gameId, $payment['mqtt_topic']]);
        $pdo->commit();

        jsonResponse(['success' => true, 'message' => 'PayHero payment confirmed and game created', 'data' => ['payment_id' => (int) $payment['id'], 'game_id' => $gameId, 'table_id' => (int) $payment['table_id']]]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string) $e->getCode() === '23000') jsonResponse(['success' => true, 'message' => 'Payment callback was already processed']);
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
