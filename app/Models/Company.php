<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Company
{
    public static function findPotentialDuplicates(array $data, ?int $excludeId = null, int $limit = 8): array
    {
        $name = self::normalizeName((string) ($data['name'] ?? ''));
        $phone = self::normalizePhone((string) ($data['phone'] ?? ''));
        $domain = self::normalizeDomain((string) ($data['web_address'] ?? ''));

        if ($name === '' && $phone === '' && $domain === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $where = [];
        $params = [];

        if (Schema::hasColumn('companies', 'business_id')) {
            $where[] = 'c.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        } elseif (Schema::hasColumn('clients', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }

        if ($excludeId !== null && $excludeId > 0) {
            $where[] = 'c.id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $matchSql = [];
        if ($name !== '') {
            $matchSql[] = 'LOWER(TRIM(COALESCE(c.name, ""))) = :name_exact';
            $params['name_exact'] = $name;
        }
        if ($phone !== '') {
            $matchSql[] = 'RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.phone, \'\'), \'(\', \'\'), \')\', \'\'), \'-\', \'\'), \' \', \'\'), \'.\', \'\'), \'+\', \'\'), \'/\', \'\'), ' . strlen($phone) . ') = :phone_exact';
            $params['phone_exact'] = $phone;
        }
        if ($domain !== '') {
            $matchSql[] = 'LOWER(COALESCE(c.web_address, "")) LIKE :domain_like';
            $params['domain_like'] = '%' . $domain . '%';
        }

        if (empty($matchSql)) {
            return [];
        }
        $where[] = '(' . implode(' OR ', $matchSql) . ')';

        $sql = 'SELECT c.id,
                       c.name,
                       c.phone,
                       c.web_address,
                       c.city,
                       c.state,
                       c.active,
                       c.deleted_at,
                       c.updated_at
                FROM companies c
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.updated_at DESC, c.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return [];
        }

        $matches = [];
        foreach ($rows as $row) {
            $score = 0;
            $reasons = [];
            $rowName = self::normalizeName((string) ($row['name'] ?? ''));
            $rowPhone = self::normalizePhone((string) ($row['phone'] ?? ''));
            $rowDomain = self::normalizeDomain((string) ($row['web_address'] ?? ''));

            if ($name !== '' && $rowName !== '' && $rowName === $name) {
                $score += 70;
                $reasons[] = 'Name match';
            }
            if ($phone !== '' && $rowPhone !== '' && str_ends_with($rowPhone, $phone)) {
                $score += 40;
                $reasons[] = 'Phone match';
            }
            if ($domain !== '' && $rowDomain !== '' && $rowDomain === $domain) {
                $score += 30;
                $reasons[] = 'Website domain match';
            }

            if ($score <= 0) {
                continue;
            }

            $status = 'active';
            if (!empty($row['deleted_at'])) {
                $status = 'deleted';
            } elseif (empty($row['active'])) {
                $status = 'inactive';
            }

            $matches[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'web_address' => (string) ($row['web_address'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'state' => (string) ($row['state'] ?? ''),
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

    public static function findActiveByName(string $name): ?array
    {
        $sql = 'SELECT id, name
                FROM companies
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                  ' . (Schema::hasColumn('companies', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '
                  AND name = :name
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['name' => trim($name)];
        if (Schema::hasColumn('companies', 'business_id') || Schema::hasColumn('clients', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $company = $stmt->fetch();

        return $company ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $columns = [
            'name',
            'phone',
            'web_address',
            'facebook',
            'instagram',
            'linkedin',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip',
            'note',
            'active',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':name',
            ':phone',
            ':web_address',
            ':facebook',
            ':instagram',
            ':linkedin',
            ':address_1',
            ':address_2',
            ':city',
            ':state',
            ':zip',
            ':note',
            ':active',
            'NOW()',
            'NOW()',
        ];

        $params = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'web_address' => $data['web_address'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'linkedin' => $data['linkedin'] ?? null,
            'address_1' => $data['address_1'] ?? null,
            'address_2' => $data['address_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'note' => $data['note'] ?? null,
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
        ];

        if ($actorId !== null && Schema::hasColumn('companies', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('companies', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }
        if (Schema::hasColumn('companies', 'business_id')) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql = 'INSERT INTO companies (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        $sets = [
            'name = :name',
            'phone = :phone',
            'web_address = :web_address',
            'facebook = :facebook',
            'instagram = :instagram',
            'linkedin = :linkedin',
            'address_1 = :address_1',
            'address_2 = :address_2',
            'city = :city',
            'state = :state',
            'zip = :zip',
            'note = :note',
            'active = :active',
            'updated_at = NOW()',
        ];

        $params = [
            'id' => $id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'web_address' => $data['web_address'],
            'facebook' => $data['facebook'],
            'instagram' => $data['instagram'],
            'linkedin' => $data['linkedin'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'note' => $data['note'],
            'active' => $data['active'],
        ];

        if ($actorId !== null && Schema::hasColumn('companies', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE companies SET ' . implode(', ', $sets) . ' WHERE id = :id';
        if (Schema::hasColumn('companies', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        $sets = [
            'active = 0',
            'updated_at = NOW()',
            'deleted_at = COALESCE(deleted_at, NOW())',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('companies', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('companies', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE companies SET ' . implode(', ', $sets) . ' WHERE id = :id';
        if (Schema::hasColumn('companies', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function search(string $term = '', string $status = 'active'): array
    {
        $clientBusinessJoin = Schema::hasColumn('clients', 'business_id')
            ? ' AND cl.business_id = :business_id_scope'
            : '';

        $sql = 'SELECT c.id,
                       c.name,
                       c.phone,
                       c.web_address,
                       c.city,
                       c.state,
                       c.active,
                       c.deleted_at,
                       c.updated_at,
                       COUNT(DISTINCT cl.id) AS client_count
                FROM companies c
                LEFT JOIN companies_x_clients cxc
                    ON cxc.company_id = c.id
                   AND cxc.deleted_at IS NULL
                   AND COALESCE(cxc.active, 1) = 1
                LEFT JOIN clients cl
                    ON cl.id = cxc.client_id
                   ' . $clientBusinessJoin . '
                   AND cl.deleted_at IS NULL
                   AND cl.active = 1';

        $where = [];
        $params = [];

        if (Schema::hasColumn('companies', 'business_id')) {
            $where[] = 'c.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

        if ($status === 'active') {
            $where[] = '(c.deleted_at IS NULL AND COALESCE(c.active, 1) = 1)';
        } elseif ($status === 'inactive') {
            $where[] = '(c.deleted_at IS NOT NULL OR COALESCE(c.active, 1) = 0)';
        }

        $term = trim($term);
        if ($term !== '') {
            $where[] = '(c.name LIKE :term
                        OR c.phone LIKE :term
                        OR c.web_address LIKE :term
                        OR c.city LIKE :term
                        OR c.state LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY c.id
                  ORDER BY c.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $clientBusinessJoin = Schema::hasColumn('clients', 'business_id')
            ? ' AND cl.business_id = :business_id_scope'
            : '';

        $hasCreatedBy = Schema::hasColumn('companies', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('companies', 'updated_by');
        $hasDeletedBy = Schema::hasColumn('companies', 'deleted_by');

        $createdBySelect = $hasCreatedBy ? 'c.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 'c.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 'c.deleted_by' : 'NULL';

        $createdByNameSelect = $hasCreatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', c.created_by))'
            : 'NULL';
        $updatedByNameSelect = $hasUpdatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', c.updated_by))'
            : 'NULL';
        $deletedByNameSelect = $hasDeletedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', c.deleted_by))'
            : 'NULL';

        $auditJoins = '';
        if ($hasCreatedBy) {
            $auditJoins .= ' LEFT JOIN users u_created ON u_created.id = c.created_by';
        }
        if ($hasUpdatedBy) {
            $auditJoins .= ' LEFT JOIN users u_updated ON u_updated.id = c.updated_by';
        }
        if ($hasDeletedBy) {
            $auditJoins .= ' LEFT JOIN users u_deleted ON u_deleted.id = c.deleted_by';
        }

        $sql = 'SELECT c.id,
                       c.name,
                       c.note,
                       c.phone,
                       c.web_address,
                       c.facebook,
                       c.instagram,
                       c.linkedin,
                       c.address_1,
                       c.address_2,
                       c.city,
                       c.state,
                       c.zip,
                       c.active,
                       c.deleted_at,
                       c.created_at,
                       ' . $createdBySelect . ' AS created_by,
                       ' . $createdByNameSelect . ' AS created_by_name,
                       c.updated_at,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $updatedByNameSelect . ' AS updated_by_name,
                       ' . $deletedBySelect . ' AS deleted_by,
                       ' . $deletedByNameSelect . ' AS deleted_by_name,
                       COUNT(DISTINCT cl.id) AS client_count
                FROM companies c
                LEFT JOIN companies_x_clients cxc
                    ON cxc.company_id = c.id
                   AND cxc.deleted_at IS NULL
                   AND COALESCE(cxc.active, 1) = 1
                LEFT JOIN clients cl
                    ON cl.id = cxc.client_id
                   ' . $clientBusinessJoin . '
                   AND cl.deleted_at IS NULL
                   AND cl.active = 1'
                . $auditJoins . '
                WHERE c.id = :id
                  ' . (Schema::hasColumn('companies', 'business_id') ? 'AND c.business_id = :business_id_scope' : '') . '
                GROUP BY c.id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if (Schema::hasColumn('companies', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $company = $stmt->fetch();

        return $company ?: null;
    }

    public static function linkedClients(int $companyId): array
    {
        $sql = 'SELECT
                    cl.id,
                    COALESCE(
                        NULLIF(cl.business_name, \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', cl.first_name, cl.last_name)), \'\'),
                        CONCAT(\'Client #\', cl.id)
                    ) AS display_name,
                    cl.phone,
                    cl.email,
                    cl.city,
                    cl.state
                FROM companies_x_clients cxc
                INNER JOIN clients cl
                    ON cl.id = cxc.client_id
                WHERE cxc.company_id = :company_id
                  AND cxc.deleted_at IS NULL
                  AND COALESCE(cxc.active, 1) = 1
                  AND cl.deleted_at IS NULL
                  AND cl.active = 1
                  ' . (Schema::hasColumn('clients', 'business_id') ? 'AND cl.business_id = :business_id_scope' : '') . '
                ORDER BY display_name ASC';

        $stmt = Database::connection()->prepare($sql);
        $params = ['company_id' => $companyId];
        if (Schema::hasColumn('clients', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function lookupByName(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT c.id, c.name, c.city, c.state
                FROM companies c
                WHERE c.deleted_at IS NULL
                  AND COALESCE(c.active, 1) = 1
                  ' . (Schema::hasColumn('companies', 'business_id') ? 'AND c.business_id = :business_id_scope' : '') . '
                  AND c.name LIKE :term
                ORDER BY c.name ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $params = ['term' => '%' . $term . '%'];
        if (Schema::hasColumn('companies', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function normalizeName(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);
        return $normalized ?? '';
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

    private static function normalizeDomain(string $value): string
    {
        $raw = trim(strtolower($value));
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/^https?:\/\//', '', $raw);
        $raw = preg_replace('/^www\./', '', (string) $raw);
        $raw = trim((string) strtok((string) $raw, '/'));

        return $raw !== '' ? $raw : '';
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
