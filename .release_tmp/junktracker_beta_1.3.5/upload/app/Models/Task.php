<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Task
{
    public static function userOptions(int $businessId, int $limit = 300): array
    {
        if (!SchemaInspector::hasTable('users') || !SchemaInspector::hasTable('business_user_memberships')) {
            return [];
        }

        $userDeletedWhere = SchemaInspector::hasColumn('users', 'deleted_at') ? 'u.deleted_at IS NULL' : '1=1';
        $userActiveWhere = SchemaInspector::hasColumn('users', 'is_active') ? 'COALESCE(u.is_active, 1) = 1' : '1=1';
        $membershipDeletedWhere = SchemaInspector::hasColumn('business_user_memberships', 'deleted_at') ? 'm.deleted_at IS NULL' : '1=1';
        $membershipActiveWhere = SchemaInspector::hasColumn('business_user_memberships', 'is_active') ? 'COALESCE(m.is_active, 1) = 1' : '1=1';

        $sql = "SELECT DISTINCT
                    u.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''), CONCAT('User #', u.id)) AS name
                FROM business_user_memberships m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.business_id = :business_id
                  AND {$membershipDeletedWhere}
                  AND {$membershipActiveWhere}
                  AND {$userDeletedWhere}
                  AND {$userActiveWhere}
                ORDER BY name ASC
                LIMIT :row_limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexList(
        int $businessId,
        string $search = '',
        string $status = '',
        int $limit = 25,
        int $offset = 0,
        string $sortBy = 'date',
        string $sortDir = 'desc'
    ): array
    {
        if (!SchemaInspector::hasTable('tasks')) {
            return [];
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 't.status' : "'open'";
        $prioritySql = SchemaInspector::hasColumn('tasks', 'priority') ? 't.priority' : 'NULL';
        $dueSql = SchemaInspector::hasColumn('tasks', 'due_at') ? 't.due_at' : 'NULL';
        $ownerIdSql = SchemaInspector::hasColumn('tasks', 'owner_user_id') ? 't.owner_user_id' : 'NULL';
        $completedAtSql = SchemaInspector::hasColumn('tasks', 'completed_at') ? 't.completed_at' : 'NULL';
        $completedBySql = SchemaInspector::hasColumn('tasks', 'completed_by') ? 't.completed_by' : 'NULL';

        $joinOwner = SchemaInspector::hasTable('users') && SchemaInspector::hasColumn('tasks', 'owner_user_id');
        $ownerNameSql = "'—'";
        $joinSql = '';
        if ($joinOwner) {
            $ownerNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''), CONCAT('User #', u.id))";
            $joinDeleted = SchemaInspector::hasColumn('users', 'deleted_at') ? 'AND u.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN users u ON u.id = t.owner_user_id {$joinDeleted}";
        }

        $joinCompletedBy = SchemaInspector::hasTable('users') && SchemaInspector::hasColumn('tasks', 'completed_by');
        $completedByNameSql = "''";
        if ($joinCompletedBy) {
            $completedByNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', done_u.first_name, done_u.last_name)), ''), NULLIF(done_u.email, ''), CONCAT('User #', done_u.id))";
            $joinDeleted = SchemaInspector::hasColumn('users', 'deleted_at') ? 'AND done_u.deleted_at IS NULL' : '';
            $joinSql .= " LEFT JOIN users done_u ON done_u.id = t.completed_by {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            $where[] = 'LOWER(' . $statusSql . ') = :status';
        }
        $where[] = "(
            :query = ''
            OR {$titleSql} LIKE :query_like_1
            OR {$ownerNameSql} LIKE :query_like_2
            OR COALESCE({$statusSql}, '') LIKE :query_like_3
            OR CAST(t.id AS CHAR) LIKE :query_like_4
        )";

        $sortBy = strtolower(trim($sortBy));
        $sortDir = strtolower(trim($sortDir)) === 'asc' ? 'ASC' : 'DESC';
        $createdDateExpr = SchemaInspector::hasColumn('tasks', 'created_at') ? 'DATE(t.created_at)' : 't.id';
        $dateExpr = "COALESCE(DATE({$dueSql}), {$createdDateExpr})";
        $sortMap = [
            'date' => "{$dateExpr} {$sortDir}, t.id {$sortDir}",
            'id' => "t.id {$sortDir}",
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['date'];

        $sql = "SELECT
                    t.id,
                    {$titleSql} AS title,
                    {$statusSql} AS status,
                    {$prioritySql} AS priority,
                    {$dueSql} AS due_at,
                    {$ownerIdSql} AS owner_user_id,
                    {$ownerNameSql} AS owner_name,
                    {$completedAtSql} AS completed_at,
                    {$completedBySql} AS completed_by,
                    {$completedByNameSql} AS completed_by_name
                FROM tasks t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBy}
                LIMIT :row_limit
                OFFSET :row_offset";

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->bindValue(':row_limit', max(1, min($limit, 1000)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function indexCount(int $businessId, string $search = '', string $status = ''): int
    {
        if (!SchemaInspector::hasTable('tasks')) {
            return 0;
        }

        $query = trim($search);
        $status = strtolower(trim($status));
        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 't.status' : "'open'";

        $joinOwner = SchemaInspector::hasTable('users') && SchemaInspector::hasColumn('tasks', 'owner_user_id');
        $ownerNameSql = "'—'";
        $joinSql = '';
        if ($joinOwner) {
            $ownerNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), NULLIF(u.email, ''), CONCAT('User #', u.id))";
            $joinDeleted = SchemaInspector::hasColumn('users', 'deleted_at') ? 'AND u.deleted_at IS NULL' : '';
            $joinSql = " LEFT JOIN users u ON u.id = t.owner_user_id {$joinDeleted}";
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        if ($status !== '') {
            $where[] = 'LOWER(' . $statusSql . ') = :status';
        }
        $where[] = "(
            :query = ''
            OR {$titleSql} LIKE :query_like_1
            OR {$ownerNameSql} LIKE :query_like_2
            OR COALESCE({$statusSql}, '') LIKE :query_like_3
            OR CAST(t.id AS CHAR) LIKE :query_like_4
        )";

        $sql = "SELECT COUNT(*)
                FROM tasks t
                {$joinSql}
                WHERE " . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        $queryLike = '%' . $query . '%';
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        if ($status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_1', $queryLike);
        $stmt->bindValue(':query_like_2', $queryLike);
        $stmt->bindValue(':query_like_3', $queryLike);
        $stmt->bindValue(':query_like_4', $queryLike);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForBusiness(int $businessId, int $taskId): ?array
    {
        if (!SchemaInspector::hasTable('tasks')) {
            return null;
        }

        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $bodySql = SchemaInspector::hasColumn('tasks', 'body')
            ? 't.body'
            : (SchemaInspector::hasColumn('tasks', 'notes') ? 't.notes' : 'NULL');
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 't.status' : "'open'";
        $prioritySql = SchemaInspector::hasColumn('tasks', 'priority') ? 't.priority' : 'NULL';
        $dueSql = SchemaInspector::hasColumn('tasks', 'due_at') ? 't.due_at' : 'NULL';
        $ownerIdSql = SchemaInspector::hasColumn('tasks', 'owner_user_id') ? 't.owner_user_id' : 'NULL';
        $assignedIdSql = SchemaInspector::hasColumn('tasks', 'assigned_user_id') ? 't.assigned_user_id' : 'NULL';
        $linkTypeSql = SchemaInspector::hasColumn('tasks', 'link_type') ? 't.link_type' : 'NULL';
        $linkIdSql = SchemaInspector::hasColumn('tasks', 'link_id') ? 't.link_id' : 'NULL';
        $completedAtSql = SchemaInspector::hasColumn('tasks', 'completed_at') ? 't.completed_at' : 'NULL';
        $completedByIdSql = SchemaInspector::hasColumn('tasks', 'completed_by') ? 't.completed_by' : 'NULL';

        $joinSql = '';
        $ownerNameSql = "'—'";
        $assignedNameSql = "'—'";
        $completedByNameSql = "''";
        if (SchemaInspector::hasTable('users')) {
            if (SchemaInspector::hasColumn('tasks', 'owner_user_id')) {
                $ownerNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', owner_u.first_name, owner_u.last_name)), ''), NULLIF(owner_u.email, ''), CONCAT('User #', owner_u.id))";
                $joinSql .= ' LEFT JOIN users owner_u ON owner_u.id = t.owner_user_id';
                if (SchemaInspector::hasColumn('users', 'deleted_at')) {
                    $joinSql .= ' AND owner_u.deleted_at IS NULL';
                }
            }
            if (SchemaInspector::hasColumn('tasks', 'assigned_user_id')) {
                $assignedNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', assigned_u.first_name, assigned_u.last_name)), ''), NULLIF(assigned_u.email, ''), CONCAT('User #', assigned_u.id))";
                $joinSql .= ' LEFT JOIN users assigned_u ON assigned_u.id = t.assigned_user_id';
                if (SchemaInspector::hasColumn('users', 'deleted_at')) {
                    $joinSql .= ' AND assigned_u.deleted_at IS NULL';
                }
            }
            if (SchemaInspector::hasColumn('tasks', 'completed_by')) {
                $completedByNameSql = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', completed_u.first_name, completed_u.last_name)), ''), NULLIF(completed_u.email, ''), CONCAT('User #', completed_u.id))";
                $joinSql .= ' LEFT JOIN users completed_u ON completed_u.id = t.completed_by';
                if (SchemaInspector::hasColumn('users', 'deleted_at')) {
                    $joinSql .= ' AND completed_u.deleted_at IS NULL';
                }
            }
        }

        $where = [];
        $where[] = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';
        $where[] = 't.id = :task_id';

        $sql = "SELECT
                    t.id,
                    {$titleSql} AS title,
                    {$bodySql} AS body,
                    {$statusSql} AS status,
                    {$prioritySql} AS priority,
                    {$dueSql} AS due_at,
                    {$ownerIdSql} AS owner_user_id,
                    {$assignedIdSql} AS assigned_user_id,
                    {$linkTypeSql} AS link_type,
                    {$linkIdSql} AS link_id,
                    {$completedAtSql} AS completed_at,
                    {$completedByIdSql} AS completed_by,
                    {$ownerNameSql} AS owner_name,
                    {$assignedNameSql} AS assigned_name,
                    {$completedByNameSql} AS completed_by_name
                FROM tasks t
                {$joinSql}
                WHERE " . implode(' AND ', $where) . '
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':task_id', $taskId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function statusSummary(int $businessId): array
    {
        $summary = [
            'open' => 0,
            'in_progress' => 0,
            'closed' => 0,
        ];

        if (!SchemaInspector::hasTable('tasks')) {
            return $summary;
        }

        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 'LOWER(t.status)' : "'open'";
        $where = [];
        $where[] = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $where[] = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT {$statusSql} AS status_key, COUNT(*) AS total
                FROM tasks t
                WHERE " . implode(' AND ', $where) . "
                GROUP BY {$statusSql}";

        $stmt = Database::connection()->prepare($sql);
        $params = [];
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $params['business_id'] = $businessId;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower((string) ($row['status_key'] ?? ''));
            if (array_key_exists($key, $summary)) {
                $summary[$key] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    public static function create(int $businessId, array $data, int $actorUserId): int
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'open')));
        $isClosed = $status === 'closed';

        $columns = [
            'business_id',
            'title',
            'body',
            'status',
            'owner_user_id',
            'assigned_user_id',
            'due_at',
            'priority',
            'link_type',
            'link_id',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':business_id',
            ':title',
            ':body',
            ':status',
            ':owner_user_id',
            ':assigned_user_id',
            ':due_at',
            ':priority',
            ':link_type',
            ':link_id',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];

        if (SchemaInspector::hasColumn('tasks', 'completed_at')) {
            $columns[] = 'completed_at';
            $values[] = ':completed_at';
        }
        if (SchemaInspector::hasColumn('tasks', 'completed_by')) {
            $columns[] = 'completed_by';
            $values[] = ':completed_by';
        }

        $sql = 'INSERT INTO tasks (
                    ' . implode(', ', $columns) . '
                ) VALUES (
                    ' . implode(', ', $values) . '
                )';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'business_id' => $businessId,
            'title' => trim((string) ($data['title'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'status' => $status,
            'owner_user_id' => (int) ($data['owner_user_id'] ?? 0),
            'assigned_user_id' => (isset($data['assigned_user_id']) && (int) $data['assigned_user_id'] > 0)
                ? (int) $data['assigned_user_id']
                : null,
            'due_at' => $data['due_at'] ?? null,
            'priority' => max(1, min(5, (int) ($data['priority'] ?? 3))),
            'link_type' => trim((string) ($data['link_type'] ?? '')),
            'link_id' => (isset($data['link_id']) && (int) $data['link_id'] > 0)
                ? (int) $data['link_id']
                : null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ];

        if (SchemaInspector::hasColumn('tasks', 'completed_at')) {
            $params['completed_at'] = $isClosed ? date('Y-m-d H:i:s') : null;
        }
        if (SchemaInspector::hasColumn('tasks', 'completed_by')) {
            $params['completed_by'] = $isClosed ? $actorUserId : null;
        }

        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $businessId, int $taskId, array $data, int $actorUserId): bool
    {
        $deletedWhere = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $status = strtolower(trim((string) ($data['status'] ?? 'open')));
        $isClosed = $status === 'closed';

        $set = [
            'title = :title',
            'body = :body',
            'status = :status',
            'owner_user_id = :owner_user_id',
            'assigned_user_id = :assigned_user_id',
            'due_at = :due_at',
            'priority = :priority',
            'link_type = :link_type',
            'link_id = :link_id',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ];
        if (SchemaInspector::hasColumn('tasks', 'completed_at')) {
            $set[] = $isClosed ? 'completed_at = COALESCE(completed_at, NOW())' : 'completed_at = NULL';
        }
        if (SchemaInspector::hasColumn('tasks', 'completed_by')) {
            $set[] = $isClosed ? 'completed_by = COALESCE(completed_by, :completed_by)' : 'completed_by = NULL';
        }

        $sql = 'UPDATE tasks
                SET ' . implode(', ', $set) . '
                WHERE id = :task_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'title' => trim((string) ($data['title'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'status' => $status,
            'owner_user_id' => (int) ($data['owner_user_id'] ?? 0),
            'assigned_user_id' => (isset($data['assigned_user_id']) && (int) $data['assigned_user_id'] > 0)
                ? (int) $data['assigned_user_id']
                : null,
            'due_at' => $data['due_at'] ?? null,
            'priority' => max(1, min(5, (int) ($data['priority'] ?? 3))),
            'link_type' => trim((string) ($data['link_type'] ?? '')),
            'link_id' => (isset($data['link_id']) && (int) $data['link_id'] > 0)
                ? (int) $data['link_id']
                : null,
            'updated_by' => $actorUserId,
            'task_id' => $taskId,
            'business_id' => $businessId,
        ];
        if (SchemaInspector::hasColumn('tasks', 'completed_by') && $isClosed) {
            $params['completed_by'] = $actorUserId;
        }

        return $stmt->execute($params);
    }

    public static function setCompletionStatus(int $businessId, int $taskId, bool $done, int $actorUserId): bool
    {
        return self::setStatus($businessId, $taskId, $done ? 'closed' : 'open', $actorUserId);
    }

    public static function setStatus(int $businessId, int $taskId, string $status, int $actorUserId): bool
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['open', 'in_progress', 'closed'], true)) {
            return false;
        }

        $deletedWhere = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 'AND deleted_at IS NULL' : '';
        $set = [
            'status = :status',
            'updated_by = :updated_by',
            'updated_at = NOW()',
        ];

        $isClosed = $status === 'closed';
        if (SchemaInspector::hasColumn('tasks', 'completed_at')) {
            $set[] = $isClosed ? 'completed_at = COALESCE(completed_at, NOW())' : 'completed_at = NULL';
        }
        if (SchemaInspector::hasColumn('tasks', 'completed_by')) {
            $set[] = $isClosed ? 'completed_by = COALESCE(completed_by, :completed_by)' : 'completed_by = NULL';
        }

        $sql = 'UPDATE tasks
                SET ' . implode(', ', $set) . '
                WHERE id = :task_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'status' => $status,
            'updated_by' => $actorUserId,
            'task_id' => $taskId,
            'business_id' => $businessId,
        ];
        if (SchemaInspector::hasColumn('tasks', 'completed_by') && $isClosed) {
            $params['completed_by'] = $actorUserId;
        }

        return $stmt->execute($params);
    }

    public static function setOwner(int $businessId, int $taskId, int $ownerUserId, int $actorUserId): bool
    {
        $deletedWhere = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 'AND deleted_at IS NULL' : '';

        $sql = 'UPDATE tasks
                SET owner_user_id = :owner_user_id,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :task_id
                  AND business_id = :business_id
                  ' . $deletedWhere;

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute([
            'owner_user_id' => $ownerUserId,
            'updated_by' => $actorUserId,
            'task_id' => $taskId,
            'business_id' => $businessId,
        ]);
    }
}
