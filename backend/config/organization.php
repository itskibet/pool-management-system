<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function currentOrganizationId(): int
{
    return max(1, (int) env('DEFAULT_ORGANIZATION_ID', '1'));
}

function normalizeTableReference(string $value): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?? '';
}
