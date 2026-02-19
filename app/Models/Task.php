<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Task
{
    public const STATUSES = ['open', 'in_progress', 'closed'];
    public const LINK_TYPES = ['general', 'client', 'estate', 'company', 'employee', 'job', 'expense', 'prospect', 'sale'];
    public const AUTO_PROSPECT_FOLLOW_UP_TITLE = 'Prospect Follow-Up';

    public static function filter(array $filters): array
    {
        self::ensureTable();

        [$whereSql, $params] = self::buildWhere($filters);

        $assignedNameSql = self::userLabelSql('ua');

        $sql = 'SELECT t.id,
                       t.title,
                       t.link_type,
                       t.link_id,
                       t.assigned_user_id,
                       t.importance,
                       t.status,
                       t.due_at,
                       t.completed_at,
                       t.created_at,
                       t.updated_at,
                       t.deleted_at,
                       ' . $assignedNameSql . ' AS assigned_user_name
                FROM todos t
                LEFT JOIN users ua ON ua.id = t.assigned_user_id
                WHERE ' . $whereSql . '
                ORDER BY t.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return self::attachLinkData($rows);
    }

    public static function summary(array $filters): array
    {
        self::ensureTable();

        $summaryFilters = $filters;
        $summaryFilters['status'] = 'all';

        [$whereSql, $params] = self::buildWhere($summaryFilters);

        $sql = 'SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN t.status = \'open\' THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN t.status = \'in_progress\' THEN 1 ELSE 0 END) AS in_progress_count,
                    SUM(CASE WHEN t.status = \'closed\' THEN 1 ELSE 0 END) AS closed_count,
                    SUM(CASE
                        WHEN t.status <> \'closed\'
                         AND t.due_at IS NOT NULL
                         AND t.due_at < NOW() THEN 1
                        ELSE 0
                    END) AS overdue_count
                FROM todos t
                WHERE ' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: [
            'total_count' => 0,
            'open_count' => 0,
            'in_progress_count' => 0,
            'closed_count' => 0,
            'overdue_count' => 0,
        ];
    }

    public static function forLinkedRecord(string $linkType, int $linkId): array
    {
        self::ensureTable();

        $linkType = self::normalizeLinkType($linkType);
        if ($linkType === 'general' || $linkType === 'all' || $linkId <= 0) {
            return [];
        }

        $assignedNameSql = self::userLabelSql('ua');

        $sql = 'SELECT t.id,
                       t.title,
                       t.link_type,
                       t.link_id,
                       t.assigned_user_id,
                       t.importance,
                       t.status,
                       t.due_at,
                       t.completed_at,
                       t.created_at,
                       t.updated_at,
                       ' . $assignedNameSql . ' AS assigned_user_name
                FROM todos t
                LEFT JOIN users ua ON ua.id = t.assigned_user_id
                WHERE t.link_type = :link_type
                  AND t.link_id = :link_id
                  AND t.deleted_at IS NULL
                ORDER BY CASE WHEN t.status = \'closed\' THEN 1 ELSE 0 END,
                         COALESCE(t.due_at, \'9999-12-31 23:59:59\') ASC,
                         t.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'link_type' => $linkType,
            'link_id' => $linkId,
        ]);

        $rows = $stmt->fetchAll();
        return self::attachLinkData($rows);
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();

        $assignedNameSql = self::userLabelSql('ua');
        $createdNameSql = self::userLabelSql('uc');
        $updatedNameSql = self::userLabelSql('uu');
        $deletedNameSql = self::userLabelSql('ud');

        $hasCreatedBy = Schema::hasColumn('todos', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('todos', 'updated_by');
        $hasDeletedBy = Schema::hasColumn('todos', 'deleted_by');

        $createdBySelect = $hasCreatedBy ? 't.created_by' : 'NULL';
        $updatedBySelect = $hasUpdatedBy ? 't.updated_by' : 'NULL';
        $deletedBySelect = $hasDeletedBy ? 't.deleted_by' : 'NULL';

        $sql = 'SELECT t.id,
                       t.title,
                       t.body,
                       t.link_type,
                       t.link_id,
                       t.assigned_user_id,
                       t.importance,
                       t.status,
                       t.outcome,
                       t.due_at,
                       t.completed_at,
                       t.created_at,
                       ' . $createdBySelect . ' AS created_by,
                       t.updated_at,
                       ' . $updatedBySelect . ' AS updated_by,
                       t.deleted_at,
                       ' . $deletedBySelect . ' AS deleted_by,
                       ' . $assignedNameSql . ' AS assigned_user_name,
                       ' . $createdNameSql . ' AS created_by_name,
                       ' . $updatedNameSql . ' AS updated_by_name,
                       ' . $deletedNameSql . ' AS deleted_by_name
                FROM todos t
                LEFT JOIN users ua ON ua.id = t.assigned_user_id
                LEFT JOIN users uc ON uc.id = t.created_by
                LEFT JOIN users uu ON uu.id = t.updated_by
                LEFT JOIN users ud ON ud.id = t.deleted_by
                WHERE t.id = :id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch();
        if (!$task) {
            return null;
        }

        $link = self::resolveLink(
            (string) ($task['link_type'] ?? 'general'),
            isset($task['link_id']) ? (int) $task['link_id'] : null
        );
        $task['link_label'] = $link['label'] ?? '—';
        $task['link_url'] = $link['url'] ?? null;
        $task['link_type_label'] = $link['type_label'] ?? self::linkTypeLabel((string) ($task['link_type'] ?? 'general'));

        return $task;
    }

    public static function create(array $data, ?int $actorId = null): int
    {
        self::ensureTable();

        $columns = [
            'title',
            'body',
            'link_type',
            'link_id',
            'assigned_user_id',
            'importance',
            'status',
            'outcome',
            'due_at',
            'completed_at',
            'created_at',
            'updated_at',
        ];
        $values = [
            ':title',
            ':body',
            ':link_type',
            ':link_id',
            ':assigned_user_id',
            ':importance',
            ':status',
            ':outcome',
            ':due_at',
            ':completed_at',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'title' => $data['title'],
            'body' => $data['body'],
            'link_type' => $data['link_type'],
            'link_id' => $data['link_id'],
            'assigned_user_id' => $data['assigned_user_id'],
            'importance' => $data['importance'],
            'status' => $data['status'],
            'outcome' => $data['outcome'],
            'due_at' => $data['due_at'],
            'completed_at' => $data['completed_at'],
        ];

        if ($actorId !== null && Schema::hasColumn('todos', 'created_by')) {
            $columns[] = 'created_by';
            $values[] = ':created_by';
            $params['created_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('todos', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'INSERT INTO todos (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'title = :title',
            'body = :body',
            'link_type = :link_type',
            'link_id = :link_id',
            'assigned_user_id = :assigned_user_id',
            'importance = :importance',
            'status = :status',
            'outcome = :outcome',
            'due_at = :due_at',
            'completed_at = :completed_at',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'title' => $data['title'],
            'body' => $data['body'],
            'link_type' => $data['link_type'],
            'link_id' => $data['link_id'],
            'assigned_user_id' => $data['assigned_user_id'],
            'importance' => $data['importance'],
            'status' => $data['status'],
            'outcome' => $data['outcome'],
            'due_at' => $data['due_at'],
            'completed_at' => $data['completed_at'],
        ];

        if ($actorId !== null && Schema::hasColumn('todos', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE todos
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function setCompletion(int $id, bool $completed, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'status = :status',
            'updated_at = NOW()',
        ];
        $params = [
            'id' => $id,
            'status' => $completed ? 'closed' : 'open',
        ];

        if ($completed) {
            $sets[] = 'completed_at = COALESCE(completed_at, NOW())';
        } else {
            $sets[] = 'completed_at = NULL';
        }

        if ($actorId !== null && Schema::hasColumn('todos', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql = 'UPDATE todos
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function softDelete(int $id, ?int $actorId = null): void
    {
        self::ensureTable();

        $sets = [
            'deleted_at = COALESCE(deleted_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = ['id' => $id];

        if ($actorId !== null && Schema::hasColumn('todos', 'updated_by')) {
            $sets[] = 'updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }
        if ($actorId !== null && Schema::hasColumn('todos', 'deleted_by')) {
            $sets[] = 'deleted_by = COALESCE(deleted_by, :deleted_by)';
            $params['deleted_by'] = $actorId;
        }

        $sql = 'UPDATE todos
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function users(): array
    {
        $sql = 'SELECT u.id, ' . self::userLabelSql('u') . ' AS name
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

        $stmt = Database::connection()->query($sql);
        return $stmt->fetchAll();
    }

    public static function lookupLinks(string $type, string $term, int $limit = 10): array
    {
        self::ensureTable();

        $type = self::normalizeLinkType($type);
        $term = trim($term);
        $limit = max(1, min($limit, 25));

        if ($type === 'general' || $term === '') {
            return [];
        }

        return match ($type) {
            'client' => self::lookupClients($term, $limit),
            'estate' => self::lookupEstates($term, $limit),
            'company' => self::lookupCompanies($term, $limit),
            'employee' => self::lookupEmployees($term, $limit),
            'job' => self::lookupJobs($term, $limit),
            'expense' => self::lookupExpenses($term, $limit),
            'prospect' => self::lookupProspects($term, $limit),
            'sale' => self::lookupSales($term, $limit),
            default => [],
        };
    }

    public static function linkExists(string $type, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $type = self::normalizeLinkType($type);
        if ($type === 'general') {
            return false;
        }

        return self::resolveLink($type, $id) !== null;
    }

    public static function resolveLink(string $type, ?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $type = self::normalizeLinkType($type);
        if ($type === 'general') {
            return null;
        }

        $item = match ($type) {
            'client' => self::findClientById($id),
            'estate' => self::findEstateById($id),
            'company' => self::findCompanyById($id),
            'employee' => self::findEmployeeById($id),
            'job' => self::findJobById($id),
            'expense' => self::findExpenseById($id),
            'prospect' => self::findProspectById($id),
            'sale' => self::findSaleById($id),
            default => null,
        };

        if (!$item) {
            return [
                'label' => self::linkTypeLabel($type) . ' #' . $id,
                'url' => null,
                'type_label' => self::linkTypeLabel($type),
            ];
        }

        $url = match ($type) {
            'client' => '/clients/' . $id,
            'estate' => '/estates/' . $id,
            'company' => '/companies/' . $id,
            'job' => '/jobs/' . $id,
            'prospect' => '/prospects/' . $id,
            'sale' => '/sales/' . $id,
            default => null,
        };

        return [
            'label' => (string) ($item['label'] ?? (self::linkTypeLabel($type) . ' #' . $id)),
            'url' => $url,
            'type_label' => self::linkTypeLabel($type),
        ];
    }

    public static function linkTypeLabel(string $type): string
    {
        return match ($type) {
            'client' => 'Client',
            'estate' => 'Estate',
            'company' => 'Company',
            'employee' => 'Employee',
            'job' => 'Job',
            'expense' => 'Expense',
            'prospect' => 'Prospect',
            'sale' => 'Sale',
            default => 'General',
        };
    }

    public static function syncOpenProspectFollowUpTask(
        int $prospectId,
        int $assignedUserId,
        string $body,
        string $dueAt,
        int $importance,
        ?int $actorId = null
    ): bool {
        self::ensureTable();

        if ($prospectId <= 0 || $assignedUserId <= 0) {
            return false;
        }

        $sql = 'UPDATE todos
                SET body = :body,
                    due_at = :due_at,
                    importance = :importance,
                    updated_at = NOW()';
        $params = [
            'body' => $body,
            'due_at' => $dueAt,
            'importance' => max(1, min(5, $importance)),
            'link_type' => 'prospect',
            'link_id' => $prospectId,
            'assigned_user_id' => $assignedUserId,
            'title' => self::AUTO_PROSPECT_FOLLOW_UP_TITLE,
        ];

        if ($actorId !== null && Schema::hasColumn('todos', 'updated_by')) {
            $sql .= ', updated_by = :updated_by';
            $params['updated_by'] = $actorId;
        }

        $sql .= ' WHERE link_type = :link_type
                  AND link_id = :link_id
                  AND assigned_user_id = :assigned_user_id
                  AND title = :title
                  AND deleted_at IS NULL
                  AND status IN (\'open\', \'in_progress\')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    private static function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $recordStatus = (string) ($filters['record_status'] ?? 'active');
        if ($recordStatus === 'active') {
            $where[] = 't.deleted_at IS NULL';
        } elseif ($recordStatus === 'deleted') {
            $where[] = 't.deleted_at IS NOT NULL';
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(t.id AS CHAR) LIKE :q
                        OR t.title LIKE :q
                        OR t.body LIKE :q
                        OR t.outcome LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $status = (string) ($filters['status'] ?? 'all');
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }

        $importance = isset($filters['importance']) ? (int) $filters['importance'] : 0;
        if ($importance >= 1 && $importance <= 5) {
            $where[] = 't.importance = :importance';
            $params['importance'] = $importance;
        }

        $linkType = self::normalizeLinkType((string) ($filters['link_type'] ?? 'all'));
        if ($linkType !== 'all') {
            $where[] = 't.link_type = :link_type';
            $params['link_type'] = $linkType;
        }

        $assignedUserId = isset($filters['assigned_user_id']) ? (int) $filters['assigned_user_id'] : 0;
        if ($assignedUserId > 0) {
            $where[] = 't.assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = $assignedUserId;
        } else {
            $ownerScope = (string) ($filters['owner_scope'] ?? 'all');
            $currentUserId = isset($filters['current_user_id']) ? (int) $filters['current_user_id'] : 0;

            if ($ownerScope === 'mine' && $currentUserId > 0) {
                $where[] = 't.assigned_user_id = :owner_scope_user_id';
                $params['owner_scope_user_id'] = $currentUserId;
            } elseif ($ownerScope === 'team' && $currentUserId > 0) {
                $where[] = 't.assigned_user_id IS NOT NULL';
                $where[] = 't.assigned_user_id <> :owner_scope_user_id';
                $params['owner_scope_user_id'] = $currentUserId;
            }
        }

        $dueStart = trim((string) ($filters['due_start'] ?? ''));
        if ($dueStart !== '') {
            $where[] = 'DATE(t.due_at) >= :due_start';
            $params['due_start'] = $dueStart;
        }

        $dueEnd = trim((string) ($filters['due_end'] ?? ''));
        if ($dueEnd !== '') {
            $where[] = 'DATE(t.due_at) <= :due_end';
            $params['due_end'] = $dueEnd;
        }

        if (empty($where)) {
            $where[] = '1=1';
        }

        return [implode(' AND ', $where), $params];
    }

    private static function attachLinkData(array $rows): array
    {
        foreach ($rows as &$row) {
            $link = self::resolveLink(
                (string) ($row['link_type'] ?? 'general'),
                isset($row['link_id']) ? (int) $row['link_id'] : null
            );
            $row['link_label'] = $link['label'] ?? '—';
            $row['link_url'] = $link['url'] ?? null;
            $row['link_type_label'] = $link['type_label'] ?? self::linkTypeLabel((string) ($row['link_type'] ?? 'general'));
        }
        unset($row);

        return $rows;
    }

    private static function normalizeLinkType(string $type): string
    {
        $type = trim(strtolower($type));
        if ($type === 'all') {
            return 'all';
        }

        return in_array($type, self::LINK_TYPES, true) ? $type : 'general';
    }

    private static function userLabelSql(string $alias): string
    {
        return 'COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ' . $alias . '.first_name, ' . $alias . '.last_name)), \'\'), CONCAT(\'User #\', ' . $alias . '.id))';
    }

    private static function lookupClients(string $term, int $limit): array
    {
        $sql = 'SELECT c.id,
                       COALESCE(
                           NULLIF(c.business_name, \'\'),
                           NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                           CONCAT(\'Client #\', c.id)
                       ) AS label,
                       CONCAT_WS(\', \', NULLIF(c.city, \'\'), NULLIF(c.state, \'\')) AS meta
                FROM clients c
                WHERE c.deleted_at IS NULL
                  AND COALESCE(c.active, 1) = 1
                  AND (
                      c.business_name LIKE :term
                      OR c.first_name LIKE :term
                      OR c.last_name LIKE :term
                      OR c.email LIKE :term
                  )
                ORDER BY label ASC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function lookupEstates(string $term, int $limit): array
    {
        $sql = 'SELECT e.id,
                       e.name AS label,
                       CONCAT_WS(\', \', NULLIF(e.city, \'\'), NULLIF(e.state, \'\')) AS meta
                FROM estates e
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.active, 1) = 1
                  AND (
                      e.name LIKE :term
                      OR e.city LIKE :term
                      OR e.state LIKE :term
                  )
                ORDER BY e.name ASC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function lookupCompanies(string $term, int $limit): array
    {
        $sql = 'SELECT c.id,
                       c.name AS label,
                       CONCAT_WS(\', \', NULLIF(c.city, \'\'), NULLIF(c.state, \'\')) AS meta
                FROM companies c
                WHERE c.deleted_at IS NULL
                  AND COALESCE(c.active, 1) = 1
                  AND c.name LIKE :term
                ORDER BY c.name ASC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function lookupEmployees(string $term, int $limit): array
    {
        $sql = 'SELECT e.id,
                       NULLIF(TRIM(CONCAT_WS(\' \', e.first_name, e.last_name)), \'\') AS label,
                       e.phone AS meta
                FROM employees e
                WHERE (e.deleted_at IS NULL)
                  AND COALESCE(e.active, 1) = 1
                  AND (
                      e.first_name LIKE :term
                      OR e.last_name LIKE :term
                      OR e.email LIKE :term
                  )
                ORDER BY label ASC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function lookupJobs(string $term, int $limit): array
    {
        $sql = 'SELECT j.id,
                       COALESCE(NULLIF(j.name, \'\'), CONCAT(\'Job #\', j.id)) AS label,
                       CONCAT_WS(\', \', NULLIF(j.city, \'\'), NULLIF(j.state, \'\')) AS meta
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

    private static function lookupExpenses(string $term, int $limit): array
    {
        $sql = 'SELECT e.id,
                       CONCAT(
                           \'Expense #\', e.id,
                           \' • \', COALESCE(NULLIF(ec.name, \'\'), NULLIF(e.category, \'\'), \'Expense\'),
                           \' • $\', FORMAT(e.amount, 2)
                       ) AS label,
                       COALESCE(NULLIF(j.name, \'\'), CASE WHEN e.job_id IS NOT NULL THEN CONCAT(\'Job #\', e.job_id) ELSE \'Unlinked\' END, \'Unlinked\') AS meta
                FROM expenses e
                LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE e.deleted_at IS NULL
                  AND COALESCE(e.is_active, 1) = 1
                  AND (
                      CAST(e.id AS CHAR) LIKE :term
                      OR e.category LIKE :term
                      OR e.description LIKE :term
                      OR ec.name LIKE :term
                      OR j.name LIKE :term
                  )
                ORDER BY e.id DESC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function lookupProspects(string $term, int $limit): array
    {
        $sql = 'SELECT p.id,
                       CONCAT(
                           \'Prospect #\', p.id,
                           \' • \',
                           COALESCE(
                               NULLIF(c.business_name, \'\'),
                               NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                               CONCAT(\'Client #\', c.id)
                           )
                       ) AS label,
                       CONCAT(\'Status: \', p.status) AS meta
                FROM prospects p
                LEFT JOIN clients c ON c.id = p.client_id
                WHERE (
                    CAST(p.id AS CHAR) LIKE :term
                    OR p.note LIKE :term
                    OR p.next_step LIKE :term
                    OR c.first_name LIKE :term
                    OR c.last_name LIKE :term
                    OR c.business_name LIKE :term
                )
                ORDER BY p.id DESC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function lookupSales(string $term, int $limit): array
    {
        $sql = 'SELECT s.id,
                       COALESCE(NULLIF(s.name, \'\'), CONCAT(\'Sale #\', s.id)) AS label,
                       CONCAT(\'$\', FORMAT(s.gross_amount, 2), \' • \', s.type) AS meta
                FROM sales s
                WHERE s.deleted_at IS NULL
                  AND COALESCE(s.active, 1) = 1
                  AND (
                      CAST(s.id AS CHAR) LIKE :term
                      OR s.name LIKE :term
                      OR s.note LIKE :term
                      OR s.type LIKE :term
                  )
                ORDER BY s.id DESC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    private static function findClientById(int $id): ?array
    {
        $sql = 'SELECT COALESCE(
                        NULLIF(c.business_name, \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                        CONCAT(\'Client #\', c.id)
                    ) AS label
                FROM clients c
                WHERE c.id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findEstateById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT name AS label FROM estates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findCompanyById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT name AS label FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findEmployeeById(int $id): ?array
    {
        $sql = 'SELECT COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', first_name, last_name)), \'\'), CONCAT(\'Employee #\', id)) AS label
                FROM employees
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findJobById(int $id): ?array
    {
        $sql = 'SELECT COALESCE(NULLIF(name, \'\'), CONCAT(\'Job #\', id)) AS label
                FROM jobs
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findExpenseById(int $id): ?array
    {
        $sql = 'SELECT CONCAT(
                        \'Expense #\', e.id,
                        \' • \', COALESCE(NULLIF(ec.name, \'\'), NULLIF(e.category, \'\'), \'Expense\'),
                        \' • $\', FORMAT(e.amount, 2)
                    ) AS label
                FROM expenses e
                LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
                WHERE e.id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findProspectById(int $id): ?array
    {
        $sql = 'SELECT CONCAT(
                        \'Prospect #\', p.id,
                        \' • \',
                        COALESCE(
                            NULLIF(c.business_name, \'\'),
                            NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                            CONCAT(\'Client #\', c.id)
                        )
                    ) AS label
                FROM prospects p
                LEFT JOIN clients c ON c.id = p.client_id
                WHERE p.id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findSaleById(int $id): ?array
    {
        $sql = 'SELECT COALESCE(NULLIF(name, \'\'), CONCAT(\'Sale #\', id)) AS label
                FROM sales
                WHERE id = :id
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS todos (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                link_type VARCHAR(30) NOT NULL DEFAULT \'general\',
                link_id BIGINT UNSIGNED NULL,
                assigned_user_id BIGINT UNSIGNED NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                importance TINYINT UNSIGNED NOT NULL DEFAULT 3,
                status ENUM(\'open\',\'in_progress\',\'closed\') NOT NULL DEFAULT \'open\',
                outcome TEXT NULL,
                due_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_link (link_type, link_id),
                KEY idx_status (status),
                KEY idx_importance (importance),
                KEY idx_assigned_user (assigned_user_id),
                KEY idx_due_at (due_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $schema = (string) config('database.database', '');
        if ($schema !== '') {
            $stmt = Database::connection()->prepare(
                'SELECT DATA_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema
                   AND TABLE_NAME = \'todos\'
                   AND COLUMN_NAME = \'link_type\'
                 LIMIT 1'
            );
            $stmt->execute(['schema' => $schema]);
            $dataType = strtolower((string) $stmt->fetchColumn());
            if ($dataType !== '' && $dataType !== 'varchar') {
                Database::connection()->exec('ALTER TABLE todos MODIFY COLUMN link_type VARCHAR(30) NOT NULL DEFAULT \'general\'');
            }
        }

        $ensured = true;
    }
}
