<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class User
{
    public static function create(array $payload, int $actorUserId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (
                email, password_hash, first_name, last_name, role, is_active, created_by, updated_by, created_at, updated_at
             ) VALUES (
                :email, :password_hash, :first_name, :last_name, :role, :is_active, :created_by, :updated_by, NOW(), NOW()
             )'
        );

        $stmt->execute([
            'email' => trim(strtolower((string) ($payload['email'] ?? ''))),
            'password_hash' => (string) ($payload['password_hash'] ?? ''),
            'first_name' => trim((string) ($payload['first_name'] ?? '')),
            'last_name' => trim((string) ($payload['last_name'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? 'general_user')),
            'is_active' => (int) ($payload['is_active'] ?? 1) === 0 ? 0 : 1,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, email, password_hash, first_name, last_name, role
                FROM users
                WHERE email = :email
                  AND deleted_at IS NULL
                  AND COALESCE(is_active, 1) = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => trim(strtolower($email))]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, email, first_name, last_name, role, is_active
             FROM users
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return false;
        }

        $sql = 'SELECT 1
                FROM users
                WHERE email = :email
                  AND deleted_at IS NULL';
        $params = ['email' => $email];

        if (($excludeUserId ?? 0) > 0) {
            $sql .= ' AND id <> :exclude_user_id';
            $params['exclude_user_id'] = (int) $excludeUserId;
        }

        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public static function updateProfile(int $userId, array $payload, int $actorUserId): void
    {
        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));
        $email = trim(strtolower((string) ($payload['email'] ?? '')));
        $passwordHash = $payload['password_hash'] ?? null;

        $sql = 'UPDATE users
                SET first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    updated_by = :updated_by,
                    updated_at = NOW()';
        $params = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'updated_by' => $actorUserId,
            'id' => $userId,
        ];

        if (is_string($passwordHash) && $passwordHash !== '') {
            $sql .= ', password_hash = :password_hash';
            $params['password_hash'] = $passwordHash;
        }

        $sql .= '
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function indexCountForBusiness(int $businessId, string $search = '', string $status = 'active'): int
    {
        $query = trim($search);
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $activeWhere = match ($status) {
            'inactive' => '(COALESCE(m.is_active, 1) = 0 OR COALESCE(u.is_active, 1) = 0)',
            'all' => '1=1',
            default => '(COALESCE(m.is_active, 1) = 1 AND COALESCE(u.is_active, 1) = 1)',
        };
        $sql = 'SELECT COUNT(*)
                FROM business_user_memberships m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.business_id = :business_id
                  AND m.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  AND u.role <> \'site_admin\'
                  AND ' . $activeWhere . '
                  AND (
                    :query = \'\'
                    OR COALESCE(u.first_name, \'\') LIKE :query_like_1
                    OR COALESCE(u.last_name, \'\') LIKE :query_like_2
                    OR COALESCE(u.email, \'\') LIKE :query_like_3
                    OR CAST(u.id AS CHAR) LIKE :query_like_4
                  )';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function indexListForBusiness(int $businessId, string $search = '', string $status = 'active', int $limit = 25, int $offset = 0): array
    {
        $query = trim($search);
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $activeWhere = match ($status) {
            'inactive' => '(COALESCE(m.is_active, 1) = 0 OR COALESCE(u.is_active, 1) = 0)',
            'all' => '1=1',
            default => '(COALESCE(m.is_active, 1) = 1 AND COALESCE(u.is_active, 1) = 1)',
        };
        $sql = 'SELECT
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.role,
                    m.role AS workspace_role,
                    COALESCE(u.is_active, 1) AS is_active,
                    COALESCE(m.is_active, 1) AS membership_active
                FROM business_user_memberships m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.business_id = :business_id
                  AND m.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  AND u.role <> \'site_admin\'
                  AND ' . $activeWhere . '
                  AND (
                    :query = \'\'
                    OR COALESCE(u.first_name, \'\') LIKE :query_like_1
                    OR COALESCE(u.last_name, \'\') LIKE :query_like_2
                    OR COALESCE(u.email, \'\') LIKE :query_like_3
                    OR CAST(u.id AS CHAR) LIKE :query_like_4
                  )
                ORDER BY
                    COALESCE(NULLIF(u.last_name, \'\'), u.email) ASC,
                    COALESCE(NULLIF(u.first_name, \'\'), \'\') ASC,
                    u.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
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

    public static function indexCountGlobal(string $search = '', string $status = 'active'): int
    {
        $query = trim($search);
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $activeWhere = match ($status) {
            'inactive' => 'COALESCE(u.is_active, 1) = 0',
            'all' => '1=1',
            default => 'COALESCE(u.is_active, 1) = 1',
        };
        $sql = 'SELECT COUNT(*)
                FROM users u
                WHERE u.deleted_at IS NULL
                  AND u.role = \'site_admin\'
                  AND ' . $activeWhere . '
                  AND NOT EXISTS (
                    SELECT 1
                    FROM business_user_memberships m
                    WHERE m.user_id = u.id
                      AND m.deleted_at IS NULL
                      AND COALESCE(m.is_active, 1) = 1
                  )
                  AND (
                    :query = \'\'
                    OR COALESCE(u.first_name, \'\') LIKE :query_like_1
                    OR COALESCE(u.last_name, \'\') LIKE :query_like_2
                    OR COALESCE(u.email, \'\') LIKE :query_like_3
                    OR CAST(u.id AS CHAR) LIKE :query_like_4
                  )';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function indexListGlobal(string $search = '', string $status = 'active', int $limit = 25, int $offset = 0): array
    {
        $query = trim($search);
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $activeWhere = match ($status) {
            'inactive' => 'COALESCE(u.is_active, 1) = 0',
            'all' => '1=1',
            default => 'COALESCE(u.is_active, 1) = 1',
        };
        $sql = "SELECT
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.role,
                    COALESCE(u.is_active, 1) AS is_active
                FROM users u
                WHERE u.deleted_at IS NULL
                  AND u.role = 'site_admin'
                  AND {$activeWhere}
                  AND NOT EXISTS (
                    SELECT 1
                    FROM business_user_memberships m
                    WHERE m.user_id = u.id
                      AND m.deleted_at IS NULL
                      AND COALESCE(m.is_active, 1) = 1
                  )
                  AND (
                    :query = ''
                    OR COALESCE(u.first_name, '') LIKE :query_like_1
                    OR COALESCE(u.last_name, '') LIKE :query_like_2
                    OR COALESCE(u.email, '') LIKE :query_like_3
                    OR CAST(u.id AS CHAR) LIKE :query_like_4
                  )
                ORDER BY
                    COALESCE(NULLIF(u.last_name, ''), u.email) ASC,
                    COALESCE(NULLIF(u.first_name, ''), '') ASC,
                    u.id DESC
                LIMIT :row_limit
                OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
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

    public static function setActiveState(int $userId, bool $isActive, int $actorUserId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET is_active = :is_active,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'is_active' => $isActive ? 1 : 0,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function displayName(array $user): string
    {
        $full = trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        return (string) ($user['email'] ?? 'User');
    }
}
