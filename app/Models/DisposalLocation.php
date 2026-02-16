<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DisposalLocation
{
    public static function allActive(): array
    {
        $sql = 'SELECT id, name, type, address_1, address_2, city, state, zip, phone, email, note, created_at, updated_at
                FROM disposal_locations
                WHERE deleted_at IS NULL
                  AND active = 1
                ORDER BY name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $sql = 'SELECT id, name, type, address_1, address_2, city, state, zip, phone, email, note, active, deleted_at
                FROM disposal_locations
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $location = $stmt->fetch();

        return $location ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $columns = ['name', 'type', 'address_1', 'address_2', 'city', 'state', 'zip', 'phone', 'email', 'note', 'active', 'created_at', 'updated_at'];
        $values = [':name', ':type', ':address_1', ':address_2', ':city', ':state', ':zip', ':phone', ':email', ':note', '1', 'NOW()', 'NOW()'];
        $params = [
            'name' => $data['name'],
            'type' => $data['type'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('disposal_locations', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('disposal_locations', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO disposal_locations (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        $sets = [
            'name = :name',
            'type = :type',
            'address_1 = :address_1',
            'address_2 = :address_2',
            'city = :city',
            'state = :state',
            'zip = :zip',
            'phone = :phone',
            'email = :email',
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'name' => $data['name'],
            'type' => $data['type'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('disposal_locations', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE disposal_locations
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        $sets = [
            'active = 0',
            'deleted_at = NOW()',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('disposal_locations', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('disposal_locations', 'deleted_by')) {
            $sets[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE disposal_locations
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function lookupActiveByType(string $term, string $type, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $sql = 'SELECT id, name, type, city, state
                FROM disposal_locations
                WHERE deleted_at IS NULL
                  AND active = 1
                  AND type = :type
                  AND (
                        name LIKE :term
                        OR city LIKE :term
                        OR state LIKE :term
                      )
                ORDER BY name ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'type' => $type,
            'term' => '%' . $term . '%',
        ]);

        return $stmt->fetchAll();
    }

    public static function findActiveByIdAndType(int $id, string $type): ?array
    {
        $sql = 'SELECT id, name, type
                FROM disposal_locations
                WHERE id = :id
                  AND type = :type
                  AND deleted_at IS NULL
                  AND active = 1
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'type' => $type,
        ]);

        $location = $stmt->fetch();
        return $location ?: null;
    }
}
