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
                notification_key VARCHAR(190) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                dismissed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_notification_state (user_id, notification_key),
                KEY idx_user_notification_states_user (user_id),
                KEY idx_user_notification_states_read (user_id, is_read),
                KEY idx_user_notification_states_dismissed (user_id, dismissed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

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

    public static function listForUser(int $userId, string $scope = 'open'): array
    {
        self::ensureSchema();

        if ($userId <= 0) {
            return [];
        }

        $scope = self::normalizeScope($scope);
        $alerts = self::buildAlerts();
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

    public static function summaryForUser(int $userId): array
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

        $all = self::listForUser($userId, 'all');
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
        $summary = self::summaryForUser($userId);
        return (int) ($summary['unread'] ?? 0);
    }

    public static function markRead(int $userId, string $key, bool $read = true): void
    {
        self::ensureSchema();

        $normalizedKey = trim($key);
        if ($userId <= 0 || $normalizedKey === '') {
            return;
        }

        $sql = 'INSERT INTO user_notification_states
                    (user_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at)
                VALUES
                    (:user_id, :notification_key, :is_read, :read_at, NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    is_read = VALUES(is_read),
                    read_at = VALUES(read_at),
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'notification_key' => $normalizedKey,
            'is_read' => $read ? 1 : 0,
            'read_at' => $read ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public static function dismiss(int $userId, string $key, bool $dismiss = true): void
    {
        self::ensureSchema();

        $normalizedKey = trim($key);
        if ($userId <= 0 || $normalizedKey === '') {
            return;
        }

        $sql = 'INSERT INTO user_notification_states
                    (user_id, notification_key, is_read, read_at, dismissed_at, created_at, updated_at)
                VALUES
                    (:user_id, :notification_key, 1, :read_at, :dismissed_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    dismissed_at = VALUES(dismissed_at),
                    is_read = CASE WHEN VALUES(dismissed_at) IS NULL THEN is_read ELSE 1 END,
                    read_at = CASE WHEN VALUES(dismissed_at) IS NULL THEN read_at ELSE COALESCE(read_at, VALUES(read_at)) END,
                    updated_at = NOW()';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
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
                  AND notification_key IN (' . implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $state = [];
        foreach ($rows as $row) {
            $state[(string) ($row['notification_key'] ?? '')] = $row;
        }

        return $state;
    }

    private static function buildAlerts(): array
    {
        $alerts = [];
        foreach ([
            [self::class, 'overdueTasks'],
            [self::class, 'upcomingTasks'],
            [self::class, 'unpaidCompletedJobs'],
            [self::class, 'prospectFollowUpsDue'],
            [self::class, 'consignorPayoutsDue'],
        ] as $loader) {
            try {
                $items = call_user_func($loader);
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

    private static function overdueTasks(): array
    {
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
                  AND t.due_at IS NOT NULL
                  AND t.due_at < NOW()
                ORDER BY t.due_at ASC, t.importance DESC, t.id DESC
                LIMIT 40';

        $rows = Database::connection()->query($sql)->fetchAll();

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

    private static function upcomingTasks(): array
    {
        $sql = 'SELECT t.id,
                       t.title,
                       t.due_at,
                       t.importance,
                       t.link_type,
                       t.link_id
                FROM todos t
                WHERE t.deleted_at IS NULL
                  AND t.status IN ("open", "in_progress")
                  AND t.due_at IS NOT NULL
                  AND t.due_at >= NOW()
                  AND t.due_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)
                ORDER BY t.due_at ASC, t.importance DESC, t.id DESC
                LIMIT 40';

        $rows = Database::connection()->query($sql)->fetchAll();

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

    private static function unpaidCompletedJobs(): array
    {
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
                  AND COALESCE(j.paid, 0) = 0
                ORDER BY COALESCE(j.updated_at, j.end_date, j.created_at) DESC, j.id DESC
                LIMIT 30';

        $rows = Database::connection()->query($sql)->fetchAll();

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

    private static function prospectFollowUpsDue(): array
    {
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
                  AND p.follow_up_on IS NOT NULL
                  AND DATE(p.follow_up_on) <= CURDATE()
                ORDER BY p.follow_up_on ASC, p.priority_rating DESC, p.id DESC
                LIMIT 30';

        $rows = Database::connection()->query($sql)->fetchAll();

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

    private static function consignorPayoutsDue(): array
    {
        Consignor::ensureSchema();

        $sql = 'SELECT c.id,
                       c.business_name,
                       c.first_name,
                       c.last_name,
                       c.next_payment_due_date,
                       c.payment_schedule
                FROM consignors c
                WHERE c.deleted_at IS NULL
                  AND COALESCE(c.active, 1) = 1
                  AND c.next_payment_due_date IS NOT NULL
                  AND c.next_payment_due_date <= CURDATE()
                ORDER BY c.next_payment_due_date ASC, c.id DESC
                LIMIT 20';

        $rows = Database::connection()->query($sql)->fetchAll();

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
