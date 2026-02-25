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
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            snapshot_date DATE NOT NULL,
            metrics_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashboard_kpi_snapshots_business_date (business_id, snapshot_date),
            KEY idx_dashboard_kpi_snapshots_business (business_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        if (!Schema::hasColumn('dashboard_kpi_snapshots', 'business_id')) {
            try {
                Database::connection()->exec('ALTER TABLE dashboard_kpi_snapshots ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id');
            } catch (\Throwable) {
                // ignore drift
            }
        }
        if (Schema::hasColumn('dashboard_kpi_snapshots', 'business_id')) {
            try {
                Database::connection()->exec('UPDATE dashboard_kpi_snapshots SET business_id = 1 WHERE business_id IS NULL OR business_id = 0');
            } catch (\Throwable) {
                // ignore drift
            }
            try {
                Database::connection()->exec('ALTER TABLE dashboard_kpi_snapshots DROP INDEX uniq_dashboard_kpi_snapshots_date');
            } catch (\Throwable) {
                // ignore drift
            }
            try {
                Database::connection()->exec('ALTER TABLE dashboard_kpi_snapshots ADD UNIQUE KEY uniq_dashboard_kpi_snapshots_business_date (business_id, snapshot_date)');
            } catch (\Throwable) {
                // ignore drift
            }
            try {
                Database::connection()->exec('CREATE INDEX idx_dashboard_kpi_snapshots_business ON dashboard_kpi_snapshots (business_id)');
            } catch (\Throwable) {
                // ignore drift
            }
        }

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
                    (business_id, snapshot_date, metrics_json, created_at, updated_at)
                VALUES
                    (:business_id, :snapshot_date, :metrics_json, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    metrics_json = VALUES(metrics_json),
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => self::currentBusinessId(),
            'snapshot_date' => $date,
            'metrics_json' => $json,
        ]);
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
