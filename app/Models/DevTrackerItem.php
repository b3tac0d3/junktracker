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
        return ['pending_review', 'backlog', 'triage', 'in_progress', 'testing', 'done', 'wont_fix'];
    }

    /** @return array<int, string> */
    public static function devStatusOptions(): array
    {
        return ['backlog', 'triage', 'in_progress', 'testing', 'done', 'wont_fix'];
    }

    /** @return array<int, string> */
    public static function reviewStatusOptions(): array
    {
        return ['pending', 'accepted', 'rejected'];
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

    /** @return array<int, string> */
    public static function companySubmissionTypes(): array
    {
        return ['bug', 'update'];
    }

    public static function isCompanySubmissionType(string $type): bool
    {
        return in_array(strtolower(trim($type)), self::companySubmissionTypes(), true);
    }

    public static function submissionCreatedLogLabel(string $itemType): string
    {
        return strtolower(trim($itemType)) === 'update' ? 'Requested' : 'Reported';
    }

    public static function defaultAcceptStatusForSubmission(array $item): string
    {
        return strtolower(trim((string) ($item['item_type'] ?? ''))) === 'update' ? 'backlog' : 'triage';
    }

    public static function submissionReviewHeading(array $item): string
    {
        return strtolower(trim((string) ($item['item_type'] ?? ''))) === 'update'
            ? 'Company Update Request Review'
            : 'Company Bug Report Review';
    }

    /**
     * @return array<string, string>
     */
    public static function submissionLabels(string $type): array
    {
        if (strtolower(trim($type)) === 'update') {
            return [
                'section' => 'Update Requests',
                'section_singular' => 'Update Request',
                'index_desc' => 'Request product updates for dev review and future releases.',
                'create_title' => 'Request an Update',
                'create_desc' => 'Your request goes to the dev team for review before it is scheduled for a release.',
                'create_button' => 'Submit Request',
                'create_icon' => 'fa-wrench',
                'list_title' => 'Submitted Requests',
                'list_empty' => 'No update requests yet.',
                'notes_label' => 'Requested change',
                'notes_placeholder' => 'Describe the update you want in a future release — workflow, UI, reports, etc.',
                'notes_required' => 'Describe the requested update so devs can review it.',
                'submit_success' => 'Update request submitted for dev review.',
                'log_success' => 'Update added to the request log.',
                'pending_alert' => 'This request is waiting for dev review.',
                'accepted_alert' => 'Accepted by devs and queued for future release work',
                'rejected_alert' => 'This request was reviewed and not accepted for the roadmap.',
            ];
        }

        return [
            'section' => 'Bug Reports',
            'section_singular' => 'Bug Report',
            'index_desc' => 'Submit issues for dev review.',
            'create_title' => 'Report a Bug',
            'create_desc' => 'Your report goes to the dev team for review before it enters the bug tracker.',
            'create_button' => 'Submit for Review',
            'create_icon' => 'fa-bug',
            'list_title' => 'Submitted Reports',
            'list_empty' => 'No bug reports yet.',
            'notes_label' => 'Description',
            'notes_placeholder' => 'What happened, what you expected, and steps to reproduce...',
            'notes_required' => 'Describe the issue so devs can review it.',
            'submit_success' => 'Bug report submitted for dev review.',
            'log_success' => 'Update added to the bug log.',
            'pending_alert' => 'This report is waiting for dev review.',
            'accepted_alert' => 'Accepted by devs and tracked as bug',
            'rejected_alert' => 'This report was reviewed and not accepted as a bug.',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending_review' => 'Pending Review',
            default => ucwords(str_replace('_', ' ', strtolower(trim($status)))),
        };
    }

    public static function reviewStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'Awaiting Dev Review',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            default => ucwords(str_replace('_', ' ', strtolower(trim($status)))),
        };
    }

    public static function hasSubmissionColumns(): bool
    {
        return SchemaInspector::hasColumn('dev_tracker_items', 'business_id')
            && SchemaInspector::hasColumn('dev_tracker_items', 'review_status');
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

    /** @return array<int, string> */
    public static function indexStatusFilterOptions(): array
    {
        return ['pending_review', 'backlog', 'triage', 'in_progress', 'testing', 'done', 'wont_fix'];
    }

    /** @return array<int, string> */
    public static function defaultIndexStatusFilters(): array
    {
        return ['backlog', 'triage', 'in_progress', 'testing'];
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeIndexStatusFilters(mixed $input): array
    {
        if ($input === '__active__' || $input === 'active') {
            return self::defaultIndexStatusFilters();
        }

        if ($input === '__pending_review__' || $input === 'pending_review') {
            return ['pending_review'];
        }

        if ($input === 'all' || $input === '') {
            return [];
        }

        $values = [];
        if (is_string($input)) {
            $values = [$input];
        } elseif (is_array($input)) {
            $values = $input;
        }

        $allowed = self::indexStatusFilterOptions();
        $normalized = [];
        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }
            if ($value === 'active') {
                return self::defaultIndexStatusFilters();
            }
            if (in_array($value, $allowed, true)) {
                $normalized[$value] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @param array<int, string>|string $statusFilters
     * @return array<int, array<string, mixed>>
     */
    public static function indexList(
        string $search = '',
        array|string $statusFilters = [],
        string $type = '',
        string $priority = '',
        int $limit = 50,
        int $offset = 0
    ): array {
        if (!SchemaInspector::hasTable('dev_tracker_items')) {
            return [];
        }

        $query = trim($search);
        if (is_string($statusFilters)) {
            $statusFilters = self::normalizeIndexStatusFilters($statusFilters);
        }
        $type = strtolower(trim($type));
        $priority = strtolower(trim($priority));

        $where = [];
        $where[] = SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        self::appendIndexStatusFilterSql($statusFilters, $where);
        if ($type !== '') {
            $where[] = 'LOWER(item_type) = :item_type';
        }
        if ($priority !== '') {
            $where[] = 'LOWER(priority) = :priority';
        }
        $where[] = self::searchWhereSql();

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
        self::bindSearchParams($stmt, $query);
        self::bindIndexStatusFilterParams($stmt, $statusFilters);
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

    /**
     * @param array<int, string>|string $statusFilters
     */
    public static function indexCount(
        string $search = '',
        array|string $statusFilters = [],
        string $type = '',
        string $priority = ''
    ): int {
        if (!SchemaInspector::hasTable('dev_tracker_items')) {
            return 0;
        }

        $query = trim($search);
        if (is_string($statusFilters)) {
            $statusFilters = self::normalizeIndexStatusFilters($statusFilters);
        }
        $type = strtolower(trim($type));
        $priority = strtolower(trim($priority));

        $where = [];
        $where[] = SchemaInspector::hasColumn('dev_tracker_items', 'deleted_at') ? 'deleted_at IS NULL' : '1=1';
        self::appendIndexStatusFilterSql($statusFilters, $where);
        if ($type !== '') {
            $where[] = 'LOWER(item_type) = :item_type';
        }
        if ($priority !== '') {
            $where[] = 'LOWER(priority) = :priority';
        }
        $where[] = self::searchWhereSql();

        $sql = 'SELECT COUNT(*)
                FROM dev_tracker_items
                WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare($sql);
        self::bindSearchParams($stmt, $query);
        self::bindIndexStatusFilterParams($stmt, $statusFilters);
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

    public static function createSubmission(array $data, int $businessId, int $actorUserId, string $itemType = 'bug'): int
    {
        if (!SchemaInspector::hasTable('dev_tracker_items') || $businessId <= 0) {
            return 0;
        }

        $itemType = strtolower(trim($itemType));
        if (!self::isCompanySubmissionType($itemType)) {
            $itemType = 'bug';
        }

        $columns = [
            'item_type', 'title', 'notes', 'status', 'priority', 'area',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ];
        $values = [
            ':item_type', ':title', ':notes', ':status', ':priority', ':area',
            ':created_by', ':updated_by', 'NOW()', 'NOW()',
        ];
        $params = [
            'item_type' => $itemType,
            'title' => trim((string) ($data['title'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'status' => 'pending_review',
            'priority' => 'normal',
            'area' => trim((string) ($data['area'] ?? '')) !== '' ? trim((string) ($data['area'] ?? '')) : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];

        if (self::hasSubmissionColumns()) {
            $columns[] = 'business_id';
            $columns[] = 'review_status';
            $columns[] = 'submitted_by';
            $values[] = ':business_id';
            $values[] = ':review_status';
            $values[] = ':submitted_by';
            $params['business_id'] = $businessId;
            $params['review_status'] = 'pending';
            $params['submitted_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $sql = 'INSERT INTO dev_tracker_items (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public static function acceptSubmission(int $itemId, int $actorUserId, string $status = 'triage'): bool
    {
        if ($itemId <= 0 || !self::hasSubmissionColumns() || !self::isValidStatus($status) || $status === 'pending_review') {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE dev_tracker_items
             SET review_status = \'accepted\',
                 status = :status,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :item_id
               AND deleted_at IS NULL
               AND review_status = \'pending\'
             LIMIT 1'
        );
        $stmt->execute([
            'item_id' => $itemId,
            'status' => strtolower(trim($status)),
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function rejectSubmission(int $itemId, int $actorUserId): bool
    {
        if ($itemId <= 0 || !self::hasSubmissionColumns()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE dev_tracker_items
             SET review_status = \'rejected\',
                 status = \'wont_fix\',
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :item_id
               AND deleted_at IS NULL
               AND review_status = \'pending\'
             LIMIT 1'
        );
        $stmt->execute([
            'item_id' => $itemId,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function isPendingSubmission(array $item): bool
    {
        return strtolower(trim((string) ($item['review_status'] ?? ''))) === 'pending'
            || strtolower(trim((string) ($item['status'] ?? ''))) === 'pending_review';
    }

    public static function belongsToBusiness(array $item, int $businessId): bool
    {
        if ($businessId <= 0) {
            return false;
        }

        return (int) ($item['business_id'] ?? 0) === $businessId;
    }

    public static function indexCountForBusiness(int $businessId, string $search = '', string $itemType = ''): int
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('dev_tracker_items') || !self::hasSubmissionColumns()) {
            return 0;
        }

        $query = trim($search);
        $itemType = strtolower(trim($itemType));
        $typeSql = self::isCompanySubmissionType($itemType) ? ' AND LOWER(item_type) = :item_type' : '';
        $sql = 'SELECT COUNT(*)
                FROM dev_tracker_items
                WHERE deleted_at IS NULL
                  AND business_id = :business_id' . $typeSql . '
                  AND ' . self::searchWhereSql();

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        self::bindSearchParams($stmt, $query);
        if ($typeSql !== '') {
            $stmt->bindValue(':item_type', $itemType);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function indexListForBusiness(int $businessId, string $search = '', string $itemType = '', int $limit = 25, int $offset = 0): array
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('dev_tracker_items') || !self::hasSubmissionColumns()) {
            return [];
        }

        $query = trim($search);
        $itemType = strtolower(trim($itemType));
        $typeSql = self::isCompanySubmissionType($itemType) ? ' AND LOWER(item_type) = :item_type' : '';
        $sql = 'SELECT
                    id,
                    item_type,
                    title,
                    status,
                    priority,
                    area,
                    review_status,
                    business_id,
                    submitted_by,
                    updated_at,
                    created_at
                FROM dev_tracker_items
                WHERE deleted_at IS NULL
                  AND business_id = :business_id' . $typeSql . '
                  AND ' . self::searchWhereSql() . '
                ORDER BY updated_at DESC, id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        self::bindSearchParams($stmt, $query);
        if ($typeSql !== '') {
            $stmt->bindValue(':item_type', $itemType);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function companySubmissionsCountForBusiness(int $businessId, string $search = '', string $itemType = ''): int
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('dev_tracker_items') || !self::hasSubmissionColumns()) {
            return 0;
        }

        $query = trim($search);
        $itemType = strtolower(trim($itemType));
        $typeSql = self::isCompanySubmissionType($itemType)
            ? ' AND LOWER(item_type) = :item_type'
            : " AND LOWER(item_type) IN ('bug', 'update')";
        $sql = 'SELECT COUNT(*)
                FROM dev_tracker_items
                WHERE deleted_at IS NULL
                  AND business_id = :business_id' . $typeSql . '
                  AND ' . self::searchWhereSql();

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        self::bindSearchParams($stmt, $query);
        if (self::isCompanySubmissionType($itemType)) {
            $stmt->bindValue(':item_type', $itemType);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function companySubmissionsListForBusiness(int $businessId, string $search = '', string $itemType = '', int $limit = 25, int $offset = 0): array
    {
        if ($businessId <= 0 || !SchemaInspector::hasTable('dev_tracker_items') || !self::hasSubmissionColumns()) {
            return [];
        }

        $query = trim($search);
        $itemType = strtolower(trim($itemType));
        $typeSql = self::isCompanySubmissionType($itemType)
            ? ' AND LOWER(item_type) = :item_type'
            : " AND LOWER(item_type) IN ('bug', 'update')";
        $sql = 'SELECT
                    id,
                    item_type,
                    title,
                    status,
                    priority,
                    area,
                    review_status,
                    business_id,
                    submitted_by,
                    updated_at,
                    created_at
                FROM dev_tracker_items
                WHERE deleted_at IS NULL
                  AND business_id = :business_id' . $typeSql . '
                  AND ' . self::searchWhereSql() . '
                ORDER BY updated_at DESC, id DESC
                LIMIT :row_limit
                OFFSET :row_offset';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        self::bindSearchParams($stmt, $query);
        if (self::isCompanySubmissionType($itemType)) {
            $stmt->bindValue(':item_type', $itemType);
        }
        $stmt->bindValue(':row_limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->bindValue(':row_offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public static function pendingReviewCount(string $itemType = ''): int
    {
        $itemType = strtolower(trim($itemType));
        if ($itemType !== '' && !self::isCompanySubmissionType($itemType)) {
            return 0;
        }

        return self::indexCount('', '__pending_review__', $itemType);
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

    private static function appendIndexStatusFilterSql(array $statusFilters, array &$where): void
    {
        if ($statusFilters === []) {
            return;
        }

        $parts = [];
        foreach ($statusFilters as $index => $status) {
            $status = strtolower(trim($status));
            if ($status === '') {
                continue;
            }
            if ($status === 'pending_review' && self::hasSubmissionColumns()) {
                $parts[] = "(LOWER(status) = 'pending_review' AND review_status = 'pending')";
                continue;
            }
            $parts[] = 'LOWER(status) = :status_filter_' . (string) $index;
        }

        if ($parts === []) {
            return;
        }

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    private static function bindIndexStatusFilterParams(\PDOStatement $stmt, array $statusFilters): void
    {
        foreach ($statusFilters as $index => $status) {
            $status = strtolower(trim($status));
            if ($status === '' || ($status === 'pending_review' && self::hasSubmissionColumns())) {
                continue;
            }
            $stmt->bindValue(':status_filter_' . (string) $index, $status);
        }
    }

    private static function searchWhereSql(): string
    {
        return "(
            :query = ''
            OR title LIKE :query_like_title
            OR COALESCE(notes, '') LIKE :query_like_notes
            OR COALESCE(area, '') LIKE :query_like_area
            OR CAST(id AS CHAR) LIKE :query_like_id
        )";
    }

    private static function bindSearchParams(\PDOStatement $stmt, string $query): void
    {
        $queryLike = '%' . $query . '%';
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':query_like_title', $queryLike);
        $stmt->bindValue(':query_like_notes', $queryLike);
        $stmt->bindValue(':query_like_area', $queryLike);
        $stmt->bindValue(':query_like_id', $queryLike);
    }
}
