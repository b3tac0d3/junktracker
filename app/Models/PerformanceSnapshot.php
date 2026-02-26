<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use DateTimeImmutable;

final class PerformanceSnapshot
{
    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS performance_snapshots (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                user_id BIGINT UNSIGNED NULL,
                label VARCHAR(120) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                expense_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                summary_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_performance_snapshots_business_created (business_id, created_at),
                KEY idx_performance_snapshots_business_range (business_id, start_date, end_date),
                KEY idx_performance_snapshots_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        if (!Schema::hasColumn('performance_snapshots', 'business_id')) {
            try {
                Database::connection()->exec('ALTER TABLE performance_snapshots ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id');
            } catch (\Throwable) {
                // ignore drift
            }
        }

        if (!Schema::hasColumn('performance_snapshots', 'gross_total')) {
            try {
                Database::connection()->exec('ALTER TABLE performance_snapshots ADD COLUMN gross_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER end_date');
            } catch (\Throwable) {
                // ignore drift
            }
        }

        if (!Schema::hasColumn('performance_snapshots', 'expense_total')) {
            try {
                Database::connection()->exec('ALTER TABLE performance_snapshots ADD COLUMN expense_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER gross_total');
            } catch (\Throwable) {
                // ignore drift
            }
        }

        if (!Schema::hasColumn('performance_snapshots', 'net_total')) {
            try {
                Database::connection()->exec('ALTER TABLE performance_snapshots ADD COLUMN net_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER expense_total');
            } catch (\Throwable) {
                // ignore drift
            }
        }

        if (!Schema::hasColumn('performance_snapshots', 'summary_json')) {
            try {
                Database::connection()->exec('ALTER TABLE performance_snapshots ADD COLUMN summary_json LONGTEXT NOT NULL AFTER net_total');
            } catch (\Throwable) {
                // ignore drift
            }
        }

        try {
            Database::connection()->exec('CREATE INDEX idx_performance_snapshots_business_created ON performance_snapshots (business_id, created_at)');
        } catch (\Throwable) {
            // ignore drift
        }

        self::$schemaEnsured = true;
    }

    public static function resolveRange(?string $preset, ?string $startDate, ?string $endDate): array
    {
        $presetKey = strtolower(trim((string) ($preset ?? 'month')));

        $today = new DateTimeImmutable('today');

        $defaultStart = $today->format('Y-m-01');
        $defaultEnd = $today->format('Y-m-t');

        if ($presetKey === 'last_month') {
            $target = $today->modify('first day of last month');
            $defaultStart = $target->format('Y-m-01');
            $defaultEnd = $target->format('Y-m-t');
        } elseif ($presetKey === 'ytd') {
            $defaultStart = $today->format('Y-01-01');
            $defaultEnd = $today->format('Y-m-d');
        } elseif ($presetKey === 'last_30_days') {
            $defaultStart = $today->modify('-29 days')->format('Y-m-d');
            $defaultEnd = $today->format('Y-m-d');
        } elseif ($presetKey === 'quarter') {
            $month = (int) $today->format('n');
            $quarterStartMonth = ((int) floor(($month - 1) / 3) * 3) + 1;
            $quarterStart = $today->setDate((int) $today->format('Y'), $quarterStartMonth, 1);
            $defaultStart = $quarterStart->format('Y-m-d');
            $defaultEnd = $today->format('Y-m-d');
        } elseif ($presetKey === 'custom') {
            $defaultStart = trim((string) ($startDate ?? ''));
            $defaultEnd = trim((string) ($endDate ?? ''));
        }

        return ReportingHub::normalizeDateRange(
            trim((string) ($startDate ?? '')) !== '' ? $startDate : $defaultStart,
            trim((string) ($endDate ?? '')) !== '' ? $endDate : $defaultEnd
        );
    }

