<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

/**
 * Aggregates reminder items for the top-nav notifications menu (past due, late schedules, etc.).
 */
final class NavNotifications
{
    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public static function summary(): array
    {
        if (auth_user() === null) {
            return ['items' => [], 'total' => 0];
        }
        if (current_business_id() <= 0) {
            return ['items' => [], 'total' => 0];
        }
        if (workspace_role() === 'punch_only') {
            return ['items' => [], 'total' => 0];
        }

        $businessId = current_business_id();
        $userId = (int) (auth_user_id() ?? 0);

        $items = [];
        $items = array_merge($items, self::myOverdueTasks($businessId, $userId));
        $items = array_merge($items, self::myTasksDueToday($businessId, $userId));
        $items = array_merge($items, self::lateDeliveries($businessId));
        $items = array_merge($items, self::lateScheduledJobs($businessId));
        $items = array_merge($items, self::overdueInvoices($businessId));

        usort($items, static function (array $a, array $b): int {
            return ((int) ($b['sort_key'] ?? 0)) <=> ((int) ($a['sort_key'] ?? 0));
        });

        $totalCount = count($items);
        $max = 25;
        if ($totalCount > $max) {
            $items = array_slice($items, 0, $max);
        }

        return [
            'items' => $items,
            'total' => $totalCount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function myOverdueTasks(int $businessId, int $userId): array
    {
        if ($userId <= 0 || !SchemaInspector::hasTable('tasks') || !SchemaInspector::hasColumn('tasks', 'due_at')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 'LOWER(t.status)' : "'open'";
        $ownerSql = SchemaInspector::hasColumn('tasks', 'owner_user_id') ? 't.owner_user_id = :user_id' : '1=0';
        $bizWhere = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $delWhere = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT t.id, {$titleSql} AS title, t.due_at
                FROM tasks t
                WHERE {$bizWhere}
                  AND {$delWhere}
                  AND {$ownerSql}
                  AND {$statusSql} IN ('open','in_progress')
                  AND t.due_at IS NOT NULL
                  AND t.due_at < NOW()
                ORDER BY t.due_at ASC
                LIMIT 12";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
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
            $id = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? '')) ?: ('Task #' . (string) $id);
            $dueAt = trim((string) ($row['due_at'] ?? ''));
            $ts = $dueAt !== '' ? strtotime($dueAt) : false;
            $out[] = [
                'kind' => 'task_overdue',
                'label' => $title,
                'meta' => 'Overdue · ' . self::formatShortDateTime($dueAt),
                'href' => url('/tasks/' . (string) $id),
                'badge' => 'danger',
                'icon' => 'fa-list-check',
                'sort_key' => $ts !== false ? $ts : 0,
            ];
        }

        return $out;
    }

