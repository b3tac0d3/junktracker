<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Sale
{
    /**
     * @return array<int, string>
     */
    public static function baseTypeOptions(): array
    {
        return ['shop', 'ebay', 'scrap', 'b2b'];
    }

    public static function indexList(int $businessId, string $search = '', string $type = '', int $limit = 25, int $offset = 0): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [];
        }

        $query = trim($search);
        $type = trim($type);

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : "'sale'";
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 's.sale_date' : 'NULL';
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 's.gross_amount'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 's.amount' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 's.net_amount'
            : $grossSql;
        $notesSql = SchemaInspector::hasColumn('sales', 'notes') ? 's.notes' : 'NULL';
        $clientIdSql = SchemaInspector::hasColumn('sales', 'client_id') ? 's.client_id' : 'NULL';

        $clientNameSql = 'NULL';
        $clientJoin = '';
        if (SchemaInspector::hasColumn('sales', 'client_id') && SchemaInspector::hasTable('clients')) {
            $clientJoin = 'LEFT JOIN clients c ON c.id = s.client_id';
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $clientJoin .= ' AND c.deleted_at IS NULL';
            }
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
                $clientJoin .= ' AND c.business_id = s.business_id';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $where[] = 's.sale_type = :sale_type';
        }

        $searchParts = [
            ":query = ''",
            "{$nameSql} LIKE :query_like_1",
            "{$typeSql} LIKE :query_like_2",
            "COALESCE({$notesSql}, '') LIKE :query_like_3",
            'CAST(s.id AS CHAR) LIKE :query_like_4',
        ];
        if ($clientNameSql !== 'NULL') {
            $searchParts[] = "{$clientNameSql} LIKE :query_like_5";
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$clientIdSql} AS client_id,
                    {$clientNameSql} AS client_name
                FROM sales s
                {$clientJoin}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY COALESCE(' . $dateSql . ', DATE(s.created_at)) DESC, s.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $stmt->bindValue(':sale_type', $type);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        if ($clientNameSql !== 'NULL') {
            $stmt->bindValue(':query_like_5', $queryLike);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $type = ''): int
    {
        if (!SchemaInspector::hasTable('sales')) {
            return 0;
        }

        $query = trim($search);
        $type = trim($type);

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : "'sale'";
        $notesSql = SchemaInspector::hasColumn('sales', 'notes') ? 's.notes' : 'NULL';

        $clientNameSql = 'NULL';
        $clientJoin = '';
        if (SchemaInspector::hasColumn('sales', 'client_id') && SchemaInspector::hasTable('clients')) {
            $clientJoin = 'LEFT JOIN clients c ON c.id = s.client_id';
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $clientJoin .= ' AND c.deleted_at IS NULL';
            }
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
                $clientJoin .= ' AND c.business_id = s.business_id';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $where[] = 's.sale_type = :sale_type';
        }

        $searchParts = [
            ":query = ''",
            "{$nameSql} LIKE :query_like_1",
            "{$typeSql} LIKE :query_like_2",
            "COALESCE({$notesSql}, '') LIKE :query_like_3",
            'CAST(s.id AS CHAR) LIKE :query_like_4',
        ];
        if ($clientNameSql !== 'NULL') {
            $searchParts[] = "{$clientNameSql} LIKE :query_like_5";
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';

        $sql = 'SELECT COUNT(*)
                FROM sales s
                ' . $clientJoin . '
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($type !== '' && SchemaInspector::hasColumn('sales', 'sale_type')) {
            $stmt->bindValue(':sale_type', $type);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        if ($clientNameSql !== 'NULL') {
            $stmt->bindValue(':query_like_5', $queryLike);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function typeOptions(int $businessId): array
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'sale_type')) {
            return self::baseTypeOptions();
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 'business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        $where[] = "COALESCE(sale_type, '') <> ''";

        $sql = 'SELECT DISTINCT sale_type
                FROM sales
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY sale_type ASC';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $dbOptions = is_array($rows) ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $rows), static fn ($value): bool => $value !== '')) : [];
        $merged = array_unique(array_merge(self::baseTypeOptions(), $dbOptions));
        sort($merged);
        return array_values($merged);
    }

    public static function create(int $businessId, array $payload, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('sales')) {
            throw new \RuntimeException('Sales table is missing.');
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $append = static function (string $column, string $placeholder, mixed $value) use (&$columns, &$placeholders, &$params): void {
            $columns[] = $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        };

        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $append('business_id', ':business_id', $businessId);
        }
        if (SchemaInspector::hasColumn('sales', 'name')) {
            $append('name', ':name', trim((string) ($payload['name'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_type')) {
            $append('sale_type', ':sale_type', trim((string) ($payload['sale_type'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_date')) {
            $append('sale_date', ':sale_date', $payload['sale_date'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'gross_amount')) {
            $append('gross_amount', ':gross_amount', (float) ($payload['gross_amount'] ?? 0));
        } elseif (SchemaInspector::hasColumn('sales', 'amount')) {
            $append('amount', ':amount', (float) ($payload['gross_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'net_amount')) {
            $append('net_amount', ':net_amount', (float) ($payload['net_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'notes')) {
            $append('notes', ':notes', trim((string) ($payload['notes'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'client_id')) {
            $append('client_id', ':client_id', $payload['client_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'created_by')) {
            $append('created_by', ':created_by', $actorUserId > 0 ? $actorUserId : null);
        }
        if (SchemaInspector::hasColumn('sales', 'updated_by')) {
            $append('updated_by', ':updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        $sql = 'INSERT INTO sales (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function summary(int $businessId): array
    {
        if (!SchemaInspector::hasTable('sales')) {
            return [
                'count' => 0,
                'gross_mtd' => 0.0,
                'net_mtd' => 0.0,
                'gross_ytd' => 0.0,
                'net_ytd' => 0.0,
            ];
        }

        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 'COALESCE(s.gross_amount, 0)'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 'COALESCE(s.amount, 0)' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 'COALESCE(s.net_amount, 0)'
            : $grossSql;
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 's.sale_date' : 'DATE(s.created_at)';

        $where = [];
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$dateSql} <= CURDATE() THEN {$grossSql} ELSE 0 END) AS gross_mtd,
                    SUM(CASE WHEN {$dateSql} >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND {$dateSql} <= CURDATE() THEN {$netSql} ELSE 0 END) AS net_mtd,
                    SUM(CASE WHEN YEAR({$dateSql}) = YEAR(CURDATE()) AND {$dateSql} <= CURDATE() THEN {$grossSql} ELSE 0 END) AS gross_ytd,
                    SUM(CASE WHEN YEAR({$dateSql}) = YEAR(CURDATE()) AND {$dateSql} <= CURDATE() THEN {$netSql} ELSE 0 END) AS net_ytd
                FROM sales s
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        return [
            'count' => (int) ($row['total_count'] ?? 0),
            'gross_mtd' => (float) ($row['gross_mtd'] ?? 0),
            'net_mtd' => (float) ($row['net_mtd'] ?? 0),
            'gross_ytd' => (float) ($row['gross_ytd'] ?? 0),
            'net_ytd' => (float) ($row['net_ytd'] ?? 0),
        ];
    }

    public static function findForBusiness(int $businessId, int $saleId): ?array
    {
        if (!SchemaInspector::hasTable('sales') || $saleId <= 0) {
            return null;
        }

        $nameSql = SchemaInspector::hasColumn('sales', 'name') ? 's.name' : "CONCAT('Sale #', s.id)";
        $typeSql = SchemaInspector::hasColumn('sales', 'sale_type') ? 's.sale_type' : "'sale'";
        $dateSql = SchemaInspector::hasColumn('sales', 'sale_date') ? 's.sale_date' : 'NULL';
        $grossSql = SchemaInspector::hasColumn('sales', 'gross_amount')
            ? 's.gross_amount'
            : (SchemaInspector::hasColumn('sales', 'amount') ? 's.amount' : '0');
        $netSql = SchemaInspector::hasColumn('sales', 'net_amount')
            ? 's.net_amount'
            : $grossSql;
        $notesSql = SchemaInspector::hasColumn('sales', 'notes') ? 's.notes' : 'NULL';
        $clientIdSql = SchemaInspector::hasColumn('sales', 'client_id') ? 's.client_id' : 'NULL';
        $createdAtSql = SchemaInspector::hasColumn('sales', 'created_at') ? 's.created_at' : 'NULL';
        $updatedAtSql = SchemaInspector::hasColumn('sales', 'updated_at') ? 's.updated_at' : 'NULL';

        $clientNameSql = 'NULL';
        $clientJoin = '';
        if (SchemaInspector::hasColumn('sales', 'client_id') && SchemaInspector::hasTable('clients')) {
            $clientJoin = 'LEFT JOIN clients c ON c.id = s.client_id';
            if (SchemaInspector::hasColumn('clients', 'deleted_at')) {
                $clientJoin .= ' AND c.deleted_at IS NULL';
            }
            if (SchemaInspector::hasColumn('clients', 'business_id') && SchemaInspector::hasColumn('sales', 'business_id')) {
                $clientJoin .= ' AND c.business_id = s.business_id';
            }
            $clientNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF(c.company_name, ''), CONCAT('Client #', c.id))";
        }

        $where = [];
        $where[] = 's.id = :sale_id';
        $where[] = SchemaInspector::hasColumn('sales', 'business_id') ? 's.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('sales', 'deleted_at') ? 's.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    s.id,
                    {$nameSql} AS name,
                    {$typeSql} AS sale_type,
                    {$dateSql} AS sale_date,
                    {$grossSql} AS gross_amount,
                    {$netSql} AS net_amount,
                    {$notesSql} AS notes,
                    {$clientIdSql} AS client_id,
                    {$clientNameSql} AS client_name,
                    {$createdAtSql} AS created_at,
                    {$updatedAtSql} AS updated_at
                FROM sales s
                {$clientJoin}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':sale_id', $saleId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function update(int $businessId, int $saleId, array $payload, int $actorUserId): void
    {
        if (!SchemaInspector::hasTable('sales') || $saleId <= 0) {
            return;
        }

        $assignments = [];
        $params = [
            'business_id' => $businessId,
            'sale_id' => $saleId,
        ];

        $append = static function (string $column, string $param, mixed $value) use (&$assignments, &$params): void {
            $assignments[] = $column . ' = :' . $param;
            $params[$param] = $value;
        };

        if (SchemaInspector::hasColumn('sales', 'name')) {
            $append('name', 'name', trim((string) ($payload['name'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_type')) {
            $append('sale_type', 'sale_type', trim((string) ($payload['sale_type'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'sale_date')) {
            $append('sale_date', 'sale_date', $payload['sale_date'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'gross_amount')) {
            $append('gross_amount', 'gross_amount', (float) ($payload['gross_amount'] ?? 0));
        } elseif (SchemaInspector::hasColumn('sales', 'amount')) {
            $append('amount', 'amount', (float) ($payload['gross_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'net_amount')) {
            $append('net_amount', 'net_amount', (float) ($payload['net_amount'] ?? 0));
        }
        if (SchemaInspector::hasColumn('sales', 'notes')) {
            $append('notes', 'notes', trim((string) ($payload['notes'] ?? '')));
        }
        if (SchemaInspector::hasColumn('sales', 'client_id')) {
            $append('client_id', 'client_id', $payload['client_id'] ?? null);
        }
        if (SchemaInspector::hasColumn('sales', 'updated_by')) {
            $append('updated_by', 'updated_by', $actorUserId > 0 ? $actorUserId : null);
        }

        if ($assignments === []) {
            return;
        }

        $sql = 'UPDATE sales
                SET ' . implode(', ', $assignments) . '
                WHERE id = :sale_id';
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('sales', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $businessId, int $saleId, int $actorUserId): bool
    {
        if (!SchemaInspector::hasTable('sales') || !SchemaInspector::hasColumn('sales', 'deleted_at') || $saleId <= 0) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        $params = [
            'sale_id' => $saleId,
        ];

        if (SchemaInspector::hasColumn('sales', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('sales', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $where = ['id = :sale_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('sales', 'business_id')) {
            $where[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }

        $sql = 'UPDATE sales
                SET ' . implode(', ', $setParts) . '
                WHERE ' . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
