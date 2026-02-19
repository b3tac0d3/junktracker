<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class UserFilterPreset
{
    public const MODULE_KEYS = ['jobs', 'tasks', 'time_tracking', 'expenses'];

    public static function isSupportedModule(string $moduleKey): bool
    {
        return in_array(trim($moduleKey), self::MODULE_KEYS, true);
    }

    public static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_filter_presets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            module_key VARCHAR(50) NOT NULL,
            preset_name VARCHAR(80) NOT NULL,
            filters_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_filter_preset_name (user_id, module_key, preset_name),
            KEY idx_user_filter_presets_user_module (user_id, module_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $ensured = true;
    }

    public static function forUser(int $userId, string $moduleKey): array
    {
        self::ensureTable();

        if ($userId <= 0 || !self::isSupportedModule($moduleKey)) {
            return [];
        }

        $sql = 'SELECT id,
                       user_id,
                       module_key,
                       preset_name,
                       filters_json,
                       created_at,
                       updated_at
                FROM user_filter_presets
                WHERE user_id = :user_id
                  AND module_key = :module_key
                ORDER BY preset_name ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'module_key' => $moduleKey,
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['filters'] = self::decodeFilters((string) ($row['filters_json'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public static function findForUser(int $id, int $userId, string $moduleKey): ?array
    {
        self::ensureTable();

        if ($id <= 0 || $userId <= 0 || !self::isSupportedModule($moduleKey)) {
            return null;
        }

        $sql = 'SELECT id,
                       user_id,
                       module_key,
                       preset_name,
                       filters_json,
                       created_at,
                       updated_at
                FROM user_filter_presets
                WHERE id = :id
                  AND user_id = :user_id
                  AND module_key = :module_key
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'module_key' => $moduleKey,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['filters'] = self::decodeFilters((string) ($row['filters_json'] ?? ''));

        return $row;
    }

    public static function save(int $userId, string $moduleKey, string $presetName, array $filters): int
    {
        self::ensureTable();

        if ($userId <= 0 || !self::isSupportedModule($moduleKey)) {
            return 0;
        }

        $name = self::normalizeName($presetName);
        if ($name === '') {
            return 0;
        }

        $json = self::encodeFilters($filters);

        $sql = 'INSERT INTO user_filter_presets
                    (user_id, module_key, preset_name, filters_json, created_at, updated_at)
                VALUES
                    (:user_id, :module_key, :preset_name, :filters_json, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    filters_json = VALUES(filters_json),
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'module_key' => $moduleKey,
            'preset_name' => $name,
            'filters_json' => $json,
        ]);

        $lookup = Database::connection()->prepare(
            'SELECT id
             FROM user_filter_presets
             WHERE user_id = :user_id
               AND module_key = :module_key
               AND preset_name = :preset_name
             LIMIT 1'
        );
        $lookup->execute([
            'user_id' => $userId,
            'module_key' => $moduleKey,
            'preset_name' => $name,
        ]);

        return (int) ($lookup->fetchColumn() ?: 0);
    }

    public static function delete(int $id, int $userId, string $moduleKey): bool
    {
        self::ensureTable();

        if ($id <= 0 || $userId <= 0 || !self::isSupportedModule($moduleKey)) {
            return false;
        }

        $sql = 'DELETE FROM user_filter_presets
                WHERE id = :id
                  AND user_id = :user_id
                  AND module_key = :module_key
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'module_key' => $moduleKey,
        ]);

        return $stmt->rowCount() > 0;
    }

    private static function normalizeName(string $name): string
    {
        $clean = trim($name);
        if ($clean === '') {
            return '';
        }

        if (mb_strlen($clean) > 80) {
            $clean = mb_substr($clean, 0, 80);
        }

        return $clean;
    }

    private static function encodeFilters(array $filters): string
    {
        $normalized = [];
        foreach ($filters as $key => $value) {
            $filterKey = trim((string) $key);
            if ($filterKey === '') {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
                $normalized[$filterKey] = $value;
                continue;
            }

            if ($value === null) {
                $normalized[$filterKey] = '';
                continue;
            }

            if (is_array($value)) {
                $normalized[$filterKey] = implode(',', array_map(static fn (mixed $item): string => trim((string) $item), $value));
                continue;
            }

            $normalized[$filterKey] = trim((string) $value);
        }

        $json = json_encode($normalized);
        return $json === false ? '{}' : $json;
    }

    private static function decodeFilters(string $json): array
    {
        $raw = trim($json);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $filters = [];
        foreach ($decoded as $key => $value) {
            $filterKey = trim((string) $key);
            if ($filterKey === '') {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $filters[$filterKey] = $value;
            }
        }

        return $filters;
    }
}
