<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ReportSummary
{
    public static function build(int $businessId, string $fromDate, string $toDate): array
    {
        $sales = self::salesSummary($businessId, $fromDate, $toDate);
        $service = self::serviceSummary($businessId, $fromDate, $toDate);
        $expenses = self::expenseSummary($businessId, $fromDate, $toDate);
        $purchases = self::purchaseSummary($businessId, $fromDate, $toDate);

        $overallGross = $sales['gross'] + $service['gross'];
        $overallNet = $sales['net'] + $service['net'] - $expenses['general_total'];
        $overallNetMinusPurchases = $overallNet - $purchases['total'];

        return [
            'sales' => $sales,
            'service' => $service,
            'expenses' => $expenses,
            'purchases' => $purchases,
            'overall' => [
                'gross' => round($overallGross, 2),
                'net' => round($overallNet, 2),
                'net_minus_purchases' => round($overallNetMinusPurchases, 2),
            ],
            'lists' => [
                'jobs' => self::jobsList($businessId, $fromDate, $toDate),
                'sales' => self::salesList($businessId, $fromDate, $toDate),
                'purchases' => self::purchasesList($businessId, $fromDate, $toDate),
            ],
        ];
    }

    private static function salesSummary(int $businessId, string $fromDate, string $toDate): array
    {
        $defaultTypes = [];
        foreach (self::saleTypeOptions($businessId) as $type) {
            $defaultTypes[$type] = ['count' => 0, 'gross' => 0.0, 'net' => 0.0];
        }

        if (!SchemaInspector::hasTable('sales')) {
            return ['count' => 0, 'gross' => 0.0, 'net' => 0.0, 'by_type' => $defaultTypes];
        }

        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? "LOWER(COALESCE(NULLIF(TRIM(s.sale_type), ''), 'other'))" : "'other'";
        $dateSql = self::dateSql('sales', 's', ['sale_date', 'created_at']);

        $where = self::baseWhere('sales', 's', $businessId);
        if ($dateSql !== null) {
            $where[] = "DATE({$dateSql}) BETWEEN :from_date AND :to_date";
        }

        $sql = "SELECT
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = self::baseParams('sales', $businessId);
        if ($dateSql !== null) {
            $params['from_date'] = $fromDate;
            $params['to_date'] = $toDate;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        $byType = $defaultTypes;
        $typeStmt = Database::connection()->prepare("SELECT
                    {$typeSql} AS sale_type,
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where) . "
                GROUP BY {$typeSql}");
        $typeStmt->execute($params);
        foreach ($typeStmt->fetchAll() as $typeRow) {
            $typeKey = strtolower(trim((string) ($typeRow['sale_type'] ?? '')));
            if ($typeKey === '') {
                $typeKey = 'other';
            }
            if (!isset($byType[$typeKey])) {
                $byType[$typeKey] = ['count' => 0, 'gross' => 0.0, 'net' => 0.0];
            }

            $byType[$typeKey] = [
                'count' => (int) ($typeRow['item_count'] ?? 0),
                'gross' => (float) ($typeRow['gross_total'] ?? 0),
                'net' => (float) ($typeRow['net_total'] ?? 0),
            ];
        }

        return [
            'count' => (int) ($row['item_count'] ?? 0),
            'gross' => (float) ($row['gross_total'] ?? 0),
            'net' => (float) ($row['net_total'] ?? 0),
            'by_type' => $byType,
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

        foreach (Sale::typeOptions($businessId) as $value) {
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
