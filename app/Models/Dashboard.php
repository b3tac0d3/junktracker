<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class Dashboard
{
    public static function overview(): array
    {
        $today = date('Y-m-d');
        $mtdStart = date('Y-m-01');
        $ytdStart = date('Y-01-01');

        $counts = self::counts($today);
        $sales = self::salesSummary($mtdStart, $ytdStart, $today);
        $jobsGross = self::jobsGrossSummary($mtdStart, $ytdStart, $today);
        $expenses = self::expenseSummary($mtdStart, $ytdStart, $today);
        $onClock = self::onClockSummary();
        $tasks = self::taskSummary();

        $grossMtd = (float) ($sales['gross_mtd'] ?? 0) + (float) ($jobsGross['gross_mtd'] ?? 0);
        $grossYtd = (float) ($sales['gross_ytd'] ?? 0) + (float) ($jobsGross['gross_ytd'] ?? 0);

        return [
            'period' => [
                'today' => $today,
                'mtd_start' => $mtdStart,
                'ytd_start' => $ytdStart,
            ],
            'counts' => $counts,
            'revenue' => [
                'sales' => $sales,
                'jobs' => $jobsGross,
                'expenses' => $expenses,
                'totals' => [
                    'gross_mtd' => $grossMtd,
                    'gross_ytd' => $grossYtd,
                    'net_mtd' => $grossMtd - (float) ($expenses['mtd'] ?? 0),
                    'net_ytd' => $grossYtd - (float) ($expenses['ytd'] ?? 0),
                ],
            ],
            'on_clock' => $onClock,
            'prospects' => [
                'follow_ups' => self::upcomingProspects(10),
            ],
            'job_pipeline' => [
                'pending' => self::jobsByStatus('pending', 10),
                'active' => self::jobsByStatus('active', 10),
            ],
            'tasks' => $tasks,
        ];
    }

    private static function counts(string $today): array
    {
        return self::safe(static function () use ($today): array {
            $sql = 'SELECT
                        (SELECT COUNT(*)
                         FROM prospects p
                         WHERE p.deleted_at IS NULL
                           AND COALESCE(p.active, 1) = 1
                           AND p.status = \'active\') AS prospects_active,
                        (SELECT COUNT(*)
                         FROM prospects p
                         WHERE p.deleted_at IS NULL
                           AND COALESCE(p.active, 1) = 1
                           AND p.status = \'active\'
                           AND p.follow_up_on IS NOT NULL
                           AND DATE(p.follow_up_on) <= :today) AS prospects_follow_up_due,
                        (SELECT COUNT(*)
                         FROM jobs j
                         WHERE j.deleted_at IS NULL
                           AND COALESCE(j.active, 1) = 1
                           AND j.job_status = \'pending\') AS jobs_pending,
                        (SELECT COUNT(*)
                         FROM jobs j
                         WHERE j.deleted_at IS NULL
                           AND COALESCE(j.active, 1) = 1
                           AND j.job_status = \'active\') AS jobs_active';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['today' => $today]);

            $row = $stmt->fetch();
            return $row ?: [];
        }, [
            'prospects_active' => 0,
            'prospects_follow_up_due' => 0,
            'jobs_pending' => 0,
            'jobs_active' => 0,
        ]);
    }

    private static function salesSummary(string $mtdStart, string $ytdStart, string $today): array
    {
        return self::safe(static function () use ($mtdStart, $ytdStart, $today): array {
            $sql = 'SELECT
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(s.end_date, s.start_date, s.created_at)) BETWEEN :mtd_start AND :today
                                THEN COALESCE(s.gross_amount, 0)
                                ELSE 0
                            END
                        ), 0) AS gross_mtd,
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(s.end_date, s.start_date, s.created_at)) BETWEEN :ytd_start AND :today
                                THEN COALESCE(s.gross_amount, 0)
                                ELSE 0
                            END
                        ), 0) AS gross_ytd,
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(s.end_date, s.start_date, s.created_at)) BETWEEN :mtd_start AND :today
                                THEN COALESCE(s.net_amount, COALESCE(s.gross_amount, 0))
                                ELSE 0
                            END
                        ), 0) AS net_mtd,
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(s.end_date, s.start_date, s.created_at)) BETWEEN :ytd_start AND :today
                                THEN COALESCE(s.net_amount, COALESCE(s.gross_amount, 0))
                                ELSE 0
                            END
                        ), 0) AS net_ytd
                    FROM sales s
                    WHERE s.deleted_at IS NULL
                      AND COALESCE(s.active, 1) = 1';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'mtd_start' => $mtdStart,
                'ytd_start' => $ytdStart,
                'today' => $today,
            ]);

            $row = $stmt->fetch();
            return $row ?: [];
        }, [
            'gross_mtd' => 0,
            'gross_ytd' => 0,
            'net_mtd' => 0,
            'net_ytd' => 0,
        ]);
    }

    private static function jobsGrossSummary(string $mtdStart, string $ytdStart, string $today): array
    {
        return self::safe(static function () use ($mtdStart, $ytdStart, $today): array {
            $sql = 'SELECT
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(j.paid_date, j.billed_date, j.end_date, j.updated_at)) BETWEEN :mtd_start AND :today
                                THEN COALESCE(j.total_billed, 0)
                                ELSE 0
                            END
                        ), 0) AS gross_mtd,
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(j.paid_date, j.billed_date, j.end_date, j.updated_at)) BETWEEN :ytd_start AND :today
                                THEN COALESCE(j.total_billed, 0)
                                ELSE 0
                            END
                        ), 0) AS gross_ytd
                    FROM jobs j
                    WHERE j.deleted_at IS NULL
                      AND COALESCE(j.active, 1) = 1';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'mtd_start' => $mtdStart,
                'ytd_start' => $ytdStart,
                'today' => $today,
            ]);

            $row = $stmt->fetch();
            return $row ?: [];
        }, [
            'gross_mtd' => 0,
            'gross_ytd' => 0,
        ]);
    }

    private static function expenseSummary(string $mtdStart, string $ytdStart, string $today): array
    {
        return self::safe(static function () use ($mtdStart, $ytdStart, $today): array {
            $sql = 'SELECT
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(e.expense_date, e.created_at)) BETWEEN :mtd_start AND :today
                                THEN COALESCE(e.amount, 0)
                                ELSE 0
                            END
                        ), 0) AS mtd,
                        COALESCE(SUM(
                            CASE
                                WHEN DATE(COALESCE(e.expense_date, e.created_at)) BETWEEN :ytd_start AND :today
                                THEN COALESCE(e.amount, 0)
                                ELSE 0
                            END
                        ), 0) AS ytd
                    FROM expenses e
                    WHERE e.deleted_at IS NULL
                      AND COALESCE(e.is_active, 1) = 1';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'mtd_start' => $mtdStart,
                'ytd_start' => $ytdStart,
                'today' => $today,
            ]);

            $row = $stmt->fetch();
            return $row ?: [];
        }, [
            'mtd' => 0,
            'ytd' => 0,
        ]);
    }

    private static function onClockSummary(): array
    {
        return self::safe(static function (): array {
            $entries = TimeEntry::openEntries([]);
            usort($entries, static fn (array $a, array $b): int => (int) ($b['open_minutes'] ?? 0) <=> (int) ($a['open_minutes'] ?? 0));

            return [
                'summary' => TimeEntry::openSummary([]),
                'entries' => array_slice($entries, 0, 10),
            ];
        }, [
            'summary' => [
                'active_count' => 0,
                'total_open_minutes' => 0,
                'total_open_paid' => 0,
            ],
            'entries' => [],
        ]);
    }

    private static function taskSummary(): array
    {
        return self::safe(static function (): array {
            return Task::summary([
                'q' => '',
                'status' => 'all',
                'importance' => 0,
                'link_type' => 'all',
                'assigned_user_id' => 0,
                'due_start' => '',
                'due_end' => '',
                'record_status' => 'active',
            ]);
        }, [
            'total_count' => 0,
            'open_count' => 0,
            'in_progress_count' => 0,
            'closed_count' => 0,
            'overdue_count' => 0,
        ]);
    }

    private static function upcomingProspects(int $limit): array
    {
        return self::safe(static function () use ($limit): array {
            $sql = 'SELECT p.id,
                           p.follow_up_on,
                           p.next_step,
                           p.priority_rating,
                           p.status,
                           COALESCE(
                               NULLIF(c.business_name, \'\'),
                               NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                               CONCAT(\'Client #\', c.id)
                           ) AS client_name
                    FROM prospects p
                    LEFT JOIN clients c ON c.id = p.client_id
                    WHERE p.deleted_at IS NULL
                      AND COALESCE(p.active, 1) = 1
                      AND p.status = \'active\'
                    ORDER BY
                      CASE WHEN p.follow_up_on IS NULL THEN 1 ELSE 0 END ASC,
                      p.follow_up_on ASC,
                      COALESCE(p.priority_rating, 0) DESC,
                      p.id DESC
                    LIMIT ' . max(1, min($limit, 50));

            $stmt = Database::connection()->query($sql);
            return $stmt->fetchAll();
        }, []);
    }

    private static function jobsByStatus(string $status, int $limit): array
    {
        return self::safe(static function () use ($status, $limit): array {
            $sql = 'SELECT j.id,
                           j.name,
                           j.city,
                           j.state,
                           j.scheduled_date,
                           j.total_quote,
                           j.total_billed,
                           COALESCE(
                               NULLIF(c.business_name, \'\'),
                               NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                               CONCAT(\'Client #\', c.id)
                           ) AS client_name
                    FROM jobs j
                    LEFT JOIN clients c ON c.id = j.client_id
                    WHERE j.deleted_at IS NULL
                      AND COALESCE(j.active, 1) = 1
                      AND j.job_status = :status
                    ORDER BY
                      CASE WHEN j.scheduled_date IS NULL THEN 1 ELSE 0 END ASC,
                      j.scheduled_date ASC,
                      j.id DESC
                    LIMIT ' . max(1, min($limit, 50));

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['status' => $status]);
            return $stmt->fetchAll();
        }, []);
    }

    private static function safe(callable $callback, array $fallback): array
    {
        try {
            $result = $callback();
            return is_array($result) ? $result : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
