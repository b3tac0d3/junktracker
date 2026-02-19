<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class AppSetting
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

            $sql = 'SELECT 1
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = :schema
                      AND TABLE_NAME = :table
                    LIMIT 1';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'schema' => $schema,
                'table' => 'app_settings',
            ]);

            self::$available = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$available = false;
        }

        return self::$available;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $normalized = trim($key);
        if ($normalized === '') {
            return $default;
        }

        if (array_key_exists($normalized, self::$cache)) {
            return self::$cache[$normalized];
        }

        if (!self::isAvailable()) {
            self::$cache[$normalized] = $default;
            return $default;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT setting_value
                 FROM app_settings
                 WHERE setting_key = :setting_key
                 LIMIT 1'
            );
            $stmt->execute(['setting_key' => $normalized]);
            $value = $stmt->fetchColumn();
            self::$cache[$normalized] = $value !== false ? (string) $value : $default;
        } catch (Throwable) {
            self::$cache[$normalized] = $default;
        }

        return self::$cache[$normalized];
    }

    public static function all(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        try {
            $rows = Database::connection()->query(
                'SELECT setting_key, setting_value
                 FROM app_settings
                 ORDER BY setting_key ASC'
            )->fetchAll();

            $output = [];
            foreach ($rows as $row) {
                $key = (string) ($row['setting_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $output[$key] = (string) ($row['setting_value'] ?? '');
            }
            self::$cache = array_merge(self::$cache, $output);

            return $output;
        } catch (Throwable) {
            return [];
        }
    }

    public static function setMany(array $pairs, ?int $actorId = null): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $pdo = Database::connection();
        $sql = 'INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at)
                VALUES (:setting_key, :setting_value, :updated_by, NOW())
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()';
        $stmt = $pdo->prepare($sql);

        foreach ($pairs as $key => $value) {
            $settingKey = trim((string) $key);
            if ($settingKey === '') {
                continue;
            }

            $settingValue = is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : json_encode($value);

            $stmt->execute([
                'setting_key' => $settingKey,
                'setting_value' => $settingValue,
                'updated_by' => $actorId,
            ]);

            self::$cache[$settingKey] = $settingValue;
        }
    }
}

