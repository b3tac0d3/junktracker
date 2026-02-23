<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DevBug
{
    public const STATUSES = ['unresearched', 'confirmed', 'working', 'fixed_closed'];
    private const LEGACY_STATUS_MAP = [
        'new' => 'unresearched',
        'in_progress' => 'working',
        'fixed' => 'fixed_closed',
        'wont_fix' => 'fixed_closed',
    ];
    public const ENVIRONMENTS = ['local', 'live', 'both'];

    public static function filter(array $filters, int $limit = 0): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildWhere($filters);
        $limitSql = '';
        if ($limit > 0) {
            $limitSql = ' LIMIT ' . max(1, min($limit, 500));
        }

        $sql = 'SELECT b.id,
                       b.title,
                       b.details,
                       b.status,
                       b.severity,
                       b.environment,
                       b.module_key,
                       b.route_path,
                       b.reported_by,
                       b.assigned_user_id,
                       b.fixed_at,
                       b.fixed_by,
                       b.created_at,
                       b.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ur.first_name, ur.last_name)), \'\'), ur.email, CONCAT(\'User #\', b.reported_by)) AS reported_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)), \'\'), ua.email, CONCAT(\'User #\', b.assigned_user_id)) AS assigned_user_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uf.first_name, uf.last_name)), \'\'), uf.email, CONCAT(\'User #\', b.fixed_by)) AS fixed_by_name
                FROM dev_bugs b
                LEFT JOIN users ur ON ur.id = b.reported_by
                LEFT JOIN users ua ON ua.id = b.assigned_user_id
                LEFT JOIN users uf ON uf.id = b.fixed_by
                WHERE ' . $whereSql . '
                ORDER BY
                    CASE b.status
                        WHEN \'unresearched\' THEN 1
                        WHEN \'new\' THEN 1
                        WHEN \'confirmed\' THEN 2
                        WHEN \'working\' THEN 3
                        WHEN \'in_progress\' THEN 3
                        WHEN \'fixed_closed\' THEN 4
                        WHEN \'fixed\' THEN 4
                        WHEN \'wont_fix\' THEN 4
                        ELSE 5
                    END ASC,
                    b.severity DESC,
                    b.updated_at DESC,
                    b.id DESC'
                . $limitSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function recentOpen(int $limit = 8): array
    {
        return self::filter([
            'status' => 'open',
            'q' => '',
            'severity' => 0,
            'environment' => 'all',
            'assigned_user_id' => 0,
        ], $limit);
    }

    public static function summary(): array
    {
        self::ensureTable();

        $sql = 'SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN status IN (\'unresearched\', \'new\') THEN 1 ELSE 0 END), 0) AS unresearched_count,
                    COALESCE(SUM(CASE WHEN status = \'confirmed\' THEN 1 ELSE 0 END), 0) AS confirmed_count,
                    COALESCE(SUM(CASE WHEN status IN (\'working\', \'in_progress\') THEN 1 ELSE 0 END), 0) AS working_count,
                    COALESCE(SUM(CASE WHEN status IN (\'fixed_closed\', \'fixed\', \'wont_fix\') THEN 1 ELSE 0 END), 0) AS fixed_closed_count,
                    COALESCE(SUM(CASE WHEN status IN (\'unresearched\', \'new\', \'confirmed\', \'working\', \'in_progress\') THEN 1 ELSE 0 END), 0) AS open_count
                FROM dev_bugs
                WHERE deleted_at IS NULL';

        $row = Database::connection()->query($sql)->fetch();
        if (!$row) {
            return [
                'total_count' => 0,
                'unresearched_count' => 0,
                'confirmed_count' => 0,
                'working_count' => 0,
                'fixed_closed_count' => 0,
                'open_count' => 0,
                // backward compatibility with previous summary keys
                'new_count' => 0,
                'in_progress_count' => 0,
                'fixed_count' => 0,
                'wont_fix_count' => 0,
            ];
        }

        $row['new_count'] = (int) ($row['unresearched_count'] ?? 0);
        $row['in_progress_count'] = (int) ($row['working_count'] ?? 0);
        $row['fixed_count'] = (int) ($row['fixed_closed_count'] ?? 0);
        $row['wont_fix_count'] = 0;

        return $row;
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();

        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT b.id,
                       b.title,
                       b.details,
                       b.status,
                       b.severity,
                       b.environment,
                       b.module_key,
                       b.route_path,
                       b.reported_by,
                       b.assigned_user_id,
                       b.fixed_at,
                       b.fixed_by,
                       b.created_at,
                       b.updated_at,
                       b.deleted_at,
                       b.created_by,
                       b.updated_by,
                       b.deleted_by,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ur.first_name, ur.last_name)), \'\'), ur.email, CONCAT(\'User #\', b.reported_by)) AS reported_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)), \'\'), ua.email, CONCAT(\'User #\', b.assigned_user_id)) AS assigned_user_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uf.first_name, uf.last_name)), \'\'), uf.email, CONCAT(\'User #\', b.fixed_by)) AS fixed_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uc.first_name, uc.last_name)), \'\'), uc.email, CONCAT(\'User #\', b.created_by)) AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', uu.first_name, uu.last_name)), \'\'), uu.email, CONCAT(\'User #\', b.updated_by)) AS updated_by_name
                FROM dev_bugs b
                LEFT JOIN users ur ON ur.id = b.reported_by
                LEFT JOIN users ua ON ua.id = b.assigned_user_id
                LEFT JOIN users uf ON uf.id = b.fixed_by
                LEFT JOIN users uc ON uc.id = b.created_by
                LEFT JOIN users uu ON uu.id = b.updated_by
                WHERE b.id = :id
                  AND b.deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureTable();

        $status = self::normalizeStatus((string) ($data['status'] ?? 'unresearched'));
        $isFixed = $status === 'fixed_closed';

        $sql = 'INSERT INTO dev_bugs (
                    title,
                    details,
                    status,
                    severity,
                    environment,
                    module_key,
                    route_path,
                    reported_by,
                    assigned_user_id,
                    fixed_at,
                    fixed_by,
                    created_at,
                    updated_at,
                    created_by,
                    updated_by
                ) VALUES (
                    :title,
                    :details,
                    :status,
                    :severity,
                    :environment,
                    :module_key,
                    :route_path,
                    :reported_by,
                    :assigned_user_id,
                    :fixed_at,
                    :fixed_by,
                    NOW(),
                    NOW(),
                    :created_by,
                    :updated_by
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'title' => $data['title'],
            'details' => $data['details'],
            'status' => $status,
            'severity' => $data['severity'],
            'environment' => $data['environment'],
            'module_key' => $data['module_key'],
            'route_path' => $data['route_path'],
            'reported_by' => $data['reported_by'] ?? $actorId,
            'assigned_user_id' => $data['assigned_user_id'],
            'fixed_at' => $isFixed ? date('Y-m-d H:i:s') : null,
            'fixed_by' => $isFixed ? $actorId : null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureTable();

        $status = self::normalizeStatus((string) ($data['status'] ?? 'unresearched'));

        $sql = 'UPDATE dev_bugs
                SET title = :title,
                    details = :details,
                    status = :status,
                    severity = :severity,
                    environment = :environment,
                    module_key = :module_key,
                    route_path = :route_path,
                    assigned_user_id = :assigned_user_id,
                    fixed_at = CASE WHEN :status = \'fixed_closed\' THEN COALESCE(fixed_at, NOW()) ELSE NULL END,
                    fixed_by = CASE WHEN :status = \'fixed_closed\' THEN COALESCE(fixed_by, :actor_id) ELSE NULL END,
                    updated_at = NOW(),
                    updated_by = :actor_id
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'details' => $data['details'],
            'status' => $status,
            'severity' => $data['severity'],
            'environment' => $data['environment'],
            'module_key' => $data['module_key'],
            'route_path' => $data['route_path'],
            'assigned_user_id' => $data['assigned_user_id'],
            'actor_id' => $actorId,
        ]);
    }

    public static function setStatus(int $id, string $status, ?int $actorId = null): void
    {
        self::ensureTable();

        $status = self::normalizeStatus($status);
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }

        $sql = 'UPDATE dev_bugs
                SET status = :status,
                    fixed_at = CASE WHEN :status = \'fixed_closed\' THEN COALESCE(fixed_at, NOW()) ELSE NULL END,
                    fixed_by = CASE WHEN :status = \'fixed_closed\' THEN COALESCE(fixed_by, :actor_id) ELSE NULL END,
                    updated_at = NOW(),
                    updated_by = :actor_id
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'actor_id' => $actorId,
        ]);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureTable();

        $sql = 'UPDATE dev_bugs
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'deleted_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    public static function users(): array
    {
        $sql = 'SELECT u.id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email, CONCAT(\'User #\', u.id)) AS name
                FROM users u';

        $where = [];
        if (Schema::hasColumn('users', 'deleted_at')) {
            $where[] = 'u.deleted_at IS NULL';
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $where[] = 'COALESCE(u.is_active, 1) = 1';
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY name ASC';

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function notes(int $bugId): array
    {
        self::ensureTable();

        if ($bugId <= 0) {
            return [];
        }

        $sql = 'SELECT n.id,
                       n.bug_id,
                       n.note,
                       n.created_by,
                       n.created_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email, CONCAT(\'User #\', n.created_by)) AS created_by_name
                FROM dev_bug_notes n
                LEFT JOIN users u ON u.id = n.created_by
                WHERE n.bug_id = :bug_id
                ORDER BY n.created_at DESC, n.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['bug_id' => $bugId]);
        return $stmt->fetchAll();
    }

    public static function addNote(int $bugId, string $note, ?int $actorId = null): int
    {
        self::ensureTable();

        if ($bugId <= 0) {
            return 0;
        }

        $cleanNote = trim($note);
        if ($cleanNote === '') {
            return 0;
        }

        $sql = 'INSERT INTO dev_bug_notes (
                    bug_id,
                    note,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :bug_id,
                    :note,
                    :created_by,
                    NOW(),
                    NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'bug_id' => $bugId,
            'note' => $cleanNote,
            'created_by' => $actorId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private static function buildWhere(array $filters): array
    {
        $where = ['b.deleted_at IS NULL'];
        $params = [];

        $status = trim((string) ($filters['status'] ?? 'open'));
        if ($status === 'open') {
            $where[] = 'b.status IN (\'unresearched\', \'new\', \'confirmed\', \'working\', \'in_progress\')';
        } elseif ($status !== 'all') {
            $normalizedStatus = self::normalizeStatus($status);
            if ($normalizedStatus === 'unresearched') {
                $where[] = 'b.status IN (\'unresearched\', \'new\')';
            } elseif ($normalizedStatus === 'working') {
                $where[] = 'b.status IN (\'working\', \'in_progress\')';
            } elseif ($normalizedStatus === 'fixed_closed') {
                $where[] = 'b.status IN (\'fixed_closed\', \'fixed\', \'wont_fix\')';
            } elseif ($normalizedStatus === 'confirmed') {
                $where[] = 'b.status = \'confirmed\'';
            }
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(b.id AS CHAR) LIKE :q
                        OR b.title LIKE :q
                        OR b.details LIKE :q
                        OR b.module_key LIKE :q
                        OR b.route_path LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $severity = isset($filters['severity']) ? (int) $filters['severity'] : 0;
        if ($severity >= 1 && $severity <= 5) {
            $where[] = 'b.severity = :severity';
            $params['severity'] = $severity;
        }

        $environment = trim((string) ($filters['environment'] ?? 'all'));
        if ($environment === 'local') {
            $where[] = 'b.environment IN (\'local\', \'both\')';
        } elseif ($environment === 'live') {
            $where[] = 'b.environment IN (\'live\', \'both\')';
        } elseif ($environment === 'both') {
            $where[] = 'b.environment = \'both\'';
        }

        $assigned = isset($filters['assigned_user_id']) ? (int) $filters['assigned_user_id'] : 0;
        if ($assigned > 0) {
            $where[] = 'b.assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = $assigned;
        }

        return [implode(' AND ', $where), $params];
    }

    private static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS dev_bugs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                details TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'unresearched\',
                severity TINYINT UNSIGNED NOT NULL DEFAULT 3,
                environment VARCHAR(16) NOT NULL DEFAULT \'local\',
                module_key VARCHAR(80) NULL,
                route_path VARCHAR(255) NULL,
                reported_by BIGINT UNSIGNED NULL,
                assigned_user_id BIGINT UNSIGNED NULL,
                fixed_at DATETIME NULL,
                fixed_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_dev_bugs_status_updated (status, updated_at),
                KEY idx_dev_bugs_severity_status (severity, status),
                KEY idx_dev_bugs_environment_status (environment, status),
                KEY idx_dev_bugs_assigned (assigned_user_id),
                KEY idx_dev_bugs_reported (reported_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS dev_bug_notes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bug_id BIGINT UNSIGNED NOT NULL,
                note TEXT NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_dev_bug_notes_bug (bug_id),
                KEY idx_dev_bug_notes_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        try {
            Database::connection()->exec('ALTER TABLE dev_bugs ALTER status SET DEFAULT \'unresearched\'');
        } catch (\Throwable) {
            try {
                Database::connection()->exec('ALTER TABLE dev_bugs MODIFY status VARCHAR(32) NOT NULL DEFAULT \'unresearched\'');
            } catch (\Throwable) {
                // ignore
            }
        }

        try {
            Database::connection()->exec(
                'UPDATE dev_bugs
                 SET status = CASE
                     WHEN status = \'new\' THEN \'unresearched\'
                     WHEN status = \'in_progress\' THEN \'working\'
                     WHEN status IN (\'fixed\', \'wont_fix\') THEN \'fixed_closed\'
                     ELSE status
                 END
                 WHERE status IN (\'new\', \'in_progress\', \'fixed\', \'wont_fix\')'
            );
        } catch (\Throwable) {
            // ignore
        }

        $ensured = true;
    }

    private static function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (in_array($normalized, self::STATUSES, true)) {
            return $normalized;
        }

        return self::LEGACY_STATUS_MAP[$normalized] ?? 'unresearched';
    }
}
