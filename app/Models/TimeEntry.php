<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class TimeEntry
{
    public static function filter(array $filters): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildWhere($filters);
        $paidExpr = self::paidExpression('e');

        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       e.end_time,
                       e.minutes_worked,
                       e.pay_rate,
                       e.total_paid,
                       e.note,
                       e.active,
                       e.deleted_at,
                       e.created_at,
                       e.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', emp.first_name, emp.last_name)), \'\'), CONCAT(\'Employee #\', emp.id)) AS employee_name,
                       CASE
                           WHEN COALESCE(e.job_id, 0) = 0 THEN \'Non-Job Time\'
                           ELSE COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id))
                       END AS job_name,
                       ' . $paidExpr . ' AS paid_calc
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE ' . $whereSql . '
                ORDER BY e.work_date DESC, e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function openEntries(array $filters = []): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildOpenWhere($filters);

        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       e.pay_rate,
                       e.note,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', emp.first_name, emp.last_name)), \'\'), CONCAT(\'Employee #\', emp.id)) AS employee_name,
                       CASE
                           WHEN COALESCE(e.job_id, 0) = 0 THEN \'Non-Job Time\'
                           ELSE COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id))
                       END AS job_name,
                       GREATEST(
                           TIMESTAMPDIFF(
                               MINUTE,
                               STR_TO_DATE(CONCAT(e.work_date, \' \', e.start_time), \'%Y-%m-%d %H:%i:%s\'),
                               NOW()
                           ),
                           0
                       ) AS open_minutes,
                       ROUND(
                           COALESCE(e.pay_rate, 0) * GREATEST(
                               TIMESTAMPDIFF(
                                   MINUTE,
                                   STR_TO_DATE(CONCAT(e.work_date, \' \', e.start_time), \'%Y-%m-%d %H:%i:%s\'),
                                   NOW()
                               ),
                               0
                           ) / 60,
                           2
                       ) AS open_paid
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE ' . $whereSql . '
                ORDER BY e.start_time ASC, e.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function openSummary(array $filters = []): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildOpenWhere($filters);

        $sql = 'SELECT
                    COUNT(*) AS active_count,
                    COALESCE(SUM(
                        GREATEST(
                            TIMESTAMPDIFF(
                                MINUTE,
                                STR_TO_DATE(CONCAT(e.work_date, \' \', e.start_time), \'%Y-%m-%d %H:%i:%s\'),
                                NOW()
                            ),
                            0
                        )
                    ), 0) AS total_open_minutes,
                    COALESCE(SUM(
                        ROUND(
                            COALESCE(e.pay_rate, 0) * GREATEST(
                                TIMESTAMPDIFF(
                                    MINUTE,
                                    STR_TO_DATE(CONCAT(e.work_date, \' \', e.start_time), \'%Y-%m-%d %H:%i:%s\'),
                                    NOW()
                                ),
                                0
                            ) / 60,
                            2
                        )
                    ), 0) AS total_open_paid
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE ' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: [
            'active_count' => 0,
            'total_open_minutes' => 0,
            'total_open_paid' => 0,
        ];
    }

    public static function summary(array $filters): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildWhere($filters);
        $paidExpr = self::paidExpression('e');

        $sql = 'SELECT
                    COALESCE(SUM(COALESCE(e.minutes_worked, 0)), 0) AS total_minutes,
                    COALESCE(SUM(' . $paidExpr . '), 0) AS total_paid,
                    COUNT(*) AS entry_count
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE ' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: [
            'total_minutes' => 0,
            'total_paid' => 0,
            'entry_count' => 0,
        ];
    }

    public static function summaryByEmployee(array $filters): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildWhere($filters);
        $paidExpr = self::paidExpression('e');

        $sql = 'SELECT e.employee_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', emp.first_name, emp.last_name)), \'\'), CONCAT(\'Employee #\', emp.id)) AS employee_name,
                       COALESCE(SUM(COALESCE(e.minutes_worked, 0)), 0) AS total_minutes,
                       COALESCE(SUM(' . $paidExpr . '), 0) AS total_paid,
                       COUNT(*) AS entry_count
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE ' . $whereSql . '
                GROUP BY e.employee_id, employee_name
                ORDER BY total_paid DESC, total_minutes DESC, employee_name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function forJob(int $jobId): array
    {
        self::ensureTable();

        $paidExpr = self::paidExpression('e');

        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       e.end_time,
                       e.minutes_worked,
                       e.pay_rate,
                       e.total_paid,
                       e.note,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', emp.first_name, emp.last_name)), \'\'), CONCAT(\'Employee #\', emp.id)) AS employee_name,
                       ' . $paidExpr . ' AS paid_calc
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                WHERE e.job_id = :job_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                ORDER BY e.work_date DESC, e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function summaryForJob(int $jobId): array
    {
        self::ensureTable();

        $paidExpr = self::paidExpression('e');

        $sql = 'SELECT
                    COALESCE(SUM(COALESCE(e.minutes_worked, 0)), 0) AS total_minutes,
                    COALESCE(SUM(' . $paidExpr . '), 0) AS total_paid,
                    COUNT(*) AS entry_count
                FROM employee_time_entries e
                WHERE e.job_id = :job_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);
        $row = $stmt->fetch();

        return $row ?: [
            'total_minutes' => 0,
            'total_paid' => 0,
            'entry_count' => 0,
        ];
    }

    public static function summaryForEmployeeBetween(int $employeeId, ?string $startDate, ?string $endDate): array
    {
        self::ensureTable();

        if ($employeeId <= 0) {
            return [
                'total_minutes' => 0,
                'total_paid' => 0,
                'entry_count' => 0,
            ];
        }

        $paidExpr = self::paidExpression('e');

        $where = [
            'e.employee_id = :employee_id',
            'e.deleted_at IS NULL',
            'COALESCE(e.active, 1) = 1',
        ];
        $params = ['employee_id' => $employeeId];

        if ($startDate !== null && $startDate !== '') {
            $where[] = 'e.work_date >= :start_date';
            $params['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'e.work_date <= :end_date';
            $params['end_date'] = $endDate;
        }

        $sql = 'SELECT
                    COALESCE(SUM(COALESCE(e.minutes_worked, 0)), 0) AS total_minutes,
                    COALESCE(SUM(' . $paidExpr . '), 0) AS total_paid,
                    COUNT(*) AS entry_count
                FROM employee_time_entries e
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: [
            'total_minutes' => 0,
            'total_paid' => 0,
            'entry_count' => 0,
        ];
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureTable();

        $columns = [
            'employee_id',
            'job_id',
            'work_date',
            'start_time',
            'end_time',
            'minutes_worked',
            'pay_rate',
            'total_paid',
            'note',
            'active',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':employee_id',
            ':job_id',
            ':work_date',
            ':start_time',
            ':end_time',
            ':minutes_worked',
            ':pay_rate',
            ':total_paid',
            ':note',
            '1',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'employee_id' => $data['employee_id'],
            'job_id' => $data['job_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'minutes_worked' => $data['minutes_worked'],
            'pay_rate' => $data['pay_rate'],
            'total_paid' => $data['total_paid'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('employee_time_entries', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('employee_time_entries', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO employee_time_entries (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();

        if ($id <= 0) {
            return null;
        }

        $paidExpr = self::paidExpression('e');

        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       e.end_time,
                       e.minutes_worked,
                       e.pay_rate,
                       e.total_paid,
                       e.note,
                       e.active,
                       e.deleted_at,
                       e.created_at,
                       e.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', emp.first_name, emp.last_name)), \'\'), CONCAT(\'Employee #\', emp.id)) AS employee_name,
                       CASE
                           WHEN COALESCE(e.job_id, 0) = 0 THEN \'Non-Job Time\'
                           ELSE COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id))
                       END AS job_name,
                       ' . $paidExpr . ' AS paid_calc
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE e.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'employee_id = :employee_id',
            'job_id = :job_id',
            'work_date = :work_date',
            'start_time = :start_time',
            'end_time = :end_time',
            'minutes_worked = :minutes_worked',
            'pay_rate = :pay_rate',
            'total_paid = :total_paid',
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'employee_id' => $data['employee_id'],
            'job_id' => $data['job_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'minutes_worked' => $data['minutes_worked'],
            'pay_rate' => $data['pay_rate'],
            'total_paid' => $data['total_paid'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && Schema::hasColumn('employee_time_entries', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE employee_time_entries
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'active = 0',
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('employee_time_entries', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('employee_time_entries', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE employee_time_entries
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function findOpenForEmployee(int $employeeId): ?array
    {
        self::ensureTable();

        if ($employeeId <= 0) {
            return null;
        }

        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       CASE
                           WHEN COALESCE(e.job_id, 0) = 0 THEN \'Non-Job Time\'
                           ELSE COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id))
                       END AS job_name
                FROM employee_time_entries e
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE e.employee_id = :employee_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  AND e.start_time IS NOT NULL
                  AND e.end_time IS NULL
                ORDER BY e.id DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['employee_id' => $employeeId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function openEntriesForJob(int $jobId): array
    {
        self::ensureTable();

        if ($jobId <= 0) {
            return [];
        }

        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       e.note,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', emp.first_name, emp.last_name)), \'\'), CONCAT(\'Employee #\', emp.id)) AS employee_name,
                       GREATEST(
                           TIMESTAMPDIFF(
                               MINUTE,
                               STR_TO_DATE(CONCAT(e.work_date, \' \', e.start_time), \'%Y-%m-%d %H:%i:%s\'),
                               NOW()
                           ),
                           0
                       ) AS open_minutes
                FROM employee_time_entries e
                LEFT JOIN employees emp ON emp.id = e.employee_id
                WHERE e.job_id = :job_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  AND e.start_time IS NOT NULL
                  AND e.end_time IS NULL
                ORDER BY e.start_time ASC, e.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    public static function openEntriesOutsideJob(int $jobId, array $employeeIds): array
    {
        self::ensureTable();

        if ($jobId <= 0 || empty($employeeIds)) {
            return [];
        }

        $employeeIds = array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, $employeeIds), static fn (int $id): bool => $id > 0));
        if (empty($employeeIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($employeeIds), '?'));
        $sql = 'SELECT e.id,
                       e.employee_id,
                       e.job_id,
                       e.work_date,
                       e.start_time,
                       CASE
                           WHEN COALESCE(e.job_id, 0) = 0 THEN \'Non-Job Time\'
                           ELSE COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id))
                       END AS job_name
                FROM employee_time_entries e
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE e.job_id <> ?
                  AND e.employee_id IN (' . $placeholders . ')
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  AND e.start_time IS NOT NULL
                  AND e.end_time IS NULL
                ORDER BY e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$jobId], $employeeIds));

        return $stmt->fetchAll();
    }

    public static function punchOut(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureTable();

        if ($id <= 0) {
            return;
        }

        $sets = [
            'end_time = :end_time',
            'minutes_worked = :minutes_worked',
            'pay_rate = :pay_rate',
            'total_paid = :total_paid',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'end_time' => $data['end_time'],
            'minutes_worked' => $data['minutes_worked'],
            'pay_rate' => $data['pay_rate'],
            'total_paid' => $data['total_paid'],
        ];

        if (array_key_exists('note', $data)) {
            $sets[] = 'note = :note';
            $params['note'] = $data['note'];
        }

        if ($actorId !== null && Schema::hasColumn('employee_time_entries', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE employee_time_entries
                SET ' . implode(', ', $sets) . '
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                  AND start_time IS NOT NULL
                  AND end_time IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function employees(): array
    {
        $hasHourlyRate = Schema::hasColumn('employees', 'hourly_rate');
        $hasWageRate = Schema::hasColumn('employees', 'wage_rate');
        $rateExpr = $hasHourlyRate && $hasWageRate
            ? 'COALESCE(e.hourly_rate, e.wage_rate)'
            : ($hasHourlyRate ? 'e.hourly_rate' : ($hasWageRate ? 'e.wage_rate' : 'NULL'));

        $sql = 'SELECT e.id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name,
                       ' . $rateExpr . ' AS pay_rate
                FROM employees e
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                ORDER BY name ASC';

        $stmt = Database::connection()->query($sql);
        return $stmt->fetchAll();
    }

    public static function jobs(): array
    {
        $sql = 'SELECT j.id,
                       COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS name,
                       j.job_status
                FROM jobs j
                WHERE j.deleted_at IS NULL
                  AND COALESCE(j.active, 1) = 1
                ORDER BY j.id DESC';

        $stmt = Database::connection()->query($sql);
        return $stmt->fetchAll();
    }

    public static function lookupEmployees(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $hasHourlyRate = Schema::hasColumn('employees', 'hourly_rate');
        $hasWageRate = Schema::hasColumn('employees', 'wage_rate');
        $rateExpr = $hasHourlyRate && $hasWageRate
            ? 'COALESCE(e.hourly_rate, e.wage_rate)'
            : ($hasHourlyRate ? 'e.hourly_rate' : ($hasWageRate ? 'e.wage_rate' : 'NULL'));

        $sql = 'SELECT e.id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\'), CONCAT(\'Employee #\', e.id)) AS name,
                       ' . $rateExpr . ' AS pay_rate
                FROM employees e
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  AND (
                        e.first_name LIKE :term
                        OR e.last_name LIKE :term
                        OR CONCAT_WS(\' \', e.first_name, e.last_name) LIKE :term
                        OR e.email LIKE :term
                        OR e.phone LIKE :term
                        OR CAST(e.id AS CHAR) LIKE :term
                      )
                ORDER BY name ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);

        return $stmt->fetchAll();
    }

    public static function lookupJobs(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $sql = 'SELECT j.id,
                       COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS name,
                       j.city,
                       j.state,
                       j.job_status
                FROM jobs j
                WHERE j.deleted_at IS NULL
                  AND COALESCE(j.active, 1) = 1
                  AND (
                        j.name LIKE :term
                        OR CAST(j.id AS CHAR) LIKE :term
                        OR j.city LIKE :term
                        OR j.state LIKE :term
                      )
                ORDER BY j.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);

        return $stmt->fetchAll();
    }

    public static function employeeRate(int $employeeId): ?float
    {
        if ($employeeId <= 0) {
            return null;
        }

        $hasHourlyRate = Schema::hasColumn('employees', 'hourly_rate');
        $hasWageRate = Schema::hasColumn('employees', 'wage_rate');
        $rateExpr = $hasHourlyRate && $hasWageRate
            ? 'COALESCE(hourly_rate, wage_rate)'
            : ($hasHourlyRate ? 'hourly_rate' : ($hasWageRate ? 'wage_rate' : 'NULL'));

        $sql = 'SELECT ' . $rateExpr . ' AS pay_rate
                FROM employees
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $employeeId]);
        $row = $stmt->fetch();
        if (!$row || !isset($row['pay_rate']) || $row['pay_rate'] === null) {
            return null;
        }

        return (float) $row['pay_rate'];
    }

    private static function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $recordStatus = (string) ($filters['record_status'] ?? 'active');
        if ($recordStatus === 'active') {
            $where[] = '(e.deleted_at IS NULL AND COALESCE(e.active, 1) = 1)';
        } elseif ($recordStatus === 'deleted') {
            $where[] = '(e.deleted_at IS NOT NULL OR COALESCE(e.active, 1) = 0)';
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(e.id AS CHAR) LIKE :q
                        OR e.note LIKE :q
                        OR CAST(e.minutes_worked AS CHAR) LIKE :q
                        OR emp.first_name LIKE :q
                        OR emp.last_name LIKE :q
                        OR j.name LIKE :q
                        OR (COALESCE(e.job_id, 0) = 0 AND \'Non-Job Time\' LIKE :q))';
            $params['q'] = '%' . $query . '%';
        }

        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : 0;
        if ($employeeId > 0) {
            $where[] = 'e.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        if (array_key_exists('job_id', $filters) && $filters['job_id'] !== null) {
            $jobId = (int) $filters['job_id'];
            if ($jobId > 0) {
                $where[] = 'e.job_id = :job_id';
                $params['job_id'] = $jobId;
            } elseif ($jobId === 0) {
                $where[] = 'COALESCE(e.job_id, 0) = 0';
            }
        }

        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $where[] = 'e.work_date >= :start_date';
            $params['start_date'] = $startDate;
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $where[] = 'e.work_date <= :end_date';
            $params['end_date'] = $endDate;
        }

        if (empty($where)) {
            $where[] = '1=1';
        }

        return [implode(' AND ', $where), $params];
    }

    private static function buildOpenWhere(array $filters): array
    {
        $where = [
            'e.deleted_at IS NULL',
            'COALESCE(e.active, 1) = 1',
            'e.start_time IS NOT NULL',
            'e.end_time IS NULL',
        ];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(e.id AS CHAR) LIKE :q
                        OR emp.first_name LIKE :q
                        OR emp.last_name LIKE :q
                        OR j.name LIKE :q
                        OR CAST(e.job_id AS CHAR) LIKE :q
                        OR (COALESCE(e.job_id, 0) = 0 AND \'Non-Job Time\' LIKE :q))';
            $params['q'] = '%' . $query . '%';
        }

        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : 0;
        if ($employeeId > 0) {
            $where[] = 'e.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        if (array_key_exists('job_id', $filters) && $filters['job_id'] !== null) {
            $jobId = (int) $filters['job_id'];
            if ($jobId > 0) {
                $where[] = 'e.job_id = :job_id';
                $params['job_id'] = $jobId;
            } elseif ($jobId === 0) {
                $where[] = 'COALESCE(e.job_id, 0) = 0';
            }
        }

        return [implode(' AND ', $where), $params];
    }

    private static function paidExpression(string $alias): string
    {
        return 'COALESCE(' . $alias . '.total_paid, ROUND(COALESCE(' . $alias . '.pay_rate, 0) * COALESCE(' . $alias . '.minutes_worked, 0) / 60, 2), 0)';
    }

    private static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS employee_time_entries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                employee_id BIGINT UNSIGNED NOT NULL,
                job_id BIGINT UNSIGNED NULL,
                work_date DATE NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                minutes_worked INT UNSIGNED NULL,
                pay_rate DECIMAL(12,2) UNSIGNED NULL,
                total_paid DECIMAL(12,2) UNSIGNED NULL,
                note TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_time_employee_date (employee_id, work_date),
                KEY idx_time_job_date (job_id, work_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureNullableJobId();
        $ensured = true;
    }

    private static function ensureNullableJobId(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (!Schema::hasColumn('employee_time_entries', 'job_id')) {
            return;
        }

        try {
            Database::connection()->exec('ALTER TABLE employee_time_entries MODIFY job_id BIGINT UNSIGNED NULL');
        } catch (Throwable) {
            // Keep runtime resilient when table is managed externally.
        }
    }
}
