<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Purchase
{
    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return ['prospect', 'pending', 'active', 'complete', 'cancelled'];
    }

    public static function indexList(int $businessId, string $search = '', string $status = '', int $limit = 25, int $offset = 0): array
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $where = [
            'p.business_id = :business_id',
            'p.deleted_at IS NULL',
        ];

        if ($status !== '' && in_array($status, self::statusOptions(), true)) {
            $where[] = 'p.status = :status';
        }

        $where[] = '(
            :query = ""
            OR p.title LIKE :query_like_1
            OR COALESCE(CONCAT_WS(" ", c.first_name, c.last_name), "") LIKE :query_like_2
            OR COALESCE(c.company_name, "") LIKE :query_like_3
            OR COALESCE(p.notes, "") LIKE :query_like_4
            OR CAST(p.id AS CHAR) LIKE :query_like_5
        )';

        $sql = 'SELECT
                    p.id,
                    p.client_id,
                    p.title,
                    p.status,
                    p.contact_date,
                    p.purchase_date,
                    p.notes,
                    p.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""), NULLIF(c.company_name, ""), CONCAT("Client #", c.id)) AS client_name
                FROM purchases p
                INNER JOIN clients c ON c.id = p.client_id
                    AND c.business_id = p.business_id
                    AND c.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';

        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if ($status !== '' && in_array($status, self::statusOptions(), true)) {
            $stmt->bindValue(':status', $status);
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

    public static function indexCount(int $businessId, string $search = '', string $status = ''): int
    {
        if (!SchemaInspector::hasTable('purchases')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $where = [
            'p.business_id = :business_id',
            'p.deleted_at IS NULL',
        ];

        if ($status !== '' && in_array($status, self::statusOptions(), true)) {
            $where[] = 'p.status = :status';
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
        if ($status !== '' && in_array($status, self::statusOptions(), true)) {
            $stmt->bindValue(':status', $status);
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
        $summary = [
            'prospect' => 0,
            'pending' => 0,
            'active' => 0,
            'complete' => 0,
            'cancelled' => 0,
        ];

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

        $sql = 'SELECT
                    p.id,
                    p.business_id,
                    p.client_id,
                    p.title,
                    p.status,
                    p.contact_date,
                    p.purchase_date,
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

        $sql = 'INSERT INTO purchases (
                    business_id,
                    client_id,
                    title,
                    status,
                    contact_date,
                    purchase_date,
                    notes,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :business_id,
                    :client_id,
                    :title,
                    :status,
                    :contact_date,
                    :purchase_date,
                    :notes,
                    :created_by,
                    :updated_by,
                    NOW(),
                    NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'title' => trim((string) ($payload['title'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'prospect')),
            'contact_date' => $payload['contact_date'] ?? null,
            'purchase_date' => $payload['purchase_date'] ?? null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $purchaseId, array $payload, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('purchases') || $purchaseId <= 0) {
            return false;
        }

        $sql = 'UPDATE purchases
                SET client_id = :client_id,
                    title = :title,
                    status = :status,
                    contact_date = :contact_date,
                    purchase_date = :purchase_date,
                    notes = :notes,
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
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'title' => trim((string) ($payload['title'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'prospect')),
            'contact_date' => $payload['contact_date'] ?? null,
            'purchase_date' => $payload['purchase_date'] ?? null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

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
}