    public static function buildReport(string $startDate, string $endDate, int $jobLimit = 12, int $saleLimit = 12): array
    {
        $range = ReportingHub::normalizeDateRange($startDate, $endDate);
        $comparisonRanges = self::comparisonRanges($range['start_date'], $range['end_date']);

        $currentSummary = self::summaryForRange($range['start_date'], $range['end_date']);
        $previousSummary = self::summaryForRange($comparisonRanges['previous']['start_date'], $comparisonRanges['previous']['end_date']);
        $yearSummary = self::summaryForRange($comparisonRanges['year']['start_date'], $comparisonRanges['year']['end_date']);

        $expenseBreakdown = self::expenseBreakdown($range['start_date'], $range['end_date']);
        $jobs = self::jobsForRange($range['start_date'], $range['end_date'], $jobLimit);
        $sales = self::salesForRange($range['start_date'], $range['end_date'], $saleLimit);

        return [
            'range' => $range,
            'comparison_ranges' => $comparisonRanges,
            'summary' => $currentSummary,
            'comparison' => [
                'previous' => [
                    'summary' => $previousSummary,
                    'deltas' => self::buildDeltaMetrics($currentSummary, $previousSummary),
                ],
                'year' => [
                    'summary' => $yearSummary,
                    'deltas' => self::buildDeltaMetrics($currentSummary, $yearSummary),
                ],
            ],
            'expense_breakdown' => $expenseBreakdown,
            'jobs' => $jobs,
            'sales' => $sales,
            'charts' => [
                'comparison' => [
                    'labels' => ['Current', 'Previous Period', 'Same Period Last Year'],
                    'gross' => [
                        (float) ($currentSummary['total_gross'] ?? 0),
                        (float) ($previousSummary['total_gross'] ?? 0),
                        (float) ($yearSummary['total_gross'] ?? 0),
                    ],
                    'expenses' => [
                        (float) ($currentSummary['total_expenses'] ?? 0),
                        (float) ($previousSummary['total_expenses'] ?? 0),
                        (float) ($yearSummary['total_expenses'] ?? 0),
                    ],
                    'net' => [
                        (float) ($currentSummary['total_net'] ?? 0),
                        (float) ($previousSummary['total_net'] ?? 0),
                        (float) ($yearSummary['total_net'] ?? 0),
                    ],
                ],
                'expenses' => [
                    'labels' => array_map(static fn (array $row): string => (string) ($row['category'] ?? 'Uncategorized'), $expenseBreakdown),
                    'values' => array_map(static fn (array $row): float => (float) ($row['total_amount'] ?? 0), $expenseBreakdown),
                ],
            ],
        ];
    }

