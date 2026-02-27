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
            'role' => 'admin',
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }
}
