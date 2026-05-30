<?php

declare(strict_types=1);

namespace App\Models;

use Core\AppCache;
use Core\Database;

final class DashboardSummary
{
    public static function byBusiness(int $businessId, int $ownerUserId): array
    {
        $cacheKey = 'dash:' . $businessId . ':' . $ownerUserId . ':' . AppCache::versionSuffix();
        if (AppCache::enabled()) {
            $cached = AppCache::get($cacheKey);
            if (is_array($cached)
                && isset($cached['sales'], $cached['estate_sales'], $cached['service'], $cached['lists'])
                && array_key_exists('upcoming_schedule', $cached['lists'])
                && array_key_exists('past_due_schedule', $cached['lists'])) {
                return $cached;
            }
        }

        $ytdFrom = date('Y-01-01');
        $ytdTo = date('Y-m-d');
        $mtdFrom = date('Y-m-01');
        $mtdTo = date('Y-m-d');
        $mtdOverall = ReportSummary::overallNetForRange($businessId, $mtdFrom, $mtdTo);
        $ytdOverall = ReportSummary::overallNetForRange($businessId, $ytdFrom, $ytdTo);

        $payload = [
            'sales' => self::salesSummary($businessId),
            'estate_sales' => self::estateSalesSummary($businessId),
            'service' => self::serviceSummary($businessId),
            'receivables' => self::receivablesSummary($businessId),
            'expenses' => self::expensesSummary($businessId),
            'purchases' => self::purchasesSummary($businessId),
            'mtd_overall_net' => (float) ($mtdOverall['net'] ?? 0),
            'ytd_overall_net' => (float) ($ytdOverall['net'] ?? 0),
            'mtd_net_minus_purchases' => (float) ($mtdOverall['net_minus_purchases'] ?? 0),
            'ytd_net_minus_purchases' => (float) ($ytdOverall['net_minus_purchases'] ?? 0),
            'jobs' => self::jobsSummary($businessId),
            'tasks' => self::tasksSummary($businessId, $ownerUserId),
            'three_month_chart' => self::lastThreeMonthsChart($businessId),
            'lists' => [
                'my_tasks_due' => Task::dueListForOwner($businessId, $ownerUserId),
                'past_due_schedule' => self::pastDueSchedule($businessId),
                'upcoming_schedule' => self::upcomingSchedule($businessId),
            ],
        ];
        if (AppCache::enabled()) {
            AppCache::set($cacheKey, $payload);
        }

        return $payload;
    }

    private static function expensesSummary(int $businessId): array
    {
        return [
            'mtd_total' => self::expensesTotalBetween($businessId, date('Y-m-01'), date('Y-m-d'), null),
            'ytd_total' => self::expensesTotalBetween($businessId, date('Y-01-01'), date('Y-m-d'), null),
        ];
    }

    private static function salesSummary(int $businessId): array
    {
        return self::normalizeSaleSummary(Sale::summary($businessId, Sale::ESTATE_SCOPE_GENERAL));
    }

    private static function estateSalesSummary(int $businessId): array
    {
        return EstateSale::dashboardSummary($businessId);
    }

    /**
     * Dashboard KPI cards expect mtd_gross/ytd_gross keys; Sale::summary returns gross_mtd/gross_ytd.
     *
     * @param array<string, mixed> $summary
     * @return array<string, float|int>
     */
    private static function normalizeSaleSummary(array $summary): array
    {
        return [
            'count' => (int) ($summary['count'] ?? 0),
            'mtd_count' => (int) ($summary['mtd_count'] ?? 0),
            'ytd_count' => (int) ($summary['ytd_count'] ?? 0),
            'mtd_gross' => (float) ($summary['gross_mtd'] ?? $summary['mtd_gross'] ?? 0),
            'mtd_net' => (float) ($summary['net_mtd'] ?? $summary['mtd_net'] ?? 0),
            'ytd_gross' => (float) ($summary['gross_ytd'] ?? $summary['ytd_gross'] ?? 0),
            'ytd_net' => (float) ($summary['net_ytd'] ?? $summary['ytd_net'] ?? 0),
        ];
    }

    private static function serviceSummary(int $businessId): array
    {
        $mtdFrom = date('Y-m-01');
        $mtdTo = date('Y-m-d');
        $ytdFrom = date('Y-01-01');
        $ytdTo = date('Y-m-d');
        $mtd = ReportSummary::servicePaymentsTotalsForRange($businessId, $mtdFrom, $mtdTo);
        $ytd = ReportSummary::servicePaymentsTotalsForRange($businessId, $ytdFrom, $ytdTo);

        return [
            'mtd_gross' => (float) ($mtd['gross'] ?? 0),
            'mtd_net' => (float) ($mtd['net'] ?? 0),
            'mtd_count' => (int) ($mtd['count'] ?? 0),
            'ytd_gross' => (float) ($ytd['gross'] ?? 0),
            'ytd_net' => (float) ($ytd['net'] ?? 0),
            'ytd_count' => (int) ($ytd['count'] ?? 0),
        ];
    }

    private static function receivablesSummary(int $businessId): array
    {
        $summary = [
            'payments_due' => 0.0,
            'past_due' => 0.0,
            'open_invoices' => 0,
        ];

        if (!SchemaInspector::hasTable('invoices')) {
            return $summary;
        }

        $invoiceTotalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $invoiceBusinessWhere = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $invoiceDeletedWhere = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';

        $typeWhere = '1=1';
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $typeWhere = "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) = 'invoice')";
        }

