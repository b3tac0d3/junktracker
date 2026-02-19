<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ReportingHub
{
    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS report_presets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                report_key VARCHAR(60) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                filters_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_report_presets_user_name (user_id, name),
                KEY idx_report_presets_user_key (user_id, report_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $schema = trim((string) config('database.database', ''));
        if ($schema !== '') {
            self::ensureForeignKey(
                'fk_report_presets_user',
                'ALTER TABLE report_presets
                 ADD CONSTRAINT fk_report_presets_user
                 FOREIGN KEY (user_id) REFERENCES users(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
        }

        self::$schemaEnsured = true;
    }

    public static function normalizeDateRange(?string $startDate, ?string $endDate): array
    {
        $start = trim((string) ($startDate ?? ''));
        $end = trim((string) ($endDate ?? ''));

        if ($start === '') {
            $start = date('Y-m-01');
        }
        if ($end === '') {
            $end = date('Y-m-t');
        }

        $startTime = strtotime($start);
        $endTime = strtotime($end);

        if ($startTime === false) {
            $start = date('Y-m-01');
            $startTime = strtotime($start);
        }
        if ($endTime === false) {
            $end = date('Y-m-t');
            $endTime = strtotime($end);
        }

        if ($startTime !== false && $endTime !== false && $startTime > $endTime) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start_date' => date('Y-m-d', strtotime($start)),
            'end_date' => date('Y-m-d', strtotime($end)),
        ];
    }

    public static function jobProfitability(string $startDate, string $endDate, int $limit = 250): array
    {
        $range = self::normalizeDateRange($startDate, $endDate);
        $limit = max(25, min($limit, 1000));

        $sql = 'SELECT
                    j.id,
                    j.name,
                    j.job_status,
                    j.scheduled_date,
                    COALESCE(
                        NULLIF(c.business_name, ""),
                        NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""),
                        CONCAT("Client #", c.id)
                    ) AS client_name,
                    COALESCE(j.total_billed, 0) AS invoice_total,
                    COALESCE(disposal.scrap_total, 0) AS scrap_total,
                    COALESCE(disposal.dump_total, 0) AS dump_total,
                    COALESCE(expenses.expense_total, 0) AS expense_total,
                    COALESCE(labor.labor_total, 0) AS labor_total
                FROM jobs j
                LEFT JOIN clients c ON c.id = j.client_id
                LEFT JOIN (
                    SELECT d.job_id,
                           COALESCE(SUM(CASE WHEN d.type = "scrap" THEN COALESCE(d.amount, 0) ELSE 0 END), 0) AS scrap_total,
                           COALESCE(SUM(CASE WHEN d.type = "dump" THEN COALESCE(d.amount, 0) ELSE 0 END), 0) AS dump_total
                    FROM job_disposal_events d
                    WHERE d.deleted_at IS NULL
                      AND DATE(COALESCE(d.event_date, d.created_at)) BETWEEN :start_date_disposal AND :end_date_disposal
                    GROUP BY d.job_id
                ) disposal ON disposal.job_id = j.id
                LEFT JOIN (
                    SELECT e.job_id,
                           COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS expense_total
                    FROM expenses e
                    WHERE e.deleted_at IS NULL
                      AND COALESCE(e.is_active, 1) = 1
                      AND DATE(COALESCE(e.expense_date, e.created_at)) BETWEEN :start_date_expense AND :end_date_expense
                    GROUP BY e.job_id
                ) expenses ON expenses.job_id = j.id
                LEFT JOIN (
                    SELECT t.job_id,
                           COALESCE(SUM(COALESCE(t.total_paid, (COALESCE(t.pay_rate, 0) * COALESCE(t.minutes_worked, 0)) / 60)), 0) AS labor_total
                    FROM employee_time_entries t
                    WHERE t.deleted_at IS NULL
                      AND COALESCE(t.active, 1) = 1
                      AND DATE(COALESCE(t.work_date, t.created_at)) BETWEEN :start_date_labor AND :end_date_labor
                    GROUP BY t.job_id
                ) labor ON labor.job_id = j.id
                WHERE j.deleted_at IS NULL
                  AND COALESCE(j.active, 1) = 1
                  AND (
                        DATE(COALESCE(j.paid_date, j.billed_date, j.end_date, j.updated_at, j.created_at)) BETWEEN :start_date_job AND :end_date_job
                        OR disposal.job_id IS NOT NULL
                        OR expenses.job_id IS NOT NULL
                        OR labor.job_id IS NOT NULL
                  )
                ORDER BY j.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'start_date_disposal' => $range['start_date'],
            'end_date_disposal' => $range['end_date'],
            'start_date_expense' => $range['start_date'],
            'end_date_expense' => $range['end_date'],
            'start_date_labor' => $range['start_date'],
            'end_date_labor' => $range['end_date'],
            'start_date_job' => $range['start_date'],
            'end_date_job' => $range['end_date'],
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $invoice = (float) ($row['invoice_total'] ?? 0);
            $scrap = (float) ($row['scrap_total'] ?? 0);
            $dump = (float) ($row['dump_total'] ?? 0);
            $expense = (float) ($row['expense_total'] ?? 0);
            $labor = (float) ($row['labor_total'] ?? 0);

            $row['revenue_total'] = $invoice + $scrap;
            $row['cost_total'] = $dump + $expense + $labor;
            $row['net_total'] = $row['revenue_total'] - $row['cost_total'];
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => (float) ($b['net_total'] ?? 0) <=> (float) ($a['net_total'] ?? 0));

        return $rows;
    }

    public static function disposalSpendVsScrapRevenue(string $startDate, string $endDate): array
    {
        $range = self::normalizeDateRange($startDate, $endDate);

        $sql = 'SELECT
                    dl.id,
                    dl.name,
                    dl.type,
                    COALESCE(SUM(CASE WHEN src.kind = "scrap" THEN src.amount ELSE 0 END), 0) AS scrap_revenue,
                    COALESCE(SUM(CASE WHEN src.kind = "dump" THEN src.amount ELSE 0 END), 0) AS dump_spend,
                    COALESCE(SUM(CASE WHEN src.kind = "expense" THEN src.amount ELSE 0 END), 0) AS expense_spend
                FROM disposal_locations dl
                LEFT JOIN (
                    SELECT d.disposal_location_id AS location_id,
                           COALESCE(d.amount, 0) AS amount,
                           CASE WHEN d.type = "scrap" THEN "scrap" ELSE "dump" END AS kind
                    FROM job_disposal_events d
                    WHERE d.deleted_at IS NULL
                      AND DATE(COALESCE(d.event_date, d.created_at)) BETWEEN :start_date_disposal AND :end_date_disposal

                    UNION ALL

                    SELECT e.disposal_location_id AS location_id,
                           COALESCE(e.amount, 0) AS amount,
                           "expense" AS kind
                    FROM expenses e
                    WHERE e.deleted_at IS NULL
                      AND COALESCE(e.is_active, 1) = 1
                      AND e.disposal_location_id IS NOT NULL
                      AND DATE(COALESCE(e.expense_date, e.created_at)) BETWEEN :start_date_expense AND :end_date_expense
                ) src ON src.location_id = dl.id
                WHERE dl.deleted_at IS NULL
                  AND COALESCE(dl.active, 1) = 1
                GROUP BY dl.id, dl.name, dl.type
                HAVING scrap_revenue <> 0 OR dump_spend <> 0 OR expense_spend <> 0
                ORDER BY (scrap_revenue - dump_spend - expense_spend) DESC, dl.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'start_date_disposal' => $range['start_date'],
            'end_date_disposal' => $range['end_date'],
            'start_date_expense' => $range['start_date'],
            'end_date_expense' => $range['end_date'],
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['net_total'] = (float) ($row['scrap_revenue'] ?? 0)
                - (float) ($row['dump_spend'] ?? 0)
                - (float) ($row['expense_spend'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    public static function employeeLaborCost(string $startDate, string $endDate): array
    {
        $range = self::normalizeDateRange($startDate, $endDate);

        $sql = 'SELECT
                    e.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", e.first_name, e.last_name)), ""), e.email, CONCAT("Employee #", e.id)) AS employee_name,
                    COALESCE(COUNT(t.id), 0) AS entry_count,
                    COALESCE(SUM(COALESCE(t.minutes_worked, 0)), 0) AS total_minutes,
                    COALESCE(SUM(COALESCE(t.total_paid, (COALESCE(t.pay_rate, 0) * COALESCE(t.minutes_worked, 0)) / 60)), 0) AS total_paid,
                    COALESCE(SUM(CASE WHEN t.job_id IS NULL OR t.job_id = 0 THEN COALESCE(t.minutes_worked, 0) ELSE 0 END), 0) AS non_job_minutes
                FROM employees e
                LEFT JOIN employee_time_entries t
                    ON t.employee_id = e.id
                   AND t.deleted_at IS NULL
                   AND COALESCE(t.active, 1) = 1
                   AND DATE(COALESCE(t.work_date, t.created_at)) BETWEEN :start_date AND :end_date
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                GROUP BY e.id, e.first_name, e.last_name, e.email
                HAVING entry_count > 0
                ORDER BY total_paid DESC, total_minutes DESC, employee_name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $minutes = (int) ($row['total_minutes'] ?? 0);
            $row['hourly_effective_rate'] = $minutes > 0
                ? round(((float) ($row['total_paid'] ?? 0) * 60) / $minutes, 2)
                : 0;
        }
        unset($row);

        return $rows;
    }

    public static function salesBySource(string $startDate, string $endDate): array
    {
        $range = self::normalizeDateRange($startDate, $endDate);

        $sql = 'SELECT
                    COALESCE(NULLIF(TRIM(s.type), ""), "other") AS source,
                    COUNT(*) AS sale_count,
                    COALESCE(SUM(COALESCE(s.gross_amount, 0)), 0) AS gross_total,
                    COALESCE(SUM(COALESCE(s.net_amount, COALESCE(s.gross_amount, 0))), 0) AS net_total
                FROM sales s
                WHERE s.deleted_at IS NULL
                  AND COALESCE(s.active, 1) = 1
                  AND DATE(COALESCE(s.end_date, s.start_date, s.created_at)) BETWEEN :start_date AND :end_date
                GROUP BY source
                ORDER BY gross_total DESC, source ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
        ]);

        return $stmt->fetchAll();
    }

    public static function totals(string $startDate, string $endDate): array
    {
        $jobs = self::jobProfitability($startDate, $endDate, 1000);
        $sales = self::salesBySource($startDate, $endDate);

        $jobsRevenue = 0.0;
        $jobsCost = 0.0;
        foreach ($jobs as $row) {
            $jobsRevenue += (float) ($row['revenue_total'] ?? 0);
            $jobsCost += (float) ($row['cost_total'] ?? 0);
        }

        $salesGross = 0.0;
        $salesNet = 0.0;
        foreach ($sales as $row) {
            $salesGross += (float) ($row['gross_total'] ?? 0);
            $salesNet += (float) ($row['net_total'] ?? 0);
        }

        return [
            'jobs_revenue' => $jobsRevenue,
            'jobs_cost' => $jobsCost,
            'jobs_net' => $jobsRevenue - $jobsCost,
            'sales_gross' => $salesGross,
            'sales_net' => $salesNet,
            'combined_gross' => $jobsRevenue + $salesGross,
            'combined_net' => ($jobsRevenue - $jobsCost) + $salesNet,
        ];
    }

    public static function presetsForUser(int $userId): array
    {
        self::ensureSchema();

        if ($userId <= 0) {
            return [];
        }

        $sql = 'SELECT id, user_id, name, report_key, start_date, end_date, filters_json, created_at, updated_at
                FROM report_presets
                WHERE user_id = :user_id
                ORDER BY name ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['filters_json'] ?? ''), true);
            $row['filters'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $rows;
    }

    public static function savePreset(int $userId, string $name, string $reportKey, string $startDate, string $endDate, array $filters = []): int
    {
        self::ensureSchema();

        if ($userId <= 0) {
            return 0;
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return 0;
        }

        $range = self::normalizeDateRange($startDate, $endDate);
        $filtersJson = json_encode($filters, JSON_UNESCAPED_SLASHES);
        if (!is_string($filtersJson) || $filtersJson === '') {
            $filtersJson = '{}';
        }

        $sql = 'INSERT INTO report_presets
                    (user_id, name, report_key, start_date, end_date, filters_json, created_at, updated_at)
                VALUES
                    (:user_id, :name, :report_key, :start_date, :end_date, :filters_json, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    report_key = VALUES(report_key),
                    start_date = VALUES(start_date),
                    end_date = VALUES(end_date),
                    filters_json = VALUES(filters_json),
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'name' => $normalizedName,
            'report_key' => trim($reportKey) !== '' ? trim($reportKey) : 'overview',
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'filters_json' => $filtersJson,
        ]);

        $select = Database::connection()->prepare(
            'SELECT id
             FROM report_presets
             WHERE user_id = :user_id
               AND name = :name
             LIMIT 1'
        );
        $select->execute([
            'user_id' => $userId,
            'name' => $normalizedName,
        ]);

        return (int) ($select->fetchColumn() ?: 0);
    }

    public static function deletePreset(int $presetId, int $userId): void
    {
        self::ensureSchema();

        if ($presetId <= 0 || $userId <= 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM report_presets
             WHERE id = :id
               AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $presetId,
            'user_id' => $userId,
        ]);
    }

    private static function ensureForeignKey(string $constraintName, string $sql, string $schema): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :schema
               AND CONSTRAINT_NAME = :constraint
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $schema,
            'constraint' => $constraintName,
        ]);

        if ($stmt->fetch()) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (\Throwable) {
            // Keep runtime stable when constraints cannot be applied on an existing environment.
        }
    }
}
