<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Employee
{
    public static function indexCount(int $businessId, string $search = ''): int
    {
        if (!SchemaInspector::hasTable('employees')) {
            return 0;
        }

        $query = trim($search);
        $nameSql = self::employeeNameSql('e');
        $userNameSql = self::userNameSql('u');
        $suffixSql = SchemaInspector::hasColumn('employees', 'suffix') ? 'e.suffix' : "''";

        $sql = "SELECT COUNT(*)
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE e.business_id = :business_id
                  AND e.deleted_at IS NULL
                  AND (
                    :query = ''
                    OR {$nameSql} LIKE :query_like_1
                    OR {$userNameSql} LIKE :query_like_2
                    OR COALESCE(e.email, '') LIKE :query_like_3
                    OR COALESCE(e.phone, '') LIKE :query_like_4
                    OR COALESCE({$suffixSql}, '') LIKE :query_like_5
                    OR CAST(e.id AS CHAR) LIKE :query_like_6
                  )";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function indexList(int $businessId, string $search = '', int $limit = 25, int $offset = 0): array
    {
        if (!SchemaInspector::hasTable('employees')) {
            return [];
        }

        $query = trim($search);
        $nameSql = self::employeeNameSql('e');
        $userNameSql = self::userNameSql('u');
        $suffixSql = SchemaInspector::hasColumn('employees', 'suffix') ? 'e.suffix' : "''";
        $noteSql = SchemaInspector::hasColumn('employees', 'note') ? 'e.note' : (SchemaInspector::hasColumn('employees', 'notes') ? 'e.notes' : 'NULL');

        $sql = "SELECT
                    e.id,
                    e.first_name,
                    e.last_name,
                    {$suffixSql} AS suffix,
                    e.email,
                    e.phone,
                    e.hourly_rate,
                    e.status,
                    e.user_id,
                    {$noteSql} AS note,
                    u.email AS linked_user_email,
                    {$userNameSql} AS linked_user_name,
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0
                        THEN {$userNameSql}
                        ELSE {$nameSql}
                    END AS display_name
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE e.business_id = :business_id
                  AND e.deleted_at IS NULL
                  AND (
                    :query = ''
                    OR {$nameSql} LIKE :query_like_1
                    OR {$userNameSql} LIKE :query_like_2
                    OR COALESCE(e.email, '') LIKE :query_like_3
                    OR COALESCE(e.phone, '') LIKE :query_like_4
                    OR COALESCE({$suffixSql}, '') LIKE :query_like_5
                    OR CAST(e.id AS CHAR) LIKE :query_like_6
                  )
                ORDER BY
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0 THEN 0 ELSE 1 END ASC,
                    display_name ASC,
                    e.id ASC
                LIMIT :row_limit
                OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':query_like_5', $queryLike);
        $stmt->bindValue(':query_like_6', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function activeOptions(int $businessId, string $search = '', int $limit = 80): array
    {
        if (!SchemaInspector::hasTable('employees')) {
            return [];
        }

        $query = trim($search);
        $nameSql = self::employeeNameSql('e');
        $userNameSql = self::userNameSql('u');

        $sql = "SELECT
                    e.id,
                    {$nameSql} AS employee_name,
                    {$userNameSql} AS linked_user_name,
                    u.email AS linked_user_email,
                    COALESCE(NULLIF({$userNameSql}, ''), {$nameSql}) AS name
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE e.business_id = :business_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.status, 'active') = 'active'
                  AND (
                    :query = ''
                    OR {$nameSql} LIKE :query_like_1
                    OR {$userNameSql} LIKE :query_like_2
                    OR COALESCE(e.email, '') LIKE :query_like_3
                    OR COALESCE(e.phone, '') LIKE :query_like_4
                    OR CAST(e.id AS CHAR) LIKE :query_like_5
                  )
                ORDER BY
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0 THEN 0 ELSE 1 END ASC,
                    name ASC,
                    e.id ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
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

    public static function businessUserOptions(int $businessId, string $search = '', int $limit = 80): array
    {
        $query = trim($search);

        $sql = "SELECT
                    u.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''), CONCAT('User #', u.id)) AS name,
                    u.email,
                    m.role
                FROM business_user_memberships m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.business_id = :business_id
                  AND m.deleted_at IS NULL
                  AND COALESCE(m.is_active, 1) = 1
                  AND u.deleted_at IS NULL
                  AND COALESCE(u.is_active, 1) = 1
                  AND u.role <> 'site_admin'
                  AND (
                    :query = ''
                    OR COALESCE(u.first_name, '') LIKE :query_like_1
                    OR COALESCE(u.last_name, '') LIKE :query_like_2
                    OR COALESCE(u.email, '') LIKE :query_like_3
                    OR CAST(u.id AS CHAR) LIKE :query_like_4
                  )
                ORDER BY name ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $employeeId): ?array
    {
        if ($employeeId <= 0 || !SchemaInspector::hasTable('employees')) {
            return null;
        }

        $nameSql = self::employeeNameSql('e');
        $userNameSql = self::userNameSql('u');
        $suffixSql = SchemaInspector::hasColumn('employees', 'suffix') ? 'e.suffix' : "''";
        $noteSql = SchemaInspector::hasColumn('employees', 'note') ? 'e.note' : (SchemaInspector::hasColumn('employees', 'notes') ? 'e.notes' : 'NULL');

        $sql = "SELECT
                    e.id,
                    e.business_id,
                    e.first_name,
                    e.last_name,
                    {$suffixSql} AS suffix,
                    e.email,
                    e.phone,
                    e.hourly_rate,
                    e.status,
                    e.user_id,
                    {$noteSql} AS note,
                    e.created_at,
                    e.updated_at,
                    u.email AS linked_user_email,
                    {$userNameSql} AS linked_user_name,
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0
                        THEN {$userNameSql}
                        ELSE {$nameSql}
                    END AS display_name,
                    {$nameSql} AS employee_name
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE e.business_id = :business_id
                  AND e.id = :employee_id
                  AND e.deleted_at IS NULL
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function findByUserForBusiness(int $businessId, int $userId): ?array
    {
        if ($userId <= 0 || !SchemaInspector::hasTable('employees')) {
            return null;
        }

        $suffixSql = SchemaInspector::hasColumn('employees', 'suffix') ? 'e.suffix' : "''";
        $nameSql = self::employeeNameSql('e');
        $userNameSql = self::userNameSql('u');

        $sql = "SELECT
                    e.id,
                    e.user_id,
                    e.first_name,
                    e.last_name,
                    {$suffixSql} AS suffix,
                    {$nameSql} AS employee_name,
                    {$userNameSql} AS linked_user_name,
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0
                        THEN {$userNameSql}
                        ELSE {$nameSql}
                    END AS display_name
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                WHERE e.business_id = :business_id
                  AND e.user_id = :user_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.status, 'active') = 'active'
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function create(int $businessId, array $payload, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('employees')) {
            throw new \RuntimeException('Employees table is missing.');
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $append = static function (string $column, string $placeholder, mixed $value) use (&$columns, &$placeholders, &$params): void {
            $columns[] = $column;
            $placeholders[] = $placeholder;
            $params[ltrim($placeholder, ':')] = $value;
        };

        $append('business_id', ':business_id', $businessId);
        $append('first_name', ':first_name', trim((string) ($payload['first_name'] ?? '')));
        $append('last_name', ':last_name', trim((string) ($payload['last_name'] ?? '')));
        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $append('suffix', ':suffix', trim((string) ($payload['suffix'] ?? '')) ?: null);
        }
        $append('email', ':email', trim((string) ($payload['email'] ?? '')) ?: null);
        $append('phone', ':phone', trim((string) ($payload['phone'] ?? '')) ?: null);
        $append('hourly_rate', ':hourly_rate', isset($payload['hourly_rate']) && $payload['hourly_rate'] !== '' ? (float) $payload['hourly_rate'] : null);
        $append('user_id', ':user_id', (isset($payload['user_id']) && (int) $payload['user_id'] > 0) ? (int) $payload['user_id'] : null);
        if (SchemaInspector::hasColumn('employees', 'note')) {
            $append('note', ':note', trim((string) ($payload['note'] ?? '')) ?: null);
        } elseif (SchemaInspector::hasColumn('employees', 'notes')) {
            $append('notes', ':notes', trim((string) ($payload['note'] ?? '')) ?: null);
        }
        if (SchemaInspector::hasColumn('employees', 'status')) {
            $append('status', ':status', trim((string) ($payload['status'] ?? 'active')) ?: 'active');
        }
        if (SchemaInspector::hasColumn('employees', 'created_by')) {
            $append('created_by', ':created_by', $actorUserId > 0 ? $actorUserId : null);
        }
        if (SchemaInspector::hasColumn('employees', 'updated_by')) {
            $append('updated_by', ':updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        $sql = 'INSERT INTO employees (' . implode(', ', $columns) . ')
                VALUES (:' . implode(', :', array_keys($params)) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $employeeId, array $payload, int $actorUserId): bool
    {
        if ($employeeId <= 0 || !SchemaInspector::hasTable('employees')) {
            return false;
        }

        $assignments = [
            'first_name = :first_name',
            'last_name = :last_name',
            'email = :email',
            'phone = :phone',
            'hourly_rate = :hourly_rate',
            'user_id = :user_id',
        ];
        $params = [
            'first_name' => trim((string) ($payload['first_name'] ?? '')),
            'last_name' => trim((string) ($payload['last_name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'hourly_rate' => isset($payload['hourly_rate']) && $payload['hourly_rate'] !== '' ? (float) $payload['hourly_rate'] : null,
            'user_id' => (isset($payload['user_id']) && (int) $payload['user_id'] > 0) ? (int) $payload['user_id'] : null,
            'employee_id' => $employeeId,
            'business_id' => $businessId,
        ];

        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $assignments[] = 'suffix = :suffix';
            $params['suffix'] = trim((string) ($payload['suffix'] ?? '')) ?: null;
        }
        if (SchemaInspector::hasColumn('employees', 'note')) {
            $assignments[] = 'note = :note';
            $params['note'] = trim((string) ($payload['note'] ?? '')) ?: null;
        } elseif (SchemaInspector::hasColumn('employees', 'notes')) {
            $assignments[] = 'notes = :notes';
            $params['notes'] = trim((string) ($payload['note'] ?? '')) ?: null;
        }
        if (SchemaInspector::hasColumn('employees', 'status')) {
            $assignments[] = 'status = :status';
            $params['status'] = trim((string) ($payload['status'] ?? 'active')) ?: 'active';
        }
        if (SchemaInspector::hasColumn('employees', 'updated_by')) {
            $assignments[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $sql = 'UPDATE employees
                SET ' . implode(', ', $assignments) . ',
                    updated_at = NOW()
                WHERE id = :employee_id
                  AND business_id = :business_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    private static function employeeNameSql(string $alias): string
    {
        $parts = ["NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), '')"];
        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $parts = ["NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name, {$alias}.suffix)), '')"];
        }

        return 'COALESCE(' . implode(', ', $parts) . ", NULLIF({$alias}.email, ''), CONCAT('Employee #', {$alias}.id))";
    }

    private static function userNameSql(string $alias): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), ''), NULLIF({$alias}.email, ''), CONCAT('User #', {$alias}.id))";
    }
}
