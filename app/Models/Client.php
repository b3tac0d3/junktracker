<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Client
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    /** @var array<string, bool> */
    private static array $tableCache = [];

    public static function indexList(int $businessId, string $search = '', int $limit = 250): array
    {
        $pdo = Database::connection();
        $query = trim($search);

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT
                    c.id,
                    c.first_name,
                    c.last_name,
                    {$companySql} AS company_name,
                    {$phoneSql} AS phone,
                    {$citySql} AS city
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  AND (
                    :query = ''
                    OR CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''), COALESCE({$companySql}, '')) LIKE :query_like_1
                    OR COALESCE({$phoneSql}, '') LIKE :query_like_2
                    OR COALESCE({$citySql}, '') LIKE :query_like_3
                  )
                ORDER BY
                    COALESCE(NULLIF(c.last_name, ''), {$companySql}, c.first_name, '') ASC,
                    COALESCE(NULLIF(c.first_name, ''), '') ASC,
                    c.id DESC
                LIMIT :row_limit";

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $clientId): ?array
    {
        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $emailSql = self::hasColumn('clients', 'email') ? 'c.email' : 'NULL';
        $secondaryPhoneSql = self::hasColumn('clients', 'secondary_phone')
            ? 'c.secondary_phone'
            : (self::hasColumn('clients', 'phone_secondary')
                ? 'c.phone_secondary'
                : (self::hasColumn('clients', 'alt_phone') ? 'c.alt_phone' : 'NULL'));
        $canTextSql = self::hasColumn('clients', 'can_text') ? 'c.can_text' : 'NULL';
        $secondaryCanTextSql = self::hasColumn('clients', 'secondary_can_text')
            ? 'c.secondary_can_text'
            : (self::hasColumn('clients', 'can_text_secondary')
                ? 'c.can_text_secondary'
                : (self::hasColumn('clients', 'secondary_phone_can_text') ? 'c.secondary_phone_can_text' : 'NULL'));
        $primaryNoteSql = self::hasColumn('clients', 'primary_note')
            ? 'c.primary_note'
            : (self::hasColumn('clients', 'notes')
                ? 'c.notes'
                : (self::hasColumn('clients', 'note') ? 'c.note' : 'NULL'));
        $addressLine1Sql = self::hasColumn('clients', 'address_line1') ? 'c.address_line1' : 'NULL';
        $addressLine2Sql = self::hasColumn('clients', 'address_line2') ? 'c.address_line2' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $stateSql = self::hasColumn('clients', 'state') ? 'c.state' : 'NULL';
        $postalCodeSql = self::hasColumn('clients', 'postal_code') ? 'c.postal_code' : 'NULL';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT
                    c.id,
                    c.first_name,
                    c.last_name,
                    {$companySql} AS company_name,
                    {$emailSql} AS email,
                    {$phoneSql} AS phone,
                    {$secondaryPhoneSql} AS secondary_phone,
                    {$canTextSql} AS can_text,
                    {$secondaryCanTextSql} AS secondary_can_text,
                    {$primaryNoteSql} AS primary_note,
                    {$addressLine1Sql} AS address_line1,
                    {$addressLine2Sql} AS address_line2,
                    {$citySql} AS city,
                    {$stateSql} AS state,
                    {$postalCodeSql} AS postal_code
                FROM clients c
                WHERE {$businessWhere}
                  AND c.id = :client_id
                  AND {$deletedWhere}
                LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function financialSummary(int $businessId, int $clientId): array
    {
        $gross = 0.0;
        $expenses = 0.0;

        if (self::hasTable('invoices')) {
            $invoiceTotalExpr = self::hasColumn('invoices', 'total')
                ? 'i.total'
                : (self::hasColumn('invoices', 'subtotal')
                    ? 'i.subtotal'
                    : (self::hasColumn('invoices', 'amount') ? 'i.amount' : '0'));

            $invoiceWhere = ['i.business_id = :business_id', 'i.client_id = :client_id'];
            if (self::hasColumn('invoices', 'deleted_at')) {
                $invoiceWhere[] = 'i.deleted_at IS NULL';
            }
            if (self::hasColumn('invoices', 'type')) {
                $invoiceWhere[] = "(i.type = 'invoice' OR i.type IS NULL)";
            }
            if (self::hasColumn('invoices', 'status')) {
                $invoiceWhere[] = "(i.status IS NULL OR i.status <> 'cancelled')";
            }

            $sql = 'SELECT COALESCE(SUM(' . $invoiceTotalExpr . '), 0) FROM invoices i WHERE ' . implode(' AND ', $invoiceWhere);
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'business_id' => $businessId,
                'client_id' => $clientId,
            ]);
            $gross = (float) $stmt->fetchColumn();
        }

        if (self::hasTable('expenses') && self::hasColumn('expenses', 'amount')) {
            $deletedCondition = self::hasColumn('expenses', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
            $businessCondition = self::hasColumn('expenses', 'business_id') ? 'e.business_id = :business_id' : '1=1';

            if (self::hasColumn('expenses', 'client_id')) {
                $sql = 'SELECT COALESCE(SUM(e.amount), 0)
                        FROM expenses e
                        WHERE ' . $businessCondition . '
                          AND e.client_id = :client_id
                          AND ' . $deletedCondition;

                $stmt = Database::connection()->prepare($sql);
                $params = ['client_id' => $clientId];
                if (self::hasColumn('expenses', 'business_id')) {
                    $params['business_id'] = $businessId;
                }
                $stmt->execute($params);
                $expenses = (float) $stmt->fetchColumn();
            } elseif (self::hasColumn('expenses', 'job_id') && self::hasTable('jobs')) {
                $jobBusinessCondition = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
                $jobDeletedCondition = self::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';

                $sql = 'SELECT COALESCE(SUM(e.amount), 0)
                        FROM expenses e
                        INNER JOIN jobs j ON j.id = e.job_id
                        WHERE ' . $businessCondition . '
                          AND ' . $deletedCondition . '
                          AND j.client_id = :client_id
                          AND ' . $jobBusinessCondition . '
                          AND ' . $jobDeletedCondition;

                $stmt = Database::connection()->prepare($sql);
                $params = ['client_id' => $clientId];
                if (self::hasColumn('expenses', 'business_id') || self::hasColumn('jobs', 'business_id')) {
                    $params['business_id'] = $businessId;
                }
                $stmt->execute($params);
                $expenses = (float) $stmt->fetchColumn();
            }
        }

        $net = $gross - $expenses;

        return [
            'gross_income' => $gross,
            'expenses' => $expenses,
            'net_income' => $net,
        ];
    }

    public static function jobsByStatus(int $businessId, int $clientId): array
    {
        $summary = [
            'prospect' => 0,
            'pending' => 0,
            'active' => 0,
            'complete' => 0,
            'cancelled' => 0,
        ];

        if (!self::hasTable('jobs')) {
            return $summary;
        }

        $businessWhere = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1 = 1';
        $statusExpr = self::hasColumn('jobs', 'status') ? 'LOWER(j.status)' : "'pending'";

        $sql = "SELECT {$statusExpr} AS status_key, COUNT(*) AS total
                FROM jobs j
                WHERE {$businessWhere}
                  AND j.client_id = :client_id
                  AND {$deletedWhere}
                GROUP BY {$statusExpr}";

        $stmt = Database::connection()->prepare($sql);
        $params = ['client_id' => $clientId];
        if (self::hasColumn('jobs', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower((string) ($row['status_key'] ?? ''));
            if (array_key_exists($key, $summary)) {
                $summary[$key] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    public static function jobHistory(int $businessId, int $clientId, int $limit = 50): array
    {
        if (!self::hasTable('jobs')) {
            return [];
        }

        $titleSql = self::hasColumn('jobs', 'title')
            ? 'j.title'
            : (self::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $statusSql = self::hasColumn('jobs', 'status') ? 'j.status' : "'pending'";
        $startSql = self::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (self::hasColumn('jobs', 'start_date') ? 'j.start_date' : 'NULL');
        $endSql = self::hasColumn('jobs', 'scheduled_end_at')
            ? 'j.scheduled_end_at'
            : (self::hasColumn('jobs', 'end_date') ? 'j.end_date' : 'NULL');
        $businessWhere = self::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT
                    j.id,
                    {$titleSql} AS title,
                    {$statusSql} AS status,
                    {$startSql} AS scheduled_start_at,
                    {$endSql} AS scheduled_end_at
                FROM jobs j
                WHERE {$businessWhere}
                  AND j.client_id = :client_id
                  AND {$deletedWhere}
                ORDER BY j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 200)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private static function hasTable(string $table): bool
    {
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, self::$tableCache)) {
            return self::$tableCache[$cacheKey];
        }

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
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

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
    }
}
