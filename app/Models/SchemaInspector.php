<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

/**
 * Schema introspection. In production, loads TABLE + COLUMN catalogs in two queries per request
 * instead of one query per hasTable/hasColumn call.
 */
final class SchemaInspector
{
    /** @var array<string, bool> */
    private static array $tableCache = [];

    /** @var array<string, bool> */
    private static array $columnCache = [];

    /** null = not attempted; true = use in-memory catalog; false = fall back to per-call queries */
    private static ?bool $catalogReady = null;

    /** @var array<string, true> */
    private static array $catalogTables = [];

    /** @var array<string, true> */
    private static array $catalogColumns = [];

    private static function tryLoadCatalog(): void
    {
        if (self::$catalogReady !== null) {
            return;
        }

        self::$catalogReady = false;
        self::$catalogTables = [];
        self::$catalogColumns = [];

        try {
            $pdo = Database::connection();

            $stmt = $pdo->query(
                "SELECT TABLE_NAME
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_TYPE = 'BASE TABLE'"
            );
            if ($stmt !== false) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $name = strtolower((string) ($row['TABLE_NAME'] ?? ''));
                    if ($name !== '') {
                        self::$catalogTables[$name] = true;
                    }
                }
            }

            $stmt = $pdo->query(
                "SELECT TABLE_NAME, COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()"
            );
            if ($stmt !== false) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $t = strtolower((string) ($row['TABLE_NAME'] ?? ''));
                    $c = strtolower((string) ($row['COLUMN_NAME'] ?? ''));
                    if ($t !== '' && $c !== '') {
                        self::$catalogColumns[$t . '.' . $c] = true;
                    }
                }
            }

            self::$catalogReady = true;
        } catch (\Throwable $e) {
            self::$catalogReady = false;
        }
    }

    public static function hasTable(string $table): bool
    {
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, self::$tableCache)) {
            return self::$tableCache[$cacheKey];
        }

        self::tryLoadCatalog();
        if (self::$catalogReady === true) {
            $exists = isset(self::$catalogTables[$cacheKey]);
            self::$tableCache[$cacheKey] = $exists;

            return $exists;
        }

        $pdo = Database::connection();

        try {
            $stmt = $pdo->prepare(
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

        self::tryLoadCatalog();
        if (self::$catalogReady === true) {
            $exists = isset(self::$catalogColumns[$cacheKey]);
            self::$columnCache[$cacheKey] = $exists;

            return $exists;
        }

        $pdo = Database::connection();

        try {
            $stmt = $pdo->prepare(
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
