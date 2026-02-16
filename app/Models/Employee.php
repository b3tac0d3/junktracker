<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Employee
{
    public static function search(string $term = '', string $status = 'active'): array
    {
        $hasHourlyRate = Schema::hasColumn('employees', 'hourly_rate');
        $hasWageRate = Schema::hasColumn('employees', 'wage_rate');
        $rateExpr = $hasHourlyRate && $hasWageRate
            ? 'COALESCE(e.hourly_rate, e.wage_rate)'
            : ($hasHourlyRate ? 'e.hourly_rate' : ($hasWageRate ? 'e.wage_rate' : 'NULL'));

        $sql = 'SELECT e.id,
                       e.first_name,
                       e.last_name,
                       e.phone,
                       e.email,
                       e.hire_date,
                       e.fire_date,
                       e.wage_type,
                       e.active,
                       e.deleted_at,
                       e.updated_at,
                       ' . $rateExpr . ' AS pay_rate
                FROM employees e';

        $where = [];
        $params = [];

        if ($status === 'active') {
            $where[] = '(e.deleted_at IS NULL AND COALESCE(e.active, 1) = 1)';
        } elseif ($status === 'inactive') {
            $where[] = '(e.deleted_at IS NOT NULL OR COALESCE(e.active, 1) = 0)';
        }

        $term = trim($term);
        if ($term !== '') {
            $where[] = '(e.first_name LIKE :term
                        OR e.last_name LIKE :term
                        OR e.phone LIKE :term
                        OR e.email LIKE :term
                        OR CAST(e.id AS CHAR) LIKE :term)';
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
        $hasCreatedBy = Schema::hasColumn('employees', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('employees', 'updated_by');
        $hasDeletedBy = Schema::hasColumn('employees', 'deleted_by');

        $createdBySelect = $hasCreatedBy ? 'e.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 'e.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 'e.deleted_by' : 'NULL';

        $createdByNameSelect = $hasCreatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_created.first_name, u_created.last_name)), \'\'), CONCAT(\'User #\', e.created_by))'
            : '\'—\'';
        $updatedByNameSelect = $hasUpdatedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_updated.first_name, u_updated.last_name)), \'\'), CONCAT(\'User #\', e.updated_by))'
            : '\'—\'';
        $deletedByNameSelect = $hasDeletedBy
            ? 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u_deleted.first_name, u_deleted.last_name)), \'\'), CONCAT(\'User #\', e.deleted_by))'
            : '\'—\'';

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
                       e.first_name,
                       e.last_name,
                       e.phone,
                       e.email,
                       e.hire_date,
                       e.fire_date,
                       e.wage_rate,
                       e.hourly_rate,
                       e.wage_type,
                       e.note,
                       e.active,
                       e.deleted_at,
                       e.created_at,
                       e.updated_at,
                       ' . $createdBySelect . ' AS created_by,
                       ' . $createdByNameSelect . ' AS created_by_name,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $updatedByNameSelect . ' AS updated_by_name,
                       ' . $deletedBySelect . ' AS deleted_by,
                       ' . $deletedByNameSelect . ' AS deleted_by_name
                FROM employees e'
                . $auditJoins . '
                WHERE e.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $employee = $stmt->fetch();

        return $employee ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        $columns = [
            'first_name',
            'last_name',
            'phone',
            'email',
            'hire_date',
            'fire_date',
            'wage_type',
            'note',
            'active',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':first_name',
            ':last_name',
            ':phone',
            ':email',
            ':hire_date',
            ':fire_date',
            ':wage_type',
            ':note',
            ':active',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'hire_date' => $data['hire_date'],
            'fire_date' => $data['fire_date'],
            'wage_type' => $data['wage_type'],
            'note' => $data['note'],
            'active' => $data['active'],
        ];

        if (Schema::hasColumn('employees', 'hourly_rate')) {
            $columns[] = 'hourly_rate';
            $values[] = ':hourly_rate';
            $params['hourly_rate'] = $data['pay_rate'];
        }
        if (Schema::hasColumn('employees', 'wage_rate')) {
            $columns[] = 'wage_rate';
            $values[] = ':wage_rate';
            $params['wage_rate'] = $data['pay_rate'];
        }
        if ($actorId !== null && Schema::hasColumn('employees', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('employees', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO employees (' . implode(', ', $columns) . ')
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
            'phone = :phone',
            'email = :email',
            'hire_date = :hire_date',
            'fire_date = :fire_date',
            'wage_type = :wage_type',
            'note = :note',
            'active = :active',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'hire_date' => $data['hire_date'],
            'fire_date' => $data['fire_date'],
            'wage_type' => $data['wage_type'],
            'note' => $data['note'],
            'active' => $data['active'],
        ];

        if (Schema::hasColumn('employees', 'hourly_rate')) {
            $sets[] = 'hourly_rate = :hourly_rate';
            $params['hourly_rate'] = $data['pay_rate'];
        }
        if (Schema::hasColumn('employees', 'wage_rate')) {
            $sets[] = 'wage_rate = :wage_rate';
            $params['wage_rate'] = $data['pay_rate'];
        }
        if ($actorId !== null && Schema::hasColumn('employees', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if (!empty($data['deleted_at']) && Schema::hasColumn('employees', 'deleted_at')) {
            $sets[] = 'deleted_at = :deleted_at';
            $params['deleted_at'] = $data['deleted_at'];
            if ($actorId !== null && Schema::hasColumn('employees', 'deleted_by')) {
                $sets[] = 'deleted_by = :deleted_by';
                $params['deleted_by'] = $actorId;
            }
        } elseif (Schema::hasColumn('employees', 'deleted_at')) {
            $sets[] = 'deleted_at = NULL';
            if (Schema::hasColumn('employees', 'deleted_by')) {
                $sets[] = 'deleted_by = NULL';
            }
        }

        $sql = 'UPDATE employees
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }
}
