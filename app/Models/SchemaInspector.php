<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class SchemaInspector
{
    /** @var array<string, bool> */
    private static array $tableCache = [];

    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function hasTable(string $table): bool
    {
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, self::$tableCache)) {
            return self::$tableCache[$cacheKey];
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                 LIMIT 1'
            );
            $stmt->execute(['table_name' => $table]);

            $exists = is_array($stmt->fetch());
            self::$tableCache[$cacheKey] = $exists;

            return $exists;
        } catch (\Throwable $e) {
            self::$tableCache[$cacheKey] = false;

            return false;
        }
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT 1
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name
                 LIMIT 1'
            );
            $stmt->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);

            $exists = is_array($stmt->fetch());
            self::$columnCache[$cacheKey] = $exists;

            return $exists;
        } catch (\Throwable $e) {
            self::$columnCache[$cacheKey] = false;

            return false;
        }
    }
}

