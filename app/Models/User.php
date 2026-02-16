<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class User
{
    public static function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, email, first_name, last_name, role, password_hash, is_active FROM users WHERE email = :email LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $createdBySelect = Schema::hasColumn('users', 'created_by') ? 'created_by' : 'NULL';
        $updatedBySelect = Schema::hasColumn('users', 'updated_by') ? 'updated_by' : 'NULL';
        $deletedAtSelect = Schema::hasColumn('users', 'deleted_at') ? 'deleted_at' : 'NULL';
        $deletedBySelect = Schema::hasColumn('users', 'deleted_by') ? 'deleted_by' : 'NULL';

        $sql = 'SELECT id,
                       email,
                       first_name,
                       last_name,
                       role,
                       is_active,
                       created_at,
                       ' . $createdBySelect . ' AS created_by,
                       updated_at,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $deletedAtSelect . ' AS deleted_at,
                       ' . $deletedBySelect . ' AS deleted_by
                FROM users
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByIdWithPassword(int $id): ?array
    {
        $sql = 'SELECT id, email, first_name, last_name, role, password_hash, is_active FROM users WHERE id = :id LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function search(string $term = '', string $status = 'active'): array
    {
        $sql = 'SELECT id, first_name, last_name, email, role, is_active, created_at FROM users';
        $params = [];
        $where = [];

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        if ($term !== '') {
            $where[] = '(email LIKE :term OR first_name LIKE :term OR last_name LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY last_name, first_name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $columns = ['email', 'first_name', 'last_name', 'role', 'password_hash', 'is_active', 'created_at', 'updated_at'];
        $values = [':email', ':first_name', ':last_name', ':role', ':password_hash', ':is_active', 'NOW()', 'NOW()'];
        $params = [
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'is_active' => $data['is_active'],
        ];

        if ($actorId !== null && Schema::hasColumn('users', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO users (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        $fields = [
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ];

        $sql = 'UPDATE users SET email = :email, first_name = :first_name, last_name = :last_name, role = :role, is_active = :is_active';

        if (!empty($data['password'])) {
            $sql .= ', password_hash = :password_hash';
            $fields['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $sql .= ', updated_at = NOW()';
        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $fields['updated_by'] = $actorId;
        }

        $sql .= ' WHERE id = :id';
        $fields['id'] = $id;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($fields);
    }

    public static function deactivate(int $id, ?int $actorId = null): void
    {
        $sets = [
            'is_active = 0',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('users', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if (Schema::hasColumn('users', 'deleted_at')) {
            $sets[] = 'deleted_at = COALESCE(deleted_at, NOW())';
        }
        if ($actorId !== null && Schema::hasColumn('users', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }
}
