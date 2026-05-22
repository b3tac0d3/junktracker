<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class EstateSale
{
    public const ON_SITE_SALE_TYPE = 'Estate Sale - On Site';

    public const SPLIT_GROSS_TOTAL = 'split_gross_total';
    public const SPLIT_NET = 'split_net';
    public const SPLIT_LESS_LABOR = 'less_labor';
    public const SPLIT_NET_TOTAL = 'split_net_total';

    /**
     * @return array<string, string>
     */
    public static function clientSplitTypeOptions(): array
    {
        return [
            self::SPLIT_GROSS_TOTAL => 'Split gross total',
            self::SPLIT_NET => 'Split net',
            self::SPLIT_LESS_LABOR => 'Less labor',
            self::SPLIT_NET_TOTAL => 'Split net total',
        ];
    }

    public static function clientSplitTypeLabel(string $value): string
    {
        $normalized = self::normalizeClientSplitType($value);

        return self::clientSplitTypeOptions()[$normalized] ?? self::clientSplitTypeOptions()[self::SPLIT_GROSS_TOTAL];
    }

    public static function clientSplitTypeHelpText(string $value): string
    {
        return match (self::normalizeClientSplitType($value)) {
            self::SPLIT_NET => 'Client share = (total sales − expenses) × client %. Our share = remainder of that net amount.',
            self::SPLIT_LESS_LABOR => 'Client share = (total sales − labor) × client %. Our share = total sales − client share − expenses − labor.',
            self::SPLIT_NET_TOTAL => 'Client share = (total sales − expenses − labor) × client %. Our share = remainder of that net total.',
            default => 'Client share = total sales × client %. Our share = total sales − client share − expenses.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function statusOptions(int $businessId = 0): array
    {
        $fallback = ['scheduled', 'active', 'complete', 'cancelled'];
        if ($businessId <= 0) {
            return $fallback;
        }

        $configured = FormSelectValue::optionsForSection($businessId, 'estate_sale_status');
        $normalized = [];
        foreach ($configured as $rawOption) {
            $option = strtolower(trim((string) $rawOption));
            if ($option === '' || in_array($option, $normalized, true)) {
                continue;
            }
            $normalized[] = $option;
        }

        return $normalized !== [] ? $normalized : $fallback;
    }

    public static function findForBusiness(int $businessId, int $id): ?array
    {
        if (!SchemaInspector::hasTable('estate_sales') || $businessId <= 0 || $id <= 0) {
            return null;
        }

        $joinSql = '';
        $clientNameSql = 'NULL AS client_name';
        if (
            SchemaInspector::hasColumn('estate_sales', 'client_id')
            && SchemaInspector::hasTable('clients')
        ) {
            $companySql = SchemaInspector::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
            $joinDeleted = SchemaInspector::hasColumn('clients', 'deleted_at') ? 'AND c.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN clients c ON c.id = es.client_id AND c.business_id = es.business_id {$joinDeleted}";
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF({$companySql}, ''), CONCAT('Client #', c.id)) AS client_name";
        }

        $sql = 'SELECT
                    es.*,
                    (SELECT COUNT(*)
                     FROM estate_sale_customers esc
                     WHERE esc.business_id = es.business_id
                       AND esc.estate_sale_id = es.id
                       AND esc.deleted_at IS NULL) AS customer_count,
                    ' . $clientNameSql . '
                FROM estate_sales es' . $joinSql . '
                WHERE es.business_id = :business_id
                  AND es.id = :id
                  AND es.deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(
        int $businessId,
        string $search = '',
        string $status = '',
        string $fromDate = '',
        string $toDate = '',
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'date',
        string $sortDir = 'desc'
    ): array {
        if (!SchemaInspector::hasTable('estate_sales')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);
        $filterDateSql = 'COALESCE(DATE(es.start_at), DATE(es.created_at))';

        $where = [
            'es.business_id = :business_id',
            'es.deleted_at IS NULL',
        ];

        if ($status === 'dispatch') {
            $where[] = "LOWER(es.status) IN ('scheduled', 'active')";
        } elseif ($status !== '') {
            $where[] = 'LOWER(es.status) = :status';
        }

        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $where[] = '(
            :query = ""
            OR es.title LIKE :query_like_1
            OR COALESCE(es.city, "") LIKE :query_like_2
            OR COALESCE(es.address_line1, "") LIKE :query_like_3
            OR COALESCE(es.notes, "") LIKE :query_like_4
            OR CAST(es.id AS CHAR) LIKE :query_like_5
        )';

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'asc' ? 'ASC' : 'DESC';
        $sortMap = [
            'date' => "(es.start_at IS NULL) ASC, es.start_at {$sortDir}, es.id {$sortDir}",
            'id' => "es.id {$sortDir}",
            'title' => "es.title {$sortDir}, es.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['date'];

        $sql = 'SELECT
                    es.id,
                    es.title,
                    es.status,
                    es.start_at,
                    es.end_at,
                    es.address_line1,
                    es.address_line2,
                    es.city,
                    es.state,
                    es.postal_code,
                    (SELECT COUNT(*)
                     FROM estate_sale_customers esc
                     WHERE esc.business_id = es.business_id
                       AND esc.estate_sale_id = es.id
                       AND esc.deleted_at IS NULL) AS customer_count
                FROM estate_sales es
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy . '
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        if ($fromDate !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(
        int $businessId,
        string $search = '',
        string $status = '',
        string $fromDate = '',
        string $toDate = ''
    ): int {
        if (!SchemaInspector::hasTable('estate_sales')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);
        $filterDateSql = 'COALESCE(DATE(es.start_at), DATE(es.created_at))';

        $where = [
            'es.business_id = :business_id',
            'es.deleted_at IS NULL',
        ];

        if ($status === 'dispatch') {
            $where[] = "LOWER(es.status) IN ('scheduled', 'active')";
        } elseif ($status !== '') {
            $where[] = 'LOWER(es.status) = :status';
        }

        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $where[] = '(
            :query = ""
            OR es.title LIKE :query_like_1
            OR COALESCE(es.city, "") LIKE :query_like_2
            OR COALESCE(es.address_line1, "") LIKE :query_like_3
            OR COALESCE(es.notes, "") LIKE :query_like_4
            OR CAST(es.id AS CHAR) LIKE :query_like_5
        )';

        $sql = 'SELECT COUNT(*)
                FROM estate_sales es
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && $status !== 'dispatch') {
            $stmt->bindValue(':status', $status);
        }
        if ($fromDate !== '') {
            $stmt->bindValue(':from_date', $fromDate);
        }
        if ($toDate !== '') {
            $stmt->bindValue(':to_date', $toDate);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('estate_sales')) {
            return 0;
        }

        $hasClientId = SchemaInspector::hasColumn('estate_sales', 'client_id');
        $hasClientSplitType = SchemaInspector::hasColumn('estate_sales', 'client_split_type');
        $clientIdSql = $hasClientId ? ', client_id' : '';
        $clientIdValueSql = $hasClientId ? ', :client_id' : '';
        $clientSplitTypeSql = $hasClientSplitType ? ', client_split_type' : '';
        $clientSplitTypeValueSql = $hasClientSplitType ? ', :client_split_type' : '';

        $sql = 'INSERT INTO estate_sales (
                    business_id, title, status, start_at, end_at,
                    address_line1, address_line2, city, state, postal_code, notes, client_percentage' . $clientSplitTypeSql . $clientIdSql . ',
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :title, :status, :start_at, :end_at,
                    :address_line1, :address_line2, :city, :state, :postal_code, :notes, :client_percentage' . $clientSplitTypeValueSql . $clientIdValueSql . ',
                    :created_by, :updated_by, NOW(), NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'business_id' => $businessId,
            'title' => trim((string) ($data['title'] ?? '')),
            'status' => strtolower(trim((string) ($data['status'] ?? 'scheduled'))),
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'client_percentage' => self::normalizeClientPercentage($data['client_percentage'] ?? null),
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];
        if ($hasClientSplitType) {
            $params['client_split_type'] = self::normalizeClientSplitType($data['client_split_type'] ?? null);
        }
        if ($hasClientId) {
            $params['client_id'] = self::normalizeClientId($data['client_id'] ?? null);
        }
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $id, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('estate_sales')) {
            return false;
        }

        $hasClientId = SchemaInspector::hasColumn('estate_sales', 'client_id');
        $hasClientSplitType = SchemaInspector::hasColumn('estate_sales', 'client_split_type');
        $clientIdSetSql = $hasClientId ? "client_id = :client_id,\n                    " : '';
        $clientSplitTypeSetSql = $hasClientSplitType ? "client_split_type = :client_split_type,\n                    " : '';

        $sql = 'UPDATE estate_sales
                SET ' . $clientIdSetSql . $clientSplitTypeSetSql . 'title = :title,
                    status = :status,
                    start_at = :start_at,
                    end_at = :end_at,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    notes = :notes,
                    client_percentage = :client_percentage,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id
                  AND business_id = :business_id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'title' => trim((string) ($data['title'] ?? '')),
            'status' => strtolower(trim((string) ($data['status'] ?? 'scheduled'))),
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'address_line1' => trim((string) ($data['address_line1'] ?? '')),
            'address_line2' => trim((string) ($data['address_line2'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'client_percentage' => self::normalizeClientPercentage($data['client_percentage'] ?? null),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $id,
            'business_id' => $businessId,
        ];
        if ($hasClientSplitType) {
            $params['client_split_type'] = self::normalizeClientSplitType($data['client_split_type'] ?? null);
        }
        if ($hasClientId) {
            $params['client_id'] = self::normalizeClientId($data['client_id'] ?? null);
        }

        return $stmt->execute($params);
    }

    public static function softDelete(int $businessId, int $id, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('estate_sales')) {
            return false;
        }

        $sql = 'UPDATE estate_sales
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id
                  AND business_id = :business_id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $id,
            'business_id' => $businessId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function validate(array $form, int $businessId): array
    {
        $errors = [];
        $title = trim((string) ($form['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Enter a title for this estate sale.';
        }

        $status = strtolower(trim((string) ($form['status'] ?? '')));
        if (!in_array($status, self::statusOptions($businessId), true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        $startAt = self::normalizeDateTimeInput((string) ($form['start_at'] ?? ''));
        $endAt = self::normalizeDateTimeInput((string) ($form['end_at'] ?? ''));
        if ($startAt !== null && $endAt !== null && strtotime($startAt) > strtotime($endAt)) {
            $errors['end_at'] = 'End time must be after the start time.';
        }

        $clientPctRaw = trim((string) ($form['client_percentage'] ?? ''));
        if ($clientPctRaw !== '') {
            if (!is_numeric($clientPctRaw)) {
                $errors['client_percentage'] = 'Client percentage must be a number.';
            } else {
                $clientPct = (float) $clientPctRaw;
                if ($clientPct < 0 || $clientPct > 100) {
                    $errors['client_percentage'] = 'Client percentage must be between 0 and 100.';
                }
            }
        }

        $clientId = (int) ($form['client_id'] ?? 0);
        if ($clientId > 0 && Client::findForBusiness($businessId, $clientId) === null) {
            $errors['client_id'] = 'Choose a valid client.';
        }

        $splitType = trim((string) ($form['client_split_type'] ?? ''));
        if ($splitType !== '' && !array_key_exists(self::normalizeClientSplitType($splitType), self::clientSplitTypeOptions())) {
            $errors['client_split_type'] = 'Choose a valid split type.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadFromForm(array $form): array
    {
        return [
            'title' => trim((string) ($form['title'] ?? '')),
            'status' => strtolower(trim((string) ($form['status'] ?? 'scheduled'))),
            'start_at' => self::normalizeDateTimeInput((string) ($form['start_at'] ?? '')),
            'end_at' => self::normalizeDateTimeInput((string) ($form['end_at'] ?? '')),
            'address_line1' => trim((string) ($form['address_line1'] ?? '')),
            'address_line2' => trim((string) ($form['address_line2'] ?? '')),
            'city' => trim((string) ($form['city'] ?? '')),
            'state' => trim((string) ($form['state'] ?? '')),
            'postal_code' => trim((string) ($form['postal_code'] ?? '')),
            'notes' => trim((string) ($form['notes'] ?? '')),
            'client_percentage' => trim((string) ($form['client_percentage'] ?? '')),
            'client_split_type' => self::normalizeClientSplitType($form['client_split_type'] ?? null),
            'client_id' => self::normalizeClientId($form['client_id'] ?? null),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function customers(int $businessId, int $estateSaleId, int $limit = 500, int $offset = 0, ?string $statusFilter = null): array
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return [];
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'first_name')) {
            return [];
        }

        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $limit = max(1, min($limit, 2000));
        $offset = max(0, $offset);
        $hasQueueNumber = SchemaInspector::hasColumn('estate_sale_customers', 'queue_number');
        $hasCheckedIn = SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at');
        $hasCheckedOut = SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at');
        $queueSql = $hasQueueNumber ? 'esc.queue_number,' : '0 AS queue_number,';
        $checkedInSql = $hasCheckedIn ? 'esc.checked_in_at,' : 'NULL AS checked_in_at,';
        $checkedOutSql = $hasCheckedOut ? 'esc.checked_out_at,' : 'NULL AS checked_out_at,';
        $orderSql = $hasQueueNumber
            ? 'esc.queue_number ASC, esc.id ASC'
            : 'esc.created_at DESC, esc.id DESC';
        $statusSql = self::customerStatusFilterSql($statusFilter);

        $sql = "SELECT
                    esc.id,
                    esc.estate_sale_id,
                    {$queueSql}
                    esc.first_name,
                    esc.last_name,
                    esc.email,
                    esc.phone,
                    esc.city,
                    esc.state,
                    esc.notes,
                    {$checkedInSql}
                    {$checkedOutSql}
                    esc.created_at AS added_at,
                    {$nameSql} AS customer_name
                FROM estate_sale_customers esc
                WHERE esc.business_id = :business_id
                  AND esc.estate_sale_id = :estate_sale_id
                  AND esc.deleted_at IS NULL{$statusSql}
                ORDER BY {$orderSql}
                LIMIT :row_limit OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function customersCount(int $businessId, int $estateSaleId, ?string $statusFilter = null): int
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return 0;
        }

        $statusSql = self::customerStatusFilterSql($statusFilter);

        $sql = 'SELECT COUNT(*)
                FROM estate_sale_customers esc
                WHERE esc.business_id = :business_id
                  AND esc.estate_sale_id = :estate_sale_id
                  AND esc.deleted_at IS NULL' . $statusSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{inside: int, waiting: int, left: int, total_seen: int, total: int}
     */
    public static function customerPresenceSummary(int $businessId, int $estateSaleId): array
    {
        $empty = ['inside' => 0, 'waiting' => 0, 'left' => 0, 'total_seen' => 0, 'total' => 0];
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return $empty;
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at')) {
            $total = self::customersCount($businessId, $estateSaleId);

            return ['inside' => 0, 'waiting' => $total, 'left' => 0, 'total_seen' => 0, 'total' => $total];
        }

        $hasCheckedOut = SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at');
        $insideSql = $hasCheckedOut
            ? 'SUM(CASE WHEN esc.checked_in_at IS NOT NULL AND esc.checked_out_at IS NULL THEN 1 ELSE 0 END)'
            : 'SUM(CASE WHEN esc.checked_in_at IS NOT NULL THEN 1 ELSE 0 END)';
        $leftSql = $hasCheckedOut
            ? 'SUM(CASE WHEN esc.checked_in_at IS NOT NULL AND esc.checked_out_at IS NOT NULL THEN 1 ELSE 0 END)'
            : '0';

        $sql = "SELECT
                    COUNT(*) AS total_count,
                    {$insideSql} AS inside_count,
                    SUM(CASE WHEN esc.checked_in_at IS NULL THEN 1 ELSE 0 END) AS waiting_count,
                    {$leftSql} AS left_count,
                    SUM(CASE WHEN esc.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS total_seen_count
                FROM estate_sale_customers esc
                WHERE esc.business_id = :business_id
                  AND esc.estate_sale_id = :estate_sale_id
                  AND esc.deleted_at IS NULL";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return $empty;
        }

        return [
            'inside' => (int) ($row['inside_count'] ?? 0),
            'waiting' => (int) ($row['waiting_count'] ?? 0),
            'left' => (int) ($row['left_count'] ?? 0),
            'total_seen' => (int) ($row['total_seen_count'] ?? 0),
            'total' => (int) ($row['total_count'] ?? 0),
        ];
    }

    public static function normalizeCustomersStatusFilter(?string $value): string
    {
        $status = strtolower(trim((string) ($value ?? '')));

        return in_array($status, ['waiting', 'inside', 'left'], true) ? $status : 'all';
    }

    private static function customerStatusFilterSql(?string $statusFilter): string
    {
        $status = self::normalizeCustomersStatusFilter($statusFilter);
        if ($status === 'all' || !SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at')) {
            return '';
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at')) {
            return match ($status) {
                'waiting' => ' AND esc.checked_in_at IS NULL',
                'inside' => ' AND esc.checked_in_at IS NOT NULL',
                default => '',
            };
        }

        return match ($status) {
            'waiting' => ' AND esc.checked_in_at IS NULL',
            'inside' => ' AND esc.checked_in_at IS NOT NULL AND esc.checked_out_at IS NULL',
            'left' => ' AND esc.checked_in_at IS NOT NULL AND esc.checked_out_at IS NOT NULL',
            default => '',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function searchCustomers(int $businessId, int $estateSaleId, string $query, int $limit = 8): array
    {
        if ($businessId <= 0 || $estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return [];
        }

        $needle = trim($query);
        if ($needle === '') {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";

        $sql = "SELECT
                    esc.id,
                    esc.first_name,
                    esc.last_name,
                    esc.email,
                    esc.phone,
                    esc.city,
                    esc.state,
                    {$nameSql} AS customer_name
                FROM estate_sale_customers esc
                WHERE esc.business_id = :business_id
                  AND esc.estate_sale_id = :estate_sale_id
                  AND esc.deleted_at IS NULL
                  AND (
                    {$nameSql} LIKE :query_like_1
                    OR COALESCE(esc.email, '') LIKE :query_like_2
                    OR COALESCE(esc.phone, '') LIKE :query_like_3
                    OR COALESCE(esc.city, '') LIKE :query_like_4
                    OR CAST(esc.id AS CHAR) LIKE :query_like_5
                  )
                ORDER BY esc.created_at DESC, esc.id DESC
                LIMIT {$limit}";

        $like = '%' . $needle . '%';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':query_like_1', $like);
        $stmt->bindValue(':query_like_2', $like);
        $stmt->bindValue(':query_like_3', $like);
        $stmt->bindValue(':query_like_4', $like);
        $stmt->bindValue(':query_like_5', $like);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function customerDisplayName(array $row): string
    {
        $name = trim((string) ($row['customer_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $combined = trim($first . ' ' . $last);
        if ($combined !== '') {
            return $combined;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? ('Customer #' . (string) $id) : 'Customer';
    }

    /**
     * @return array<string, string>
     */
    public static function validateCustomer(array $data): array
    {
        $errors = [];
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        if ($firstName === '' && $lastName === '') {
            $errors['first_name'] = 'Enter a first or last name.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function customerPayloadFromInput(array $input): array
    {
        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => strtoupper(trim((string) ($input['state'] ?? ''))),
        ];
    }

    public static function createCustomer(int $businessId, int $estateSaleId, array $data, int $actorUserId): int
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return 0;
        }

        if (self::findForBusiness($businessId, $estateSaleId) === null) {
            return 0;
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'first_name')) {
            return 0;
        }

        $payload = self::customerPayloadFromInput($data);
        $queueNumber = SchemaInspector::hasColumn('estate_sale_customers', 'queue_number')
            ? self::nextCustomerQueueNumber($businessId, $estateSaleId)
            : 0;

        $columns = [
            'business_id', 'estate_sale_id',
            'first_name', 'last_name', 'email', 'phone', 'city', 'state',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ];
        $values = [
            ':business_id', ':estate_sale_id',
            ':first_name', ':last_name', ':email', ':phone', ':city', ':state',
            ':created_by', ':updated_by', 'NOW()', 'NOW()',
        ];
        $params = [
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'city' => $payload['city'],
            'state' => $payload['state'],
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];

        if ($queueNumber > 0) {
            array_splice($columns, 2, 0, ['queue_number']);
            array_splice($values, 2, 0, [':queue_number']);
            $params['queue_number'] = $queueNumber;
        }

        $sql = 'INSERT INTO estate_sale_customers (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findCustomerForSale(int $businessId, int $estateSaleId, int $customerId): ?array
    {
        if ($estateSaleId <= 0 || $customerId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return null;
        }

        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $hasQueueNumber = SchemaInspector::hasColumn('estate_sale_customers', 'queue_number');
        $hasCheckedIn = SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at');
        $hasCheckedOut = SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at');
        $queueSql = $hasQueueNumber ? 'esc.queue_number,' : '0 AS queue_number,';
        $checkedInSql = $hasCheckedIn ? 'esc.checked_in_at,' : 'NULL AS checked_in_at,';
        $checkedOutSql = $hasCheckedOut ? 'esc.checked_out_at,' : 'NULL AS checked_out_at,';

        $sql = "SELECT
                    esc.id,
                    esc.estate_sale_id,
                    {$queueSql}
                    esc.first_name,
                    esc.last_name,
                    esc.email,
                    esc.phone,
                    esc.city,
                    esc.state,
                    esc.notes,
                    {$checkedInSql}
                    {$checkedOutSql}
                    esc.created_at AS added_at,
                    {$nameSql} AS customer_name
                FROM estate_sale_customers esc
                WHERE esc.business_id = :business_id
                  AND esc.estate_sale_id = :estate_sale_id
                  AND esc.id = :id
                  AND esc.deleted_at IS NULL
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $customerId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function removeCustomer(int $businessId, int $estateSaleId, int $customerId, int $actorUserId): bool
    {
        if ($estateSaleId <= 0 || $customerId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return false;
        }

        $sql = 'UPDATE estate_sale_customers
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND estate_sale_id = :estate_sale_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'id' => $customerId,
        ]);
    }

    public static function nextCustomerQueueNumber(int $businessId, int $estateSaleId): int
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return 1;
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'queue_number')) {
            return 1;
        }

        $sql = 'SELECT COALESCE(MAX(queue_number), 0) + 1
                FROM estate_sale_customers
                WHERE business_id = :business_id
                  AND estate_sale_id = :estate_sale_id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->execute();

        return max(1, (int) $stmt->fetchColumn());
    }

    public static function customerCheckInStatus(array $row): string
    {
        $checkedIn = trim((string) ($row['checked_in_at'] ?? ''));
        $checkedOut = trim((string) ($row['checked_out_at'] ?? ''));

        if ($checkedIn === '') {
            return 'waiting';
        }

        if ($checkedOut === '') {
            return 'inside';
        }

        return 'left';
    }

    public static function customerCheckInStatusLabel(string $status): string
    {
        return match ($status) {
            'inside' => 'Inside',
            'left' => 'Checked out',
            default => 'Waiting',
        };
    }

    public static function customerVisitDurationMinutes(?string $checkedInAt, ?string $checkedOutAt): ?int
    {
        $inRaw = trim((string) ($checkedInAt ?? ''));
        $outRaw = trim((string) ($checkedOutAt ?? ''));
        if ($inRaw === '' || $outRaw === '') {
            return null;
        }

        $inTs = strtotime($inRaw);
        $outTs = strtotime($outRaw);
        if ($inTs === false || $outTs === false || $outTs < $inTs) {
            return null;
        }

        return (int) round(($outTs - $inTs) / 60);
    }

    public static function formatVisitDuration(?int $minutes): string
    {
        if ($minutes === null || $minutes < 0) {
            return '—';
        }

        if ($minutes <= 0) {
            return '0m';
        }

        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours <= 0) {
            return sprintf('%dm', $mins);
        }

        return sprintf('%dh %02dm', $hours, $mins);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function checkInCustomer(int $businessId, int $estateSaleId, int $customerId, int $actorUserId): ?array
    {
        if (
            $estateSaleId <= 0
            || $customerId <= 0
            || !SchemaInspector::hasTable('estate_sale_customers')
            || !SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at')
        ) {
            return null;
        }

        $customer = self::findCustomerForSale($businessId, $estateSaleId, $customerId);
        if ($customer === null) {
            return null;
        }

        if (self::customerCheckInStatus($customer) === 'inside') {
            return null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $updateSql = 'UPDATE estate_sale_customers
                          SET checked_in_at = NOW(),
                              checked_out_at = NULL,
                              updated_by = :updated_by,
                              updated_at = NOW()
                          WHERE business_id = :business_id
                            AND estate_sale_id = :estate_sale_id
                            AND id = :id
                            AND deleted_at IS NULL';

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                'business_id' => $businessId,
                'estate_sale_id' => $estateSaleId,
                'id' => $customerId,
            ]);

            if ($updateStmt->rowCount() <= 0) {
                $pdo->rollBack();

                return null;
            }

            if (SchemaInspector::hasTable('estate_sale_customer_visits')) {
                $visitSql = 'INSERT INTO estate_sale_customer_visits (
                                business_id, estate_sale_id, estate_sale_customer_id, checked_in_at, created_at
                             ) VALUES (
                                :business_id, :estate_sale_id, :estate_sale_customer_id, NOW(), NOW()
                             )';
                $visitStmt = $pdo->prepare($visitSql);
                $visitStmt->execute([
                    'business_id' => $businessId,
                    'estate_sale_id' => $estateSaleId,
                    'estate_sale_customer_id' => $customerId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        return self::findCustomerForSale($businessId, $estateSaleId, $customerId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function checkOutCustomer(int $businessId, int $estateSaleId, int $customerId, int $actorUserId): ?array
    {
        if (
            $estateSaleId <= 0
            || $customerId <= 0
            || !SchemaInspector::hasTable('estate_sale_customers')
            || !SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at')
        ) {
            return null;
        }

        $customer = self::findCustomerForSale($businessId, $estateSaleId, $customerId);
        if ($customer === null || self::customerCheckInStatus($customer) !== 'inside') {
            return null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $updateSql = 'UPDATE estate_sale_customers
                          SET checked_out_at = NOW(),
                              updated_by = :updated_by,
                              updated_at = NOW()
                          WHERE business_id = :business_id
                            AND estate_sale_id = :estate_sale_id
                            AND id = :id
                            AND deleted_at IS NULL
                            AND checked_in_at IS NOT NULL
                            AND checked_out_at IS NULL';

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                'business_id' => $businessId,
                'estate_sale_id' => $estateSaleId,
                'id' => $customerId,
            ]);

            if ($updateStmt->rowCount() <= 0) {
                $pdo->rollBack();

                return null;
            }

            if (SchemaInspector::hasTable('estate_sale_customer_visits')) {
                $visitSql = 'UPDATE estate_sale_customer_visits
                             SET checked_out_at = NOW()
                             WHERE business_id = :business_id
                               AND estate_sale_id = :estate_sale_id
                               AND estate_sale_customer_id = :estate_sale_customer_id
                               AND checked_out_at IS NULL
                             ORDER BY checked_in_at DESC, id DESC
                             LIMIT 1';
                $visitStmt = $pdo->prepare($visitSql);
                $visitStmt->execute([
                    'business_id' => $businessId,
                    'estate_sale_id' => $estateSaleId,
                    'estate_sale_customer_id' => $customerId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        return self::findCustomerForSale($businessId, $estateSaleId, $customerId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function customerVisits(int $businessId, int $estateSaleId, int $customerId, int $limit = 100): array
    {
        if (
            $estateSaleId <= 0
            || $customerId <= 0
            || !SchemaInspector::hasTable('estate_sale_customer_visits')
        ) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $sql = 'SELECT
                    id,
                    checked_in_at,
                    checked_out_at,
                    created_at
                FROM estate_sale_customer_visits
                WHERE business_id = :business_id
                  AND estate_sale_id = :estate_sale_id
                  AND estate_sale_customer_id = :estate_sale_customer_id
                ORDER BY checked_in_at DESC, id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_customer_id', $customerId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function customerSales(int $businessId, int $estateSaleId, int $customerId, int $limit = 100): array
    {
        if (
            $estateSaleId <= 0
            || $customerId <= 0
            || !SchemaInspector::hasTable('sales')
            || !SchemaInspector::hasColumn('sales', 'estate_sale_customer_id')
        ) {
            return [];
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : 'NULL';
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date')
            ? 's.sale_date'
            : (SchemaInspector::hasColumn('sales', 'created_at') ? 's.created_at' : 'NULL');
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');

        $where = [
            's.estate_sale_id = :estate_sale_id',
            's.estate_sale_customer_id = :estate_sale_customer_id',
        ];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $limit = max(1, min($limit, 500));
        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount
                FROM sales s
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE({$dateSql}, s.id) DESC, s.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_customer_id', $customerId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerPayloadForJson(array $customer): array
    {
        $customerId = (int) ($customer['id'] ?? 0);
        $status = self::customerCheckInStatus($customer);
        $checkedInAt = trim((string) ($customer['checked_in_at'] ?? ''));
        $checkedOutAt = trim((string) ($customer['checked_out_at'] ?? ''));
        $durationMinutes = self::customerVisitDurationMinutes(
            $checkedInAt !== '' ? $checkedInAt : null,
            $checkedOutAt !== '' ? $checkedOutAt : null
        );

        return [
            'id' => $customerId,
            'queue_number' => (int) ($customer['queue_number'] ?? 0),
            'name' => self::customerDisplayName($customer),
            'first_name' => trim((string) ($customer['first_name'] ?? '')),
            'last_name' => trim((string) ($customer['last_name'] ?? '')),
            'email' => trim((string) ($customer['email'] ?? '')),
            'phone' => trim((string) ($customer['phone'] ?? '')),
            'city' => trim((string) ($customer['city'] ?? '')),
            'state' => trim((string) ($customer['state'] ?? '')),
            'added_at' => trim((string) ($customer['added_at'] ?? '')),
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
            'check_in_status' => $status,
            'check_in_status_label' => self::customerCheckInStatusLabel($status),
            'visit_duration' => self::formatVisitDuration($durationMinutes),
            'show_url' => $customerId > 0 && (int) ($customer['estate_sale_id'] ?? 0) > 0
                ? url('/estate-sales/' . (string) ((int) ($customer['estate_sale_id'] ?? 0)) . '/customers/' . (string) $customerId)
                : '',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function expenseCategoryOptions(int $businessId): array
    {
        $defaults = ['Advertising', 'Setup', 'Supplies', 'Labor', 'Utilities', 'Other'];
        $configured = FormSelectValue::optionsForSection($businessId, 'estate_sale_expense_category');

        return $configured !== [] ? $configured : $defaults;
    }

    /**
     * @return array<string, float|null>
     */
    public static function financialSummary(int $businessId, int $estateSaleId, array $estateSale = []): array
    {
        $totalSales = 0.0;
        if ($estateSaleId > 0 && SchemaInspector::hasTable('sales') && SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
                ? 'COALESCE(s.gross_amount, 0)'
                : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
            $where = ['s.estate_sale_id = :estate_sale_id'];
            if (SchemaInspector::hasColumn('sales', 'business_id')) {
                $where[] = 's.business_id = :business_id';
            }
            if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
                $where[] = 's.deleted_at IS NULL';
            }

            $sql = 'SELECT COALESCE(SUM(' . $grossSql . '), 0) AS total_sales FROM sales s WHERE ' . implode(' AND ', $where);
            $stmt = Database::connection()->prepare($sql);
            if (SchemaInspector::hasColumn('sales', 'business_id')) {
                $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            }
            $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
            $stmt->execute();
            $totalSales = (float) ($stmt->fetchColumn() ?: 0);
        }

        $totalExpenses = 0.0;
        if ($estateSaleId > 0 && SchemaInspector::hasTable('expenses') && SchemaInspector::hasColumn('expenses', 'estate_sale_id')) {
            $where = ['e.estate_sale_id = :estate_sale_id'];
            if (SchemaInspector::hasColumn('expenses', 'business_id')) {
                $where[] = 'e.business_id = :business_id';
            }
            if (SchemaInspector::hasColumn('expenses', 'deleted_at')) {
                $where[] = 'e.deleted_at IS NULL';
            }

            $sql = 'SELECT COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS total_expenses FROM expenses e WHERE ' . implode(' AND ', $where);
            $stmt = Database::connection()->prepare($sql);
            if (SchemaInspector::hasColumn('expenses', 'business_id')) {
                $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            }
            $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
            $stmt->execute();
            $totalExpenses = (float) ($stmt->fetchColumn() ?: 0);
        }

        $clientPct = self::normalizeClientPercentage($estateSale['client_percentage'] ?? null);
        $splitType = self::normalizeClientSplitType($estateSale['client_split_type'] ?? null);
        $totalLabor = round(self::laborCostByEstateSale($businessId, $estateSaleId), 2);

        $clientShare = null;
        $ourShare = null;
        $splitBase = null;
        if ($clientPct !== null) {
            switch ($splitType) {
                case self::SPLIT_NET:
                    $splitBase = round($totalSales - $totalExpenses, 2);
                    $clientShare = round($splitBase * ($clientPct / 100), 2);
                    $ourShare = round($splitBase - $clientShare, 2);
                    break;
                case self::SPLIT_LESS_LABOR:
                    $splitBase = round($totalSales - $totalLabor, 2);
                    $clientShare = round($splitBase * ($clientPct / 100), 2);
                    $ourShare = round($totalSales - $clientShare - $totalExpenses - $totalLabor, 2);
                    break;
                case self::SPLIT_NET_TOTAL:
                    $splitBase = round($totalSales - $totalExpenses - $totalLabor, 2);
                    $clientShare = round($splitBase * ($clientPct / 100), 2);
                    $ourShare = round($splitBase - $clientShare, 2);
                    break;
                case self::SPLIT_GROSS_TOTAL:
                default:
                    $splitBase = round($totalSales, 2);
                    $clientShare = round($totalSales * ($clientPct / 100), 2);
                    $ourShare = round($totalSales - $clientShare - $totalExpenses, 2);
                    break;
            }
        }

        return [
            'total_sales' => round($totalSales, 2),
            'total_expenses' => round($totalExpenses, 2),
            'total_labor' => $totalLabor,
            'client_percentage' => $clientPct,
            'client_split_type' => $splitType,
            'client_split_type_label' => self::clientSplitTypeLabel($splitType),
            'split_help_text' => self::clientSplitTypeHelpText($splitType),
            'split_base' => $splitBase,
            'client_share' => $clientShare,
            'our_share' => $ourShare,
        ];
    }

    /**
     * @return array{sales: array<int, array<string, mixed>>, count: int, total_amount: float}
     */
    public static function salesSummary(int $businessId, int $estateSaleId, int $limit = 500, int $offset = 0): array
    {
        $financial = self::financialSummary($businessId, $estateSaleId);

        return [
            'sales' => self::sales($businessId, $estateSaleId, $limit, $offset),
            'count' => self::salesCount($businessId, $estateSaleId),
            'total_amount' => (float) ($financial['total_sales'] ?? 0),
        ];
    }

    public static function salesCount(int $businessId, int $estateSaleId): int
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            return 0;
        }

        $where = ['s.estate_sale_id = :estate_sale_id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = 'SELECT COUNT(*)
                FROM sales s
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sales(int $businessId, int $estateSaleId, int $limit = 500, int $offset = 0): array
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            return [];
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : 'NULL';
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date')
            ? 's.sale_date'
            : (SchemaInspector::hasColumn('sales', 'created_at') ? 's.created_at' : 'NULL');
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount') ? 'COALESCE(s.net_amount, 0)' : $grossSql;
        $customerNameSql = 'NULL';
        $joins = [];

        if (
            SchemaInspector::hasColumn('sales', 'estate_sale_customer_id')
            && SchemaInspector::hasTable('estate_sale_customers')
        ) {
            $joins[] = 'LEFT JOIN estate_sale_customers esc ON esc.id = s.estate_sale_customer_id AND esc.deleted_at IS NULL';
            $customerNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        }

        $where = ['s.estate_sale_id = :estate_sale_id'];
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
                    {$customerNameSql} AS customer_name
                FROM sales s";

        if ($joins !== []) {
            $sql .= "\n" . implode("\n", $joins);
        }

        $sql .= "\nWHERE " . implode(' AND ', $where);
        $sql .= "\nORDER BY COALESCE({$dateSql}, s.id) DESC, s.id DESC";
        $limit = max(1, min($limit, 2000));
        $offset = max(0, $offset);
        $sql .= "\nLIMIT :row_limit OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function expenses(int $businessId, int $estateSaleId, int $limit = 500): array
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'estate_sale_id')) {
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
        $noteSql = SchemaInspector::hasColumn('expenses', 'note')
            ? 'e.note'
            : (SchemaInspector::hasColumn('expenses', 'notes') ? 'e.notes' : 'NULL');
        $createdSql = SchemaInspector::hasColumn('expenses', 'created_at') ? 'e.created_at' : 'NULL';

        $where = ['e.estate_sale_id = :estate_sale_id'];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $where[] = 'e.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            $where[] = 'e.deleted_at IS NULL';
        }

        $sql = "SELECT
                    e.id,
                    {$dateSql} AS expense_date,
                    {$amountSql} AS amount,
                    {$nameSql} AS name,
                    {$categorySql} AS category,
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
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 2000)), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function expensePayloadFromInput(array $input): array
    {
        return [
            'category' => trim((string) ($input['category'] ?? '')),
            'amount' => trim((string) ($input['amount'] ?? '')),
            'expense_date' => trim((string) ($input['expense_date'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    public static function validateExpense(array $data): array
    {
        $errors = [];
        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '') {
            $errors['category'] = 'Choose a category.';
        }

        $amountRaw = trim((string) ($data['amount'] ?? ''));
        if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) {
            $errors['amount'] = 'Enter a valid amount.';
        }

        $dateRaw = trim((string) ($data['expense_date'] ?? ''));
        if ($dateRaw !== '' && strtotime($dateRaw) === false) {
            $errors['expense_date'] = 'Date is invalid.';
        }

        return $errors;
    }

    public static function createExpense(int $businessId, int $estateSaleId, array $data, int $actorUserId): int
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'amount')) {
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
            $append('job_id', ':job_id', null);
        }
        if (SchemaInspector::hasColumn('expenses', 'estate_sale_id')) {
            $append('estate_sale_id', ':estate_sale_id', $estateSaleId);
        }

        $expenseDate = trim((string) ($data['expense_date'] ?? ''));
        if ($expenseDate === '') {
            $expenseDate = date('Y-m-d');
        } else {
            $ts = strtotime($expenseDate);
            $expenseDate = $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
        }

        if (SchemaInspector::hasColumn('expenses', 'expense_date')) {
            $append('expense_date', ':expense_date', $expenseDate);
        } elseif (SchemaInspector::hasColumn('expenses', 'date')) {
            $append('date', ':expense_date', $expenseDate);
        }

        $append('amount', ':amount', round((float) ($data['amount'] ?? 0), 2));

        $category = trim((string) ($data['category'] ?? ''));
        if (SchemaInspector::hasColumn('expenses', 'category')) {
            $append('category', ':category', $category);
        } elseif (SchemaInspector::hasColumn('expenses', 'expense_type')) {
            $append('expense_type', ':category', $category);
        } elseif (SchemaInspector::hasColumn('expenses', 'type')) {
            $append('type', ':category', $category);
        }

        if (SchemaInspector::hasColumn('expenses', 'name')) {
            $append('name', ':name', $category);
        }

        $note = trim((string) ($data['note'] ?? ''));
        if (SchemaInspector::hasColumn('expenses', 'note')) {
            $append('note', ':note', $note);
        } elseif (SchemaInspector::hasColumn('expenses', 'notes')) {
            $append('notes', ':note', $note);
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

        $sql = 'INSERT INTO expenses (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function removeExpense(int $businessId, int $estateSaleId, int $expenseId, int $actorUserId): bool
    {
        if ($estateSaleId <= 0 || $expenseId <= 0 || !SchemaInspector::hasTable('expenses') || !SchemaInspector::hasColumn('expenses', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        $params = [
            'expense_id' => $expenseId,
            'estate_sale_id' => $estateSaleId,
        ];

        if (SchemaInspector::hasColumn('expenses', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('expenses', 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }

        $where = ['id = :expense_id', 'estate_sale_id = :estate_sale_id'];
        if (SchemaInspector::hasColumn('expenses', 'business_id')) {
            $where[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }

        $sql = 'UPDATE expenses SET ' . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $where) . ' AND deleted_at IS NULL LIMIT 1';
        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function assignedEmployees(int $businessId, int $estateSaleId): array
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_employee_assignments') || !SchemaInspector::hasTable('employees')) {
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
                FROM estate_sale_employee_assignments ja
                INNER JOIN employees e ON e.id = ja.employee_id
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE ja.business_id = :business_id
                  AND ja.estate_sale_id = :estate_sale_id
                  AND ja.deleted_at IS NULL
                  AND e.business_id = :employee_business_id
                  AND e.deleted_at IS NULL
                ORDER BY
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0 THEN 0 ELSE 1 END ASC,
                    display_name ASC,
                    e.id ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function findAssignedEmployee(int $businessId, int $estateSaleId, int $employeeId): ?array
    {
        if ($estateSaleId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('estate_sale_employee_assignments') || !SchemaInspector::hasTable('employees')) {
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
                FROM estate_sale_employee_assignments ja
                INNER JOIN employees e ON e.id = ja.employee_id
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE ja.business_id = :business_id
                  AND ja.estate_sale_id = :estate_sale_id
                  AND ja.employee_id = :employee_id
                  AND ja.deleted_at IS NULL
                  AND e.business_id = :employee_business_id
                  AND e.deleted_at IS NULL
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function assignEmployee(int $businessId, int $estateSaleId, int $employeeId, int $actorUserId): bool
    {
        if ($estateSaleId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('estate_sale_employee_assignments')) {
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

        $existingSql = 'SELECT id, deleted_at
                        FROM estate_sale_employee_assignments
                        WHERE business_id = :business_id
                          AND estate_sale_id = :estate_sale_id
                          AND employee_id = :employee_id
                        ORDER BY id DESC
                        LIMIT 1';
        $existingStmt = Database::connection()->prepare($existingSql);
        $existingStmt->execute([
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
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
                $restoreSql = 'UPDATE estate_sale_employee_assignments
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

        $sql = 'INSERT INTO estate_sale_employee_assignments (
                    business_id, estate_sale_id, employee_id, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :estate_sale_id, :employee_id, :created_by, :updated_by, NOW(), NOW()
                )';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'employee_id' => $employeeId,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function employeeSearchOptions(int $businessId, int $estateSaleId, string $query = '', int $limit = 10): array
    {
        if (!SchemaInspector::hasTable('employees') || !SchemaInspector::hasTable('estate_sale_employee_assignments')) {
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
                    FROM estate_sale_employee_assignments ja
                    WHERE ja.business_id = :assignment_business_id
                      AND ja.estate_sale_id = :estate_sale_id
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
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
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
     * @return array<int, array<string, mixed>>
     */
    public static function unassignedEmployeesForEstateSale(int $businessId, int $estateSaleId): array
    {
        return self::employeeSearchOptions($businessId, $estateSaleId, '', 500);
    }

    /**
     * @return array{entries: int, open_entries: int, hours: float}
     */
    public static function timeSummary(int $businessId, int $estateSaleId): array
    {
        $empty = ['entries' => 0, 'open_entries' => 0, 'hours' => 0.0];
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id')) {
            return $empty;
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
                  AND t.estate_sale_id = :estate_sale_id
                  AND {$deletedWhere}";

        $stmt = Database::connection()->prepare($sql);
        $params = ['estate_sale_id' => $estateSaleId];
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function timeLogsByEstateSale(int $businessId, int $estateSaleId, int $limit = 200): array
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id')) {
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
        $where[] = 't.estate_sale_id = :estate_sale_id';
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
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 2000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function laborCostByEstateSale(int $businessId, int $estateSaleId): float
    {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('employee_time_entries') || !SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id')) {
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
        $where[] = 't.estate_sale_id = :estate_sale_id';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT COALESCE(SUM(({$durationExpr} / 60) * {$hourlyRateSql}), 0)
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = ['estate_sale_id' => $estateSaleId];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    public static function normalizeClientSplitType(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        $options = self::clientSplitTypeOptions();

        return array_key_exists($normalized, $options) ? $normalized : self::SPLIT_GROSS_TOTAL;
    }

    private static function normalizeClientId(mixed $value): ?int
    {
        $clientId = (int) $value;

        return $clientId > 0 ? $clientId : null;
    }

    private static function normalizeClientPercentage(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        $pct = round((float) $raw, 2);
        if ($pct < 0 || $pct > 100) {
            return null;
        }

        return $pct;
    }

    private static function normalizeDateTimeInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
