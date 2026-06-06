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
        string $jobType = '',
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
        $jobType = strtolower(trim($jobType));

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
        if ($jobType !== '' && $jobTypeSql !== 'NULL') {
            $where[] = 'LOWER(COALESCE(' . $jobTypeSql . ", '')) = :job_type";
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
        if ($jobType !== '' && $jobTypeSql !== 'NULL') {
            $stmt->bindValue(':job_type', $jobType);
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
        if (!is_array($rows)) {
            return [];
        }

        return self::enrichIndexRowsWithBilling($rows, $businessId);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function enrichIndexRowsWithBilling(array $rows, int $businessId): array
    {
        if ($rows === [] || !SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'job_id')) {
            return $rows;
        }

        $jobIds = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $jobId = (int) ($row['id'] ?? 0);
            if ($jobId > 0) {
                $jobIds[$jobId] = $jobId;
            }
        }
        if ($jobIds === []) {
            return $rows;
        }

        $billingByJob = self::indexBillingByJobIds($businessId, array_values($jobIds));
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $jobId = (int) ($row['id'] ?? 0);
            $billing = $billingByJob[$jobId] ?? null;
            if (!is_array($billing)) {
                $row['billing_total'] = null;
                $row['billing_price_state'] = '';
                $row['billing_doc_id'] = 0;
                continue;
            }
            $row['billing_total'] = (float) ($billing['total'] ?? 0);
            $row['billing_price_state'] = self::billingListPriceState($billing);
            $row['billing_doc_id'] = (int) ($billing['id'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<int> $jobIds
     * @return array<int, array<string, mixed>>
     */
    public static function indexBillingByJobIds(int $businessId, array $jobIds): array
    {
        $jobIds = array_values(array_unique(array_filter(array_map(static fn ($id): int => (int) $id, $jobIds), static fn (int $id): bool => $id > 0)));
        if ($businessId <= 0 || $jobIds === [] || !SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'job_id')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($jobIds), '?'));
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'COALESCE(i.total, 0)'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
        $dueDateSql = SchemaInspector::hasColumn('invoices', 'due_date') ? 'i.due_date' : 'NULL';
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'i.status' : "''";
        $typeSql = SchemaInspector::hasColumn('invoices', 'type')
            ? "LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice'))"
            : "'invoice'";

        $paidSql = '0';
        if (SchemaInspector::hasTable('payments') && SchemaInspector::hasColumn('payments', 'invoice_id') && SchemaInspector::hasColumn('payments', 'amount')) {
            $payDel = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'AND p.deleted_at IS NULL' : '';
            $payBiz = SchemaInspector::hasColumn('payments', 'business_id') ? 'AND p.business_id = i.business_id' : '';
            $paidSql = "(SELECT COALESCE(SUM(p.amount), 0)
                         FROM payments p
                         WHERE p.invoice_id = i.id {$payDel} {$payBiz})";
        }

        $where = ['i.job_id IN (' . $placeholders . ')'];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $where[] = 'i.business_id = ?';
        }
        if (SchemaInspector::hasColumn('invoices', 'deleted_at')) {
            $where[] = 'i.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice')) IN ('invoice', 'estimate'))";
        }

        $sql = "SELECT ranked.job_id, ranked.id, ranked.doc_type, ranked.total, ranked.due_date, ranked.status, ranked.paid
                FROM (
                    SELECT
                        i.job_id,
                        i.id,
                        {$typeSql} AS doc_type,
                        {$totalSql} AS total,
                        {$dueDateSql} AS due_date,
                        {$statusSql} AS status,
                        {$paidSql} AS paid,
                        ROW_NUMBER() OVER (
                            PARTITION BY i.job_id
                            ORDER BY
                                CASE
                                    WHEN {$typeSql} = 'invoice' THEN 0
                                    ELSE 1
                                END,
                                i.id DESC
                        ) AS rn
                    FROM invoices i
                    WHERE " . implode(' AND ', $where) . "
                ) ranked
                WHERE ranked.rn = 1";

        $stmt = Database::connection()->prepare($sql);
        $bindIndex = 1;
        foreach ($jobIds as $jobId) {
            $stmt->bindValue($bindIndex, $jobId, \PDO::PARAM_INT);
            $bindIndex++;
        }
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue($bindIndex, $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $jobId = (int) ($row['job_id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }
            $out[$jobId] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function billingListPriceState(array $doc): string
    {
        $type = strtolower(trim((string) ($doc['doc_type'] ?? 'invoice')));
        if ($type === 'estimate') {
            return 'estimate';
        }

        if (self::billingDocIsOverdue($doc)) {
            return 'overdue';
        }

        return 'invoice';
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function billingDocIsOverdue(array $doc): bool
    {
        $due = trim((string) ($doc['due_date'] ?? ''));
        if ($due === '') {
            return false;
        }

        $dueTs = strtotime($due . ' 12:00:00');
        if ($dueTs === false || $dueTs >= strtotime('today 12:00:00')) {
            return false;
        }

        $status = strtolower(trim((string) ($doc['status'] ?? '')));
        if (in_array($status, ['paid', 'paid_in_full', 'cancelled', 'draft', 'declined', 'write_off'], true)) {
            return false;
        }

        $total = (float) ($doc['total'] ?? 0);
        $paid = (float) ($doc['paid'] ?? 0);

        return ($total - $paid) > 0.009;
    }

    public static function filteredSummary(
        int $businessId,
        string $search = '',
        string $status = '',
        string $jobType = '',
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $empty = [
            'pending_invoices' => 0.0,
            'past_due_invoices' => 0.0,
            'pending_estimates' => 0.0,
            'total_invoice_due' => 0.0,
        ];

        if (!SchemaInspector::hasTable('jobs')) {
            return $empty;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $jobType = strtolower(trim($jobType));
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $jobTypeSql = SchemaInspector::hasColumn('jobs', 'job_type') ? 'j.job_type' : 'NULL';
        $createdDateSql = SchemaInspector::hasColumn('jobs', 'created_at') ? 'DATE(j.created_at)' : 'CURDATE()';
        $scheduledDateSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'DATE(j.scheduled_start_at)'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'DATE(j.start_date)' : $createdDateSql);
        $filterDateSql = "COALESCE({$scheduledDateSql}, {$createdDateSql})";
        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : "''";

        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $joinSql = '';
        $clientNameSql = "''";
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$joinDeleted}";
        }

        $invoiceJoinSql = '';
        $paymentsJoinSql = '';
        $pendingInvoicesExpr = '0';
        $pastDueInvoicesExpr = '0';
        $pendingEstimatesExpr = '0';
        $totalInvoiceDueExpr = '0';
        $hasPaymentsTable = false;

        if (SchemaInspector::hasTable('invoices') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $invoiceJoinOn = ['i.job_id = j.id'];
            if (SchemaInspector::hasColumn('invoices', 'business_id') && SchemaInspector::hasColumn('jobs', 'business_id')) {
                $invoiceJoinOn[] = 'i.business_id = j.business_id';
            }
            if (SchemaInspector::hasColumn('invoices', 'deleted_at')) {
                $invoiceJoinOn[] = 'i.deleted_at IS NULL';
            }
            $invoiceJoinSql = ' LEFT JOIN invoices i ON ' . implode(' AND ', $invoiceJoinOn);

            $invoiceTotalSql = SchemaInspector::hasColumn('invoices', 'total')
                ? 'COALESCE(i.total, 0)'
                : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'COALESCE(i.subtotal, 0)' : '0');
            $invoiceTypeSql = SchemaInspector::hasColumn('invoices', 'type')
                ? "LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'invoice'))"
                : "'invoice'";
            $isInvoiceSql = "{$invoiceTypeSql} = 'invoice'";
            $isEstimateSql = "{$invoiceTypeSql} = 'estimate'";
            $openInvoiceStatusSql = SchemaInspector::hasColumn('invoices', 'status')
                ? "LOWER(TRIM(COALESCE(i.status, ''))) NOT IN ('paid','paid_in_full','cancelled','draft','declined','write_off')"
                : '1=1';
            $openEstimateStatusSql = SchemaInspector::hasColumn('invoices', 'status')
                ? "LOWER(TRIM(COALESCE(i.status, ''))) NOT IN ('declined','converted','cancelled')"
                : '1=1';
            $isOverdueSql = SchemaInspector::hasColumn('invoices', 'due_date')
                ? '(i.due_date IS NOT NULL AND DATE(i.due_date) < CURDATE())'
                : '0';
            $isNotOverdueSql = SchemaInspector::hasColumn('invoices', 'due_date')
                ? '(i.due_date IS NULL OR DATE(i.due_date) >= CURDATE())'
                : '1=1';

            $hasPaymentsTable = SchemaInspector::hasTable('payments')
                && SchemaInspector::hasColumn('payments', 'invoice_id')
                && SchemaInspector::hasColumn('payments', 'amount');
            if ($hasPaymentsTable) {
                $paymentsWhere = ['p.invoice_id IS NOT NULL'];
                if (SchemaInspector::hasColumn('payments', 'business_id')) {
                    $paymentsWhere[] = 'p.business_id = :payments_business_id';
                }
                if (SchemaInspector::hasColumn('payments', 'deleted_at')) {
                    $paymentsWhere[] = 'p.deleted_at IS NULL';
                }
                $paymentsJoinSql = ' LEFT JOIN (
                    SELECT p.invoice_id, COALESCE(SUM(p.amount), 0) AS paid_total
                    FROM payments p
                    WHERE ' . implode(' AND ', $paymentsWhere) . '
                    GROUP BY p.invoice_id
                ) pmt ON pmt.invoice_id = i.id';
            } else {
                $paymentsJoinSql = ' LEFT JOIN (SELECT NULL AS invoice_id, 0 AS paid_total) pmt ON 1=0';
            }

            $balanceSql = "GREATEST({$invoiceTotalSql} - COALESCE(pmt.paid_total, 0), 0)";
            $docPresentSql = 'i.id IS NOT NULL';
            $unpaidInvoiceSql = "{$docPresentSql} AND {$isInvoiceSql} AND {$openInvoiceStatusSql} AND {$balanceSql} > 0.009";

            $pendingInvoicesExpr = "CASE WHEN {$unpaidInvoiceSql} AND {$isNotOverdueSql} THEN {$balanceSql} ELSE 0 END";
            $pastDueInvoicesExpr = "CASE WHEN {$unpaidInvoiceSql} AND {$isOverdueSql} THEN {$balanceSql} ELSE 0 END";
            $pendingEstimatesExpr = "CASE WHEN {$docPresentSql} AND {$isEstimateSql} AND {$openEstimateStatusSql} THEN {$invoiceTotalSql} ELSE 0 END";
            $totalInvoiceDueExpr = "CASE WHEN {$unpaidInvoiceSql} THEN {$balanceSql} ELSE 0 END";
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
        if ($jobType !== '' && $jobTypeSql !== 'NULL') {
            $where[] = 'LOWER(COALESCE(' . $jobTypeSql . ", '')) = :job_type";
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
                    COALESCE(SUM({$pendingInvoicesExpr}), 0) AS pending_invoices,
                    COALESCE(SUM({$pastDueInvoicesExpr}), 0) AS past_due_invoices,
                    COALESCE(SUM({$pendingEstimatesExpr}), 0) AS pending_estimates,
                    COALESCE(SUM({$totalInvoiceDueExpr}), 0) AS total_invoice_due
                FROM jobs j
                {$joinSql}
                {$invoiceJoinSql}
                {$paymentsJoinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($hasPaymentsTable) {
            if (SchemaInspector::hasColumn('payments', 'business_id')) {
                $stmt->bindValue(':payments_business_id', $businessId, \PDO::PARAM_INT);
            }
        }
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        if ($jobType !== '' && $jobTypeSql !== 'NULL') {
            $stmt->bindValue(':job_type', $jobType);
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

        $summary = [
            'pending_invoices' => (float) ($row['pending_invoices'] ?? 0),
            'past_due_invoices' => Invoice::sumPastDueOpenBalances($businessId, true),
            'pending_estimates' => (float) ($row['pending_estimates'] ?? 0),
            'total_invoice_due' => (float) ($row['total_invoice_due'] ?? 0),
        ];

        return $summary;
    }

    /**
     * Per-job gross/net SQL (outer alias `j`) matching financialSummary(): invoice + sales gross;
     * net = (invoice gross − labor + adjustments − expenses) + sales net.
     */
    private static function filteredSummaryGrossNetExprs(): array
    {
        $legacyGross = SchemaInspector::hasColumn('jobs', 'gross_amount')
            ? 'COALESCE(j.gross_amount, 0)'
            : (SchemaInspector::hasColumn('jobs', 'job_gross')
                ? 'COALESCE(j.job_gross, 0)'
                : '0');
        $legacyNet = SchemaInspector::hasColumn('jobs', 'net_amount')
            ? 'COALESCE(j.net_amount, 0)'
            : (SchemaInspector::hasColumn('jobs', 'job_net')
                ? 'COALESCE(j.job_net, 0)'
                : '0');

        $inv = self::sqlScalarInvoiceGrossForJobJ();
        $sg = self::sqlScalarSalesGrossForJobJ();
        $sn = self::sqlScalarSalesNetForJobJ();
        $lab = self::sqlScalarLaborCostForJobJ();
        $exp = self::sqlScalarExpensesForJobJ();
        $adj = self::sqlScalarAdjustmentsForJobJ();

        $canDerive = ($inv !== '0' || $sg !== '0' || $sn !== '0' || $lab !== '0' || $exp !== '0' || $adj !== '0');

        if (!$canDerive) {
            return [$legacyGross, $legacyNet];
        }

        $grossExpr = '(' . $inv . ' + ' . $sg . ')';
        $netExpr = '((' . $inv . ' - ' . $lab . ' + ' . $adj . ' - ' . $exp . ') + ' . $sn . ')';

        return [$grossExpr, $netExpr];
    }

    private static function sqlScalarInvoiceGrossForJobJ(): string
    {
        if (!SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'job_id')) {
            return '0';
        }
        $totalExpr = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');
        $where = ['i.job_id = j.id'];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $where[] = 'i.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('invoices', 'deleted_at')) {
            $where[] = 'i.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('invoices', 'status')) {
            $where[] = "i.status <> 'cancelled'";
        }
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "(LOWER(i.type) = 'invoice' OR i.type IS NULL OR TRIM(i.type) = '')";
        }

        return '(SELECT COALESCE(SUM(' . $totalExpr . '), 0) FROM invoices i WHERE ' . implode(' AND ', $where) . ')';
    }

    private static function sqlScalarSalesGrossForJobJ(): string
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'job_id')) {
            return '0';
        }
        $grossCol = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 's.gross_amount'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 's.amount' : '0');
        $where = ['s.job_id = j.id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        return '(SELECT COALESCE(SUM(COALESCE(' . $grossCol . ', 0)), 0) FROM sales s WHERE ' . implode(' AND ', $where) . ')';
    }

    private static function sqlScalarSalesNetForJobJ(): string
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'job_id')) {
            return '0';
        }
        $netCol = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 's.net_amount'
            : (SchemaInspector::hasColumn('sales', 'gross_amount')
                ? 's.gross_amount'
                : (SchemaInspector::hasColumn('sales', 'amount') ? 's.amount' : '0'));
        $where = ['s.job_id = j.id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        return '(SELECT COALESCE(SUM(COALESCE(' . $netCol . ', 0)), 0) FROM sales s WHERE ' . implode(' AND ', $where) . ')';
    }

    private static function sqlScalarLaborCostForJobJ(): string
    {
        if (!SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            return '0';
        }
        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return '0';
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
        $where = ['t.job_id = j.id'];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $where[] = 't.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $where[] = 't.deleted_at IS NULL';
        }

        $timeLaborSql = '(SELECT COALESCE(SUM((' . $durationExpr . ' / 60) * ' . $hourlyRateSql . '), 0) FROM employee_time_entries t' . $joinSql . ' WHERE ' . implode(' AND ', $where) . ')';

        return '(' . $timeLaborSql . ' + ' . self::sqlScalarLaborBonusForJobJ() . ')';
    }

    private static function sqlScalarLaborBonusForJobJ(): string
    {
        if (!SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'job_id') || !SchemaInspector::hasColumn('expenses', 'amount')) {
            return '0';
        }

        $categoryColumnSql = Expense::categoryColumnSql('ex');
        if ($categoryColumnSql === '') {
            return '0';
        }

        $where = ['ex.job_id = j.id', Expense::sqlCategoryIsLaborExpense($categoryColumnSql)];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $where[] = 'ex.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            $where[] = 'ex.deleted_at IS NULL';
        }

        return '(SELECT COALESCE(SUM(ex.amount), 0) FROM expenses ex WHERE ' . implode(' AND ', $where) . ')';
    }

    private static function sqlScalarExpensesForJobJ(): string
    {
        if (!SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'job_id') || !SchemaInspector::hasColumn('expenses', 'amount')) {
            return '0';
        }
        $where = ['e.job_id = j.id', Expense::sqlWhereNotLaborExpense('e')];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $where[] = 'e.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            $where[] = 'e.deleted_at IS NULL';
        }

        return '(SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE ' . implode(' AND ', $where) . ')';
    }

    private static function sqlScalarAdjustmentsForJobJ(): string
    {
        if (!SchemaInspector::hasTable('job_adjustments') || !SchemaInspector::hasColumn('job_adjustments', 'amount')) {
            return '0';
        }
        $where = ['a.job_id = j.id'];
        if (SchemaInspector::hasColumn('job_adjustments', 'business_id')) {
            $where[] = 'a.business_id = j.business_id';
        }
        if (SchemaInspector::hasColumn('job_adjustments', 'deleted_at')) {
            $where[] = 'a.deleted_at IS NULL';
        }

        return '(SELECT COALESCE(SUM(a.amount), 0) FROM job_adjustments a WHERE ' . implode(' AND ', $where) . ')';
    }

    public static function indexCount(
        int $businessId,
        string $search = '',
        string $status = '',
        string $jobType = '',
        ?string $fromDate = null,
        ?string $toDate = null
    ): int
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $jobType = strtolower(trim($jobType));

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
        if ($jobType !== '' && $jobTypeSql !== 'NULL') {
            $where[] = 'LOWER(COALESCE(' . $jobTypeSql . ", '')) = :job_type";
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
        if ($jobType !== '' && $jobTypeSql !== 'NULL') {
            $stmt->bindValue(':job_type', $jobType);
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
        $clientPhoneSql = "''";
        $joinSql = '';
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $clientPhoneSql = SchemaInspector::hasColumn('clients', 'phone') ? "COALESCE(c.phone, '')" : "''";
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

        $closeoutExtra = '';
        if (SchemaInspector::hasColumn('jobs', 'closeout_truck_loaded')) {
            $closeoutExtra = ',
                    j.closeout_truck_loaded AS closeout_truck_loaded,
                    j.closeout_site_clean AS closeout_site_clean,
                    j.closeout_signature_name AS closeout_signature_name,
                    j.closeout_completed_at AS closeout_completed_at';
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
                    {$clientNameSql} AS client_name,
                    {$clientPhoneSql} AS client_phone
                    {$closeoutExtra}
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

    /**
     * Jobs with a schedule in range (for Google Calendar backfill).
     *
     * @return list<array<string, mixed>>
     */
    public static function scheduledForCalendarSync(int $businessId, string $start, string $end): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $startSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'j.start_date' : null);
        if ($startSql === null) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $endSql = SchemaInspector::hasColumn('jobs', 'scheduled_end_at')
            ? 'j.scheduled_end_at'
            : (SchemaInspector::hasColumn('jobs', 'end_date') ? 'j.end_date' : 'NULL');
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $jobTypeSql = SchemaInspector::hasColumn('jobs', 'job_type') ? 'j.job_type' : 'NULL';
        $activeSql = SchemaInspector::hasColumn('jobs', 'is_active') ? 'j.is_active' : '1';
        $notesSql = SchemaInspector::hasColumn('jobs', 'notes') ? 'j.notes' : "''";
        $address1Sql = SchemaInspector::hasColumn('jobs', 'address_line1') ? 'j.address_line1' : "''";
        $address2Sql = SchemaInspector::hasColumn('jobs', 'address_line2') ? 'j.address_line2' : "''";
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : "''";
        $stateSql = SchemaInspector::hasColumn('jobs', 'state') ? 'j.state' : "''";
        $postalSql = SchemaInspector::hasColumn('jobs', 'postal_code') ? 'j.postal_code' : "''";
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
        $businessWhere = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';

        $joinClient = SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('jobs', 'client_id');
        $clientNameSql = "''";
        $clientPhoneSql = "''";
        $joinSql = '';
        if ($joinClient) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), '')";
            $clientPhoneSql = SchemaInspector::hasColumn('clients', 'phone') ? "COALESCE(c.phone, '')" : "''";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = j.client_id {$joinDeleted}";
        }

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$jobTypeSql} AS job_type,
                    {$statusSql} AS status,
                    {$activeSql} AS is_active,
                    {$startSql} AS scheduled_start_at,
                    {$endSql} AS scheduled_end_at,
                    {$notesSql} AS notes,
                    {$address1Sql} AS address_line1,
                    {$address2Sql} AS address_line2,
                    {$citySql} AS city,
                    {$stateSql} AS state,
                    {$postalSql} AS postal_code,
                    {$clientNameSql} AS client_name,
                    {$clientPhoneSql} AS client_phone
                FROM jobs j
                {$joinSql}
                WHERE {$businessWhere}
                  {$deletedWhere}
                  AND {$startSql} IS NOT NULL
                  AND {$startSql} >= :start_at
                  AND {$startSql} < :end_at
                ORDER BY {$startSql} ASC, j.id ASC
                LIMIT 2000";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':start_at', $start);
        $stmt->bindValue(':end_at', $end);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $jobs = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $jobs[] = $row;
            }
        }

        return $jobs;
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
                      AND ' . $deletedWhere . '
                      AND ' . Expense::sqlWhereNotLaborExpense('e');
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

        $tips = max(0.0, $payments - $invoiceGross);
        $balanceDue = max(0.0, $invoiceGross - $payments);

        $subAssignment = JobSubcontractorAssignment::isAvailable()
            ? JobSubcontractorAssignment::findForJob($businessId, $jobId)
            : null;
        $subOurCut = null;
        $metricsRevenue = $totalNet;
        if (is_array($subAssignment) && strtolower(trim((string) ($subAssignment['status'] ?? ''))) === 'completed') {
            $subOurCut = $subAssignment['our_cut'] ?? null;
            if ($subOurCut !== null && is_numeric($subOurCut)) {
                $metricsRevenue = (float) $subOurCut + $salesNet;
            }
        }

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
            'tips' => $tips,
            'expenses' => $expenses,
            'labor' => $laborCost,
            'labor_cost' => $laborCost,
            'adjustments' => $adjustments,
            'net' => $totalNet,
            'balance' => $balanceDue,
            'sub_assignment' => $subAssignment,
            'sub_our_cut' => $subOurCut !== null ? round((float) $subOurCut, 2) : null,
            'metrics_revenue' => round($metricsRevenue, 2),
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

    /**
     * Per-employee totals for all time logged on a job (includes open punches).
     *
     * @return list<array{employee_id: int, employee_name: string, entry_count: int, open_entries: int, total_minutes: int, total_hours: float, labor_cost: float}>
     */
    public static function laborTotalsByEmployee(int $businessId, int $jobId): array
    {
        if (
            $jobId <= 0
            || !SchemaInspector::hasTable('employee_time_entries')
            || !SchemaInspector::hasColumn('employee_time_entries', 'job_id')
            || !SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')
        ) {
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
            if (SchemaInspector::hasColumn('employees', 'deleted_at')) {
                $joinSql .= ' AND e.deleted_at IS NULL';
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
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'employee_id') ? 't.employee_id IS NOT NULL' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    t.employee_id,
                    {$employeeNameSql} AS employee_name,
                    COUNT(*) AS entry_count,
                    SUM(CASE WHEN {$clockOutSql} IS NULL THEN 1 ELSE 0 END) AS open_entries,
                    COALESCE(SUM({$durationExpr}), 0) AS total_minutes,
                    ROUND(COALESCE(SUM(({$durationExpr} / 60) * {$hourlyRateSql}), 0), 2) AS labor_cost
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                GROUP BY t.employee_id, {$employeeNameSql}
                ORDER BY employee_name ASC, t.employee_id ASC";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
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
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            $minutes = (int) ($row['total_minutes'] ?? 0);
            $out[] = [
                'employee_id' => $employeeId,
                'employee_name' => trim((string) ($row['employee_name'] ?? '')) ?: ('Employee #' . (string) $employeeId),
                'entry_count' => (int) ($row['entry_count'] ?? 0),
                'open_entries' => (int) ($row['open_entries'] ?? 0),
                'total_minutes' => $minutes,
                'total_hours' => round($minutes / 60, 2),
                'labor_cost' => (float) ($row['labor_cost'] ?? 0),
            ];
        }

        foreach (self::laborBonusTotalsByEmployee($businessId, $jobId) as $bonusRow) {
            if (!is_array($bonusRow)) {
                continue;
            }
            $employeeId = (int) ($bonusRow['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            $bonusTotal = (float) ($bonusRow['bonus_total'] ?? 0);
            if ($bonusTotal <= 0) {
                continue;
            }
            $found = false;
            foreach ($out as $index => $row) {
                if ((int) ($row['employee_id'] ?? 0) !== $employeeId) {
                    continue;
                }
                $out[$index]['labor_cost'] = round((float) ($row['labor_cost'] ?? 0) + $bonusTotal, 2);
                $out[$index]['bonus_total'] = round($bonusTotal, 2);
                $found = true;
                break;
            }
            if (!$found) {
                $out[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => trim((string) ($bonusRow['employee_name'] ?? '')) ?: ('Employee #' . (string) $employeeId),
                    'entry_count' => 0,
                    'open_entries' => 0,
                    'total_minutes' => 0,
                    'total_hours' => 0.0,
                    'labor_cost' => round($bonusTotal, 2),
                    'bonus_total' => round($bonusTotal, 2),
                ];
            }
        }

        usort($out, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
        });

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function laborBonusesByJob(int $businessId, int $jobId, int $limit = 200): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'job_id')) {
            return [];
        }

        $categoryColumnSql = Expense::categoryColumnSql('e');
        if ($categoryColumnSql === '') {
            return [];
        }

        $dateSql = SchemaInspector::hasColumn('expenses', 'expense_date')
            ? 'e.expense_date'
            : (SchemaInspector::hasColumn('expenses', 'date') ? 'e.date' : 'NULL');
        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'e.amount' : '0';
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $employeeIdSql = Expense::supportsEmployeeLink() ? 'e.employee_id' : 'NULL';

        $joinSql = '';
        $employeeNameSql = 'NULL';
        if (Expense::supportsEmployeeLink() && SchemaInspector::hasTable('employees')) {
            $joinSql .= ' LEFT JOIN employees emp ON emp.id = e.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND emp.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('employees', 'deleted_at')) {
                $joinSql .= ' AND emp.deleted_at IS NULL';
            }
            $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', emp.first_name, emp.last_name)), ''), NULLIF(emp.email, ''), CONCAT('Employee #', e.employee_id))";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = 'e.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
        $where[] = Expense::sqlCategoryIsLaborExpense($categoryColumnSql);

        $sql = "SELECT
                    e.id,
                    {$employeeIdSql} AS employee_id,
                    {$employeeNameSql} AS employee_name,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$noteSql} AS note
                FROM expenses e
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$dateSql} DESC, e.id DESC
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function laborBonusTotalsByEmployee(int $businessId, int $jobId): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'job_id')) {
            return [];
        }
        if (!Expense::supportsEmployeeLink()) {
            return [];
        }

        $categoryColumnSql = Expense::categoryColumnSql('e');
        if ($categoryColumnSql === '') {
            return [];
        }

        $amountSql = SchemaInspector::hasColumn('expenses', 'amount') ? 'e.amount' : '0';
        $joinSql = '';
        $employeeNameSql = "CONCAT('Employee #', e.employee_id)";
        if (SchemaInspector::hasTable('employees')) {
            $joinSql .= ' LEFT JOIN employees emp ON emp.id = e.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND emp.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('employees', 'deleted_at')) {
                $joinSql .= ' AND emp.deleted_at IS NULL';
            }
            $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', emp.first_name, emp.last_name)), ''), NULLIF(emp.email, ''), CONCAT('Employee #', e.employee_id))";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = 'e.job_id = :job_id';
        $where[] = 'e.employee_id IS NOT NULL';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
        $where[] = Expense::sqlCategoryIsLaborExpense($categoryColumnSql);

        $sql = "SELECT
                    e.employee_id,
                    {$employeeNameSql} AS employee_name,
                    COALESCE(SUM({$amountSql}), 0) AS bonus_total
                FROM expenses e
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                GROUP BY e.employee_id, {$employeeNameSql}
                HAVING bonus_total > 0
                ORDER BY employee_name ASC, e.employee_id ASC";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
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

    public static function updateStatus(int $businessId, int $jobId, string $status, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('jobs') || !SchemaInspector::hasColumn('jobs', 'status')) {
            return false;
        }

        $status = strtolower(trim($status));
        if ($status === '') {
            return false;
        }

        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $sql = 'UPDATE jobs SET
                    status = :status,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :job_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':status', $status);
        $uid = $actorUserId > 0 ? $actorUserId : null;
        $stmt->bindValue(':updated_by', $uid, $uid === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function saveCloseout(int $businessId, int $jobId, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasColumn('jobs', 'closeout_truck_loaded')) {
            return false;
        }

        $truck = !empty($data['closeout_truck_loaded']) ? 1 : 0;
        $clean = !empty($data['closeout_site_clean']) ? 1 : 0;
        $sig = trim((string) ($data['closeout_signature_name'] ?? ''));
        $sig = $sig !== '' ? substr($sig, 0, 190) : null;
        $done = !empty($data['closeout_completed_at']) ? $data['closeout_completed_at'] : null;

        $sql = 'UPDATE jobs SET
                    closeout_truck_loaded = :t1,
                    closeout_site_clean = :t2,
                    closeout_signature_name = :sig,
                    closeout_completed_at = :done,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :job_id
                  AND business_id = :business_id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute([
            't1' => $truck,
            't2' => $clean,
            'sig' => $sig,
            'done' => $done,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'job_id' => $jobId,
            'business_id' => $businessId,
        ]);
    }

    public static function deactivate(int $businessId, int $jobId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return false;
        }

        $sets = [];
        if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
            $sets[] = 'deleted_at = NOW()';
        }
        if (SchemaInspector::hasColumn('jobs', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
        }
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
        if (SchemaInspector::hasColumn('jobs', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
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
        $weightSql = SchemaInspector::hasColumn('expenses', 'weight') ? 'e.weight' : 'NULL';
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
        $employeeIdSql = Expense::supportsEmployeeLink() ? 'e.employee_id' : 'NULL';

        $joinSql = '';
        $employeeNameSql = 'NULL';
        if (Expense::supportsEmployeeLink() && SchemaInspector::hasTable('employees')) {
            $joinSql .= ' LEFT JOIN employees emp ON emp.id = e.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND emp.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('employees', 'deleted_at')) {
                $joinSql .= ' AND emp.deleted_at IS NULL';
            }
            $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', emp.first_name, emp.last_name)), ''), NULLIF(emp.email, ''), CONCAT('Employee #', e.employee_id))";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = 'e.job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    e.id,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$weightSql} AS weight,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
                    {$employeeIdSql} AS employee_id,
                    {$employeeNameSql} AS employee_name,
                    {$paymentMethodSql} AS payment_method,
                    {$noteSql} AS note,
                    {$createdSql} AS created_at
                FROM expenses e
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.id ASC
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

    public static function disposalWeightTotalByJob(int $businessId, int $jobId): float
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'job_id')) {
            return 0.0;
        }
        if (!SchemaInspector::hasColumn('expenses', 'weight')) {
            return 0.0;
        }

        $categorySql = SchemaInspector::hasColumn('expenses', 'category')
            ? 'category'
            : (SchemaInspector::hasColumn('expenses', 'expense_type')
                ? 'expense_type'
                : (SchemaInspector::hasColumn('expenses', 'type') ? 'type' : ''));
        if ($categorySql === '') {
            return 0.0;
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'business_id = :business_id' : '1=1';
        $where[] = 'job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        $where[] = "LOWER(TRIM({$categorySql})) = 'disposal'";
        $where[] = 'weight IS NOT NULL';
        $where[] = 'weight > 0';

        $sql = 'SELECT COALESCE(SUM(weight), 0)
                FROM expenses
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
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

        $category = trim((string) ($data['category'] ?? ''));
        if (SchemaInspector::hasColumn('expenses', 'weight')) {
            $append('weight', ':weight', Expense::weightForSave($category, (string) ($data['weight'] ?? '')));
        }

        if (SchemaInspector::hasColumn('expenses', 'employee_id')) {
            $append('employee_id', ':employee_id', Expense::employeeIdForSave($category, $data['employee_id'] ?? 0));
        }

        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $append('category', ':category', $category);
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $append('expense_type', ':category', $category);
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $append('type', ':category', $category);
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
        $weightSql = SchemaInspector::hasColumn('expenses', 'weight') ? 'e.weight' : 'NULL';
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
        $employeeIdSql = Expense::supportsEmployeeLink() ? 'e.employee_id' : 'NULL';

        $joinSql = '';
        $employeeNameSql = 'NULL';
        if (Expense::supportsEmployeeLink() && SchemaInspector::hasTable('employees')) {
            $joinSql .= ' LEFT JOIN employees emp ON emp.id = e.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('expenses', 'business_id')) {
                $joinSql .= ' AND emp.business_id = e.business_id';
            }
            if (SchemaInspector::hasColumn('employees', 'deleted_at')) {
                $joinSql .= ' AND emp.deleted_at IS NULL';
            }
            $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', emp.first_name, emp.last_name)), ''), NULLIF(emp.email, ''), CONCAT('Employee #', e.employee_id))";
        }

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
                    {$weightSql} AS weight,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
                    {$employeeIdSql} AS employee_id,
                    {$employeeNameSql} AS employee_name,
                    {$paymentMethodSql} AS payment_method,
                    {$referenceSql} AS reference_number,
                    {$noteSql} AS note,
                    {$createdSql} AS created_at
                FROM expenses e
                {$joinSql}
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

        $category = trim((string) ($data['category'] ?? ''));

        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $setParts[] = 'category = :category';
            $params['category'] = $category;
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $setParts[] = 'expense_type = :category';
            $params['category'] = $category;
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $setParts[] = '`type` = :category';
            $params['category'] = $category;
        }

        if (SchemaInspector::hasColumn('expenses', 'weight')) {
            $setParts[] = 'weight = :weight';
            $params['weight'] = Expense::weightForSave($category, (string) ($data['weight'] ?? ''));
        }

        if (SchemaInspector::hasColumn('expenses', 'employee_id')) {
            $setParts[] = 'employee_id = :employee_id';
            $params['employee_id'] = Expense::employeeIdForSave($category, $data['employee_id'] ?? 0);
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

        return (float) $stmt->fetchColumn() + self::laborBonusCostByJob($businessId, $jobId);
    }

    public static function laborBonusCostByJob(int $businessId, int $jobId): float
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'job_id')) {
            return 0.0;
        }

        $categoryColumn = Expense::categoryColumnName();
        if ($categoryColumn === '') {
            return 0.0;
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('expenses', 'business_id') ? 'business_id = :business_id' : '1=1';
        $where[] = 'job_id = :job_id';
        $where[] = SchemaInspector::hasColumn('expenses', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        $where[] = Expense::sqlCategoryIsLaborExpense($categoryColumn);

        $sql = 'SELECT COALESCE(SUM(amount), 0)
                FROM expenses
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
    }

    public static function laborCostByClient(int $businessId, int $clientId): float
    {
        if ($clientId <= 0 || !SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            return 0.0;
        }
        if (!SchemaInspector::hasTable('jobs') || !SchemaInspector::hasColumn('jobs', 'client_id') || !SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
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

        $joinSql = ' INNER JOIN jobs j ON j.id = t.job_id';
        if (SchemaInspector::hasColumn('jobs', 'business_id') && SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $joinSql .= ' AND j.business_id = t.business_id';
        }
        if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
            $joinSql .= ' AND j.deleted_at IS NULL';
        }

        $employeeJoinSql = '';
        $hourlyRateSql = '0';
        if (SchemaInspector::hasTable('employees')) {
            $employeeJoinSql = ' LEFT JOIN employees e ON e.id = t.employee_id';
            if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
                $employeeJoinSql .= ' AND e.business_id = t.business_id';
            }
            $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';
        }

        $where = ['j.client_id = :client_id'];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $where[] = 't.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $where[] = 't.deleted_at IS NULL';
        }

        $sql = "SELECT COALESCE(SUM(({$durationExpr} / 60) * {$hourlyRateSql}), 0)
                FROM employee_time_entries t
                {$joinSql}
                {$employeeJoinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = ['client_id' => $clientId];
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

    public static function unassignEmployee(int $businessId, int $jobId, int $employeeId, int $actorUserId): bool
    {
        if ($jobId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('job_employee_assignments')) {
            return false;
        }

        if (self::findAssignedEmployee($businessId, $jobId, $employeeId) === null) {
            return false;
        }

        if (TimeEntry::hasActiveEntryForJob($businessId, $jobId, $employeeId)) {
            return false;
        }

        $sets = [
            'deleted_at = NOW()',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ];
        if (SchemaInspector::hasColumn('job_employee_assignments', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
        }

        $sql = 'UPDATE job_employee_assignments
                SET ' . implode(', ', $sets) . '
                WHERE business_id = :business_id
                  AND job_id = :job_id
                  AND employee_id = :employee_id
                  AND deleted_at IS NULL';

        $params = [
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'job_id' => $jobId,
            'employee_id' => $employeeId,
        ];
        if (SchemaInspector::hasColumn('job_employee_assignments', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute($params);
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
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Active employees in the business not yet assigned to this job (for bulk add UI).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function unassignedEmployeesForJob(int $businessId, int $jobId): array
    {
        return self::employeeSearchOptions($businessId, $jobId, '', 500);
    }
}
