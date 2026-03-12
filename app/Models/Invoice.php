<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Invoice
{
    /** @var array<int, string>|null */
    private static ?array $statusEnumValues = null;

    public static function clientOptions(int $businessId, int $limit = 300): array
    {
        if (!SchemaInspector::hasTable('clients')) {
            return [];
        }

        $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $businessWhere = SchemaInspector::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1=1';
        $deletedWhere = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1=1';
        $statusWhere = SchemaInspector::hasColumn('clients', 'status') ? "COALESCE(c.status, 'active') = 'active'" : '1=1';

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

    public static function jobOptions(int $businessId, int $limit = 300): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $businessWhere = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    j.id,
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Job #', j.id)) AS title
                FROM jobs j
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                ORDER BY j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexList(int $businessId, string $search = '', string $status = '', int $limit = 25, int $offset = 0): array
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CONCAT('INV-', i.id)";
        $typeSql = SchemaInspector::hasColumn('invoices', 'type') ? 'i.type' : "'invoice'";
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'i.status' : "'draft'";
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');
        $issueDateSql = SchemaInspector::hasColumn('invoices', 'issue_date') ? 'i.issue_date' : 'NULL';
        $dueDateSql = SchemaInspector::hasColumn('invoices', 'due_date') ? 'i.due_date' : 'NULL';
        $jobIdSql = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id' : 'NULL';

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = i.client_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            $where[] = 'LOWER(' . $statusSql . ') = :status';
        }
        $where[] = "(
            :query = ''
            OR {$numberSql} LIKE :query_like_1
            OR {$clientNameSql} LIKE :query_like_2
            OR {$statusSql} LIKE :query_like_3
            OR CAST(i.id AS CHAR) LIKE :query_like_4
        )";

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    {$typeSql} AS type,
                    {$statusSql} AS status,
                    {$totalSql} AS total,
                    {$issueDateSql} AS issue_date,
                    {$dueDateSql} AS due_date,
                    {$jobIdSql} AS job_id,
                    {$clientNameSql} AS client_name
                FROM invoices i
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY i.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
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

    public static function indexCount(int $businessId, string $search = '', string $status = ''): int
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CONCAT('INV-', i.id)";
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'i.status' : "'draft'";

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = i.client_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            $where[] = 'LOWER(' . $statusSql . ') = :status';
        }
        $where[] = "(
            :query = ''
            OR {$numberSql} LIKE :query_like_1
            OR {$clientNameSql} LIKE :query_like_2
            OR {$statusSql} LIKE :query_like_3
            OR CAST(i.id AS CHAR) LIKE :query_like_4
        )";

        $sql = "SELECT COUNT(*)
                FROM invoices i
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForBusiness(int $businessId, int $invoiceId): ?array
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return null;
        }

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CONCAT('INV-', i.id)";
        $typeSql = SchemaInspector::hasColumn('invoices', 'type') ? 'i.type' : "'invoice'";
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'i.status' : "'draft'";
        $subtotalSql = SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0';
        $taxRateSql = SchemaInspector::hasColumn('invoices', 'tax_rate') ? 'i.tax_rate' : '0';
        $taxAmountSql = SchemaInspector::hasColumn('invoices', 'tax_amount') ? 'i.tax_amount' : '0';
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');
        $issueDateSql = SchemaInspector::hasColumn('invoices', 'issue_date') ? 'i.issue_date' : 'NULL';
        $dueDateSql = SchemaInspector::hasColumn('invoices', 'due_date') ? 'i.due_date' : 'NULL';
        $customerNoteSql = SchemaInspector::hasColumn('invoices', 'customer_note') ? 'i.customer_note' : 'NULL';
        $internalNoteSql = SchemaInspector::hasColumn('invoices', 'internal_note') ? 'i.internal_note' : 'NULL';
        $clientIdSql = SchemaInspector::hasColumn('invoices', 'client_id') ? 'i.client_id' : 'NULL';
        $jobIdSql = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id' : 'NULL';

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = i.client_id {$joinDeleted}";
        }

        $jobTitleSql = "'—'";
        $jobAddress1Sql = 'NULL';
        $jobAddress2Sql = 'NULL';
        $jobCitySql = 'NULL';
        $jobStateSql = 'NULL';
        $jobPostalSql = 'NULL';
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
                ? "COALESCE(NULLIF(TRIM(j.title), ''), CONCAT('Job #', j.id))"
                : (SchemaInspector::hasColumn('jobs', 'name')
                    ? "COALESCE(NULLIF(TRIM(j.name), ''), CONCAT('Job #', j.id))"
                    : "CONCAT('Job #', j.id)");
            $jobAddress1Sql = SchemaInspector::hasColumn('jobs', 'address_line1') ? 'j.address_line1' : 'NULL';
            $jobAddress2Sql = SchemaInspector::hasColumn('jobs', 'address_line2') ? 'j.address_line2' : 'NULL';
            $jobCitySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : 'NULL';
            $jobStateSql = SchemaInspector::hasColumn('jobs', 'state') ? 'j.state' : 'NULL';
            $jobPostalSql = SchemaInspector::hasColumn('jobs', 'postal_code') ? 'j.postal_code' : 'NULL';
            $joinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN jobs j ON j.id = i.job_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        $where[] = 'i.id = :invoice_id';

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    {$typeSql} AS type,
                    {$statusSql} AS status,
                    {$subtotalSql} AS subtotal,
                    {$taxRateSql} AS tax_rate,
                    {$taxAmountSql} AS tax_amount,
                    {$totalSql} AS total,
                    {$issueDateSql} AS issue_date,
                    {$dueDateSql} AS due_date,
                    {$customerNoteSql} AS customer_note,
                    {$internalNoteSql} AS internal_note,
                    {$clientIdSql} AS client_id,
                    {$jobIdSql} AS job_id,
                    {$clientNameSql} AS client_name,
                    {$jobTitleSql} AS job_title,
                    {$jobAddress1Sql} AS job_address_line1,
                    {$jobAddress2Sql} AS job_address_line2,
                    {$jobCitySql} AS job_city,
                    {$jobStateSql} AS job_state,
                    {$jobPostalSql} AS job_postal_code
                FROM invoices i
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':invoice_id', $invoiceId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function findByJobAndType(int $businessId, int $jobId, string $type): ?array
    {
        $rows = self::listByJobAndType($businessId, $jobId, $type);
        return $rows[0] ?? null;
    }

    public static function listByJobAndType(int $businessId, int $jobId, string $type): array
    {
        if (!SchemaInspector::hasTable('invoices') || $jobId <= 0) {
            return [];
        }

        $type = strtolower(trim($type));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            return [];
        }

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? 'i.invoice_number'
            : "CONCAT('INV-', i.id)";
        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'i.status' : "'draft'";
        $updatedSql = SchemaInspector::hasColumn('invoices', 'updated_at') ? 'i.updated_at' : 'NULL';
        $createdSql = SchemaInspector::hasColumn('invoices', 'created_at') ? 'i.created_at' : 'NULL';
        $issueDateSql = SchemaInspector::hasColumn('invoices', 'issue_date') ? 'i.issue_date' : 'NULL';
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');

        $where = [];
        $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id = :job_id' : '1=0';
        $where[] = SchemaInspector::hasColumn('invoices', 'type') ? 'LOWER(i.type) = :type' : '1=0';

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    {$statusSql} AS status,
                    {$createdSql} AS created_at,
                    {$updatedSql} AS updated_at,
                    {$issueDateSql} AS issue_date,
                    {$totalSql} AS total
                FROM invoices i
                WHERE " . implode(' AND ', $where) . '
                ORDER BY i.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'job_id' => $jobId,
            'type' => $type,
        ];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function lineItems(int $businessId, int $invoiceId): array
    {
        if (!SchemaInspector::hasTable('invoice_items')) {
            return [];
        }

        $typeSql = SchemaInspector::hasColumn('invoice_items', 'item_type') ? 'ii.item_type' : "'item'";
        $descriptionSql = SchemaInspector::hasColumn('invoice_items', 'description') ? 'ii.description' : "CONCAT('Item #', ii.id)";
        $noteSql = SchemaInspector::hasColumn('invoice_items', 'note') ? 'ii.note' : 'NULL';
        $qtySql = SchemaInspector::hasColumn('invoice_items', 'quantity') ? 'ii.quantity' : '1';
        $unitSql = SchemaInspector::hasColumn('invoice_items', 'unit_price') ? 'ii.unit_price' : '0';
        $lineTotalSql = SchemaInspector::hasColumn('invoice_items', 'line_total') ? 'ii.line_total' : '(' . $qtySql . ' * ' . $unitSql . ')';
        $taxableSql = SchemaInspector::hasColumn('invoice_items', 'taxable') ? 'ii.taxable' : '0';

        $where = [];
        $where[] = SchemaInspector::hasColumn('invoice_items', 'business_id') ? 'ii.business_id = :business_id' : '1=1';
        $where[] = 'ii.invoice_id = :invoice_id';

        $sql = "SELECT
                    ii.id,
                    {$typeSql} AS item_type,
                    {$descriptionSql} AS description,
                    {$noteSql} AS note,
                    {$qtySql} AS quantity,
                    {$unitSql} AS unit_price,
                    {$lineTotalSql} AS line_total,
                    {$taxableSql} AS taxable
                FROM invoice_items ii
                WHERE " . implode(' AND ', $where) . '
                ORDER BY ii.id ASC';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('invoice_items', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':invoice_id', $invoiceId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function replaceLineItems(int $businessId, int $invoiceId, array $items, int $actorUserId): void
    {
        if (!SchemaInspector::hasTable('invoice_items')) {
            return;
        }

        $deleteSql = 'DELETE FROM invoice_items
                      WHERE invoice_id = :invoice_id';
        if (SchemaInspector::hasColumn('invoice_items', 'business_id')) {
            $deleteSql .= ' AND business_id = :business_id';
        }

        $deleteStmt = Database::connection()->prepare($deleteSql);
        $deleteParams = ['invoice_id' => $invoiceId];
        if (SchemaInspector::hasColumn('invoice_items', 'business_id')) {
            $deleteParams['business_id'] = $businessId;
        }
        $deleteStmt->execute($deleteParams);

        if ($items === []) {
            return;
        }

        $insertSql = 'INSERT INTO invoice_items (
                        business_id,
                        invoice_id,
                        item_type,
                        description,
                        note,
                        quantity,
                        unit_price,
                        taxable,
                        line_total,
                        created_by,
                        updated_by,
                        created_at,
                        updated_at
                      ) VALUES (
                        :business_id,
                        :invoice_id,
                        :item_type,
                        :description,
                        :note,
                        :quantity,
                        :unit_price,
                        :taxable,
                        :line_total,
                        :created_by,
                        :updated_by,
                        NOW(),
                        NOW()
                      )';

        $insertStmt = Database::connection()->prepare($insertSql);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $qty = max(0.0, (float) ($item['quantity'] ?? 0));
            $rate = max(0.0, (float) ($item['rate'] ?? 0));
            $lineTotal = round($qty * $rate, 2);

            $insertStmt->execute([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'item_type' => $name,
                'description' => $name,
                'note' => trim((string) ($item['note'] ?? '')),
                'quantity' => $qty,
                'unit_price' => $rate,
                'taxable' => !empty($item['taxable']) ? 1 : 0,
                'line_total' => $lineTotal,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
        }
    }

    public static function payments(int $businessId, int $invoiceId): array
    {
        if (!SchemaInspector::hasTable('payments')) {
            return [];
        }

        $amountSql = SchemaInspector::hasColumn('payments', 'amount') ? 'p.amount' : '0';
        $paidAtSql = SchemaInspector::hasColumn('payments', 'paid_at') ? 'p.paid_at' : 'NULL';
        $paymentTypeSql = SchemaInspector::hasColumn('payments', 'payment_type') ? 'p.payment_type' : "'payment'";
        $methodSql = SchemaInspector::hasColumn('payments', 'method') ? 'p.method' : 'NULL';
        $referenceSql = SchemaInspector::hasColumn('payments', 'reference_number') ? 'p.reference_number' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('payments', 'note') ? 'p.note' : 'NULL';

        $where = [];
        $where[] = SchemaInspector::hasColumn('payments', 'business_id') ? 'p.business_id = :business_id' : '1=1';
        $where[] = 'p.invoice_id = :invoice_id';
        $where[] = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    p.id,
                    {$amountSql} AS amount,
                    {$paidAtSql} AS paid_at,
                    {$paymentTypeSql} AS payment_type,
                    {$methodSql} AS method,
                    {$referenceSql} AS reference_number,
                    {$noteSql} AS note
                FROM payments p
                WHERE " . implode(' AND ', $where) . '
                ORDER BY p.id ASC';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('payments', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':invoice_id', $invoiceId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function paymentInvoiceOptions(int $businessId, int $jobId = 0, int $limit = 500): array
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return [];
        }

        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? "COALESCE(NULLIF(TRIM(i.invoice_number), ''), CONCAT('Invoice #', i.id))"
            : "CONCAT('Invoice #', i.id)";
        $typeSql = SchemaInspector::hasColumn('invoices', 'type') ? 'LOWER(i.type)' : "'invoice'";
        $jobIdSql = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id' : 'NULL';
        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');

        $joinSql = '';
        $clientNameSql = "'—'";
        if (SchemaInspector::hasTable('clients') && SchemaInspector::hasColumn('invoices', 'client_id')) {
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), CONCAT('Client #', c.id))";
            $clientJoinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN clients c ON c.id = i.client_id {$clientJoinDeleted}";
        }

        $jobTitleSql = "'—'";
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $jobTitleSql = SchemaInspector::hasColumn('jobs', 'title')
                ? "COALESCE(NULLIF(TRIM(j.title), ''), CONCAT('Job #', j.id))"
                : (SchemaInspector::hasColumn('jobs', 'name')
                    ? "COALESCE(NULLIF(TRIM(j.name), ''), CONCAT('Job #', j.id))"
                    : "CONCAT('Job #', j.id)");
            $jobJoinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN jobs j ON j.id = i.job_id {$jobJoinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "({$typeSql} = 'invoice' OR i.type IS NULL OR TRIM(i.type) = '')";
        }
        if ($jobId > 0 && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $where[] = 'i.job_id = :job_id';
        }

        $sql = "SELECT
                    i.id,
                    {$numberSql} AS invoice_number,
                    {$jobIdSql} AS job_id,
                    {$totalSql} AS total,
                    {$clientNameSql} AS client_name,
                    {$jobTitleSql} AS job_title
                FROM invoices i
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY i.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($jobId > 0 && SchemaInspector::hasColumn('invoices', 'job_id')) {
            $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 2000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function paymentsByJob(int $businessId, int $jobId): array
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('payments') || !SchemaInspector::hasTable('invoices')) {
            return [];
        }
        if (!SchemaInspector::hasColumn('payments', 'invoice_id') || !SchemaInspector::hasColumn('invoices', 'job_id')) {
            return [];
        }

        $amountSql = SchemaInspector::hasColumn('payments', 'amount') ? 'p.amount' : '0';
        $paidAtSql = SchemaInspector::hasColumn('payments', 'paid_at') ? 'p.paid_at' : 'NULL';
        $paymentTypeSql = SchemaInspector::hasColumn('payments', 'payment_type') ? 'p.payment_type' : "'payment'";
        $methodSql = SchemaInspector::hasColumn('payments', 'method') ? 'p.method' : 'NULL';
        $referenceSql = SchemaInspector::hasColumn('payments', 'reference_number') ? 'p.reference_number' : 'NULL';
        $createdSql = SchemaInspector::hasColumn('payments', 'created_at') ? 'p.created_at' : 'NULL';
        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
            ? "COALESCE(NULLIF(TRIM(i.invoice_number), ''), CONCAT('Invoice #', i.id))"
            : "CONCAT('Invoice #', i.id)";

        $where = [];
        $where[] = 'i.job_id = :job_id';
        $hasPaymentBusiness = SchemaInspector::hasColumn('payments', 'business_id');
        $hasInvoiceBusiness = SchemaInspector::hasColumn('invoices', 'business_id');
        $where[] = $hasPaymentBusiness ? 'p.business_id = :payment_business_id' : '1=1';
        $where[] = $hasInvoiceBusiness ? 'i.business_id = :invoice_business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    p.id,
                    p.invoice_id,
                    {$numberSql} AS invoice_number,
                    {$amountSql} AS amount,
                    {$paidAtSql} AS paid_at,
                    {$paymentTypeSql} AS payment_type,
                    {$methodSql} AS method,
                    {$referenceSql} AS reference_number,
                    {$createdSql} AS created_at
                FROM payments p
                INNER JOIN invoices i ON i.id = p.invoice_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY p.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $params = ['job_id' => $jobId];
        if ($hasPaymentBusiness) {
            $params['payment_business_id'] = $businessId;
        }
        if ($hasInvoiceBusiness) {
            $params['invoice_business_id'] = $businessId;
        }
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findPaymentForBusiness(int $businessId, int $paymentId): ?array
    {
        if ($paymentId <= 0 || !SchemaInspector::hasTable('payments')) {
            return null;
        }

        $amountSql = SchemaInspector::hasColumn('payments', 'amount') ? 'p.amount' : '0';
        $paidAtSql = SchemaInspector::hasColumn('payments', 'paid_at') ? 'p.paid_at' : 'NULL';
        $paymentTypeSql = SchemaInspector::hasColumn('payments', 'payment_type') ? 'p.payment_type' : "'payment'";
        $methodSql = SchemaInspector::hasColumn('payments', 'method') ? 'p.method' : 'NULL';
        $referenceSql = SchemaInspector::hasColumn('payments', 'reference_number') ? 'p.reference_number' : 'NULL';
        $noteSql = SchemaInspector::hasColumn('payments', 'note') ? 'p.note' : 'NULL';
        $invoiceIdSql = SchemaInspector::hasColumn('payments', 'invoice_id') ? 'p.invoice_id' : 'NULL';
        $createdBySql = SchemaInspector::hasColumn('payments', 'created_by') ? 'p.created_by' : 'NULL';

        $joinSql = '';
        $invoiceNumberSql = "'—'";
        $invoiceTypeSql = "'invoice'";
        $jobIdSql = 'NULL';
        if (SchemaInspector::hasTable('invoices') && SchemaInspector::hasColumn('payments', 'invoice_id')) {
            $invoiceNumberSql = SchemaInspector::hasColumn('invoices', 'invoice_number')
                ? "COALESCE(NULLIF(TRIM(i.invoice_number), ''), CONCAT('Invoice #', i.id))"
                : "CONCAT('Invoice #', i.id)";
            $invoiceTypeSql = SchemaInspector::hasColumn('invoices', 'type') ? 'LOWER(i.type)' : "'invoice'";
            $jobIdSql = SchemaInspector::hasColumn('invoices', 'job_id') ? 'i.job_id' : 'NULL';
            $invoiceDeleted = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'AND i.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN invoices i ON i.id = p.invoice_id {$invoiceDeleted}";
        }
        $receivedByNameSql = "'—'";
        if (SchemaInspector::hasTable('users') && SchemaInspector::hasColumn('payments', 'created_by')) {
            $userFirstNameSql = SchemaInspector::hasColumn('users', 'first_name') ? 'u.first_name' : 'NULL';
            $userLastNameSql = SchemaInspector::hasColumn('users', 'last_name') ? 'u.last_name' : 'NULL';
            $userEmailSql = SchemaInspector::hasColumn('users', 'email') ? 'u.email' : 'NULL';
            $userDeleted = SchemaInspector::hasColumn('users', 'deleted_at') ? 'AND u.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN users u ON u.id = p.created_by {$userDeleted}";
            $receivedByNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$userFirstNameSql}, {$userLastNameSql})), ''), NULLIF(TRIM({$userEmailSql}), ''), CONCAT('User #', p.created_by))";
        }

        $where = [];
        $where[] = 'p.id = :payment_id';
        $where[] = SchemaInspector::hasColumn('payments', 'business_id') ? 'p.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    p.id,
                    {$invoiceIdSql} AS invoice_id,
                    {$amountSql} AS amount,
                    {$paidAtSql} AS paid_at,
                    {$paymentTypeSql} AS payment_type,
                    {$methodSql} AS method,
                    {$referenceSql} AS reference_number,
                    {$noteSql} AS note,
                    {$createdBySql} AS created_by,
                    {$receivedByNameSql} AS received_by_name,
                    {$invoiceNumberSql} AS invoice_number,
                    {$invoiceTypeSql} AS invoice_type,
                    {$jobIdSql} AS job_id
                FROM payments p
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('payments', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':payment_id', $paymentId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function createPayment(int $businessId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('payments')) {
            return 0;
        }

        $columns = ['business_id', 'invoice_id', 'amount', 'paid_at', 'created_by', 'updated_by', 'created_at', 'updated_at'];
        $values = [':business_id', ':invoice_id', ':amount', ':paid_at', ':created_by', ':updated_by', 'NOW()', 'NOW()'];
        $params = [
            'business_id' => $businessId,
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'amount' => (float) ($data['amount'] ?? 0),
            'paid_at' => $data['paid_at'] ?? null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ];

        if (SchemaInspector::hasColumn('payments', 'method')) {
            $columns[] = 'method';
            $values[] = ':method';
            $params['method'] = strtolower(trim((string) ($data['method'] ?? '')));
        }
        if (SchemaInspector::hasColumn('payments', 'payment_type')) {
            $columns[] = 'payment_type';
            $values[] = ':payment_type';
            $params['payment_type'] = strtolower(trim((string) ($data['payment_type'] ?? 'payment')));
        }
        if (SchemaInspector::hasColumn('payments', 'reference_number')) {
            $columns[] = 'reference_number';
            $values[] = ':reference_number';
            $params['reference_number'] = trim((string) ($data['reference_number'] ?? ''));
        }
        if (SchemaInspector::hasColumn('payments', 'note')) {
            $columns[] = 'note';
            $values[] = ':note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        }

        $sql = 'INSERT INTO payments (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updatePayment(int $businessId, int $paymentId, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('payments')) {
            return false;
        }

        $set = [
            'invoice_id = :invoice_id',
            'amount = :amount',
            'paid_at = :paid_at',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ];
        $params = [
            'invoice_id' => (int) ($data['invoice_id'] ?? 0),
            'amount' => (float) ($data['amount'] ?? 0),
            'paid_at' => $data['paid_at'] ?? null,
            'updated_by' => $actorUserId,
            'payment_id' => $paymentId,
            'business_id' => $businessId,
        ];

        if (SchemaInspector::hasColumn('payments', 'method')) {
            $set[] = 'method = :method';
            $params['method'] = strtolower(trim((string) ($data['method'] ?? '')));
        }
        if (SchemaInspector::hasColumn('payments', 'payment_type')) {
            $set[] = 'payment_type = :payment_type';
            $params['payment_type'] = strtolower(trim((string) ($data['payment_type'] ?? 'payment')));
        }
        if (SchemaInspector::hasColumn('payments', 'reference_number')) {
            $set[] = 'reference_number = :reference_number';
            $params['reference_number'] = trim((string) ($data['reference_number'] ?? ''));
        }
        if (SchemaInspector::hasColumn('payments', 'note')) {
            $set[] = 'note = :note';
            $params['note'] = trim((string) ($data['note'] ?? ''));
        }

        $deletedWhere = SchemaInspector::hasColumn('payments', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $sql = 'UPDATE payments
                SET ' . implode(', ', $set) . '
                WHERE id = :payment_id
                  AND business_id = :business_id' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function syncInvoicePaymentStatusesForJob(int $businessId, int $jobId, int $actorUserId = 0): void
    {
        if (
            $jobId <= 0
            || !SchemaInspector::hasTable('invoices')
            || !SchemaInspector::hasColumn('invoices', 'status')
            || !SchemaInspector::hasColumn('invoices', 'job_id')
        ) {
            return;
        }

        $invoiceTotalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');
        $invoiceStatusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'LOWER(i.status)' : "'unsent'";
        $invoiceIssueSql = SchemaInspector::hasColumn('invoices', 'issue_date') ? 'i.issue_date' : 'NULL';

        $invoiceWhere = [];
        $hasInvoiceBusiness = SchemaInspector::hasColumn('invoices', 'business_id');
        $invoiceWhere[] = 'i.job_id = :job_id';
        $invoiceWhere[] = $hasInvoiceBusiness ? 'i.business_id = :invoice_business_id' : '1=1';
        $invoiceWhere[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $invoiceWhere[] = "(LOWER(i.type) = 'invoice' OR i.type IS NULL OR TRIM(i.type) = '')";
        }

        $invoiceSql = "SELECT
                            i.id,
                            {$invoiceTotalSql} AS invoice_total,
                            {$invoiceStatusSql} AS invoice_status,
                            {$invoiceIssueSql} AS issue_date
                       FROM invoices i
                       WHERE " . implode(' AND ', $invoiceWhere) . '
                       ORDER BY COALESCE(issue_date, "9999-12-31"), i.id ASC';

        $invoiceStmt = Database::connection()->prepare($invoiceSql);
        $invoiceParams = ['job_id' => $jobId];
        if ($hasInvoiceBusiness) {
            $invoiceParams['invoice_business_id'] = $businessId;
        }
        $invoiceStmt->execute($invoiceParams);
        $invoiceRows = $invoiceStmt->fetchAll();
        if (!is_array($invoiceRows) || $invoiceRows === []) {
            return;
        }

        $totalPayments = 0.0;
        if (
            SchemaInspector::hasTable('payments')
            && SchemaInspector::hasColumn('payments', 'invoice_id')
            && SchemaInspector::hasColumn('payments', 'amount')
        ) {
            $paymentWhere = [];
            $hasPaymentBusiness = SchemaInspector::hasColumn('payments', 'business_id');
            $paymentWhere[] = 'i2.job_id = :sum_job_id';
            $paymentWhere[] = $hasPaymentBusiness ? 'p.business_id = :sum_payment_business_id' : '1=1';
            $paymentWhere[] = $hasInvoiceBusiness ? 'i2.business_id = :sum_invoice_business_id' : '1=1';
            $paymentWhere[] = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';
            $paymentWhere[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i2.deleted_at IS NULL' : '1=1';
            if (SchemaInspector::hasColumn('invoices', 'type')) {
                $paymentWhere[] = "(LOWER(i2.type) = 'invoice' OR i2.type IS NULL OR TRIM(i2.type) = '')";
            }

            $sumSql = "SELECT COALESCE(SUM(p.amount), 0) AS total_payments
                       FROM payments p
                       INNER JOIN invoices i2 ON i2.id = p.invoice_id
                       WHERE " . implode(' AND ', $paymentWhere);
            $sumStmt = Database::connection()->prepare($sumSql);
            $sumParams = ['sum_job_id' => $jobId];
            if ($hasPaymentBusiness) {
                $sumParams['sum_payment_business_id'] = $businessId;
            }
            if ($hasInvoiceBusiness) {
                $sumParams['sum_invoice_business_id'] = $businessId;
            }
            $sumStmt->execute($sumParams);
            $totalPayments = (float) ($sumStmt->fetchColumn() ?: 0);
        }

        $remaining = max(0.0, $totalPayments);
        $updates = [];
        foreach ($invoiceRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $invoiceId = (int) ($row['id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }

            $invoiceTotal = max(0.0, (float) ($row['invoice_total'] ?? 0));
            $currentStatus = self::normalizeStatusForStorage('invoice', (string) ($row['invoice_status'] ?? 'unsent'));
            $nextStatus = $currentStatus;

            if ($invoiceTotal > 0.0) {
                if ($remaining >= $invoiceTotal) {
                    $nextStatus = self::normalizeStatusForStorage('invoice', 'paid_in_full');
                    $remaining -= $invoiceTotal;
                } elseif ($remaining > 0.0) {
                    $nextStatus = self::normalizeStatusForStorage('invoice', 'partially_paid');
                    $remaining = 0.0;
                } elseif (in_array($currentStatus, ['paid_in_full', 'partially_paid', 'paid', 'partial'], true)) {
                    $nextStatus = self::normalizeStatusForStorage('invoice', 'unsent');
                }
            }

            if ($nextStatus !== $currentStatus) {
                $updates[] = [
                    'id' => $invoiceId,
                    'status' => $nextStatus,
                ];
            }
        }

        if ($updates === []) {
            return;
        }

        $setParts = ['status = :status', 'updated_at = NOW()'];
        if (SchemaInspector::hasColumn('invoices', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
        }
        $deletedWhere = SchemaInspector::hasColumn('invoices', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $updateSql = 'UPDATE invoices
                      SET ' . implode(', ', $setParts) . '
                      WHERE id = :invoice_id
                        AND job_id = :job_id' .
                        ($hasInvoiceBusiness ? ' AND business_id = :business_id' : '') .
                        $deletedWhere;
        $updateStmt = Database::connection()->prepare($updateSql);

        foreach ($updates as $update) {
            $params = [
                'status' => $update['status'],
                'invoice_id' => (int) ($update['id'] ?? 0),
                'job_id' => $jobId,
            ];
            if (SchemaInspector::hasColumn('invoices', 'updated_by')) {
                $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
            }
            if ($hasInvoiceBusiness) {
                $params['business_id'] = $businessId;
            }
            $updateStmt->execute($params);
        }
    }

    public static function summary(int $businessId): array
    {
        if (!SchemaInspector::hasTable('invoices')) {
            return ['open' => 0, 'paid' => 0, 'total' => 0];
        }

        $statusSql = SchemaInspector::hasColumn('invoices', 'status') ? 'LOWER(i.status)' : "'unsent'";
        $where = [];
        $where[] = SchemaInspector::hasColumn('invoices', 'business_id') ? 'i.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';
        if (SchemaInspector::hasColumn('invoices', 'type')) {
            $where[] = "(LOWER(i.type) = 'invoice' OR i.type IS NULL OR TRIM(i.type) = '')";
        }

        $sql = "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN {$statusSql} IN ('paid_in_full','paid') THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN {$statusSql} IN ('unsent','sent','partially_paid','draft','partial') THEN 1 ELSE 0 END) AS open_count
                FROM invoices i
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = [];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'open' => (int) ($row['open_count'] ?? 0),
            'paid' => (int) ($row['paid_count'] ?? 0),
            'total' => (int) ($row['total_count'] ?? 0),
        ];
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        $sql = 'INSERT INTO invoices (
                    business_id, client_id, job_id, type, status, invoice_number,
                    issue_date, due_date, subtotal, tax_rate, tax_amount, total,
                    customer_note, internal_note, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :client_id, :job_id, :type, :status, :invoice_number,
                    :issue_date, :due_date, :subtotal, :tax_rate, :tax_amount, :total,
                    :customer_note, :internal_note, :created_by, :updated_by, NOW(), NOW()
                )';

        $type = strtolower(trim((string) ($data['type'] ?? 'invoice')));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }
        $defaultStatus = $type === 'estimate' ? 'draft' : 'unsent';
        $status = strtolower(trim((string) ($data['status'] ?? $defaultStatus)));
        $status = self::normalizeStatusForStorage($type, $status);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => (int) ($data['client_id'] ?? 0),
            'job_id' => (isset($data['job_id']) && (int) $data['job_id'] > 0) ? (int) $data['job_id'] : null,
            'type' => $type,
            'status' => $status,
            'invoice_number' => trim((string) ($data['invoice_number'] ?? '')),
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => (float) ($data['subtotal'] ?? 0),
            'tax_rate' => (float) ($data['tax_rate'] ?? 0),
            'tax_amount' => (float) ($data['tax_amount'] ?? 0),
            'total' => (float) ($data['total'] ?? 0),
            'customer_note' => trim((string) ($data['customer_note'] ?? '')),
            'internal_note' => trim((string) ($data['internal_note'] ?? '')),
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $invoiceId, array $data, int $actorUserId): bool
    {
        $deletedWhere = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'AND deleted_at IS NULL' : '';

        $sql = 'UPDATE invoices
                SET client_id = :client_id,
                    job_id = :job_id,
                    type = :type,
                    status = :status,
                    invoice_number = :invoice_number,
                    issue_date = :issue_date,
                    due_date = :due_date,
                    subtotal = :subtotal,
                    tax_rate = :tax_rate,
                    tax_amount = :tax_amount,
                    total = :total,
                    customer_note = :customer_note,
                    internal_note = :internal_note,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :invoice_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $type = strtolower(trim((string) ($data['type'] ?? 'invoice')));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }
        $defaultStatus = $type === 'estimate' ? 'draft' : 'unsent';
        $status = strtolower(trim((string) ($data['status'] ?? $defaultStatus)));
        $status = self::normalizeStatusForStorage($type, $status);

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute([
            'client_id' => (int) ($data['client_id'] ?? 0),
            'job_id' => (isset($data['job_id']) && (int) $data['job_id'] > 0) ? (int) $data['job_id'] : null,
            'type' => $type,
            'status' => $status,
            'invoice_number' => trim((string) ($data['invoice_number'] ?? '')),
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => (float) ($data['subtotal'] ?? 0),
            'tax_rate' => (float) ($data['tax_rate'] ?? 0),
            'tax_amount' => (float) ($data['tax_amount'] ?? 0),
            'total' => (float) ($data['total'] ?? 0),
            'customer_note' => trim((string) ($data['customer_note'] ?? '')),
            'internal_note' => trim((string) ($data['internal_note'] ?? '')),
            'updated_by' => $actorUserId,
            'invoice_id' => $invoiceId,
            'business_id' => $businessId,
        ]);
    }

    public static function updateStatus(int $businessId, int $invoiceId, string $type, string $status, int $actorUserId): bool
    {
        if (
            !SchemaInspector::hasTable('invoices')
            || !SchemaInspector::hasColumn('invoices', 'status')
            || $invoiceId <= 0
        ) {
            return false;
        }

        $type = strtolower(trim($type));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }
        $status = self::normalizeStatusForStorage($type, $status);

        $setParts = ['status = :status'];
        if (SchemaInspector::hasColumn('invoices', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        if (SchemaInspector::hasColumn('invoices', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
        }

        $whereParts = ['id = :invoice_id'];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('invoices', 'deleted_at')) {
            $whereParts[] = 'deleted_at IS NULL';
        }

        $sql = 'UPDATE invoices
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);

        $params = [
            'status' => $status,
            'invoice_id' => $invoiceId,
        ];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('invoices', 'updated_by')) {
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $invoiceId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        if (SchemaInspector::hasColumn('invoices', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
        }
        if (SchemaInspector::hasColumn('invoices', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        if (SchemaInspector::hasColumn('invoices', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
        }

        $whereParts = ['id = :invoice_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
        }

        $sql = 'UPDATE invoices
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);

        $params = [
            'invoice_id' => $invoiceId,
        ];
        if (SchemaInspector::hasColumn('invoices', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('invoices', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('invoices', 'updated_by')) {
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function softDeletePayment(int $businessId, int $paymentId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('payments') || !SchemaInspector::hasColumn('payments', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        if (SchemaInspector::hasColumn('payments', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
        }
        if (SchemaInspector::hasColumn('payments', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        if (SchemaInspector::hasColumn('payments', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
        }

        $whereParts = ['id = :payment_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('payments', 'business_id')) {
            $whereParts[] = 'business_id = :business_id';
        }

        $sql = 'UPDATE payments
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $whereParts);
        $stmt = Database::connection()->prepare($sql);

        $params = [
            'payment_id' => $paymentId,
        ];
        if (SchemaInspector::hasColumn('payments', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('payments', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('payments', 'updated_by')) {
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    private static function normalizeStatusForStorage(string $type, string $status): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }

        $status = strtolower(trim($status));
        if ($status === '') {
            $status = $type === 'estimate' ? 'draft' : 'unsent';
        }

        $allowed = self::statusEnumValues();
        if ($allowed === []) {
            return $status;
        }

        if (in_array($status, $allowed, true)) {
            return $status;
        }

        if ($type === 'invoice') {
            $legacyMap = [
                'unsent' => 'draft',
                'partially_paid' => 'partial',
                'paid_in_full' => 'paid',
            ];
            if (isset($legacyMap[$status]) && in_array($legacyMap[$status], $allowed, true)) {
                return $legacyMap[$status];
            }
        }

        $preferred = $type === 'estimate'
            ? ['draft', 'sent', 'approved', 'declined']
            : ['unsent', 'sent', 'partially_paid', 'paid_in_full', 'draft', 'partial', 'paid'];

        foreach ($preferred as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        return $allowed[0];
    }

    /**
     * @return array<int, string>
     */
    private static function statusEnumValues(): array
    {
        if (self::$statusEnumValues !== null) {
            return self::$statusEnumValues;
        }

        if (!SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'status')) {
            self::$statusEnumValues = [];
            return self::$statusEnumValues;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            'table_name' => 'invoices',
            'column_name' => 'status',
        ]);

        $columnType = (string) ($stmt->fetchColumn() ?: '');
        if ($columnType === '') {
            self::$statusEnumValues = [];
            return self::$statusEnumValues;
        }

        $values = [];
        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches) && isset($matches[1])) {
            foreach ($matches[1] as $rawValue) {
                $value = strtolower(trim(stripslashes((string) $rawValue)));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        self::$statusEnumValues = array_values(array_unique($values));
        return self::$statusEnumValues;
    }
}