    public static function saveSnapshot(int $userId, string $label, string $startDate, string $endDate, array $report): int
    {
        self::ensureSchema();

        $businessId = self::currentBusinessId();
        if ($businessId <= 0) {
            return 0;
        }

        $range = ReportingHub::normalizeDateRange($startDate, $endDate);

        $normalizedLabel = trim($label);
        if ($normalizedLabel === '') {
            $normalizedLabel = 'Snapshot ' . $range['start_date'] . ' to ' . $range['end_date'];
        }
        $normalizedLabel = substr($normalizedLabel, 0, 120);

        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO performance_snapshots
                (business_id, user_id, label, start_date, end_date, gross_total, expense_total, net_total, summary_json, created_at, updated_at)
             VALUES
                (:business_id, :user_id, :label, :start_date, :end_date, :gross_total, :expense_total, :net_total, :summary_json, NOW(), NOW())'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId > 0 ? $userId : null,
            'label' => $normalizedLabel,
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'gross_total' => (float) ($summary['total_gross'] ?? 0),
            'expense_total' => (float) ($summary['total_expenses'] ?? 0),
            'net_total' => (float) ($summary['total_net'] ?? 0),
            'summary_json' => $encoded,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function snapshots(int $limit = 24): array
    {
        self::ensureSchema();

        $limit = max(1, min($limit, 100));
        $businessId = self::currentBusinessId();
        if ($businessId <= 0) {
            return [];
        }

        $sql = 'SELECT s.id,
                       s.label,
                       s.start_date,
                       s.end_date,
                       s.gross_total,
                       s.expense_total,
                       s.net_total,
                       s.created_at,
                       s.user_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), u.email, CONCAT("User #", u.id)) AS created_by_name
                FROM performance_snapshots s
                LEFT JOIN users u ON u.id = s.user_id
                WHERE s.business_id = :business_id
                ORDER BY s.created_at DESC, s.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['business_id' => $businessId]);

        return $stmt->fetchAll();
    }

    public static function findSnapshot(int $id): ?array
    {
        self::ensureSchema();

        if ($id <= 0) {
            return null;
        }

        $businessId = self::currentBusinessId();
        if ($businessId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, business_id, user_id, label, start_date, end_date, gross_total, expense_total, net_total, summary_json, created_at, updated_at
             FROM performance_snapshots
             WHERE id = :id
               AND business_id = :business_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'business_id' => $businessId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $decoded = json_decode((string) ($row['summary_json'] ?? ''), true);
        $row['payload'] = is_array($decoded) ? $decoded : [];

        return $row;
    }

    private static function summaryForRange(string $startDate, string $endDate): array
    {
        $summary = [
            'sales_count' => 0,
            'sales_gross' => 0.0,
            'sales_net' => 0.0,
            'job_count' => 0,
            'jobs_gross' => 0.0,
            'jobs_pending_count' => 0,
            'jobs_active_count' => 0,
            'jobs_completed_count' => 0,
            'expense_count' => 0,
            'expense_total' => 0.0,
            'scrap_revenue' => 0.0,
            'dump_fees' => 0.0,
            'payroll_total' => 0.0,
            'total_gross' => 0.0,
            'total_expenses' => 0.0,
            'total_net' => 0.0,
        ];

        if (Schema::tableExists('sales')) {
            [$scopeSql, $scopeParams] = self::scopeClause('sales', 's');
            $salesDateExpr = self::coalesceDateExpression('sales', 's', ['end_date', 'start_date', 'created_at']);
            $salesGrossExpr = Schema::hasColumn('sales', 'gross_amount') ? 'COALESCE(s.gross_amount, 0)' : '0';
            $salesNetExpr = Schema::hasColumn('sales', 'net_amount')
                ? 'COALESCE(s.net_amount, ' . $salesGrossExpr . ')'
                : $salesGrossExpr;

            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) AS sales_count,
                        COALESCE(SUM(' . $salesGrossExpr . '), 0) AS sales_gross,
                        COALESCE(SUM(' . $salesNetExpr . '), 0) AS sales_net
                 FROM sales s
                 WHERE 1 = 1'
                 . (Schema::hasColumn('sales', 'deleted_at') ? ' AND s.deleted_at IS NULL' : '')
                 . (Schema::hasColumn('sales', 'active') ? ' AND COALESCE(s.active, 1) = 1' : '')
                 . $scopeSql . '
                   AND ' . $salesDateExpr . ' BETWEEN :start_date AND :end_date'
            );
            $stmt->execute($scopeParams + [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $row = $stmt->fetch() ?: [];
            $summary['sales_count'] = (int) ($row['sales_count'] ?? 0);
            $summary['sales_gross'] = (float) ($row['sales_gross'] ?? 0);
            $summary['sales_net'] = (float) ($row['sales_net'] ?? 0);
        }

        if (Schema::tableExists('jobs')) {
            [$scopeSql, $scopeParams] = self::scopeClause('jobs', 'j');
            $jobDateExpr = self::coalesceDateExpression('jobs', 'j', [
                'paid_in_full_date',
                'paid_date',
                'billed_date',
                'scheduled_date',
                'start_date',
                'end_date',
                'updated_at',
                'created_at',
            ]);
            $jobsGrossExpr = Schema::hasColumn('jobs', 'total_billed')
                ? 'COALESCE(j.total_billed, 0)'
                : (Schema::hasColumn('jobs', 'total_quote') ? 'COALESCE(j.total_quote, 0)' : '0');

            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) AS job_count,
                        COALESCE(SUM(' . $jobsGrossExpr . '), 0) AS jobs_gross,
                        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(j.job_status, ""))) = "pending" THEN 1 ELSE 0 END), 0) AS jobs_pending_count,
                        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(j.job_status, ""))) = "active" THEN 1 ELSE 0 END), 0) AS jobs_active_count,
                        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(j.job_status, ""))) IN ("complete", "completed", "paid", "paid_in_full") THEN 1 ELSE 0 END), 0) AS jobs_completed_count
                 FROM jobs j
                 WHERE 1 = 1'
                 . (Schema::hasColumn('jobs', 'deleted_at') ? ' AND j.deleted_at IS NULL' : '')
                 . (Schema::hasColumn('jobs', 'active') ? ' AND COALESCE(j.active, 1) = 1' : '')
                 . $scopeSql . '
                   AND ' . $jobDateExpr . ' BETWEEN :start_date AND :end_date'
            );
            $stmt->execute($scopeParams + [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $row = $stmt->fetch() ?: [];
            $summary['job_count'] = (int) ($row['job_count'] ?? 0);
            $summary['jobs_gross'] = (float) ($row['jobs_gross'] ?? 0);
            $summary['jobs_pending_count'] = (int) ($row['jobs_pending_count'] ?? 0);
            $summary['jobs_active_count'] = (int) ($row['jobs_active_count'] ?? 0);
            $summary['jobs_completed_count'] = (int) ($row['jobs_completed_count'] ?? 0);
        }

        if (Schema::tableExists('expenses')) {
            [$scopeSql, $scopeParams] = self::scopeClause('expenses', 'e');
            $expenseDateExpr = self::coalesceDateExpression('expenses', 'e', ['expense_date', 'created_at']);

            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) AS expense_count,
                        COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS expense_total
                 FROM expenses e
                 WHERE 1 = 1'
                 . (Schema::hasColumn('expenses', 'deleted_at') ? ' AND e.deleted_at IS NULL' : '')
                 . (Schema::hasColumn('expenses', 'is_active') ? ' AND COALESCE(e.is_active, 1) = 1' : '')
                 . $scopeSql . '
                   AND ' . $expenseDateExpr . ' BETWEEN :start_date AND :end_date'
            );
            $stmt->execute($scopeParams + [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $row = $stmt->fetch() ?: [];
            $summary['expense_count'] = (int) ($row['expense_count'] ?? 0);
            $summary['expense_total'] = (float) ($row['expense_total'] ?? 0);
        }

        if (Schema::tableExists('job_disposal_events')) {
            [$scopeSql, $scopeParams] = self::scopeClause('job_disposal_events', 'd');
            $disposalDateExpr = self::coalesceDateExpression('job_disposal_events', 'd', ['event_date', 'created_at']);

            $stmt = Database::connection()->prepare(
                'SELECT COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(d.type, ""))) = "scrap" THEN COALESCE(d.amount, 0) ELSE 0 END), 0) AS scrap_revenue,
                        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(d.type, ""))) = "dump" THEN COALESCE(d.amount, 0) ELSE 0 END), 0) AS dump_fees
                 FROM job_disposal_events d
                 WHERE 1 = 1'
                 . (Schema::hasColumn('job_disposal_events', 'deleted_at') ? ' AND d.deleted_at IS NULL' : '')
                 . (Schema::hasColumn('job_disposal_events', 'active') ? ' AND COALESCE(d.active, 1) = 1' : '')
                 . $scopeSql . '
                   AND ' . $disposalDateExpr . ' BETWEEN :start_date AND :end_date'
            );
            $stmt->execute($scopeParams + [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $row = $stmt->fetch() ?: [];
            $summary['scrap_revenue'] = (float) ($row['scrap_revenue'] ?? 0);
            $summary['dump_fees'] = (float) ($row['dump_fees'] ?? 0);
        }

        if (Schema::tableExists('employee_time_entries')) {
            [$scopeSql, $scopeParams] = self::scopeClause('employee_time_entries', 't');
            $payrollDateExpr = self::coalesceDateExpression('employee_time_entries', 't', ['work_date', 'created_at']);
            $payExpr = Schema::hasColumn('employee_time_entries', 'total_paid')
                ? 'COALESCE(t.total_paid, (COALESCE(t.pay_rate, 0) * COALESCE(t.minutes_worked, 0)) / 60)'
                : '(COALESCE(t.pay_rate, 0) * COALESCE(t.minutes_worked, 0)) / 60';

            $stmt = Database::connection()->prepare(
                'SELECT COALESCE(SUM(' . $payExpr . '), 0) AS payroll_total
                 FROM employee_time_entries t
                 WHERE 1 = 1'
                 . (Schema::hasColumn('employee_time_entries', 'deleted_at') ? ' AND t.deleted_at IS NULL' : '')
                 . (Schema::hasColumn('employee_time_entries', 'active') ? ' AND COALESCE(t.active, 1) = 1' : '')
                 . $scopeSql . '
                   AND ' . $payrollDateExpr . ' BETWEEN :start_date AND :end_date'
            );
            $stmt->execute($scopeParams + [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $row = $stmt->fetch() ?: [];
            $summary['payroll_total'] = (float) ($row['payroll_total'] ?? 0);
        }

        $summary['total_gross'] = (float) $summary['sales_gross']
            + (float) $summary['jobs_gross']
            + (float) $summary['scrap_revenue'];
        $summary['total_expenses'] = (float) $summary['expense_total']
            + (float) $summary['dump_fees']
            + (float) $summary['payroll_total'];
        $summary['total_net'] = (float) $summary['total_gross'] - (float) $summary['total_expenses'];

        return $summary;
    }

    private static function expenseBreakdown(string $startDate, string $endDate): array
    {
        if (!Schema::tableExists('expenses')) {
            return [];
        }

        [$scopeSql, $scopeParams] = self::scopeClause('expenses', 'e');
        $expenseDateExpr = self::coalesceDateExpression('expenses', 'e', ['expense_date', 'created_at']);
        $categoryNameExpr = Schema::tableExists('expense_categories')
            ? 'COALESCE(NULLIF(TRIM(ec.name), ""), NULLIF(TRIM(e.category), ""), "Uncategorized")'
            : 'COALESCE(NULLIF(TRIM(e.category), ""), "Uncategorized")';
        $categoryJoin = Schema::tableExists('expense_categories')
            ? 'LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id'
            : '';
        $groupByExpr = Schema::tableExists('expense_categories')
            ? 'GROUP BY ec.name, e.category'
            : 'GROUP BY e.category';

        $stmt = Database::connection()->prepare(
            'SELECT ' . $categoryNameExpr . ' AS category,
                    COUNT(*) AS expense_count,
                    COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS total_amount
             FROM expenses e
             ' . $categoryJoin . '
             WHERE 1 = 1'
             . (Schema::hasColumn('expenses', 'deleted_at') ? ' AND e.deleted_at IS NULL' : '')
             . (Schema::hasColumn('expenses', 'is_active') ? ' AND COALESCE(e.is_active, 1) = 1' : '')
             . $scopeSql . '
               AND ' . $expenseDateExpr . ' BETWEEN :start_date AND :end_date
             ' . $groupByExpr . '
             ORDER BY total_amount DESC, category ASC
             LIMIT 12'
        );
        $stmt->execute($scopeParams + [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }

    private static function jobsForRange(string $startDate, string $endDate, int $limit): array
    {
        if (!Schema::tableExists('jobs')) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        [$scopeSql, $scopeParams] = self::scopeClause('jobs', 'j');
        $jobDateExpr = self::coalesceDateExpression('jobs', 'j', [
            'paid_in_full_date',
            'paid_date',
            'billed_date',
            'scheduled_date',
            'start_date',
            'end_date',
            'updated_at',
            'created_at',
        ]);

        $stmt = Database::connection()->prepare(
            'SELECT j.id,
                    j.name,
                    j.job_status,
                    ' . $jobDateExpr . ' AS job_date,
                    ' . (Schema::hasColumn('jobs', 'total_billed') ? 'COALESCE(j.total_billed, 0)' : '0') . ' AS total_billed,
                    COALESCE(
                        NULLIF(c.business_name, ""),
                        NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""),
                        CONCAT("Client #", c.id)
                    ) AS client_name
             FROM jobs j
             LEFT JOIN clients c ON c.id = j.client_id
             WHERE 1 = 1'
             . (Schema::hasColumn('jobs', 'deleted_at') ? ' AND j.deleted_at IS NULL' : '')
             . (Schema::hasColumn('jobs', 'active') ? ' AND COALESCE(j.active, 1) = 1' : '')
             . $scopeSql . '
               AND ' . $jobDateExpr . ' BETWEEN :start_date AND :end_date
             ORDER BY job_date DESC, j.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute($scopeParams + [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }

    private static function salesForRange(string $startDate, string $endDate, int $limit): array
    {
        if (!Schema::tableExists('sales')) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        [$scopeSql, $scopeParams] = self::scopeClause('sales', 's');
        $salesDateExpr = self::coalesceDateExpression('sales', 's', ['end_date', 'start_date', 'created_at']);

        $stmt = Database::connection()->prepare(
            'SELECT s.id,
                    COALESCE(NULLIF(TRIM(s.name), ""), CONCAT("Sale #", s.id)) AS name,
                    COALESCE(NULLIF(TRIM(s.type), ""), "other") AS type,
                    ' . $salesDateExpr . ' AS sale_date,
                    ' . (Schema::hasColumn('sales', 'gross_amount') ? 'COALESCE(s.gross_amount, 0)' : '0') . ' AS gross_amount,
                    ' . (Schema::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, COALESCE(s.gross_amount, 0))' : (Schema::hasColumn('sales', 'gross_amount') ? 'COALESCE(s.gross_amount, 0)' : '0')) . ' AS net_amount,
                    COALESCE(NULLIF(j.name, ""), CONCAT("Job #", j.id)) AS job_name,
                    j.id AS job_id
             FROM sales s
             LEFT JOIN jobs j ON j.id = s.job_id
             WHERE 1 = 1'
             . (Schema::hasColumn('sales', 'deleted_at') ? ' AND s.deleted_at IS NULL' : '')
             . (Schema::hasColumn('sales', 'active') ? ' AND COALESCE(s.active, 1) = 1' : '')
             . $scopeSql . '
               AND ' . $salesDateExpr . ' BETWEEN :start_date AND :end_date
             ORDER BY sale_date DESC, s.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute($scopeParams + [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll();
    }

    private static function comparisonRanges(string $startDate, string $endDate): array
    {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);

        $daySpan = ((int) $start->diff($end)->days) + 1;

        $previousEnd = $start->modify('-1 day');
        $previousStart = $previousEnd->modify('-' . max(0, $daySpan - 1) . ' days');

        $yearStart = $start->modify('-1 year');
        $yearEnd = $end->modify('-1 year');

        return [
            'current' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ],
            'previous' => [
                'start_date' => $previousStart->format('Y-m-d'),
                'end_date' => $previousEnd->format('Y-m-d'),
            ],
            'year' => [
                'start_date' => $yearStart->format('Y-m-d'),
                'end_date' => $yearEnd->format('Y-m-d'),
            ],
        ];
    }

    private static function buildDeltaMetrics(array $currentSummary, array $baselineSummary): array
    {
        $keys = [
            'sales_gross',
            'sales_net',
            'jobs_gross',
            'expense_total',
            'payroll_total',
            'total_gross',
            'total_expenses',
            'total_net',
        ];

        $deltas = [];
        foreach ($keys as $key) {
            $currentValue = (float) ($currentSummary[$key] ?? 0);
            $baselineValue = (float) ($baselineSummary[$key] ?? 0);
            $difference = $currentValue - $baselineValue;

            $percent = null;
            if (abs($baselineValue) > 0.0001) {
                $percent = ($difference / $baselineValue) * 100;
            }

            $deltas[$key] = [
                'difference' => $difference,
                'percent' => $percent,
            ];
        }

        return $deltas;
    }

    private static function coalesceDateExpression(string $table, string $alias, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $parts[] = $alias . '.' . $column;
            }
        }

        if (empty($parts)) {
            return 'DATE(CURDATE())';
        }

        return 'DATE(COALESCE(' . implode(', ', $parts) . '))';
    }

    private static function scopeClause(string $table, string $alias): array
    {
        if (!Schema::hasColumn($table, 'business_id')) {
            return ['', []];
        }

        $businessId = self::currentBusinessId();
        if ($businessId <= 0) {
            return [' AND 1 = 0', []];
        }

        return [' AND ' . $alias . '.business_id = :business_id', ['business_id' => $businessId]];
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
