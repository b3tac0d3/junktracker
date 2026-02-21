<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Schema
{
    private static array $tableCache = [];
    private static array $columnCache = [];

    public static function tableExists(string $table): bool
    {
        $tableName = trim($table);
        if ($tableName === '') {
            return false;
        }

        if (array_key_exists($tableName, self::$tableCache)) {
            return self::$tableCache[$tableName];
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$tableCache[$tableName] = false;
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
            'table' => $tableName,
        ]);

        self::$tableCache[$tableName] = (bool) $stmt->fetchColumn();
        return self::$tableCache[$tableName];
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$columnCache[$cacheKey] = false;
            return false;
        }

        $sql = 'SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'schema' => $schema,
            'table' => $table,
            'column' => $column,
        ]);

        self::$columnCache[$cacheKey] = (bool) $stmt->fetchColumn();
        return self::$columnCache[$cacheKey];
    }
}