    /**
     * Tasks due today (same calendar day) that are not yet overdue by time-of-day.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function myTasksDueToday(int $businessId, int $userId): array
    {
        if ($userId <= 0 || !SchemaInspector::hasTable('tasks') || !SchemaInspector::hasColumn('tasks', 'due_at')) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('tasks', 'title') ? 't.title' : "CONCAT('Task #', t.id)";
        $statusSql = SchemaInspector::hasColumn('tasks', 'status') ? 'LOWER(t.status)' : "'open'";
        $ownerSql = SchemaInspector::hasColumn('tasks', 'owner_user_id') ? 't.owner_user_id = :user_id' : '1=0';
        $bizWhere = SchemaInspector::hasColumn('tasks', 'business_id') ? 't.business_id = :business_id' : '1=1';
        $delWhere = SchemaInspector::hasColumn('tasks', 'deleted_at') ? 't.deleted_at IS NULL' : '1=1';

        $sql = "SELECT t.id, {$titleSql} AS title, t.due_at
                FROM tasks t
                WHERE {$bizWhere}
                  AND {$delWhere}
                  AND {$ownerSql}
                  AND {$statusSql} IN ('open','in_progress')
                  AND t.due_at IS NOT NULL
                  AND DATE(t.due_at) = CURDATE()
                  AND t.due_at >= NOW()
                ORDER BY t.due_at ASC
                LIMIT 8";

        $stmt = Database::connection()->prepare($sql);
        if (SchemaInspector::hasColumn('tasks', 'business_id')) {
            $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
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
            $id = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? '')) ?: ('Task #' . (string) $id);
            $dueAt = trim((string) ($row['due_at'] ?? ''));
            $ts = $dueAt !== '' ? strtotime($dueAt) : false;
            $out[] = [
                'kind' => 'task_due_today',
                'label' => $title,
                'meta' => 'Due today · ' . self::formatShortTime($dueAt),
                'href' => url('/tasks/' . (string) $id),
                'badge' => 'warning',
                'icon' => 'fa-list-check',
                'sort_key' => $ts !== false ? $ts : 0,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function lateDeliveries(int $businessId): array
    {
        if (!SchemaInspector::hasTable('client_deliveries')) {
            return [];
        }

        $sql = "SELECT d.id, d.scheduled_at,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\" \", c.first_name, c.last_name)), \"\"), NULLIF(c.company_name, \"\"), CONCAT(\"Client #\", c.id)) AS client_name
                FROM client_deliveries d
                INNER JOIN clients c ON c.id = d.client_id AND c.business_id = d.business_id AND c.deleted_at IS NULL
                WHERE d.business_id = :business_id
                  AND d.deleted_at IS NULL
                  AND LOWER(d.status) = 'scheduled'
                  AND d.scheduled_at IS NOT NULL
                  AND d.scheduled_at < NOW()
                ORDER BY d.scheduled_at ASC
                LIMIT 8";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
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
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['client_name'] ?? '')) ?: 'Delivery';
            $at = trim((string) ($row['scheduled_at'] ?? ''));
            $ts = $at !== '' ? strtotime($at) : false;
            $out[] = [
                'kind' => 'delivery_late',
                'label' => 'Delivery: ' . $name,
                'meta' => 'Scheduled ' . self::formatShortDateTime($at),
                'href' => url('/deliveries/' . (string) $id),
                'badge' => 'danger',
                'icon' => 'fa-truck',
                'sort_key' => $ts !== false ? $ts : 0,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function lateScheduledJobs(int $businessId): array
    {
        if (!SchemaInspector::hasTable('jobs')) {
            return [];
        }

        $startSql = SchemaInspector::hasColumn('jobs', 'scheduled_start_at')
            ? 'j.scheduled_start_at'
            : (SchemaInspector::hasColumn('jobs', 'start_date') ? 'j.start_date' : null);
        if ($startSql === null) {
            return [];
        }

        $titleSql = SchemaInspector::hasColumn('jobs', 'title')
            ? 'j.title'
            : (SchemaInspector::hasColumn('jobs', 'name') ? 'j.name' : "CONCAT('Job #', j.id)");
        $statusSql = SchemaInspector::hasColumn('jobs', 'status') ? 'LOWER(j.status)' : "'pending'";
        $deletedWhere = SchemaInspector::hasColumn('jobs', 'deleted_at') ? 'AND j.deleted_at IS NULL' : '';

        $sql = "SELECT j.id, {$titleSql} AS title, {$startSql} AS scheduled_start_at
                FROM jobs j
                WHERE j.business_id = :business_id
                  {$deletedWhere}
                  AND {$statusSql} IN ('pending','active')
                  AND {$startSql} IS NOT NULL
                  AND {$startSql} < NOW()
                ORDER BY {$startSql} ASC
                LIMIT 8";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
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
            $id = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? '')) ?: ('Job #' . (string) $id);
            $at = trim((string) ($row['scheduled_start_at'] ?? ''));
            $ts = $at !== '' ? strtotime($at) : false;
            $out[] = [
                'kind' => 'job_late',
                'label' => $title,
                'meta' => 'Scheduled ' . self::formatShortDateTime($at),
                'href' => url('/jobs/' . (string) $id),
                'badge' => 'warning',
                'icon' => 'fa-briefcase',
                'sort_key' => $ts !== false ? $ts : 0,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function overdueInvoices(int $businessId): array
    {
        if (!SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'due_date')) {
            return [];
        }

        $totalSql = SchemaInspector::hasColumn('invoices', 'total')
            ? 'i.total'
            : (SchemaInspector::hasColumn('invoices', 'subtotal') ? 'i.subtotal' : '0');
        $numberSql = SchemaInspector::hasColumn('invoices', 'invoice_number') ? 'i.invoice_number' : "CAST(i.id AS CHAR)";
        $typeWhere = SchemaInspector::hasColumn('invoices', 'type')
            ? "(LOWER(COALESCE(NULLIF(TRIM(i.type), ''), 'estimate')) = 'invoice')"
            : '1=1';
        $delWhere = SchemaInspector::hasColumn('invoices', 'deleted_at') ? 'i.deleted_at IS NULL' : '1=1';

        $statusWhere = '';
        if (SchemaInspector::hasColumn('invoices', 'status')) {
            $statusWhere = "AND LOWER(TRIM(COALESCE(i.status, ''))) NOT IN ('paid','paid_in_full','cancelled','draft','declined')";
        }

        $unpaidWhere = '';
        if (SchemaInspector::hasTable('payments') && SchemaInspector::hasColumn('payments', 'amount')) {
            $amountCol = SchemaInspector::hasColumn('payments', 'amount') ? 'p.amount' : '0';
            $payDel = SchemaInspector::hasColumn('payments', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';
            $payBiz = SchemaInspector::hasColumn('payments', 'business_id') ? 'p.business_id = i.business_id' : '1=1';
            $unpaidWhere = "AND (
                GREATEST(0, COALESCE({$totalSql}, 0) - COALESCE((
                    SELECT SUM({$amountCol}) FROM payments p
                    WHERE p.invoice_id = i.id AND {$payDel} AND {$payBiz}
                ), 0)) > 0.009
            )";
        }

        $sql = "SELECT i.id, {$numberSql} AS inv_no, {$totalSql} AS inv_total, i.due_date
                FROM invoices i
                WHERE i.business_id = :business_id
                  AND {$delWhere}
                  AND {$typeWhere}
                  AND i.due_date IS NOT NULL
                  AND DATE(i.due_date) < CURDATE()
                  {$statusWhere}
                  {$unpaidWhere}
                ORDER BY i.due_date ASC
                LIMIT 8";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':business_id', $businessId, \PDO::PARAM_INT);
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
            $id = (int) ($row['id'] ?? 0);
            $no = trim((string) ($row['inv_no'] ?? '')) ?: ('#' . (string) $id);
            $due = trim((string) ($row['due_date'] ?? ''));
            $ts = $due !== '' ? strtotime($due . ' 12:00:00') : false;
            $amt = (float) ($row['inv_total'] ?? 0);
            $out[] = [
                'kind' => 'invoice_overdue',
                'label' => 'Invoice ' . $no,
                'meta' => 'Due ' . self::formatShortDateOnly($due) . ' · $' . number_format($amt, 2),
                'href' => url('/billing/' . (string) $id),
                'badge' => 'danger',
                'icon' => 'fa-file-invoice-dollar',
                'sort_key' => $ts !== false ? $ts : 0,
            ];
        }

        return $out;
    }

    private static function formatShortDateTime(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('M j, g:i A', $ts);
    }

    private static function formatShortTime(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('g:i A', $ts);
    }

    private static function formatShortDateOnly(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('M j, Y', $ts);
    }
}
