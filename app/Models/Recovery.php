<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class Recovery
{
    private const ENTITIES = [
        'jobs' => ['table' => 'jobs', 'label' => 'Jobs', 'title_column' => 'name', 'active_column' => 'active'],
        'clients' => ['table' => 'clients', 'label' => 'Clients', 'title_column' => null, 'active_column' => 'active'],
        'employees' => ['table' => 'employees', 'label' => 'Employees', 'title_column' => null, 'active_column' => 'active'],
        'prospects' => ['table' => 'prospects', 'label' => 'Prospects', 'title_column' => null, 'active_column' => 'active'],
        'sales' => ['table' => 'sales', 'label' => 'Sales', 'title_column' => 'name', 'active_column' => 'active'],
        'companies' => ['table' => 'companies', 'label' => 'Companies', 'title_column' => 'name', 'active_column' => 'active'],
        'estates' => ['table' => 'estates', 'label' => 'Estates', 'title_column' => 'name', 'active_column' => 'active'],
        'consignors' => ['table' => 'consignors', 'label' => 'Consignors', 'title_column' => null, 'active_column' => 'active'],
    ];

    public static function entities(): array
    {
        return array_map(
            static fn (array $meta): string => (string) ($meta['label'] ?? ''),
            self::ENTITIES
        );
    }

    public static function deleted(string $entityKey, string $query = '', int $limit = 200): array
    {
        $entity = self::ENTITIES[$entityKey] ?? null;
        if ($entity === null) {
            return [];
        }

        $table = (string) $entity['table'];
        if (!Schema::hasColumn($table, 'deleted_at')) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $params = [];
        $titleSql = self::titleSelectSql($entityKey);
        $sql = 'SELECT id,
                       ' . $titleSql . ' AS item_title,
                       deleted_at,
                       updated_at
                FROM ' . $table . '
                WHERE deleted_at IS NOT NULL';

        $needle = trim($query);
        if ($needle !== '') {
            $sql .= ' AND (' . self::searchSql($entityKey) . ')';
            $params['q'] = '%' . $needle . '%';
        }

        $sql .= ' ORDER BY deleted_at DESC, id DESC LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function restore(string $entityKey, int $id, ?int $actorId = null): bool
    {
        $entity = self::ENTITIES[$entityKey] ?? null;
        if ($entity === null || $id <= 0) {
            return false;
        }

        $table = (string) $entity['table'];
        if (!Schema::hasColumn($table, 'deleted_at')) {
            return false;
        }

        $sets = ['deleted_at = NULL'];
        $params = ['id' => $id];

        $activeColumn = (string) ($entity['active_column'] ?? '');
        if ($activeColumn !== '' && Schema::hasColumn($table, $activeColumn)) {
            $sets[] = $activeColumn . ' = 1';
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($actorId !== null && Schema::hasColumn($table, 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if (Schema::hasColumn($table, 'deleted_by')) {
            $sets[] = 'deleted_by = NULL';
        }

        $sql = 'UPDATE ' . $table . '
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NOT NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function softDeleteCounts(): array
    {
        $counts = [];
        foreach (self::ENTITIES as $entityKey => $meta) {
            $table = (string) $meta['table'];
            $counts[$entityKey] = self::countDeletedRows($table);
        }

        return $counts;
    }

    private static function countDeletedRows(string $table): int
    {
        if (!Schema::hasColumn($table, 'deleted_at')) {
            return 0;
        }

        try {
            $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NOT NULL';
            return (int) Database::connection()->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function titleSelectSql(string $entityKey): string
    {
        return match ($entityKey) {
            'clients' => 'COALESCE(NULLIF(business_name, \'\'), NULLIF(TRIM(CONCAT_WS(\' \', first_name, last_name)), \'\'), CONCAT(\'Client #\', id))',
            'employees' => 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', first_name, last_name)), \'\'), CONCAT(\'Employee #\', id))',
            'prospects' => 'CONCAT(\'Prospect #\', id)',
            'consignors' => 'COALESCE(NULLIF(business_name, \'\'), NULLIF(TRIM(CONCAT_WS(\' \', first_name, last_name)), \'\'), CONCAT(\'Consignor #\', id))',
            default => 'COALESCE(NULLIF(name, \'\'), CONCAT(UCASE(LEFT(\'' . $entityKey . '\', 1)), SUBSTRING(\'' . $entityKey . '\', 2), \' #\', id))',
        };
    }

    private static function searchSql(string $entityKey): string
    {
        return match ($entityKey) {
            'clients' => 'business_name LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q OR CAST(id AS CHAR) LIKE :q',
            'employees' => 'first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q OR CAST(id AS CHAR) LIKE :q',
            'prospects' => 'note LIKE :q OR next_step LIKE :q OR CAST(id AS CHAR) LIKE :q',
            'consignors' => 'business_name LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q OR consignor_number LIKE :q OR CAST(id AS CHAR) LIKE :q',
            default => 'name LIKE :q OR CAST(id AS CHAR) LIKE :q',
        };
    }
}
