<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class SiteAdminTicket
{
    public const STATUSES = ['unopened', 'pending', 'working', 'closed'];
    public const CATEGORIES = ['bug', 'question', 'suggestion', 'account', 'billing', 'other'];
    public const NOTE_VISIBILITY = ['customer', 'internal'];

    public static function ensureTables(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS site_admin_tickets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                business_id BIGINT UNSIGNED NULL,
                submitted_by_user_id BIGINT UNSIGNED NOT NULL,
                submitted_by_email VARCHAR(255) NOT NULL,
                category VARCHAR(32) NOT NULL DEFAULT \'question\',
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'unopened\',
                priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
                assigned_to_user_id BIGINT UNSIGNED NULL,
                opened_at DATETIME NULL,
                closed_at DATETIME NULL,
                last_customer_note_at DATETIME NULL,
                last_admin_note_at DATETIME NULL,
                converted_bug_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_site_admin_tickets_status (status),
                KEY idx_site_admin_tickets_priority_status (priority, status),
                KEY idx_site_admin_tickets_assigned (assigned_to_user_id),
                KEY idx_site_admin_tickets_submitter (submitted_by_user_id),
                KEY idx_site_admin_tickets_business (business_id),
                KEY idx_site_admin_tickets_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS site_admin_ticket_notes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                visibility VARCHAR(20) NOT NULL DEFAULT \'customer\',
                note TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_site_admin_ticket_notes_ticket (ticket_id),
                KEY idx_site_admin_ticket_notes_visibility (visibility),
                KEY idx_site_admin_ticket_notes_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ensured = true;
    }

    public static function create(array $data, int $submittedByUserId, ?int $actorId = null): int
    {
        self::ensureTables();

        $businessId = isset($data['business_id']) ? (int) $data['business_id'] : null;
        if ($businessId !== null && $businessId <= 0) {
            $businessId = null;
        }

        $category = self::normalizeCategory($data['category'] ?? null);
        $priority = self::normalizePriority($data['priority'] ?? null);

        $sql = 'INSERT INTO site_admin_tickets (
                    business_id,
                    submitted_by_user_id,
                    submitted_by_email,
                    category,
                    subject,
                    message,
                    status,
                    priority,
                    assigned_to_user_id,
                    opened_at,
                    closed_at,
                    last_customer_note_at,
                    last_admin_note_at,
                    converted_bug_id,
                    created_at,
                    updated_at,
                    created_by,
                    updated_by
                ) VALUES (
                    :business_id,
                    :submitted_by_user_id,
                    :submitted_by_email,
                    :category,
                    :subject,
                    :message,
                    \'unopened\',
                    :priority,
                    NULL,
                    NULL,
                    NULL,
                    NOW(),
                    NULL,
                    NULL,
                    NOW(),
                    NOW(),
                    :created_by,
                    :updated_by
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'business_id' => $businessId,
            'submitted_by_user_id' => $submittedByUserId,
            'submitted_by_email' => trim((string) ($data['submitted_by_email'] ?? '')),
            'category' => $category,
            'subject' => trim((string) ($data['subject'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
            'priority' => $priority,
            'created_by' => $actorId ?? $submittedByUserId,
            'updated_by' => $actorId ?? $submittedByUserId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function listForUser(int $userId, array $filters = []): array
    {
        self::ensureTables();
        if ($userId <= 0) {
            return [];
        }

        [$whereSql, $params] = self::buildUserWhere($userId, $filters);

        $sql = 'SELECT t.id,
                       t.business_id,
                       t.submitted_by_user_id,
                       t.submitted_by_email,
                       t.category,
                       t.subject,
                       t.message,
                       t.status,
                       t.priority,
                       t.assigned_to_user_id,
                       t.opened_at,
                       t.closed_at,
                       t.last_customer_note_at,
                       t.last_admin_note_at,
                       t.converted_bug_id,
                       t.created_at,
                       t.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)), \'\'), ua.email, CONCAT(\'User #\', t.assigned_to_user_id)) AS assigned_to_name,
                       COALESCE(NULLIF(b.name, \'\'), CONCAT(\'Business #\', t.business_id)) AS business_name
                FROM site_admin_tickets t
                LEFT JOIN users ua ON ua.id = t.assigned_to_user_id
                LEFT JOIN businesses b ON b.id = t.business_id
                WHERE ' . $whereSql . '
                ORDER BY
                    CASE t.status
                        WHEN \'unopened\' THEN 1
                        WHEN \'working\' THEN 2
                        WHEN \'pending\' THEN 3
                        ELSE 4
                    END,
                    t.priority DESC,
                    t.updated_at DESC,
                    t.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function adminQueue(array $filters = []): array
    {
        self::ensureTables();

        [$whereSql, $params] = self::buildAdminWhere($filters);

        $sql = 'SELECT t.id,
                       t.business_id,
                       t.submitted_by_user_id,
                       t.submitted_by_email,
                       t.category,
                       t.subject,
                       t.message,
                       t.status,
                       t.priority,
                       t.assigned_to_user_id,
                       t.opened_at,
                       t.closed_at,
                       t.last_customer_note_at,
                       t.last_admin_note_at,
                       t.converted_bug_id,
                       t.created_at,
                       t.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', us.first_name, us.last_name)), \'\'), us.email, CONCAT(\'User #\', t.submitted_by_user_id)) AS submitted_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)), \'\'), ua.email, CONCAT(\'User #\', t.assigned_to_user_id)) AS assigned_to_name,
                       COALESCE(NULLIF(b.name, \'\'), CONCAT(\'Business #\', t.business_id)) AS business_name,
                       (SELECT COUNT(*) FROM site_admin_ticket_notes n WHERE n.ticket_id = t.id) AS note_count
                FROM site_admin_tickets t
                LEFT JOIN users us ON us.id = t.submitted_by_user_id
                LEFT JOIN users ua ON ua.id = t.assigned_to_user_id
                LEFT JOIN businesses b ON b.id = t.business_id
                WHERE ' . $whereSql . '
                ORDER BY
                    CASE t.status
                        WHEN \'unopened\' THEN 1
                        WHEN \'working\' THEN 2
                        WHEN \'pending\' THEN 3
                        ELSE 4
                    END,
                    t.priority DESC,
                    t.updated_at DESC,
                    t.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function summary(array $filters = []): array
    {
        self::ensureTables();
        [$whereSql, $params] = self::buildAdminWhere($filters);

        $sql = 'SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN t.status = \'unopened\' THEN 1 ELSE 0 END), 0) AS unopened_count,
                    COALESCE(SUM(CASE WHEN t.status = \'pending\' THEN 1 ELSE 0 END), 0) AS pending_count,
                    COALESCE(SUM(CASE WHEN t.status = \'working\' THEN 1 ELSE 0 END), 0) AS working_count,
                    COALESCE(SUM(CASE WHEN t.status = \'closed\' THEN 1 ELSE 0 END), 0) AS closed_count
                FROM site_admin_tickets t
                WHERE ' . $whereSql;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: [
            'total_count' => 0,
            'unopened_count' => 0,
            'pending_count' => 0,
            'working_count' => 0,
            'closed_count' => 0,
        ];
    }

    public static function adminUnreadCount(): int
    {
        self::ensureTables();

        $sql = 'SELECT COUNT(*)
                FROM site_admin_tickets t
                WHERE t.status = \'unopened\'
                   OR (
                        t.status IN (\'pending\', \'working\')
                        AND t.last_customer_note_at IS NOT NULL
                        AND (t.last_admin_note_at IS NULL OR t.last_customer_note_at > t.last_admin_note_at)
                   )';

        $count = Database::connection()->query($sql)->fetchColumn();
        return is_numeric((string) $count) ? max(0, (int) $count) : 0;
    }

    public static function findByIdForUser(int $id, int $userId): ?array
    {
        self::ensureTables();
        if ($id <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT t.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', us.first_name, us.last_name)), \'\'), us.email, CONCAT(\'User #\', t.submitted_by_user_id)) AS submitted_by_name,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)), \'\'), ua.email, CONCAT(\'User #\', t.assigned_to_user_id)) AS assigned_to_name,
                    COALESCE(NULLIF(b.name, \'\'), CONCAT(\'Business #\', t.business_id)) AS business_name
             FROM site_admin_tickets t
             LEFT JOIN users us ON us.id = t.submitted_by_user_id
             LEFT JOIN users ua ON ua.id = t.assigned_to_user_id
             LEFT JOIN businesses b ON b.id = t.business_id
             WHERE t.id = :id
               AND t.submitted_by_user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByIdForAdmin(int $id): ?array
    {
        self::ensureTables();
        if ($id <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT t.*,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', us.first_name, us.last_name)), \'\'), us.email, CONCAT(\'User #\', t.submitted_by_user_id)) AS submitted_by_name,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)), \'\'), ua.email, CONCAT(\'User #\', t.assigned_to_user_id)) AS assigned_to_name,
                    COALESCE(NULLIF(b.name, \'\'), CONCAT(\'Business #\', t.business_id)) AS business_name
             FROM site_admin_tickets t
             LEFT JOIN users us ON us.id = t.submitted_by_user_id
             LEFT JOIN users ua ON ua.id = t.assigned_to_user_id
             LEFT JOIN businesses b ON b.id = t.business_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function notes(int $ticketId, bool $includeInternal = true): array
    {
        self::ensureTables();
        if ($ticketId <= 0) {
            return [];
        }

        $sql = 'SELECT n.id,
                       n.ticket_id,
                       n.user_id,
                       n.visibility,
                       n.note,
                       n.created_at,
                       n.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email, CONCAT(\'User #\', n.user_id)) AS user_name
                FROM site_admin_ticket_notes n
                LEFT JOIN users u ON u.id = n.user_id
                WHERE n.ticket_id = :ticket_id';
        $params = ['ticket_id' => $ticketId];
        if (!$includeInternal) {
            $sql .= ' AND n.visibility = :visibility';
            $params['visibility'] = 'customer';
        }
        $sql .= ' ORDER BY n.created_at ASC, n.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function addNote(
        int $ticketId,
        int $userId,
        string $note,
        string $visibility = 'customer',
        bool $fromRequester = false
    ): int {
        self::ensureTables();
        if ($ticketId <= 0 || trim($note) === '') {
            return 0;
        }

        $normalizedVisibility = self::normalizeVisibility($visibility);
        $sql = 'INSERT INTO site_admin_ticket_notes (
                    ticket_id,
                    user_id,
                    visibility,
                    note,
                    created_at,
                    updated_at,
                    created_by
                ) VALUES (
                    :ticket_id,
                    :user_id,
                    :visibility,
                    :note,
                    NOW(),
                    NOW(),
                    :created_by
                )';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'ticket_id' => $ticketId,
            'user_id' => $userId > 0 ? $userId : null,
            'visibility' => $normalizedVisibility,
            'note' => trim($note),
            'created_by' => $userId > 0 ? $userId : null,
        ]);

        $noteId = (int) Database::connection()->lastInsertId();
        self::touchAfterNote($ticketId, $userId, $normalizedVisibility, $fromRequester);
        return $noteId;
    }

    public static function updateFromAdmin(int $ticketId, array $data, int $actorId): void
    {
        self::ensureTables();
        if ($ticketId <= 0) {
            return;
        }

        $status = self::normalizeStatus($data['status'] ?? null);
        $category = self::normalizeCategory($data['category'] ?? null);
        $priority = self::normalizePriority($data['priority'] ?? null);
        $assignedTo = isset($data['assigned_to_user_id']) ? (int) $data['assigned_to_user_id'] : 0;
        if ($assignedTo <= 0) {
            $assignedTo = null;
        }

        $sql = 'UPDATE site_admin_tickets
                SET status = :status,
                    category = :category,
                    priority = :priority,
                    assigned_to_user_id = :assigned_to_user_id,
                    opened_at = CASE
                        WHEN opened_at IS NULL AND :status IN (\'pending\', \'working\', \'closed\') THEN NOW()
                        ELSE opened_at
                    END,
                    closed_at = CASE
                        WHEN :status = \'closed\' THEN COALESCE(closed_at, NOW())
                        ELSE NULL
                    END,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $ticketId,
            'status' => $status,
            'category' => $category,
            'priority' => $priority,
            'assigned_to_user_id' => $assignedTo,
            'updated_by' => $actorId > 0 ? $actorId : null,
        ]);
    }

    public static function pickUp(int $ticketId, int $actorId): void
    {
        self::ensureTables();
        if ($ticketId <= 0 || $actorId <= 0) {
            return;
        }

        $sql = 'UPDATE site_admin_tickets
                SET assigned_to_user_id = :actor_id,
                    status = CASE WHEN status = \'closed\' THEN \'closed\' ELSE \'working\' END,
                    opened_at = COALESCE(opened_at, NOW()),
                    updated_at = NOW(),
                    updated_by = :actor_id
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $ticketId,
            'actor_id' => $actorId,
        ]);
    }

    public static function markViewedByAdmin(int $ticketId, int $actorId): void
    {
        self::ensureTables();
        if ($ticketId <= 0) {
            return;
        }

        $sql = 'UPDATE site_admin_tickets
                SET opened_at = COALESCE(opened_at, NOW()),
                    status = CASE WHEN status = \'unopened\' THEN \'pending\' ELSE status END,
                    last_admin_note_at = NOW(),
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $ticketId,
            'updated_by' => $actorId > 0 ? $actorId : null,
        ]);
    }

    public static function convertToBug(int $ticketId, int $actorId, array $bugData = []): ?int
    {
        self::ensureTables();

        $ticket = self::findByIdForAdmin($ticketId);
        if (!$ticket) {
            return null;
        }

        $existingBugId = (int) ($ticket['converted_bug_id'] ?? 0);
        if ($existingBugId > 0) {
            return $existingBugId;
        }

        $notes = self::notes($ticketId, true);
        $detailsParts = [];
        $detailsParts[] = 'Converted from site admin ticket #' . $ticketId . '.';
        $detailsParts[] = 'Category: ' . self::labelCategory((string) ($ticket['category'] ?? 'other'));
        $detailsParts[] = 'Submitted By: ' . (string) ($ticket['submitted_by_name'] ?? ('User #' . (int) ($ticket['submitted_by_user_id'] ?? 0)));
        $detailsParts[] = 'Reply Email: ' . (string) ($ticket['submitted_by_email'] ?? '');
        if (!empty($ticket['business_name'])) {
            $detailsParts[] = 'Business: ' . (string) $ticket['business_name'];
        }
        $detailsParts[] = '';
        $detailsParts[] = 'Original Message:';
        $detailsParts[] = (string) ($ticket['message'] ?? '');
        if (!empty($notes)) {
            $detailsParts[] = '';
            $detailsParts[] = 'Ticket Notes:';
            foreach ($notes as $note) {
                $when = format_datetime($note['created_at'] ?? null);
                $who = trim((string) ($note['user_name'] ?? 'System'));
                $visibility = self::normalizeVisibility($note['visibility'] ?? 'customer');
                $detailsParts[] = '- [' . $when . '] ' . $who . ' (' . $visibility . '): ' . trim((string) ($note['note'] ?? ''));
            }
        }

        $severity = self::normalizePriority($bugData['severity'] ?? ($ticket['priority'] ?? 3));
        $environment = in_array((string) ($bugData['environment'] ?? 'both'), DevBug::ENVIRONMENTS, true)
            ? (string) $bugData['environment']
            : 'both';
        $assignedUserId = isset($ticket['assigned_to_user_id']) ? (int) $ticket['assigned_to_user_id'] : 0;
        if ($assignedUserId <= 0) {
            $assignedUserId = null;
        }

        $subject = trim((string) ($ticket['subject'] ?? ''));
        $title = $subject !== '' ? $subject : ('Support Ticket #' . $ticketId);

        $bugId = DevBug::create([
            'title' => '[Ticket #' . $ticketId . '] ' . $title,
            'details' => implode("\n", $detailsParts),
            'status' => 'unresearched',
            'severity' => $severity,
            'environment' => $environment,
            'module_key' => 'site_support',
            'route_path' => '/site-admin/support/' . $ticketId,
            'reported_by' => isset($ticket['submitted_by_user_id']) ? (int) $ticket['submitted_by_user_id'] : null,
            'assigned_user_id' => $assignedUserId,
        ], $actorId);

        $sql = 'UPDATE site_admin_tickets
                SET converted_bug_id = :bug_id,
                    status = \'closed\',
                    closed_at = COALESCE(closed_at, NOW()),
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $ticketId,
            'bug_id' => $bugId,
            'updated_by' => $actorId > 0 ? $actorId : null,
        ]);

        self::addNote(
            $ticketId,
            $actorId,
            'Converted to bug board ticket #' . $bugId . ' and closed.',
            'customer',
            false
        );

        return $bugId > 0 ? $bugId : null;
    }

    public static function assignableSiteAdmins(): array
    {
        self::ensureTables();

        $sql = 'SELECT u.id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), u.email, CONCAT(\'User #\', u.id)) AS name
                FROM users u
                WHERE u.role >= 4';
        if (Schema::hasColumn('users', 'deleted_at')) {
            $sql .= ' AND u.deleted_at IS NULL';
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $sql .= ' AND COALESCE(u.is_active, 1) = 1';
        }
        $sql .= ' ORDER BY name ASC, u.id ASC';

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function labelCategory(string $category): string
    {
        return match (self::normalizeCategory($category)) {
            'bug' => 'Bug',
            'question' => 'Question',
            'suggestion' => 'Suggestion',
            'account' => 'Account',
            'billing' => 'Billing',
            default => 'Other',
        };
    }

    public static function labelStatus(string $status): string
    {
        return match (self::normalizeStatus($status)) {
            'unopened' => 'Unopened',
            'pending' => 'Pending',
            'working' => 'Working',
            default => 'Closed',
        };
    }

    private static function buildUserWhere(int $userId, array $filters): array
    {
        $where = ['t.submitted_by_user_id = :submitted_by_user_id'];
        $params = ['submitted_by_user_id' => $userId];

        $status = self::normalizeStatusFilter($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }

        $category = self::normalizeCategoryFilter($filters['category'] ?? 'all');
        if ($category !== 'all') {
            $where[] = 't.category = :category';
            $params['category'] = $category;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(t.id AS CHAR) LIKE :q OR t.subject LIKE :q OR t.message LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private static function buildAdminWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $status = self::normalizeStatusFilter($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }

        $category = self::normalizeCategoryFilter($filters['category'] ?? 'all');
        if ($category !== 'all') {
            $where[] = 't.category = :category';
            $params['category'] = $category;
        }

        $priority = isset($filters['priority']) ? (int) $filters['priority'] : 0;
        if ($priority >= 1 && $priority <= 5) {
            $where[] = 't.priority = :priority';
            $params['priority'] = $priority;
        }

        $assignedTo = isset($filters['assigned_to_user_id']) ? (int) $filters['assigned_to_user_id'] : 0;
        if ($assignedTo > 0) {
            $where[] = 't.assigned_to_user_id = :assigned_to_user_id';
            $params['assigned_to_user_id'] = $assignedTo;
        } elseif (($filters['assigned_to_user_id'] ?? '') === 'unassigned') {
            $where[] = 't.assigned_to_user_id IS NULL';
        }

        $businessId = isset($filters['business_id']) ? (int) $filters['business_id'] : 0;
        if ($businessId > 0) {
            $where[] = 't.business_id = :business_id';
            $params['business_id'] = $businessId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(t.id AS CHAR) LIKE :q
                        OR t.subject LIKE :q
                        OR t.message LIKE :q
                        OR t.submitted_by_email LIKE :q
                        OR CAST(t.submitted_by_user_id AS CHAR) LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private static function touchAfterNote(int $ticketId, int $userId, string $visibility, bool $fromRequester): void
    {
        $sets = [
            'updated_at = NOW()',
            'updated_by = :updated_by',
        ];
        $params = [
            'id' => $ticketId,
            'updated_by' => $userId > 0 ? $userId : null,
        ];

        if ($fromRequester) {
            $sets[] = 'last_customer_note_at = NOW()';
            $sets[] = 'status = CASE
                        WHEN status = \'closed\' THEN \'pending\'
                        WHEN status = \'unopened\' THEN \'pending\'
                        ELSE status
                    END';
        } elseif ($visibility === 'internal') {
            $sets[] = 'last_admin_note_at = NOW()';
        } else {
            $sets[] = 'last_admin_note_at = NOW()';
        }

        $sql = 'UPDATE site_admin_tickets
                SET ' . implode(', ', $sets) . '
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    private static function normalizeCategory(mixed $value): string
    {
        $category = strtolower(trim((string) $value));
        if (!in_array($category, self::CATEGORIES, true)) {
            return 'other';
        }

        return $category;
    }

    private static function normalizeCategoryFilter(mixed $value): string
    {
        $category = strtolower(trim((string) $value));
        if ($category === '' || $category === 'all') {
            return 'all';
        }

        return in_array($category, self::CATEGORIES, true) ? $category : 'all';
    }

    private static function normalizeStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if (!in_array($status, self::STATUSES, true)) {
            return 'unopened';
        }

        return $status;
    }

    private static function normalizeStatusFilter(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if ($status === '' || $status === 'all') {
            return 'all';
        }

        return in_array($status, self::STATUSES, true) ? $status : 'all';
    }

    private static function normalizePriority(mixed $value): int
    {
        $priority = (int) $value;
        if ($priority < 1 || $priority > 5) {
            return 3;
        }

        return $priority;
    }

    private static function normalizeVisibility(mixed $value): string
    {
        $visibility = strtolower(trim((string) $value));
        if (!in_array($visibility, self::NOTE_VISIBILITY, true)) {
            return 'customer';
        }

        return $visibility;
    }
}
