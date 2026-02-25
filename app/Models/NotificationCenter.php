<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class NotificationCenter
{
    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS user_notification_states (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                notification_key VARCHAR(190) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                dismissed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_notification_state (user_id, business_id, notification_key),
                KEY idx_user_notification_states_user (user_id),
                KEY idx_user_notification_states_business (business_id),
                KEY idx_user_notification_states_read (user_id, is_read),
                KEY idx_user_notification_states_dismissed (user_id, dismissed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        if (!Schema::hasColumn('user_notification_states', 'business_id')) {
            try {
                Database::connection()->exec('ALTER TABLE user_notification_states ADD COLUMN business_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER user_id');
            } catch (\Throwable) {
                // Migration should handle this in strict environments.
            }
        }
        try {
            Database::connection()->exec('CREATE INDEX idx_user_notification_states_business ON user_notification_states (business_id)');
        } catch (\Throwable) {
            // index exists
        }
        try {
            Database::connection()->exec('UPDATE user_notification_states SET business_id = 1 WHERE business_id IS NULL OR business_id = 0');
        } catch (\Throwable) {
            // not required if column absent
        }

        $schema = trim((string) config('database.database', ''));
        if ($schema !== '') {
            self::ensureForeignKey(
                'fk_user_notification_states_user',
                'ALTER TABLE user_notification_states
                 ADD CONSTRAINT fk_user_notification_states_user
                 FOREIGN KEY (user_id) REFERENCES users(id)
                 ON DELETE CASCADE ON UPDATE CASCADE',
                $schema
            );
        }

        self::$schemaEnsured = true;
    }

    public static function listForUser(
        int $userId,
        string $scope = 'open',
        ?int $viewerUserId = null,
        ?int $viewerRole = null
    ): array
    {
        self::ensureSchema();

        if ($userId <= 0) {
            return [];
        }

        $viewerUserId = $viewerUserId ?? $userId;
        $viewerRole = $viewerRole ?? self::userRole($viewerUserId);
        if (!self::canViewUserNotifications($viewerUserId, $viewerRole, $userId)) {
            return [];
        }
        if (!self::userExistsInScope($userId)) {
            return [];
        }

        $subjectRole = self::userRole($userId);
        $scope = self::normalizeScope($scope);
        $alerts = self::buildAlertsForUser($userId, $subjectRole);
        if (empty($alerts)) {
            return [];
        }

        $states = self::statesForUser($userId, array_map(static fn (array $alert): string => (string) $alert['key'], $alerts));

        $rows = [];
        foreach ($alerts as $alert) {
            $key = (string) $alert['key'];
            $state = $states[$key] ?? null;
            $isRead = !empty($state['is_read']);
            $isDismissed = !empty($state['dismissed_at']);

            if ($scope === 'open' && $isDismissed) {
                continue;
            }
            if ($scope === 'unread' && ($isDismissed || $isRead)) {
                continue;
            }
            if ($scope === 'dismissed' && !$isDismissed) {
                continue;
            }

            $row = $alert;
            $row['is_read'] = $isRead;
            $row['read_at'] = $state['read_at'] ?? null;
            $row['is_dismissed'] = $isDismissed;
            $row['dismissed_at'] = $state['dismissed_at'] ?? null;
            $rows[] = $row;
        }

        return $rows;
    }

    public static function summaryForUser(
        int $userId,
        ?int $viewerUserId = null,
        ?int $viewerRole = null
    ): array
    {
        self::ensureSchema();

        if ($userId <= 0) {
            return [
                'total' => 0,
                'open' => 0,
                'unread' => 0,
                'dismissed' => 0,
            ];
        }

        $all = self::listForUser($userId, 'all', $viewerUserId, $viewerRole);
        $open = 0;
        $unread = 0;
        $dismissed = 0;

        foreach ($all as $row) {
            if (!empty($row['is_dismissed'])) {
                $dismissed++;
                continue;
            }
            $open++;
            if (empty($row['is_read'])) {
                $unread++;
            }
        }

        return [
            'total' => count($all),
            'open' => $open,
            'unread' => $unread,
            'dismissed' => $dismissed,
        ];
    }

    public static function unreadCount(int $userId): int
    {
        $summary = self::summaryForUser($userId, $userId, self::userRole($userId));
        return (int) ($summary['unread'] ?? 0);
    }

    public static function canViewSubject(int $viewerUserId, int $subjectUserId, ?int $viewerRole = null): bool
    {
        $resolvedRole = $viewerRole ?? self::userRole($viewerUserId);
        return self::canViewUserNotifications($viewerUserId, $resolvedRole, $subjectUserId);
    }

    public static function userOptionsForViewer(int $viewerUserId, ?int $viewerRole = null): array
    {
        self::ensureSchema();

        if ($viewerUserId <= 0) {
            return [];
        }

        $resolvedRole = $viewerRole ?? self::userRole($viewerUserId);
        if ($resolvedRole === 99 || $resolvedRole >= 2) {
            $sql = 'SELECT u.id,
                           COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), u.email, CONCAT("User #", u.id)) AS name
                    FROM users u';
            $where = [];
            $params = [];
            if (Schema::hasColumn('users', 'business_id')) {
                $where[] = 'u.business_id = :business_id';
                $params['business_id'] = self::currentBusinessId();
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $where[] = 'COALESCE(u.is_active, 1) = 1';
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $where[] = 'u.deleted_at IS NULL';
            }
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY name ASC, u.id ASC';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT u.id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), u.email, CONCAT("User #", u.id)) AS name
             FROM users u
             WHERE u.id = :id
               ' . (Schema::hasColumn('users', 'business_id') ? 'AND u.business_id = :business_id' : '') . '
             LIMIT 1'
        );
        $params = ['id' => $viewerUserId];
        if (Schema::hasColumn('users', 'business_id')) {
            $params['business_id'] = self::currentBusinessId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ? [$row] : [];
    }

    public static function markRead(int $userId, string $key, bool $read = true): void
    {
        self::ensureSchema();

        $normalizedKey = trim($key);
        if ($userId <= 0 || $normalizedKey === '') {
            return;
        }

        $sql = 'INSERT INTO user_notification_states
                    (user_id, business_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at)
                VALUES
                    (:user_id, :business_id, :notification_key, :is_read, :read_at, NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    is_read = VALUES(is_read),
                    read_at = VALUES(read_at),
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'business_id' => self::currentBusinessId(),
            'notification_key' => $normalizedKey,
            'is_read' => $read ? 1 : 0,
            'read_at' => $read ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public static function markReadMany(int $userId, array $keys): int
    {
        self::ensureSchema();

        if ($userId <= 0 || empty($keys)) {
            return 0;
        }

        $count = 0;
        $deduped = [];
        foreach ($keys as $key) {
            $normalized = trim((string) $key);
            if ($normalized !== '') {
                $deduped[$normalized] = true;
            }
        }

        foreach (array_keys($deduped) as $key) {
            self::markRead($userId, $key, true);
            $count++;
        }

        return $count;
    }

    public static function dismiss(int $userId, string $key, bool $dismiss = true): void
    {
        self::ensureSchema();

        $normalizedKey = trim($key);
        if ($userId <= 0 || $normalizedKey === '') {
            return;
        }

        $sql = 'INSERT INTO user_notification_states
                    (user_id, business_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at)
                VALUES
                    (:user_id, :business_id, :notification_key, 1, :read_at, :dismissed_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    dismissed_at = VALUES(dismissed_at),
                    is_read = CASE WHEN VALUES(dismissed_at) IS NULL THEN is_read ELSE 1 END,
                    read_at = CASE WHEN VALUES(dismissed_at) IS NULL THEN read_at ELSE COALESCE(read_at, VALUES(read_at)) END,
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'business_id' => self::currentBusinessId(),
            'notification_key' => $normalizedKey,
            'read_at' => date('Y-m-d H:i:s'),
            'dismissed_at' => $dismiss ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private static function normalizeScope(string $scope): string
    {
        $normalized = strtolower(trim($scope));
        return match ($normalized) {
            'open', 'unread', 'dismissed', 'all' => $normalized,
            default => 'open',
        };
    }

    private static function statesForUser(int $userId, array $keys): array
    {
        if ($userId <= 0 || empty($keys)) {
            return [];
        }

        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach (array_values(array_unique($keys)) as $index => $key) {
            $paramKey = 'key_' . $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $key;
        }

        $sql = 'SELECT notification_key, is_read, read_at, dismissed_at
                FROM user_notification_states
                WHERE user_id = :user_id
                  AND business_id = :business_id
                  AND notification_key IN (' . implode(', ', $placeholders) . ')';
        $params['business_id'] = self::currentBusinessId();

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $state = [];
        foreach ($rows as $row) {
            $state[(string) ($row['notification_key'] ?? '')] = $row;
        }

        return $state;
    }

    private static function buildAlertsForUser(int $subjectUserId, int $subjectRole): array
    {
        $alerts = [];
        $loaders = [
            static fn (): array => self::overdueTasks($subjectUserId),
            static fn (): array => self::upcomingTasks($subjectUserId),
            static fn (): array => self::unpaidCompletedJobs($subjectUserId, $subjectRole),
            static fn (): array => self::prospectFollowUpsDue($subjectUserId, $subjectRole),
            static fn (): array => self::consignorPayoutsDue($subjectUserId, $subjectRole),
        ];

        foreach ($loaders as $loader) {
            try {
                $items = $loader();
            } catch (\Throwable) {
                $items = [];
            }

            if (is_array($items) && !empty($items)) {
                $alerts = array_merge($alerts, $items);
            }
        }

        if (empty($alerts)) {
            return [];
        }

        $now = time();
        usort($alerts, static function (array $a, array $b) use ($now): int {
            $aDue = strtotime((string) ($a['due_at'] ?? '')) ?: PHP_INT_MAX;
            $bDue = strtotime((string) ($b['due_at'] ?? '')) ?: PHP_INT_MAX;

            $aOverdue = $aDue <= $now ? 1 : 0;
            $bOverdue = $bDue <= $now ? 1 : 0;
            if ($aOverdue !== $bOverdue) {
                return $bOverdue <=> $aOverdue;
            }

            $priorityCompare = (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0);
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            if ($aDue !== $bDue) {
                return $aDue <=> $bDue;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $alerts;
    }

    private static function overdueTasks(int $subjectUserId): array
    {
        if ($subjectUserId <= 0) {
            return [];
        }

        $ownershipWhere = self::taskOwnershipWhere('t');
        $sql = 'SELECT t.id,
                       t.title,
                       t.due_at,
                       t.importance,
                       t.link_type,
                       t.link_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), u.email, "Unassigned") AS assigned_user_name
                FROM todos t
                LEFT JOIN users u ON u.id = t.assigned_user_id
                WHERE t.deleted_at IS NULL
                  AND t.status IN ("open", "in_progress")
                  ' . (Schema::hasColumn('todos', 'business_id') ? 'AND t.business_id = :business_id
                  ' : '') . '
                  AND ' . $ownershipWhere['sql'] . '
                  AND t.due_at IS NOT NULL
                  AND t.due_at < NOW()
                ORDER BY t.due_at ASC, t.importance DESC, t.id DESC
                LIMIT 40';

        $params = $ownershipWhere['params'] + ['subject_user_id' => $subjectUserId];
        if (Schema::hasColumn('todos', 'business_id')) {
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $alerts = [];
        foreach ($rows as $row) {
            $taskId = (int) ($row['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Task #' . $taskId;
            }

            $link = Task::resolveLink((string) ($row['link_type'] ?? 'general'), isset($row['link_id']) ? (int) $row['link_id'] : null);
            $linkLabel = trim((string) ($link['label'] ?? ''));

            $alerts[] = [
                'key' => self::makeKey('task_overdue', $taskId, (string) ($row['due_at'] ?? '')),
                'type' => 'task_overdue',
                'severity' => 'danger',
                'priority' => 110 + (int) ($row['importance'] ?? 0),
                'title' => $title,
                'message' => $linkLabel !== ''
                    ? 'Overdue task linked to ' . $linkLabel
                    : 'Overdue task needs attention',
                'url' => '/tasks/' . $taskId,
                'due_at' => (string) ($row['due_at'] ?? ''),
            ];
        }

        return $alerts;
    }

    private static function upcomingTasks(int $subjectUserId): array
    {
        if ($subjectUserId <= 0) {
            return [];
        }

        $ownershipWhere = self::taskOwnershipWhere('t');
        $sql = 'SELECT t.id,
                       t.title,
                       t.due_at,
                       t.importance,
                       t.link_type,
                       t.link_id
                FROM todos t
                WHERE t.deleted_at IS NULL
                  AND t.status IN ("open", "in_progress")
                  ' . (Schema::hasColumn('todos', 'business_id') ? 'AND t.business_id = :business_id
                  ' : '') . '
                  AND ' . $ownershipWhere['sql'] . '
                  AND t.due_at IS NOT NULL
                  AND t.due_at >= NOW()
                  AND t.due_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)
                ORDER BY t.due_at ASC, t.importance DESC, t.id DESC
                LIMIT 40';

        $params = $ownershipWhere['params'] + ['subject_user_id' => $subjectUserId];
        if (Schema::hasColumn('todos', 'business_id')) {
            $params['business_id'] = self::currentBusinessId();
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $alerts = [];
        foreach ($rows as $row) {
            $taskId = (int) ($row['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = 'Task #' . $taskId;
            }

            $link = Task::resolveLink((string) ($row['link_type'] ?? 'general'), isset($row['link_id']) ? (int) $row['link_id'] : null);
            $linkLabel = trim((string) ($link['label'] ?? ''));

            $alerts[] = [
                'key' => self::makeKey('task_upcoming', $taskId, (string) ($row['due_at'] ?? '')),
                'type' => 'task_upcoming',
                'severity' => 'warning',
                'priority' => 70 + (int) ($row['importance'] ?? 0),
                'title' => $title,
                'message' => $linkLabel !== ''
                    ? 'Upcoming task linked to ' . $linkLabel
                    : 'Upcoming task due soon',
                'url' => '/tasks/' . $taskId,
                'due_at' => (string) ($row['due_at'] ?? ''),
            ];
        }

        return $alerts;
    }

    private static function unpaidCompletedJobs(int $subjectUserId, int $subjectRole): array
    {
        if ($subjectUserId <= 0) {
            return [];
        }

        $where = [];
        $params = [];
        if (Schema::hasColumn('jobs', 'business_id')) {
            $where[] = 'j.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }
        if (Schema::hasColumn('jobs', 'created_by')) {
            $where[] = 'j.created_by = :subject_user_id';
            $params['subject_user_id'] = $subjectUserId;
        } elseif ($subjectRole < 2 && $subjectRole !== 99) {
            return [];
        }

        $sql = 'SELECT j.id,
                       j.name,
                       j.total_billed,
                       j.paid,
                       j.updated_at,
                       COALESCE(
                           NULLIF(c.business_name, ""),
                           NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""),
                           CONCAT("Client #", c.id)
                       ) AS client_name
                FROM jobs j
                LEFT JOIN clients c ON c.id = j.client_id
                WHERE j.deleted_at IS NULL
                  AND COALESCE(j.active, 1) = 1
                  AND j.job_status = "complete"
                  AND COALESCE(j.total_billed, 0) > 0
                  AND COALESCE(j.paid, 0) = 0' . (!empty($where) ? ' AND ' . implode(' AND ', $where) : '') . '
                ORDER BY COALESCE(j.updated_at, j.end_date, j.created_at) DESC, j.id DESC
                LIMIT 30';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $alerts = [];
        foreach ($rows as $row) {
            $jobId = (int) ($row['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $jobName = trim((string) ($row['name'] ?? ''));
            if ($jobName === '') {
                $jobName = 'Job #' . $jobId;
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            $alerts[] = [
                'key' => self::makeKey('job_unpaid', $jobId, (string) ($row['updated_at'] ?? '')),
                'type' => 'job_unpaid',
                'severity' => 'warning',
                'priority' => 95,
                'title' => $jobName,
                'message' => $clientName !== ''
                    ? 'Completed job for ' . $clientName . ' is not marked paid.'
                    : 'Completed job is not marked paid.',
                'url' => '/jobs/' . $jobId,
                'due_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return $alerts;
    }

    private static function prospectFollowUpsDue(int $subjectUserId, int $subjectRole): array
    {
        if ($subjectUserId <= 0) {
            return [];
        }

        $where = [];
        $params = [];
        if (Schema::hasColumn('prospects', 'business_id')) {
            $where[] = 'p.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }
        if (Schema::hasColumn('prospects', 'created_by')) {
            $where[] = 'p.created_by = :subject_user_id';
            $params['subject_user_id'] = $subjectUserId;
        } elseif ($subjectRole < 2 && $subjectRole !== 99) {
            return [];
        }

        $sql = 'SELECT p.id,
                       p.follow_up_on,
                       p.next_step,
                       p.priority_rating,
                       COALESCE(
                           NULLIF(c.business_name, ""),
                           NULLIF(TRIM(CONCAT_WS(" ", c.first_name, c.last_name)), ""),
                           CONCAT("Client #", c.id)
                       ) AS client_name
                FROM prospects p
                LEFT JOIN clients c ON c.id = p.client_id
                WHERE p.deleted_at IS NULL
                  AND COALESCE(p.active, 1) = 1
                  AND p.status = "active"
                  ' . (!empty($where) ? 'AND ' . implode(' AND ', $where) . '
                  ' : '') . '
                  AND p.follow_up_on IS NOT NULL
                  AND DATE(p.follow_up_on) <= CURDATE()
                ORDER BY p.follow_up_on ASC, p.priority_rating DESC, p.id DESC
                LIMIT 30';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $alerts = [];
        foreach ($rows as $row) {
            $prospectId = (int) ($row['id'] ?? 0);
            if ($prospectId <= 0) {
                continue;
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            if ($clientName === '') {
                $clientName = 'Prospect #' . $prospectId;
            }

            $nextStep = trim((string) ($row['next_step'] ?? ''));
            if ($nextStep === '') {
                $nextStep = 'follow up';
            }
            $nextStep = str_replace('_', ' ', $nextStep);

            $alerts[] = [
                'key' => self::makeKey('prospect_follow_up', $prospectId, (string) ($row['follow_up_on'] ?? '')),
                'type' => 'prospect_follow_up',
                'severity' => 'info',
                'priority' => 85 + (int) ($row['priority_rating'] ?? 0),
                'title' => $clientName,
                'message' => 'Prospect follow-up due (' . $nextStep . ').',
                'url' => '/prospects/' . $prospectId,
                'due_at' => (string) ($row['follow_up_on'] ?? ''),
            ];
        }

        return $alerts;
    }

    private static function consignorPayoutsDue(int $subjectUserId, int $subjectRole): array
    {
        if ($subjectUserId <= 0) {
            return [];
        }

        Consignor::ensureSchema();

        $where = [];
        $params = [];
        if (Schema::hasColumn('consignors', 'business_id')) {
            $where[] = 'c.business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }
        if (Schema::hasColumn('consignors', 'created_by')) {
            $where[] = 'c.created_by = :subject_user_id';
            $params['subject_user_id'] = $subjectUserId;
        } elseif ($subjectRole < 2 && $subjectRole !== 99) {
            return [];
        }

        $sql = 'SELECT c.id,
                       c.business_name,
                       c.first_name,
                       c.last_name,
                       c.next_payment_due_date,
                       c.payment_schedule
                FROM consignors c
                WHERE c.deleted_at IS NULL
                  AND COALESCE(c.active, 1) = 1
                  ' . (!empty($where) ? 'AND ' . implode(' AND ', $where) . '
                  ' : '') . '
                  AND c.next_payment_due_date IS NOT NULL
                  AND c.next_payment_due_date <= CURDATE()
                ORDER BY c.next_payment_due_date ASC, c.id DESC
                LIMIT 20';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $alerts = [];
        foreach ($rows as $row) {
            $consignorId = (int) ($row['id'] ?? 0);
            if ($consignorId <= 0) {
                continue;
            }

            $name = trim((string) ($row['business_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            }
            if ($name === '') {
                $name = 'Consignor #' . $consignorId;
            }

            $alerts[] = [
                'key' => self::makeKey('consignor_payout_due', $consignorId, (string) ($row['next_payment_due_date'] ?? '')),
                'type' => 'consignor_payout_due',
                'severity' => 'primary',
                'priority' => 75,
                'title' => $name,
                'message' => 'Consignor payout due (' . (string) ($row['payment_schedule'] ?? 'schedule') . ').',
                'url' => '/consignors/' . $consignorId,
                'due_at' => (string) ($row['next_payment_due_date'] ?? ''),
            ];
        }

        return $alerts;
    }

    private static function makeKey(string $type, int $id, string $stamp = ''): string
    {
        return hash('sha256', $type . '|' . $id . '|' . trim($stamp));
    }

    private static function taskOwnershipWhere(string $alias): array
    {
        $subjectParam = ':subject_user_id';
        if (Schema::hasColumn('todos', 'created_by')) {
            return [
                'sql' => '((' . $alias . '.assigned_user_id = ' . $subjectParam . ')
                           OR (' . $alias . '.assigned_user_id IS NULL AND ' . $alias . '.created_by = ' . $subjectParam . '))',
                'params' => [],
            ];
        }

        return [
            'sql' => $alias . '.assigned_user_id = ' . $subjectParam,
            'params' => [],
        ];
    }

    private static function userRole(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $sql = 'SELECT role FROM users WHERE id = :id';
        $params = ['id' => $userId];
        if (Schema::hasColumn('users', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $role = $stmt->fetchColumn();

        return is_numeric((string) $role) ? (int) $role : 0;
    }

    private static function userExistsInScope(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = 'SELECT 1 FROM users WHERE id = :id';
        $params = ['id' => $userId];
        if (Schema::hasColumn('users', 'business_id')) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private static function canViewUserNotifications(int $viewerUserId, int $viewerRole, int $subjectUserId): bool
    {
        if ($viewerUserId <= 0 || $subjectUserId <= 0) {
            return false;
        }

        if ($viewerUserId === $subjectUserId) {
            return true;
        }

        return $viewerRole === 99 || $viewerRole >= 2;
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(0, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }

    private static function ensureForeignKey(string $constraintName, string $sql, string $schema): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :schema
               AND CONSTRAINT_NAME = :constraint
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $schema,
            'constraint' => $constraintName,
        ]);

        if ($stmt->fetch()) {
            return;
        }

        try {
            Database::connection()->exec($sql);
        } catch (\Throwable) {
            // Keep runtime stable when constraints cannot be applied on an existing environment.
        }
    }
}
