<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class TimeEntry
{
    public static function employeeOptions(int $businessId, int $limit = 300): array
    {
        if (!SchemaInspector::hasTable('employees')) {
            return [];
        }

        $businessWhere = SchemaInspector::hasColumn('employees', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $deletedWhere = SchemaInspector::hasColumn('employees', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
        $statusWhere = SchemaInspector::hasColumn('employees', 'status') ? "COALESCE(e.status, 'active') = 'active'" : '1=1';

        $sql = "SELECT
                    e.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.first_name, e.last_name)), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id)) AS name
                FROM employees e
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                  AND {$statusWhere}
                ORDER BY name ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employees', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function jobOptions(int $businessId, int $limit = 300): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $businessWhere = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    j.id,
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Job #', j.id)) AS title
                FROM jobs j
                WHERE {$businessWhere}
                  AND {$deletedWhere}
                ORDER BY j.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexList(int $businessId, string $search = '', string $state = '', int $limit = 25, int $offset = 0, ?int $scopeEmployeeId = null): array
    {
        if (!SchemaInspector::hasTable('employee_time_entries')) {
            return [];
        }

        $query = trim($search);
        $state = strtolower(trim($state)); // open|closed|''

        $clockInSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at') ? 't.clock_in_at' : 'NULL';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $durationSql = SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes') ? 't.duration_minutes' : 'NULL';
        $isNonJobSql = SchemaInspector::hasColumn('employee_time_entries', 'is_non_job') ? 't.is_non_job' : '0';
        $jobIdSql = SchemaInspector::hasColumn('employee_time_entries', 'job_id') ? 't.job_id' : 'NULL';
        $employeeIdSql = SchemaInspector::hasColumn('employee_time_entries', 'employee_id') ? 't.employee_id' : 'NULL';

        $joinSql = '';
        $employeeNameSql = "'—'";
        $hourlyRateSql = '0';
        if (SchemaInspector::hasTable('employees') && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $employeeNameParts = ['e.first_name', 'e.last_name'];
            if (SchemaInspector::hasColumn('employees', 'suffix')) {
                $employeeNameParts[] = 'e.suffix';
            }
            $employeeBaseNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
            $employeeNameSql = $employeeBaseNameSql;
            $joinDeleted = SchemaInspector::hasColumn('employees', 'deleted_at') ? 'AND e.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN employees e ON e.id = t.employee_id {$joinDeleted}";
            $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

            if (SchemaInspector::hasTable('users')) {
                $joinSql .= ' LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL';
                $linkedUserNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
                $employeeNameSql = "COALESCE(NULLIF({$linkedUserNameSql}, ''), {$employeeBaseNameSql})";
            }
        }

        $jobTitleSql = "'Non-Job Time'";
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            $jobTitleField = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $jobTitleSql = "CASE WHEN COALESCE({$isNonJobSql}, 0) = 1 OR t.job_id IS NULL THEN 'Non-Job Time' ELSE COALESCE(NULLIF({$jobTitleField}, ''), CONCAT('Job #', j.id)) END";
            $joinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN jobs j ON j.id = t.job_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $where[] = 't.employee_id = :scope_employee_id';
        }
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        if ($state === 'open') {
            $where[] = $clockOutSql . ' IS NULL';
        } elseif ($state === 'closed') {
            $where[] = $clockOutSql . ' IS NOT NULL';
        }
        $where[] = "(
            :query = ''
            OR {$employeeNameSql} LIKE :query_like_1
            OR {$jobTitleSql} LIKE :query_like_2
            OR CAST(t.id AS CHAR) LIKE :query_like_3
        )";

        $sql = "SELECT
                    t.id,
                    {$employeeIdSql} AS employee_id,
                    {$jobIdSql} AS job_id,
                    {$clockInSql} AS clock_in_at,
                    {$clockOutSql} AS clock_out_at,
                    {$durationSql} AS duration_minutes,
                    {$isNonJobSql} AS is_non_job,
                    {$employeeNameSql} AS employee_name,
                    {$jobTitleSql} AS job_title
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY t.id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $stmt->bindValue(':scope_employee_id', (int) $scopeEmployeeId, \PDO::PARAM_INT);
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

    public static function indexCount(int $businessId, string $search = '', string $state = '', ?int $scopeEmployeeId = null): int
    {
        if (!SchemaInspector::hasTable('employee_time_entries')) {
            return 0;
        }

        $query = trim($search);
        $state = strtolower(trim($state)); // open|closed|''

        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $isNonJobSql = SchemaInspector::hasColumn('employee_time_entries', 'is_non_job') ? 't.is_non_job' : '0';

        $joinSql = '';
        $employeeNameSql = "'—'";
        $hourlyRateSql = '0';
        if (SchemaInspector::hasTable('employees') && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $employeeNameParts = ['e.first_name', 'e.last_name'];
            if (SchemaInspector::hasColumn('employees', 'suffix')) {
                $employeeNameParts[] = 'e.suffix';
            }
            $employeeBaseNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
            $employeeNameSql = $employeeBaseNameSql;
            $joinDeleted = SchemaInspector::hasColumn('employees', 'deleted_at') ? 'AND e.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN employees e ON e.id = t.employee_id {$joinDeleted}";
            $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

            if (SchemaInspector::hasTable('users')) {
                $joinSql .= ' LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL';
                $linkedUserNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
                $employeeNameSql = "COALESCE(NULLIF({$linkedUserNameSql}, ''), {$employeeBaseNameSql})";
            }
        }

        $jobTitleSql = "'Non-Job Time'";
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            $jobTitleField = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $jobTitleSql = "CASE WHEN COALESCE({$isNonJobSql}, 0) = 1 OR t.job_id IS NULL THEN 'Non-Job Time' ELSE COALESCE(NULLIF({$jobTitleField}, ''), CONCAT('Job #', j.id)) END";
            $joinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN jobs j ON j.id = t.job_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $where[] = 't.employee_id = :scope_employee_id';
        }
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        if ($state === 'open') {
            $where[] = $clockOutSql . ' IS NULL';
        } elseif ($state === 'closed') {
            $where[] = $clockOutSql . ' IS NOT NULL';
        }
        $where[] = "(
            :query = ''
            OR {$employeeNameSql} LIKE :query_like_1
            OR {$jobTitleSql} LIKE :query_like_2
            OR CAST(t.id AS CHAR) LIKE :query_like_3
        )";

        $sql = "SELECT COUNT(*)
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $stmt->bindValue(':scope_employee_id', (int) $scopeEmployeeId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForBusiness(int $businessId, int $entryId, ?int $scopeEmployeeId = null): ?array
    {
        if (!SchemaInspector::hasTable('employee_time_entries')) {
            return null;
        }

        $clockInSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at') ? 't.clock_in_at' : 'NULL';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $durationSql = SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes') ? 't.duration_minutes' : 'NULL';
        $isNonJobSql = SchemaInspector::hasColumn('employee_time_entries', 'is_non_job') ? 't.is_non_job' : '0';
        $notesSql = SchemaInspector::hasColumn('employee_time_entries', 'notes') ? 't.notes' : 'NULL';
        $employeeIdSql = SchemaInspector::hasColumn('employee_time_entries', 'employee_id') ? 't.employee_id' : 'NULL';
        $jobIdSql = SchemaInspector::hasColumn('employee_time_entries', 'job_id') ? 't.job_id' : 'NULL';
        $inLatSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_in_lat') ? 't.clock_in_lat' : 'NULL';
        $inLngSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_in_lng') ? 't.clock_in_lng' : 'NULL';
        $outLatSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_lat') ? 't.clock_out_lat' : 'NULL';
        $outLngSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_lng') ? 't.clock_out_lng' : 'NULL';

        $joinSql = '';
        $employeeNameSql = "'—'";
        $hourlyRateSql = '0';
        if (SchemaInspector::hasTable('employees') && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $employeeNameParts = ['e.first_name', 'e.last_name'];
            if (SchemaInspector::hasColumn('employees', 'suffix')) {
                $employeeNameParts[] = 'e.suffix';
            }
            $employeeBaseNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
            $employeeNameSql = $employeeBaseNameSql;
            $joinDeleted = SchemaInspector::hasColumn('employees', 'deleted_at') ? 'AND e.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN employees e ON e.id = t.employee_id {$joinDeleted}";
            $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

            if (SchemaInspector::hasTable('users')) {
                $joinSql .= ' LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL';
                $linkedUserNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
                $employeeNameSql = "COALESCE(NULLIF({$linkedUserNameSql}, ''), {$employeeBaseNameSql})";
            }
        }

        $jobTitleSql = "'Non-Job Time'";
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            $jobTitleField = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $jobTitleSql = "CASE WHEN COALESCE({$isNonJobSql}, 0) = 1 OR t.job_id IS NULL THEN 'Non-Job Time' ELSE COALESCE(NULLIF({$jobTitleField}, ''), CONCAT('Job #', j.id)) END";
            $joinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN jobs j ON j.id = t.job_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $where[] = 't.employee_id = :scope_employee_id';
        }
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        $where[] = 't.id = :entry_id';

        $sql = "SELECT
                    t.id,
                    {$clockInSql} AS clock_in_at,
                    {$clockOutSql} AS clock_out_at,
                    {$durationSql} AS duration_minutes,
                    {$isNonJobSql} AS is_non_job,
                    {$notesSql} AS notes,
                    {$employeeIdSql} AS employee_id,
                    {$jobIdSql} AS job_id,
                    {$inLatSql} AS clock_in_lat,
                    {$inLngSql} AS clock_in_lng,
                    {$outLatSql} AS clock_out_lat,
                    {$outLngSql} AS clock_out_lng,
                    {$hourlyRateSql} AS hourly_rate,
                    {$employeeNameSql} AS employee_name,
                    {$jobTitleSql} AS job_title
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $stmt->bindValue(':scope_employee_id', (int) $scopeEmployeeId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':entry_id', $entryId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function summary(int $businessId, ?int $scopeEmployeeId = null): array
    {
        if (!SchemaInspector::hasTable('employee_time_entries')) {
            return ['entries' => 0, 'open_entries' => 0, 'hours' => 0.0];
        }

        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $durationSql = SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes') ? 'COALESCE(t.duration_minutes, 0)' : '0';
        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $where[] = 't.employee_id = :scope_employee_id';
        }
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT
                    COUNT(*) AS total_entries,
                    SUM(CASE WHEN {$clockOutSql} IS NULL THEN 1 ELSE 0 END) AS open_entries,
                    COALESCE(SUM({$durationSql}), 0) AS total_minutes
                FROM employee_time_entries t
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $params = [];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $params['scope_employee_id'] = (int) $scopeEmployeeId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'entries' => (int) ($row['total_entries'] ?? 0),
            'open_entries' => (int) ($row['open_entries'] ?? 0),
            'hours' => round(((int) ($row['total_minutes'] ?? 0)) / 60, 2),
        ];
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        $hasEstateSaleId = SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id');
        $estateSaleIdSql = $hasEstateSaleId ? ', estate_sale_id' : '';
        $estateSaleIdValueSql = $hasEstateSaleId ? ', :estate_sale_id' : '';

        $sql = 'INSERT INTO employee_time_entries (
                    business_id, employee_id, job_id' . $estateSaleIdSql . ', is_non_job,
                    clock_in_at, clock_out_at, duration_minutes,
                    clock_in_lat, clock_in_lng, clock_out_lat, clock_out_lng,
                    notes, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :business_id, :employee_id, :job_id' . $estateSaleIdValueSql . ', :is_non_job,
                    :clock_in_at, :clock_out_at, :duration_minutes,
                    :clock_in_lat, :clock_in_lng, :clock_out_lat, :clock_out_lng,
                    :notes, :created_by, :updated_by, NOW(), NOW()
                )';

        $params = [
            'business_id' => $businessId,
            'employee_id' => (int) ($data['employee_id'] ?? 0),
            'job_id' => (isset($data['job_id']) && (int) $data['job_id'] > 0) ? (int) $data['job_id'] : null,
            'is_non_job' => ((int) ($data['is_non_job'] ?? 0)) === 1 ? 1 : 0,
            'clock_in_at' => $data['clock_in_at'] ?? null,
            'clock_out_at' => $data['clock_out_at'] ?? null,
            'duration_minutes' => (isset($data['duration_minutes']) && (int) $data['duration_minutes'] > 0)
                ? (int) $data['duration_minutes']
                : null,
            'clock_in_lat' => $data['clock_in_lat'] ?? null,
            'clock_in_lng' => $data['clock_in_lng'] ?? null,
            'clock_out_lat' => $data['clock_out_lat'] ?? null,
            'clock_out_lng' => $data['clock_out_lng'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ];
        if ($hasEstateSaleId) {
            $params['estate_sale_id'] = (isset($data['estate_sale_id']) && (int) $data['estate_sale_id'] > 0)
                ? (int) $data['estate_sale_id']
                : null;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $entryId, array $data, int $actorUserId): bool
    {
        $deletedWhere = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $hasEstateSaleId = SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id');
        $estateSaleIdSetSql = $hasEstateSaleId ? "estate_sale_id = :estate_sale_id,\n                    " : '';

        $sql = 'UPDATE employee_time_entries
                SET employee_id = :employee_id,
                    job_id = :job_id,
                    ' . $estateSaleIdSetSql . 'is_non_job = :is_non_job,
                    clock_in_at = :clock_in_at,
                    clock_out_at = :clock_out_at,
                    duration_minutes = :duration_minutes,
                    clock_in_lat = :clock_in_lat,
                    clock_in_lng = :clock_in_lng,
                    clock_out_lat = :clock_out_lat,
                    clock_out_lng = :clock_out_lng,
                    notes = :notes,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :entry_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $params = [
            'employee_id' => (int) ($data['employee_id'] ?? 0),
            'job_id' => (isset($data['job_id']) && (int) $data['job_id'] > 0) ? (int) $data['job_id'] : null,
            'is_non_job' => ((int) ($data['is_non_job'] ?? 0)) === 1 ? 1 : 0,
            'clock_in_at' => $data['clock_in_at'] ?? null,
            'clock_out_at' => $data['clock_out_at'] ?? null,
            'duration_minutes' => (isset($data['duration_minutes']) && (int) $data['duration_minutes'] > 0)
                ? (int) $data['duration_minutes']
                : null,
            'clock_in_lat' => $data['clock_in_lat'] ?? null,
            'clock_in_lng' => $data['clock_in_lng'] ?? null,
            'clock_out_lat' => $data['clock_out_lat'] ?? null,
            'clock_out_lng' => $data['clock_out_lng'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'updated_by' => $actorUserId,
            'entry_id' => $entryId,
            'business_id' => $businessId,
        ];
        if ($hasEstateSaleId) {
            $params['estate_sale_id'] = (isset($data['estate_sale_id']) && (int) $data['estate_sale_id'] > 0)
                ? (int) $data['estate_sale_id']
                : null;
        }

        $stmt = Database::connection()->prepare($sql);

        return $stmt->execute($params);
    }

    public static function softDelete(int $businessId, int $entryId, int $actorUserId, ?int $scopeEmployeeId = null): bool
    {
        if ($entryId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return false;
        }
        if (!SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            return false;
        }

        $set = ['deleted_at = NOW()'];
        $params = [
            'entry_id' => $entryId,
        ];
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_by')) {
            $set[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'updated_by')) {
            $set[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $where = ['id = :entry_id', 'deleted_at IS NULL'];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $where[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (($scopeEmployeeId ?? 0) > 0 && SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $where[] = 'employee_id = :scope_employee_id';
            $params['scope_employee_id'] = (int) $scopeEmployeeId;
        }

        $sql = 'UPDATE employee_time_entries
                SET ' . implode(', ', $set) . '
                WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function punchBoardJobOptions(int $businessId, int $limit = 200): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : "''";

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        if (SchemaInspector::hasColumn('jobs', 'status')) {
            $where[] = "LOWER(COALESCE(j.status, '')) = :active_status";
        }

        $sql = "SELECT
                    j.id,
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Job #', j.id)) AS title,
                    {$citySql} AS city
                FROM jobs j
                WHERE " . implode(' AND ', $where) . '
                ORDER BY title ASC, j.id ASC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (SchemaInspector::hasColumn('jobs', 'status')) {
            $stmt->bindValue(':active_status', 'active');
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function jobSearchOptions(int $businessId, string $query = '', int $limit = 8): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $query = trim($query);
        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'j.city' : "''";

        $where = [];
        $where[] = SchemaInspector::hasColumn('jobs', 'business_id') ? 'j.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'j.deleted_at IS NULL' : '1=1';
        $where[] = "(
            :query = ''
            OR COALESCE({$titleSql}, '') LIKE :query_like_1
            OR CAST(j.id AS CHAR) LIKE :query_like_2
            OR COALESCE({$citySql}, '') LIKE :query_like_3
        )";

        $sql = "SELECT
                    j.id,
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Job #', j.id)) AS title,
                    {$citySql} AS city
                FROM jobs j
                WHERE " . implode(' AND ', $where) . '
                ORDER BY j.id DESC
                LIMIT :row_limit';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 100)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function jobExistsForBusiness(int $businessId, int $jobId): bool
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('jobs')) {
            return false;
        }

        $where = ['id = :job_id'];
        $params = ['job_id' => $jobId];
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $where[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
            $where[] = 'deleted_at IS NULL';
        }

        $sql = 'SELECT 1
                FROM jobs
                WHERE ' . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public static function jobLabelForBusiness(int $businessId, int $jobId): ?string
    {
        if ($jobId <= 0 || !SchemaInspector::hasTable('jobs')) {
            return null;
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'name' : "CONCAT('Job #', id)");
        $citySql = SchemaInspector::hasColumn('jobs', 'city') ? 'city' : "''";

        $where = ['id = :job_id'];
        $params = ['job_id' => $jobId];
        if (SchemaInspector::hasColumn('jobs', 'business_id')) {
            $where[] = 'business_id = :business_id';
            $params['business_id'] = $businessId;
        }
        if (SchemaInspector::hasColumn('jobs', 'deleted_at')) {
            $where[] = 'deleted_at IS NULL';
        }

        $sql = "SELECT
                    COALESCE(NULLIF({$titleSql}, ''), CONCAT('Job #', id)) AS title,
                    {$citySql} AS city
                FROM jobs
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $city = trim((string) ($row['city'] ?? ''));
        return $city !== '' ? ($title . ' · ' . $city) : $title;
    }

    public static function hasOverlapForEmployee(
        int $businessId,
        int $employeeId,
        string $startAt,
        ?string $endAt = null,
        ?int $excludeEntryId = null
    ): bool {
        if ($employeeId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return false;
        }
        if (!SchemaInspector::hasColumn('employee_time_entries', 'employee_id')
            || !SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return false;
        }

        $where = [];
        $where[] = 't.employee_id = :employee_id';
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $where[] = 't.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $where[] = 't.deleted_at IS NULL';
        }
        if (($excludeEntryId ?? 0) > 0) {
            $where[] = 't.id <> :exclude_entry_id';
        }

        $clockInExpr = 't.clock_in_at';
        $clockOutExpr = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $existingEndExpr = "COALESCE({$clockOutExpr}, NOW())";
        $where[] = "{$clockInExpr} < :new_end_effective";
        $where[] = "{$existingEndExpr} > :new_start";

        $sql = 'SELECT 1
                FROM employee_time_entries t
                WHERE ' . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'employee_id' => $employeeId,
            'new_start' => $startAt,
            'new_end_effective' => $endAt !== null && trim($endAt) !== ''
                ? $endAt
                : date('Y-m-d H:i:s'),
        ];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        if (($excludeEntryId ?? 0) > 0) {
            $params['exclude_entry_id'] = (int) $excludeEntryId;
        }
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public static function openEntryForEmployee(int $businessId, int $employeeId): ?array
    {
        if ($employeeId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return null;
        }

        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $isNonJobSql = SchemaInspector::hasColumn('employee_time_entries', 'is_non_job') ? 't.is_non_job' : '0';

        $jobTitleSql = "'Non-Job Time'";
        $joinSql = '';
        $estateSaleIdSql = SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id') ? 't.estate_sale_id' : 'NULL AS estate_sale_id';
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            $jobTitleField = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $joinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql = "LEFT JOIN jobs j ON j.id = t.job_id {$joinDeleted}";
            $jobTitleSql = "CASE WHEN COALESCE({$isNonJobSql}, 0) = 1 THEN 'Non-Job Time' WHEN t.job_id IS NOT NULL THEN COALESCE(NULLIF({$jobTitleField}, ''), CONCAT('Job #', j.id)) ELSE 'Non-Job Time' END";
        }
        if (
            SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id')
            && SchemaInspector::hasTable('estate_sales')
        ) {
            $estateSaleTitleField = SchemaInspector::hasColumn('estate_sales', 'title') ? 'es.title' : "CONCAT('Estate Sale #', es.id)";
            $estateJoinDeleted = SchemaInspector::hasColumn('estate_sales', 'deleted_at') ? 'AND es.deleted_at IS NULL' : '';
            $joinSql .= ($joinSql !== '' ? "\n" : '') . "LEFT JOIN estate_sales es ON es.id = t.estate_sale_id {$estateJoinDeleted}";
            $estateTitleSql = "COALESCE(NULLIF({$estateSaleTitleField}, ''), CONCAT('Estate Sale #', t.estate_sale_id))";
            $jobTitleSql = "CASE
                WHEN COALESCE({$isNonJobSql}, 0) = 1 THEN 'Non-Job Time'
                WHEN t.estate_sale_id IS NOT NULL THEN {$estateTitleSql}
                WHEN t.job_id IS NOT NULL THEN {$jobTitleSql}
                ELSE 'Non-Job Time'
            END";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'employee_id') ? 't.employee_id = :employee_id' : '1=1';
        $where[] = $clockOutSql . ' IS NULL';

        $notesSql = SchemaInspector::hasColumn('employee_time_entries', 'notes') ? 't.notes' : "''";

        $sql = "SELECT
                    t.id,
                    t.employee_id,
                    t.job_id,
                    {$estateSaleIdSql},
                    t.clock_in_at,
                    {$isNonJobSql} AS is_non_job,
                    {$notesSql} AS notes,
                    {$jobTitleSql} AS job_title
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY t.id DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'employee_id')) {
            $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function punchInNow(int $businessId, int $employeeId, ?int $jobId, bool $isNonJob, int $actorUserId, string $notes = '', ?int $estateSaleId = null): int
    {
        $clockInAt = date('Y-m-d H:i:s');
        $resolvedEstateSaleId = $estateSaleId !== null && $estateSaleId > 0 ? $estateSaleId : null;
        $resolvedJobId = $resolvedEstateSaleId !== null
            ? null
            : ($isNonJob ? null : ($jobId !== null && $jobId > 0 ? $jobId : null));

        return self::create($businessId, [
            'employee_id' => $employeeId,
            'job_id' => $resolvedJobId,
            'estate_sale_id' => $resolvedEstateSaleId,
            'is_non_job' => ($isNonJob && $resolvedEstateSaleId === null) ? 1 : 0,
            'clock_in_at' => $clockInAt,
            'clock_out_at' => null,
            'duration_minutes' => null,
            'clock_in_lat' => null,
            'clock_in_lng' => null,
            'clock_out_lat' => null,
            'clock_out_lng' => null,
            'notes' => trim($notes),
        ], $actorUserId);
    }

    public static function punchOutOpenEntry(int $businessId, int $employeeId, int $actorUserId): ?int
    {
        $openEntry = self::openEntryForEmployee($businessId, $employeeId);
        if ($openEntry === null) {
            return null;
        }

        $clockInAt = trim((string) ($openEntry['clock_in_at'] ?? ''));
        $clockOutAt = date('Y-m-d H:i:s');
        $durationMinutes = null;
        $clockInTs = strtotime($clockInAt);
        $clockOutTs = strtotime($clockOutAt);
        if ($clockInTs !== false && $clockOutTs !== false && $clockOutTs >= $clockInTs) {
            $durationMinutes = (int) floor(($clockOutTs - $clockInTs) / 60);
        }

        self::update($businessId, (int) ($openEntry['id'] ?? 0), [
            'employee_id' => (int) ($openEntry['employee_id'] ?? $employeeId),
            'job_id' => isset($openEntry['job_id']) ? (int) ($openEntry['job_id'] ?? 0) : null,
            'estate_sale_id' => isset($openEntry['estate_sale_id']) ? (int) ($openEntry['estate_sale_id'] ?? 0) : null,
            'is_non_job' => ((int) ($openEntry['is_non_job'] ?? 0)) === 1 ? 1 : 0,
            'clock_in_at' => $clockInAt,
            'clock_out_at' => $clockOutAt,
            'duration_minutes' => $durationMinutes,
            'clock_in_lat' => null,
            'clock_in_lng' => null,
            'clock_out_lat' => null,
            'clock_out_lng' => null,
            'notes' => trim((string) ($openEntry['notes'] ?? '')),
        ], $actorUserId);

        return (int) ($openEntry['id'] ?? 0);
    }

    /**
     * Close the open entry and immediately start a new one at the same timestamp (job switch).
     *
     * @return array{ok: true, closed_entry_id: int, new_entry_id: int}|array{ok: false, error: string, noop?: bool}
     */
    public static function punchSwitchJob(
        int $businessId,
        int $employeeId,
        int $actorUserId,
        ?int $newJobId,
        bool $newIsNonJob,
        string $newNotes
    ): array {
        $open = self::openEntryForEmployee($businessId, $employeeId);
        if ($open === null) {
            return ['ok' => false, 'error' => 'no_open'];
        }

        $tNotes = trim($newNotes);
        $oJob = (int) ($open['job_id'] ?? 0);
        $oNj = ((int) ($open['is_non_job'] ?? 0)) === 1;
        $oNotes = trim((string) ($open['notes'] ?? ''));
        $nJob = $newIsNonJob ? 0 : (int) ($newJobId ?? 0);

        if ($oJob === $nJob && $oNj === $newIsNonJob && $oNotes === $tNotes) {
            return ['ok' => false, 'error' => 'noop', 'noop' => true];
        }

        $clockAt = date('Y-m-d H:i:s');
        $openId = (int) ($open['id'] ?? 0);
        if ($openId <= 0) {
            return ['ok' => false, 'error' => 'no_open'];
        }

        $clockInAt = trim((string) ($open['clock_in_at'] ?? ''));
        $clockInTs = strtotime($clockInAt);
        $clockOutTs = strtotime($clockAt);
        $durationMinutes = null;
        if ($clockInTs !== false && $clockOutTs !== false && $clockOutTs >= $clockInTs) {
            $durationMinutes = (int) floor(($clockOutTs - $clockInTs) / 60);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::update($businessId, $openId, [
                'employee_id' => (int) ($open['employee_id'] ?? $employeeId),
                'job_id' => isset($open['job_id']) ? (int) ($open['job_id'] ?? 0) : null,
                'is_non_job' => $oNj ? 1 : 0,
                'clock_in_at' => $clockInAt,
                'clock_out_at' => $clockAt,
                'duration_minutes' => $durationMinutes,
                'clock_in_lat' => null,
                'clock_in_lng' => null,
                'clock_out_lat' => null,
                'clock_out_lng' => null,
                'notes' => $oNotes,
            ], $actorUserId);

            if (self::hasOverlapForEmployee($businessId, $employeeId, $clockAt, null, null)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'overlap'];
            }

            $newId = self::create($businessId, [
                'employee_id' => $employeeId,
                'job_id' => $newIsNonJob ? null : ($newJobId !== null && $newJobId > 0 ? $newJobId : null),
                'is_non_job' => $newIsNonJob ? 1 : 0,
                'clock_in_at' => $clockAt,
                'clock_out_at' => null,
                'duration_minutes' => null,
                'clock_in_lat' => null,
                'clock_in_lng' => null,
                'clock_out_lat' => null,
                'clock_out_lng' => null,
                'notes' => $tNotes,
            ], $actorUserId);

            if ($newId <= 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'create_failed'];
            }

            $pdo->commit();
            return ['ok' => true, 'closed_entry_id' => $openId, 'new_entry_id' => $newId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'exception'];
        }
    }

    public static function punchBoardEmployees(int $businessId, ?int $scopeEmployeeId = null): array
    {
        if (!SchemaInspector::hasTable('employees')) {
            return [];
        }

        $employeeNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.first_name, e.last_name" .
            (SchemaInspector::hasColumn('employees', 'suffix') ? ', e.suffix' : '') .
            ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
        $linkedUserNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''), CONCAT('User #', u.id))";

        $openScopeWhere = [];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $openScopeWhere[] = 'ot.business_id = :open_business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $openScopeWhere[] = 'ot.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at')) {
            $openScopeWhere[] = 'ot.clock_out_at IS NULL';
        }
        $openWhereSql = $openScopeWhere === [] ? '1=1' : implode(' AND ', $openScopeWhere);

        $jobTitleField = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $openNotesField = SchemaInspector::hasColumn('employee_time_entries', 'notes') ? 'open_entry.notes' : "''";

        $sql = "SELECT
                    e.id,
                    e.user_id,
                    {$employeeNameSql} AS employee_name,
                    {$linkedUserNameSql} AS linked_user_name,
                    u.email AS linked_user_email,
                    open_entry.id AS open_entry_id,
                    open_entry.clock_in_at AS open_clock_in_at,
                    open_entry.job_id AS open_job_id,
                    {$openNotesField} AS open_notes,
                    CASE
                        WHEN COALESCE(open_entry.is_non_job, 0) = 1 OR open_entry.job_id IS NULL THEN 'Non-Job Time'
                        ELSE COALESCE(NULLIF({$jobTitleField}, ''), CONCAT('Job #', j.id))
                    END AS open_job_title
                FROM employees e
                LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL
                LEFT JOIN (
                    SELECT ot.employee_id, MAX(ot.id) AS open_entry_id
                    FROM employee_time_entries ot
                    WHERE {$openWhereSql}
                    GROUP BY ot.employee_id
                ) open_lookup ON open_lookup.employee_id = e.id
                LEFT JOIN employee_time_entries open_entry ON open_entry.id = open_lookup.open_entry_id
                LEFT JOIN jobs j ON j.id = open_entry.job_id
                WHERE e.business_id = :business_id
                  AND e.deleted_at IS NULL
                  AND COALESCE(e.status, 'active') = 'active'";

        if (($scopeEmployeeId ?? 0) > 0) {
            $sql .= ' AND e.id = :scope_employee_id';
        }

        $sql .= " ORDER BY
                    CASE WHEN e.user_id IS NOT NULL AND e.user_id > 0 THEN 0 ELSE 1 END ASC,
                    COALESCE(NULLIF({$linkedUserNameSql}, ''), {$employeeNameSql}) ASC,
                    e.id ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':open_business_id', $businessId, \PDO::PARAM_INT);
        }
        if (($scopeEmployeeId ?? 0) > 0) {
            $stmt->bindValue(':scope_employee_id', (int) $scopeEmployeeId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function hasActiveEntryForJob(int $businessId, int $jobId, int $employeeId): bool
    {
        if ($jobId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return false;
        }

        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at')) {
            return false;
        }

        $where = [
            't.employee_id = :employee_id',
            't.clock_out_at IS NULL',
            't.job_id = :job_id',
        ];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $where[] = 't.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $where[] = 't.deleted_at IS NULL';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'is_non_job')) {
            $where[] = 'COALESCE(t.is_non_job, 0) = 0';
        }

        $sql = 'SELECT COUNT(*) FROM employee_time_entries t WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->bindValue(':job_id', $jobId, \PDO::PARAM_INT);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hasActiveEntryForEstateSale(int $businessId, int $estateSaleId, int $employeeId): bool
    {
        if ($estateSaleId <= 0 || $employeeId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return false;
        }

        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at')
            || !SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id')) {
            return false;
        }

        $where = [
            't.employee_id = :employee_id',
            't.clock_out_at IS NULL',
            't.estate_sale_id = :estate_sale_id',
        ];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $where[] = 't.business_id = :business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $where[] = 't.deleted_at IS NULL';
        }

        $sql = 'SELECT COUNT(*) FROM employee_time_entries t WHERE ' . implode(' AND ', $where);
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->bindValue(':estate_sale_id', $estateSaleId, \PDO::PARAM_INT);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function timeCardsEmployeeRollup(int $businessId, string $fromDate, string $toDate, ?int $scopeEmployeeId = null): array
    {
        if (!SchemaInspector::hasTable('employees') || !SchemaInspector::hasTable('employee_time_entries')) {
            return [];
        }

        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return [];
        }

        $durationExpr = self::sqlEffectiveDurationMinutes('t');
        $clockInSql = 't.clock_in_at';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

        $employeeNameParts = ['e.first_name', 'e.last_name'];
        if (SchemaInspector::hasColumn('employees', 'suffix')) {
            $employeeNameParts[] = 'e.suffix';
        }
        $employeeBaseNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $employeeNameParts) . ")), ''), NULLIF(e.email, ''), CONCAT('Employee #', e.id))";
        $employeeNameSql = $employeeBaseNameSql;
        if (SchemaInspector::hasTable('users')) {
            $linkedUserNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''))";
            $employeeNameSql = "COALESCE(NULLIF({$linkedUserNameSql}, ''), {$employeeBaseNameSql})";
        }

        $timeJoin = ['t.employee_id = e.id'];
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id') && SchemaInspector::hasColumn('employees', 'business_id')) {
            $timeJoin[] = 't.business_id = e.business_id';
        }
        if (SchemaInspector::hasColumn('employee_time_entries', 'deleted_at')) {
            $timeJoin[] = 't.deleted_at IS NULL';
        }
        $timeJoin[] = "DATE({$clockInSql}) >= :from_date";
        $timeJoin[] = "DATE({$clockInSql}) <= :to_date";

        $where = [];
        $where[] = SchemaInspector::hasColumn('employees', 'business_id') ? 'e.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('employees', 'deleted_at') ? 'e.deleted_at IS NULL' : '1=1';
        if (SchemaInspector::hasColumn('employees', 'status')) {
            $where[] = "COALESCE(e.status, 'active') = 'active'";
        }
        if (($scopeEmployeeId ?? 0) > 0) {
            $where[] = 'e.id = :scope_employee_id';
        }

        $userJoin = SchemaInspector::hasTable('users') ? ' LEFT JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL' : '';

        $sql = "SELECT
                    e.id AS employee_id,
                    {$employeeNameSql} AS employee_name,
                    {$hourlyRateSql} AS hourly_rate,
                    COUNT(t.id) AS entry_count,
                    COALESCE(SUM({$durationExpr}), 0) AS total_minutes,
                    ROUND(COALESCE(SUM(({$durationExpr} / 60) * {$hourlyRateSql}), 0), 2) AS payout_total,
                    SUM(CASE WHEN {$clockOutSql} IS NULL AND t.id IS NOT NULL THEN 1 ELSE 0 END) AS open_entries
                FROM employees e
                {$userJoin}
                LEFT JOIN employee_time_entries t ON " . implode(' AND ', $timeJoin) . '
                WHERE ' . implode(' AND ', $where) . "
                GROUP BY e.id, {$employeeNameSql}, {$hourlyRateSql}
                ORDER BY employee_name ASC, e.id ASC";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employees', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if (($scopeEmployeeId ?? 0) > 0) {
            $stmt->bindValue(':scope_employee_id', (int) $scopeEmployeeId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':from_date', $fromDate);
        $stmt->bindValue(':to_date', $toDate);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $minutes = (int) ($row['total_minutes'] ?? 0);
            $out[] = [
                'employee_id' => (int) ($row['employee_id'] ?? 0),
                'employee_name' => (string) ($row['employee_name'] ?? ''),
                'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
                'entry_count' => (int) ($row['entry_count'] ?? 0),
                'open_entries' => (int) ($row['open_entries'] ?? 0),
                'total_minutes' => $minutes,
                'total_hours' => round($minutes / 60, 2),
                'payout_total' => (float) ($row['payout_total'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function timeCardEntriesForEmployee(
        int $businessId,
        int $employeeId,
        string $fromDate,
        string $toDate,
        int $limit = 2000
    ): array {
        if ($employeeId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return [];
        }

        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return [];
        }

        $clockInSql = 't.clock_in_at';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $durationExpr = self::sqlEffectiveDurationMinutes('t');
        $isNonJobSql = SchemaInspector::hasColumn('employee_time_entries', 'is_non_job') ? 't.is_non_job' : '0';
        $notesSql = SchemaInspector::hasColumn('employee_time_entries', 'notes') ? 't.notes' : "''";
        $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

        $joinSql = ' LEFT JOIN employees e ON e.id = t.employee_id';
        if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $joinSql .= ' AND e.business_id = t.business_id';
        }
        if (SchemaInspector::hasColumn('employees', 'deleted_at')) {
            $joinSql .= ' AND e.deleted_at IS NULL';
        }

        $jobTitleSql = "'Non-Job Time'";
        if (SchemaInspector::hasTable('jobs') && SchemaInspector::hasColumn('employee_time_entries', 'job_id')) {
            $jobTitleField = SchemaInspector::hasColumn('jobs', 'title')
                ? 'j.title'
                : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
            $joinDeleted = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN jobs j ON j.id = t.job_id {$joinDeleted}";
            $jobTitleSql = "CASE WHEN COALESCE({$isNonJobSql}, 0) = 1 THEN 'Non-Job Time' WHEN t.job_id IS NOT NULL THEN COALESCE(NULLIF({$jobTitleField}, ''), CONCAT('Job #', j.id)) ELSE 'Non-Job Time' END";
        }
        if (
            SchemaInspector::hasColumn('employee_time_entries', 'estate_sale_id')
            && SchemaInspector::hasTable('estate_sales')
        ) {
            $estateSaleTitleField = SchemaInspector::hasColumn('estate_sales', 'title') ? 'es.title' : "CONCAT('Estate Sale #', es.id)";
            $estateJoinDeleted = SchemaInspector::hasColumn('estate_sales', 'deleted_at') ? 'AND es.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN estate_sales es ON es.id = t.estate_sale_id {$estateJoinDeleted}";
            $estateTitleSql = "COALESCE(NULLIF({$estateSaleTitleField}, ''), CONCAT('Estate Sale #', t.estate_sale_id))";
            $jobTitleSql = "CASE
                WHEN COALESCE({$isNonJobSql}, 0) = 1 THEN 'Non-Job Time'
                WHEN t.estate_sale_id IS NOT NULL THEN {$estateTitleSql}
                WHEN t.job_id IS NOT NULL THEN {$jobTitleSql}
                ELSE 'Non-Job Time'
            END";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'employee_id') ? 't.employee_id = :employee_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        $where[] = "DATE({$clockInSql}) >= :from_date";
        $where[] = "DATE({$clockInSql}) <= :to_date";

        $sql = "SELECT
                    t.id,
                    t.employee_id,
                    t.job_id,
                    {$clockInSql} AS clock_in_at,
                    {$clockOutSql} AS clock_out_at,
                    {$durationExpr} AS duration_minutes,
                    {$isNonJobSql} AS is_non_job,
                    {$notesSql} AS notes,
                    {$jobTitleSql} AS job_title,
                    {$hourlyRateSql} AS hourly_rate,
                    ROUND(({$durationExpr} / 60) * {$hourlyRateSql}, 2) AS payout_total
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$clockInSql} DESC, t.id DESC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $fromDate);
        $stmt->bindValue(':to_date', $toDate);
        $stmt->bindValue(':row_limit', max(1, min($limit, 5000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{entry_count: int, open_entries: int, total_minutes: int, total_hours: float, payout_total: float}
     */
    public static function timeCardTotalsForEmployee(int $businessId, int $employeeId, string $fromDate, string $toDate): array
    {
        $empty = [
            'entry_count' => 0,
            'open_entries' => 0,
            'total_minutes' => 0,
            'total_hours' => 0.0,
            'payout_total' => 0.0,
        ];

        if ($employeeId <= 0 || !SchemaInspector::hasTable('employee_time_entries')) {
            return $empty;
        }

        if (!SchemaInspector::hasColumn('employee_time_entries', 'clock_in_at')) {
            return $empty;
        }

        $durationExpr = self::sqlEffectiveDurationMinutes('t');
        $clockInSql = 't.clock_in_at';
        $clockOutSql = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : 'NULL';
        $hourlyRateSql = SchemaInspector::hasColumn('employees', 'hourly_rate') ? 'COALESCE(e.hourly_rate, 0)' : '0';

        $joinSql = ' LEFT JOIN employees e ON e.id = t.employee_id';
        if (SchemaInspector::hasColumn('employees', 'business_id') && SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $joinSql .= ' AND e.business_id = t.business_id';
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'employee_id') ? 't.employee_id = :employee_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        $where[] = "DATE({$clockInSql}) >= :from_date";
        $where[] = "DATE({$clockInSql}) <= :to_date";

        $sql = "SELECT
                    COUNT(*) AS entry_count,
                    SUM(CASE WHEN {$clockOutSql} IS NULL THEN 1 ELSE 0 END) AS open_entries,
                    COALESCE(SUM({$durationExpr}), 0) AS total_minutes,
                    ROUND(COALESCE(SUM(({$durationExpr} / 60) * {$hourlyRateSql}), 0), 2) AS payout_total
                FROM employee_time_entries t
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('employee_time_entries', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':employee_id', $employeeId, \PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $fromDate);
        $stmt->bindValue(':to_date', $toDate);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return $empty;
        }

        $minutes = (int) ($row['total_minutes'] ?? 0);

        return [
            'entry_count' => (int) ($row['entry_count'] ?? 0),
            'open_entries' => (int) ($row['open_entries'] ?? 0),
            'total_minutes' => $minutes,
            'total_hours' => round($minutes / 60, 2),
            'payout_total' => (float) ($row['payout_total'] ?? 0),
        ];
    }

    private static function sqlEffectiveDurationMinutes(string $alias = 't'): string
    {
        $clockIn = "{$alias}.clock_in_at";
        $clockOut = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at')
            ? "{$alias}.clock_out_at"
            : 'NULL';

        if (!SchemaInspector::hasColumn('employee_time_entries', 'duration_minutes')) {
            return "CASE
                WHEN {$clockOut} IS NOT NULL THEN GREATEST(TIMESTAMPDIFF(MINUTE, {$clockIn}, {$clockOut}), 0)
                ELSE GREATEST(TIMESTAMPDIFF(MINUTE, {$clockIn}, NOW()), 0)
            END";
        }

        return "CASE
            WHEN {$alias}.duration_minutes IS NOT NULL AND {$alias}.duration_minutes > 0 THEN {$alias}.duration_minutes
            WHEN {$clockOut} IS NOT NULL THEN GREATEST(TIMESTAMPDIFF(MINUTE, {$clockIn}, {$clockOut}), 0)
            ELSE GREATEST(TIMESTAMPDIFF(MINUTE, {$clockIn}, NOW()), 0)
        END";
    }
}
