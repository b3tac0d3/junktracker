<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ExpenseCategory
{
    public static function allActive(): array
    {
        self::ensureTable();

        $sql = 'SELECT id, name, note, created_at, updated_at
                FROM expense_categories
                WHERE deleted_at IS NULL
                  AND active = 1
                  ' . (Schema::hasColumn('expense_categories', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '
                ORDER BY name';

        $stmt = Database::connection()->prepare($sql);
        $params = [];
        if (Schema::hasColumn('expense_categories', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();

        $sql = 'SELECT id, name, note, active, deleted_at
                FROM expense_categories
                WHERE id = :id
                  AND deleted_at IS NULL
                  ' . (Schema::hasColumn('expense_categories', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if (Schema::hasColumn('expense_categories', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureTable();

        $columns = ['name', 'note', 'active', 'created_at', 'updated_at'];
        $values = [':name', ':note', '1', 'NOW()', 'NOW()'];
        $params = [
            'name' => $data['name'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('expense_categories', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('expense_categories', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }
        if (Schema::hasColumn('expense_categories', 'business_id')) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql = 'INSERT INTO expense_categories (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'name = :name',
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'name' => $data['name'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('expense_categories', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE expense_categories
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL';
        if (Schema::hasColumn('expense_categories', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'active = 0',
            'deleted_at = NOW()',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('expense_categories', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('expense_categories', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE expense_categories
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL';
        if (Schema::hasColumn('expense_categories', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    private static function ensureTable(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS expense_categories (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    note TEXT NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    deleted_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_expense_categories_name (name),
                    KEY idx_expense_categories_deleted_at (deleted_at),
                    KEY idx_expense_categories_active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        Database::connection()->exec($sql);
        $ensured = true;
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
