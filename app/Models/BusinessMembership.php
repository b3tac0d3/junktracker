<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class BusinessMembership
{
    public static function firstActiveMembership(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.business_id, m.role, b.name AS business_name
             FROM business_user_memberships m
             INNER JOIN businesses b ON b.id = m.business_id
             WHERE m.user_id = :user_id
               AND m.deleted_at IS NULL
               AND COALESCE(m.is_active, 1) = 1
               AND b.deleted_at IS NULL
               AND COALESCE(b.is_active, 1) = 1
             ORDER BY m.id ASC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function membershipsForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.business_id, m.role, b.name AS business_name
             FROM business_user_memberships m
             INNER JOIN businesses b ON b.id = m.business_id
             WHERE m.user_id = :user_id
               AND m.deleted_at IS NULL
               AND b.deleted_at IS NULL
             ORDER BY b.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function userHasBusiness(int $userId, int $businessId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM business_user_memberships
             WHERE user_id = :user_id
               AND business_id = :business_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'business_id' => $businessId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public static function roleForBusiness(int $userId, int $businessId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT role
             FROM business_user_memberships
             WHERE user_id = :user_id
               AND business_id = :business_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'business_id' => $businessId,
        ]);

        $role = $stmt->fetchColumn();
        return is_string($role) && $role !== '' ? $role : null;
    }

    public static function assignAdmin(int $businessId, int $userId, int $actorUserId): void
    {
        self::assignRole($businessId, $userId, 'admin', $actorUserId);
    }

    public static function assignRole(int $businessId, int $userId, string $role, int $actorUserId): void
    {
        $normalizedRole = strtolower(trim($role));
        if (!in_array($normalizedRole, ['general_user', 'admin', 'punch_only'], true)) {
            $normalizedRole = 'general_user';
        }

        $existing = self::findMembership($businessId, $userId);
        if ($existing !== null) {
            $stmt = Database::connection()->prepare(
                'UPDATE business_user_memberships
                 SET role = :role,
                     is_active = 1,
                     deleted_at = NULL,
                     deleted_by = NULL,
                     updated_by = :updated_by,
                     updated_at = NOW()
                 WHERE id = :membership_id
                 LIMIT 1'
            );
            $stmt->execute([
                'role' => $normalizedRole,
                'updated_by' => $actorUserId > 0 ? $actorUserId : null,
                'membership_id' => (int) ($existing['id'] ?? 0),
            ]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO business_user_memberships (
                business_id, user_id, role, is_active, created_by, updated_by, created_at, updated_at
             ) VALUES (
                :business_id, :user_id, :role, 1, :created_by, :updated_by, NOW(), NOW()
             )'
        );

        $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'role' => $normalizedRole,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);
    }

    public static function setRoleForBusiness(int $businessId, int $userId, string $role, int $actorUserId): bool
    {
        $normalizedRole = strtolower(trim($role));
        if (!in_array($normalizedRole, ['general_user', 'admin', 'punch_only'], true)) {
            $normalizedRole = 'general_user';
        }

        $stmt = Database::connection()->prepare(
            'UPDATE business_user_memberships
             SET role = :role,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND user_id = :user_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'role' => $normalizedRole,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function setActiveForBusiness(int $businessId, int $userId, bool $isActive, int $actorUserId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE business_user_memberships
             SET is_active = :is_active,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE business_id = :business_id
               AND user_id = :user_id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'is_active' => $isActive ? 1 : 0,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function findForBusiness(int $businessId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, business_id, user_id, role, is_active, deleted_at
             FROM business_user_memberships
             WHERE business_id = :business_id
               AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private static function findMembership(int $businessId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id
             FROM business_user_memberships
             WHERE business_id = :business_id
               AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return list<string>
     */
    public static function digestEmailsForBusiness(int $businessId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT LOWER(TRIM(u.email)) AS email
             FROM business_user_memberships m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.business_id = :business_id
               AND m.deleted_at IS NULL
               AND COALESCE(m.is_active, 1) = 1
               AND m.role IN (\'admin\', \'general_user\')
               AND u.deleted_at IS NULL
               AND COALESCE(u.is_active, 1) = 1
               AND TRIM(u.email) <> \'\''
        );
        $stmt->execute(['business_id' => $businessId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $e = strtolower(trim((string) ($row['email'] ?? '')));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) !== false) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out));
    }
}
