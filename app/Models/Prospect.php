<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Prospect
{
    private const REQUIRED_COLUMNS = [
        'priority_rating' => 'ALTER TABLE prospects ADD COLUMN priority_rating TINYINT UNSIGNED NULL AFTER status',
        'next_step' => 'ALTER TABLE prospects ADD COLUMN next_step VARCHAR(50) NULL AFTER priority_rating',
        'created_by' => 'ALTER TABLE prospects ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER created_at',
        'updated_by' => 'ALTER TABLE prospects ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER updated_at',
        'deleted_by' => 'ALTER TABLE prospects ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at',
    ];

    private static bool $ensured = false;
    private static array $columnMap = [];

    public static function filter(array $filters): array
    {
        self::ensureColumns();

        $sql = "SELECT p.id,
                       p.client_id,
                       p.contacted_on,
                       p.follow_up_on,
                       p.status,
                       p.priority_rating,
                       p.next_step,
                       p.note,
                       p.active,
                       p.deleted_at,
                       p.updated_at,
                       COALESCE(
                           NULLIF(c.business_name, ''),
                           NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''),
                           CONCAT('Client #', c.id)
                       ) AS client_name
                FROM prospects p
                LEFT JOIN clients c ON c.id = p.client_id";

        $where = [];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(p.id AS CHAR) LIKE :q
                        OR c.first_name LIKE :q
                        OR c.last_name LIKE :q
                        OR c.business_name LIKE :q
                        OR p.next_step LIKE :q
                        OR p.note LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $status = (string) ($filters['status'] ?? 'all');
        if (in_array($status, ['active', 'converted', 'closed'], true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $recordStatus = (string) ($filters['record_status'] ?? 'active');
        if ($recordStatus === 'active') {
            $where[] = '(p.deleted_at IS NULL AND COALESCE(p.active, 1) = 1)';
        } elseif ($recordStatus === 'inactive') {
            $where[] = '(p.deleted_at IS NOT NULL OR COALESCE(p.active, 1) = 0)';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        self::ensureColumns();

        $hasCreatedBy = self::hasColumn('created_by');
        $hasUpdatedBy = self::hasColumn('updated_by');
        $hasDeletedBy = self::hasColumn('deleted_by');

        $createdBySelect = $hasCreatedBy ? 'p.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 'p.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 'p.deleted_by' : 'NULL';

        $createdByNameSelect = $hasCreatedBy
            ? "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_created.first_name, u_created.last_name)), ''), CONCAT('User #', p.created_by))"
            : "'—'";
        $updatedByNameSelect = $hasUpdatedBy
            ? "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_updated.first_name, u_updated.last_name)), ''), CONCAT('User #', p.updated_by))"
            : "'—'";
        $deletedByNameSelect = $hasDeletedBy
            ? "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_deleted.first_name, u_deleted.last_name)), ''), CONCAT('User #', p.deleted_by))"
            : "'—'";

        $auditJoins = '';
        if ($hasCreatedBy) {
            $auditJoins .= ' LEFT JOIN users u_created ON u_created.id = p.created_by';
        }
        if ($hasUpdatedBy) {
            $auditJoins .= ' LEFT JOIN users u_updated ON u_updated.id = p.updated_by';
        }
        if ($hasDeletedBy) {
            $auditJoins .= ' LEFT JOIN users u_deleted ON u_deleted.id = p.deleted_by';
        }

        $clientNameSelect = "COALESCE(
                           NULLIF(c.business_name, ''),
                           NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''),
                           CONCAT('Client #', c.id)
                       )";

        $sql = 'SELECT p.id,
                       p.client_id,
                       p.contacted_on,
                       p.follow_up_on,
                       p.status,
                       p.priority_rating,
                       p.next_step,
                       p.note,
                       p.active,
                       p.created_at,
                       p.updated_at,
                       p.deleted_at,
                       ' . $createdBySelect . ' AS created_by,
                       ' . $createdByNameSelect . ' AS created_by_name,
                       ' . $updatedBySelect . ' AS updated_by,
                       ' . $updatedByNameSelect . ' AS updated_by_name,
                       ' . $deletedBySelect . ' AS deleted_by,
                       ' . $deletedByNameSelect . ' AS deleted_by_name,
                       ' . $clientNameSelect . ' AS client_name
                FROM prospects p
                LEFT JOIN clients c ON c.id = p.client_id'
                . $auditJoins . '
                WHERE p.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $prospect = $stmt->fetch();

        return $prospect ?: null;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureColumns();

        $columns = [
            'client_id',
            'contacted_on',
            'follow_up_on',
            'status',
            'priority_rating',
            'next_step',
            'note',
            'active',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':client_id',
            ':contacted_on',
            ':follow_up_on',
            ':status',
            ':priority_rating',
            ':next_step',
            ':note',
            '1',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'client_id' => $data['client_id'],
            'contacted_on' => $data['contacted_on'],
            'follow_up_on' => $data['follow_up_on'],
            'status' => $data['status'],
            'priority_rating' => $data['priority_rating'],
            'next_step' => $data['next_step'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && self::hasColumn('created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && self::hasColumn('updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO prospects (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureColumns();

        $sets = [
            'client_id = :client_id',
            'contacted_on = :contacted_on',
            'follow_up_on = :follow_up_on',
            'status = :status',
            'priority_rating = :priority_rating',
            'next_step = :next_step',
            'note = :note',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'client_id' => $data['client_id'],
            'contacted_on' => $data['contacted_on'],
            'follow_up_on' => $data['follow_up_on'],
            'status' => $data['status'],
            'priority_rating' => $data['priority_rating'],
            'next_step' => $data['next_step'],
            'note' => $data['note'],
        ];

        if ($actorId !== null && self::hasColumn('updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE prospects
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureColumns();

        $sets = [
            'active = 0',
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && self::hasColumn('updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && self::hasColumn('deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE prospects
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function convertToJob(int $id, int $jobId, ?int $actorId = null): void
    {
        self::ensureColumns();

        $sets = [
            'status = :status',
            'active = 0',
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'status' => 'converted',
        ];

        if ($actorId !== null && self::hasColumn('updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && self::hasColumn('deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        if (self::hasColumn('note')) {
            $sets[] = 'note = CONCAT_WS("\n", NULLIF(TRIM(COALESCE(note, "")), ""), :conversion_note)';
            $params['conversion_note'] = 'Converted to job #' . $jobId;
        }

        $sql = 'UPDATE prospects
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function clientExists(int $clientId): bool
    {
        $sql = 'SELECT id
                FROM clients
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND COALESCE(active, 1) = 1
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $clientId]);
        return (bool) $stmt->fetch();
    }

    private static function hasColumn(string $column): bool
    {
        return isset(self::$columnMap[$column]) && self::$columnMap[$column] === true;
    }

    private static function ensureColumns(): void
    {
        if (self::$ensured) {
            return;
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$ensured = true;
            return;
        }

        $fetchColumns = static function () use ($schema): array {
            $sql = "SELECT COLUMN_NAME
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = :schema
                      AND TABLE_NAME = 'prospects'";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['schema' => $schema]);
            $columns = [];
            foreach ($stmt->fetchAll() as $row) {
                $name = (string) ($row['COLUMN_NAME'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
            return $columns;
        };

        self::$columnMap = $fetchColumns();

        foreach (self::REQUIRED_COLUMNS as $column => $ddl) {
            if (isset(self::$columnMap[$column])) {
                continue;
            }
            Database::connection()->exec($ddl);
            self::$columnMap[$column] = true;
        }

        if (!isset(self::$columnMap['contacted_on'])) {
            Database::connection()->exec('ALTER TABLE prospects ADD COLUMN contacted_on DATE NULL AFTER client_id');
            self::$columnMap['contacted_on'] = true;
        }

        if (!isset(self::$columnMap['follow_up_on'])) {
            Database::connection()->exec('ALTER TABLE prospects ADD COLUMN follow_up_on DATE NULL AFTER contacted_on');
            self::$columnMap['follow_up_on'] = true;
        }

        self::$ensured = true;
    }
}
