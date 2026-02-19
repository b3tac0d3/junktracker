<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DashboardKpiSnapshot
{
    public static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Database::connection()->exec('CREATE TABLE IF NOT EXISTS dashboard_kpi_snapshots (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_date DATE NOT NULL,
            metrics_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashboard_kpi_snapshots_date (snapshot_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $ensured = true;
    }

    public static function record(string $snapshotDate, array $metrics): void
    {
        $date = trim($snapshotDate);
        if ($date === '') {
            return;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return;
        }
        $date = date('Y-m-d', $timestamp);

        self::ensureTable();

        $json = json_encode($metrics);
        if ($json === false) {
            return;
        }

        $sql = 'INSERT INTO dashboard_kpi_snapshots
                    (snapshot_date, metrics_json, created_at, updated_at)
                VALUES
                    (:snapshot_date, :metrics_json, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    metrics_json = VALUES(metrics_json),
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'snapshot_date' => $date,
            'metrics_json' => $json,
        ]);
    }
}
