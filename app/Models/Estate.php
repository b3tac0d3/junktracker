<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Estate
{
    public static function search(string $term = '', string $status = 'active'): array
    {
        $sql = 'SELECT e.id,
                       e.client_id,
                       e.name,
                       e.phone,
                       e.email,
                       e.city,
                       e.state,
                       e.active,
                       e.deleted_at,
                       e.updated_at,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS primary_client_name
                FROM estates e
                LEFT JOIN clients c ON c.id = e.client_id';

        $where = [];
        $params = [];

        if ($status === 'active') {
            $where[] = '(e.deleted_at IS NULL AND e.active = 1)';
        } elseif ($status === 'inactive') {
            $where[] = '(e.deleted_at IS NOT NULL OR e.active = 0)';
        }

        $term = trim($term);
        if ($term !== '') {
            $where[] = '(e.name LIKE :term
                        OR e.phone LIKE :term
                        OR e.email LIKE :term
                        OR e.city LIKE :term
                        OR e.state LIKE :term
                        OR c.first_name LIKE :term
                        OR c.last_name LIKE :term
                        OR c.business_name LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $hasCreatedBy = Schema::hasColumn('estates', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('estates', 'updated_by');
        $hasDeletedBy = Schema::hasColumn('estates', 'deleted_by');

        $createdBySelect = $hasCreatedBy ? 'e.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 'e.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 'e.deleted_by' : 'NULL';

        $createdByNameSelect = $hasCreatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', e.created_by))'
            : 'NULL';
        $updatedByNameSelect = $hasUpdatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', e.updated_by))'
            : 'NULL';
        $deletedByNameSelect = $hasDeletedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', e.deleted_by))'
            : 'NULL';

        $auditJoins = '';
        if ($hasCreatedBy) {
            $auditJoins .= ' LEFT JOIN users u_created ON u_created.id = e.created_by';
        }
        if ($hasUpdatedBy) {
            $auditJoins .= ' LEFT JOIN users u_updated ON u_updated.id = e.updated_by';
        }
        if ($hasDeletedBy) {
            $auditJoins .= ' LEFT JOIN users u_deleted ON u_deleted.id = e.deleted_by';
        }

        $sql = 'SELECT e.id,
                       e.client_id,
                       e.name,
                       e.note,
                       e.address_1,
                       e.address_2,
                       e.city,
                       e.state,
                       e.zip,
                       e.phone,
                       e.can_text,
                       e.email,
                       e.active,
                       e.deleted_at,
                       e.created_at,
                       e.updated_at,
                       ' . $createdBySelect . ' AS created_by,
                       ' . $createdByNameSelect . ' AS created_by_name,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $updatedByNameSelect . ' AS updated_by_name,
                       ' . $deletedBySelect . ' AS deleted_by,
                       ' . $deletedByNameSelect . ' AS deleted_by_name,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS primary_client_name
                FROM estates e
                LEFT JOIN clients c ON c.id = e.client_id
                ' . $auditJoins . '
                WHERE e.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $estate = $stmt->fetch();

        return $estate ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $columns = [
            'client_id',
            'name',
            'note',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip',
            'phone',
            'can_text',
            'email',
            'active',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':client_id',
            ':name',
            ':note',
            ':address_1',
            ':address_2',
            ':city',
            ':state',
            ':zip',
            ':phone',
            ':can_text',
            ':email',
            ':active',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'client_id' => $data['client_id'],
            'name' => $data['name'],
            'note' => $data['note'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'can_text' => $data['can_text'],
            'email' => $data['email'],
            'active' => $data['active'],
        ];

        if ($actorId !== null && Schema::hasColumn('estates', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('estates', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO estates (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        $sets = [
            'client_id = :client_id',
            'name = :name',
            'note = :note',
            'address_1 = :address_1',
            'address_2 = :address_2',
            'city = :city',
            'state = :state',
            'zip = :zip',
            'phone = :phone',
            'can_text = :can_text',
            'email = :email',
            'active = :active',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'client_id' => $data['client_id'],
            'name' => $data['name'],
            'note' => $data['note'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'can_text' => $data['can_text'],
            'email' => $data['email'],
            'active' => $data['active'],
        ];

        if ($actorId !== null && Schema::hasColumn('estates', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE estates
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function primaryClient(int $estateId): ?array
    {
        $sql = 'SELECT c.id,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS label,
                       c.phone,
                       c.email,
                       c.city,
                       c.state
                FROM estates e
                INNER JOIN clients c ON c.id = e.client_id
                WHERE e.id = :estate_id
                  AND c.deleted_at IS NULL
                  AND c.active = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['estate_id' => $estateId]);
        $client = $stmt->fetch();

        return $client ?: null;
    }

    public static function relatedClients(int $estateId): array
    {
        if (!self::hasEstateClientLinkTable()) {
            $primary = self::primaryClient($estateId);
            return $primary ? [$primary] : [];
        }

        $sql = 'SELECT id, label, phone, email, city, state
                FROM (
                    SELECT
                        c.id,
                        COALESCE(
                            NULLIF(c.business_name, \'\'),
                            NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                            CONCAT(\'Client #\', c.id)
                        ) AS label,
                        c.phone,
                        c.email,
                        c.city,
                        c.state
                    FROM estates e
                    INNER JOIN clients c ON c.id = e.client_id
                    WHERE e.id = :estate_id_primary
                      AND c.deleted_at IS NULL
                      AND c.active = 1

                    UNION

                    SELECT
                        c2.id,
                        COALESCE(
                            NULLIF(c2.business_name, \'\'),
                            NULLIF(TRIM(CONCAT_WS(\' \', c2.first_name, c2.last_name)), \'\'),
                            CONCAT(\'Client #\', c2.id)
                        ) AS label,
                        c2.phone,
                        c2.email,
                        c2.city,
                        c2.state
                    FROM estates_x_clients exc
                    INNER JOIN clients c2 ON c2.id = exc.client_id
                    WHERE exc.estate_id = :estate_id_linked
                      AND exc.deleted_at IS NULL
                      AND COALESCE(exc.active, 1) = 1
                      AND c2.deleted_at IS NULL
                      AND c2.active = 1
                ) clients
                ORDER BY label ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'estate_id_primary' => $estateId,
            'estate_id_linked' => $estateId,
        ]);

        return $stmt->fetchAll();
    }

    public static function lookupByName(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT e.id, e.name, e.city, e.state
                FROM estates e
                WHERE e.deleted_at IS NULL
                  AND e.active = 1
                  AND e.name LIKE :term
                ORDER BY e.name ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);

        return $stmt->fetchAll();
    }

    private static function hasEstateClientLinkTable(): bool
    {
        static $hasTable = null;

        if ($hasTable !== null) {
            return $hasTable;
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            $hasTable = false;
            return $hasTable;
        }

        $sql = 'SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = \'estates_x_clients\'
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['schema' => $schema]);

        $hasTable = (bool) $stmt->fetchColumn();
        return $hasTable;
    }
}
