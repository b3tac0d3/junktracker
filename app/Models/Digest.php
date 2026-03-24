<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class Digest
{
    /** Plain-text summary for daily email (one business). */
    public static function buildText(int $businessId): string
    {
        $lines = [];
        $lines[] = 'JunkTracker daily digest';
        $lines[] = '';

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $jobCount = self::countJobsScheduledOn($businessId, $tomorrow);
        $lines[] = 'Jobs scheduled tomorrow (' . $tomorrow . '): ' . (string) $jobCount;

        $openPunches = self::countOpenPunches($businessId);
        $lines[] = 'Open time punches (not clocked out): ' . (string) $openPunches;

        $overdue = self::countOverdueInvoices($businessId);
        $lines[] = 'Overdue invoices (past due date, balance due): ' . (string) $overdue;
        $lines[] = '';
        $lines[] = '— JunkTracker';

        return implode("\n", $lines);
    }

    private static function countJobsScheduledOn(int $businessId, string $date): int
    {
        if (!SchemaInspector::hasTable('jobs') || !SchemaInspector::hasColumn('jobs', 'scheduled_start_at')) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM jobs j
             WHERE j.business_id = :business_id
               AND j.deleted_at IS NULL
               AND DATE(j.scheduled_start_at) = :d'
        );
        $stmt->execute(['business_id' => $businessId, 'd' => $date]);

        return (int) $stmt->fetchColumn();
    }

    private static function countOpenPunches(int $businessId): int
    {
        if (!SchemaInspector::hasTable('employee_time_entries')) {
            return 0;
        }
        $out = SchemaInspector::hasColumn('employee_time_entries', 'clock_out_at') ? 't.clock_out_at' : null;
        if ($out === null) {
            return 0;
        }

        $del = SchemaInspector::hasColumn('employee_time_entries', 'deleted_at') ? 'AND t.deleted_at IS NULL' : '';
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM employee_time_entries t
             WHERE t.business_id = :business_id
               {$del}
               AND {$out} IS NULL"
        );
        $stmt->execute(['business_id' => $businessId]);

        return (int) $stmt->fetchColumn();
    }

    private static function countOverdueInvoices(int $businessId): int
    {
        if (!SchemaInspector::hasTable('invoices') || !SchemaInspector::hasColumn('invoices', 'due_date')) {
            return 0;
        }

        $typeCol = SchemaInspector::hasColumn('invoices', 'type') ? 'i.type' : "'invoice'";
        $totalCol = SchemaInspector::hasColumn('invoices', 'total') ? 'i.total' : '0';
        $dueCol = 'i.due_date';

        $statusSkip = SchemaInspector::hasColumn('invoices', 'status')
            ? "AND LOWER(COALESCE(i.status, '')) NOT IN ('paid_in_full', 'cancelled')"
            : '';

        $sql = "SELECT i.id, {$totalCol} AS total
                FROM invoices i
                WHERE i.business_id = :business_id
                  AND i.deleted_at IS NULL
                  AND {$dueCol} IS NOT NULL
                  AND DATE({$dueCol}) < CURDATE()
                  AND LOWER(COALESCE({$typeCol}, 'invoice')) = 'invoice'
                  {$statusSkip}";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['business_id' => $businessId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $bal = Invoice::remainingBalanceForInvoice($businessId, $id);
            if ($bal > 0.009) {
                $count++;
            }
        }

        return $count;
    }
}
