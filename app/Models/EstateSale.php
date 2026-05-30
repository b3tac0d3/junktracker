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
            self::SPLIT_GROSS_TOTAL => 'Split gross total (% of sales)',
            self::SPLIT_NET => 'Split net (% sales − expenses)',
            self::SPLIT_LESS_LABOR => 'Less labor (% sales − labor)',
            self::SPLIT_NET_TOTAL => 'Split net total (% sales − exp − labor)',
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
            self::SPLIT_NET => 'Example: 60% net after expenses on $10,000 sales with $2,000 expenses → client gets 60% of $8,000 = $4,800; we keep the rest of that net amount.',
            self::SPLIT_LESS_LABOR => 'Example: 60% on $10,000 sales with $1,500 labor → client gets 60% of $8,500 = $5,100; we keep sales minus client share, expenses, and labor.',
            self::SPLIT_NET_TOTAL => 'Example: 60% net total on $10,000 sales with $2,000 expenses and $1,500 labor → client gets 60% of $6,500 = $3,900; we keep the rest of that net total.',
            default => 'Example: 60% of $10,000 gross sales → client gets $6,000; we keep sales minus client share and then pay expenses from our share.',
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

    /**
     * @return array<string, int>
     */
    public static function statusSummaryForClient(int $businessId, int $clientId): array
    {
        $summary = [];
        foreach (self::statusOptions($businessId) as $status) {
            $summary[$status] = 0;
        }

        if (
            $businessId <= 0
            || $clientId <= 0
            || !SchemaInspector::hasTable('estate_sales')
            || !SchemaInspector::hasColumn('estate_sales', 'client_id')
        ) {
            return $summary;
        }

        $sql = 'SELECT LOWER(es.status) AS status_key, COUNT(*) AS total
                FROM estate_sales es
                WHERE es.business_id = :business_id
                  AND es.client_id = :client_id
                  AND es.deleted_at IS NULL
                GROUP BY LOWER(es.status)';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
        ]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower(trim((string) ($row['status_key'] ?? '')));
            if (array_key_exists($key, $summary)) {
                $summary[$key] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forClient(int $businessId, int $clientId, int $limit = 50): array
    {
        if (
            $businessId <= 0
            || $clientId <= 0
            || !SchemaInspector::hasTable('estate_sales')
            || !SchemaInspector::hasColumn('estate_sales', 'client_id')
        ) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $sql = 'SELECT
                    es.id,
                    es.title,
                    es.status,
                    es.start_at,
                    es.end_at,
                    es.city,
                    es.created_at
                FROM estate_sales es
                WHERE es.business_id = :business_id
                  AND es.client_id = :client_id
                  AND es.deleted_at IS NULL
                ORDER BY es.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
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

    private static function useCustomerMemberships(): bool
    {
        return SchemaInspector::hasTable('estate_sale_customer_memberships');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findCustomerProfile(int $businessId, int $customerId): ?array
    {
        if ($businessId <= 0 || $customerId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return null;
        }

        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $hasSubscribes = SchemaInspector::hasColumn('estate_sale_customers', 'subscribes_to_future_sales');
        $hasContactMethod = SchemaInspector::hasColumn('estate_sale_customers', 'future_sales_contact_method');
        $subscribesSql = $hasSubscribes ? 'esc.subscribes_to_future_sales,' : '0 AS subscribes_to_future_sales,';
        $contactMethodSql = $hasContactMethod ? 'esc.future_sales_contact_method,' : 'NULL AS future_sales_contact_method,';

        $sql = "SELECT
                    esc.id,
                    esc.estate_sale_id,
                    esc.first_name,
                    esc.last_name,
                    esc.email,
                    esc.phone,
                    esc.city,
                    esc.state,
                    esc.notes,
                    {$subscribesSql}
                    {$contactMethodSql}
                    esc.created_at AS added_at,
                    {$nameSql} AS customer_name
                FROM estate_sale_customers esc
                WHERE esc.business_id = :business_id
                  AND esc.id = :id
                  AND esc.deleted_at IS NULL
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $customerId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function isCustomerOnSale(int $businessId, int $estateSaleId, int $customerId): bool
    {
        if ($businessId <= 0 || $estateSaleId <= 0 || $customerId <= 0) {
            return false;
        }

        if (self::useCustomerMemberships()) {
            $sql = 'SELECT 1
                    FROM estate_sale_customer_memberships m
                    WHERE m.business_id = :business_id
                      AND m.estate_sale_id = :estate_sale_id
                      AND m.customer_id = :customer_id
                      AND m.deleted_at IS NULL
                    LIMIT 1';
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'business_id' => $businessId,
                'estate_sale_id' => $estateSaleId,
                'customer_id' => $customerId,
            ]);

            return (bool) $stmt->fetchColumn();
        }

        return self::findCustomerForSale($businessId, $estateSaleId, $customerId) !== null;
    }

    public static function attachCustomerToSale(int $businessId, int $estateSaleId, int $customerId, int $actorUserId): bool
    {
        if ($estateSaleId <= 0 || $customerId <= 0 || self::findForBusiness($businessId, $estateSaleId) === null) {
            return false;
        }

        if (self::findCustomerProfile($businessId, $customerId) === null) {
            return false;
        }

        if (self::isCustomerOnSale($businessId, $estateSaleId, $customerId)) {
            return false;
        }

        if (!self::useCustomerMemberships()) {
            return false;
        }

        return self::upsertCustomerMembership($businessId, $estateSaleId, $customerId, $actorUserId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function searchCustomerProfiles(int $businessId, int $estateSaleId, string $query, int $limit = 8): array
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return [];
        }

        $needle = trim($query);
        if ($needle === '') {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $match = self::customerProfileSearchMatchSql($needle, $nameSql);
        $saleIdParam = 'profile_sale_id';
        $membershipJoin = self::useCustomerMemberships()
            ? ' LEFT JOIN estate_sale_customer_memberships m
                ON m.business_id = esc.business_id
               AND m.estate_sale_id = :' . $saleIdParam . '
               AND m.customer_id = esc.id
               AND m.deleted_at IS NULL'
            : '';
        $onSaleSql = self::useCustomerMemberships()
            ? ', CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END AS already_on_sale'
            : ', CASE WHEN esc.estate_sale_id = :' . $saleIdParam . ' THEN 1 ELSE 0 END AS already_on_sale';

        $sql = "SELECT
                    esc.id,
                    esc.first_name,
                    esc.last_name,
                    esc.email,
                    esc.phone,
                    esc.city,
                    esc.state,
                    {$nameSql} AS customer_name
                    {$onSaleSql}
                FROM estate_sale_customers esc{$membershipJoin}
                WHERE esc.business_id = :business_id
                  AND esc.deleted_at IS NULL
                  AND {$match['sql']}
                ORDER BY already_on_sale ASC, esc.updated_at DESC, esc.id DESC
                LIMIT {$limit}";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':' . $saleIdParam, $estateSaleId, \PDO::PARAM_INT);
        foreach ($match['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private static function upsertCustomerMembership(int $businessId, int $estateSaleId, int $customerId, int $actorUserId): bool
    {
        $queueNumber = self::nextCustomerQueueNumber($businessId, $estateSaleId);
        $actor = $actorUserId > 0 ? $actorUserId : null;

        $restoreSql = 'UPDATE estate_sale_customer_memberships
                       SET deleted_at = NULL,
                           deleted_by = NULL,
                           checked_in_at = NULL,
                           checked_out_at = NULL,
                           queue_number = :queue_number,
                           updated_by = :updated_by,
                           updated_at = NOW()
                       WHERE business_id = :business_id
                         AND estate_sale_id = :estate_sale_id
                         AND customer_id = :customer_id
                         AND deleted_at IS NOT NULL';
        $restoreStmt = Database::connection()->prepare($restoreSql);
        $restoreStmt->execute([
            'queue_number' => $queueNumber,
            'updated_by' => $actor,
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'customer_id' => $customerId,
        ]);
        if ($restoreStmt->rowCount() > 0) {
            return true;
        }

        return self::insertCustomerMembership($businessId, $estateSaleId, $customerId, $actorUserId, $queueNumber);
    }

    private static function insertCustomerMembership(
        int $businessId,
        int $estateSaleId,
        int $customerId,
        int $actorUserId,
        ?int $queueNumber = null
    ): bool {
        $queueNumber = $queueNumber ?? self::nextCustomerQueueNumber($businessId, $estateSaleId);
        $sql = 'INSERT INTO estate_sale_customer_memberships (
                    business_id, estate_sale_id, customer_id, queue_number,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :estate_sale_id, :customer_id, :queue_number,
                    :created_by, :updated_by, NOW(), NOW()
                )';
        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute([
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'customer_id' => $customerId,
            'queue_number' => $queueNumber,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function customersViaMemberships(int $businessId, int $estateSaleId, int $limit, int $offset, ?string $statusFilter): array
    {
        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $limit = max(1, min($limit, 2000));
        $offset = max(0, $offset);
        $statusSql = self::customerStatusFilterSql($statusFilter, 'm');

        $sql = "SELECT
                    esc.id,
                    m.estate_sale_id,
                    m.queue_number,
                    esc.first_name,
                    esc.last_name,
                    esc.email,
                    esc.phone,
                    esc.city,
                    esc.state,
                    esc.notes,
                    m.checked_in_at,
                    m.checked_out_at,
                    m.created_at AS added_at,
                    {$nameSql} AS customer_name
                FROM estate_sale_customer_memberships m
                INNER JOIN estate_sale_customers esc
                    ON esc.id = m.customer_id
                   AND esc.business_id = m.business_id
                WHERE m.business_id = :business_id
                  AND m.estate_sale_id = :estate_sale_id
                  AND m.deleted_at IS NULL
                  AND esc.deleted_at IS NULL{$statusSql}
                ORDER BY m.queue_number ASC, esc.id ASC
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

        if (self::useCustomerMemberships()) {
            return self::customersViaMemberships($businessId, $estateSaleId, $limit, $offset, $statusFilter);
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

        if (self::useCustomerMemberships()) {
            $statusSql = self::customerStatusFilterSql($statusFilter, 'm');
            $sql = 'SELECT COUNT(*)
                    FROM estate_sale_customer_memberships m
                    INNER JOIN estate_sale_customers esc
                        ON esc.id = m.customer_id
                       AND esc.business_id = m.business_id
                    WHERE m.business_id = :business_id
                      AND m.estate_sale_id = :estate_sale_id
                      AND m.deleted_at IS NULL
                      AND esc.deleted_at IS NULL' . $statusSql;
            $stmt = Database::connection()->prepare($sql);
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
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

        if (self::useCustomerMemberships()) {
            $sql = "SELECT
                        COUNT(*) AS total_count,
                        SUM(CASE WHEN m.checked_in_at IS NOT NULL AND m.checked_out_at IS NULL THEN 1 ELSE 0 END) AS inside_count,
                        SUM(CASE WHEN m.checked_in_at IS NULL THEN 1 ELSE 0 END) AS waiting_count,
                        SUM(CASE WHEN m.checked_in_at IS NOT NULL AND m.checked_out_at IS NOT NULL THEN 1 ELSE 0 END) AS left_count,
                        SUM(CASE WHEN m.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS total_seen_count
                    FROM estate_sale_customer_memberships m
                    INNER JOIN estate_sale_customers esc
                        ON esc.id = m.customer_id
                       AND esc.business_id = m.business_id
                    WHERE m.business_id = :business_id
                      AND m.estate_sale_id = :estate_sale_id
                      AND m.deleted_at IS NULL
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

    private static function customerStatusFilterSql(?string $statusFilter, string $alias = 'esc'): string
    {
        $status = self::normalizeCustomersStatusFilter($statusFilter);
        if ($status === 'all') {
            return '';
        }

        $hasCheckedIn = self::useCustomerMemberships()
            ? true
            : SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at');
        if (!$hasCheckedIn) {
            return '';
        }

        $hasCheckedOut = self::useCustomerMemberships()
            ? true
            : SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at');
        if (!$hasCheckedOut) {
            return match ($status) {
                'waiting' => " AND {$alias}.checked_in_at IS NULL",
                'inside' => " AND {$alias}.checked_in_at IS NOT NULL",
                default => '',
            };
        }

        return match ($status) {
            'waiting' => " AND {$alias}.checked_in_at IS NULL",
            'inside' => " AND {$alias}.checked_in_at IS NOT NULL AND {$alias}.checked_out_at IS NULL",
            'left' => " AND {$alias}.checked_in_at IS NOT NULL AND {$alias}.checked_out_at IS NOT NULL",
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

        $subscribes = !empty($data['subscribes_to_future_sales']);
        $contactMethod = self::normalizeFutureSalesContactMethod($data['future_sales_contact_method'] ?? null);
        if ($subscribes && $contactMethod === null) {
            $errors['future_sales_contact_method'] = 'Choose how to contact them about future sales.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function futureSalesContactMethodOptions(): array
    {
        return [
            'call' => 'Call',
            'text' => 'Text',
            'email' => 'Email',
        ];
    }

    public static function futureSalesContactMethodLabel(?string $method): string
    {
        $normalized = self::normalizeFutureSalesContactMethod($method);
        if ($normalized === null) {
            return '';
        }

        return self::futureSalesContactMethodOptions()[$normalized] ?? ucfirst($normalized);
    }

    public static function normalizeFutureSalesContactMethod(mixed $value): ?string
    {
        $method = strtolower(trim((string) $value));
        if ($method === '') {
            return null;
        }

        return array_key_exists($method, self::futureSalesContactMethodOptions()) ? $method : null;
    }

    /**
     * Possible duplicate estate customers (name, phone, email) within the business.
     *
     * @return list<array{id: int, display_name: string, estate_sale_id: int, estate_sale_title: string, reasons: list<string>, same_sale: bool}>
     */
    public static function findDuplicateCustomerMatches(
        int $businessId,
        array $candidate,
        ?int $excludeCustomerId = null,
        ?int $currentEstateSaleId = null
    ): array {
        if ($businessId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return [];
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'first_name')) {
            return [];
        }

        $n = self::normalizedCustomerDuplicateFields($candidate);
        $firstName = $n['first_name'];
        $lastName = $n['last_name'];
        $phoneDigits = $n['phone_digits'];
        $emailNorm = $n['email'];

        $or = [];
        $params = [];

        if ($firstName !== '' && $lastName !== '') {
            $or[] = '(LOWER(TRIM(COALESCE(esc.first_name, \'\'))) = :dup_first_name AND LOWER(TRIM(COALESCE(esc.last_name, \'\'))) = :dup_last_name)';
            $params['dup_first_name'] = $firstName;
            $params['dup_last_name'] = $lastName;
        }

        if ($phoneDigits !== '' && SchemaInspector::hasColumn('estate_sale_customers', 'phone')) {
            $digitsExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(esc.phone, ''), '-', ''), '(', ''), ')', ''), ' ', ''), '.', ''), '+', '')";
            $or[] = '(' . $digitsExpr . ' = :dup_phone)';
            $params['dup_phone'] = $phoneDigits;
        }

        if ($emailNorm !== '' && SchemaInspector::hasColumn('estate_sale_customers', 'email')) {
            $or[] = "LOWER(TRIM(COALESCE(esc.email, ''))) = :dup_email";
            $params['dup_email'] = $emailNorm;
        }

        if ($or === []) {
            return [];
        }

        $excludeSql = ($excludeCustomerId !== null && $excludeCustomerId > 0) ? ' AND esc.id <> :exclude_id' : '';
        $hasSaleTitle = SchemaInspector::hasTable('estate_sales') && SchemaInspector::hasColumn('estate_sales', 'title');
        $titleSql = $hasSaleTitle
            ? "COALESCE(NULLIF(TRIM(es.title), ''), CONCAT('Estate Sale #', es.id))"
            : "CONCAT('Estate Sale #', esc.estate_sale_id)";
        $joinSql = $hasSaleTitle
            ? ' INNER JOIN estate_sales es ON es.id = esc.estate_sale_id AND es.business_id = esc.business_id'
            : '';

        $sql = 'SELECT DISTINCT esc.id, esc.estate_sale_id, ' . $titleSql . ' AS estate_sale_title
                FROM estate_sale_customers esc' . $joinSql . '
                WHERE esc.business_id = :business_id
                  AND esc.deleted_at IS NULL' . $excludeSql . '
                  AND (' . implode(' OR ', $or) . ')
                ORDER BY esc.id DESC
                LIMIT 50';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($excludeCustomerId !== null && $excludeCustomerId > 0) {
            $stmt->bindValue(':exclude_id', $excludeCustomerId, \PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $existing = self::findCustomerProfile($businessId, $id);
            if ($existing === null) {
                continue;
            }

            $reasons = self::customerDuplicateReasonsAgainstCandidate($candidate, $existing);
            if ($reasons === []) {
                continue;
            }

            $estateSaleId = (int) ($row['estate_sale_id'] ?? 0);
            $sameSale = $currentEstateSaleId !== null
                && $currentEstateSaleId > 0
                && self::isCustomerOnSale($businessId, $currentEstateSaleId, $id);
            $linkSaleId = $sameSale ? $currentEstateSaleId : ($estateSaleId > 0 ? $estateSaleId : 0);

            $out[] = [
                'id' => $id,
                'display_name' => self::customerDisplayName($existing),
                'estate_sale_id' => $estateSaleId,
                'estate_sale_title' => trim((string) ($row['estate_sale_title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId),
                'reasons' => $reasons,
                'same_sale' => $sameSale,
                'show_url' => $linkSaleId > 0
                    ? url('/estate-sales/' . (string) $linkSaleId . '/customers/' . (string) $id)
                    : '',
            ];
        }

        return $out;
    }

    public static function indexCountAllCustomers(int $businessId, string $search = ''): int
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return 0;
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'first_name')) {
            return 0;
        }

        $searchSql = self::allCustomersSearchSql($search);
        $hasSaleTitle = SchemaInspector::hasTable('estate_sales') && SchemaInspector::hasColumn('estate_sales', 'title');
        $joinSql = $hasSaleTitle
            ? ' LEFT JOIN estate_sales es ON es.id = esc.estate_sale_id AND es.business_id = esc.business_id'
            : '';

        $sql = 'SELECT COUNT(DISTINCT esc.id)
                FROM estate_sale_customers esc' . $joinSql . '
                WHERE esc.business_id = :business_id
                  AND esc.deleted_at IS NULL' . $searchSql['where'];

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        foreach ($searchSql['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function indexListAllCustomers(int $businessId, string $search = '', int $limit = 25, int $offset = 0): array
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return [];
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'first_name')) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $searchSql = self::allCustomersSearchSql($search);
        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $hasQueueNumber = SchemaInspector::hasColumn('estate_sale_customers', 'queue_number');
        $queueSql = $hasQueueNumber ? 'esc.queue_number,' : '0 AS queue_number,';
        $hasSubscribes = SchemaInspector::hasColumn('estate_sale_customers', 'subscribes_to_future_sales');
        $subscribesSql = $hasSubscribes ? 'esc.subscribes_to_future_sales,' : '0 AS subscribes_to_future_sales,';
        $hasContactMethod = SchemaInspector::hasColumn('estate_sale_customers', 'future_sales_contact_method');
        $contactMethodSql = $hasContactMethod ? 'esc.future_sales_contact_method,' : 'NULL AS future_sales_contact_method,';
        $hasSaleTitle = SchemaInspector::hasTable('estate_sales') && SchemaInspector::hasColumn('estate_sales', 'title');
        $titleSql = $hasSaleTitle
            ? "COALESCE(NULLIF(TRIM(es.title), ''), CONCAT('Estate Sale #', es.id)) AS estate_sale_title,"
            : "CONCAT('Estate Sale #', esc.estate_sale_id) AS estate_sale_title,";
        $joinSql = $hasSaleTitle
            ? ' LEFT JOIN estate_sales es ON es.id = esc.estate_sale_id AND es.business_id = esc.business_id'
            : '';

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
                    {$subscribesSql}
                    {$contactMethodSql}
                    esc.created_at AS added_at,
                    {$titleSql}
                    {$nameSql} AS customer_name
                FROM estate_sale_customers esc{$joinSql}
                WHERE esc.business_id = :business_id
                  AND esc.deleted_at IS NULL{$searchSql['where']}
                ORDER BY esc.created_at DESC, esc.id DESC
                LIMIT :row_limit OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        foreach ($searchSql['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':row_limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{where: string, params: array<string, string>, join: string}
     */
    private static function allCustomersSearchSql(string $search): array
    {
        $needle = trim($search);
        if ($needle === '') {
            return ['where' => '', 'params' => [], 'join' => ''];
        }

        $like = '%' . $needle . '%';
        $hasSaleTitle = SchemaInspector::hasTable('estate_sales') && SchemaInspector::hasColumn('estate_sales', 'title');
        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $match = self::customerProfileSearchMatchSql($needle, $nameSql, 'all_cust');
        $parts = [$match['sql']];
        $params = $match['params'];
        if ($hasSaleTitle) {
            $parts[] = 'es.title LIKE :all_cust_like_sale';
            $params['all_cust_like_sale'] = $like;
        }

        return [
            'where' => ' AND (' . implode(' OR ', $parts) . ')',
            'params' => $params,
            'join' => $hasSaleTitle ? 'es' : '',
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array{first_name: string, last_name: string, phone_digits: string, email: string}
     */
    private static function normalizedCustomerDuplicateFields(array $candidate): array
    {
        return [
            'first_name' => self::normalizeCustomerIdentity((string) ($candidate['first_name'] ?? '')),
            'last_name' => self::normalizeCustomerIdentity((string) ($candidate['last_name'] ?? '')),
            'phone_digits' => self::normalizeCustomerPhone((string) ($candidate['phone'] ?? '')),
            'email' => strtolower(trim((string) ($candidate['email'] ?? ''))),
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $existing
     * @return list<string>
     */
    private static function customerDuplicateReasonsAgainstCandidate(array $candidate, array $existing): array
    {
        $n = self::normalizedCustomerDuplicateFields($candidate);
        $existingFirst = self::normalizeCustomerIdentity((string) ($existing['first_name'] ?? ''));
        $existingLast = self::normalizeCustomerIdentity((string) ($existing['last_name'] ?? ''));
        $existingPhone = self::normalizeCustomerPhone((string) ($existing['phone'] ?? ''));
        $existingEmail = strtolower(trim((string) ($existing['email'] ?? '')));

        $reasons = [];
        if ($n['first_name'] !== '' && $n['last_name'] !== '' && $existingFirst === $n['first_name'] && $existingLast === $n['last_name']) {
            $reasons[] = 'name';
        }
        if ($n['phone_digits'] !== '' && $existingPhone !== '' && $existingPhone === $n['phone_digits']) {
            $reasons[] = 'phone';
        }
        if ($n['email'] !== '' && $existingEmail !== '' && $n['email'] === $existingEmail) {
            $reasons[] = 'email';
        }

        return $reasons;
    }

    private static function normalizeCustomerIdentity(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function normalizeCustomerPhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function customerPhoneDigitsSql(string $alias = 'esc'): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$alias}.phone, ''), '-', ''), '(', ''), ')', ''), ' ', ''), '.', ''), '+', '')";
    }

    /**
     * @return array{sql: string, params: array<string, string>}
     */
    private static function customerProfileSearchMatchSql(string $needle, string $nameSql, string $paramPrefix = 'profile'): array
    {
        $like = '%' . $needle . '%';
        $parts = [
            "{$nameSql} LIKE :{$paramPrefix}_like_name",
            "COALESCE(esc.first_name, '') LIKE :{$paramPrefix}_like_first",
            "COALESCE(esc.last_name, '') LIKE :{$paramPrefix}_like_last",
            "COALESCE(esc.email, '') LIKE :{$paramPrefix}_like_email",
            "COALESCE(esc.phone, '') LIKE :{$paramPrefix}_like_phone",
            "COALESCE(esc.city, '') LIKE :{$paramPrefix}_like_city",
            "CAST(esc.id AS CHAR) LIKE :{$paramPrefix}_like_id",
        ];
        $params = [
            "{$paramPrefix}_like_name" => $like,
            "{$paramPrefix}_like_first" => $like,
            "{$paramPrefix}_like_last" => $like,
            "{$paramPrefix}_like_email" => $like,
            "{$paramPrefix}_like_phone" => $like,
            "{$paramPrefix}_like_city" => $like,
            "{$paramPrefix}_like_id" => $like,
        ];

        $phoneDigits = self::normalizeCustomerPhone($needle);
        if (strlen($phoneDigits) >= 2) {
            $phoneDigitsExpr = self::customerPhoneDigitsSql('esc');
            $parts[] = "{$phoneDigitsExpr} LIKE :{$paramPrefix}_phone_digits";
            $params["{$paramPrefix}_phone_digits"] = '%' . $phoneDigits . '%';
        }

        return [
            'sql' => '(' . implode(' OR ', $parts) . ')',
            'params' => $params,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerPayloadFromInput(array $input): array
    {
        $subscribes = !empty($input['subscribes_to_future_sales']);
        $contactMethod = self::normalizeFutureSalesContactMethod($input['future_sales_contact_method'] ?? null);
        if (!$subscribes) {
            $contactMethod = null;
        }

        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => strtoupper(trim((string) ($input['state'] ?? ''))),
            'subscribes_to_future_sales' => $subscribes ? 1 : 0,
            'future_sales_contact_method' => $contactMethod,
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
        $useMemberships = self::useCustomerMemberships();
        $queueNumber = !$useMemberships && SchemaInspector::hasColumn('estate_sale_customers', 'queue_number')
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

        if (SchemaInspector::hasColumn('estate_sale_customers', 'subscribes_to_future_sales')) {
            $columns[] = 'subscribes_to_future_sales';
            $values[] = ':subscribes_to_future_sales';
            $params['subscribes_to_future_sales'] = (int) ($payload['subscribes_to_future_sales'] ?? 0);
        }
        if (SchemaInspector::hasColumn('estate_sale_customers', 'future_sales_contact_method')) {
            $columns[] = 'future_sales_contact_method';
            $values[] = ':future_sales_contact_method';
            $params['future_sales_contact_method'] = $payload['future_sales_contact_method'] ?? null;
        }

        $sql = 'INSERT INTO estate_sale_customers (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $customerId = (int) Database::connection()->lastInsertId();
        if ($customerId <= 0) {
            return 0;
        }

        if ($useMemberships && !self::insertCustomerMembership($businessId, $estateSaleId, $customerId, $actorUserId)) {
            return 0;
        }

        return $customerId;
    }

    public static function updateCustomer(int $businessId, int $estateSaleId, int $customerId, array $data, int $actorUserId): bool
    {
        if ($estateSaleId <= 0 || $customerId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return false;
        }

        if (self::findCustomerForSale($businessId, $estateSaleId, $customerId) === null) {
            return false;
        }

        if (!SchemaInspector::hasColumn('estate_sale_customers', 'first_name')) {
            return false;
        }

        $payload = self::customerPayloadFromInput($data);

        $setParts = [
            'first_name = :first_name',
            'last_name = :last_name',
            'email = :email',
            'phone = :phone',
            'city = :city',
            'state = :state',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ];
        $executeParams = [
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'city' => $payload['city'],
            'state' => $payload['state'],
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'id' => $customerId,
        ];

        if (SchemaInspector::hasColumn('estate_sale_customers', 'subscribes_to_future_sales')) {
            $setParts[] = 'subscribes_to_future_sales = :subscribes_to_future_sales';
            $executeParams['subscribes_to_future_sales'] = (int) ($payload['subscribes_to_future_sales'] ?? 0);
        }
        if (SchemaInspector::hasColumn('estate_sale_customers', 'future_sales_contact_method')) {
            $setParts[] = 'future_sales_contact_method = :future_sales_contact_method';
            $executeParams['future_sales_contact_method'] = $payload['future_sales_contact_method'] ?? null;
        }

        $sql = 'UPDATE estate_sale_customers
                SET ' . implode(', ', $setParts) . '
                WHERE business_id = :business_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        unset($executeParams['estate_sale_id']);

        return $stmt->execute($executeParams);
    }

    public static function findCustomerForSale(int $businessId, int $estateSaleId, int $customerId): ?array
    {
        if ($estateSaleId <= 0 || $customerId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return null;
        }

        if (self::useCustomerMemberships()) {
            $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
            $hasSubscribes = SchemaInspector::hasColumn('estate_sale_customers', 'subscribes_to_future_sales');
            $hasContactMethod = SchemaInspector::hasColumn('estate_sale_customers', 'future_sales_contact_method');
            $subscribesSql = $hasSubscribes ? 'esc.subscribes_to_future_sales,' : '0 AS subscribes_to_future_sales,';
            $contactMethodSql = $hasContactMethod ? 'esc.future_sales_contact_method,' : 'NULL AS future_sales_contact_method,';

            $sql = "SELECT
                        esc.id,
                        m.estate_sale_id,
                        m.queue_number,
                        esc.first_name,
                        esc.last_name,
                        esc.email,
                        esc.phone,
                        esc.city,
                        esc.state,
                        esc.notes,
                        {$subscribesSql}
                        {$contactMethodSql}
                        m.checked_in_at,
                        m.checked_out_at,
                        m.created_at AS added_at,
                        {$nameSql} AS customer_name
                    FROM estate_sale_customer_memberships m
                    INNER JOIN estate_sale_customers esc
                        ON esc.id = m.customer_id
                       AND esc.business_id = m.business_id
                    WHERE m.business_id = :business_id
                      AND m.estate_sale_id = :estate_sale_id
                      AND m.customer_id = :id
                      AND m.deleted_at IS NULL
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

        $nameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', esc.first_name, esc.last_name)), ''), CONCAT('Customer #', esc.id))";
        $hasQueueNumber = SchemaInspector::hasColumn('estate_sale_customers', 'queue_number');
        $hasCheckedIn = SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at');
        $hasCheckedOut = SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at');
        $hasSubscribes = SchemaInspector::hasColumn('estate_sale_customers', 'subscribes_to_future_sales');
        $hasContactMethod = SchemaInspector::hasColumn('estate_sale_customers', 'future_sales_contact_method');
        $queueSql = $hasQueueNumber ? 'esc.queue_number,' : '0 AS queue_number,';
        $checkedInSql = $hasCheckedIn ? 'esc.checked_in_at,' : 'NULL AS checked_in_at,';
        $checkedOutSql = $hasCheckedOut ? 'esc.checked_out_at,' : 'NULL AS checked_out_at,';
        $subscribesSql = $hasSubscribes ? 'esc.subscribes_to_future_sales,' : '0 AS subscribes_to_future_sales,';
        $contactMethodSql = $hasContactMethod ? 'esc.future_sales_contact_method,' : 'NULL AS future_sales_contact_method,';

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
                    {$subscribesSql}
                    {$contactMethodSql}
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

        if (self::useCustomerMemberships()) {
            $sql = 'UPDATE estate_sale_customer_memberships
                    SET deleted_at = NOW(),
                        deleted_by = :deleted_by,
                        updated_by = :updated_by,
                        updated_at = NOW()
                    WHERE business_id = :business_id
                      AND estate_sale_id = :estate_sale_id
                      AND customer_id = :customer_id
                      AND deleted_at IS NULL';
            $stmt = Database::connection()->prepare($sql);

            return $stmt->execute([
                'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
                'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                'business_id' => $businessId,
                'estate_sale_id' => $estateSaleId,
                'customer_id' => $customerId,
            ]);
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

        if (self::useCustomerMemberships()) {
            $sql = 'SELECT COALESCE(MAX(queue_number), 0) + 1
                    FROM estate_sale_customer_memberships
                    WHERE business_id = :business_id
                      AND estate_sale_id = :estate_sale_id
                      AND deleted_at IS NULL';
            $stmt = Database::connection()->prepare($sql);
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
            $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
            $stmt->execute();

            return max(1, (int) $stmt->fetchColumn());
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
            || (!self::useCustomerMemberships() && !SchemaInspector::hasColumn('estate_sale_customers', 'checked_in_at'))
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
            if (self::useCustomerMemberships()) {
                $updateSql = 'UPDATE estate_sale_customer_memberships
                              SET checked_in_at = NOW(),
                                  checked_out_at = NULL,
                                  updated_by = :updated_by,
                                  updated_at = NOW()
                              WHERE business_id = :business_id
                                AND estate_sale_id = :estate_sale_id
                                AND customer_id = :customer_id
                                AND deleted_at IS NULL';
                $updateParams = [
                    'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                    'business_id' => $businessId,
                    'estate_sale_id' => $estateSaleId,
                    'customer_id' => $customerId,
                ];
            } else {
                $updateSql = 'UPDATE estate_sale_customers
                              SET checked_in_at = NOW(),
                                  checked_out_at = NULL,
                                  updated_by = :updated_by,
                                  updated_at = NOW()
                              WHERE business_id = :business_id
                                AND estate_sale_id = :estate_sale_id
                                AND id = :id
                                AND deleted_at IS NULL';
                $updateParams = [
                    'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                    'business_id' => $businessId,
                    'estate_sale_id' => $estateSaleId,
                    'id' => $customerId,
                ];
            }

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

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
            || (!self::useCustomerMemberships() && !SchemaInspector::hasColumn('estate_sale_customers', 'checked_out_at'))
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
            if (self::useCustomerMemberships()) {
                $updateSql = 'UPDATE estate_sale_customer_memberships
                              SET checked_out_at = NOW(),
                                  updated_by = :updated_by,
                                  updated_at = NOW()
                              WHERE business_id = :business_id
                                AND estate_sale_id = :estate_sale_id
                                AND customer_id = :customer_id
                                AND deleted_at IS NULL
                                AND checked_in_at IS NOT NULL
                                AND checked_out_at IS NULL';
                $updateParams = [
                    'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                    'business_id' => $businessId,
                    'estate_sale_id' => $estateSaleId,
                    'customer_id' => $customerId,
                ];
            } else {
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
                $updateParams = [
                    'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                    'business_id' => $businessId,
                    'estate_sale_id' => $estateSaleId,
                    'id' => $customerId,
                ];
            }

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

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
        $paymentMethodSql = SchemaInspector::hasColumn('sales', 'payment_method')
            ? 's.payment_method'
            : "'" . Sale::PAYMENT_METHOD_DEFAULT . "' AS payment_method";

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
                    {$grossSql} AS gross_amount,
                    {$paymentMethodSql}
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
            'subscribes_to_future_sales' => !empty($customer['subscribes_to_future_sales']),
            'future_sales_contact_method' => self::normalizeFutureSalesContactMethod($customer['future_sales_contact_method'] ?? null) ?? '',
            'future_sales_contact_method_label' => self::futureSalesContactMethodLabel($customer['future_sales_contact_method'] ?? null),
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
        if (
            $estateSaleId > 0
            && (
                !array_key_exists('client_percentage', $estateSale)
                || !array_key_exists('client_split_type', $estateSale)
            )
        ) {
            $loaded = self::findForBusiness($businessId, $estateSaleId);
            if ($loaded !== null) {
                $estateSale = array_merge($loaded, $estateSale);
            }
        }

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

        $clientShare = self::clientShareFromSales(
            $businessId,
            $estateSaleId,
            $clientPct,
            $splitType,
            $totalSales,
            $totalExpenses,
            $totalLabor
        );

        $ourShare = null;
        $splitBase = null;
        if ($clientShare !== null) {
            switch ($splitType) {
                case self::SPLIT_NET:
                    $splitBase = round($totalSales - $totalExpenses, 2);
                    $ourShare = round($splitBase - $clientShare, 2);
                    break;
                case self::SPLIT_LESS_LABOR:
                    $splitBase = round($totalSales - $totalLabor, 2);
                    $ourShare = round($totalSales - $clientShare - $totalExpenses - $totalLabor, 2);
                    break;
                case self::SPLIT_NET_TOTAL:
                    $splitBase = round($totalSales - $totalExpenses - $totalLabor, 2);
                    $ourShare = round($splitBase - $clientShare, 2);
                    break;
                case self::SPLIT_GROSS_TOTAL:
                default:
                    $splitBase = round($totalSales, 2);
                    $ourShare = round($totalSales - $clientShare - $totalExpenses, 2);
                    break;
            }
        }

        return [
            'total_sales' => round($totalSales, 2),
            'gross' => round($totalSales, 2),
            'total_expenses' => round($totalExpenses, 2),
            'total_labor' => $totalLabor,
            'client_percentage' => $clientPct,
            'client_split_type' => $splitType,
            'client_split_type_label' => self::clientSplitTypeLabel($splitType),
            'split_help_text' => self::clientSplitTypeHelpText($splitType),
            'split_base' => $splitBase,
            'client_share' => $clientShare,
            'our_share' => $ourShare,
            'net' => $ourShare,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @param array<string, mixed> $estateSale
     * @return array<int, array<string, mixed>>
     */
    public static function enrichSalesWithClientPercentage(array $sales, array $estateSale): array
    {
        $standard = self::normalizeClientPercentage($estateSale['client_percentage'] ?? null);

        foreach ($sales as $index => $sale) {
            if (!is_array($sale)) {
                continue;
            }

            $override = self::normalizeClientPercentage($sale['client_percentage'] ?? null);
            $sales[$index]['estate_client_percentage'] = $standard;
            $sales[$index]['effective_client_percentage'] = $override ?? $standard;
            $sales[$index]['client_percentage_is_override'] = $override !== null;
        }

        return $sales;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    public static function enrichSaleRecordsWithClientPercentage(array $records): array
    {
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $override = self::normalizeClientPercentage($record['client_percentage'] ?? null);
            $standard = self::normalizeClientPercentage($record['estate_client_percentage'] ?? null);
            $records[$index]['estate_client_percentage'] = $standard;
            $records[$index]['effective_client_percentage'] = $override ?? $standard;
            $records[$index]['client_percentage_is_override'] = $override !== null;
        }

        return $records;
    }

    /**
     * @return array<string, float|null>
     */
    public static function saleClientPercentageMeta(array $sale, array $estateSale): array
    {
        $override = self::normalizeClientPercentage($sale['client_percentage'] ?? null);
        $standard = self::normalizeClientPercentage($estateSale['client_percentage'] ?? null);

        return [
            'estate_client_percentage' => $standard,
            'effective_client_percentage' => $override ?? $standard,
            'client_percentage_is_override' => $override !== null,
        ];
    }

    private static function clientShareFromSales(
        int $businessId,
        int $estateSaleId,
        ?float $defaultPct,
        string $splitType,
        float $totalSales,
        float $totalExpenses,
        float $totalLabor
    ): ?float {
        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'estate_sale_id')) {
            return null;
        }

        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $clientPctSql = SchemaInspector::hasColumn('sales', 'client_percentage')
            ? 's.client_percentage'
            : 'NULL';

        $where = ['s.estate_sale_id = :estate_sale_id'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = "SELECT {$grossSql} AS gross_amount, {$clientPctSql}
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $clientShare = 0.0;
        $hasApplicablePct = false;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $gross = round((float) ($row['gross_amount'] ?? 0), 2);
            $pct = self::normalizeClientPercentage($row['client_percentage'] ?? null) ?? $defaultPct;
            if ($pct === null) {
                continue;
            }

            $hasApplicablePct = true;
            $saleBase = match ($splitType) {
                self::SPLIT_NET => $totalSales > 0
                    ? round(($gross / $totalSales) * ($totalSales - $totalExpenses), 2)
                    : 0.0,
                self::SPLIT_LESS_LABOR => $totalSales > 0
                    ? round(($gross / $totalSales) * ($totalSales - $totalLabor), 2)
                    : 0.0,
                self::SPLIT_NET_TOTAL => $totalSales > 0
                    ? round(($gross / $totalSales) * ($totalSales - $totalExpenses - $totalLabor), 2)
                    : 0.0,
                default => $gross,
            };

            $clientShare += round($saleBase * ($pct / 100), 2);
        }

        return $hasApplicablePct ? round($clientShare, 2) : null;
    }

    /**
     * Dashboard MTD/YTD totals: gross = on-site sales; net = our share after the agreed split.
     *
     * @return array{count:int, mtd_count:int, ytd_count:int, mtd_gross:float, mtd_net:float, ytd_gross:float, ytd_net:float}
     */
    public static function dashboardSummary(int $businessId): array
    {
        $mtd = self::periodFinancialTotals($businessId, date('Y-m-01'), date('Y-m-d'));
        $ytd = self::periodFinancialTotals($businessId, date('Y-01-01'), date('Y-m-d'));

        return [
            'count' => (int) ($ytd['estate_sale_count'] ?? 0),
            'mtd_count' => (int) ($mtd['transaction_count'] ?? 0),
            'ytd_count' => (int) ($ytd['transaction_count'] ?? 0),
            'mtd_gross' => (float) ($mtd['gross'] ?? 0),
            'mtd_net' => (float) ($mtd['net'] ?? 0),
            'ytd_gross' => (float) ($ytd['gross'] ?? 0),
            'ytd_net' => (float) ($ytd['net'] ?? 0),
        ];
    }

    /**
     * Period totals for estate on-site sales using split-based net (our share).
     *
     * @return array{gross:float, net:float, transaction_count:int, estate_sale_count:int}
     */
    public static function periodFinancialTotals(int $businessId, string $fromDate, string $toDate): array
    {
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);
        $empty = [
            'gross' => 0.0,
            'net' => 0.0,
            'transaction_count' => 0,
            'estate_sale_count' => 0,
        ];

        if (
            $businessId <= 0
            || $fromDate === ''
            || $toDate === ''
            || !SchemaInspector::hasTable('sales')
            || !SchemaInspector::hasColumn('sales', 'estate_sale_id')
        ) {
            return $empty;
        }

        $periodByEstateSale = self::salesTotalsByEstateSaleInPeriod($businessId, $fromDate, $toDate);
        if ($periodByEstateSale === []) {
            return $empty;
        }

        $gross = 0.0;
        $net = 0.0;
        $transactionCount = 0;

        foreach ($periodByEstateSale as $estateSaleId => $periodTotals) {
            if (!is_array($periodTotals)) {
                continue;
            }

            $periodGross = (float) ($periodTotals['gross'] ?? 0);
            $periodCount = (int) ($periodTotals['count'] ?? 0);
            if ($periodGross <= 0.0001 && $periodCount <= 0) {
                continue;
            }

            $gross += $periodGross;
            $transactionCount += $periodCount;

            $estateSale = self::findForBusiness($businessId, (int) $estateSaleId) ?? [];
            $financial = self::financialSummary($businessId, (int) $estateSaleId, $estateSale);
            $transactionNet = self::transactionNetShare($periodGross, $financial);
            if ($transactionNet !== null) {
                $net += $transactionNet;
            }
        }

        return [
            'gross' => round($gross, 2),
            'net' => round($net, 2),
            'transaction_count' => $transactionCount,
            'estate_sale_count' => count($periodByEstateSale),
        ];
    }

    /**
     * Per-estate-sale totals for on-site transactions in a date range.
     *
     * @return list<array{estate_sale_id:int, title:string, transaction_count:int, gross:float, net:float}>
     */
    public static function periodBreakdownForRange(int $businessId, string $fromDate, string $toDate): array
    {
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);
        if ($businessId <= 0 || $fromDate === '' || $toDate === '') {
            return [];
        }

        $periodByEstateSale = self::salesTotalsByEstateSaleInPeriod($businessId, $fromDate, $toDate);
        if ($periodByEstateSale === []) {
            return [];
        }

        $rows = [];
        foreach ($periodByEstateSale as $estateSaleId => $periodTotals) {
            if (!is_array($periodTotals)) {
                continue;
            }

            $estateSaleId = (int) $estateSaleId;
            if ($estateSaleId <= 0) {
                continue;
            }

            $periodGross = (float) ($periodTotals['gross'] ?? 0);
            $periodCount = (int) ($periodTotals['count'] ?? 0);
            if ($periodGross <= 0.0001 && $periodCount <= 0) {
                continue;
            }

            $estateSale = self::findForBusiness($businessId, $estateSaleId) ?? [];
            $title = trim((string) ($estateSale['title'] ?? ''));
            if ($title === '') {
                $title = 'Estate sale #' . (string) $estateSaleId;
            }

            $financial = self::financialSummary($businessId, $estateSaleId, $estateSale);
            $transactionNet = self::transactionNetShare($periodGross, $financial);

            $rows[] = [
                'estate_sale_id' => $estateSaleId,
                'title' => $title,
                'transaction_count' => $periodCount,
                'gross' => round($periodGross, 2),
                'net' => round($transactionNet ?? 0.0, 2),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $grossCmp = ($b['gross'] ?? 0) <=> ($a['gross'] ?? 0);
            if ($grossCmp !== 0) {
                return $grossCmp;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $rows;
    }

    /**
     * Allocate our split share to a single on-site transaction.
     */
    public static function transactionNetShare(float $transactionGross, array $financial): ?float
    {
        if ($transactionGross <= 0.0001) {
            return 0.0;
        }

        $totalSales = (float) ($financial['total_sales'] ?? $financial['gross'] ?? 0);
        $ourShare = $financial['our_share'] ?? $financial['net'] ?? null;
        if ($totalSales <= 0.0001 || $ourShare === null) {
            return null;
        }

        return round($transactionGross * ((float) $ourShare / $totalSales), 2);
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    public static function applySplitNetToSalesRecords(int $businessId, array $records): array
    {
        if ($records === []) {
            return [];
        }

        $financialCache = [];
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $estateSaleId = (int) ($record['estate_sale_id'] ?? 0);
            if ($estateSaleId <= 0) {
                continue;
            }

            if (!isset($financialCache[$estateSaleId])) {
                $estateSale = self::findForBusiness($businessId, $estateSaleId) ?? [];
                $financialCache[$estateSaleId] = self::financialSummary($businessId, $estateSaleId, $estateSale);
            }

            $gross = (float) ($record['gross_amount'] ?? 0);
            $shareNet = self::transactionNetShare($gross, $financialCache[$estateSaleId]);
            $records[$index]['net_amount'] = $shareNet;
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function enrichIndexRowsWithFinancials(int $businessId, array $rows): array
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $estateSaleId = (int) ($row['id'] ?? 0);
            if ($estateSaleId <= 0) {
                continue;
            }

            $estateSale = self::findForBusiness($businessId, $estateSaleId);
            if ($estateSale === null) {
                continue;
            }

            if (array_key_exists('customer_count', $row)) {
                $estateSale['customer_count'] = (int) ($row['customer_count'] ?? 0);
            }

            $financial = self::financialSummary($businessId, $estateSaleId, $estateSale);
            $rows[$index]['gross_total'] = (float) ($financial['gross'] ?? 0);
            $rows[$index]['net_total'] = $financial['net'];
        }

        return $rows;
    }

    /**
     * @return array<int, array{gross: float, count: int}>
     */
    private static function salesTotalsByEstateSaleInPeriod(int $businessId, string $fromDate, string $toDate): array
    {
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $filterDateSql = SchemaInspector::hasColumn('sales', 'sale_date')
            ? (SchemaInspector::hasColumn('sales', 'created_at')
                ? 'DATE(COALESCE(s.sale_date, s.created_at))'
                : 'DATE(s.sale_date)')
            : (SchemaInspector::hasColumn('sales', 'created_at') ? 'DATE(s.created_at)' : "'0000-00-00'");

        $where = [
            's.estate_sale_id IS NOT NULL',
            's.estate_sale_id > 0',
            "{$filterDateSql} BETWEEN :from_date AND :to_date",
        ];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 's.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $sql = "SELECT
                    s.estate_sale_id,
                    COUNT(*) AS item_count,
                    COALESCE(SUM({$grossSql}), 0) AS gross_total
                FROM sales s
                WHERE " . implode(' AND ', $where) . '
                GROUP BY s.estate_sale_id';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':from_date', $fromDate, \PDO::PARAM_STR);
        $stmt->bindValue(':to_date', $toDate, \PDO::PARAM_STR);
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
            $estateSaleId = (int) ($row['estate_sale_id'] ?? 0);
            if ($estateSaleId <= 0) {
                continue;
            }
            $out[$estateSaleId] = [
                'gross' => (float) ($row['gross_total'] ?? 0),
                'count' => (int) ($row['item_count'] ?? 0),
            ];
        }

        return $out;
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
        $clientPctSql = SchemaInspector::hasColumn('sales', 'client_percentage') ? 's.client_percentage' : 'NULL AS client_percentage';
        $paymentMethodSql = SchemaInspector::hasColumn('sales', 'payment_method')
            ? 's.payment_method'
            : "'" . Sale::PAYMENT_METHOD_DEFAULT . "' AS payment_method";
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
                    {$clientPctSql},
                    {$paymentMethodSql},
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

    public static function unassignEmployee(int $businessId, int $estateSaleId, int $employeeId, int $actorUserId): bool
    {
        if ($estateSaleId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('estate_sale_employee_assignments')) {
            return false;
        }

        if (self::findAssignedEmployee($businessId, $estateSaleId, $employeeId) === null) {
            return false;
        }

        if (TimeEntry::hasActiveEntryForEstateSale($businessId, $estateSaleId, $employeeId)) {
            return false;
        }

        $sets = [
            'deleted_at = NOW()',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ];
        if (SchemaInspector::hasColumn('estate_sale_employee_assignments', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
        }

        $sql = 'UPDATE estate_sale_employee_assignments
                SET ' . implode(', ', $sets) . '
                WHERE business_id = :business_id
                  AND estate_sale_id = :estate_sale_id
                  AND employee_id = :employee_id
                  AND deleted_at IS NULL';

        $params = [
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'estate_sale_id' => $estateSaleId,
            'employee_id' => $employeeId,
        ];
        if (SchemaInspector::hasColumn('estate_sale_employee_assignments', 'deleted_by')) {
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute($params);
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

    /**
     * @return array<string, mixed>
     */
    public static function metricsReport(int $businessId, int $estateSaleId, array $estateSale): array
    {
        $saleDays = self::metricsSaleDays($businessId, $estateSaleId, $estateSale);
        $allSales = self::enrichSalesWithClientPercentage(self::sales($businessId, $estateSaleId, 5000, 0), $estateSale);
        $allExpenses = self::expenses($businessId, $estateSaleId, 2000);
        $allTimeLogs = self::timeLogsByEstateSale($businessId, $estateSaleId, 2000);
        $visitStats = self::metricsVisitStats($businessId, $estateSaleId);
        $customerCountsByDay = self::metricsCustomerCountsByDay($businessId, $estateSaleId, $allSales);

        $overallFinancial = self::financialSummary($businessId, $estateSaleId, $estateSale);
        $overall = self::metricsSnapshot(
            null,
            $allSales,
            $allExpenses,
            $allTimeLogs,
            $visitStats,
            $customerCountsByDay,
            $estateSale,
            $overallFinancial,
            (int) self::customersCount($businessId, $estateSaleId)
        );

        $days = [];
        $dayNumber = 0;
        foreach ($saleDays as $day) {
            $dayNumber++;
            $days[] = array_merge(
                self::metricsSnapshot(
                    $day,
                    $allSales,
                    $allExpenses,
                    $allTimeLogs,
                    $visitStats,
                    $customerCountsByDay,
                    $estateSale,
                    null,
                    0
                ),
                [
                    'date' => $day,
                    'label' => date('D, M j, Y', strtotime($day . ' 12:00:00')),
                    'day_number' => $dayNumber,
                ]
            );
        }

        $splitLabels = [];
        $splitCounts = [];
        $splitGross = [];
        foreach ($overall['split_breakdown'] as $row) {
            $splitLabels[] = (string) ($row['label'] ?? '');
            $splitCounts[] = (int) ($row['sale_count'] ?? 0);
            $splitGross[] = round((float) ($row['gross_total'] ?? 0), 2);
        }

        $dailyLabels = [];
        $dailyGross = [];
        $dailyCustomers = [];
        foreach ($days as $dayRow) {
            $dailyLabels[] = date('M j', strtotime((string) ($dayRow['date'] ?? '')));
            $dailyGross[] = round((float) ($dayRow['financial']['gross'] ?? 0), 2);
            $dailyCustomers[] = (int) ($dayRow['customer_count'] ?? 0);
        }

        return [
            'sale_days' => $saleDays,
            'overall' => $overall,
            'days' => $days,
            'labor' => self::metricsLaborBreakdown($saleDays, $allTimeLogs),
            'charts' => [
                'split_labels' => $splitLabels,
                'split_counts' => $splitCounts,
                'split_gross' => $splitGross,
                'daily_labels' => $dailyLabels,
                'daily_gross' => $dailyGross,
                'daily_customers' => $dailyCustomers,
            ],
        ];
    }

    /**
     * @param array<int, string> $saleDays
     * @param array<int, array<string, mixed>> $timeLogs
     * @return array<string, mixed>
     */
    private static function metricsLaborBreakdown(array $saleDays, array $timeLogs): array
    {
        $dayEmployee = [];
        $employeeTotals = [];
        $grandTotal = 0.0;

        foreach ($timeLogs as $log) {
            if (!is_array($log)) {
                continue;
            }

            $employeeId = (int) ($log['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            $day = self::metricsDateOnly($log['clock_in_at'] ?? null);
            if ($day === '') {
                continue;
            }

            $name = trim((string) ($log['employee_name'] ?? ''));
            if ($name === '') {
                $name = 'Employee #' . (string) $employeeId;
            }

            $minutes = max(0, (int) ($log['duration_minutes'] ?? 0));
            $amount = round((float) ($log['labor_cost'] ?? 0), 2);
            $grandTotal += $amount;

            $dayKey = $day . ':' . (string) $employeeId;
            if (!isset($dayEmployee[$dayKey])) {
                $dayEmployee[$dayKey] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $name,
                    'minutes' => 0,
                    'amount_owed' => 0.0,
                    'entry_count' => 0,
                ];
            }
            $dayEmployee[$dayKey]['minutes'] += $minutes;
            $dayEmployee[$dayKey]['amount_owed'] += $amount;
            $dayEmployee[$dayKey]['entry_count']++;

            if (!isset($employeeTotals[$employeeId])) {
                $employeeTotals[$employeeId] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $name,
                    'minutes' => 0,
                    'amount_owed' => 0.0,
                    'entry_count' => 0,
                ];
            }
            $employeeTotals[$employeeId]['minutes'] += $minutes;
            $employeeTotals[$employeeId]['amount_owed'] += $amount;
            $employeeTotals[$employeeId]['entry_count']++;
        }

        $finalizeEmployeeRow = static function (array $row): array {
            $minutes = (int) ($row['minutes'] ?? 0);
            $amount = round((float) ($row['amount_owed'] ?? 0), 2);
            $hours = $minutes > 0 ? round($minutes / 60, 2) : 0.0;

            return [
                'employee_id' => (int) ($row['employee_id'] ?? 0),
                'employee_name' => (string) ($row['employee_name'] ?? ''),
                'minutes' => $minutes,
                'hours' => $hours,
                'hours_display' => self::formatVisitDuration($minutes),
                'hourly_rate' => $hours > 0 ? round($amount / $hours, 2) : null,
                'amount_owed' => $amount,
                'entry_count' => (int) ($row['entry_count'] ?? 0),
            ];
        };

        $days = [];
        $dayNumber = 0;
        foreach ($saleDays as $day) {
            $dayNumber++;
            $employees = [];
            foreach ($dayEmployee as $key => $row) {
                if (!str_starts_with($key, $day . ':')) {
                    continue;
                }
                $employees[] = $finalizeEmployeeRow($row);
            }

            usort($employees, static function (array $a, array $b): int {
                return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
            });

            $dayTotal = 0.0;
            foreach ($employees as $employee) {
                $dayTotal += (float) ($employee['amount_owed'] ?? 0);
            }

            $days[] = [
                'date' => $day,
                'label' => date('D, M j, Y', strtotime($day . ' 12:00:00')),
                'day_number' => $dayNumber,
                'employees' => $employees,
                'day_total' => round($dayTotal, 2),
            ];
        }

        $employeesOverall = array_map($finalizeEmployeeRow, array_values($employeeTotals));
        usort($employeesOverall, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
        });
        foreach ($employeesOverall as $index => $row) {
            $employeesOverall[$index]['amount_owed'] = round((float) ($row['amount_owed'] ?? 0), 2);
        }

        return [
            'days' => $days,
            'employees' => $employeesOverall,
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function metricsSaleDays(int $businessId, int $estateSaleId, array $estateSale): array
    {
        $start = self::metricsDateOnly($estateSale['start_at'] ?? null);
        $end = self::metricsDateOnly($estateSale['end_at'] ?? null);

        if ($start !== '' && $end !== '') {
            return self::metricsDatesBetweenInclusive($start, $end);
        }
        if ($start !== '') {
            return [$start];
        }
        if ($end !== '') {
            return [$end];
        }

        $dates = [];
        foreach (self::sales($businessId, $estateSaleId, 5000, 0) as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            $day = self::metricsDateOnly($sale['sale_date'] ?? null);
            if ($day !== '') {
                $dates[$day] = true;
            }
        }

        if ($dates === [] && SchemaInspector::hasTable('estate_sale_customer_visits')) {
            $sql = 'SELECT DISTINCT DATE(checked_in_at) AS visit_day
                    FROM estate_sale_customer_visits
                    WHERE business_id = :business_id AND estate_sale_id = :estate_sale_id
                    ORDER BY visit_day ASC';
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['business_id' => $businessId, 'estate_sale_id' => $estateSaleId]);
            foreach ($stmt->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $day = trim((string) ($row['visit_day'] ?? ''));
                if ($day !== '') {
                    $dates[$day] = true;
                }
            }
        }

        if ($dates === []) {
            return [date('Y-m-d')];
        }

        $sorted = array_keys($dates);
        sort($sorted);

        return self::metricsDatesBetweenInclusive($sorted[0], $sorted[array_key_last($sorted)]);
    }
    /**
     * @param array<int, array<string, mixed>> $sales
     * @param array<int, array<string, mixed>> $expenses
     * @param array<int, array<string, mixed>> $timeLogs
     * @param array<string, mixed> $visitStats
     * @param array<string, int> $customerCountsByDay
     * @param array<string, mixed> $estateSale
     * @param array<string, mixed>|null $financialPrefetched
     * @return array<string, mixed>
     */
    private static function metricsSnapshot(
        ?string $day,
        array $sales,
        array $expenses,
        array $timeLogs,
        array $visitStats,
        array $customerCountsByDay,
        array $estateSale,
        ?array $financialPrefetched,
        int $overallCustomerCount
    ): array {
        $filteredSales = self::metricsFilterSalesByDay($sales, $day);
        $saleCount = count($filteredSales);
        $grossTotal = 0.0;
        foreach ($filteredSales as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            $grossTotal += (float) ($sale['gross_amount'] ?? 0);
        }
        $grossTotal = round($grossTotal, 2);
        $avgSalePrice = $saleCount > 0 ? round($grossTotal / $saleCount, 2) : null;

        $splitBreakdown = self::metricsSplitBreakdown($filteredSales, $estateSale);
        $totalExpenses = round(self::metricsSumExpensesForDay($expenses, $day), 2);
        $totalLabor = round(self::metricsSumLaborForDay($timeLogs, $day), 2);

        $clientPct = self::normalizeClientPercentage($estateSale['client_percentage'] ?? null);
        $splitType = self::normalizeClientSplitType($estateSale['client_split_type'] ?? null);
        $clientShare = self::metricsClientShareFromRows($filteredSales, $clientPct, $splitType, $grossTotal, $totalExpenses, $totalLabor);
        $ourShare = self::metricsOurShareFromParts($splitType, $grossTotal, $totalExpenses, $totalLabor, $clientShare);

        $financial = $financialPrefetched ?? [
            'gross' => $grossTotal,
            'total_sales' => $grossTotal,
            'total_expenses' => $totalExpenses,
            'total_labor' => $totalLabor,
            'client_share' => $clientShare,
            'our_share' => $ourShare,
            'net' => $ourShare,
            'client_split_type' => $splitType,
            'client_split_type_label' => self::clientSplitTypeLabel($splitType),
            'client_percentage' => $clientPct,
        ];

        $waitKey = $day ?? '__overall__';
        $shoppingKey = $day ?? '__overall__';
        $avgWait = $visitStats['wait'][$waitKey] ?? null;
        $avgShopping = $visitStats['shopping'][$shoppingKey] ?? null;

        return [
            'customer_count' => $day === null ? $overallCustomerCount : (int) ($customerCountsByDay[$day] ?? 0),
            'sale_count' => $saleCount,
            'avg_sale_price' => $avgSalePrice,
            'avg_wait_minutes' => $avgWait,
            'avg_shopping_minutes' => $avgShopping,
            'avg_wait_display' => self::formatVisitDuration($avgWait !== null ? (int) round($avgWait) : null),
            'avg_shopping_display' => self::formatVisitDuration($avgShopping !== null ? (int) round($avgShopping) : null),
            'split_breakdown' => $splitBreakdown,
            'financial' => $financial,
            'profit_steps' => self::metricsProfitSteps($financial, $estateSale),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<int, array<string, mixed>>
     */
    private static function metricsFilterSalesByDay(array $sales, ?string $day): array
    {
        if ($day === null) {
            return $sales;
        }

        $filtered = [];
        foreach ($sales as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            if (self::metricsDateOnly($sale['sale_date'] ?? null) === $day) {
                $filtered[] = $sale;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<int, array<string, mixed>>
     */
    private static function metricsSplitBreakdown(array $sales, array $estateSale): array
    {
        $standard = self::normalizeClientPercentage($estateSale['client_percentage'] ?? null);
        $groups = [];

        foreach ($sales as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            $override = self::normalizeClientPercentage($sale['client_percentage'] ?? null);
            $effective = $override ?? $standard;
            $key = $effective !== null ? number_format($effective, 2, '.', '') : 'unset';
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'percentage' => $effective,
                    'label' => $effective !== null ? format_client_percentage($effective) : 'Not set',
                    'sale_count' => 0,
                    'gross_total' => 0.0,
                ];
            }
            $groups[$key]['sale_count']++;
            $groups[$key]['gross_total'] += (float) ($sale['gross_amount'] ?? 0);
        }

        $rows = array_values($groups);
        usort($rows, static function (array $a, array $b): int {
            return ((float) ($b['percentage'] ?? -1)) <=> ((float) ($a['percentage'] ?? -1));
        });
        foreach ($rows as $index => $row) {
            $rows[$index]['gross_total'] = round((float) ($row['gross_total'] ?? 0), 2);
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     */
    private static function metricsClientShareFromRows(
        array $sales,
        ?float $defaultPct,
        string $splitType,
        float $totalSales,
        float $totalExpenses,
        float $totalLabor
    ): ?float {
        if ($sales === []) {
            return null;
        }

        $clientShare = 0.0;
        $hasApplicablePct = false;

        foreach ($sales as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            $gross = round((float) ($sale['gross_amount'] ?? 0), 2);
            $pct = self::normalizeClientPercentage($sale['client_percentage'] ?? null) ?? $defaultPct;
            if ($pct === null) {
                continue;
            }

            $hasApplicablePct = true;
            $saleBase = match ($splitType) {
                self::SPLIT_NET => $totalSales > 0
                    ? round(($gross / $totalSales) * ($totalSales - $totalExpenses), 2)
                    : 0.0,
                self::SPLIT_LESS_LABOR => $totalSales > 0
                    ? round(($gross / $totalSales) * ($totalSales - $totalLabor), 2)
                    : 0.0,
                self::SPLIT_NET_TOTAL => $totalSales > 0
                    ? round(($gross / $totalSales) * ($totalSales - $totalExpenses - $totalLabor), 2)
                    : 0.0,
                default => $gross,
            };
            $clientShare += round($saleBase * ($pct / 100), 2);
        }

        return $hasApplicablePct ? round($clientShare, 2) : null;
    }

    private static function metricsOurShareFromParts(
        string $splitType,
        float $totalSales,
        float $totalExpenses,
        float $totalLabor,
        ?float $clientShare
    ): ?float {
        if ($clientShare === null) {
            return null;
        }

        return match ($splitType) {
            self::SPLIT_NET => round(($totalSales - $totalExpenses) - $clientShare, 2),
            self::SPLIT_LESS_LABOR => round($totalSales - $clientShare - $totalExpenses - $totalLabor, 2),
            self::SPLIT_NET_TOTAL => round(($totalSales - $totalExpenses - $totalLabor) - $clientShare, 2),
            default => round($totalSales - $clientShare - $totalExpenses, 2),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $expenses
     */
    private static function metricsSumExpensesForDay(array $expenses, ?string $day): float
    {
        $total = 0.0;
        foreach ($expenses as $expense) {
            if (!is_array($expense)) {
                continue;
            }
            $expenseDay = self::metricsDateOnly($expense['expense_date'] ?? ($expense['created_at'] ?? null));
            if ($day !== null && $expenseDay !== $day) {
                continue;
            }
            $total += (float) ($expense['amount'] ?? 0);
        }

        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $timeLogs
     */
    private static function metricsSumLaborForDay(array $timeLogs, ?string $day): float
    {
        $total = 0.0;
        foreach ($timeLogs as $log) {
            if (!is_array($log)) {
                continue;
            }
            $logDay = self::metricsDateOnly($log['clock_in_at'] ?? null);
            if ($day !== null && $logDay !== $day) {
                continue;
            }
            $total += (float) ($log['labor_cost'] ?? 0);
        }

        return $total;
    }
    /**
     * @return array{wait: array<string, float>, shopping: array<string, float>}
     */
    private static function metricsVisitStats(int $businessId, int $estateSaleId): array
    {
        $wait = ['__overall__' => 0.0];
        $shopping = ['__overall__' => 0.0];
        $waitCounts = ['__overall__' => 0];
        $shoppingCounts = ['__overall__' => 0];

        if ($estateSaleId <= 0 || !SchemaInspector::hasTable('estate_sale_customers')) {
            return ['wait' => [], 'shopping' => []];
        }

        if (self::useCustomerMemberships()) {
            $sql = 'SELECT m.created_at, m.checked_in_at, m.checked_out_at
                    FROM estate_sale_customer_memberships m
                    WHERE m.estate_sale_id = :estate_sale_id
                      AND m.business_id = :business_id
                      AND m.deleted_at IS NULL';
        } else {
            $sql = 'SELECT esc.created_at, esc.checked_in_at, esc.checked_out_at
                    FROM estate_sale_customers esc
                    WHERE esc.estate_sale_id = :estate_sale_id';
            if (SchemaInspector::hasColumn('estate_sale_customers', 'business_id')) {
                $sql .= ' AND esc.business_id = :business_id';
            }
            if (SchemaInspector::hasColumn('estate_sale_customers', 'deleted_at')) {
                $sql .= ' AND esc.deleted_at IS NULL';
            }
        }

        $stmt = Database::connection()->prepare($sql);
        $params = ['estate_sale_id' => $estateSaleId];
        if (SchemaInspector::hasColumn('estate_sale_customers', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        $hasVisitsTable = SchemaInspector::hasTable('estate_sale_customer_visits');

        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $checkedIn = trim((string) ($row['checked_in_at'] ?? ''));
            $created = trim((string) ($row['created_at'] ?? ''));
            if ($checkedIn !== '' && $created !== '') {
                $waitMinutes = self::customerVisitDurationMinutes($created, $checkedIn);
                if ($waitMinutes !== null && $waitMinutes >= 0) {
                    $day = self::metricsDateOnly($checkedIn);
                    $wait['__overall__'] = ($wait['__overall__'] ?? 0) + $waitMinutes;
                    $waitCounts['__overall__'] = ($waitCounts['__overall__'] ?? 0) + 1;
                    if ($day !== '') {
                        $wait[$day] = ($wait[$day] ?? 0) + $waitMinutes;
                        $waitCounts[$day] = ($waitCounts[$day] ?? 0) + 1;
                    }
                }
            }

            if (!$hasVisitsTable) {
                $checkedOut = trim((string) ($row['checked_out_at'] ?? ''));
                if ($checkedIn !== '' && $checkedOut !== '') {
                    $shopMinutes = self::customerVisitDurationMinutes($checkedIn, $checkedOut);
                    if ($shopMinutes !== null && $shopMinutes >= 0) {
                        $day = self::metricsDateOnly($checkedIn);
                        $shopping['__overall__'] = ($shopping['__overall__'] ?? 0) + $shopMinutes;
                        $shoppingCounts['__overall__'] = ($shoppingCounts['__overall__'] ?? 0) + 1;
                        if ($day !== '') {
                            $shopping[$day] = ($shopping[$day] ?? 0) + $shopMinutes;
                            $shoppingCounts[$day] = ($shoppingCounts[$day] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        if ($hasVisitsTable) {
            $visitSql = 'SELECT v.checked_in_at, v.checked_out_at
                         FROM estate_sale_customer_visits v
                         WHERE v.estate_sale_id = :estate_sale_id';
            if (SchemaInspector::hasColumn('estate_sale_customer_visits', 'business_id')) {
                $visitSql .= ' AND v.business_id = :business_id';
            }
            $visitStmt = Database::connection()->prepare($visitSql);
            $visitStmt->execute($params);
            foreach ($visitStmt->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $checkedIn = trim((string) ($row['checked_in_at'] ?? ''));
                $checkedOut = trim((string) ($row['checked_out_at'] ?? ''));
                if ($checkedIn === '' || $checkedOut === '') {
                    continue;
                }
                $shopMinutes = self::customerVisitDurationMinutes($checkedIn, $checkedOut);
                if ($shopMinutes === null || $shopMinutes < 0) {
                    continue;
                }
                $day = self::metricsDateOnly($checkedIn);
                $shopping['__overall__'] = ($shopping['__overall__'] ?? 0) + $shopMinutes;
                $shoppingCounts['__overall__'] = ($shoppingCounts['__overall__'] ?? 0) + 1;
                if ($day !== '') {
                    $shopping[$day] = ($shopping[$day] ?? 0) + $shopMinutes;
                    $shoppingCounts[$day] = ($shoppingCounts[$day] ?? 0) + 1;
                }
            }
        }

        $waitAvg = [];
        foreach ($wait as $key => $sum) {
            $count = (int) ($waitCounts[$key] ?? 0);
            if ($count > 0) {
                $waitAvg[$key] = round($sum / $count, 1);
            }
        }

        $shoppingAvg = [];
        foreach ($shopping as $key => $sum) {
            $count = (int) ($shoppingCounts[$key] ?? 0);
            if ($count > 0) {
                $shoppingAvg[$key] = round($sum / $count, 1);
            }
        }

        return ['wait' => $waitAvg, 'shopping' => $shoppingAvg];
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<string, int>
     */
    private static function metricsCustomerCountsByDay(int $businessId, int $estateSaleId, array $sales): array
    {
        $counts = [];
        $add = static function (string $day, int $customerId) use (&$counts): void {
            if ($day === '' || $customerId <= 0) {
                return;
            }
            if (!isset($counts[$day])) {
                $counts[$day] = [];
            }
            $counts[$day][$customerId] = true;
        };

        if (SchemaInspector::hasTable('estate_sale_customers')) {
            $sql = 'SELECT id, created_at, checked_in_at FROM estate_sale_customers
                    WHERE estate_sale_id = :estate_sale_id';
            if (SchemaInspector::hasColumn('estate_sale_customers', 'business_id')) {
                $sql .= ' AND business_id = :business_id';
            }
            if (SchemaInspector::hasColumn('estate_sale_customers', 'deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }
            $stmt = Database::connection()->prepare($sql);
            $params = ['estate_sale_id' => $estateSaleId];
            if (SchemaInspector::hasColumn('estate_sale_customers', 'business_id')) {
                $params['business_id'] = $businessId;
            }
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $customerId = (int) ($row['id'] ?? 0);
                $add(self::metricsDateOnly($row['created_at'] ?? null), $customerId);
                $add(self::metricsDateOnly($row['checked_in_at'] ?? null), $customerId);
            }
        }

        foreach ($sales as $sale) {
            if (!is_array($sale)) {
                continue;
            }
            $customerId = (int) ($sale['estate_sale_customer_id'] ?? 0);
            $add(self::metricsDateOnly($sale['sale_date'] ?? null), $customerId);
        }

        $result = [];
        foreach ($counts as $day => $customerMap) {
            $result[$day] = count($customerMap);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $financial
     * @param array<string, mixed> $estateSale
     * @return array<int, array<string, mixed>>
     */
    private static function metricsProfitSteps(array $financial, array $estateSale): array
    {
        $splitType = self::normalizeClientSplitType($financial['client_split_type'] ?? ($estateSale['client_split_type'] ?? null));
        $splitLabel = self::clientSplitTypeLabel($splitType);
        $gross = round((float) ($financial['gross'] ?? $financial['total_sales'] ?? 0), 2);
        $expenses = round((float) ($financial['total_expenses'] ?? 0), 2);
        $labor = round((float) ($financial['total_labor'] ?? 0), 2);
        $clientShare = ($financial['client_share'] ?? null) !== null ? round((float) $financial['client_share'], 2) : null;
        $ourShare = ($financial['our_share'] ?? $financial['net'] ?? null) !== null ? round((float) ($financial['our_share'] ?? $financial['net']), 2) : null;

        $steps = [
            ['label' => 'Gross on-site sales', 'amount' => $gross, 'kind' => 'line'],
        ];

        if ($splitType === self::SPLIT_NET) {
            $steps[] = ['label' => 'Less total expenses', 'amount' => -$expenses, 'kind' => 'subtract'];
            if ($clientShare !== null) {
                $steps[] = ['label' => 'Client share (' . $splitLabel . ', per-sale %)', 'amount' => -$clientShare, 'kind' => 'subtract'];
            }
        } elseif ($splitType === self::SPLIT_LESS_LABOR) {
            $steps[] = ['label' => 'Less total labor', 'amount' => -$labor, 'kind' => 'subtract'];
            if ($clientShare !== null) {
                $steps[] = ['label' => 'Client share (' . $splitLabel . ', per-sale %)', 'amount' => -$clientShare, 'kind' => 'subtract'];
            }
            $steps[] = ['label' => 'Less total expenses', 'amount' => -$expenses, 'kind' => 'subtract'];
        } elseif ($splitType === self::SPLIT_NET_TOTAL) {
            $steps[] = ['label' => 'Less total expenses', 'amount' => -$expenses, 'kind' => 'subtract'];
            $steps[] = ['label' => 'Less total labor', 'amount' => -$labor, 'kind' => 'subtract'];
            if ($clientShare !== null) {
                $steps[] = ['label' => 'Client share (' . $splitLabel . ', per-sale %)', 'amount' => -$clientShare, 'kind' => 'subtract'];
            }
        } else {
            if ($clientShare !== null) {
                $steps[] = ['label' => 'Client share (' . $splitLabel . ', per-sale %)', 'amount' => -$clientShare, 'kind' => 'subtract'];
            }
            $steps[] = ['label' => 'Less total expenses', 'amount' => -$expenses, 'kind' => 'subtract'];
        }

        if ($ourShare !== null) {
            $steps[] = ['label' => 'Our profit (after split, labor & expenses)', 'amount' => $ourShare, 'kind' => 'total'];
        }

        return $steps;
    }

    private static function metricsDateOnly(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        $ts = strtotime($raw);

        return $ts === false ? '' : date('Y-m-d', $ts);
    }

    /**
     * @return array<int, string>
     */
    private static function metricsDatesBetweenInclusive(string $start, string $end): array
    {
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $days = [];
        $current = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);
        while ($current <= $last) {
            $days[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $days;
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

    public static function normalizeClientPercentage(mixed $value): ?float
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
