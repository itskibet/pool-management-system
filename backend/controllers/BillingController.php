<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/organization.php';

function listDailyClosings(): never
{
    requireAuth();

    $stmt = db()->prepare(
        'SELECT d.id, d.business_date, d.gross_revenue, d.platform_fee_rate,
                d.platform_fee_amount, d.status, d.closed_at,
                u.name AS closed_by_name
         FROM daily_closings d
         LEFT JOIN users u ON u.id = d.closed_by
         WHERE d.organization_id = ?
         ORDER BY d.business_date DESC'
    );
    $stmt->execute([currentOrganizationId()]);

    jsonResponse(['data' => $stmt->fetchAll()]);
}

function closeDailyBusiness(): never
{
    $actor = requireRoles(['owner', 'admin']);
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];

    $businessDate = trim((string) ($input['business_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessDate)) {
        jsonResponse(['error' => 'business_date must use YYYY-MM-DD'], 422);
    }

    $organizationId = (int) $actor['organization_id'];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $check = $pdo->prepare(
            'SELECT id FROM daily_closings
             WHERE organization_id = ? AND business_date = ?
             FOR UPDATE'
        );
        $check->execute([$organizationId, $businessDate]);
        if ($check->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'This business day is already closed'], 409);
        }

        $revenue = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE organization_id = ?
               AND status = "confirmed"
               AND paid_at >= ?
               AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)'
        );
        $revenue->execute([$organizationId, $businessDate, $businessDate]);
        $grossRevenue = round((float) $revenue->fetchColumn(), 2);
        $feeRate = 4.00;
        $feeAmount = round($grossRevenue * ($feeRate / 100), 2);

        $insert = $pdo->prepare(
            'INSERT INTO daily_closings
             (organization_id, business_date, gross_revenue, platform_fee_rate, platform_fee_amount, status, closed_by)
             VALUES (?, ?, ?, ?, ?, "closed", ?)'
        );
        $insert->execute([
            $organizationId,
            $businessDate,
            $grossRevenue,
            $feeRate,
            $feeAmount,
            $actor['id'],
        ]);

        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        jsonResponse([
            'message' => 'Business day closed and 4% platform fee calculated',
            'data' => [
                'id' => $id,
                'business_date' => $businessDate,
                'gross_revenue' => $grossRevenue,
                'platform_fee_rate' => $feeRate,
                'platform_fee_amount' => $feeAmount,
                'status' => 'closed',
            ],
        ], 201);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string) $e->getCode() === '23000') {
            jsonResponse(['error' => 'This business day is already closed'], 409);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
