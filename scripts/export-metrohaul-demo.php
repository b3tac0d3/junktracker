<?php

declare(strict_types=1);

/**
 * Export Metro Haul demo data as SQL INSERT statements for live import.
 *
 * Usage:
 *   HTTP_HOST=localhost php scripts/export-metrohaul-demo.php
 *   HTTP_HOST=localhost php scripts/export-metrohaul-demo.php --business-id=2 --output=database/seeds/metrohaul-demo.sql
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $root . '/app/bootstrap.php';

use Core\Database;

$args = array_slice($argv, 1);
$businessId = 2;
$outputPath = $root . '/database/seeds/metrohaul-demo.sql';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--business-id=')) {
        $businessId = max(1, (int) substr($arg, strlen('--business-id=')));
    }
    if (str_starts_with($arg, '--output=')) {
        $outputPath = (string) substr($arg, strlen('--output='));
        if ($outputPath[0] !== '/') {
            $outputPath = $root . '/' . ltrim($outputPath, '/');
        }
    }
}

$pdo = Database::connection();
$dir = dirname($outputPath);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$business = $pdo->prepare('SELECT * FROM businesses WHERE id = :id LIMIT 1');
$business->execute(['id' => $businessId]);
$businessRow = $business->fetch(PDO::FETCH_ASSOC);
if (!is_array($businessRow)) {
    fwrite(STDERR, "Business #{$businessId} not found.\n");
    exit(1);
}

$tables = [
    'business_user_memberships',
    'clients',
    'employees',
    'quotes',
    'jobs',
    'job_employee_assignments',
    'events',
    'invoices',
    'invoice_items',
    'payments',
    'sales',
    'expenses',
    'purchases',
    'tasks',
    'employee_time_entries',
];

$lines = [];
$lines[] = '-- Metro Haul demo export';
$lines[] = '-- Generated: ' . date('Y-m-d H:i:s');
$lines[] = '-- Business ID: ' . $businessId;
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
$lines[] = '';
$lines[] = '-- Business row (upsert)';
$lines[] = buildUpsert($pdo, 'businesses', [$businessRow], 'id');
$lines[] = '';

foreach ($tables as $table) {
    if (!tableExists($pdo, $table)) {
        continue;
    }

    $rows = fetchBusinessRows($pdo, $table, $businessId);
    if ($rows === []) {
        continue;
    }

    $lines[] = '-- ' . $table . ' (' . count($rows) . ' rows)';
    $lines[] = buildUpsert($pdo, $table, $rows, primaryKeyColumn($pdo, $table));
    $lines[] = '';
}

$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
$lines[] = '';

file_put_contents($outputPath, implode("\n", $lines));
echo "Exported to {$outputPath}\n";

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function primaryKeyColumn(PDO $pdo, string $table): string
{
    return 'id';
}

/**
 * @return list<array<string, mixed>>
 */
function fetchBusinessRows(PDO $pdo, string $table, int $businessId): array
{
    if ($table === 'business_user_memberships') {
        $stmt = $pdo->prepare(
            'SELECT * FROM business_user_memberships WHERE business_id = :business_id AND deleted_at IS NULL'
        );
        $stmt->execute(['business_id' => $businessId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (!columnExists($pdo, $table, 'business_id')) {
        return [];
    }

    $deleted = columnExists($pdo, $table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE business_id = :business_id{$deleted} ORDER BY id ASC");
    $stmt->execute(['business_id' => $businessId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param list<array<string, mixed>> $rows
 */
function buildUpsert(PDO $pdo, string $table, array $rows, string $primaryKey): string
{
    if ($rows === []) {
        return '-- (no rows)';
    }

    $columns = array_keys($rows[0]);
    $columnSql = implode(', ', array_map(static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`', $columns));
    $chunks = [];

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = sqlValue($row[$column] ?? null);
        }
        $chunks[] = '(' . implode(', ', $values) . ')';
    }

    $updates = [];
    foreach ($columns as $column) {
        if ($column === $primaryKey) {
            continue;
        }
        $quoted = '`' . str_replace('`', '``', $column) . '`';
        $updates[] = "{$quoted} = VALUES({$quoted})";
    }

    $sql = "INSERT INTO `{$table}` ({$columnSql}) VALUES\n  " . implode(",\n  ", $chunks);
    if ($updates !== []) {
        $sql .= "\nON DUPLICATE KEY UPDATE\n  " . implode(",\n  ", $updates);
    }
    $sql .= ';';

    return $sql;
}

function sqlValue(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return $GLOBALS['pdo']->quote((string) $value);
}

$GLOBALS['pdo'] = $pdo;
