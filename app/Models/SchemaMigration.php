<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class SchemaMigration
{
    private static ?bool $tableAvailable = null;

    public static function isAvailable(): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$tableAvailable = false;
            return false;
        }

        $sql = 'SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = :table
                LIMIT 1';

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'schema' => $schema,
                'table' => 'schema_migrations',
            ]);
            self::$tableAvailable = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }

    public static function latest(): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $sql = 'SELECT migration_key, checksum, applied_at
                FROM schema_migrations
                ORDER BY applied_at DESC, id DESC
                LIMIT 1';

        try {
            $row = Database::connection()->query($sql)->fetch();
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function recent(int $limit = 10): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $safeLimit = max(1, min($limit, 50));
        $sql = 'SELECT migration_key, checksum, applied_at
                FROM schema_migrations
                ORDER BY applied_at DESC, id DESC
                LIMIT ' . $safeLimit;

        try {
            return Database::connection()->query($sql)->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}