        $statusWhere = '1=1';
        if (SchemaInspector::hasColumn('invoices', 'status')) {
            $statusWhere = "LOWER(COALESCE(i.status, '')) NOT IN ('paid','paid_in_full','cancelled','declined','closed','write_off')";
        }

        $hasPaymentsTable = SchemaInspector::hasTable('payments');
        $hasPaymentsInvoiceId = SchemaInspector::hasColumn('payments', 'invoice_id');
        $paymentsJoin = '';
        $paymentsWhere = [];
        if ($hasPaymentsTable && $hasPaymentsInvoiceId) {
            $paymentsJoin = 'LEFT JOIN (
                SELECT p.invoice_id, COALESCE(SUM(p.amount), 0) AS paid_total
                FROM payments p';
            $paymentsWhere[] = 'p.invoice_id IS NOT NULL';
            if (SchemaInspector::hasColumn('payments', 'business_id')) {
                $paymentsWhere[] = 'p.business_id = :payments_business_id';
            }
            if (SchemaInspector::hasColumn('payments', 'deleted_at')) {
                $paymentsWhere[] = 'p.deleted_at IS NULL';
            }
            if ($paymentsWhere !== []) {
                $paymentsJoin .= ' WHERE ' . implode(' AND ', $paymentsWhere);
            }
            $paymentsJoin .= ' GROUP BY p.invoice_id
            ) pmt ON pmt.invoice_id = i.id';
        } else {
            $paymentsJoin = 'LEFT JOIN (SELECT NULL AS invoice_id, 0 AS paid_total) pmt ON 1=0';
        }

        $balanceSql = "GREATEST({$invoiceTotalSql} - COALESCE(pmt.paid_total, 0), 0)";
        $openBalanceSql = "CASE WHEN {$balanceSql} > 0.009 THEN {$balanceSql} ELSE 0 END";
        $isPastDueSql = SchemaInspector::hasColumn('invoices', 'due_date')
            ? '(i.due_date IS NOT NULL AND DATE(i.due_date) < CURDATE())'
            : '0';

        $sql = "SELECT
                    COALESCE(SUM({$openBalanceSql}), 0) AS payments_due,
                    COALESCE(SUM(CASE WHEN {$isPastDueSql} AND {$balanceSql} > 0.009 THEN {$balanceSql} ELSE 0 END), 0) AS past_due,
                    COALESCE(SUM(CASE WHEN {$balanceSql} > 0.009 THEN 1 ELSE 0 END), 0) AS open_invoices
                FROM invoices i
                {$paymentsJoin}
                WHERE {$invoiceBusinessWhere}
                  AND {$invoiceDeletedWhere}
                  AND {$typeWhere}
                  AND {$statusWhere}";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($hasPaymentsTable && $hasPaymentsInvoiceId && SchemaInspector::hasColumn('payments', 'business_id')) {
            $stmt->bindValue(':payments_business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'payments_due' => (float) ($row['payments_due'] ?? 0),
            'past_due' => (float) ($row['past_due'] ?? 0),
            'open_invoices' => (int) ($row['open_invoices'] ?? 0),
        ];
    }

    private static function purchasesSummary(int $businessId): array
    {
        $summary = [
            'mtd_total' => 0.0,
            'mtd_count' => 0,
            'ytd_total' => 0.0,
            'ytd_count' => 0,
        ];

        if (!SchemaInspector::hasTable('purchases')) {
            return $summary;
        }

        $amountSql = SchemaInspector::hasColumn('purchases', 'purchase_price') ? 'COALESCE(p.purchase_price, 0)' : '0';
        $dateSql = SchemaInspector::hasColumn('purchases', 'purchase_date') ? 'DATE(p.purchase_date)' : 'DATE(p.created_at)';

        $where = [
            SchemaInspector::hasColumn('purchases', 'business_id') ? 'p.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('purchases', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1',
        ];

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$dateSql} <= CURDATE() THEN {$amountSql} ELSE 0 END), 0) AS mtd_total,
                    COALESCE(SUM(CASE WHEN {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$dateSql} <= CURDATE() THEN 1 ELSE 0 END), 0) AS mtd_count,
                    COALESCE(SUM(CASE WHEN {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-01-01') AND {$dateSql} <= CURDATE() THEN {$amountSql} ELSE 0 END), 0) AS ytd_total,
                    COALESCE(SUM(CASE WHEN {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-01-01') AND {$dateSql} <= CURDATE() THEN 1 ELSE 0 END), 0) AS ytd_count
                FROM purchases p
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('purchases', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'mtd_total' => (float) ($row['mtd_total'] ?? 0),
            'mtd_count' => (int) ($row['mtd_count'] ?? 0),
            'ytd_total' => (float) ($row['ytd_total'] ?? 0),
            'ytd_count' => (int) ($row['ytd_count'] ?? 0),
        ];
    }

    private static function expensesTotalBetween(int $businessId, string $fromDate, string $toDate, ?bool $jobOnly): float
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return 0.0;
        }

        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'COALESCE(e.amount, 0)' : '0';
        $dateSql = SchemaInspector::hasColumn('expenses', 'expense_date') ? 'DATE(e.expense_date)' : 'DATE(e.created_at)';
        $where = [
            SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1',
            "{$dateSql} BETWEEN :from_date AND :to_date",
        ];

        if (SchemaInspector::hasColumn('expenses', 'job_id')) {
            if ($jobOnly === true) {
                $where[] = 'e.job_id IS NOT NULL';
            } elseif ($jobOnly === false) {
                $where[] = 'e.job_id IS NULL';
            }
        } elseif ($jobOnly === true) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM({$amountSql}), 0) AS total_amount
                FROM expenses e
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':from_date', $fromDate, \PDO::PARAM_STR);
        $stmt->bindValue(':to_date', $toDate, \PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch();
        return (float) ($row['total_amount'] ?? 0);
    }

    private static function jobsSummary(int $businessId): array
    {
        $summary = [
            'dispatch' => 0,
            'prospect' => 0,
            'active' => 0,
        ];

        if (!SchemaInspector::hasTable('jobs')) {
            return $summary;
        }

        $where = [
            SchemaInspector::hasColumn('jobs', 'business_id') ? 'business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'deleted_at IS NULL' : '1=1',
        ];

        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'LOWER(status)' : "'pending'";
        $sql = "SELECT
                    SUM(CASE WHEN {$statusSql} IN ('pending','active') THEN 1 ELSE 0 END) AS dispatch_count,
                    SUM(CASE WHEN {$statusSql} = 'prospect' THEN 1 ELSE 0 END) AS prospect_count,
                    SUM(CASE WHEN {$statusSql} = 'active' THEN 1 ELSE 0 END) AS active_count
                FROM jobs
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'dispatch' => (int) ($row['dispatch_count'] ?? 0),
            'prospect' => (int) ($row['prospect_count'] ?? 0),
            'active' => (int) ($row['active_count'] ?? 0),
        ];
    }

    private static function tasksSummary(int $businessId, int $ownerUserId): array
    {
        $summary = [
            'mine_open' => 0,
            'mine_due_today' => 0,
            'mine_overdue' => 0,
        ];

        if (!SchemaInspector::hasTable('tasks') || !SchemaInspector::hasColumn('tasks', 'owner_user_id')) {
            return $summary;
        }

        $where = [
            SchemaInspector::hasColumn('tasks', 'business_id') ? 'business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('tasks', 'deleted_at') ? 'deleted_at IS NULL' : '1=1',
            'owner_user_id = :owner_user_id',
        ];

        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 'LOWER(status)' : "'open'";
        $dueSql = SchemaInspector::hasColumn('tasks', 'due_at') ? 'DATE(due_at)' : 'NULL';

        $sql = "SELECT
                    SUM(CASE WHEN {$statusSql} IN ('open','in_progress') THEN 1 ELSE 0 END) AS mine_open,
                    SUM(CASE WHEN {$statusSql} IN ('open','in_progress') AND {$dueSql} = CURDATE() THEN 1 ELSE 0 END) AS mine_due_today,
                    SUM(CASE WHEN {$statusSql} IN ('open','in_progress') AND {$dueSql} < CURDATE() THEN 1 ELSE 0 END) AS mine_overdue
                FROM tasks
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':owner_user_id', $ownerUserId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $summary;
        }

        return [
            'mine_open' => (int) ($row['mine_open'] ?? 0),
            'mine_due_today' => (int) ($row['mine_due_today'] ?? 0),
            'mine_overdue' => (int) ($row['mine_overdue'] ?? 0),
        ];
    }

    private static function dispatchJobs(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title') ? 'j.title' : "CONCAT('Job #', j.id)";
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $scheduledSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at') ? 'j.scheduled_start_at' : 'NULL';

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = j.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')) {
                $joinSql .= ' AND c.business_id = j.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [
            SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1',
            "LOWER({$statusSql}) IN ('pending','active')",
        ];

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    LOWER({$statusSql}) AS status,
                    {$scheduledSql} AS scheduled_start_at,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    CASE WHEN {$scheduledSql} IS NULL THEN 1 ELSE 0 END,
                    {$scheduledSql} ASC,
                    j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function upcomingDeliveries(int $businessId, int $limit = 12): array
    {
        if (!SchemaInspector::hasTable('client_deliveries')) {
            return [];
        }

        $windowEnd = date('Y-m-d H:i:s', strtotime('+14 days 23:59:59'));

        $addr2Sql = SchemaInspector::hasColumn('client_deliveries', 'address_line2')
            ? 'd.address_line2'
            : "''";

        $sql = 'SELECT
                    d.id,
                    d.scheduled_at,
                    d.end_at,
                    d.status,
                    d.address_line1,
                    ' . $addr2Sql . ' AS address_line2,
                    d.city,
                    d.state,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM client_deliveries d
                INNER JOIN clients c ON c.id = d.client_id
                    AND c.business_id = d.business_id
                    AND c.deleted_at IS NULL
                WHERE d.business_id = :business_id
                  AND d.deleted_at IS NULL
                  AND LOWER(d.status) = :delivery_status
                  AND d.scheduled_at >= NOW()
                  AND d.scheduled_at <= :window_end
                ORDER BY d.scheduled_at ASC, d.id ASC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':delivery_status', 'scheduled');
        $stmt->bindValue(':window_end', $windowEnd);
        $stmt->bindValue(':row_limit', max(1, min($limit, 30)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private static function prospectJobs(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title') ? 'j.title' : "CONCAT('Job #', j.id)";
        $scheduledSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at') ? 'j.scheduled_start_at' : 'NULL';
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'prospect'";

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = j.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')) {
                $joinSql .= ' AND c.business_id = j.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [
            SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1',
            "LOWER({$statusSql}) = 'prospect'",
        ];

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$scheduledSql} AS scheduled_start_at,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function outstandingPurchaseQuotes(int $businessId, int $limit = 6): array
    {
        if (!SchemaInspector::hasTable('purchase_quotes') || !SchemaInspector::hasTable('clients')) {
            return [];
        }

        $sql = 'SELECT
                    pq.id,
                    pq.title,
                    LOWER(COALESCE(pq.status, "new")) AS status,
                    pq.next_follow_up_at AS due_date,
                    pq.contact_date,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM purchase_quotes pq
                INNER JOIN clients c ON c.id = pq.client_id
                    AND c.business_id = pq.business_id
                    AND c.deleted_at IS NULL
                WHERE pq.business_id = :business_id
                  AND pq.deleted_at IS NULL
                  AND LOWER(COALESCE(pq.status, "new")) IN ("new", "sent", "follow_up")
                ORDER BY
                    CASE WHEN pq.next_follow_up_at IS NULL THEN 1 ELSE 0 END,
                    pq.next_follow_up_at ASC,
                    pq.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{date: string, label: string, is_past: bool, is_today: bool, items: list<array{title: string, start: string, url: string, event_type: string, customer_name: string, all_day: bool, color: string}>}>
     */
    private static function pastDueSchedule(int $businessId, int $lookbackDays = 60): array
    {
        $nowTs = time();
        $start = date('Y-m-d 00:00:00', strtotime('-' . max(1, $lookbackDays) . ' days'));
        $end = date('Y-m-d 23:59:59');
        $events = EventFeed::range($businessId, $start, $end, []);

        $days = self::buildDashboardAgendaDays(
            $events,
            $nowTs,
            static fn (array $event): bool => self::isPastDueDashboardAgendaEvent($event, $nowTs),
            true
        );

        if (\can_view_financials()) {
            $days = self::mergeDashboardAgendaItems(
                $days,
                self::pastDueInvoiceAgendaItems($businessId, $lookbackDays),
                $nowTs,
                true
            );
        }

        return $days;
    }

    /**
     * @return list<array{title: string, start: string, url: string, event_type: string, customer_name: string, all_day: bool, color: string, time_label?: string}>
     */
    private static function pastDueInvoiceAgendaItems(int $businessId, int $lookbackDays = 60): array
    {
        if (!SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'due_date')) {
            return [];
        }

        $lookbackStart = date('Y-m-d', strtotime('-' . max(1, $lookbackDays) . ' days'));
        $invoiceTotalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CAST(i.id AS CHAR)";
        $invoiceBusinessWhere = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $invoiceDeletedWhere = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';

        $typeWhere = '1=1';
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $typeWhere = "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) = 'invoice')";
        }

        $statusWhere = '1=1';
        if (SchemaInspector::hasColumn('invoices', 'status')) {
            $statusWhere = "LOWER(COALESCE(i.status, '')) NOT IN ('paid','paid_in_full','cancelled','declined','closed','write_off')";
        }

        $clientJoin = '';
        $clientNameSql = "''";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $clientJoinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $clientJoin = "LEFT JOIN clients c ON c.id = i.client_id {$clientJoinDeleted}";
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), '')";
        }

        $hasPaymentsTable = SchemaInspector::hasTable('payments');
        $hasPaymentsInvoiceId = SchemaInspector::hasColumn('payments', 'invoice_id');
        $paymentsJoin = '';
        $paymentsWhere = [];
        if ($hasPaymentsTable && $hasPaymentsInvoiceId) {
            $paymentsJoin = 'LEFT JOIN (
                SELECT p.invoice_id, COALESCE(SUM(p.amount), 0) AS paid_total
                FROM payments p';
            $paymentsWhere[] = 'p.invoice_id IS NOT NULL';
            if (SchemaInspector::hasColumn('payments', 'business_id')) {
                $paymentsWhere[] = 'p.business_id = :payments_business_id';
            }
            if (SchemaInspector::hasColumn('payments', 'deleted_at')) {
                $paymentsWhere[] = 'p.deleted_at IS NULL';
            }
            if ($paymentsWhere !== []) {
                $paymentsJoin .= ' WHERE ' . implode(' AND ', $paymentsWhere);
            }
            $paymentsJoin .= ' GROUP BY p.invoice_id
            ) pmt ON pmt.invoice_id = i.id';
        } else {
            $paymentsJoin = 'LEFT JOIN (SELECT NULL AS invoice_id, 0 AS paid_total) pmt ON 1=0';
        }

        $balanceSql = "GREATEST({$invoiceTotalSql} - COALESCE(pmt.paid_total, 0), 0)";

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    DATE(i.due_date) AS due_date,
                    {$balanceSql} AS balance_due,
                    {$clientNameSql} AS client_name
                FROM invoices i
                {$clientJoin}
                {$paymentsJoin}
                WHERE {$invoiceBusinessWhere}
                  AND {$invoiceDeletedWhere}
                  AND {$typeWhere}
                  AND {$statusWhere}
                  AND i.due_date IS NOT NULL
                  AND DATE(i.due_date) >= :lookback_start
                  AND DATE(i.due_date) < CURDATE()
                  AND {$balanceSql} > 0.009
                ORDER BY i.due_date ASC, i.id ASC
                LIMIT 100";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($hasPaymentsTable && $hasPaymentsInvoiceId && SchemaInspector::hasColumn('payments', 'business_id')) {
            $stmt->bindValue(':payments_business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':lookback_start', $lookbackStart);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $dueRaw = trim((string) ($row['due_date'] ?? ''));
            $dueDateKey = self::dashboardAgendaDateKey($dueRaw);
            if ($dueDateKey === null) {
                continue;
            }

            $invoiceNo = trim((string) ($row['invoice_number'] ?? ''));
            if ($invoiceNo === '') {
                $invoiceNo = '#' . (string) $id;
            }
            $balanceDue = (float) ($row['balance_due'] ?? 0);
            $clientName = trim((string) ($row['client_name'] ?? ''));
            $metaClient = $clientName !== '' ? $clientName : 'Open balance';

            $items[] = [
                'title' => 'Invoice ' . $invoiceNo,
                'start' => $dueDateKey . 'T12:00:00',
                'agenda_date' => $dueDateKey,
                'url' => url('/billing/' . (string) $id),
                'event_type' => 'Invoice',
                'customer_name' => $metaClient . ' · $' . number_format($balanceDue, 2) . ' due',
                'all_day' => true,
                'color' => '#ca8a04',
                'time_label' => \format_date($dueDateKey),
            ];
        }

        return $items;
    }

    /**
     * @param list<array{date: string, label: string, is_past: bool, is_today: bool, items: list<array<string, mixed>>}> $dayGroups
     * @param list<array<string, mixed>> $items
     * @return list<array{date: string, label: string, is_past: bool, is_today: bool, items: list<array<string, mixed>>}>
     */
    private static function mergeDashboardAgendaItems(array $dayGroups, array $items, int $nowTs, bool $pastDueMode): array
    {
        if ($items === []) {
            return $dayGroups;
        }

        $today = date('Y-m-d', $nowTs);
        $tomorrow = date('Y-m-d', strtotime('+1 day', $nowTs));
        $yesterday = date('Y-m-d', strtotime('-1 day', $nowTs));
        $byDate = [];

        foreach ($dayGroups as $dayGroup) {
            if (!is_array($dayGroup)) {
                continue;
            }
            $dateKey = trim((string) ($dayGroup['date'] ?? ''));
            if ($dateKey === '') {
                continue;
            }
            $byDate[$dateKey] = is_array($dayGroup['items'] ?? null) ? $dayGroup['items'] : [];
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dateKey = trim((string) ($item['agenda_date'] ?? ''));
            if ($dateKey === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
                $startTs = strtotime((string) ($item['start'] ?? ''));
                if ($startTs === false) {
                    continue;
                }
                $dateKey = date('Y-m-d', $startTs);
            }
            $byDate[$dateKey][] = $item;
        }

        ksort($byDate);

        $days = [];
        foreach ($byDate as $dateKey => $dayItems) {
            if ($dayItems === []) {
                continue;
            }
            usort($dayItems, static function (array $a, array $b): int {
                $cmp = strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            $days[] = [
                'date' => $dateKey,
                'label' => self::dashboardAgendaDayLabel($dateKey, $today, $tomorrow, $yesterday, $pastDueMode),
                'is_past' => $dateKey < $today || ($pastDueMode && $dateKey === $today),
                'is_today' => $dateKey === $today,
                'items' => $dayItems,
            ];
        }

        return $days;
    }

    /**
     * @return list<array{date: string, label: string, is_past: bool, is_today: bool, items: list<array{title: string, start: string, url: string, event_type: string, customer_name: string, all_day: bool, color: string}>}>
     */
    private static function upcomingSchedule(int $businessId, int $lookaheadDays = 21): array
    {
        $nowTs = time();
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59', strtotime('+' . max(1, $lookaheadDays) . ' days'));
        $events = EventFeed::range($businessId, $start, $end, []);

        return self::buildDashboardAgendaDays(
            $events,
            $nowTs,
            static fn (array $event): bool => self::isOpenDashboardAgendaEvent($event, $nowTs),
            false
        );
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param callable(array<string, mixed>): bool $includeEvent
     * @return list<array{date: string, label: string, is_past: bool, is_today: bool, items: list<array{title: string, start: string, url: string, event_type: string, customer_name: string, all_day: bool, color: string}>}>
     */
    private static function buildDashboardAgendaDays(array $events, int $nowTs, callable $includeEvent, bool $pastDueMode): array
    {
        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
        });

        $today = date('Y-m-d', $nowTs);
        $tomorrow = date('Y-m-d', strtotime('+1 day', $nowTs));
        $yesterday = date('Y-m-d', strtotime('-1 day', $nowTs));
        $byDate = [];

        foreach ($events as $event) {
            if (!is_array($event) || !$includeEvent($event)) {
                continue;
            }
            $item = self::dashboardAgendaItemFromEvent($event);
            if ($item === null) {
                continue;
            }
            $startTs = strtotime((string) $item['start']);
            if ($startTs === false) {
                continue;
            }
            $dateKey = date('Y-m-d', $startTs);
            $byDate[$dateKey][] = $item;
        }

        ksort($byDate);

        $days = [];
        foreach ($byDate as $dateKey => $items) {
            if ($items === []) {
                continue;
            }
            $days[] = [
                'date' => $dateKey,
                'label' => self::dashboardAgendaDayLabel($dateKey, $today, $tomorrow, $yesterday, $pastDueMode),
                'is_past' => $dateKey < $today || ($pastDueMode && $dateKey === $today),
                'is_today' => $dateKey === $today,
                'items' => $items,
            ];
        }

        return $days;
    }

    /**
     * @param array<string, mixed> $event
     * @return array{title: string, start: string, url: string, event_type: string, customer_name: string, all_day: bool, color: string}|null
     */
    private static function dashboardAgendaItemFromEvent(array $event): ?array
    {
        $startRaw = trim((string) ($event['start'] ?? ''));
        if ($startRaw === '' || strtotime($startRaw) === false) {
            return null;
        }

        $props = is_array($event['extendedProps'] ?? null) ? $event['extendedProps'] : [];
        $eventType = trim((string) ($props['eventType'] ?? ''));
        if ($eventType === '') {
            $eventType = trim((string) ($props['jtType'] ?? ''));
        }

        return [
            'title' => trim((string) ($event['title'] ?? '')),
            'start' => $startRaw,
            'url' => trim((string) ($event['url'] ?? '')),
            'event_type' => $eventType,
            'customer_name' => trim((string) ($props['customerName'] ?? '')),
            'all_day' => (bool) ($event['allDay'] ?? false),
            'color' => trim((string) ($event['backgroundColor'] ?? '')),
        ];
    }

    private static function dashboardAgendaDateKey(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches)) {
            return $matches[1];
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private static function dashboardAgendaDayLabel(
        string $dateKey,
        string $today,
        string $tomorrow,
        string $yesterday,
        bool $pastDueMode
    ): string {
        $formatted = date('l, F j', strtotime($dateKey));
        if ($dateKey === $today) {
            return ($pastDueMode ? 'Today (overdue) · ' : 'Today · ') . $formatted;
        }
        if ($dateKey === $tomorrow) {
            return 'Tomorrow · ' . $formatted;
        }
        if ($pastDueMode && $dateKey === $yesterday) {
            return 'Yesterday · ' . $formatted;
        }

        return $formatted;
    }

    /**
     * Statuses that should not appear on dashboard Past due or Work in progress agendas.
     */
    private static function isClosedDashboardAgendaStatus(string $status): bool
    {
        if ($status === '') {
            return false;
        }

        return in_array($status, [
            'active',
            'won',
            'expired',
            'inactive',
            'closed',
            'completed',
            'complete',
            'cancelled',
            'declined',
            'converted',
            'lost',
        ], true);
    }

    /**
     * Dashboard work-in-progress: open items only; jobs must be scheduled in the future.
     */
    private static function isOpenDashboardAgendaEvent(array $event, int $nowTs): bool
    {
        $startRaw = trim((string) ($event['start'] ?? ''));
        if ($startRaw === '') {
            return false;
        }
        $startTs = strtotime($startRaw);
        if ($startTs === false) {
            return false;
        }

        $props = is_array($event['extendedProps'] ?? null) ? $event['extendedProps'] : [];
        $status = strtolower(trim((string) ($props['jtStatus'] ?? '')));
        if (self::isClosedDashboardAgendaStatus($status)) {
            return false;
        }

        $eventId = trim((string) ($event['id'] ?? ''));
        $isAllDay = (bool) ($event['allDay'] ?? false);
        $eventDate = date('Y-m-d', $startTs);
        $todayDate = date('Y-m-d', $nowTs);

        if ($eventDate < $todayDate) {
            return false;
        }

        if (str_starts_with($eventId, 'job:')) {
            return $startTs >= $nowTs;
        }

        if (str_starts_with($eventId, 'task:')) {
            return !self::isPastDueDashboardAgendaEvent($event, $nowTs);
        }

        if ($isAllDay && $eventDate === $todayDate) {
            return true;
        }

        return $startTs >= $nowTs;
    }

    /**
     * Dashboard past-due: open items whose scheduled/due time has passed.
     * Active jobs and won/expired quotes are excluded — work has started or the quote closed.
     */
    private static function isPastDueDashboardAgendaEvent(array $event, int $nowTs): bool
    {
        $startRaw = trim((string) ($event['start'] ?? ''));
        if ($startRaw === '') {
            return false;
        }
        $startTs = strtotime($startRaw);
        if ($startTs === false) {
            return false;
        }

        $props = is_array($event['extendedProps'] ?? null) ? $event['extendedProps'] : [];
        $status = strtolower(trim((string) ($props['jtStatus'] ?? '')));
        if (self::isClosedDashboardAgendaStatus($status)) {
            return false;
        }

        $eventId = trim((string) ($event['id'] ?? ''));
        $isAllDay = (bool) ($event['allDay'] ?? false);
        $eventDate = date('Y-m-d', $startTs);
        $todayDate = date('Y-m-d', $nowTs);

        if (str_starts_with($eventId, 'job:')) {
            return $startTs < $nowTs;
        }

        if (str_starts_with($eventId, 'task:')) {
            if ($eventDate < $todayDate) {
                return true;
            }
            if ($eventDate === $todayDate && !$isAllDay) {
                return $startTs < $nowTs;
            }

            return false;
        }

        if ($eventDate < $todayDate) {
            return true;
        }
        if ($eventDate === $todayDate && !$isAllDay) {
            return $startTs < $nowTs;
        }

        return false;
    }

    private static function outstandingQuotes(int $businessId, int $limit = 6): array
    {
        if (SchemaInspector::hasTable('quotes')) {
            $sql = 'SELECT
                        q.id,
                        q.title AS invoice_number,
                        LOWER(COALESCE(q.status, "new")) AS status,
                        COALESCE(q.quoted_amount, 0) AS total,
                        q.created_at AS issue_date,
                        q.next_follow_up_at AS due_date,
                        COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name,
                        q.converted_job_id AS job_id,
                    q.title AS job_title,
                    "quote" AS source_type
                    FROM quotes q
                    INNER JOIN clients c ON c.id = q.client_id
                        AND c.business_id = q.business_id
                        AND c.deleted_at IS NULL
                    WHERE q.business_id = :business_id
                      AND q.deleted_at IS NULL
                      AND LOWER(COALESCE(q.status, "new")) IN ("new", "sent", "follow_up")
                    ORDER BY
                        CASE WHEN q.next_follow_up_at IS NULL THEN 1 ELSE 0 END,
                        q.next_follow_up_at ASC,
                        q.id DESC
                    LIMIT :row_limit';
            $stmt = Database::connection()->prepare($sql);
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        }

        if (!SchemaInspector::hasTable('invoices')) {
            return [];
        }

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CONCAT('EST-', i.id)";
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'LOWER(COALESCE(i.status, ""))' : "'draft'";
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $issueDateSql = SchemaInspector::hasColumn('invoices', 'issue_date') ? 'i.issue_date' : 'NULL';
        $dueDateSql = SchemaInspector::hasColumn('invoices', 'due_date') ? 'i.due_date' : 'NULL';
        $typeSql = SchemaInspector::hasColumn('invoices', 'type') ? 'LOWER(COALESCE(i.type, ""))' : "'invoice'";
        $jobIdSql = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id' : 'NULL';

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $joinSql = ' LEFT JOIN clients c ON c.id = i.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('invoices', 'business_id')) {
                $joinSql .= ' AND c.business_id = i.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $jobJoinSql = '';
        $jobTitleSql = 'NULL';
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $jobBaseTitleSql = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $jobJoinSql = ' LEFT JOIN jobs j ON j.id = i.job_id';
            if (SchemaInspector::hasColumn('jobs', 'business_id') && SchemaInspector::hasColumn('invoices', 'business_id')) {
                $jobJoinSql .= ' AND j.business_id = i.business_id';
            }
            if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
                $jobJoinSql .= ' AND j.deleted_at IS NULL';
            }
            $jobTitleSql = "COALESCE(NULLIF({$jobBaseTitleSql}, ''), CONCAT('Job #', j.id))";
        }

        $where = [
            SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1',
            "{$typeSql} = 'estimate'",
            "{$statusSql} NOT IN ('declined', 'closed', 'converted', 'cancelled', 'approved')",
        ];

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    {$statusSql} AS status,
                    {$totalSql} AS total,
                    {$issueDateSql} AS issue_date,
                    {$dueDateSql} AS due_date,
                    {$clientNameSql} AS client_name,
                    {$jobIdSql} AS job_id,
                    {$jobTitleSql} AS job_title,
                    'estimate' AS source_type
                FROM invoices i
                {$joinSql}
                {$jobJoinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    CASE WHEN {$dueDateSql} IS NULL THEN 1 ELSE 0 END,
                    {$dueDateSql} ASC,
                    i.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function recentSales(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [];
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : "'sale'";
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 's.sale_date' : 's.created_at';
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;

        $where = [
            SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1',
            'DATE(' . $dateSql . ") >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'DATE(' . $dateSql . ') <= CURDATE()',
        ];
        Sale::appendScopeToWhere($where, Sale::ESTATE_SCOPE_GENERAL);

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount
                FROM sales s
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$dateSql} DESC, s.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function recentEstateSaleRecords(int $businessId, int $limit = 5): array
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            return [];
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 's.sale_date' : 's.created_at';
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;
        $estateTitleSql = SchemaInspector::hasTable('estate_sales') && SchemaInspector::hasColumn('estate_sales', 'title')
            ? "COALESCE(NULLIF(TRIM(es.title), ''), CONCAT('Estate Sale #', es.id))"
            : "CONCAT('Estate Sale #', s.estate_sale_id)";
        $customerNameSql = 'NULL';
        $joins = [];
        if (SchemaInspector::hasTable('estate_sales')) {
            $join = 'LEFT JOIN estate_sales es ON es.id = s.estate_sale_id';
            if (SchemaInspector::hasColumn('estate_sales', 'deleted_at')) {
                $join .= ' AND es.deleted_at IS NULL';
            }
            if (SchemaInspector::hasColumn('estate_sales', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
                $join .= ' AND es.business_id = s.business_id';
            }
            $joins[] = $join;
        }
        if (SchemaInspector::hasColumn('sales', 'estate_sale_customer_id') && SchemaInspector::hasTable('estate_sale_customers')) {
            $join = 'LEFT JOIN estate_sale_customers esc ON esc.id = s.estate_sale_customer_id';
            if (SchemaInspector::hasColumn('estate_sale_customers', 'deleted_at')) {
                $join .= ' AND esc.deleted_at IS NULL';
            }
            $joins[] = $join;
            $customerNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        }

        $where = [
            SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1',
            'DATE(' . $dateSql . ") >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            'DATE(' . $dateSql . ') <= CURDATE()',
        ];
        Sale::appendScopeToWhere($where, Sale::ESTATE_SCOPE_ESTATE_ONLY);

        $sql = "SELECT
                    s.id,
                    s.estate_sale_id,
                    {$nameSql} AS name,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$estateTitleSql} AS estate_sale_title,
                    {$customerNameSql} AS customer_name
                FROM sales s";
        if ($joins !== []) {
            $sql .= "\n" . implode("\n", $joins);
        }
        $sql .= "\nWHERE " . implode(' AND ', $where) . "
                ORDER BY {$dateSql} DESC, s.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Last three calendar months (oldest → newest): total gross (sales + estate + service), sales gross, estate sales gross, service gross, expenses total, net profit.
     * Net uses payments received for service income, not invoiced totals.
     *
     * @return array{months: list<array{label: string, total_gross: float, sales_gross: float, service_gross: float, expenses_total: float, net_profit: float}>}
     */
    public static function lastThreeMonthsChart(int $businessId): array
    {
        $months = [];
        $start = new \DateTimeImmutable('first day of this month');
        for ($i = 2; $i >= 0; $i--) {
            $dt = $start->modify('-' . $i . ' months');
            $from = $dt->format('Y-m-01');
            $to = $dt->format('Y-m-t');
            $label = $dt->format('M Y');
            $salesGross = self::monthlySalesGross($businessId, $from, $to, Sale::ESTATE_SCOPE_GENERAL);
            $estateSalesGross = self::monthlySalesGross($businessId, $from, $to, Sale::ESTATE_SCOPE_ESTATE_ONLY);
            $salesNet = self::monthlySalesNet($businessId, $from, $to, Sale::ESTATE_SCOPE_GENERAL);
            $estatePeriod = EstateSale::periodFinancialTotals($businessId, $from, $to);
            $estateSalesNet = (float) ($estatePeriod['net'] ?? 0);
            $servicePayments = ReportSummary::servicePaymentsTotalsForRange($businessId, $from, $to);
            $serviceGross = (float) ($servicePayments['gross'] ?? 0);
            $serviceNet = (float) ($servicePayments['net'] ?? 0);
            $jobExp = self::expensesTotalBetween($businessId, $from, $to, true);
            $generalExp = self::expensesTotalBetween($businessId, $from, $to, false);
            $expensesTotal = round($jobExp + $generalExp, 2);
            $netProfit = round($salesNet + $estateSalesNet + $serviceNet - $generalExp, 2);
            $totalGross = round($salesGross + $estateSalesGross + $serviceGross, 2);
            $months[] = [
                'label' => $label,
                'total_gross' => $totalGross,
                'sales_gross' => round($salesGross, 2),
                'estate_sales_gross' => round($estateSalesGross, 2),
                'service_gross' => round($serviceGross, 2),
                'expenses_total' => $expensesTotal,
                'net_profit' => $netProfit,
            ];
        }

        return ['months' => $months];
    }

    private static function monthlySalesGross(int $businessId, string $from, string $to, string $estateSaleScope = Sale::ESTATE_SCOPE_ALL): float
    {
        if (!SchemaInspector::hasTable('sales')) {
            return 0.0;
        }
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 'DATE(s.sale_date)' : 'DATE(s.created_at)';
        $where = [
            SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1',
            "{$dateSql} BETWEEN :from_date AND :to_date",
        ];
        Sale::appendScopeToWhere($where, $estateSaleScope);
        $sql = "SELECT COALESCE(SUM({$grossSql}), 0) AS gross_total FROM sales s WHERE " . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':from_date', $from, \PDO::PARAM_STR);
        $stmt->bindValue(':to_date', $to, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        return (float) ($row['gross_total'] ?? 0);
    }

    private static function monthlySalesNet(int $businessId, string $from, string $to, string $estateSaleScope = Sale::ESTATE_SCOPE_ALL): float
    {
        if (!SchemaInspector::hasTable('sales')) {
            return 0.0;
        }
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 'DATE(s.sale_date)' : 'DATE(s.created_at)';
        $where = [
            SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1',
            "{$dateSql} BETWEEN :from_date AND :to_date",
        ];
        Sale::appendScopeToWhere($where, $estateSaleScope);
        $sql = "SELECT COALESCE(SUM({$netSql}), 0) AS net_total FROM sales s WHERE " . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':from_date', $from, \PDO::PARAM_STR);
        $stmt->bindValue(':to_date', $to, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        return (float) ($row['net_total'] ?? 0);
    }
}
