<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Employee
{
    public static function supportsUserLinking(): bool
    {
        return Schema::hasColumn('employees', 'user_id');
    }

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

        if (Schema::hasColumn('employees', 'business_id')) {
            $where[] = 'e.business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

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
                  ' . (Schema::hasColumn('employees', 'business_id') ? 'AND e.business_id = :business_id_scope' : '') . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if (Schema::hasColumn('employees', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $employee = $stmt->fetch();

        return $employee ?: null;
    }

    public static function findForUser(array $user): ?array
    {
        $userId = isset($user['id']) ? (int) $user['id'] : 0;
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $firstName = strtolower(trim((string) ($user['first_name'] ?? '')));
        $lastName = strtolower(trim((string) ($user['last_name'] ?? '')));

        $hasHourlyRate = Schema::hasColumn('employees', 'hourly_rate');
        $hasWageRate = Schema::hasColumn('employees', 'wage_rate');
        $rateExpr = $hasHourlyRate && $hasWageRate
            ? 'COALESCE(e.hourly_rate, e.wage_rate)'
            : ($hasHourlyRate ? 'e.hourly_rate' : ($hasWageRate ? 'e.wage_rate' : 'NULL'));

        $baseSelect = 'SELECT e.id,
                              e.first_name,
                              e.last_name,
                              e.email,
                              e.active,
                              e.deleted_at,
                              ' . $rateExpr . ' AS pay_rate,
                              COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name
                       FROM employees e
                       WHERE e.deleted_at IS NULL
                         AND COALESCE(e.active, 1) = 1
                         ' . (Schema::hasColumn('employees', 'business_id') ? 'AND e.business_id = :business_id_scope' : '') . '
                         AND ';
        $baseParams = [];
        if (Schema::hasColumn('employees', 'business_id')) {
            $baseParams['business_id_scope'] = self::currentBusinessId();
        }

        if ($userId > 0 && Schema::hasColumn('employees', 'user_id')) {
            $sql = $baseSelect . 'e.user_id = :user_id
                                  ORDER BY e.id ASC
                                  LIMIT 1';
            $stmt = Database::connection()->prepare($sql);
            $params = $baseParams;
            $params['user_id'] = $userId;
            $stmt->execute($params);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        if ($email !== '') {
            $sql = $baseSelect . 'LOWER(COALESCE(e.email, \'\')) = :email
                                  ORDER BY e.id ASC
                                  LIMIT 2';
            $stmt = Database::connection()->prepare($sql);
            $params = $baseParams;
            $params['email'] = $email;
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            if (count($rows) === 1) {
                return $rows[0];
            }
        }

        if ($firstName !== '' && $lastName !== '') {
            $sql = $baseSelect . 'LOWER(e.first_name) = :first_name
                                  AND LOWER(e.last_name) = :last_name
                                  ORDER BY e.id ASC
                                  LIMIT 2';
            $stmt = Database::connection()->prepare($sql);
            $params = $baseParams;
            $params['first_name'] = $firstName;
            $params['last_name'] = $lastName;
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            if (count($rows) === 1) {
                return $rows[0];
            }
        }

        return null;
    }

    public static function linkedToUser(int $userId): ?array
    {
        if ($userId <= 0 || !self::supportsUserLinking()) {
            return null;
        }

        $sql = 'SELECT e.id,
                       e.first_name,
                       e.last_name,
                       e.email,
                       e.phone,
                       e.active,
                       e.deleted_at,
                       e.user_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name
                FROM employees e
                WHERE e.user_id = :user_id
                  ' . (Schema::hasColumn('employees', 'business_id') ? 'AND e.business_id = :business_id_scope' : '') . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['user_id' => $userId];
        if (Schema::hasColumn('employees', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function lookupForUserLink(string $term = ''): array
    {
        if (!self::supportsUserLinking()) {
            return [];
        }

        $term = trim($term);
        $sql = 'SELECT e.id,
                       e.first_name,
                       e.last_name,
                       e.email,
                       e.phone,
                       e.user_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email) AS linked_user_name
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  ' . (Schema::hasColumn('employees', 'business_id') ? 'AND e.business_id = :business_id_scope' : '') . '';
        $params = [];
        if (Schema::hasColumn('employees', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }

        if ($term !== '') {
            $sql .= ' AND (
                        e.first_name LIKE :term
                        OR e.last_name LIKE :term
                        OR e.email LIKE :term
                        OR e.phone LIKE :term
                        OR CAST(e.id AS CHAR) LIKE :term
                      )';
            $params['term'] = '%' . $term . '%';
        }

        $sql .= ' ORDER BY e.last_name ASC, e.first_name ASC, e.id ASC
                  LIMIT 25';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findUserLinkCandidates(array $userData): array
    {
        if (!self::supportsUserLinking()) {
            return [];
        }

        $email = strtolower(trim((string) ($userData['email'] ?? '')));
        $firstName = strtolower(trim((string) ($userData['first_name'] ?? '')));
        $lastName = strtolower(trim((string) ($userData['last_name'] ?? '')));

        $conditions = [];
        $params = [];
        $scoreParts = ['0'];

        if ($email !== '') {
            $conditions[] = 'LOWER(COALESCE(e.email, \'\')) = :email';
            $params['email'] = $email;
            $scoreParts[] = 'CASE WHEN LOWER(COALESCE(e.email, \'\')) = :email THEN 100 ELSE 0 END';
        }

        if ($firstName !== '' && $lastName !== '') {
            $conditions[] = '(LOWER(COALESCE(e.first_name, \'\')) = :first_name
                          AND LOWER(COALESCE(e.last_name, \'\')) = :last_name)';
            $params['first_name'] = $firstName;
            $params['last_name'] = $lastName;
            $scoreParts[] = 'CASE WHEN LOWER(COALESCE(e.first_name, \'\')) = :first_name
                                    AND LOWER(COALESCE(e.last_name, \'\')) = :last_name
                              THEN 60 ELSE 0 END';
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = 'SELECT e.id,
                       e.first_name,
                       e.last_name,
                       e.email,
                       e.phone,
                       e.user_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email) AS linked_user_name,
                       (' . implode(' + ', $scoreParts) . ') AS match_score
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  ' . (Schema::hasColumn('employees', 'business_id') ? 'AND e.business_id = :business_id_scope' : '') . '
                  AND (' . implode(' OR ', $conditions) . ')
                ORDER BY match_score DESC, e.last_name ASC, e.first_name ASC, e.id ASC
                LIMIT 10';
        if (Schema::hasColumn('employees', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['match_score'] = isset($row['match_score']) ? (int) $row['match_score'] : 0;
        }
        unset($row);

        return $rows;
    }

    public static function findActiveById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT e.id,
                       e.first_name,
                       e.last_name,
                       e.email,
                       e.phone,
                       e.active,
                       e.deleted_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name
                FROM employees e
                WHERE e.id = :id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  ' . (Schema::hasColumn('employees', 'business_id') ? 'AND e.business_id = :business_id_scope' : '') . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = ['id' => $id];
        if (Schema::hasColumn('employees', 'business_id')) {
            $params['business_id_scope'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function assignToUser(int $employeeId, int $userId, ?int $actorId = null): void
    {
        if ($employeeId <= 0 || $userId <= 0 || !self::supportsUserLinking()) {
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $clearSets = ['user_id = NULL', 'updated_at = NOW()'];
            $clearParams = ['user_id' => $userId];
            if ($actorId !== null && Schema::hasColumn('employees', 'updated_by')) {
                $clearSets[] = 'updated_by = :updated_by';
                $clearParams['updated_by'] = $actorId;
            }

            $clearSql = 'UPDATE employees
                         SET ' . implode(', ', $clearSets) . '
                         WHERE user_id = :user_id';
            if (Schema::hasColumn('employees', 'business_id')) {
                $clearSql .= ' AND business_id = :business_id_scope';
                $clearParams['business_id_scope'] = self::currentBusinessId();
            }
            $clearStmt = $pdo->prepare($clearSql);
            $clearStmt->execute($clearParams);

            $assignSets = ['user_id = :user_id', 'updated_at = NOW()'];
            $assignParams = [
                'employee_id' => $employeeId,
                'user_id' => $userId,
            ];
            if ($actorId !== null && Schema::hasColumn('employees', 'updated_by')) {
                $assignSets[] = 'updated_by = :updated_by';
                $assignParams['updated_by'] = $actorId;
            }

            $assignSql = 'UPDATE employees
                          SET ' . implode(', ', $assignSets) . '
                          WHERE id = :employee_id
                          ' . (Schema::hasColumn('employees', 'business_id') ? 'AND business_id = :business_id_scope' : '') . '
                          LIMIT 1';
            if (Schema::hasColumn('employees', 'business_id')) {
                $assignParams['business_id_scope'] = self::currentBusinessId();
            }
            $assignStmt = $pdo->prepare($assignSql);
            $assignStmt->execute($assignParams);

            if ($assignStmt->rowCount() < 1) {
                throw new \RuntimeException('Unable to link employee to user.');
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function clearUserLink(int $userId, ?int $actorId = null): int
    {
        if ($userId <= 0 || !self::supportsUserLinking()) {
            return 0;
        }

        $sets = ['user_id = NULL', 'updated_at = NOW()'];
        $params = ['user_id' => $userId];
        if ($actorId !== null && Schema::hasColumn('employees', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE employees
                SET ' . implode(', ', $sets) . '
                WHERE user_id = :user_id';
        if (Schema::hasColumn('employees', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
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
        if (Schema::hasColumn('employees', 'business_id')) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = self::currentBusinessId();
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
        if (Schema::hasColumn('employees', 'business_id')) {
            $sql .= ' AND business_id = :business_id_scope';
            $params['business_id_scope'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }
}
