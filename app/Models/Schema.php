<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Schema
{
    private static array $columnCache = [];

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
