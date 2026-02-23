<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Schema
{
    private static array $tableCache = [];
    private static array $columnCache = [];
    private static bool $cacheBootstrapped = false;
    private static bool $cacheDirty = false;
    private static array $tableColumnsCache = [];

    public static function tableExists(string $table): bool
    {
        self::bootstrapCache();

        $tableName = trim($table);
        if ($tableName === '') {
            return false;
        }

        if (array_key_exists($tableName, self::$tableCache)) {
            return self::$tableCache[$tableName];
        }

        if (isset(self::$tableColumnsCache[$tableName])) {
            self::$tableCache[$tableName] = true;
            return true;
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
        self::$cacheDirty = true;
        self::persistCache();
        return self::$tableCache[$tableName];
    }

    public static function hasColumn(string $table, string $column): bool
    {
        self::bootstrapCache();

        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        $tableName = trim($table);
        $columnName = trim($column);
        if ($tableName === '' || $columnName === '') {
            self::$columnCache[$cacheKey] = false;
            return false;
        }

        $columns = self::loadTableColumns($tableName);
        self::$columnCache[$cacheKey] = isset($columns[$columnName]);
        return self::$columnCache[$cacheKey];
    }

    private static function loadTableColumns(string $table): array
    {
        if (isset(self::$tableColumnsCache[$table])) {
            return self::$tableColumnsCache[$table];
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$tableColumnsCache[$table] = [];
            self::$tableCache[$table] = false;
            return self::$tableColumnsCache[$table];
        }

        $stmt = Database::connection()->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table'
        );
        $stmt->execute([
            'schema' => $schema,
            'table' => $table,
        ]);

        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['COLUMN_NAME'] ?? ''));
            if ($name === '') {
                continue;
            }
            $columns[$name] = true;
            self::$columnCache[$table . '.' . $name] = true;
        }

        self::$tableColumnsCache[$table] = $columns;
        self::$tableCache[$table] = !empty($columns);
        self::$cacheDirty = true;
        self::persistCache();

        return $columns;
    }

    private static function bootstrapCache(): void
    {
        if (self::$cacheBootstrapped) {
            return;
        }
        self::$cacheBootstrapped = true;

        $path = self::cacheFilePath();
        if (!is_file($path)) {
            return;
        }

        $ttl = self::cacheTtlSeconds();
        if ($ttl > 0) {
            $mtime = @filemtime($path);
            if ($mtime !== false && $mtime < (time() - $ttl)) {
                return;
            }
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        $schema = (string) config('database.database', '');
        if ((string) ($decoded['schema'] ?? '') !== $schema) {
            return;
        }

        $tables = is_array($decoded['tables'] ?? null) ? $decoded['tables'] : [];
        foreach ($tables as $table => $columns) {
            if (!is_string($table)) {
                continue;
            }

            if (!is_array($columns)) {
                self::$tableCache[$table] = false;
                self::$tableColumnsCache[$table] = [];
                continue;
            }

            $columnMap = [];
            foreach ($columns as $column) {
                if (!is_string($column) || trim($column) === '') {
                    continue;
                }

                $columnMap[$column] = true;
                self::$columnCache[$table . '.' . $column] = true;
            }

            self::$tableColumnsCache[$table] = $columnMap;
            self::$tableCache[$table] = !empty($columnMap);
        }
    }

    private static function persistCache(): void
    {
        if (!self::$cacheDirty) {
            return;
        }

        $tables = [];
        foreach (self::$tableColumnsCache as $table => $columns) {
            if (!is_array($columns)) {
                continue;
            }
            $tables[$table] = array_keys($columns);
        }

        $payload = json_encode([
            'schema' => (string) config('database.database', ''),
            'generated_at' => date('c'),
            'tables' => $tables,
        ]);
        if (!is_string($payload)) {
            return;
        }

        $path = self::cacheFilePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        @file_put_contents($path, $payload, LOCK_EX);
        self::$cacheDirty = false;
    }

    private static function cacheFilePath(): string
    {
        return BASE_PATH . '/storage/cache/schema_map.json';
    }

    private static function cacheTtlSeconds(): int
    {
        $ttl = (int) config('app.schema_cache_ttl', 600);
        return max(0, min(86400, $ttl));
    }
}
