<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ClientDelivery
{
    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return ['need_to_schedule', 'scheduled', 'completed', 'cancelled'];
    }

    public static function findForBusiness(int $businessId, int $id): ?array
    {
        if (!SchemaInspector::hasTable('client_deliveries') || $businessId <= 0 || $id <= 0) {
            return null;
        }

        $sql = 'SELECT
                    d.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM client_deliveries d
                INNER JOIN clients c ON c.id = d.client_id
                    AND c.business_id = d.business_id
                    AND c.deleted_at IS NULL
                WHERE d.business_id = :business_id
                  AND d.id = :id
                  AND d.deleted_at IS NULL';

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
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'scheduled_at',
        string $sortDir = 'desc'
    ): array {
        if (!SchemaInspector::hasTable('client_deliveries')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $allowedStatuses = self::statusOptions();
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $where = [
            'd.business_id = :business_id',
            'd.deleted_at IS NULL',
        ];
        if ($status !== '') {
            $where[] = 'LOWER(d.status) = :status';
        }

        $hasAddressLine2 = SchemaInspector::hasColumn('client_deliveries', 'address_line2');
        $addr2Search = $hasAddressLine2
            ? ' OR COALESCE(d.address_line2, "") LIKE :query_like_2b'
            : '';

        $where[] = '(
            :query = ""
            OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :query_like_1
            OR COALESCE(d.address_line1, "") LIKE :query_like_2'
            . $addr2Search . '
            OR COALESCE(d.notes, "") LIKE :query_like_3
            OR CAST(d.id AS CHAR) LIKE :query_like_4
        )';

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'asc' ? 'ASC' : 'DESC';
        $sortMap = [
            'scheduled_at' => "(d.scheduled_at IS NULL) ASC, d.scheduled_at {$sortDir}, d.id {$sortDir}",
            'id' => "d.id {$sortDir}",
            'client_name' => "COALESCE(NULLIF(TRIM(CONCAT_WS(\" \", c.first_name, c.last_name)), \"\"), NULLIF(c.company_name, \"\"), CONCAT(\"Client #\", c.id)) {$sortDir}, d.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['scheduled_at'];

        $addressLine2Select = $hasAddressLine2 ? 'd.address_line2' : 'NULL AS address_line2';

        $sql = 'SELECT
                    d.id,
                    d.client_id,
                    d.scheduled_at,
                    d.end_at,
                    d.address_line1,
                    ' . $addressLine2Select . ',
                    d.city,
                    d.state,
                    d.postal_code,
                    d.status,
                    d.notes,
                    d.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM client_deliveries d
                INNER JOIN clients c ON c.id = d.client_id
                    AND c.business_id = d.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy . '
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        if ($hasAddressLine2) {
            $stmt->bindValue(':query_like_2b', $queryLike);
        }
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
        if (!SchemaInspector::hasTable('client_deliveries')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $allowedStatuses = self::statusOptions();
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $where = [
            'd.business_id = :business_id',
            'd.deleted_at IS NULL',
        ];
        if ($status !== '') {
            $where[] = 'LOWER(d.status) = :status';
        }

        $hasAddressLine2 = SchemaInspector::hasColumn('client_deliveries', 'address_line2');
        $addr2Search = $hasAddressLine2
            ? ' OR COALESCE(d.address_line2, "") LIKE :query_like_2b'
            : '';

        $where[] = '(
            :query = ""
            OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) LIKE :query_like_1
            OR COALESCE(d.address_line1, "") LIKE :query_like_2'
            . $addr2Search . '
            OR COALESCE(d.notes, "") LIKE :query_like_3
            OR CAST(d.id AS CHAR) LIKE :query_like_4
        )';

        $sql = 'SELECT COUNT(*) AS row_count
                FROM client_deliveries d
                INNER JOIN clients c ON c.id = d.client_id
                    AND c.business_id = d.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        if ($hasAddressLine2) {
            $stmt->bindValue(':query_like_2b', $queryLike);
        }
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function validate(array $data, int $businessId): array
    {
        $errors = [];
        $clientId = (int) ($data['client_id'] ?? 0);
        if ($clientId <= 0) {
            $errors['client_id'] = 'Select a client.';
        } elseif (Client::findForBusiness($businessId, $clientId) === null) {
            $errors['client_id'] = 'Client not found.';
        }

        $status = strtolower(trim((string) ($data['status'] ?? 'need_to_schedule')));
        if (!in_array($status, self::statusOptions(), true)) {
            $errors['status'] = 'Invalid status.';
        }

        $scheduledAt = self::normalizeDateTime((string) ($data['scheduled_at'] ?? ''));
        if ($status === 'need_to_schedule') {
            // Time not set yet
        } elseif ($status === 'scheduled' || $status === 'completed') {
            if ($scheduledAt === null) {
                $errors['scheduled_at'] = 'Enter a scheduled date and time.';
            }
        }
        // cancelled: scheduled time optional

        $addressLine1 = trim((string) ($data['address_line1'] ?? ''));
        if (strlen($addressLine1) > 190) {
            $errors['address_line1'] = 'Address line 1 is too long.';
        }

        $addressLine2 = trim((string) ($data['address_line2'] ?? ''));
        if (strlen($addressLine2) > 190) {
            $errors['address_line2'] = 'Address line 2 is too long.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('client_deliveries')) {
            return 0;
        }

        $status = strtolower(trim((string) ($data['status'] ?? 'need_to_schedule')));
        if (!in_array($status, self::statusOptions(), true)) {
            $status = 'need_to_schedule';
        }

        $scheduledAt = self::normalizeDateTime((string) ($data['scheduled_at'] ?? ''));
        if ($status === 'need_to_schedule') {
            $scheduledAt = null;
        }
        if (($status === 'scheduled' || $status === 'completed') && $scheduledAt === null) {
            return 0;
        }

        $hasAddressLine2 = SchemaInspector::hasColumn('client_deliveries', 'address_line2');
        $insertCols = 'business_id, client_id, scheduled_at, end_at, address_line1';
        $insertVals = ':business_id, :client_id, :scheduled_at, :end_at, :address_line1';
        if ($hasAddressLine2) {
            $insertCols .= ', address_line2';
            $insertVals .= ', :address_line2';
        }
        $insertCols .= ', city, state, postal_code, notes, status, created_by, updated_by, created_at, updated_at';
        $insertVals .= ', :city, :state, :postal_code, :notes, :status, :created_by, :updated_by, NOW(), NOW()';

        $sql = 'INSERT INTO client_deliveries (' . $insertCols . ') VALUES (' . $insertVals . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':client_id', (int) ($data['client_id'] ?? 0), \PDO::PARAM_INT);
        if ($scheduledAt === null) {
            $stmt->bindValue(':scheduled_at', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':scheduled_at', $scheduledAt);
        }
        $stmt->bindValue(':end_at', null, \PDO::PARAM_NULL);
        $stmt->bindValue(':address_line1', self::nullIfEmpty(trim((string) ($data['address_line1'] ?? ''))));
        if ($hasAddressLine2) {
            $stmt->bindValue(':address_line2', self::nullIfEmpty(trim((string) ($data['address_line2'] ?? ''))));
        }
        $stmt->bindValue(':city', self::nullIfEmpty(trim((string) ($data['city'] ?? ''))));
        $stmt->bindValue(':state', self::nullIfEmpty(trim((string) ($data['state'] ?? ''))));
        $stmt->bindValue(':postal_code', self::nullIfEmpty(trim((string) ($data['postal_code'] ?? ''))));
        $stmt->bindValue(':notes', self::nullIfEmpty(trim((string) ($data['notes'] ?? ''))));
        $stmt->bindValue(':status', $status);
        $uid = $actorUserId > 0 ? $actorUserId : null;
        $stmt->bindValue(':created_by', $uid, $uid === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $uid, $uid === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $businessId, int $id, array $data, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('client_deliveries') || $id <= 0) {
            return false;
        }

        $status = strtolower(trim((string) ($data['status'] ?? 'need_to_schedule')));
        if (!in_array($status, self::statusOptions(), true)) {
            $status = 'need_to_schedule';
        }

        $scheduledAt = self::normalizeDateTime((string) ($data['scheduled_at'] ?? ''));
        if ($status === 'need_to_schedule') {
            $scheduledAt = null;
        }
        if (($status === 'scheduled' || $status === 'completed') && $scheduledAt === null) {
            return false;
        }

        $hasAddressLine2 = SchemaInspector::hasColumn('client_deliveries', 'address_line2');
        $setParts = [
            'client_id = :client_id',
            'scheduled_at = :scheduled_at',
            'end_at = :end_at',
            'address_line1 = :address_line1',
        ];
        if ($hasAddressLine2) {
            $setParts[] = 'address_line2 = :address_line2';
        }
        $setParts = array_merge($setParts, [
            'city = :city',
            'state = :state',
            'postal_code = :postal_code',
            'notes = :notes',
            'status = :status',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ]);

        $sql = 'UPDATE client_deliveries SET ' . implode(', ', $setParts) . '
                WHERE business_id = :business_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':client_id', (int) ($data['client_id'] ?? 0), \PDO::PARAM_INT);
        if ($scheduledAt === null) {
            $stmt->bindValue(':scheduled_at', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':scheduled_at', $scheduledAt);
        }
        $stmt->bindValue(':end_at', null, \PDO::PARAM_NULL);
        $stmt->bindValue(':address_line1', self::nullIfEmpty(trim((string) ($data['address_line1'] ?? ''))));
        if ($hasAddressLine2) {
            $stmt->bindValue(':address_line2', self::nullIfEmpty(trim((string) ($data['address_line2'] ?? ''))));
        }
        $stmt->bindValue(':city', self::nullIfEmpty(trim((string) ($data['city'] ?? ''))));
        $stmt->bindValue(':state', self::nullIfEmpty(trim((string) ($data['state'] ?? ''))));
        $stmt->bindValue(':postal_code', self::nullIfEmpty(trim((string) ($data['postal_code'] ?? ''))));
        $stmt->bindValue(':notes', self::nullIfEmpty(trim((string) ($data['notes'] ?? ''))));
        $stmt->bindValue(':status', $status);
        $uid = $actorUserId > 0 ? $actorUserId : null;
        $stmt->bindValue(':updated_by', $uid, $uid === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $id, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('client_deliveries') || $id <= 0) {
            return false;
        }

        $sql = 'UPDATE client_deliveries SET
                    deleted_at = NOW(),
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE business_id = :business_id
                  AND id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $uid = $actorUserId > 0 ? $actorUserId : null;
        $stmt->bindValue(':updated_by', $uid, $uid === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private static function normalizeDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $raw) === 1) {
            $raw .= ':00';
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
