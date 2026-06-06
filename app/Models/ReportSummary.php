<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ReportSummary
{
    public static function build(int $businessId, string $fromDate, string $toDate): array
    {
        $sales = self::salesSummary($businessId, $fromDate, $toDate);
        $estateSales = self::estateSalesSummary($businessId, $fromDate, $toDate);
        $service = self::serviceSummary($businessId, $fromDate, $toDate);
        $expenses = self::expenseSummary($businessId, $fromDate, $toDate);
        $purchases = self::purchaseSummary($businessId, $fromDate, $toDate);

        $overallGross = $sales['gross'] + $estateSales['gross'] + $service['gross'];
        $overallNet = $sales['net'] + $estateSales['net'] + $service['net'] - $expenses['general_total'];
        $overallNetMinusPurchases = $overallNet - $purchases['total'];

        return [
            'sales' => $sales,
            'estate_sales' => $estateSales,
            'service' => $service,
            'expenses' => $expenses,
            'purchases' => $purchases,
            'overall' => [
                'gross' => round($overallGross, 2),
                'net' => round($overallNet, 2),
                'net_minus_purchases' => round($overallNetMinusPurchases, 2),
            ],
            'margin_by_job' => self::marginByJob($businessId, $fromDate, $toDate),
        ];
    }

    /**
     * Jobs with activity in the date range (same rules as the former income report list).
     *
     * @return list<array<string, mixed>>
     */
    public static function jobsInRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::jobsList($businessId, $fromDate, $toDate, 200);
    }

    /**
     * Total number of jobs in the date range (the jobs list is capped separately).
     */
    public static function jobsCountForRange(int $businessId, string $fromDate, string $toDate): int
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return 0;
        }

        $dateSql = self::dateSql('jobs', 'j', ['scheduled_start_at', 'created_at']);
        $where = self::baseWhere('jobs', 'j', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = 'SELECT COUNT(*) AS cnt FROM jobs j WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('jobs', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Sales aggregates for the period (full totals, not limited to list rows).
     *
     * @return array{count:int, gross:float, net:float, by_type: array<string, array{count:int, gross:float, net:float}>}
     */
    public static function salesTotalsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::salesSummary($businessId, $fromDate, $toDate);
    }

    /**
     * Estate sale transaction aggregates for the period.
     *
     * @return array{count:int, transaction_count:int, estate_sale_count:int, gross:float, net:float, by_event: list<array{estate_sale_id:int, title:string, transaction_count:int, gross:float, net:float}>}
     */
    public static function estateSalesTotalsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::estateSalesSummary($businessId, $fromDate, $toDate);
    }

    /**
     * Purchase aggregates for the period (full totals, not limited to list rows).
     *
     * @return array{count:int, total:float}
     */
    public static function purchaseTotalsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::purchaseSummary($businessId, $fromDate, $toDate);
    }

    /**
     * Service (invoice) aggregates for the period (full totals, not limited to list rows).
     *
     * @return array{count:int, gross:float, job_expenses:float, net:float}
     */
    public static function serviceTotalsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::serviceSummary($businessId, $fromDate, $toDate);
    }

    /**
     * Service payment aggregates for the period (cash received on service invoices).
     *
     * @return array{count:int, gross:float, job_expenses:float, net:float}
     */
    public static function servicePaymentsTotalsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::servicePaymentsSummary($businessId, $fromDate, $toDate);
    }

    /**
     * Overall net for dashboard KPIs: sales + estate + service payments − general expenses.
     *
     * @return array{net:float, net_minus_purchases:float}
     */
    public static function overallNetForRange(int $businessId, string $fromDate, string $toDate): array
    {
        $sales = self::salesSummary($businessId, $fromDate, $toDate);
        $estateSales = self::estateSalesSummary($businessId, $fromDate, $toDate);
        $service = self::servicePaymentsSummary($businessId, $fromDate, $toDate);
        $expenses = self::expenseSummary($businessId, $fromDate, $toDate);
        $purchases = self::purchaseSummary($businessId, $fromDate, $toDate);
        $net = $sales['net'] + $estateSales['net'] + $service['net'] - $expenses['general_total'];

        return [
            'net' => round($net, 2),
            'net_minus_purchases' => round($net - $purchases['total'], 2),
        ];
    }

    /**
     * Sales in the date range (same rules as the former income report list).
     *
     * @return list<array<string, mixed>>
     */
    public static function salesInRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::salesList($businessId, $fromDate, $toDate, 200);
    }

    /**
     * Purchases with a date in the range (same rules as the income report list).
     *
     * @return list<array<string, mixed>>
     */
    public static function purchasesInRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::purchasesList($businessId, $fromDate, $toDate, 200);
    }

    /**
     * Expense totals and category breakdown for the range.
     *
     * @return array{count:int, job_total:float, general_total:float, total:float, by_category: list<array{category:string, total:float, count:int}>}
     */
    public static function expenseReportData(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return [
                'count' => 0,
                'job_total' => 0.0,
                'general_total' => 0.0,
                'total' => 0.0,
                'by_category' => [],
            ];
        }

        $jobTotal = self::expensesTotal($businessId, $fromDate, $toDate, true);
        $generalTotal = self::expensesTotal($businessId, $fromDate, $toDate, false);
        $count = self::expensesCount($businessId, $fromDate, $toDate);

        return [
            'count' => $count,
            'job_total' => $jobTotal,
            'general_total' => $generalTotal,
            'total' => round($jobTotal + $generalTotal, 2),
            'by_category' => self::expensesByCategory($businessId, $fromDate, $toDate, 200),
        ];
    }

    /**
     * Service invoices with issue (or created) date in the range.
     *
     * @return list<array<string, mixed>>
     */
    public static function invoicesInRange(int $businessId, string $fromDate, string $toDate): array
    {
        return self::invoicesList($businessId, $fromDate, $toDate, 200);
    }

    /**
     * @return list<array{job_id:int, title:string, sales_net:float, purchase_cogs:float, margin:float}>
     */
    public static function marginByJob(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasTable('jobs')) {
            return [];
        }
        if (!SchemaInspector::hasColumn('sales', 'job_id')) {
            return [];
        }

        $dateSql = self::dateSql('sales', 's', ['sale_date', 'created_at']);
        if ($dateSql === null) {
            return [];
        }

        $netSql = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 'COALESCE(s.net_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'gross_amount') ? 'COALESCE(s.gross_amount, 0)' : '0');

        $purchaseJoin = '';
        $cogsExpr = '0';
        if (SchemaInspector::hasTable('purchases') && SchemaInspector::hasColumn('sales', 'purchase_id') && SchemaInspector::hasColumn('purchases', 'purchase_price')) {
            $purchaseJoin = 'LEFT JOIN purchases p ON p.id = s.purchase_id AND p.business_id = s.business_id AND p.deleted_at IS NULL';
            $cogsExpr = 'COALESCE(p.purchase_price, 0)';
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? "COALESCE(NULLIF(TRIM(j.title), ''), CONCAT('Job #', j.id))"
            : "CONCAT('Job #', j.id)";

        $sql = "SELECT
                    j.id AS job_id,
                    {$titleSql} AS title,
                    COALESCE(SUM({$netSql}), 0) AS sales_net,
                    COALESCE(SUM({$cogsExpr}), 0) AS purchase_cogs
                FROM sales s
                INNER JOIN jobs j ON j.id = s.job_id AND j.business_id = s.business_id
                {$purchaseJoin}
                WHERE " . implode(' AND ', self::baseWhere('sales', 's', $businessId)) . "
                  AND j.deleted_at IS NULL
                  AND DATE({$dateSql}) BETWEEN :from_date AND :to_date
                GROUP BY j.id, j.title
                HAVING ABS(COALESCE(SUM({$netSql}), 0)) > 0.0001 OR ABS(COALESCE(SUM({$cogsExpr}), 0)) > 0.0001
                ORDER BY (COALESCE(SUM({$netSql}), 0) - COALESCE(SUM({$cogsExpr}), 0)) DESC
                LIMIT 50";

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('sales', $businessId);
        $params['from_date'] = $fromDate;
        $params['to_date'] = $toDate;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sn = (float) ($row['sales_net'] ?? 0);
            $cogs = (float) ($row['purchase_cogs'] ?? 0);
            $out[] = [
                'job_id' => (int) ($row['job_id'] ?? 0),
                'title' => trim((string) ($row['title'] ?? '')) ?: '—',
                'sales_net' => round($sn, 2),
                'purchase_cogs' => round($cogs, 2),
                'margin' => round($sn - $cogs, 2),
            ];
        }

        return $out;
    }

    /**
     * Period totals for job-linked sales vs purchase COGS (same rules as margin by job, all jobs — no row limit).
     *
     * @return array{sales_net: float, purchase_cogs: float, margin: float}
     */
    public static function marginTotalsForRange(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasTable('jobs')) {
            return ['sales_net' => 0.0, 'purchase_cogs' => 0.0, 'margin' => 0.0];
        }
        if (!SchemaInspector::hasColumn('sales', 'job_id')) {
            return ['sales_net' => 0.0, 'purchase_cogs' => 0.0, 'margin' => 0.0];
        }

        $dateSql = self::dateSql('sales', 's', ['sale_date', 'created_at']);
        if ($dateSql === null) {
            return ['sales_net' => 0.0, 'purchase_cogs' => 0.0, 'margin' => 0.0];
        }

        $netSql = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 'COALESCE(s.net_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'gross_amount') ? 'COALESCE(s.gross_amount, 0)' : '0');

        $purchaseJoin = '';
        $cogsExpr = '0';
        if (SchemaInspector::hasTable('purchases') && SchemaInspector::hasColumn('sales', 'purchase_id') && SchemaInspector::hasColumn('purchases', 'purchase_price')) {
            $purchaseJoin = 'LEFT JOIN purchases p ON p.id = s.purchase_id AND p.business_id = s.business_id AND p.deleted_at IS NULL';
            $cogsExpr = 'COALESCE(p.purchase_price, 0)';
        }

        $sql = "SELECT
                    COALESCE(SUM(job_margins.sales_net), 0) AS total_sales_net,
                    COALESCE(SUM(job_margins.purchase_cogs), 0) AS total_purchase_cogs
                FROM (
                    SELECT
                        COALESCE(SUM({$netSql}), 0) AS sales_net,
                        COALESCE(SUM({$cogsExpr}), 0) AS purchase_cogs
                    FROM sales s
                    INNER JOIN jobs j ON j.id = s.job_id AND j.business_id = s.business_id
                    {$purchaseJoin}
                    WHERE " . implode(' AND ', self::baseWhere('sales', 's', $businessId)) . "
                      AND j.deleted_at IS NULL
                      AND DATE({$dateSql}) BETWEEN :from_date AND :to_date
                    GROUP BY j.id
                    HAVING ABS(COALESCE(SUM({$netSql}), 0)) > 0.0001 OR ABS(COALESCE(SUM({$cogsExpr}), 0)) > 0.0001
                ) AS job_margins";

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('sales', $businessId);
        $params['from_date'] = $fromDate;
        $params['to_date'] = $toDate;
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['sales_net' => 0.0, 'purchase_cogs' => 0.0, 'margin' => 0.0];
        }

        $sn = (float) ($row['total_sales_net'] ?? 0);
        $cogs = (float) ($row['total_purchase_cogs'] ?? 0);

        return [
            'sales_net' => round($sn, 2),
            'purchase_cogs' => round($cogs, 2),
            'margin' => round($sn - $cogs, 2),
        ];
    }

    private static function salesSummary(int $businessId, string $fromDate, string $toDate): array
    {
        $defaultTypes = [];
        foreach (self::saleTypeOptions($businessId) as $type) {
            $defaultTypes[$type] = ['count' => 0, 'gross' => 0.0, 'net' => 0.0];
        }

        $totals = Sale::periodTotals($businessId, $fromDate, $toDate, Sale::ESTATE_SCOPE_GENERAL, true);
        $byType = is_array($totals['by_type'] ?? null) ? $totals['by_type'] : [];
        unset($totals['by_type']);

        foreach ($byType as $typeKey => $typeSummary) {
            if (!isset($defaultTypes[$typeKey])) {
                $defaultTypes[$typeKey] = ['count' => 0, 'gross' => 0.0, 'net' => 0.0];
            }
            $defaultTypes[$typeKey] = $typeSummary;
        }

        return [
            'count' => (int) ($totals['count'] ?? 0),
            'gross' => (float) ($totals['gross'] ?? 0),
            'net' => (float) ($totals['net'] ?? 0),
            'by_type' => $defaultTypes,
        ];
    }

    /**
     * @return array{count:int, transaction_count:int, estate_sale_count:int, gross:float, net:float, by_event: list<array{estate_sale_id:int, title:string, transaction_count:int, gross:float, net:float}>}
     */
    private static function estateSalesSummary(int $businessId, string $fromDate, string $toDate): array
    {
        $totals = EstateSale::periodFinancialTotals($businessId, $fromDate, $toDate);

        return [
            'count' => (int) ($totals['transaction_count'] ?? 0),
            'transaction_count' => (int) ($totals['transaction_count'] ?? 0),
            'estate_sale_count' => (int) ($totals['estate_sale_count'] ?? 0),
            'gross' => (float) ($totals['gross'] ?? 0),
            'net' => (float) ($totals['net'] ?? 0),
            'by_event' => EstateSale::periodBreakdownForRange($businessId, $fromDate, $toDate),
        ];
    }

    /**
     * @return list<string>
     */
    private static function saleTypeOptions(int $businessId): array
    {
        $options = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            FormSelectValue::optionsForSection($businessId, 'sale_type')
        );

        foreach (Sale::typeOptions($businessId, Sale::ESTATE_SCOPE_GENERAL) as $value) {
            $normalized = strtolower(trim((string) $value));
            if ($normalized !== '') {
                $options[] = $normalized;
            }
        }

        $options = array_values(array_unique(array_filter($options, static fn (string $value): bool => $value !== '')));
        sort($options);

        return $options;
    }

    private static function serviceSummary(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return ['count' => 0, 'gross' => 0.0, 'job_expenses' => 0.0, 'net' => 0.0];
        }

        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $dateSql = self::dateSql('invoices', 'i', ['issue_date', 'created_at']);

        $where = self::baseWhere('invoices', 'i', $businessId);
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) = 'invoice')";
        }
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$totalSql}), 0) AS gross_total
                FROM invoices i
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('invoices', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        $gross = (float) ($row['gross_total'] ?? 0);
        $jobExpenses = self::expensesTotal($businessId, $fromDate, $toDate, true);
        $subMetrics = JobSubcontractorAssignment::completedMetricsForRange($businessId, $fromDate, $toDate);
        if (($subMetrics['count'] ?? 0) > 0) {
            $gross = $gross - (float) ($subMetrics['invoice_gross_total'] ?? 0) + (float) ($subMetrics['our_cut_total'] ?? 0);
        }

        return [
            'count' => (int) ($row['item_count'] ?? 0),
            'gross' => round($gross, 2),
            'job_expenses' => $jobExpenses,
            'net' => round($gross - $jobExpenses, 2),
            'sub_completed_count' => (int) ($subMetrics['count'] ?? 0),
            'sub_our_cut_total' => (float) ($subMetrics['our_cut_total'] ?? 0),
        ];
    }

    private static function servicePaymentsSummary(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('payments') || !SchemaInspector::hasTable('invoices')) {
            return ['count' => 0, 'gross' => 0.0, 'job_expenses' => 0.0, 'net' => 0.0];
        }

        $amountSql = SchemaInspector::hasColumn('payments', 'amount') ? 'COALESCE(p.amount, 0)' : '0';
        $dateSql = SchemaInspector::hasColumn('payments', 'paid_at') ? 'DATE(p.paid_at)' : 'DATE(p.created_at)';

        $where = [
            SchemaInspector::hasColumn('payments', 'business_id') ? 'p.business_id = :business_id' : '1=1',
            SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :invoice_business_id' : '1=1',
            SchemaInspector::hasColumn('payments', 'invoice_id') ? 'p.invoice_id = i.id' : '1=0',
            SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1',
            SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1',
            "{$dateSql} BETWEEN :from_date AND :to_date",
        ];
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) = 'invoice')";
        }
        if (SchemaInspector::hasColumn('invoices', 'status')) {
            $where[] = "LOWER(COALESCE(i.status, '')) NOT IN ('cancelled','declined','closed')";
        }

        $sql = "SELECT
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$amountSql}), 0) AS gross_total
                FROM payments p
                INNER JOIN invoices i ON i.id = p.invoice_id
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('payments', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':invoice_business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':from_date', $fromDate, \PDO::PARAM_STR);
        $stmt->bindValue(':to_date', $toDate, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        $gross = (float) ($row['gross_total'] ?? 0);
        $jobExpenses = self::expensesTotal($businessId, $fromDate, $toDate, true);

        return [
            'count' => (int) ($row['item_count'] ?? 0),
            'gross' => $gross,
            'job_expenses' => $jobExpenses,
            'net' => round($gross - $jobExpenses, 2),
        ];
    }

    private static function expenseSummary(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return [
                'count' => 0,
                'job_total' => 0.0,
                'general_total' => 0.0,
                'total' => 0.0,
                'by_category' => [],
            ];
        }

        $jobTotal = self::expensesTotal($businessId, $fromDate, $toDate, true);
        $generalTotal = self::expensesTotal($businessId, $fromDate, $toDate, false);
        $count = self::expensesCount($businessId, $fromDate, $toDate);

        return [
            'count' => $count,
            'job_total' => $jobTotal,
            'general_total' => $generalTotal,
            'total' => round($jobTotal + $generalTotal, 2),
            'by_category' => self::expensesByCategory($businessId, $fromDate, $toDate),
        ];
    }

    private static function purchaseSummary(int $businessId, string $fromDate, string $toDate): array
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return ['count' => 0, 'total' => 0.0];
        }

        $priceSql = SchemaInspector::hasColumn('purchases', 'purchase_price') ? 'COALESCE(p.purchase_price, 0)' : '0';
        $dateSql = self::dateSql('purchases', 'p', ['purchase_date', 'created_at']);
        $where = self::baseWhere('purchases', 'p', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$priceSql}), 0) AS total_amount
                FROM purchases p
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('purchases', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'count' => (int) ($row['item_count'] ?? 0),
            'total' => (float) ($row['total_amount'] ?? 0),
        ];
    }

    private static function jobsList(int $businessId, string $fromDate, string $toDate, int $limit = 25): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? "COALESCE(NULLIF(TRIM(j.title), ''), CONCAT('Job #', j.id))"
            : (SchemaInspector::hasColumn('jobs', 'name')
                ? "COALESCE(NULLIF(TRIM(j.name), ''), CONCAT('Job #', j.id))"
                : "CONCAT('Job #', j.id)");
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'LOWER(COALESCE(j.status, \'\'))' : "''";
        $scheduledSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at') ? 'j.scheduled_start_at' : 'NULL';
        $dateSql = self::dateSql('jobs', 'j', ['scheduled_start_at', 'created_at']);

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
            $clientNameSql = self::clientNameSql('c');
        }

        $where = self::baseWhere('jobs', 'j', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$statusSql} AS status,
                    {$scheduledSql} AS scheduled_start_at,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY j.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('jobs', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function salesList(int $businessId, string $fromDate, string $toDate, int $limit = 25): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [];
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name')
            ? "COALESCE(NULLIF(TRIM(s.name), ''), CONCAT('Sale #', s.id))"
            : "CONCAT('Sale #', s.id)";
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;
        $typeSql = SchemaInspector::hasColumn('sales', 'type') ? 'LOWER(COALESCE(s.type, \'\'))' : "''";
        $dateSql = self::dateSql('sales', 's', ['sale_date', 'created_at']);

        $where = self::baseWhere('sales', 's', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }
        Sale::appendScopeToWhere($where, Sale::ESTATE_SCOPE_GENERAL);

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS type,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    " . ($dateSql !== null ? $dateSql : 'NULL') . " AS sale_date
                FROM sales s
                WHERE " . implode(' AND ', $where) . '
                ORDER BY s.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('sales', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function purchasesList(int $businessId, string $fromDate, string $toDate, int $limit = 25): array
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('purchases', 'title')
            ? "COALESCE(NULLIF(TRIM(p.title), ''), CONCAT('Purchase #', p.id))"
            : "CONCAT('Purchase #', p.id)";
        $statusSql = SchemaInspector::hasColumn('purchases', 'status') ? 'LOWER(COALESCE(p.status, \'\'))' : "''";
        $priceSql = SchemaInspector::hasColumn('purchases', 'purchase_price') ? 'COALESCE(p.purchase_price, 0)' : '0';
        $dateSql = self::dateSql('purchases', 'p', ['purchase_date', 'contact_date', 'created_at']);

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('purchases', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = p.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('purchases', 'business_id')) {
                $joinSql .= ' AND c.business_id = p.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = self::clientNameSql('c');
        }

        $where = self::baseWhere('purchases', 'p', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    p.id,
                    {$titleSql} AS title,
                    {$statusSql} AS status,
                    {$priceSql} AS purchase_price,
                    {$clientNameSql} AS client_name,
                    " . ($dateSql !== null ? $dateSql : 'NULL') . " AS purchase_date
                FROM purchases p
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY p.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('purchases', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function invoicesList(int $businessId, string $fromDate, string $toDate, int $limit = 200): array
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return [];
        }

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CONCAT('INV-', i.id)";
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'i.status' : "'draft'";
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $issueDateSql = SchemaInspector::hasColumn('invoices', 'issue_date') ? 'i.issue_date' : 'NULL';
        $dueDateSql = SchemaInspector::hasColumn('invoices', 'due_date') ? 'i.due_date' : 'NULL';
        $jobIdSql = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id' : 'NULL';

        $dateSql = self::dateSql('invoices', 'i', ['issue_date', 'created_at']);

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $joinSql = 'LEFT JOIN clients c ON c.id = i.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('invoices', 'business_id')) {
                $joinSql .= ' AND c.business_id = i.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $joinSql .= ' AND c.deleted_at IS NULL';
            }
            $clientNameSql = self::clientNameSql('c');
        }

        $where = self::baseWhere('invoices', 'i', $businessId);
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) = 'invoice')";
        }
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    {$statusSql} AS status,
                    {$totalSql} AS total,
                    {$jobIdSql} AS job_id,
                    {$clientNameSql} AS client_name,
                    {$issueDateSql} AS issue_date,
                    {$dueDateSql} AS due_date
                FROM invoices i
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY i.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('invoices', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{category: string, total: float, count: int}>
     */
    private static function expensesByCategory(int $businessId, string $fromDate, string $toDate, int $limit = 12): array
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return [];
        }

        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'COALESCE(e.amount, 0)' : '0';
        $dateSql = self::dateSql('expenses', 'e', ['expense_date', 'date', 'created_at']);
        $categoryExpr = SchemaInspector::hasColumn('expenses', 'category')
            ? "COALESCE(NULLIF(TRIM(e.category), ''), 'Uncategorized')"
            : (SchemaInspector::hasColumn('expenses', 'expense_category')
                ? "COALESCE(NULLIF(TRIM(e.expense_category), ''), 'Uncategorized')"
                : "'Uncategorized'");

        $where = self::baseWhere('expenses', 'e', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    {$categoryExpr} AS category_name,
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$amountSql}), 0) AS total_amount
                FROM expenses e
                WHERE " . implode(' AND ', $where) . '
                GROUP BY ' . $categoryExpr . '
                ORDER BY total_amount DESC, category_name ASC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('expenses', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 50)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'category' => trim((string) ($row['category_name'] ?? 'Uncategorized')) ?: 'Uncategorized',
                'total' => (float) ($row['total_amount'] ?? 0),
                'count' => (int) ($row['item_count'] ?? 0),
            ];
        }
        return $out;
    }

    private static function expensesTotal(int $businessId, string $fromDate, string $toDate, bool $jobLinked): float
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return 0.0;
        }

        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'COALESCE(e.amount, 0)' : '0';
        $dateSql = self::dateSql('expenses', 'e', ['expense_date', 'date', 'created_at']);
        $where = self::baseWhere('expenses', 'e', $businessId);
        if (SchemaInspector::hasColumn('expenses', 'job_id')) {
            $where[] = $jobLinked ? 'e.job_id IS NOT NULL' : 'e.job_id IS NULL';
        }
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT COALESCE(SUM({$amountSql}), 0) AS total_amount
                FROM expenses e
                WHERE " . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('expenses', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        $stmt->execute($params);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private static function expensesCount(int $businessId, string $fromDate, string $toDate): int
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return 0;
        }

        $dateSql = self::dateSql('expenses', 'e', ['expense_date', 'date', 'created_at']);
        $where = self::baseWhere('expenses', 'e', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = 'SELECT COUNT(*)
                FROM expenses e
                WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('expenses', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private static function baseWhere(string $table, string $alias, int $businessId): array
    {
        $where = [];
        $where[] = SchemaInspector::hasColumn($table, 'business_id') ? "{$alias}.business_id = :business_id" : '1=1';
        $where[] = SchemaInspector::hasColumn($table, 'deleted_at') ? "{$alias}.deleted_at IS NULL" : '1=1';
        return $where;
    }

    /**
     * @return array<string, int>
     */
    private static function baseParams(string $table, int $businessId): array
    {
        if (SchemaInspector::hasColumn($table, 'business_id')) {
            return ['business_id' => $businessId];
        }
        return [];
    }

    /**
     * @param list<string> $candidates
     */
    private static function dateSql(string $table, string $alias, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (SchemaInspector::hasColumn($table, $column)) {
                return "{$alias}.{$column}";
            }
        }
        return null;
    }

    private static function clientNameSql(string $alias): string
    {
        $firstNameSql = SchemaInspector::hasColumn('clients', 'first_name') ? "{$alias}.first_name" : 'NULL';
        $lastNameSql = SchemaInspector::hasColumn('clients', 'last_name') ? "{$alias}.last_name" : 'NULL';
        $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? "{$alias}.company_name" : 'NULL';

        return "COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', {$firstNameSql}, {$lastNameSql})), ''),
            NULLIF(TRIM({$companySql}), ''),
            CONCAT('Client #', {$alias}.id)
        )";
    }
}
