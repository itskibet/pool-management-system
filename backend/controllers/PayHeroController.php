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
    $baseUrl = rtrim((string) env('PAYHERO_BASE_URL', 'https://api.payhero.africa'), '/');
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

/**
 * Create a PayHero offline Paybill request.
 * This does not send an STK Push. The customer completes the payment manually
 * in M-Pesa using the Paybill and account reference returned by PayHero.
 */
function initiatePayHeroPaybill(): never
{
    $input = readJsonInput();
    $tableId = filter_var($input['table_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$tableId) jsonResponse(['error' => 'table_id is required'], 422);

    $accountId = filter_var(env('PAYHERO_ACCOUNT_ID'), FILTER_VALIDATE_INT);
    $networkCode = trim((string) env('PAYHERO_NETWORK_CODE', ''));
    $merchantFeeRaw = env('PAYHERO_MERCHANT_FEE', '0');
    $merchantFee = is_numeric($merchantFeeRaw) ? (float) $merchantFeeRaw : -1;
    $callbackUrl = trim((string) env('PAYHERO_CALLBACK_URL', ''));
    $paybillNumber = trim((string) env('PAYHERO_PAYBILL_NUMBER', ''));
    $accountPrefix = trim((string) env('PAYHERO_PAYBILL_PREFIX', ''));

    if (!$accountId) jsonResponse(['error' => 'PAYHERO_ACCOUNT_ID is not configured'], 503);
    if ($networkCode === '') jsonResponse(['error' => 'PAYHERO_NETWORK_CODE is not configured'], 503);
    if ($merchantFee < 0) jsonResponse(['error' => 'PAYHERO_MERCHANT_FEE must be zero or greater'], 503);
    if ($callbackUrl === '') jsonResponse(['error' => 'PAYHERO_CALLBACK_URL is not configured'], 503);
    if ($paybillNumber === '' || $accountPrefix === '') {
        jsonResponse(['error' => 'Configure PAYHERO_PAYBILL_NUMBER and PAYHERO_PAYBILL_PREFIX from your PayHero account'], 503);
    }

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
        $insert = $pdo->prepare('INSERT INTO payments (organization_id, table_id, payment_method, amount, external_reference, status) VALUES (?, ?, "paybill", ?, ?, "pending")');
        $insert->execute([$organizationId, $table['id'], $table['price'], $externalReference]);
        $paymentId = (int) $pdo->lastInsertId();

        $payload = [
            'amount' => (int) round((float) $table['price']),
            'provider' => 'm-pesa',
            'network_code' => $networkCode,
            'account_id' => $accountId,
            'is_offline' => true,
            'merchant_fee' => $merchantFee,
            'external_reference' => $externalReference,
            'callback_url' => $callbackUrl,
        ];

        try {
            $response = payHeroClient()->post('/api/v2/payments', [
                'auth' => [$username, $password],
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Unable to connect to PayHero'], 502);
        }

        $statusCode = $response->getStatusCode();
        $responseBody = json_decode((string) $response->getBody(), true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($responseBody) || ($responseBody['success'] ?? false) !== true) {
            $failed = $pdo->prepare('UPDATE payments SET status = "failed", raw_callback = ? WHERE id = ? AND status = "pending"');
            $failed->execute([json_encode(['type' => 'payhero_paybill_create_error', 'http_status' => $statusCode, 'response' => $responseBody], JSON_THROW_ON_ERROR), $paymentId]);
            $pdo->commit();
            jsonResponse(['error' => 'PayHero did not accept the Paybill request', 'data' => ['payment_id' => $paymentId]], 502);
        }

        $checkoutRequestId = trim((string) ($responseBody['CheckoutRequestID'] ?? ''));
        $merchantReference = trim((string) ($responseBody['reference'] ?? ''));
        if ($checkoutRequestId === '') {
            $pdo->rollBack();
            jsonResponse(['error' => 'PayHero response did not include a CheckoutRequestID'], 502);
        }

        $accountReference = $accountPrefix . '#' . $checkoutRequestId;
        $update = $pdo->prepare('UPDATE payments SET checkout_request_id = ?, merchant_request_id = ?, account_reference = ? WHERE id = ? AND status = "pending"');
        $update->execute([$checkoutRequestId, $merchantReference !== '' ? $merchantReference : null, $accountReference, $paymentId]);
        $pdo->commit();

        jsonResponse([
            'message' => 'Paybill instructions generated. Complete payment manually in M-Pesa; no STK Push was sent.',
            'data' => [
                'payment_id' => $paymentId,
                'table_id' => (int) $table['id'],
                'table_number' => $table['table_number'],
                'amount' => (float) $table['price'],
                'paybill_number' => $paybillNumber,
                'account_reference' => $accountReference,
                'external_reference' => $externalReference,
                'checkout_request_id' => $checkoutRequestId,
                'status' => 'pending',
                'payment_method' => 'paybill',
            ],
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function payHeroCallback(string $providedSecret): never
{
    // PayHero's documented callback payload does not describe a signed webhook
    // header. Authenticate this endpoint with a high-entropy secret in the
    // callback URL path until PayHero supports a verifiable signature/header.
    $expectedSecret = trim((string) env('PAYHERO_CALLBACK_SECRET', ''));
    if ($expectedSecret === '' || strlen($expectedSecret) < 43) {
        jsonResponse(['error' => 'PayHero callback authentication is not configured'], 503);
    }
    if (!hash_equals($expectedSecret, $providedSecret)) {
        jsonResponse(['error' => 'Unauthorized callback'], 401);
    }

    $input = readJsonInput();
    if ($input === []) jsonResponse(['error' => 'Invalid PayHero callback payload'], 400);

    $externalReference = trim((string) ($input['external_reference'] ?? ''));
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    $transactionType = strtolower(trim((string) ($input['transaction_type'] ?? 'inbound_payment')));
    $amount = is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0.0;
    if ($externalReference === '' || !in_array($status, ['success', 'failed'], true) || $amount <= 0) {
        jsonResponse(['error' => 'PayHero callback is missing required or valid fields'], 422);
    }
    if ($transactionType !== 'inbound_payment') {
        jsonResponse(['error' => 'Callback is not an inbound payment'], 422);
    }
    if (isset($input['currency']) && strtoupper(trim((string) $input['currency'])) !== 'KES') {
        jsonResponse(['error' => 'Callback currency does not match KES'], 422);
    }
    if (array_key_exists('success', $input)) {
        if (!is_bool($input['success']) || (($status === 'success') !== $input['success'])) {
            jsonResponse(['error' => 'Callback status and success fields do not match'], 422);
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $paymentStmt = $pdo->prepare('SELECT p.id, p.organization_id, p.table_id, p.amount, p.status, p.external_reference, t.table_number, t.status AS table_status, t.mqtt_topic FROM payments p INNER JOIN tables t ON t.id = p.table_id WHERE p.external_reference = ? LIMIT 1 FOR UPDATE');
        $paymentStmt->execute([$externalReference]);
        $payment = $paymentStmt->fetch();
        if (!$payment) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Payment reference not found'], 404);
        }
        if (abs($amount - (float) $payment['amount']) > 0.001) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Callback amount does not match the payment'], 422);
        }
        if ($payment['status'] === 'confirmed') {
            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Payment already processed']);
        }

        $transactionId = trim((string) ($input['transaction_id'] ?? ''));
        $providerReference = trim((string) ($input['provider_reference'] ?? ''));
        $merchantReference = trim((string) ($input['reference'] ?? ''));
        $resolvedTransactionId = $transactionId !== '' ? $transactionId : ($providerReference !== '' ? $providerReference : $merchantReference);

        if ($status !== 'success') {
            $failed = $pdo->prepare('UPDATE payments SET status = "failed", raw_callback = ? WHERE id = ? AND status = "pending"');
            $failed->execute([json_encode($input, JSON_THROW_ON_ERROR), $payment['id']]);
            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Failed Paybill payment recorded']);
        }
        if ($resolvedTransactionId === '') throw new RuntimeException('Successful PayHero callback has no transaction reference');
        if ($payment['table_status'] !== 'available') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Payment succeeded but the table is no longer available', 'payment_id' => (int) $payment['id']], 409);
        }

        $duplicateStmt = $pdo->prepare('SELECT id FROM payments WHERE transaction_id = ? AND id <> ? LIMIT 1 FOR UPDATE');
        $duplicateStmt->execute([$resolvedTransactionId, $payment['id']]);
        if ($duplicateStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'PayHero transaction was already processed'], 409);
        }

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

        jsonResponse(['success' => true, 'message' => 'PayHero Paybill payment confirmed and game created', 'data' => ['payment_id' => (int) $payment['id'], 'game_id' => $gameId, 'table_id' => (int) $payment['table_id']]]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string) $e->getCode() === '23000') jsonResponse(['success' => true, 'message' => 'Payment callback was already processed']);
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
