<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Purchase
{
    /**
     * @return array<int, string>
     */
    public static function statusOptions(int $businessId = 0): array
    {
        $fallback = ['prospect', 'pending', 'active', 'complete', 'cancelled'];
        if ($businessId <= 0) {
            return $fallback;
        }

        $configured = FormSelectValue::optionsForSection($businessId, 'purchase_status');
        $normalized = [];
        foreach ($configured as $rawOption) {
            $option = strtolower(trim((string) $rawOption));
            if ($option === '') {
                continue;
            }
            if (in_array($option, $normalized, true)) {
                continue;
            }
            $normalized[] = $option;
        }

        return $normalized !== [] ? $normalized : $fallback;
    }

    public static function indexList(
        int $businessId,
        string $search = '',
        string $status = '',
        string|int $fromDate = '',
        string|int $toDate = '',
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'date',
        string $sortDir = 'desc'
    ): array
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $fromDate = trim((string) $fromDate);
        $toDate = trim((string) $toDate);

        $filterDateSql = 'COALESCE(DATE(p.purchase_date), DATE(p.contact_date), DATE(p.created_at))';

        $where = [
            'p.business_id = :business_id',
            'p.deleted_at IS NULL',
        ];

        if ($status !== '' && in_array($status, self::statusOptions($businessId), true)) {
            $where[] = 'p.status = :status';
        }
        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $where[] = '(
            :query = ""
            OR p.title LIKE :query_like_1
            OR COALESCE(CONCAT_WS(" ", c.first_name, c.last_name), "") LIKE :query_like_2
            OR COALESCE(c.company_name, "") LIKE :query_like_3
            OR COALESCE(p.notes, "") LIKE :query_like_4
            OR CAST(p.id AS CHAR) LIKE :query_like_5
        )';

        $purchasePriceSql = SchemaInspector::hasColumn('purchases', 'purchase_price') ? 'p.purchase_price' : '0';

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'asc' ? 'ASC' : 'DESC';
        $sortMap = [
            'date' => "{$filterDateSql} {$sortDir}, p.id {$sortDir}",
            'id' => "p.id {$sortDir}",
            'client_name' => "COALESCE(NULLIF(TRIM(CONCAT_WS(\" \", c.first_name, c.last_name)), \"\"), NULLIF(c.company_name, \"\"), CONCAT(\"Client #\", c.id)) {$sortDir}, p.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['date'];

        $sql = 'SELECT
                    p.id,
                    p.client_id,
                    p.title,
                    p.status,
                    p.contact_date,
                    p.purchase_date,
                    ' . $purchasePriceSql . ' AS purchase_price,
                    p.notes,
                    p.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM purchases p
                INNER JOIN clients c ON c.id = p.client_id
                    AND c.business_id = p.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy . '
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';

        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && in_array($status, self::statusOptions($businessId), true)) {
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

    public static function indexCount(int $businessId, string $search = '', string $status = '', string|int $fromDate = '', string|int $toDate = ''): int
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $fromDate = trim((string) $fromDate);
        $toDate = trim((string) $toDate);

        $filterDateSql = 'COALESCE(DATE(p.purchase_date), DATE(p.contact_date), DATE(p.created_at))';

        $where = [
            'p.business_id = :business_id',
            'p.deleted_at IS NULL',
        ];

        if ($status !== '' && in_array($status, self::statusOptions($businessId), true)) {
            $where[] = 'p.status = :status';
        }
        if ($fromDate !== '') {
            $where[] = "{$filterDateSql} >= :from_date";
        }
        if ($toDate !== '') {
            $where[] = "{$filterDateSql} <= :to_date";
        }

        $where[] = '(
            :query = ""
            OR p.title LIKE :query_like_1
            OR COALESCE(CONCAT_WS(" ", c.first_name, c.last_name), "") LIKE :query_like_2
            OR COALESCE(c.company_name, "") LIKE :query_like_3
            OR COALESCE(p.notes, "") LIKE :query_like_4
            OR CAST(p.id AS CHAR) LIKE :query_like_5
        )';

        $sql = 'SELECT COUNT(*)
                FROM purchases p
                INNER JOIN clients c ON c.id = p.client_id
                    AND c.business_id = p.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';

        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && in_array($status, self::statusOptions($businessId), true)) {
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

    public static function statusSummary(int $businessId): array
    {
        $summary = [];
        foreach (self::statusOptions($businessId) as $statusOption) {
            $summary[$statusOption] = 0;
        }
        if ($summary === []) {
            $summary = [
                'prospect' => 0,
                'pending' => 0,
                'active' => 0,
                'complete' => 0,
                'cancelled' => 0,
            ];
        }

        if (!SchemaInspector::hasTable('purchases')) {
            return $summary;
        }

        $sql = 'SELECT p.status, COUNT(*) AS total
                FROM purchases p
                WHERE p.business_id = :business_id
                  AND p.deleted_at IS NULL
                GROUP BY p.status';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower(trim((string) ($row['status'] ?? '')));
            if (array_key_exists($key, $summary)) {
                $summary[$key] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    public static function findForBusiness(int $businessId, int $purchaseId): ?array
    {
        if (!SchemaInspector::hasTable('purchases') || $purchaseId <= 0) {
            return null;
        }

        $purchasePriceSql = SchemaInspector::hasColumn('purchases', 'purchase_price') ? 'p.purchase_price' : '0';

        $sql = 'SELECT
                    p.id,
                    p.business_id,
                    p.client_id,
                    p.title,
                    p.status,
                    p.contact_date,
                    p.purchase_date,
                    ' . $purchasePriceSql . ' AS purchase_price,
                    p.notes,
                    p.created_by,
                    p.updated_by,
                    p.created_at,
                    p.updated_at,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name,
                    c.phone AS client_phone,
                    c.city AS client_city
                FROM purchases p
                INNER JOIN clients c ON c.id = p.client_id
                    AND c.business_id = p.business_id
                    AND c.deleted_at IS NULL
                WHERE p.business_id = :business_id
                  AND p.id = :purchase_id
                  AND p.deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':purchase_id', $purchaseId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $payload, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('purchases')) {
            throw new \RuntimeException('Purchases table is missing.');
        }

        $columns = ['business_id', 'client_id', 'title', 'status', 'contact_date', 'purchase_date', 'notes'];
        $placeholders = [':business_id', ':client_id', ':title', ':status', ':contact_date', ':purchase_date', ':notes'];
        $params = [
            'business_id' => $businessId,
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'title' => trim((string) ($payload['title'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'prospect')),
            'contact_date' => $payload['contact_date'] ?? null,
            'purchase_date' => $payload['purchase_date'] ?? null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];

        if (SchemaInspector::hasColumn('purchases', 'purchase_price')) {
            $columns[] = 'purchase_price';
            $placeholders[] = ':purchase_price';
            $params['purchase_price'] = round((float) ($payload['purchase_price'] ?? 0), 2);
        }
        if (SchemaInspector::hasColumn('purchases', 'created_by')) {
            $columns[] = 'created_by';
            $placeholders[] = ':created_by';
            $params['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('purchases', 'updated_by')) {
            $columns[] = 'updated_by';
            $placeholders[] = ':updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('purchases', 'created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        if (SchemaInspector::hasColumn('purchases', 'updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO purchases (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $purchaseId, array $payload, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('purchases') || $purchaseId <= 0) {
            return false;
        }

        $assignments = [
            'client_id = :client_id',
            'title = :title',
            'status = :status',
            'contact_date = :contact_date',
            'purchase_date = :purchase_date',
            'notes = :notes',
        ];

        $params = [
            'business_id' => $businessId,
            'purchase_id' => $purchaseId,
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'title' => trim((string) ($payload['title'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'prospect')),
            'contact_date' => $payload['contact_date'] ?? null,
            'purchase_date' => $payload['purchase_date'] ?? null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];

        if (SchemaInspector::hasColumn('purchases', 'purchase_price')) {
            $assignments[] = 'purchase_price = :purchase_price';
            $params['purchase_price'] = round((float) ($payload['purchase_price'] ?? 0), 2);
        }
        if (SchemaInspector::hasColumn('purchases', 'updated_by')) {
            $assignments[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('purchases', 'updated_at')) {
            $assignments[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE purchases
                SET ' . implode(', ', $assignments) . '
                WHERE id = :purchase_id
                  AND business_id = :business_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $businessId, int $purchaseId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('purchases') || $purchaseId <= 0) {
            return false;
        }

        $sql = 'UPDATE purchases
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :purchase_id
                  AND business_id = :business_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'purchase_id' => $purchaseId,
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function tasksByPurchase(int $businessId, int $purchaseId, int $limit = 20): array
    {
        if (!SchemaInspector::hasTable('tasks')) {
            return [];
        }

        $sql = 'SELECT
                    t.id,
                    t.title,
                    t.status,
                    t.due_at,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), NULLIF(u.email, ""), CONCAT("User #", u.id)) AS owner_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_user_id
                    AND u.deleted_at IS NULL
                WHERE t.business_id = :business_id
                  AND t.deleted_at IS NULL
                  AND t.link_type = :link_type
                  AND t.link_id = :link_id
                ORDER BY t.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':link_type', 'purchase');
        $stmt->bindValue(':link_id', $purchaseId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 100)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function totalsByClient(int $businessId, int $clientId): array
    {
        if (!SchemaInspector::hasTable('purchases') || $clientId <= 0) {
            return ['count' => 0, 'total_purchase_price' => 0.0];
        }

        $purchasePriceSql = SchemaInspector::hasColumn('purchases', 'purchase_price')
            ? 'COALESCE(p.purchase_price, 0)'
            : '0';

        $sql = 'SELECT
                    COUNT(*) AS row_count,
                    COALESCE(SUM(' . $purchasePriceSql . '), 0) AS purchase_total
                FROM purchases p
                WHERE p.business_id = :business_id
                  AND p.client_id = :client_id
                  AND p.deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => $clientId,
        ]);
        $row = $stmt->fetch();

        return [
            'count' => (int) ($row['row_count'] ?? 0),
            'total_purchase_price' => (float) ($row['purchase_total'] ?? 0),
        ];
    }

    public static function listByClient(int $businessId, int $clientId, int $limit = 200): array
    {
        if (!SchemaInspector::hasTable('purchases') || $clientId <= 0) {
            return [];
        }

        $purchasePriceSql = SchemaInspector::hasColumn('purchases', 'purchase_price') ? 'p.purchase_price' : '0';
        $safeLimit = max(1, min($limit, 1000));
        $clientDeletedJoin = SchemaInspector::hasColumn('clients', 'deleted_at') ? ' AND c.deleted_at IS NULL' : '';
        $purchaseDeletedWhere = SchemaInspector::hasColumn('purchases', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';
        $purchaseBusinessWhere = SchemaInspector::hasColumn('purchases', 'business_id')
            ? 'AND (p.business_id = :purchase_business_id OR p.business_id IS NULL OR p.business_id = 0)'
            : '';
        $clientBusinessWhere = SchemaInspector::hasColumn('clients', 'business_id')
            ? 'AND c.business_id = :client_business_id'
            : '';

        $sql = 'SELECT
                    p.id,
                    p.title,
                    p.status,
                    p.contact_date,
                    p.purchase_date,
                    ' . $purchasePriceSql . ' AS purchase_price
                FROM purchases p
                INNER JOIN clients c ON c.id = p.client_id
                    ' . $clientDeletedJoin . '
                WHERE c.id = :client_id
                  ' . $clientBusinessWhere . '
                  ' . $purchaseBusinessWhere . '
                  ' . $purchaseDeletedWhere . '
                ORDER BY p.id DESC
                LIMIT ' . $safeLimit;

        $stmt = Database::connection()->prepare($sql);
        $params = ['client_id' => $clientId];
        if (SchemaInspector::hasColumn('clients', 'business_id')) {
            $params['client_business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('purchases', 'business_id')) {
            $params['purchase_business_id'] = $businessId;
        }
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
