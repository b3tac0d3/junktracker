<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Job
{
    public static function clientOptions(int $businessId, int $limit = 300): array
    {
        if (!SchemaInspector::hasTable('clients')) {
            return [];
        }

        $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $deletedWhere = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1=1';
        $statusWhere = SchemaInspector::hasColumn('clients', 'status') ? "COALESCE(c.status, 'active') = 'active'" : '1=1';
        $businessWhere = SchemaInspector::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1=1';

        $sql = "SELECT
                    c.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF({$companySql}, ''), CONCAT('Client #', c.id)) AS name
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  AND {$statusWhere}
                ORDER BY name ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexList(
        int $businessId,
        string $search = '',
        string $status = '',
        int $limit = 25,
        int $offset = 0,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $sortBy = 'date',
        string $sortDir = 'desc'
    ): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $pdo = Database::connection();
        $query = trim($search);
        $status = strtolower(trim($status));

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $jobTypeSql = SchemaInspector::hasColumn('jobs', 'job_type') ? 'j.job_type' : 'NULL';
        $activeSql = SchemaInspector::hasColumn('jobs', 'is_active') ? 'j.is_active' : '1';
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : 'NULL';
        $startSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'j.start_date' : 'NULL');
        $createdDateSql = SchemaInspector::hasColumn('jobs', 'created_at') ? 'DATE(j.created_at)' : 'NULL';
        $scheduledDateSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'DATE(j.scheduled_start_at)'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'DATE(j.start_date)' : $createdDateSql);
        $filterDateSql = "COALESCE({$scheduledDateSql}, {$createdDateSql})";
        $clientIdSql = SchemaInspector::hasColumn('jobs', 'client_id') ? 'j.client_id' : 'NULL';

        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $clientNameSql = "'—'";
        $joinSql = '';
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            if ($status === 'dispatch') {
                $where[] = "LOWER({$statusSql}) IN ('pending', 'active')";
            } else {
                $where[] = 'LOWER(' . $statusSql . ') = :status';
            }
        }
        if ($fromDate !== null && trim($fromDate) !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }
        $where[] = "(
            :query = ''
            OR {$titleSql} LIKE :query_like_1
            OR {$clientNameSql} LIKE :query_like_2
            OR COALESCE({$citySql}, '') LIKE :query_like_3
            OR CAST(j.id AS CHAR) LIKE :query_like_4
        )";

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'asc' ? 'ASC' : 'DESC';
        $sortable = [
            'date' => "{$filterDateSql} {$sortDir}, j.id {$sortDir}",
            'id' => "j.id {$sortDir}",
            'client_name' => "{$clientNameSql} {$sortDir}, j.id {$sortDir}",
        ];
        $orderBy = $sortable[$sortBy] ?? $sortable['date'];

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$jobTypeSql} AS job_type,
                    {$statusSql} AS status,
                    {$activeSql} AS is_active,
                    {$citySql} AS city,
                    {$startSql} AS scheduled_start_at,
                    {$clientIdSql} AS client_id,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy . '
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        if ($fromDate !== null && trim($fromDate) !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function filteredSummary(
        int $businessId,
        string $search = '',
        string $status = '',
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        if (!SchemaInspector::hasTable('jobs')) {
            return ['job_potential' => 0.0, 'gross_mtd' => 0.0, 'net_mtd' => 0.0, 'gross_ytd' => 0.0, 'net_ytd' => 0.0];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $createdDateSql = SchemaInspector::hasColumn('jobs', 'created_at') ? 'DATE(j.created_at)' : 'CURDATE()';
        $scheduledDateSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'DATE(j.scheduled_start_at)'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'DATE(j.start_date)' : $createdDateSql);
        $filterDateSql = "COALESCE({$scheduledDateSql}, {$createdDateSql})";
        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : "''";

        $potentialExpr = '0';
        if (SchemaInspector::hasTable('invoices') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $invoiceTotalExpr = SchemaInspector::hasColumn('invoices', 'total')
                ? 'COALESCE(i.total, 0)'
                : (SchemaInspector::hasColumn('invoices', 'subtotal')
                    ? 'COALESCE(i.subtotal, 0)'
                    : '0');
            $invoiceBusinessSql = SchemaInspector::hasColumn('invoices', 'business_id')
                ? 'AND i.business_id = j.business_id'
                : '';
            $invoiceDeletedSql = SchemaInspector::hasColumn('invoices', 'deleted_at')
                ? 'AND i.deleted_at IS NULL'
                : '';
            $invoiceTypeSql = SchemaInspector::hasColumn('invoices', 'type')
                ? "AND LOWER(COALESCE(i.type, '')) = 'estimate'"
                : '';

            $potentialExpr = "(SELECT COALESCE(SUM({$invoiceTotalExpr}), 0)
                FROM invoices i
                WHERE i.job_id = j.id
                {$invoiceBusinessSql}
                {$invoiceDeletedSql}
                {$invoiceTypeSql})";
        } elseif (SchemaInspector::hasColumn('jobs', 'job_potential')) {
            $potentialExpr = 'COALESCE(j.job_potential, 0)';
        } elseif (SchemaInspector::hasColumn('jobs', 'potential')) {
            $potentialExpr = 'COALESCE(j.potential, 0)';
        } elseif (SchemaInspector::hasColumn('jobs', 'potential_amount')) {
            $potentialExpr = 'COALESCE(j.potential_amount, 0)';
        }
        $grossExpr = SchemaInspector::hasColumn('jobs', 'gross_amount')
            ? 'COALESCE(j.gross_amount, 0)'
            : (SchemaInspector::hasColumn('jobs', 'job_gross')
                ? 'COALESCE(j.job_gross, 0)'
                : '0');
        $netExpr = SchemaInspector::hasColumn('jobs', 'net_amount')
            ? 'COALESCE(j.net_amount, 0)'
            : (SchemaInspector::hasColumn('jobs', 'job_net')
                ? 'COALESCE(j.job_net, 0)'
                : '0');

        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $joinSql = '';
        $clientNameSql = "''";
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            if ($status === 'dispatch') {
                $where[] = "LOWER({$statusSql}) IN ('pending', 'active')";
            } else {
                $where[] = 'LOWER(' . $statusSql . ') = :status';
            }
        }
        if ($fromDate !== null && trim($fromDate) !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }
        $where[] = "(
            :query = ''
            OR {$titleSql} LIKE :query_like_1
            OR {$clientNameSql} LIKE :query_like_2
            OR COALESCE({$citySql}, '') LIKE :query_like_3
            OR CAST(j.id AS CHAR) LIKE :query_like_4
        )";

        $sql = "SELECT
                    COALESCE(SUM({$potentialExpr}), 0) AS job_potential,
                    COALESCE(SUM(CASE WHEN {$filterDateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$filterDateSql} <= CURDATE() THEN {$grossExpr} ELSE 0 END), 0) AS gross_mtd,
                    COALESCE(SUM(CASE WHEN {$filterDateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$filterDateSql} <= CURDATE() THEN {$netExpr} ELSE 0 END), 0) AS net_mtd,
                    COALESCE(SUM(CASE WHEN YEAR({$filterDateSql}) = YEAR(CURDATE()) AND {$filterDateSql} <= CURDATE() THEN {$grossExpr} ELSE 0 END), 0) AS gross_ytd,
                    COALESCE(SUM(CASE WHEN YEAR({$filterDateSql}) = YEAR(CURDATE()) AND {$filterDateSql} <= CURDATE() THEN {$netExpr} ELSE 0 END), 0) AS net_ytd
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        if ($fromDate !== null && trim($fromDate) !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'job_potential' => (float) ($row['job_potential'] ?? 0),
            'gross_mtd' => (float) ($row['gross_mtd'] ?? 0),
            'net_mtd' => (float) ($row['net_mtd'] ?? 0),
            'gross_ytd' => (float) ($row['gross_ytd'] ?? 0),
            'net_ytd' => (float) ($row['net_ytd'] ?? 0),
        ];
    }

    public static function indexCount(
        int $businessId,
        string $search = '',
        string $status = '',
        ?string $fromDate = null,
        ?string $toDate = null
    ): int
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $jobTypeSql = SchemaInspector::hasColumn('jobs', 'job_type') ? 'j.job_type' : 'NULL';
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : 'NULL';
        $createdDateSql = SchemaInspector::hasColumn('jobs', 'created_at') ? 'DATE(j.created_at)' : 'NULL';
        $scheduledDateSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'DATE(j.scheduled_start_at)'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'DATE(j.start_date)' : $createdDateSql);
        $filterDateSql = "COALESCE({$scheduledDateSql}, {$createdDateSql})";

        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $clientNameSql = "'—'";
        $joinSql = '';
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            if ($status === 'dispatch') {
                $where[] = "LOWER({$statusSql}) IN ('pending', 'active')";
            } else {
                $where[] = 'LOWER(' . $statusSql . ') = :status';
            }
        }
        if ($fromDate !== null && trim($fromDate) !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }
        $where[] = "(
            :query = ''
            OR {$titleSql} LIKE :query_like_1
            OR {$clientNameSql} LIKE :query_like_2
            OR COALESCE({$citySql}, '') LIKE :query_like_3
            OR CAST(j.id AS CHAR) LIKE :query_like_4
        )";

        $sql = "SELECT COUNT(*)
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        if ($fromDate !== null && trim($fromDate) !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForBusiness(int $businessId, int $jobId): ?array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return null;
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $jobTypeSql = SchemaInspector::hasColumn('jobs', 'job_type') ? 'j.job_type' : 'NULL';
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : 'NULL';
        $stateSql = SchemaInspector::hasColumn('jobs', 'state') ? 'j.state' : 'NULL';
        $postalSql = SchemaInspector::hasColumn('jobs', 'postal_code') ? 'j.postal_code' : 'NULL';
        $address1Sql = SchemaInspector::hasColumn('jobs', 'address_line1') ? 'j.address_line1' : 'NULL';
        $address2Sql = SchemaInspector::hasColumn('jobs', 'address_line2') ? 'j.address_line2' : 'NULL';
        $scheduledStartSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'j.start_date' : 'NULL');
        $scheduledEndSql = SchemaInspector::hasColumn('jobs', 'scheduled_end_at')
            ? 'j.scheduled_end_at'
            : (SchemaInspector::hasColumn('jobs', 'end_date') ? 'j.end_date' : 'NULL');
        $actualStartSql = SchemaInspector::hasColumn('jobs', 'actual_start_at') ? 'j.actual_start_at' : 'NULL';
        $actualEndSql = SchemaInspector::hasColumn('jobs', 'actual_end_at') ? 'j.actual_end_at' : 'NULL';
        $notesSql = SchemaInspector::hasColumn('jobs', 'notes') ? 'j.notes' : 'NULL';
        $clientIdSql = SchemaInspector::hasColumn('jobs', 'client_id') ? 'j.client_id' : 'NULL';
        $activeSql = SchemaInspector::hasColumn('jobs', 'is_active') ? 'j.is_active' : '1';

        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $clientNameSql = "'—'";
        $joinSql = '';
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        $where[] = 'j.id = :job_id';

        if (!isset($jobTypeSql)) {
            $jobTypeSql = 'NULL';
        }

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$jobTypeSql} AS job_type,
                    {$statusSql} AS status,
                    {$activeSql} AS is_active,
                    {$address1Sql} AS address_line1,
                    {$address2Sql} AS address_line2,
                    {$citySql} AS city,
                    {$stateSql} AS state,
                    {$postalSql} AS postal_code,
                    {$scheduledStartSql} AS scheduled_start_at,
                    {$scheduledEndSql} AS scheduled_end_at,
                    {$actualStartSql} AS actual_start_at,
                    {$actualEndSql} AS actual_end_at,
                    {$notesSql} AS notes,
                    {$clientIdSql} AS client_id,
                    {$clientNameSql} AS client_name
                FROM jobs j
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function financialSummary(int $businessId, int $jobId): array
    {
        $gross = 0.0;
        $expenses = 0.0;
        $payments = 0.0;
        $laborCost = 0.0;
        $adjustments = 0.0;
        $salesGross = 0.0;
        $salesNet = 0.0;

        if (SchemaInspector::hasTable('invoices') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $totalExpr = SchemaInspector::hasColumn('invoices', 'total')
                ? 'i.total'
                : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');

            $where = [];
            $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
            $where[] = 'i.job_id = :job_id';
            $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
            if (SchemaInspector::hasColumn('invoices', 'status')) {
                $where[] = "i.status <> 'cancelled'";
            }
            if (SchemaInspector::hasColumn('invoices', 'type')) {
                $where[] = "(LOWER(i.type) = 'invoice' OR i.type IS NULL OR TRIM(i.type) = '')";
            }

            $sql = 'SELECT COALESCE(SUM(' . $totalExpr . '), 0)
                    FROM invoices i
                    WHERE ' . implode(' AND ', $where);

            $stmt = Database::connection()->prepare($sql);
            $params = ['job_id' => $jobId];
            if (SchemaInspector::hasColumn('invoices', 'business_id')) {
                $params['business_id'] = $businessId;
            }
            $stmt->execute($params);
            $gross = (float) $stmt->fetchColumn();
        }

        if (SchemaInspector::hasTable('payments') && SchemaInspector::hasColumn('payments', 'invoice_id') && SchemaInspector::hasColumn('payments', 'amount')) {
            $invoiceBusiness = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :invoice_business_id' : '1=1';
            $paymentBusiness = SchemaInspector::hasColumn('payments', 'business_id') ? 'p.business_id = :payment_business_id' : '1=1';
            $invoiceDeleted = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
            $paymentDeleted = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';
            $invoiceTypeWhere = SchemaInspector::hasColumn('invoices', 'type')
                ? "AND (LOWER(i.type) = 'invoice' OR i.type IS NULL OR TRIM(i.type) = '')"
                : '';

            $sql = 'SELECT COALESCE(SUM(p.amount), 0)
                    FROM payments p
                    INNER JOIN invoices i ON i.id = p.invoice_id
                    WHERE i.job_id = :job_id
                      AND ' . $invoiceBusiness . '
                      AND ' . $paymentBusiness . '
                      AND ' . $invoiceDeleted . '
                      AND ' . $paymentDeleted . '
                      ' . $invoiceTypeWhere;

            $stmt = Database::connection()->prepare($sql);
            $params = ['job_id' => $jobId];
            if (SchemaInspector::hasColumn('invoices', 'business_id')) {
                $params['invoice_business_id'] = $businessId;
            }
            if (SchemaInspector::hasColumn('payments', 'business_id')) {
                $params['payment_business_id'] = $businessId;
            }
            $stmt->execute($params);
            $payments = (float) $stmt->fetchColumn();
        }

        if (SchemaInspector::hasTable('expenses') && SchemaInspector::hasColumn('expenses', 'amount') && SchemaInspector::hasColumn('expenses', 'job_id')) {
            $businessWhere = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
            $deletedWhere = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
            $sql = 'SELECT COALESCE(SUM(e.amount), 0)
                    FROM expenses e
                    WHERE ' . $businessWhere . '
                      AND e.job_id = :job_id
                      AND ' . $deletedWhere;
            $stmt = Database::connection()->prepare($sql);
            $params = ['job_id' => $jobId];
            if (SchemaInspector::hasColumn('expenses', 'business_id')) {
                $params['business_id'] = $businessId;
            }
            $stmt->execute($params);
            $expenses = (float) $stmt->fetchColumn();
        }

        $invoiceGross = $gross;
        $salesTotals = Sale::salesTotalsByJob($businessId, $jobId);
        $salesGross = (float) ($salesTotals['gross'] ?? 0);
        $salesNet = (float) ($salesTotals['net'] ?? 0);

        $laborCost = self::laborCostByJob($businessId, $jobId);
        $adjustments = self::adjustmentTotalByJob($businessId, $jobId);
        $jobOnlyGross = $invoiceGross;
        $grossWithSales = $jobOnlyGross + $salesGross;
        $operatingGross = $grossWithSales - $laborCost + $adjustments;
        $jobOnlyNet = ($jobOnlyGross - $laborCost + $adjustments) - $expenses;
        $totalNet = $jobOnlyNet + $salesNet;

        return [
            'raw_gross' => $invoiceGross,
            'invoice_gross' => $invoiceGross,
            'job_gross' => $jobOnlyGross,
            'sales_gross' => $salesGross,
            'sales_net' => $salesNet,
            'operating_gross' => $operatingGross,
            'job_net' => $jobOnlyNet,
            'total_gross' => $grossWithSales,
            'total_net' => $totalNet,
            'gross' => $grossWithSales,
            'payments' => $payments,
            'expenses' => $expenses,
            'labor' => $laborCost,
            'labor_cost' => $laborCost,
            'adjustments' => $adjustments,
            'net' => $totalNet,
            'balance' => $invoiceGross - $payments,
        ];
    }

    public static function timeSummary(int $businessId, int $jobId): array
    {
        if (!SchemaInspector::hasTable('employee_time_entries')) {
            return [
                'entries' => 0,
                'open_entries' => 0,
                'hours' => 0.0,
            ];
        }

        $businessWhere = SchemaInspector::hasColumn('employee_time_entries', 'business_id')
            ? 't.business_id = :business_id'
            : '1=1';
        $deletedWhere = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')
            ? 't.deleted_at IS NULL'
            : '1=1';
        $durationExpr = SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes')
            ? 'COALESCE(t.duration_minutes, 0)'
            : '0';
        $clockOutExpr = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';

        $sql = "SELECT
                    COUNT(*) AS total_entries,
                    SUM(CASE WHEN {$clockOutExpr} IS NULL THEN 1 ELSE 0 END) AS open_entries,
                    COALESCE(SUM({$durationExpr}), 0) AS total_minutes
                FROM employee_time_entries t
                WHERE {$businessWhere}
                  AND t.job_id = :job_id
                  AND {$deletedWhere}";

        $stmt = Database::connection()->prepare($sql);
        $params = ['job_id' => $jobId];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'entries' => (int) ($row['total_entries'] ?? 0),
            'open_entries' => (int) ($row['open_entries'] ?? 0),
            'hours' => round(((int) ($row['total_minutes'] ?? 0)) / 60, 2),
        ];
    }

    public static function timeLogsByJob(int $businessId, int $jobId, int $limit = 200): array
    {
        if (!SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            return [];
        }
        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return [];
        }

        $clockInSql = 't.clock_in_at';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $durationExpr = SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes')
            ? "CASE
                    WHEN t.duration_minutes IS NOT NULL AND t.duration_minutes > 0 THEN t.duration_minutes
                    WHEN {$clockOutSql} IS NOT NULL THEN GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, {$clockOutSql}), 0)
                    ELSE GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, NOW()), 0)
                END"
            : "CASE
                    WHEN {$clockOutSql} IS NOT NULL THEN GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, {$clockOutSql}), 0)
                    ELSE GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, NOW()), 0)
                END";

        $joinSql = '';
        $employeeNameSql = "CONCAT('Employee #', t.employee_id)";
        $hourlyRateSql = '0';
        if (SchemaInspector::hasTable('employees') && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $joinSql .= ' LEFT JOIN employees e ON e.id = t.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
                $joinSql .= ' AND e.business_id = t.business_id';
            }

            $employeeNameParts = ['e.first_name', 'e.last_name'];
            if (SchemaInspector::hasColumn('employees', 'suffix')) {
                $employeeNameParts[] = 'e.suffix';
            }
            $employeeBaseNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', t.employee_id))";
            $employeeNameSql = $employeeBaseNameSql;
            $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

            if (SchemaInspector::hasTable('users')) {
                $joinSql .= ' LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL';
                $linkedUserNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
                $employeeNameSql = "COALESCE(NULLIF({$linkedUserNameSql}, ''), {$employeeBaseNameSql})";
            }
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = 't.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    t.id,
                    t.employee_id,
                    {$employeeNameSql} AS employee_name,
                    {$clockInSql} AS clock_in_at,
                    {$clockOutSql} AS clock_out_at,
                    {$durationExpr} AS duration_minutes,
                    {$hourlyRateSql} AS hourly_rate,
                    ROUND(({$durationExpr} / 60) * {$hourlyRateSql}, 2) AS labor_cost,
                    t.created_at
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$clockInSql} DESC, t.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 2000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        $hasJobType = SchemaInspector::hasColumn('jobs', 'job_type');
        $sql = 'INSERT INTO jobs (
                    business_id, client_id, title, ' . ($hasJobType ? 'job_type, ' : '') . 'status,
                    scheduled_start_at, scheduled_end_at, actual_start_at, actual_end_at,
                    address_line1, address_line2, city, state, postal_code, notes,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :client_id, :title, ' . ($hasJobType ? ':job_type, ' : '') . ':status,
                    :scheduled_start_at, :scheduled_end_at, :actual_start_at, :actual_end_at,
                    :address_line1, :address_line2, :city, :state, :postal_code, :notes,
                    :created_by, :updated_by, NOW(), NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'business_id' => $businessId,
            'client_id' => (int) ($data['client_id'] ?? 0),
            'title' => trim((string) ($data['title'] ?? '')),
            'status' => strtolower(trim((string) ($data['status'] ?? 'pending'))),
            'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
            'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
            'actual_start_at' => $data['actual_start_at'] ?? null,
            'actual_end_at' => $data['actual_end_at'] ?? null,
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ];
        if ($hasJobType) {
            $params['job_type'] = trim((string) ($data['job_type'] ?? ''));
        }
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $jobId, array $data, int $actorUserId): bool
    {
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $hasJobType = SchemaInspector::hasColumn('jobs', 'job_type');

        $sql = 'UPDATE jobs
                SET client_id = :client_id,
                    title = :title,
                    ' . ($hasJobType ? 'job_type = :job_type,' : '') . '
                    status = :status,
                    scheduled_start_at = :scheduled_start_at,
                    scheduled_end_at = :scheduled_end_at,
                    actual_start_at = :actual_start_at,
                    actual_end_at = :actual_end_at,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    notes = :notes,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :job_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'client_id' => (int) ($data['client_id'] ?? 0),
            'title' => trim((string) ($data['title'] ?? '')),
            'status' => strtolower(trim((string) ($data['status'] ?? 'pending'))),
            'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
            'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
            'actual_start_at' => $data['actual_start_at'] ?? null,
            'actual_end_at' => $data['actual_end_at'] ?? null,
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'updated_by' => $actorUserId,
            'job_id' => $jobId,
            'business_id' => $businessId,
        ];
        if ($hasJobType) {
            $params['job_type'] = trim((string) ($data['job_type'] ?? ''));
        }

        return $stmt->execute($params);
    }

    public static function deactivate(int $businessId, int $jobId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return false;
        }

        $sets = [];
        if (SchemaInspector::hasColumn('jobs', 'is_active')) {
            $sets[] = 'is_active = 0';
        }
        if (SchemaInspector::hasColumn('jobs', 'status')) {
            $sets[] = "status = 'inactive'";
        }
        if (SchemaInspector::hasColumn('jobs', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
        }
        if (SchemaInspector::hasColumn('jobs', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($sets === []) {
            return false;
        }

        $businessWhere = SchemaInspector::hasColumn('jobs', 'business_id') ? 'AND business_id = :business_id' : '';
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = 'UPDATE jobs
                SET ' . implode(', ', $sets) . '
                WHERE id = :job_id
                  ' . $businessWhere . '
                  ' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        $params = ['job_id' => $jobId];
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('jobs', 'updated_by')) {
            $params['updated_by'] = $actorUserId;
        }

        return $stmt->execute($params);
    }

    public static function expensesByJob(int $businessId, int $jobId, int $limit = 200): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('expenses')) {
            return [];
        }
        if (!SchemaInspector::hasColumn('expenses', 'job_id')) {
            return [];
        }

        $dateSql = SchemaInspector::hasColumn('expenses', 'expense_date')
            ? 'e.expense_date'
            : (SchemaInspector::hasColumn('expenses', 'date') ? 'e.date' : 'NULL');
        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'e.amount' : '0';
        $nameSql = SchemaInspector::hasColumn('expenses', 'name') ? 'e.name' : "CONCAT('Expense #', e.id)";
        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'e.category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'e.expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'e.type' : 'NULL'));
        $paymentMethodSql = SchemaInspector::hasColumn('expenses', 'payment_method') ? 'e.payment_method' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $createdSql = SchemaInspector::hasColumn('expenses', 'created_at') ? 'e.created_at' : 'NULL';

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = 'e.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    e.id,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
                    {$paymentMethodSql} AS payment_method,
                    {$noteSql} AS note,
                    {$createdSql} AS created_at
                FROM expenses e
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE({$dateSql}, {$createdSql}) DESC, e.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 2000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function createExpense(int $businessId, int $jobId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'amount')) {
            return 0;
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $append = static function (string $column, string $placeholder, mixed $value) use (&$columns, &$placeholders, &$params): void {
            $columns[] = $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        };

        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $append('business_id', ':business_id', $businessId);
        }
        if (SchemaInspector::hasColumn('expenses', 'job_id')) {
            $append('job_id', ':job_id', $jobId);
        }
        if (SchemaInspector::hasColumn('expenses', 'expense_date')) {
            $append('expense_date', ':expense_date', $data['expense_date'] ?? null);
        } elseif (SchemaInspector::hasColumn('expenses', 'date')) {
            $append('date', ':expense_date', $data['expense_date'] ?? null);
        }
        $append('amount', ':amount', (float) ($data['amount'] ?? 0));

        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $append('category', ':category', trim((string) ($data['category'] ?? '')));
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $append('expense_type', ':category', trim((string) ($data['category'] ?? '')));
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $append('type', ':category', trim((string) ($data['category'] ?? '')));
        }
        if (SchemaInspector::hasColumn('expenses', 'name')) {
            $append('name', ':name', trim((string) ($data['name'] ?? '')));
        }
        if (SchemaInspector::hasColumn('expenses', 'payment_method')) {
            $append('payment_method', ':payment_method', trim((string) ($data['payment_method'] ?? '')));
        }

        if (SchemaInspector::hasColumn('expenses', 'note')) {
            $append('note', ':note', trim((string) ($data['note'] ?? '')));
        } elseif (SchemaInspector::hasColumn('expenses', 'notes')) {
            $append('notes', ':note', trim((string) ($data['note'] ?? '')));
        }

        if (SchemaInspector::hasColumn('expenses', 'created_by')) {
            $append('created_by', ':created_by', $actorUserId > 0 ? $actorUserId : null);
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $append('updated_by', ':updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        if (SchemaInspector::hasColumn('expenses', 'created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO expenses (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findExpenseForJob(int $businessId, int $jobId, int $expenseId): ?array
    {
        if ($expenseId <= 0 || $jobId <= 0 || !SchemaInspector::hasTable('expenses')) {
            return null;
        }
        if (!SchemaInspector::hasColumn('expenses', 'job_id')) {
            return null;
        }

        $dateSql = SchemaInspector::hasColumn('expenses', 'expense_date')
            ? 'e.expense_date'
            : (SchemaInspector::hasColumn('expenses', 'date') ? 'e.date' : 'NULL');
        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'e.amount' : '0';
        $nameSql = SchemaInspector::hasColumn('expenses', 'name') ? 'e.name' : "CONCAT('Expense #', e.id)";
        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'e.category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'e.expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'e.type' : 'NULL'));
        $paymentMethodSql = SchemaInspector::hasColumn('expenses', 'payment_method') ? 'e.payment_method' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $referenceSql = SchemaInspector::hasColumn('expenses', 'reference_number') ? 'e.reference_number' : 'NULL';
        $createdSql = SchemaInspector::hasColumn('expenses', 'created_at') ? 'e.created_at' : 'NULL';

        $where = [];
        $where[] = 'e.id = :expense_id';
        $where[] = 'e.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    e.id,
                    e.job_id,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
                    {$paymentMethodSql} AS payment_method,
                    {$referenceSql} AS reference_number,
                    {$noteSql} AS note,
                    {$createdSql} AS created_at
                FROM expenses e
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'expense_id' => $expenseId,
            'job_id' => $jobId,
        ];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function softDeleteExpense(int $businessId, int $jobId, int $expenseId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        if (SchemaInspector::hasColumn('expenses', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
        }

        $whereParts = ['id = :expense_id', 'job_id = :job_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
        }

        $sql = 'UPDATE expenses
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);

        $params = [
            'expense_id' => $expenseId,
            'job_id' => $jobId,
        ];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('expenses', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function updateExpense(int $businessId, int $jobId, int $expenseId, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('expenses')) {
            return false;
        }

        $setParts = [];
        $params = [
            'expense_id' => $expenseId,
            'job_id' => $jobId,
        ];

        if (SchemaInspector::hasColumn('expenses', 'expense_date')) {
            $setParts[] = 'expense_date = :expense_date';
            $params['expense_date'] = $data['expense_date'] ?? null;
        } elseif (SchemaInspector::hasColumn('expenses', 'date')) {
            $setParts[] = '`date` = :expense_date';
            $params['expense_date'] = $data['expense_date'] ?? null;
        }

        if (SchemaInspector::hasColumn('expenses', 'amount')) {
            $setParts[] = 'amount = :amount';
            $params['amount'] = (float) ($data['amount'] ?? 0);
        }

        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $setParts[] = 'category = :category';
            $params['category'] = trim((string) ($data['category'] ?? ''));
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $setParts[] = 'expense_type = :category';
            $params['category'] = trim((string) ($data['category'] ?? ''));
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $setParts[] = '`type` = :category';
            $params['category'] = trim((string) ($data['category'] ?? ''));
        }
        if (SchemaInspector::hasColumn('expenses', 'name')) {
            $setParts[] = 'name = :name';
            $params['name'] = trim((string) ($data['name'] ?? ''));
        }
        if (SchemaInspector::hasColumn('expenses', 'payment_method')) {
            $setParts[] = 'payment_method = :payment_method';
            $params['payment_method'] = trim((string) ($data['payment_method'] ?? ''));
        }

        if (SchemaInspector::hasColumn('expenses', 'note')) {
            $setParts[] = 'note = :note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        } elseif (SchemaInspector::hasColumn('expenses', 'notes')) {
            $setParts[] = 'notes = :note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        }

        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }

        if ($setParts === []) {
            return false;
        }

        $whereParts = ['id = :expense_id', 'job_id = :job_id'];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            $whereParts[] = 'deleted_at IS NULL';
        }

        $sql = 'UPDATE expenses
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function adjustmentsByJob(int $businessId, int $jobId, int $limit = 500): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('job_adjustments')) {
            return [];
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('job_adjustments', 'business_id') ? 'a.business_id = :business_id' : '1=1';
        $where[] = 'a.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('job_adjustments', 'deleted_at') ? 'a.deleted_at IS NULL' : '1=1';

        $dateSql = SchemaInspector::hasColumn('job_adjustments', 'adjustment_date') ? 'a.adjustment_date' : 'a.created_at';
        $noteSql = SchemaInspector::hasColumn('job_adjustments', 'note') ? 'a.note' : 'NULL';
        $amountSql = SchemaInspector::hasColumn('job_adjustments', 'amount') ? 'a.amount' : '0';
        $nameSql = SchemaInspector::hasColumn('job_adjustments', 'name') ? 'a.name' : 'NULL';

        $sql = "SELECT
                    a.id,
                    a.job_id,
                    {$nameSql} AS name,
                    {$dateSql} AS adjustment_date,
                    {$amountSql} AS amount,
                    {$noteSql} AS note,
                    a.created_at
                FROM job_adjustments a
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE({$dateSql}, a.created_at) DESC, a.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 2000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function createAdjustment(int $businessId, int $jobId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('job_adjustments') || !SchemaInspector::hasColumn('job_adjustments', 'amount')) {
            return 0;
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $append = static function (string $column, string $placeholder, mixed $value) use (&$columns, &$placeholders, &$params): void {
            $columns[] = $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        };

        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $append('business_id', ':business_id', $businessId);
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'job_id')) {
            $append('job_id', ':job_id', $jobId);
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'adjustment_date')) {
            $append('adjustment_date', ':adjustment_date', $data['adjustment_date'] ?? null);
        }
        $append('amount', ':amount', (float) ($data['amount'] ?? 0));
        if (SchemaInspector::hasColumn('job_adjustments', 'name')) {
            $append('name', ':name', trim((string) ($data['name'] ?? '')));
        }

        if (SchemaInspector::hasColumn('job_adjustments', 'note')) {
            $append('note', ':note', trim((string) ($data['note'] ?? '')));
        }

        if (SchemaInspector::hasColumn('job_adjustments', 'created_by')) {
            $append('created_by', ':created_by', $actorUserId > 0 ? $actorUserId : null);
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'updated_by')) {
            $append('updated_by', ':updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        if (SchemaInspector::hasColumn('job_adjustments', 'created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO job_adjustments (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findAdjustmentForJob(int $businessId, int $jobId, int $adjustmentId): ?array
    {
        if ($adjustmentId <= 0 || $jobId <= 0 || !SchemaInspector::hasTable('job_adjustments')) {
            return null;
        }

        $where = [];
        $where[] = 'a.id = :adjustment_id';
        $where[] = 'a.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('job_adjustments', 'business_id') ? 'a.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('job_adjustments', 'deleted_at') ? 'a.deleted_at IS NULL' : '1=1';

        $dateSql = SchemaInspector::hasColumn('job_adjustments', 'adjustment_date') ? 'a.adjustment_date' : 'a.created_at';
        $noteSql = SchemaInspector::hasColumn('job_adjustments', 'note') ? 'a.note' : 'NULL';
        $amountSql = SchemaInspector::hasColumn('job_adjustments', 'amount') ? 'a.amount' : '0';
        $nameSql = SchemaInspector::hasColumn('job_adjustments', 'name') ? 'a.name' : 'NULL';

        $sql = "SELECT
                    a.id,
                    a.job_id,
                    {$nameSql} AS name,
                    {$dateSql} AS adjustment_date,
                    {$amountSql} AS amount,
                    {$noteSql} AS note,
                    a.created_at
                FROM job_adjustments a
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'adjustment_id' => $adjustmentId,
            'job_id' => $jobId,
        ];
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function updateAdjustment(int $businessId, int $jobId, int $adjustmentId, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('job_adjustments')) {
            return false;
        }

        $setParts = [];
        $params = [
            'adjustment_id' => $adjustmentId,
            'job_id' => $jobId,
        ];

        if (SchemaInspector::hasColumn('job_adjustments', 'adjustment_date')) {
            $setParts[] = 'adjustment_date = :adjustment_date';
            $params['adjustment_date'] = $data['adjustment_date'] ?? null;
        }

        if (SchemaInspector::hasColumn('job_adjustments', 'amount')) {
            $setParts[] = 'amount = :amount';
            $params['amount'] = (float) ($data['amount'] ?? 0);
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'name')) {
            $setParts[] = 'name = :name';
            $params['name'] = trim((string) ($data['name'] ?? ''));
        }

        if (SchemaInspector::hasColumn('job_adjustments', 'note')) {
            $setParts[] = 'note = :note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        }

        if (SchemaInspector::hasColumn('job_adjustments', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }

        if ($setParts === []) {
            return false;
        }

        $whereParts = ['id = :adjustment_id', 'job_id = :job_id'];
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'deleted_at')) {
            $whereParts[] = 'deleted_at IS NULL';
        }

        $sql = 'UPDATE job_adjustments
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function softDeleteAdjustment(int $businessId, int $jobId, int $adjustmentId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('job_adjustments') || !SchemaInspector::hasColumn('job_adjustments', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        if (SchemaInspector::hasColumn('job_adjustments', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
        }

        $whereParts = ['id = :adjustment_id', 'job_id = :job_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
        }

        $sql = 'UPDATE job_adjustments
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);

        $params = [
            'adjustment_id' => $adjustmentId,
            'job_id' => $jobId,
        ];
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'updated_by')) {
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function laborCostByJob(int $businessId, int $jobId): float
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            return 0.0;
        }
        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return 0.0;
        }

        $clockInSql = 't.clock_in_at';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $durationExpr = SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes')
            ? "CASE
                    WHEN t.duration_minutes IS NOT NULL AND t.duration_minutes > 0 THEN t.duration_minutes
                    WHEN {$clockOutSql} IS NOT NULL THEN GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, {$clockOutSql}), 0)
                    ELSE GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, NOW()), 0)
                END"
            : "CASE
                    WHEN {$clockOutSql} IS NOT NULL THEN GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, {$clockOutSql}), 0)
                    ELSE GREATEST(TIMESTAMPDIFF(MINUTE, {$clockInSql}, NOW()), 0)
                END";

        $joinSql = '';
        $hourlyRateSql = '0';
        if (SchemaInspector::hasTable('employees')) {
            $joinSql .= ' LEFT JOIN employees e ON e.id = t.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
                $joinSql .= ' AND e.business_id = t.business_id';
            }
            $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = 't.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT COALESCE(SUM(({$durationExpr} / 60) * {$hourlyRateSql}), 0)
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = ['job_id' => $jobId];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    public static function adjustmentTotalByJob(int $businessId, int $jobId): float
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('job_adjustments') || !SchemaInspector::hasColumn('job_adjustments', 'amount')) {
            return 0.0;
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('job_adjustments', 'business_id') ? 'a.business_id = :business_id' : '1=1';
        $where[] = 'a.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('job_adjustments', 'deleted_at') ? 'a.deleted_at IS NULL' : '1=1';

        $sql = 'SELECT COALESCE(SUM(a.amount), 0)
                FROM job_adjustments a
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = ['job_id' => $jobId];
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    public static function assignedEmployees(int $businessId, int $jobId): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('job_employee_assignments') || !SchemaInspector::hasTable('employees')) {
            return [];
        }

        $employeeNameParts = ['e.first_name', 'e.last_name'];
        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $employeeNameParts[] = 'e.suffix';
        }
        $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
        $userNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
        $statusSql = SchemaInspector::hasColumn('employees', 'status') ? 'e.status' : "'active'";

        $sql = "SELECT
                    ja.id AS assignment_id,
                    e.id AS employee_id,
                    e.user_id,
                    {$statusSql} AS employee_status,
                    {$employeeNameSql} AS employee_name,
                    {$userNameSql} AS linked_user_name,
                    u.email AS linked_user_email,
                    COALESCE(NULLIF({$userNameSql}, ''), {$employeeNameSql}) AS display_name
                FROM job_employee_assignments ja
                INNER JOIN employees e ON e.id = ja.employee_id
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE ja.business_id = :business_id
                  AND ja.job_id = :job_id
                  AND ja.deleted_at IS NULL
                  AND e.business_id = :employee_business_id
                  AND e.deleted_at IS NULL
                ORDER BY
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0 THEN 0 ELSE 1 END ASC,
                    display_name ASC,
                    e.id ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findAssignedEmployee(int $businessId, int $jobId, int $employeeId): ?array
    {
        if ($jobId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('job_employee_assignments') || !SchemaInspector::hasTable('employees')) {
            return null;
        }

        $employeeNameParts = ['e.first_name', 'e.last_name'];
        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $employeeNameParts[] = 'e.suffix';
        }
        $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
        $userNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
        $statusSql = SchemaInspector::hasColumn('employees', 'status') ? 'e.status' : "'active'";

        $sql = "SELECT
                    ja.id AS assignment_id,
                    e.id AS employee_id,
                    e.user_id,
                    {$statusSql} AS employee_status,
                    {$employeeNameSql} AS employee_name,
                    {$userNameSql} AS linked_user_name,
                    u.email AS linked_user_email,
                    COALESCE(NULLIF({$userNameSql}, ''), {$employeeNameSql}) AS display_name
                FROM job_employee_assignments ja
                INNER JOIN employees e ON e.id = ja.employee_id
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE ja.business_id = :business_id
                  AND ja.job_id = :job_id
                  AND ja.employee_id = :employee_id
                  AND ja.deleted_at IS NULL
                  AND e.business_id = :employee_business_id
                  AND e.deleted_at IS NULL
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function assignEmployee(int $businessId, int $jobId, int $employeeId, int $actorUserId): bool
    {
        if ($jobId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('job_employee_assignments')) {
            return false;
        }

        $employee = Employee::findForBusiness($businessId, $employeeId);
        if ($employee === null) {
            return false;
        }

        $status = strtolower(trim((string) ($employee['status'] ?? 'active')));
        if ($status === 'inactive') {
            return false;
        }

        $existingSql = "SELECT id, deleted_at
                        FROM job_employee_assignments
                        WHERE business_id = :business_id
                          AND job_id = :job_id
                          AND employee_id = :employee_id
                        ORDER BY id DESC
                        LIMIT 1";
        $existingStmt = Database::connection()->prepare($existingSql);
        $existingStmt->execute([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'employee_id' => $employeeId,
        ]);
        $existing = $existingStmt->fetch();
        if (is_array($existing)) {
            $assignmentId = (int) ($existing['id'] ?? 0);
            $deletedAt = trim((string) ($existing['deleted_at'] ?? ''));
            if ($assignmentId > 0 && $deletedAt === '') {
                return true;
            }

            if ($assignmentId > 0) {
                $restoreSql = 'UPDATE job_employee_assignments
                               SET deleted_at = NULL,
                                   deleted_by = NULL,
                                   updated_by = :updated_by,
                                   updated_at = NOW()
                               WHERE id = :id';
                $restoreStmt = Database::connection()->prepare($restoreSql);
                $restoreStmt->execute([
                    'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                    'id' => $assignmentId,
                ]);
                return true;
            }
        }

        $sql = 'INSERT INTO job_employee_assignments (
                    business_id, job_id, employee_id, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :job_id, :employee_id, :created_by, :updated_by, NOW(), NOW()
                )';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'employee_id' => $employeeId,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return true;
    }

    public static function employeeSearchOptions(int $businessId, int $jobId, string $query = '', int $limit = 10): array
    {
        if (!SchemaInspector::hasTable('employees')) {
            return [];
        }

        $query = trim($query);
        $employeeNameParts = ['e.first_name', 'e.last_name'];
        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $employeeNameParts[] = 'e.suffix';
        }
        $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
        $userNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";

        $sql = "SELECT
                    e.id,
                    {$employeeNameSql} AS employee_name,
                    {$userNameSql} AS linked_user_name,
                    u.email AS linked_user_email,
                    COALESCE(NULLIF({$userNameSql}, ''), {$employeeNameSql}) AS name
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE e.business_id = :business_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.status, 'active') = 'active'
                  AND (
                    :query = ''
                    OR {$employeeNameSql} LIKE :query_like_1
                    OR {$userNameSql} LIKE :query_like_2
                    OR COALESCE(e.email, '') LIKE :query_like_3
                    OR COALESCE(e.phone, '') LIKE :query_like_4
                    OR CAST(e.id AS CHAR) LIKE :query_like_5
                  )
                  AND NOT EXISTS (
                    SELECT 1
                    FROM job_employee_assignments ja
                    WHERE ja.business_id = :assignment_business_id
                      AND ja.job_id = :job_id
                      AND ja.employee_id = e.id
                      AND ja.deleted_at IS NULL
                  )
                ORDER BY
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0 THEN 0 ELSE 1 END ASC,
                    name ASC,
                    e.id ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':assignment_business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 100)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
