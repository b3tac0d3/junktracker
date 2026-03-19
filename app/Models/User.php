<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class User
{
    public static function create(array $payload, int $actorUserId): int
    {
        $columns = [
            'email',
            'password_hash',
            'first_name',
            'last_name',
            'role',
            'is_active',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $placeholders = [
            ':email',
            ':password_hash',
            ':first_name',
            ':last_name',
            ':role',
            ':is_active',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'email' => trim(strtolower((string) ($payload['email'] ?? ''))),
            'password_hash' => (string) ($payload['password_hash'] ?? ''),
            'first_name' => trim((string) ($payload['first_name'] ?? '')),
            'last_name' => trim((string) ($payload['last_name'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? 'general_user')),
            'is_active' => (int) ($payload['is_active'] ?? 1) === 0 ? 0 : 1,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];

        if (SchemaInspector::hasColumn('users', 'must_change_password')) {
            $columns[] = 'must_change_password';
            $placeholders[] = ':must_change_password';
            $params['must_change_password'] = (int) ($payload['must_change_password'] ?? 0) === 1 ? 1 : 0;
        }

        if (self::hasInvitationColumns()) {
            $columns[] = 'invited_at';
            $placeholders[] = 'NOW()';
            $columns[] = 'invitation_expires_at';
            $placeholders[] = 'DATE_ADD(NOW(), INTERVAL 1 DAY)';
            $columns[] = 'invitation_accepted_at';
            $placeholders[] = 'NULL';
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );

        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findByEmail(string $email): ?array
    {
        $mustChangeSql = self::hasMustChangePasswordColumn() ? 'COALESCE(must_change_password, 0)' : '0';

        $sql = 'SELECT id, email, password_hash, first_name, last_name, role, ' . $mustChangeSql . ' AS must_change_password, ' . self::invitationSelectSql() . '
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
        $mustChangeSql = self::hasMustChangePasswordColumn() ? 'COALESCE(must_change_password, 0)' : '0';

        $stmt = Database::connection()->prepare(
            'SELECT id, email, first_name, last_name, role, is_active, ' . $mustChangeSql . ' AS must_change_password, ' . self::invitationSelectSql() . '
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
        if (array_key_exists('must_change_password', $payload) && self::hasMustChangePasswordColumn()) {
            $sql .= ', must_change_password = :must_change_password';
            $params['must_change_password'] = (int) ($payload['must_change_password'] ?? 0) === 1 ? 1 : 0;
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
                    COALESCE(m.is_active, 1) AS membership_active,
                    ' . self::invitationSelectSql() . '
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
                    COALESCE(u.is_active, 1) AS is_active,
                    " . self::invitationSelectSql() . "
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

    public static function resendInvitation(int $userId, string $passwordHash, int $actorUserId): void
    {
        $sql = 'UPDATE users
                SET password_hash = :password_hash,
                    updated_by = :updated_by,
                    updated_at = NOW()';
        $params = [
            'password_hash' => $passwordHash,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $userId,
        ];

        if (self::hasMustChangePasswordColumn()) {
            $sql .= ', must_change_password = 1';
        }
        if (self::hasInvitationColumns()) {
            $sql .= ',
                    invited_at = NOW(),
                    invitation_expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
                    invitation_accepted_at = NULL';
        }

        $sql .= '
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function autoAcceptInvitation(int $userId, string $passwordHash, int $actorUserId): void
    {
        $sql = 'UPDATE users
                SET password_hash = :password_hash,
                    updated_by = :updated_by,
                    updated_at = NOW()';
        $params = [
            'password_hash' => $passwordHash,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $userId,
        ];

        if (self::hasMustChangePasswordColumn()) {
            $sql .= ', must_change_password = 1';
        }
        if (self::hasInvitationColumns()) {
            $sql .= ',
                    invited_at = NOW(),
                    invitation_expires_at = NULL,
                    invitation_accepted_at = NOW()';
        }

        $sql .= '
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function markInvitationAccepted(int $userId, int $actorUserId): void
    {
        if (!self::hasInvitationColumns()) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET invitation_accepted_at = NOW(),
                 invitation_expires_at = NULL,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
               AND invitation_accepted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $userId,
        ]);
    }

    public static function issuePasswordReset(int $userId, string $tokenHash, int $expiresInHours, int $actorUserId): bool
    {
        if (!self::hasPasswordResetColumns()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET password_reset_token_hash = :token_hash,
                 password_reset_sent_at = NOW(),
                 password_reset_expires_at = DATE_ADD(NOW(), INTERVAL :expires_hours HOUR),
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':token_hash', $tokenHash);
        $stmt->bindValue(':expires_hours', max(1, $expiresInHours), \PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId > 0 ? $actorUserId : null);
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function findByPasswordResetToken(string $token): ?array
    {
        if (!self::hasPasswordResetColumns()) {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $mustChangeSql = self::hasMustChangePasswordColumn() ? 'COALESCE(must_change_password, 0)' : '0';

        $stmt = Database::connection()->prepare(
            'SELECT id,
                    email,
                    first_name,
                    last_name,
                    role,
                    is_active,
                    ' . $mustChangeSql . ' AS must_change_password,
                    ' . self::invitationSelectSql() . '
             FROM users
             WHERE password_reset_token_hash = :token_hash
               AND password_reset_expires_at IS NOT NULL
               AND password_reset_expires_at >= NOW()
               AND deleted_at IS NULL
               AND COALESCE(is_active, 1) = 1
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function clearPasswordReset(int $userId, int $actorUserId): void
    {
        if (!self::hasPasswordResetColumns()) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET password_reset_token_hash = NULL,
                 password_reset_sent_at = NULL,
                 password_reset_expires_at = NULL,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $userId,
        ]);
    }

    public static function displayName(array $user): string
    {
        $full = trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        return (string) ($user['email'] ?? 'User');
    }

    private static function hasMustChangePasswordColumn(): bool
    {
        return SchemaInspector::hasColumn('users', 'must_change_password');
    }

    private static function hasInvitationColumns(): bool
    {
        return SchemaInspector::hasColumn('users', 'invited_at')
            && SchemaInspector::hasColumn('users', 'invitation_expires_at')
            && SchemaInspector::hasColumn('users', 'invitation_accepted_at');
    }

    private static function hasPasswordResetColumns(): bool
    {
        return SchemaInspector::hasColumn('users', 'password_reset_token_hash')
            && SchemaInspector::hasColumn('users', 'password_reset_expires_at')
            && SchemaInspector::hasColumn('users', 'password_reset_sent_at');
    }

    private static function invitationSelectSql(): string
    {
        if (!self::hasInvitationColumns()) {
            return 'NULL AS invited_at, NULL AS invitation_expires_at, NULL AS invitation_accepted_at';
        }

        return 'invited_at AS invited_at, invitation_expires_at AS invitation_expires_at, invitation_accepted_at AS invitation_accepted_at';
    }
}
