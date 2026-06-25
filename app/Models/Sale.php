<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Sale
{
    /**
     * @return array<int, string>
     */
    public static function baseTypeOptions(): array
    {
        return ['shop', 'ebay', 'scrap', 'b2b', EstateSale::ON_SITE_SALE_TYPE];
    }

    public const ESTATE_SCOPE_ALL = 'all';
    public const ESTATE_SCOPE_GENERAL = 'general';
    public const ESTATE_SCOPE_ESTATE_ONLY = 'estate_only';

    public const PAYMENT_METHOD_DEFAULT = 'cash';

    /**
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'venmo' => 'Venmo',
            'cashapp' => 'Cash App',
            'paypal' => 'PayPal',
            'credit_card' => 'Credit Card',
            'check' => 'Check',
            'other' => 'Other',
        ];
    }

    public static function paymentMethodLabel(mixed $value): string
    {
        $normalized = self::normalizePaymentMethod($value);

        return self::paymentMethodOptions()[$normalized];
    }

    public static function normalizePaymentMethod(mixed $value): string
    {
        $key = strtolower(trim((string) ($value ?? '')));
        if ($key === 'cc') {
            $key = 'credit_card';
        }

        $options = self::paymentMethodOptions();

        return array_key_exists($key, $options) ? $key : self::PAYMENT_METHOD_DEFAULT;
    }

    public static function indexList(
        int $businessId,
        string $search = '',
        string $type = '',
        string $fromDate = '',
        string $toDate = '',
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'date',
        string $sortDir = 'desc',
        string $estateSaleScope = self::ESTATE_SCOPE_ALL
    ): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [];
        }

        $query = trim($search);
        $type = trim($type);
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);

        $nameSql = self::saleNameExpr();
        $typeSql = self::saleTypeExpr();
        $dateSql = self::saleDateExpr();
        $filterDateSql = self::saleFilterDateExpr();
        $grossSql = self::saleGrossExpr();
        $netSql = self::saleNetExpr();
        $notesSql = self::saleNotesExpr();
        $clientIdSql = self::saleClientIdExpr();
        $jobIdSql = self::saleJobIdExpr();
        $purchaseIdSql = self::salePurchaseIdExpr();

        $clientNameSql = 'NULL';
        $jobTitleSql = 'NULL';
        $purchaseTitleSql = 'NULL';
        $joins = [];

        if (self::canJoinClients()) {
            $joins[] = self::clientJoinSql();
            $clientNameSql = self::clientNameExpr();
        }
        if (self::canJoinJobs()) {
            $joins[] = self::jobJoinSql();
            $jobTitleSql = self::jobTitleExpr();
        }
        if (self::canJoinPurchases()) {
            $joins[] = self::purchaseJoinSql();
            $purchaseTitleSql = self::purchaseTitleExpr();
        }

        $estateSaleScope = self::normalizeEstateSaleScope($estateSaleScope);
        $estateSaleIdSql = SchemaInspector::hasColumn('sales', 'estate_sale_id') ? 's.estate_sale_id' : 'NULL';
        $clientPctSql = SchemaInspector::hasColumn('sales', 'client_percentage') ? 's.client_percentage' : 'NULL AS client_percentage';
        $paymentMethodSql = SchemaInspector::hasColumn('sales', 'payment_method')
            ? 's.payment_method'
            : "'" . self::PAYMENT_METHOD_DEFAULT . "' AS payment_method";
        $estateClientPctSql = 'NULL AS estate_client_percentage';
        $estateSaleTitleSql = 'NULL';
        $estateCustomerNameSql = 'NULL';
        if ($estateSaleScope === self::ESTATE_SCOPE_ESTATE_ONLY && SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            if (SchemaInspector::hasTable('estate_sales')) {
                $joins[] = self::estateSaleJoinSql();
                $estateSaleTitleSql = self::estateSaleTitleExpr();
                if (SchemaInspector::hasColumn('estate_sales', 'client_percentage')) {
                    $estateClientPctSql = 'es.client_percentage AS estate_client_percentage';
                }
            }
            if (SchemaInspector::hasColumn('sales', 'estate_sale_customer_id') && SchemaInspector::hasTable('estate_sale_customers')) {
                $joins[] = self::estateSaleCustomerJoinSql();
                $estateCustomerNameSql = self::estateSaleCustomerNameExpr();
            }
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';
        self::appendEstateSaleScopeCondition($where, $estateSaleScope);
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $where[] = 's.sale_type = :sale_type';
        }
        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $queryLike = '%' . $query . '%';
        $bind = [
            ':query_empty' => $query,
        ];

        $searchParts = [":query_empty = ''"];
        $searchIndex = 0;
        $addSearch = static function (string $expr) use (&$searchParts, &$bind, &$searchIndex, $queryLike): void {
            $searchIndex++;
            $placeholder = ':query_like_' . (string) $searchIndex;
            $searchParts[] = $expr . ' LIKE ' . $placeholder;
            $bind[$placeholder] = $queryLike;
        };

        $addSearch($nameSql);
        $addSearch($typeSql);
        $addSearch("COALESCE({$notesSql}, '')");
        $addSearch('CAST(s.id AS CHAR)');
        if ($clientNameSql !== 'NULL') {
            $addSearch($clientNameSql);
        }
        if ($jobTitleSql !== 'NULL') {
            $addSearch($jobTitleSql);
        }
        if ($purchaseTitleSql !== 'NULL') {
            $addSearch($purchaseTitleSql);
        }
        if ($estateSaleTitleSql !== 'NULL') {
            $addSearch($estateSaleTitleSql);
        }
        if ($estateCustomerNameSql !== 'NULL') {
            $addSearch($estateCustomerNameSql);
        }

        $where[] = '(' . implode(' OR ', $searchParts) . ')';

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$clientIdSql} AS client_id,
                    {$clientNameSql} AS client_name,
                    {$jobIdSql} AS job_id,
                    {$jobTitleSql} AS job_title,
                    {$purchaseIdSql} AS purchase_id,
                    {$purchaseTitleSql} AS purchase_title,
                    {$estateSaleIdSql} AS estate_sale_id,
                    {$estateSaleTitleSql} AS estate_sale_title,
                    {$estateCustomerNameSql} AS estate_customer_name,
                    {$clientPctSql},
                    {$paymentMethodSql},
                    {$estateClientPctSql}
                FROM sales s\n";

        if ($joins !== []) {
            $sql .= implode("\n", $joins) . "\n";
        }

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'asc' ? 'ASC' : 'DESC';
        $sortDateExpr = "COALESCE({$dateSql}, DATE(s.created_at))";
        $sortMap = [
            'date' => "{$sortDateExpr} {$sortDir}, s.id {$sortDir}",
            'id' => "s.id {$sortDir}",
            'client_name' => "{$clientNameSql} {$sortDir}, s.id {$sortDir}",
            'estate_sale_title' => "{$estateSaleTitleSql} {$sortDir}, s.id {$sortDir}",
            'customer_name' => "{$estateCustomerNameSql} {$sortDir}, s.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['date'];

        $sql .= "WHERE " . implode(' AND ', $where) . "\n";
        $sql .= "ORDER BY {$orderBy}\n";
        $sql .= "LIMIT :row_limit\n";
        $sql .= 'OFFSET :row_offset';

        return self::fetchIndexRows($sql, $businessId, $type, $fromDate, $toDate, $bind, $limit, $offset);
    }

    public static function indexCount(
        int $businessId,
        string $search = '',
        string $type = '',
        string $fromDate = '',
        string $toDate = '',
        string $estateSaleScope = self::ESTATE_SCOPE_ALL
    ): int
    {
        if (!SchemaInspector::hasTable('sales')) {
            return 0;
        }

        $query = trim($search);
        $type = trim($type);
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);

        $nameSql = self::saleNameExpr();
        $typeSql = self::saleTypeExpr();
        $notesSql = self::saleNotesExpr();
        $filterDateSql = self::saleFilterDateExpr();

        $clientNameSql = 'NULL';
        $jobTitleSql = 'NULL';
        $purchaseTitleSql = 'NULL';
        $joins = [];

        if (self::canJoinClients()) {
            $joins[] = self::clientJoinSql();
            $clientNameSql = self::clientNameExpr();
        }
        if (self::canJoinJobs()) {
            $joins[] = self::jobJoinSql();
            $jobTitleSql = self::jobTitleExpr();
        }
        if (self::canJoinPurchases()) {
            $joins[] = self::purchaseJoinSql();
            $purchaseTitleSql = self::purchaseTitleExpr();
        }

        $estateSaleScope = self::normalizeEstateSaleScope($estateSaleScope);
        $estateSaleTitleSql = 'NULL';
        $estateCustomerNameSql = 'NULL';
        if ($estateSaleScope === self::ESTATE_SCOPE_ESTATE_ONLY && SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            if (SchemaInspector::hasTable('estate_sales')) {
                $joins[] = self::estateSaleJoinSql();
                $estateSaleTitleSql = self::estateSaleTitleExpr();
            }
            if (SchemaInspector::hasColumn('sales', 'estate_sale_customer_id') && SchemaInspector::hasTable('estate_sale_customers')) {
                $joins[] = self::estateSaleCustomerJoinSql();
                $estateCustomerNameSql = self::estateSaleCustomerNameExpr();
            }
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';
        self::appendEstateSaleScopeCondition($where, $estateSaleScope);
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $where[] = 's.sale_type = :sale_type';
        }
        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $queryLike = '%' . $query . '%';
        $bind = [
            ':query_empty' => $query,
        ];
        $searchParts = [":query_empty = ''"];
        $searchIndex = 0;
        $addSearch = static function (string $expr) use (&$searchParts, &$bind, &$searchIndex, $queryLike): void {
            $searchIndex++;
            $placeholder = ':query_like_' . (string) $searchIndex;
            $searchParts[] = $expr . ' LIKE ' . $placeholder;
            $bind[$placeholder] = $queryLike;
        };

        $addSearch($nameSql);
        $addSearch($typeSql);
        $addSearch("COALESCE({$notesSql}, '')");
        $addSearch('CAST(s.id AS CHAR)');
        if ($clientNameSql !== 'NULL') {
            $addSearch($clientNameSql);
        }
        if ($jobTitleSql !== 'NULL') {
            $addSearch($jobTitleSql);
        }
        if ($purchaseTitleSql !== 'NULL') {
            $addSearch($purchaseTitleSql);
        }
        if ($estateSaleTitleSql !== 'NULL') {
            $addSearch($estateSaleTitleSql);
        }
        if ($estateCustomerNameSql !== 'NULL') {
            $addSearch($estateCustomerNameSql);
        }

        $where[] = '(' . implode(' OR ', $searchParts) . ')';

        $sql = "SELECT COUNT(*)\n";
        $sql .= "FROM sales s\n";
        if ($joins !== []) {
            $sql .= implode("\n", $joins) . "\n";
        }
        $sql .= 'WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $stmt->bindValue(':sale_type', $type);
        }
        if ($fromDate !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        foreach ($bind as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function typeOptions(int $businessId, string $estateSaleScope = self::ESTATE_SCOPE_ALL): array
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'sale_type')) {
            return self::baseTypeOptions();
        }

        $estateSaleScope = self::normalizeEstateSaleScope($estateSaleScope);
        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 'business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        $where[] = "COALESCE(sale_type, '') <> ''";
        self::appendEstateSaleScopeCondition($where, $estateSaleScope, '');

        $sql = 'SELECT DISTINCT sale_type
                FROM sales
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY sale_type ASC';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $dbOptions = is_array($rows)
            ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $rows), static fn ($value): bool => $value !== ''))
            : [];
        $merged = array_unique(array_merge(self::baseTypeOptions(), $dbOptions));
        sort($merged);
        return array_values($merged);
    }

    public static function create(int $businessId, array $payload, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('sales')) {
            throw new \RuntimeException('Sales table is missing.');
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $append = static function (string $column, string $placeholder, mixed $value) use (&$columns, &$placeholders, &$params): void {
            $columns[] = $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        };

        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $append('business_id', ':business_id', $businessId);
        }
        if (SchemaInspector::hasColumn('sales', 'name')) {
            $append('name', ':name', trim((string) ($payload['name'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_type')) {
            $append('sale_type', ':sale_type', trim((string) ($payload['sale_type'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_date')) {
            $append('sale_date', ':sale_date', $payload['sale_date'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'gross_amount')) {
            $append('gross_amount', ':gross_amount', (float) ($payload['gross_amount'] ?? 0));
        } elseif (SchemaInspector::hasColumn('sales', 'amount')) {
            $append('amount', ':amount', (float) ($payload['gross_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'net_amount')) {
            $append('net_amount', ':net_amount', (float) ($payload['net_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'notes')) {
            $append('notes', ':notes', trim((string) ($payload['notes'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'client_id')) {
            $append('client_id', ':client_id', $payload['client_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'job_id')) {
            $append('job_id', ':job_id', $payload['job_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'purchase_id')) {
            $append('purchase_id', ':purchase_id', $payload['purchase_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            $append('estate_sale_id', ':estate_sale_id', $payload['estate_sale_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'estate_sale_customer_id')) {
            $append('estate_sale_customer_id', ':estate_sale_customer_id', $payload['estate_sale_customer_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'client_percentage') && array_key_exists('client_percentage', $payload)) {
            $append('client_percentage', ':client_percentage', $payload['client_percentage']);
        }
        if (SchemaInspector::hasColumn('sales', 'payment_method')) {
            $append('payment_method', ':payment_method', self::normalizePaymentMethod($payload['payment_method'] ?? null));
        }
        if (SchemaInspector::hasColumn('sales', 'created_by')) {
            $append('created_by', ':created_by', $actorUserId > 0 ? $actorUserId : null);
        }
        if (SchemaInspector::hasColumn('sales', 'updated_by')) {
            $append('updated_by', ':updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        $sql = 'INSERT INTO sales (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function summary(int $businessId, string $estateSaleScope = self::ESTATE_SCOPE_ALL): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [
                'count' => 0,
                'mtd_count' => 0,
                'ytd_count' => 0,
                'gross_mtd' => 0.0,
                'net_mtd' => 0.0,
                'gross_ytd' => 0.0,
                'net_ytd' => 0.0,
            ];
        }

        $estateSaleScope = self::normalizeEstateSaleScope($estateSaleScope);
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 'COALESCE(s.net_amount, 0)'
            : $grossSql;
        $filterDateSql = self::saleFilterDateExpr('s');

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';
        self::appendEstateSaleScopeCondition($where, $estateSaleScope);

        $sql = "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN {$filterDateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$filterDateSql} <= CURDATE() THEN 1 ELSE 0 END) AS mtd_count,
                    SUM(CASE WHEN YEAR({$filterDateSql}) = YEAR(CURDATE()) AND {$filterDateSql} <= CURDATE() THEN 1 ELSE 0 END) AS ytd_count,
                    SUM(CASE WHEN {$filterDateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$filterDateSql} <= CURDATE() THEN {$grossSql} ELSE 0 END) AS gross_mtd,
                    SUM(CASE WHEN {$filterDateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$filterDateSql} <= CURDATE() THEN {$netSql} ELSE 0 END) AS net_mtd,
                    SUM(CASE WHEN YEAR({$filterDateSql}) = YEAR(CURDATE()) AND {$filterDateSql} <= CURDATE() THEN {$grossSql} ELSE 0 END) AS gross_ytd,
                    SUM(CASE WHEN YEAR({$filterDateSql}) = YEAR(CURDATE()) AND {$filterDateSql} <= CURDATE() THEN {$netSql} ELSE 0 END) AS net_ytd
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        return [
            'count' => (int) ($row['total_count'] ?? 0),
            'mtd_count' => (int) ($row['mtd_count'] ?? 0),
            'ytd_count' => (int) ($row['ytd_count'] ?? 0),
            'gross_mtd' => (float) ($row['gross_mtd'] ?? 0),
            'net_mtd' => (float) ($row['net_mtd'] ?? 0),
            'gross_ytd' => (float) ($row['gross_ytd'] ?? 0),
            'net_ytd' => (float) ($row['net_ytd'] ?? 0),
        ];
    }

    /**
     * @return array{count:int, gross:float, net:float, by_type?: array<string, array{count:int, gross:float, net:float}>}
     */
    public static function periodTotals(
        int $businessId,
        string $fromDate,
        string $toDate,
        string $estateSaleScope = self::ESTATE_SCOPE_ALL,
        bool $withTypeBreakdown = false
    ): array {
        $empty = ['count' => 0, 'gross' => 0.0, 'net' => 0.0];
        if (!SchemaInspector::hasTable('sales')) {
            return $withTypeBreakdown ? $empty + ['by_type' => []] : $empty;
        }

        $estateSaleScope = self::normalizeEstateSaleScope($estateSaleScope);
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);
        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');
        $filterDateSql = self::saleFilterDateExpr('s');

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';
        self::appendEstateSaleScopeCondition($where, $estateSaleScope);
        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $sql = "SELECT
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($fromDate !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        $stmt->execute();
        $row = $stmt->fetch();

        $result = [
            'count' => (int) ($row['item_count'] ?? 0),
            'gross' => (float) ($row['gross_total'] ?? 0),
            'net' => (float) ($row['net_total'] ?? 0),
        ];

        if (!$withTypeBreakdown || !SchemaInspector::hasColumn('sales', 'sale_type')) {
            return $result;
        }

        $typeSql = "LOWER(COALESCE(NULLIF(TRIM(s.sale_type), ''), 'other'))";
        $typeStmt = Database::connection()->prepare("SELECT
                    {$typeSql} AS sale_type,
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where) . "
                GROUP BY {$typeSql}");
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $typeStmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($fromDate !== '') {
            $typeStmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== '') {
            $typeStmt->bindValue(':to_date', $toDate);
        }
        $typeStmt->execute();

        $byType = [];
        foreach ($typeStmt->fetchAll() as $typeRow) {
            if (!is_array($typeRow)) {
                continue;
            }
            $typeKey = strtolower(trim((string) ($typeRow['sale_type'] ?? '')));
            if ($typeKey === '') {
                $typeKey = 'other';
            }
            $byType[$typeKey] = [
                'count' => (int) ($typeRow['item_count'] ?? 0),
                'gross' => (float) ($typeRow['gross_total'] ?? 0),
                'net' => (float) ($typeRow['net_total'] ?? 0),
            ];
        }

        $result['by_type'] = $byType;

        return $result;
    }

    public static function findForBusiness(int $businessId, int $saleId): ?array
    {
        if (!SchemaInspector::hasTable('sales') || $saleId <= 0) {
            return null;
        }

        $nameSql = self::saleNameExpr();
        $typeSql = self::saleTypeExpr();
        $dateSql = self::saleDateExpr();
        $grossSql = self::saleGrossExpr();
        $netSql = self::saleNetExpr();
        $notesSql = self::saleNotesExpr();
        $clientIdSql = self::saleClientIdExpr();
        $jobIdSql = self::saleJobIdExpr();
        $purchaseIdSql = self::salePurchaseIdExpr();
        $estateSaleIdSql = SchemaInspector::hasColumn('sales', 'estate_sale_id') ? 's.estate_sale_id' : 'NULL AS estate_sale_id';
        $estateSaleCustomerIdSql = SchemaInspector::hasColumn('sales', 'estate_sale_customer_id')
            ? 's.estate_sale_customer_id'
            : 'NULL AS estate_sale_customer_id';
        $clientPctSql = SchemaInspector::hasColumn('sales', 'client_percentage')
            ? 's.client_percentage'
            : 'NULL AS client_percentage';
        $paymentMethodSql = SchemaInspector::hasColumn('sales', 'payment_method')
            ? 's.payment_method'
            : "'" . self::PAYMENT_METHOD_DEFAULT . "' AS payment_method";
        $createdAtSql = SchemaInspector::hasColumn('sales', 'created_at') ? 's.created_at' : 'NULL';
        $updatedAtSql = SchemaInspector::hasColumn('sales', 'updated_at') ? 's.updated_at' : 'NULL';
        $createdByIdSql = SchemaInspector::hasColumn('sales', 'created_by') ? 's.created_by' : 'NULL';
        $createdByNameSql = 'NULL';
        $clientNameSql = 'NULL';
        $jobTitleSql = 'NULL';
        $purchaseTitleSql = 'NULL';
        $joins = [];

        if (self::canJoinClients()) {
            $joins[] = self::clientJoinSql();
            $clientNameSql = self::clientNameExpr();
        }
        if (self::canJoinJobs()) {
            $joins[] = self::jobJoinSql();
            $jobTitleSql = self::jobTitleExpr();
        }
        if (self::canJoinPurchases()) {
            $joins[] = self::purchaseJoinSql();
            $purchaseTitleSql = self::purchaseTitleExpr();
        }
        if (SchemaInspector::hasColumn('sales', 'created_by') && SchemaInspector::hasTable('users')) {
            $userJoin = 'LEFT JOIN users creator ON creator.id = s.created_by';
            if (SchemaInspector::hasColumn('users', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
                $userJoin .= ' AND creator.business_id = s.business_id';
            }
            if (SchemaInspector::hasColumn('users', 'deleted_at')) {
                $userJoin .= ' AND creator.deleted_at IS NULL';
            }
            $joins[] = $userJoin;
            $createdByNameSql = "COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', creator.first_name, creator.last_name)), ''),
                NULLIF(TRIM(creator.email), ''),
                CONCAT('User #', s.created_by)
            )";
        }

        $where = [];
        $where[] = 's.id = :sale_id';
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$notesSql} AS notes,
                    {$clientIdSql} AS client_id,
                    {$clientNameSql} AS client_name,
                    {$jobIdSql} AS job_id,
                    {$jobTitleSql} AS job_title,
                    {$purchaseIdSql} AS purchase_id,
                    {$purchaseTitleSql} AS purchase_title,
                    {$estateSaleIdSql},
                    {$estateSaleCustomerIdSql},
                    {$clientPctSql},
                    {$paymentMethodSql},
                    {$createdByIdSql} AS created_by,
                    {$createdByNameSql} AS created_by_name,
                    {$createdAtSql} AS created_at,
                    {$updatedAtSql} AS updated_at
                FROM sales s\n";

        if ($joins !== []) {
            $sql .= implode("\n", $joins) . "\n";
        }

        $sql .= "WHERE " . implode(' AND ', $where) . "\n";
        $sql .= 'LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':sale_id', $saleId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function update(int $businessId, int $saleId, array $payload, int $actorUserId): void
    {
        if (!SchemaInspector::hasTable('sales') || $saleId <= 0) {
            return;
        }

        $assignments = [];
        $params = [
            'business_id' => $businessId,
            'sale_id' => $saleId,
        ];

        $append = static function (string $column, string $param, mixed $value) use (&$assignments, &$params): void {
            $assignments[] = $column . ' = :' . $param;
            $params[$param] = $value;
        };

        if (SchemaInspector::hasColumn('sales', 'name')) {
            $append('name', 'name', trim((string) ($payload['name'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_type')) {
            $append('sale_type', 'sale_type', trim((string) ($payload['sale_type'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_date')) {
            $append('sale_date', 'sale_date', $payload['sale_date'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'gross_amount')) {
            $append('gross_amount', 'gross_amount', (float) ($payload['gross_amount'] ?? 0));
        } elseif (SchemaInspector::hasColumn('sales', 'amount')) {
            $append('amount', 'amount', (float) ($payload['gross_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'net_amount')) {
            $append('net_amount', 'net_amount', (float) ($payload['net_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'notes')) {
            $append('notes', 'notes', trim((string) ($payload['notes'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'client_id')) {
            $append('client_id', 'client_id', $payload['client_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'job_id')) {
            $append('job_id', 'job_id', $payload['job_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'purchase_id')) {
            $append('purchase_id', 'purchase_id', $payload['purchase_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            $append('estate_sale_id', 'estate_sale_id', $payload['estate_sale_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'estate_sale_customer_id')) {
            $append('estate_sale_customer_id', 'estate_sale_customer_id', $payload['estate_sale_customer_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'client_percentage') && array_key_exists('client_percentage', $payload)) {
            $append('client_percentage', 'client_percentage', $payload['client_percentage']);
        }
        if (SchemaInspector::hasColumn('sales', 'payment_method')) {
            $append('payment_method', 'payment_method', self::normalizePaymentMethod($payload['payment_method'] ?? null));
        }
        if (SchemaInspector::hasColumn('sales', 'updated_by')) {
            $append('updated_by', 'updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        if ($assignments === []) {
            return;
        }

        $sql = 'UPDATE sales
                SET ' . implode(', ', $assignments) . '
                WHERE id = :sale_id';
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $businessId, int $saleId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'deleted_at') || $saleId <= 0) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        $params = [
            'sale_id' => $saleId,
        ];

        if (SchemaInspector::hasColumn('sales', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('sales', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $where = ['id = :sale_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }

        $sql = 'UPDATE sales
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function salesTotalsByJob(int $businessId, int $jobId): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'job_id')) {
            return ['gross' => 0.0, 'net' => 0.0];
        }

        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');

        $where = ['s.job_id = :job_id'];
        $params = ['job_id' => $jobId];

        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = "SELECT
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'gross' => (float) ($row['gross_total'] ?? 0),
            'net' => (float) ($row['net_total'] ?? 0),
        ];
    }

    public static function salesByJob(int $businessId, int $jobId, int $limit = 200): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'job_id')) {
            return [];
        }

        $nameSql = self::saleNameExpr('s');
        $typeSql = self::saleTypeExpr('s');
        $dateSql = self::saleDateExpr('s');
        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');
        $createdAtSql = SchemaInspector::hasColumn('sales', 'created_at') ? 's.created_at' : 'NULL';

        $where = ['s.job_id = :job_id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$createdAtSql} AS created_at
                FROM sales s
                WHERE " . implode(' AND ', $where) . '
                ORDER BY s.id ASC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function salesTotalsByPurchase(int $businessId, int $purchaseId): array
    {
        if ($purchaseId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'purchase_id')) {
            return ['gross' => 0.0, 'net' => 0.0, 'count' => 0];
        }

        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');

        $where = ['s.purchase_id = :purchase_id'];
        $params = ['purchase_id' => $purchaseId];

        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = "SELECT
                    COUNT(*) AS row_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'count' => (int) ($row['row_count'] ?? 0),
            'gross' => (float) ($row['gross_total'] ?? 0),
            'net' => (float) ($row['net_total'] ?? 0),
        ];
    }

    public static function salesByPurchase(int $businessId, int $purchaseId, int $limit = 200): array
    {
        if ($purchaseId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'purchase_id')) {
            return [];
        }

        $nameSql = self::saleNameExpr('s');
        $typeSql = self::saleTypeExpr('s');
        $dateSql = self::saleDateExpr('s');
        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');

        $where = ['s.purchase_id = :purchase_id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount
                FROM sales s
                WHERE " . implode(' AND ', $where) . '
                ORDER BY s.id ASC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':purchase_id', $purchaseId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function salesTotalsByClient(int $businessId, int $clientId): array
    {
        if ($clientId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'client_id')) {
            return ['count' => 0, 'gross' => 0.0, 'net' => 0.0];
        }

        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');

        $where = ['s.client_id = :client_id'];
        $params = ['client_id' => $clientId];

        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }
        self::appendEstateSaleScopeCondition($where, self::ESTATE_SCOPE_GENERAL);

        $sql = "SELECT
                    COUNT(*) AS row_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total,
                    COALESCE(SUM({$netSql}), 0) AS net_total
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'count' => (int) ($row['row_count'] ?? 0),
            'gross' => (float) ($row['gross_total'] ?? 0),
            'net' => (float) ($row['net_total'] ?? 0),
        ];
    }

    public static function salesByClient(int $businessId, int $clientId, int $limit = 200): array
    {
        if ($clientId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'client_id')) {
            return [];
        }

        $nameSql = self::saleNameExpr('s');
        $typeSql = self::saleTypeExpr('s');
        $dateSql = self::saleDateExpr('s');
        $grossSql = self::saleGrossExpr('s');
        $netSql = self::saleNetExpr('s');
        $jobIdSql = self::saleJobIdExpr('s');
        $purchaseIdSql = self::salePurchaseIdExpr('s');

        $jobTitleSql = 'NULL';
        $purchaseTitleSql = 'NULL';
        $joins = [];

        if (self::canJoinJobs()) {
            $joins[] = self::jobJoinSql();
            $jobTitleSql = self::jobTitleExpr();
        }
        if (self::canJoinPurchases()) {
            $joins[] = self::purchaseJoinSql();
            $purchaseTitleSql = self::purchaseTitleExpr();
        }

        $where = ['s.client_id = :client_id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }
        self::appendEstateSaleScopeCondition($where, self::ESTATE_SCOPE_GENERAL);

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$jobIdSql} AS job_id,
                    {$jobTitleSql} AS job_title,
                    {$purchaseIdSql} AS purchase_id,
                    {$purchaseTitleSql} AS purchase_title
                FROM sales s\n";
        if ($joins !== []) {
            $sql .= implode("\n", $joins) . "\n";
        }
        $sql .= "WHERE " . implode(' AND ', $where) . "\n";
        $sql .= "ORDER BY s.id DESC\n";
        $sql .= 'LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function jobSearchOptions(int $businessId, string $query = '', int $limit = 8): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $query = trim($query);
        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : "''";
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $createdDateSql = SchemaInspector::hasColumn('jobs', 'created_at') ? 'DATE(j.created_at)' : 'NULL';
        $jobDateSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'DATE(j.scheduled_start_at)'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'DATE(j.start_date)' : $createdDateSql);

        $joins = [];
        $clientNameSql = "''";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id')) {
            $join = 'LEFT JOIN clients c ON c.id = j.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')) {
                $join .= ' AND c.business_id = j.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $join .= ' AND c.deleted_at IS NULL';
            }
            $joins[] = $join;
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), '')";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        $where[] = "(
            :query = ''
            OR COALESCE({$titleSql}, '') LIKE :query_like_1
            OR CAST(j.id AS CHAR) LIKE :query_like_2
            OR COALESCE({$citySql}, '') LIKE :query_like_3
            OR COALESCE({$clientNameSql}, '') LIKE :query_like_4
        )";

        $sql = "SELECT
                    j.id,
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Job #', j.id)) AS title,
                    {$citySql} AS city,
                    {$clientNameSql} AS client_name,
                    {$jobDateSql} AS job_date,
                    {$statusSql} AS status
                FROM jobs j\n";
        if ($joins !== []) {
            $sql .= implode("\n", $joins) . "\n";
        }
        $sql .= 'WHERE ' . implode(' AND ', $where) . "\n";
        $sql .= 'ORDER BY j.id DESC' . "\n";
        $sql .= 'LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 100)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function purchaseSearchOptions(int $businessId, string $query = '', int $limit = 8): array
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return [];
        }

        $query = trim($query);
        $titleSql = SchemaInspector::hasColumn('purchases', 'title') ? 'p.title' : "CONCAT('Purchase #', p.id)";
        $statusSql = SchemaInspector::hasColumn('purchases', 'status') ? 'p.status' : "''";

        $joins = [];
        $clientNameSql = "''";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('purchases', 'client_id')) {
            $join = 'LEFT JOIN clients c ON c.id = p.client_id';
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('purchases', 'business_id')) {
                $join .= ' AND c.business_id = p.business_id';
            }
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $join .= ' AND c.deleted_at IS NULL';
            }
            $joins[] = $join;
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), '')";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('purchases', 'business_id') ? 'p.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('purchases', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';
        $where[] = "(
            :query = ''
            OR COALESCE({$titleSql}, '') LIKE :query_like_1
            OR CAST(p.id AS CHAR) LIKE :query_like_2
            OR COALESCE({$statusSql}, '') LIKE :query_like_3
            OR COALESCE({$clientNameSql}, '') LIKE :query_like_4
        )";

        $sql = "SELECT
                    p.id,
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Purchase #', p.id)) AS title,
                    {$statusSql} AS status,
                    {$clientNameSql} AS client_name
                FROM purchases p\n";
        if ($joins !== []) {
            $sql .= implode("\n", $joins) . "\n";
        }
        $sql .= "WHERE " . implode(' AND ', $where) . "\n";
        $sql .= "ORDER BY p.id DESC\n";
        $sql .= 'LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('purchases', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 100)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $bind
     * @return array<int, array<string, mixed>>
     */
    private static function fetchIndexRows(
        string $sql,
        int $businessId,
        string $type,
        string $fromDate,
        string $toDate,
        array $bind,
        int $limit,
        int $offset
    ): array {
        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $stmt->bindValue(':sale_type', $type);
        }
        if ($fromDate !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        foreach ($bind as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function normalizeEstateSaleScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        if ($scope === self::ESTATE_SCOPE_GENERAL || $scope === self::ESTATE_SCOPE_ESTATE_ONLY) {
            return $scope;
        }

        return self::ESTATE_SCOPE_ALL;
    }

    /**
     * @param array<int, string> $where
     */
    /**
     * @param array<int, string> $where
     */
    public static function appendScopeToWhere(array &$where, string $scope, string $alias = 's'): void
    {
        self::appendEstateSaleScopeCondition($where, $scope, $alias);
    }

    private static function appendEstateSaleScopeCondition(array &$where, string $scope, string $alias = 's'): void
    {
        if (!SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            return;
        }

        $scope = self::normalizeEstateSaleScope($scope);
        $column = $alias !== '' ? $alias . '.estate_sale_id' : 'estate_sale_id';
        if ($scope === self::ESTATE_SCOPE_GENERAL) {
            $where[] = "({$column} IS NULL OR {$column} = 0)";
            return;
        }

        if ($scope === self::ESTATE_SCOPE_ESTATE_ONLY) {
            $where[] = "{$column} IS NOT NULL AND {$column} > 0";
        }
    }

    private static function estateSaleJoinSql(): string
    {
        $join = 'LEFT JOIN estate_sales es ON es.id = s.estate_sale_id';
        if (SchemaInspector::hasColumn('estate_sales', 'deleted_at')) {
            $join .= ' AND es.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('estate_sales', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
            $join .= ' AND es.business_id = s.business_id';
        }

        return $join;
    }

    private static function estateSaleCustomerJoinSql(): string
    {
        $join = 'LEFT JOIN estate_sale_customers esc ON esc.id = s.estate_sale_customer_id';
        if (SchemaInspector::hasColumn('estate_sale_customers', 'deleted_at')) {
            $join .= ' AND esc.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('estate_sale_customers', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
            $join .= ' AND esc.business_id = s.business_id';
        }

        return $join;
    }

    private static function estateSaleTitleExpr(): string
    {
        if (SchemaInspector::hasColumn('estate_sales', 'title')) {
            return "COALESCE(NULLIF(TRIM(es.title), ''), CONCAT('Estate Sale #', es.id))";
        }

        return "CONCAT('Estate Sale #', es.id)";
    }

    private static function estateSaleCustomerNameExpr(): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
    }

    private static function saleNameExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'name') ? $alias . '.name' : "CONCAT('Sale #', {$alias}.id)";
    }

    private static function saleTypeExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'sale_type') ? $alias . '.sale_type' : "'sale'";
    }

    private static function saleDateExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'sale_date') ? $alias . '.sale_date' : 'NULL';
    }

    private static function saleFilterDateExpr(string $alias = 's'): string
    {
        if (SchemaInspector::hasColumn('sales', 'sale_date')) {
            if (SchemaInspector::hasColumn('sales', 'created_at')) {
                return "DATE(COALESCE({$alias}.sale_date, {$alias}.created_at))";
            }

            return "DATE({$alias}.sale_date)";
        }

        if (SchemaInspector::hasColumn('sales', 'created_at')) {
            return "DATE({$alias}.created_at)";
        }

        return "'0000-00-00'";
    }

    private static function saleGrossExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'gross_amount')
            ? "COALESCE({$alias}.gross_amount, 0)"
            : (SchemaInspector::hasColumn('sales', 'amount') ? "COALESCE({$alias}.amount, 0)" : '0');
    }

    private static function saleNetExpr(string $alias = 's'): string
    {
        if (SchemaInspector::hasColumn('sales', 'net_amount')) {
            return "COALESCE({$alias}.net_amount, 0)";
        }

        return self::saleGrossExpr($alias);
    }

    private static function saleNotesExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'notes') ? $alias . '.notes' : 'NULL';
    }

    private static function saleClientIdExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'client_id') ? $alias . '.client_id' : 'NULL';
    }

    private static function saleJobIdExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'job_id') ? $alias . '.job_id' : 'NULL';
    }

    private static function salePurchaseIdExpr(string $alias = 's'): string
    {
        return SchemaInspector::hasColumn('sales', 'purchase_id') ? $alias . '.purchase_id' : 'NULL';
    }

    private static function canJoinClients(): bool
    {
        return SchemaInspector::hasColumn('sales', 'client_id') && SchemaInspector::hasTable('clients');
    }

    private static function canJoinJobs(): bool
    {
        return SchemaInspector::hasColumn('sales', 'job_id') && SchemaInspector::hasTable('jobs');
    }

    private static function canJoinPurchases(): bool
    {
        return SchemaInspector::hasColumn('sales', 'purchase_id') && SchemaInspector::hasTable('purchases');
    }

    private static function clientJoinSql(): string
    {
        $join = 'LEFT JOIN clients c ON c.id = s.client_id';
        if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
            $join .= ' AND c.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
            $join .= ' AND c.business_id = s.business_id';
        }
        return $join;
    }

    private static function jobJoinSql(): string
    {
        $join = 'LEFT JOIN jobs j ON j.id = s.job_id';
        if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
            $join .= ' AND j.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('jobs', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
            $join .= ' AND j.business_id = s.business_id';
        }
        return $join;
    }

    private static function purchaseJoinSql(): string
    {
        $join = 'LEFT JOIN purchases p ON p.id = s.purchase_id';
        if (SchemaInspector::hasColumn('purchases', 'deleted_at')) {
            $join .= ' AND p.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('purchases', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
            $join .= ' AND p.business_id = s.business_id';
        }
        return $join;
    }

    private static function clientNameExpr(): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
    }

    private static function jobTitleExpr(): string
    {
        if (SchemaInspector::hasColumn('jobs', 'title')) {
            return "COALESCE(NULLIF(TRIM(j.title), ''), CONCAT('Job #', j.id))";
        }
        if (SchemaInspector::hasColumn('jobs', 'name')) {
            return "COALESCE(NULLIF(TRIM(j.name), ''), CONCAT('Job #', j.id))";
        }

        return "CONCAT('Job #', j.id)";
    }

    private static function purchaseTitleExpr(): string
    {
        if (SchemaInspector::hasColumn('purchases', 'title')) {
            return "COALESCE(NULLIF(TRIM(p.title), ''), CONCAT('Purchase #', p.id))";
        }

        return "CONCAT('Purchase #', p.id)";
    }
}
