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

    public static function indexList(int $businessId, string $search = '', int $limit = 25, int $offset = 0): array
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
                LIMIT :row_limit
                OFFSET :row_offset";

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
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = ''): int
    {
        $pdo = Database::connection();
        $query = trim($search);

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT COUNT(*)
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  AND (
                    :query = ''
                    OR CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''), COALESCE({$companySql}, '')) LIKE :query_like_1
                    OR COALESCE({$phoneSql}, '') LIKE :query_like_2
                    OR COALESCE({$citySql}, '') LIKE :query_like_3
                  )";

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function searchOptions(int $businessId, string $query, int $limit = 8): array
    {
        $pdo = Database::connection();
        $needle = trim($query);
        if ($needle === '') {
            return [];
        }

        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $citySql = self::hasColumn('clients', 'city') ? 'c.city' : 'NULL';
        $addressLine1Sql = self::hasColumn('clients', 'address_line1') ? 'c.address_line1' : 'NULL';
        $addressLine2Sql = self::hasColumn('clients', 'address_line2') ? 'c.address_line2' : 'NULL';
        $stateSql = self::hasColumn('clients', 'state') ? 'c.state' : 'NULL';
        $postalCodeSql = self::hasColumn('clients', 'postal_code') ? 'c.postal_code' : 'NULL';
        $statusSql = self::hasColumn('clients', 'status') ? 'c.status' : "'active'";
        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = "SELECT
                    c.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), NULLIF({$companySql}, ''), CONCAT('Client #', c.id)) AS name,
                    COALESCE({$phoneSql}, '') AS phone,
                    COALESCE({$companySql}, '') AS company_name,
                    COALESCE({$citySql}, '') AS city,
                    COALESCE({$addressLine1Sql}, '') AS address_line1,
                    COALESCE({$addressLine2Sql}, '') AS address_line2,
                    COALESCE({$stateSql}, '') AS state,
                    COALESCE({$postalCodeSql}, '') AS postal_code,
                    COALESCE({$statusSql}, 'active') AS status
                FROM clients c
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  AND (
                    CONCAT_WS(' ', COALESCE(c.first_name, ''), COALESCE(c.last_name, ''), COALESCE({$companySql}, '')) LIKE :query_like_1
                    OR COALESCE({$phoneSql}, '') LIKE :query_like_2
                    OR COALESCE({$citySql}, '') LIKE :query_like_3
                    OR CAST(c.id AS CHAR) LIKE :query_like_4
                  )
                ORDER BY
                    COALESCE(NULLIF(c.last_name, ''), {$companySql}, c.first_name, '') ASC,
                    COALESCE(NULLIF(c.first_name, ''), '') ASC,
                    c.id DESC
                LIMIT :row_limit";

        $stmt = $pdo->prepare($sql);
        $queryLike = '%' . $needle . '%';
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 50)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function findForBusiness(int $businessId, int $clientId): ?array
    {
        $companySql = self::hasColumn('clients', 'company_name') ? 'c.company_name' : 'NULL';
        $phoneSql = self::hasColumn('clients', 'phone') ? 'c.phone' : 'NULL';
        $emailSql = self::hasColumn('clients', 'email') ? 'c.email' : 'NULL';
        $secondaryPhoneColumn = self::secondaryPhoneColumn();
        $secondaryPhoneSql = $secondaryPhoneColumn !== null ? 'c.' . $secondaryPhoneColumn : 'NULL';
        $canTextColumn = self::primaryCanTextColumn();
        $canTextSql = $canTextColumn !== null ? 'c.' . $canTextColumn : 'NULL';
        $secondaryCanTextColumn = self::secondaryCanTextColumn();
        $secondaryCanTextSql = $secondaryCanTextColumn !== null ? 'c.' . $secondaryCanTextColumn : 'NULL';
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

    public static function displayName(array $client): string
    {
        $name = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        $company = trim((string) ($client['company_name'] ?? ''));
        if ($company !== '') {
            return $company;
        }

        return 'Client #' . (string) ((int) ($client['id'] ?? 0));
    }

    public static function findPotentialDuplicate(int $businessId, array $candidate): ?array
    {
        if (!self::hasTable('clients')) {
            return null;
        }

        $firstName = self::normalizeIdentity((string) ($candidate['first_name'] ?? ''));
        $lastName = self::normalizeIdentity((string) ($candidate['last_name'] ?? ''));
        $companyName = self::normalizeIdentity((string) ($candidate['company_name'] ?? ''));
        $phoneDigits = self::normalizePhone((string) ($candidate['phone'] ?? ''));

        $or = [];
        $params = [];

        if ($firstName !== '' && $lastName !== '') {
            $or[] = '(LOWER(TRIM(COALESCE(c.first_name, \'\'))) = :dup_first_name AND LOWER(TRIM(COALESCE(c.last_name, \'\'))) = :dup_last_name)';
            $params['dup_first_name'] = $firstName;
            $params['dup_last_name'] = $lastName;
        }

        if ($companyName !== '' && self::hasColumn('clients', 'company_name')) {
            $or[] = "LOWER(TRIM(COALESCE(c.company_name, ''))) = :dup_company_name";
            $params['dup_company_name'] = $companyName;
        }

        if ($phoneDigits !== '' && self::hasColumn('clients', 'phone')) {
            $phoneExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.phone, ''), '-', ''), '(', ''), ')', ''), ' ', ''), '.', ''), '+', '')";
            $or[] = $phoneExpr . ' = :dup_phone';
            $params['dup_phone'] = $phoneDigits;
        }

        if ($or === []) {
            return null;
        }

        $businessWhere = self::hasColumn('clients', 'business_id') ? 'c.business_id = :business_id' : '1 = 1';
        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? 'c.deleted_at IS NULL' : '1 = 1';

        $sql = 'SELECT c.id
                FROM clients c
                WHERE ' . $businessWhere . '
                  AND ' . $deletedWhere . '
                  AND (' . implode(' OR ', $or) . ')
                ORDER BY c.id DESC
                LIMIT 25';

        $stmt = Database::connection()->prepare($sql);
        if (self::hasColumn('clients', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $existing = self::findForBusiness($businessId, $id);
            if ($existing === null) {
                continue;
            }

            $existingFirst = self::normalizeIdentity((string) ($existing['first_name'] ?? ''));
            $existingLast = self::normalizeIdentity((string) ($existing['last_name'] ?? ''));
            $existingCompany = self::normalizeIdentity((string) ($existing['company_name'] ?? ''));
            $existingPhone = self::normalizePhone((string) ($existing['phone'] ?? ''));

            $nameMatch = ($firstName !== '' && $lastName !== '' && $existingFirst === $firstName && $existingLast === $lastName);
            $companyMatch = ($companyName !== '' && $existingCompany !== '' && $existingCompany === $companyName);
            $phoneMatch = ($phoneDigits !== '' && $existingPhone !== '' && $existingPhone === $phoneDigits);

            if (($nameMatch || $companyMatch) && ($phoneDigits === '' || $phoneMatch)) {
                return $existing;
            }

            if (!$nameMatch && !$companyMatch && $phoneMatch) {
                return $existing;
            }
        }

        return null;
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
            $hasExpenseBusinessId = self::hasColumn('expenses', 'business_id');
            $businessCondition = $hasExpenseBusinessId ? 'e.business_id = :expenses_business_id' : '1=1';

            if (self::hasColumn('expenses', 'client_id')) {
                $sql = 'SELECT COALESCE(SUM(e.amount), 0)
                        FROM expenses e
                        WHERE ' . $businessCondition . '
                          AND e.client_id = :client_id
                          AND ' . $deletedCondition;

                $stmt = Database::connection()->prepare($sql);
                $params = ['client_id' => $clientId];
                if ($hasExpenseBusinessId) {
                    $params['expenses_business_id'] = $businessId;
                }
                $stmt->execute($params);
                $expenses = (float) $stmt->fetchColumn();
            } elseif (self::hasColumn('expenses', 'job_id') && self::hasTable('jobs')) {
                $hasJobBusinessId = self::hasColumn('jobs', 'business_id');
                $jobBusinessCondition = $hasJobBusinessId ? 'j.business_id = :jobs_business_id' : '1=1';
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
                if ($hasExpenseBusinessId) {
                    $params['expenses_business_id'] = $businessId;
                }
                if ($hasJobBusinessId) {
                    $params['jobs_business_id'] = $businessId;
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

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        $columns = ['business_id', 'first_name', 'last_name'];
        $values = [':business_id', ':first_name', ':last_name'];
        $params = [
            'business_id' => $businessId,
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
        ];

        $optional = [
            'company_name',
            'email',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'client_type',
            'status',
            'primary_note',
            'notes',
        ];

        foreach ($optional as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            if (!self::hasColumn('clients', $column)) {
                continue;
            }

            $columns[] = $column;
            $values[] = ':' . $column;

            $value = $data[$column] ?? null;
            if (in_array($column, ['can_text', 'secondary_can_text'], true)) {
                $params[$column] = ((int) $value) === 1 ? 1 : 0;
            } else {
                $params[$column] = is_string($value) ? trim($value) : $value;
            }
        }

        if (array_key_exists('secondary_phone', $data)) {
            $secondaryPhoneColumn = self::secondaryPhoneColumn();
            if ($secondaryPhoneColumn !== null) {
                $columns[] = $secondaryPhoneColumn;
                $values[] = ':secondary_phone_value';
                $params['secondary_phone_value'] = trim((string) ($data['secondary_phone'] ?? ''));
            }
        }

        if (array_key_exists('can_text', $data)) {
            $canTextColumn = self::primaryCanTextColumn();
            if ($canTextColumn !== null) {
                $columns[] = $canTextColumn;
                $values[] = ':can_text_value';
                $params['can_text_value'] = ((int) ($data['can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('secondary_can_text', $data)) {
            $secondaryCanTextColumn = self::secondaryCanTextColumn();
            if ($secondaryCanTextColumn !== null) {
                $columns[] = $secondaryCanTextColumn;
                $values[] = ':secondary_can_text_value';
                $params['secondary_can_text_value'] = ((int) ($data['secondary_can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (self::hasColumn('clients', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }
        if (self::hasColumn('clients', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        $sql = 'INSERT INTO clients (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $clientId, array $data, int $actorUserId): bool
    {
        $sets = [
            'first_name = :first_name',
            'last_name = :last_name',
        ];
        $params = [
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'business_id' => $businessId,
            'client_id' => $clientId,
        ];

        $optional = [
            'company_name',
            'email',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'client_type',
            'status',
            'primary_note',
            'notes',
        ];

        foreach ($optional as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            if (!self::hasColumn('clients', $column)) {
                continue;
            }

            $sets[] = $column . ' = :' . $column;
            $value = $data[$column] ?? null;
            if (in_array($column, ['can_text', 'secondary_can_text'], true)) {
                $params[$column] = ((int) $value) === 1 ? 1 : 0;
            } else {
                $params[$column] = is_string($value) ? trim($value) : $value;
            }
        }

        if (array_key_exists('secondary_phone', $data)) {
            $secondaryPhoneColumn = self::secondaryPhoneColumn();
            if ($secondaryPhoneColumn !== null) {
                $sets[] = $secondaryPhoneColumn . ' = :secondary_phone_value';
                $params['secondary_phone_value'] = trim((string) ($data['secondary_phone'] ?? ''));
            }
        }

        if (array_key_exists('can_text', $data)) {
            $canTextColumn = self::primaryCanTextColumn();
            if ($canTextColumn !== null) {
                $sets[] = $canTextColumn . ' = :can_text_value';
                $params['can_text_value'] = ((int) ($data['can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('secondary_can_text', $data)) {
            $secondaryCanTextColumn = self::secondaryCanTextColumn();
            if ($secondaryCanTextColumn !== null) {
                $sets[] = $secondaryCanTextColumn . ' = :secondary_can_text_value';
                $params['secondary_can_text_value'] = ((int) ($data['secondary_can_text'] ?? 0)) === 1 ? 1 : 0;
            }
        }

        if (self::hasColumn('clients', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId;
        }
        if (self::hasColumn('clients', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $deletedWhere = self::hasColumn('clients', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $sql = 'UPDATE clients
                SET ' . implode(', ', $sets) . '
                WHERE id = :client_id
                  AND business_id = :business_id' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($params);
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

    private static function secondaryPhoneColumn(): ?string
    {
        foreach (['secondary_phone', 'phone_secondary', 'alt_phone'] as $column) {
            if (self::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function primaryCanTextColumn(): ?string
    {
        foreach (['can_text', 'can_text_primary', 'phone_can_text'] as $column) {
            if (self::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function secondaryCanTextColumn(): ?string
    {
        foreach (['secondary_can_text', 'can_text_secondary', 'secondary_phone_can_text'] as $column) {
            if (self::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function normalizeIdentity(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    private static function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
