<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class InvoiceItemType
{
    public static function isAvailable(): bool
    {
        return SchemaInspector::hasTable('invoice_item_types');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(int $businessId, string $search = '', string $status = 'active', int $limit = 25, int $offset = 0): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $where = [
            'business_id = :business_id',
            'deleted_at IS NULL',
        ];

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $where[] = "(:query = '' OR name LIKE :query_like_1 OR COALESCE(default_note, '') LIKE :query_like_2 OR CAST(id AS CHAR) LIKE :query_like_3)";

                $sql = 'SELECT
                    id,
                    name,
                    default_unit_price,
                    default_taxable,
                    default_note,
                    is_active
                FROM invoice_item_types
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY name ASC, id ASC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $status = 'active'): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));

        $where = [
            'business_id = :business_id',
            'deleted_at IS NULL',
        ];

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $where[] = "(:query = '' OR name LIKE :query_like_1 OR COALESCE(default_note, '') LIKE :query_like_2 OR CAST(id AS CHAR) LIKE :query_like_3)";

        $sql = 'SELECT COUNT(*)
                FROM invoice_item_types
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeOptions(int $businessId): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, name, default_unit_price, default_taxable, default_note
             FROM invoice_item_types
             WHERE business_id = :business_id
               AND deleted_at IS NULL
               AND is_active = 1
             ORDER BY name ASC, id ASC'
        );
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $id): ?array
    {
        if (!self::isAvailable() || $id <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, name, default_unit_price, default_taxable, default_note, is_active
             FROM invoice_item_types
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function nameExists(int $businessId, string $name, ?int $excludeId = null): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return false;
        }

        $sql = 'SELECT 1
                FROM invoice_item_types
                WHERE business_id = :business_id
                  AND deleted_at IS NULL
                  AND LOWER(name) = :name';
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        if ($excludeId !== null && $excludeId > 0) {
            $stmt->bindValue(':exclude_id', $excludeId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return is_array($stmt->fetch());
    }

    public static function create(int $businessId, array $payload, int $actorUserId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO invoice_item_types (
                business_id,
                name,
                default_unit_price,
                default_taxable,
                default_note,
                is_active,
                created_by,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                :business_id,
                :name,
                :default_unit_price,
                :default_taxable,
                :default_note,
                :is_active,
                :created_by,
                :updated_by,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'business_id' => $businessId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'default_unit_price' => (float) ($payload['default_unit_price'] ?? 0),
            'default_taxable' => !empty($payload['default_taxable']) ? 1 : 0,
            'default_note' => trim((string) ($payload['default_note'] ?? '')),
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $id, array $payload, int $actorUserId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE invoice_item_types
             SET name = :name,
                 default_unit_price = :default_unit_price,
                 default_taxable = :default_taxable,
                 default_note = :default_note,
                 is_active = :is_active,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'name' => trim((string) ($payload['name'] ?? '')),
            'default_unit_price' => (float) ($payload['default_unit_price'] ?? 0),
            'default_taxable' => !empty($payload['default_taxable']) ? 1 : 0,
            'default_note' => trim((string) ($payload['default_note'] ?? '')),
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);
    }

    public static function softDelete(int $businessId, int $id, int $actorUserId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE invoice_item_types
             SET is_active = 0,
                 deleted_at = NOW(),
                 deleted_by = :deleted_by,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'deleted_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'id' => $id,
        ]);
    }
}
