<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Company
{
    public static function findActiveByName(string $name): ?array
    {
        $sql = 'SELECT id, name
                FROM companies
                WHERE deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                  AND name = :name
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['name' => trim($name)]);
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
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function search(string $term = '', string $status = 'active'): array
    {
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
                   AND cl.deleted_at IS NULL
                   AND cl.active = 1';

        $where = [];
        $params = [];

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
                   AND cl.deleted_at IS NULL
                   AND cl.active = 1'
                . $auditJoins . '
                WHERE c.id = :id
                GROUP BY c.id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
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
                ORDER BY display_name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

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
                  AND c.name LIKE :term
                ORDER BY c.name ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);

        return $stmt->fetchAll();
    }
}
