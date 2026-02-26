<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Client
{
    public static function search(string $term = '', string $status = 'active'): array
    {
        self::ensureCompanyLinkTable();

        $businessScope = self::businessScopeClause('clients', 'cl');

        $sql = 'SELECT cl.id,
                       cl.first_name,
                       cl.last_name,
                       cl.business_name,
                       cl.phone,
                       cl.email,
                       cl.city,
                       cl.state,
                       cl.active,
                       cl.deleted_at,
                       cl.updated_at,
                       GROUP_CONCAT(DISTINCT co.name ORDER BY co.name SEPARATOR ", ") AS company_names
                FROM clients cl
                LEFT JOIN companies_x_clients cxc
                    ON cxc.client_id = cl.id
                   AND cxc.deleted_at IS NULL
                   AND COALESCE(cxc.active, 1) = 1
                LEFT JOIN companies co
                    ON co.id = cxc.company_id
                   AND co.deleted_at IS NULL
                   AND COALESCE(co.active, 1) = 1';

        $where = [];
        $params = [];

        $businessScope = self::businessScopeClause('clients', 'cl');
        if ($businessScope !== null) {
            $where[] = $businessScope;
            $params['business_id_scope'] = self::currentBusinessId();
        }

        if ($status === 'active') {
            $where[] = '(cl.deleted_at IS NULL AND cl.active = 1)';
        } elseif ($status === 'inactive') {
            $where[] = '(cl.deleted_at IS NOT NULL OR cl.active = 0)';
        }

        $term = trim($term);
        if ($term !== '') {
            $where[] = '(cl.first_name LIKE :term
                        OR cl.last_name LIKE :term
                        OR cl.business_name LIKE :term
                        OR cl.phone LIKE :term
                        OR cl.email LIKE :term
                        OR co.name LIKE :term
                        OR cl.city LIKE :term
                        OR cl.state LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY cl.id
                  ORDER BY cl.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        self::ensureCompanyLinkTable();
        $businessScope = self::businessScopeClause('clients', 'cl');
        $hasCreatedBy = Schema::hasColumn('clients', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('clients', 'updated_by');
        $hasDeletedBy = Schema::hasColumn('clients', 'deleted_by');

        $createdBySelect = $hasCreatedBy ? 'cl.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 'cl.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 'cl.deleted_by' : 'NULL';

        $createdByNameSelect = $hasCreatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', cl.created_by))'
            : 'NULL';
        $updatedByNameSelect = $hasUpdatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', cl.updated_by))'
            : 'NULL';
        $deletedByNameSelect = $hasDeletedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', cl.deleted_by))'
            : 'NULL';

        $auditJoins = '';
        if ($hasCreatedBy) {
            $auditJoins .= ' LEFT JOIN users u_created ON u_created.id = cl.created_by';
        }
        if ($hasUpdatedBy) {
            $auditJoins .= ' LEFT JOIN users u_updated ON u_updated.id = cl.updated_by';
        }
        if ($hasDeletedBy) {
            $auditJoins .= ' LEFT JOIN users u_deleted ON u_deleted.id = cl.deleted_by';
        }

        $sql = 'SELECT cl.id,
                       cl.first_name,
                       cl.last_name,
                       cl.business_name,
                       cl.phone,
                       cl.can_text,
                       cl.email,
                       cl.address_1,
                       cl.address_2,
                       cl.city,
                       cl.state,
                       cl.zip,
                       cl.client_type,
                       cl.note,
                       cl.active,
                       cl.deleted_at,
                       ' . $deletedBySelect . ' AS deleted_by,
                       ' . $deletedByNameSelect . ' AS deleted_by_name,
                       cl.created_at,
                       ' . $createdBySelect . ' AS created_by,
                       ' . $createdByNameSelect . ' AS created_by_name,
                       cl.updated_at,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $updatedByNameSelect . ' AS updated_by_name,
                       GROUP_CONCAT(DISTINCT co.name ORDER BY co.name SEPARATOR ", ") AS company_names
                FROM clients cl
                LEFT JOIN companies_x_clients cxc
                    ON cxc.client_id = cl.id
                   AND cxc.deleted_at IS NULL
                   AND COALESCE(cxc.active, 1) = 1
                LEFT JOIN companies co
                    ON co.id = cxc.company_id
                   AND co.deleted_at IS NULL
                   AND COALESCE(co.active, 1) = 1'
                . $auditJoins . '
                WHERE cl.id = :id'
                . ($businessScope !== null ? ' AND ' . $businessScope : '') . '
                GROUP BY cl.id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if ($businessScope !== null) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        $client = $stmt->fetch();
        return $client ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $columns = [
            'first_name',
            'last_name',
            'business_name',
            'phone',
            'can_text',
            'email',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip',
            'client_type',
            'note',
            'active',
            'created_at',
            'updated_at',
        ];

        $values = [
            ':first_name',
            ':last_name',
            ':business_name',
            ':phone',
            ':can_text',
            ':email',
            ':address_1',
            ':address_2',
            ':city',
            ':state',
            ':zip',
            ':client_type',
            ':note',
            ':active',
            'NOW()',
            'NOW()',
        ];

        $params = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'business_name' => $data['business_name'],
            'phone' => $data['phone'],
            'can_text' => $data['can_text'],
            'email' => $data['email'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'client_type' => $data['client_type'],
            'note' => $data['note'],
            'active' => $data['active'],
        ];

        if ($actorId !== null && Schema::hasColumn('clients', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('clients', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }
        if (Schema::hasColumn('clients', 'business_id')) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql = 'INSERT INTO clients (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        $sets = [
            'first_name = :first_name',
            'last_name = :last_name',
            'business_name = :business_name',
            'phone = :phone',
            'can_text = :can_text',
            'email = :email',
            'address_1 = :address_1',
            'address_2 = :address_2',
            'city = :city',
            'state = :state',
            'zip = :zip',
            'client_type = :client_type',
            'note = :note',
            'active = :active',
            'updated_at = NOW()',
        ];

        $params = [
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'business_name' => $data['business_name'],
            'phone' => $data['phone'],
            'can_text' => $data['can_text'],
            'email' => $data['email'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'client_type' => $data['client_type'],
            'note' => $data['note'],
            'active' => $data['active'],
        ];

        if ($actorId !== null && Schema::hasColumn('clients', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id';
        if (Schema::hasColumn('clients', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function deactivate(int $id, ?int $actorId = null): void
    {
        $sets = [
            'active = 0',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if (Schema::hasColumn('clients', 'deleted_at')) {
            $sets[] = 'deleted_at = COALESCE(deleted_at, NOW())';
        }
        if ($actorId !== null && Schema::hasColumn('clients', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('clients', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id';
        if (Schema::hasColumn('clients', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function linkedCompanies(int $clientId): array
    {
        self::ensureCompanyLinkTable();

        $sql = 'SELECT co.id,
                       co.name,
                       co.phone,
                       co.web_address,
                       co.city,
                       co.state
                FROM companies_x_clients cxc
                INNER JOIN companies co
                    ON co.id = cxc.company_id
                INNER JOIN clients cl
                    ON cl.id = cxc.client_id
                WHERE cxc.client_id = :client_id
                  AND cxc.deleted_at IS NULL
                  AND COALESCE(cxc.active, 1) = 1
                  AND co.deleted_at IS NULL
                  AND COALESCE(co.active, 1) = 1';

        $params = ['client_id' => $clientId];
        if (Schema::hasColumn('clients', 'business_id')) {
            $sql .= ' AND cl.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        if (Schema::hasColumn('companies', 'business_id')) {
            $sql .= ' AND co.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $sql .= '
                ORDER BY co.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function primaryCompany(int $clientId): ?array
    {
        self::ensureCompanyLinkTable();

        $sql = 'SELECT co.id, co.name
                FROM companies_x_clients cxc
                INNER JOIN companies co ON co.id = cxc.company_id
                INNER JOIN clients cl ON cl.id = cxc.client_id
                WHERE cxc.client_id = :client_id
                  AND cxc.deleted_at IS NULL
                  AND COALESCE(cxc.active, 1) = 1
                  AND co.deleted_at IS NULL
                  AND COALESCE(co.active, 1) = 1';

        $params = ['client_id' => $clientId];
        if (Schema::hasColumn('clients', 'business_id')) {
            $sql .= ' AND cl.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        if (Schema::hasColumn('companies', 'business_id')) {
            $sql .= ' AND co.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $sql .= '
                ORDER BY cxc.id DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $company = $stmt->fetch();

        return $company ?: null;
    }

    public static function syncCompanyLink(int $clientId, ?int $companyId, ?int $actorId = null): void
    {
        self::ensureCompanyLinkTable();

        if (self::findById($clientId) === null) {
            return;
        }
        if ($companyId !== null && $companyId > 0 && Company::findById($companyId) === null) {
            return;
        }

        $deactivateSets = [
            'active = 0',
            'deleted_at = NOW()',
            'updated_at = NOW()',
        ];
        $deactivateParams = ['client_id' => $clientId];
        if ($actorId !== null && Schema::hasColumn('companies_x_clients', 'updated_by')) {
            $deactivateSets[] = 'updated_by = :updated_by';
            $deactivateParams['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('companies_x_clients', 'deleted_by')) {
            $deactivateSets[] = 'deleted_by = :deleted_by';
            $deactivateParams['deleted_by'] = $actorId;
        }

        $deactivateSql = 'UPDATE companies_x_clients
                          SET ' . implode(', ', $deactivateSets) . '
                          WHERE client_id = :client_id
                            AND deleted_at IS NULL
                            AND COALESCE(active, 1) = 1';

        $deactivateStmt = Database::connection()->prepare($deactivateSql);
        $deactivateStmt->execute($deactivateParams);

        if ($companyId === null || $companyId <= 0) {
            return;
        }

        $existingSql = 'SELECT id
                        FROM companies_x_clients
                        WHERE client_id = :client_id
                          AND company_id = :company_id
                        ORDER BY id DESC
                        LIMIT 1';
        $existingStmt = Database::connection()->prepare($existingSql);
        $existingStmt->execute([
            'client_id' => $clientId,
            'company_id' => $companyId,
        ]);
        $existing = $existingStmt->fetch();

        if ($existing && isset($existing['id'])) {
            $reactivateSets = [
                'active = 1',
                'deleted_at = NULL',
                'updated_at = NOW()',
            ];
            $reactivateParams = ['id' => (int) $existing['id']];
            if ($actorId !== null && Schema::hasColumn('companies_x_clients', 'updated_by')) {
                $reactivateSets[] = 'updated_by = :updated_by';
                $reactivateParams['updated_by'] = $actorId;
            }
            if (Schema::hasColumn('companies_x_clients', 'deleted_by')) {
                $reactivateSets[] = 'deleted_by = NULL';
            }

            $reactivateSql = 'UPDATE companies_x_clients
                              SET ' . implode(', ', $reactivateSets) . '
                              WHERE id = :id';
            $reactivateStmt = Database::connection()->prepare($reactivateSql);
            $reactivateStmt->execute($reactivateParams);
            return;
        }

        $insertColumns = ['company_id', 'client_id', 'created_at', 'updated_at', 'active'];
        $insertValues = [':company_id', ':client_id', 'NOW()', 'NOW()', '1'];
        $insertParams = [
            'company_id' => $companyId,
            'client_id' => $clientId,
        ];
        if ($actorId !== null && Schema::hasColumn('companies_x_clients', 'created_by')) {
            $insertColumns[] = 'created_by';
            $insertValues[] = ':created_by';
            $insertParams['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('companies_x_clients', 'updated_by')) {
            $insertColumns[] = 'updated_by';
            $insertValues[] = ':updated_by';
            $insertParams['updated_by'] = $actorId;
        }

        $insertSql = 'INSERT INTO companies_x_clients (' . implode(', ', $insertColumns) . ')
                      VALUES (' . implode(', ', $insertValues) . ')';

        $insertStmt = Database::connection()->prepare($insertSql);
        $insertStmt->execute($insertParams);
    }

    public static function lookupByName(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT cl.id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(" ", cl.first_name, cl.last_name)), ""), CONCAT("Client #", cl.id)) AS label,
                       cl.city,
                       cl.state
                FROM clients cl
                WHERE cl.deleted_at IS NULL
                  AND cl.active = 1
                  ' . (Schema::hasColumn('clients', 'business_id') ? 'AND cl.business_id = :business_id_scope' : '') . '
                  AND (
                        cl.first_name LIKE :term
                        OR cl.last_name LIKE :term
                        OR cl.email LIKE :term
                        OR CONCAT_WS(" ", cl.first_name, cl.last_name) LIKE :term
                  )
                ORDER BY label ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $params = ['term' => '%' . $term . '%'];
        if (Schema::hasColumn('clients', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findPotentialDuplicates(array $data, ?int $excludeId = null, int $limit = 8): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phoneDigits = self::normalizePhone((string) ($data['phone'] ?? ''));
        $firstName = strtolower(trim((string) ($data['first_name'] ?? '')));
        $lastName = strtolower(trim((string) ($data['last_name'] ?? '')));
        $zip = self::normalizeZip((string) ($data['zip'] ?? ''));

        $hasEmail = $email !== '';
        $hasPhone = strlen($phoneDigits) >= 7;
        $hasName = $firstName !== '' && $lastName !== '';

        if (!$hasEmail && !$hasPhone && !$hasName) {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $phoneExpression = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cl.phone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', ''), '/', '')";

        $where = [];
        $matchClauses = [];
        $params = [];

        if ($excludeId !== null && $excludeId > 0) {
            $where[] = 'cl.id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        if (Schema::hasColumn('clients', 'business_id')) {
            $where[] = 'cl.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        if ($hasEmail) {
            $matchClauses[] = 'LOWER(TRIM(COALESCE(cl.email, ""))) = :email_exact';
            $params['email_exact'] = $email;
        }
        if ($hasPhone) {
            $matchClauses[] = 'RIGHT(' . $phoneExpression . ', ' . strlen($phoneDigits) . ') = :phone_exact';
            $params['phone_exact'] = $phoneDigits;
        }
        if ($hasName) {
            $matchClauses[] = '(LOWER(TRIM(COALESCE(cl.first_name, ""))) = :first_name_exact
                                AND LOWER(TRIM(COALESCE(cl.last_name, ""))) = :last_name_exact)';
            $params['first_name_exact'] = $firstName;
            $params['last_name_exact'] = $lastName;
        }

        if (empty($matchClauses)) {
            return [];
        }

        $where[] = '(' . implode(' OR ', $matchClauses) . ')';

        $sql = 'SELECT cl.id,
                       cl.first_name,
                       cl.last_name,
                       cl.phone,
                       cl.email,
                       cl.city,
                       cl.state,
                       cl.zip,
                       cl.client_type,
                       cl.active,
                       cl.deleted_at,
                       cl.updated_at
                FROM clients cl
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY cl.updated_at DESC, cl.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return [];
        }

        $matches = [];
        foreach ($rows as $row) {
            $rowEmail = strtolower(trim((string) ($row['email'] ?? '')));
            $rowPhone = self::normalizePhone((string) ($row['phone'] ?? ''));
            $rowFirst = strtolower(trim((string) ($row['first_name'] ?? '')));
            $rowLast = strtolower(trim((string) ($row['last_name'] ?? '')));
            $rowZip = self::normalizeZip((string) ($row['zip'] ?? ''));

            $emailMatch = $hasEmail && $rowEmail !== '' && $rowEmail === $email;
            $phoneMatch = $hasPhone && $rowPhone !== '' && str_ends_with($rowPhone, $phoneDigits);
            $nameMatch = $hasName && $rowFirst === $firstName && $rowLast === $lastName;
            $zipMatch = $zip !== '' && $rowZip !== '' && $rowZip === $zip;

            $score = 0;
            $reasons = [];

            if ($emailMatch) {
                $score += 60;
                $reasons[] = 'Email match';
            }
            if ($phoneMatch) {
                $score += 45;
                $reasons[] = 'Phone match';
            }
            if ($nameMatch) {
                $score += 30;
                $reasons[] = 'Name match';
            }
            if ($zipMatch) {
                $score += 10;
                $reasons[] = 'ZIP match';
            }
            if ($nameMatch && $zipMatch) {
                $score += 15;
            }

            if ($score <= 0) {
                continue;
            }

            $displayName = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
            if ($displayName === '') {
                $displayName = 'Client #' . (int) ($row['id'] ?? 0);
            }

            $status = 'active';
            if (!empty($row['deleted_at'])) {
                $status = 'deleted';
            } elseif (empty($row['active'])) {
                $status = 'inactive';
            }

            $matches[] = [
                'id' => (int) ($row['id'] ?? 0),
                'display_name' => $displayName,
                'phone' => (string) ($row['phone'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'state' => (string) ($row['state'] ?? ''),
                'zip' => (string) ($row['zip'] ?? ''),
                'client_type' => (string) ($row['client_type'] ?? ''),
                'status' => $status,
                'match_score' => $score,
                'match_reasons' => $reasons,
            ];
        }

        usort($matches, static function (array $a, array $b): int {
            $scoreCompare = (int) ($b['match_score'] ?? 0) <=> (int) ($a['match_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
        });

        return array_slice($matches, 0, $limit);
    }

    public static function workSummary(int $clientId): array
    {
        if ($clientId <= 0) {
            return self::emptyWorkSummary();
        }

        [$jobFilterSql, $jobFilterParams] = self::buildClientJobFilter('j', $clientId);
        $jobWhere = self::buildActiveRecordWhere('jobs', 'j');
        $jobWhere[] = $jobFilterSql;

        $statusCompleteExpr = Schema::hasColumn('jobs', 'job_status')
            ? "LOWER(TRIM(COALESCE(j.job_status, ''))) = 'complete'"
            : '0 = 1';

        $jobSummarySql = 'SELECT
                            COUNT(*) AS total_jobs,
                            COALESCE(SUM(CASE WHEN ' . $statusCompleteExpr . ' THEN 1 ELSE 0 END), 0) AS completed_jobs,
                            COALESCE(SUM(' . self::jobGrossExpression('j') . '), 0) AS jobs_gross_total
                          FROM jobs j
                          WHERE ' . implode(' AND ', $jobWhere);

        $jobStmt = Database::connection()->prepare($jobSummarySql);
        $jobStmt->execute($jobFilterParams);
        $jobSummary = $jobStmt->fetch() ?: [];

        $expenseTotal = 0.0;
        if (Schema::tableExists('expenses')) {
            $expenseWhere = self::buildActiveRecordWhere('expenses', 'e');
            $expenseWhere[] = $jobFilterSql;
            $expenseSql = 'SELECT COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS expense_total
                           FROM expenses e
                           INNER JOIN jobs j ON j.id = e.job_id
                           WHERE ' . implode(' AND ', $expenseWhere);
            $expenseStmt = Database::connection()->prepare($expenseSql);
            $expenseStmt->execute($jobFilterParams);
            $expenseTotal = (float) ($expenseStmt->fetchColumn() ?? 0);
        }

        $salesGross = 0.0;
        $salesCount = 0;
        if (Schema::tableExists('sales')) {
            $salesWhere = self::buildActiveRecordWhere('sales', 's');
            $salesWhere[] = $jobFilterSql;
            $salesSql = 'SELECT
                            COALESCE(SUM(COALESCE(s.gross_amount, 0)), 0) AS gross_total,
                            COUNT(*) AS sale_count
                         FROM sales s
                         INNER JOIN jobs j ON j.id = s.job_id
                         WHERE ' . implode(' AND ', $salesWhere);
            $salesStmt = Database::connection()->prepare($salesSql);
            $salesStmt->execute($jobFilterParams);
            $salesRow = $salesStmt->fetch() ?: [];
            $salesGross = (float) ($salesRow['gross_total'] ?? 0);
            $salesCount = (int) ($salesRow['sale_count'] ?? 0);
        }

        $jobsGross = (float) ($jobSummary['jobs_gross_total'] ?? 0);

        return [
            'total_jobs' => (int) ($jobSummary['total_jobs'] ?? 0),
            'completed_jobs' => (int) ($jobSummary['completed_jobs'] ?? 0),
            'jobs_gross_total' => $jobsGross,
            'expenses_total' => $expenseTotal,
            'sales_gross_total' => $salesGross,
            'sales_count' => $salesCount,
            'combined_gross_total' => $jobsGross + $salesGross,
        ];
    }

    public static function completedJobs(int $clientId, int $limit = 25): array
    {
        if ($clientId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 100));
        [$jobFilterSql, $jobFilterParams] = self::buildClientJobFilter('j', $clientId);
        $where = self::buildActiveRecordWhere('jobs', 'j');
        $where[] = $jobFilterSql;

        if (Schema::hasColumn('jobs', 'job_status')) {
            $where[] = "LOWER(TRIM(COALESCE(j.job_status, ''))) = 'complete'";
        }

        $sql = 'SELECT
                    j.id,
                    j.name,
                    j.city,
                    j.state,
                    j.end_date,
                    j.paid_date,
                    j.updated_at,
                    ' . self::jobGrossExpression('j') . ' AS gross_total
                FROM jobs j
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY COALESCE(j.end_date, j.paid_date, j.updated_at, j.created_at) DESC, j.id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        foreach ($jobFilterParams as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return '';
        }

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    private static function normalizeZip(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($value));
        return $normalized ?? '';
    }

    private static function buildClientJobFilter(string $jobAlias, int $clientId): array
    {
        $conditions = [sprintf('%s.client_id = :client_id', $jobAlias)];
        $params = ['client_id' => $clientId];
        if (Schema::hasColumn('jobs', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        if (Schema::hasColumn('jobs', 'contact_client_id')) {
            $conditions[] = sprintf('%s.contact_client_id = :client_id', $jobAlias);
        }
        if (Schema::hasColumn('jobs', 'job_owner_type') && Schema::hasColumn('jobs', 'job_owner_id')) {
            $conditions[] = sprintf(
                "(LOWER(TRIM(COALESCE(%s.job_owner_type, ''))) = 'client' AND %s.job_owner_id = :client_id)",
                $jobAlias,
                $jobAlias
            );
        }

        return ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    private static function buildActiveRecordWhere(string $table, string $alias): array
    {
        $where = [];
        if (Schema::hasColumn($table, 'deleted_at')) {
            $where[] = sprintf('%s.deleted_at IS NULL', $alias);
        }
        if (Schema::hasColumn($table, 'active')) {
            $where[] = sprintf('COALESCE(%s.active, 1) = 1', $alias);
        }
        if (Schema::hasColumn($table, 'business_id')) {
            $where[] = sprintf('%s.business_id = :business_id_scope', $alias);
        }

        return $where;
    }

    private static function jobGrossExpression(string $jobAlias): string
    {
        $hasTotalBilled = Schema::hasColumn('jobs', 'total_billed');
        $hasTotalQuote = Schema::hasColumn('jobs', 'total_quote');
        if ($hasTotalBilled && $hasTotalQuote) {
            return sprintf('COALESCE(%s.total_billed, %s.total_quote, 0)', $jobAlias, $jobAlias);
        }
        if ($hasTotalBilled) {
            return sprintf('COALESCE(%s.total_billed, 0)', $jobAlias);
        }
        if ($hasTotalQuote) {
            return sprintf('COALESCE(%s.total_quote, 0)', $jobAlias);
        }

        return '0';
    }

    private static function emptyWorkSummary(): array
    {
        return [
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'jobs_gross_total' => 0.0,
            'expenses_total' => 0.0,
            'sales_gross_total' => 0.0,
            'sales_count' => 0,
            'combined_gross_total' => 0.0,
        ];
    }

    private static function businessScopeClause(string $table, string $alias): ?string
    {
        if (!Schema::hasColumn($table, 'business_id')) {
            return null;
        }

        return $alias . '.business_id = :business_id_scope';
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }

    private static function ensureCompanyLinkTable(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS companies_x_clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                updated_by BIGINT UNSIGNED DEFAULT NULL,
                deleted_by BIGINT UNSIGNED DEFAULT NULL,
                active TINYINT(1) DEFAULT 1,
                PRIMARY KEY (id),
                KEY idx_company_id (company_id),
                KEY idx_client_id (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ensured = true;
    }
}
