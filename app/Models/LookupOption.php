<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class LookupOption
{
    private static ?bool $available = null;
    private static array $cache = [];

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            $schema = (string) config('database.database', '');
            if ($schema === '') {
                self::$available = false;
                return false;
            }

            $stmt = Database::connection()->prepare(
                'SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = :schema
                   AND TABLE_NAME = :table
                 LIMIT 1'
            );
            $stmt->execute([
                'schema' => $schema,
                'table' => 'app_lookups',
            ]);
            self::$available = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$available = false;
        }

        return self::$available;
    }

    public static function groups(): array
    {
        return [
            'job_status' => 'Job Status',
            'prospect_status' => 'Prospect Status',
            'prospect_next_step' => 'Prospect Next Step',
        ];
    }

    public static function options(string $groupKey): array
    {
        $group = trim($groupKey);
        if ($group === '') {
            return [];
        }

        if (isset(self::$cache[$group])) {
            return self::$cache[$group];
        }

        if (!self::isAvailable()) {
            self::$cache[$group] = [];
            return [];
        }

        try {
            $sql = 'SELECT id,
                           group_key,
                           value_key,
                           label,
                           sort_order,
                           active
                    FROM app_lookups
                    WHERE group_key = :group_key
                      AND deleted_at IS NULL
                    ORDER BY sort_order ASC, id ASC';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['group_key' => $group]);
            $rows = $stmt->fetchAll();
            self::$cache[$group] = $rows;
        } catch (Throwable) {
            self::$cache[$group] = [];
        }

        return self::$cache[$group];
    }

    public static function allForAdmin(string $groupKey): array
    {
        $group = trim($groupKey);
        if ($group === '' || !self::isAvailable()) {
            return [];
        }

        $sql = 'SELECT id,
                       group_key,
                       value_key,
                       label,
                       sort_order,
                       active,
                       deleted_at,
                       created_at,
                       updated_at
                FROM app_lookups
                WHERE group_key = :group_key
                ORDER BY deleted_at IS NOT NULL ASC, sort_order ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['group_key' => $group]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        if ($id <= 0 || !self::isAvailable()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, group_key, value_key, label, sort_order, active, deleted_at
             FROM app_lookups
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $sql = 'INSERT INTO app_lookups
                    (group_key, value_key, label, sort_order, active, created_by, updated_by, created_at, updated_at)
                VALUES
                    (:group_key, :value_key, :label, :sort_order, :active, :created_by, :updated_by, NOW(), NOW())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'group_key' => $data['group_key'],
            'value_key' => $data['value_key'],
            'label' => $data['label'],
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'active' => !empty($data['active']) ? 1 : 0,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        self::clearGroupCache((string) $data['group_key']);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        if ($id <= 0 || !self::isAvailable()) {
            return;
        }

        $existing = self::findById($id);
        if (!$existing) {
            return;
        }

        $sql = 'UPDATE app_lookups
                SET group_key = :group_key,
                    value_key = :value_key,
                    label = :label,
                    sort_order = :sort_order,
                    active = :active,
                    deleted_at = NULL,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'group_key' => $data['group_key'],
            'value_key' => $data['value_key'],
            'label' => $data['label'],
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'active' => !empty($data['active']) ? 1 : 0,
            'updated_by' => $actorId,
        ]);

        self::clearGroupCache((string) $existing['group_key']);
        self::clearGroupCache((string) $data['group_key']);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        if ($id <= 0 || !self::isAvailable()) {
            return;
        }

        $existing = self::findById($id);
        if (!$existing) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE app_lookups
             SET active = 0,
                 deleted_at = COALESCE(deleted_at, NOW()),
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'updated_by' => $actorId,
        ]);

        self::clearGroupCache((string) $existing['group_key']);
    }

    public static function seedDefaults(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $defaults = [
            'job_status' => [
                ['pending', 'Pending', 10],
                ['active', 'Active', 20],
                ['complete', 'Complete', 30],
                ['cancelled', 'Cancelled', 40],
            ],
            'prospect_status' => [
                ['active', 'Active', 10],
                ['converted', 'Converted', 20],
                ['closed', 'Closed', 30],
            ],
            'prospect_next_step' => [
                ['follow_up', 'Follow Up', 10],
                ['call', 'Call', 20],
                ['text', 'Text', 30],
                ['send_quote', 'Send Quote', 40],
                ['make_appointment', 'Make Appointment', 50],
                ['other', 'Other', 60],
            ],
        ];

        $sql = 'INSERT IGNORE INTO app_lookups
                    (group_key, value_key, label, sort_order, active, created_at, updated_at)
                VALUES
                    (:group_key, :value_key, :label, :sort_order, 1, NOW(), NOW())';
        $stmt = Database::connection()->prepare($sql);

        foreach ($defaults as $group => $rows) {
            foreach ($rows as [$valueKey, $label, $sortOrder]) {
                $stmt->execute([
                    'group_key' => $group,
                    'value_key' => $valueKey,
                    'label' => $label,
                    'sort_order' => $sortOrder,
                ]);
            }
            self::clearGroupCache($group);
        }
    }

    private static function clearGroupCache(string $groupKey): void
    {
        unset(self::$cache[$groupKey]);
    }
}
