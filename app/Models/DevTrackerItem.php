<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DevTrackerItem
{
    /** @return array<int, string> */
    public static function typeOptions(): array
    {
        return ['bug', 'update', 'feature', 'note'];
    }

    /** @return array<int, string> */
    public static function statusOptions(): array
    {
        return ['backlog', 'triage', 'in_progress', 'testing', 'done', 'wont_fix'];
    }

    /** @return array<int, string> */
    public static function priorityOptions(): array
    {
        return ['low', 'normal', 'high', 'urgent'];
    }

    public static function isValidType(string $type): bool
    {
        return in_array(strtolower(trim($type)), self::typeOptions(), true);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), self::statusOptions(), true);
    }

    public static function isValidPriority(string $priority): bool
    {
        return in_array(strtolower(trim($priority)), self::priorityOptions(), true);
    }

    public static function typeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'bug' => 'Bug',
            'update' => 'Update',
            'feature' => 'Feature',
            'note' => 'Note',
            default => ucfirst(trim($type)),
        };
    }

    public static function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', strtolower(trim($status))));
    }

    public static function priorityLabel(string $priority): string
    {
        return ucfirst(strtolower(trim($priority)));
    }

    /**
     * @return array<string, int>
     */
    public static function statusSummary(): array
    {
        if (!SchemaInspector::hasTable('dev_tracker_items')) {
            return [];
        }

        $statusSql = SchemaInspector::hasColumn('dev_tracker_items', 'status') ? 'status' : "'backlog'";
        $where = SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';

        $sql = "SELECT LOWER({$statusSql}) AS status_key, COUNT(*) AS row_count
                FROM dev_tracker_items
                WHERE {$where}
                GROUP BY LOWER({$statusSql})";

        $stmt = Database::connection()->query($sql);
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $summary = [];
        foreach (self::statusOptions() as $status) {
            $summary[$status] = 0;
        }
        $summary['all'] = 0;

        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower(trim((string) ($row['status_key'] ?? '')));
            $count = (int) ($row['row_count'] ?? 0);
            if ($key === '') {
                continue;
            }
            $summary[$key] = $count;
            $summary['all'] += $count;
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(
        string $search = '',
        string $status = '',
        string $type = '',
        string $priority = '',
        int $limit = 50,
        int $offset = 0
    ): array {
        if (!SchemaInspector::hasTable('dev_tracker_items')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $type = strtolower(trim($type));
        $priority = strtolower(trim($priority));

        $where = [];
        $where[] = SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        if ($status === '__active__') {
            $where[] = "LOWER(status) NOT IN ('done', 'wont_fix')";
        } elseif ($status !== '' && $status !== 'all') {
            $where[] = 'LOWER(status) = :status';
        }
        if ($type !== '') {
            $where[] = 'LOWER(item_type) = :item_type';
        }
        if ($priority !== '') {
            $where[] = 'LOWER(priority) = :priority';
        }
        $where[] = "(
            :query = ''
            OR title LIKE :query_like
            OR COALESCE(notes, '') LIKE :query_like
            OR COALESCE(area, '') LIKE :query_like
            OR CAST(id AS CHAR) LIKE :query_like
        )";

        $sql = 'SELECT
                    id,
                    item_type,
                    title,
                    status,
                    priority,
                    area,
                    updated_at,
                    created_at
                FROM dev_tracker_items
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY
                    CASE LOWER(status)
                        WHEN \'in_progress\' THEN 1
                        WHEN \'triage\' THEN 2
                        WHEN \'testing\' THEN 3
                        WHEN \'backlog\' THEN 4
                        WHEN \'done\' THEN 5
                        WHEN \'wont_fix\' THEN 6
                        ELSE 7
                    END,
                    CASE LOWER(priority)
                        WHEN \'urgent\' THEN 1
                        WHEN \'high\' THEN 2
                        WHEN \'normal\' THEN 3
                        ELSE 4
                    END,
                    updated_at DESC,
                    id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like', $queryLike);
        if ($status !== '' && $status !== 'all') {
            $stmt->bindValue(':status', $status);
        }
        if ($type !== '') {
            $stmt->bindValue(':item_type', $type);
        }
        if ($priority !== '') {
            $stmt->bindValue(':priority', $priority);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(
        string $search = '',
        string $status = '',
        string $type = '',
        string $priority = ''
    ): int {
        if (!SchemaInspector::hasTable('dev_tracker_items')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $type = strtolower(trim($type));
        $priority = strtolower(trim($priority));

        $where = [];
        $where[] = SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        if ($status === '__active__') {
            $where[] = "LOWER(status) NOT IN ('done', 'wont_fix')";
        } elseif ($status !== '' && $status !== 'all') {
            $where[] = 'LOWER(status) = :status';
        }
        if ($type !== '') {
            $where[] = 'LOWER(item_type) = :item_type';
        }
        if ($priority !== '') {
            $where[] = 'LOWER(priority) = :priority';
        }
        $where[] = "(
            :query = ''
            OR title LIKE :query_like
            OR COALESCE(notes, '') LIKE :query_like
            OR COALESCE(area, '') LIKE :query_like
            OR CAST(id AS CHAR) LIKE :query_like
        )";

        $sql = 'SELECT COUNT(*)
                FROM dev_tracker_items
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like', $queryLike);
        if ($status !== '' && $status !== 'all') {
            $stmt->bindValue(':status', $status);
        }
        if ($type !== '') {
            $stmt->bindValue(':item_type', $type);
        }
        if ($priority !== '') {
            $stmt->bindValue(':priority', $priority);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function find(int $itemId): ?array
    {
        if ($itemId <= 0 || !SchemaInspector::hasTable('dev_tracker_items')) {
            return null;
        }

        $where = ['id = :item_id'];
        $where[] = SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';

        $sql = 'SELECT *
                FROM dev_tracker_items
                WHERE ' . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':item_id', $itemId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public static function create(array $data, int $actorUserId): int
    {
        if (!SchemaInspector::hasTable('dev_tracker_items')) {
            return 0;
        }

        $sql = 'INSERT INTO dev_tracker_items (
                    item_type, title, notes, status, priority, area,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :item_type, :title, :notes, :status, :priority, :area,
                    :created_by, :updated_by, NOW(), NOW()
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'item_type' => strtolower(trim((string) ($data['item_type'] ?? 'bug'))),
            'title' => trim((string) ($data['title'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'status' => strtolower(trim((string) ($data['status'] ?? 'backlog'))),
            'priority' => strtolower(trim((string) ($data['priority'] ?? 'normal'))),
            'area' => trim((string) ($data['area'] ?? '')) !== '' ? trim((string) ($data['area'] ?? '')) : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $itemId, array $data, int $actorUserId): bool
    {
        if ($itemId <= 0 || !SchemaInspector::hasTable('dev_tracker_items')) {
            return false;
        }

        $sql = 'UPDATE dev_tracker_items
                SET item_type = :item_type,
                    title = :title,
                    notes = :notes,
                    status = :status,
                    priority = :priority,
                    area = :area,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :item_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'item_id' => $itemId,
            'item_type' => strtolower(trim((string) ($data['item_type'] ?? 'bug'))),
            'title' => trim((string) ($data['title'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'status' => strtolower(trim((string) ($data['status'] ?? 'backlog'))),
            'priority' => strtolower(trim((string) ($data['priority'] ?? 'normal'))),
            'area' => trim((string) ($data['area'] ?? '')) !== '' ? trim((string) ($data['area'] ?? '')) : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function updateStatus(int $itemId, string $status, int $actorUserId): bool
    {
        if ($itemId <= 0 || !self::isValidStatus($status) || !SchemaInspector::hasTable('dev_tracker_items')) {
            return false;
        }

        $sql = 'UPDATE dev_tracker_items
                SET status = :status,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :item_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'item_id' => $itemId,
            'status' => strtolower(trim($status)),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $itemId, int $actorUserId): bool
    {
        if ($itemId <= 0 || !SchemaInspector::hasTable('dev_tracker_items') || !SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at')) {
            return false;
        }

        $setParts = ['deleted_at = NOW()'];
        $params = ['item_id' => $itemId];
        if (SchemaInspector::hasColumn('dev_tracker_items', 'deleted_by')) {
            $setParts[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        if (SchemaInspector::hasColumn('dev_tracker_items', 'updated_by')) {
            $setParts[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $sql = 'UPDATE dev_tracker_items
                SET ' . implode(', ', $setParts) . '
                WHERE id = :item_id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
