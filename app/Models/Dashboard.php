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
        $outstandingTasks = self::outstandingTasks(8, 8);
        $inviteSummary = User::outstandingInviteSummary();
        $outstandingInvites = ((int) ($inviteSummary['outstanding_count'] ?? 0) > 0)
            ? User::outstandingInvites(10)
            : [];
        $consignorPayments = self::consignorPaymentsDue(10);
        $completedUnbilled = self::completedUnbilledJobs(10);

        $grossMtd = (float) ($sales['gross_mtd'] ?? 0) + (float) ($jobsGross['gross_mtd'] ?? 0);
        $grossYtd = (float) ($sales['gross_ytd'] ?? 0) + (float) ($jobsGross['gross_ytd'] ?? 0);

        $overview = [
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
            'tasks_outstanding' => $outstandingTasks,
            'invites' => [
                'summary' => $inviteSummary,
                'rows' => $outstandingInvites,
            ],
            'consignor_payments' => $consignorPayments,
            'completed_unbilled_jobs' => $completedUnbilled,
            'alert_queue' => self::buildAlertQueue($outstandingTasks, $completedUnbilled, $consignorPayments, $outstandingInvites),
        ];

        self::storeSnapshot($today, $overview);

        return $overview;
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

    private static function outstandingTasks(int $overdueLimit, int $upcomingLimit): array
    {
        return self::safe(static function () use ($overdueLimit, $upcomingLimit): array {
            $fetch = static function (bool $overdue, int $limit): array {
                $sql = 'SELECT t.id,
                               t.title,
                               t.link_type,
                               t.link_id,
                               t.assigned_user_id,
                               t.importance,
                               t.status,
                               t.due_at,
                               COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', u.first_name, u.last_name)), \'\'), CONCAT(\'User #\', u.id)) AS assigned_user_name
                        FROM todos t
                        LEFT JOIN users u ON u.id = t.assigned_user_id
                        WHERE t.deleted_at IS NULL
                          AND t.status IN (\'open\', \'in_progress\')
                          AND t.due_at IS NOT NULL
                          AND ' . ($overdue ? 't.due_at < NOW()' : 't.due_at >= NOW()') . '
                        ORDER BY t.due_at ASC, t.importance DESC, t.id DESC
                        LIMIT ' . max(1, min($limit, 25));

                $stmt = Database::connection()->query($sql);
                $rows = $stmt->fetchAll();

                foreach ($rows as &$row) {
                    $linkType = (string) ($row['link_type'] ?? 'general');
                    $linkId = isset($row['link_id']) ? (int) $row['link_id'] : null;
                    $link = Task::resolveLink($linkType, $linkId);
                    $row['link_label'] = $link['label'] ?? '—';
                    $row['link_url'] = $link['url'] ?? null;
                }
                unset($row);

                return $rows;
            };

            return [
                'overdue' => $fetch(true, $overdueLimit),
                'upcoming' => $fetch(false, $upcomingLimit),
            ];
        }, [
            'overdue' => [],
            'upcoming' => [],
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

    private static function consignorPaymentsDue(int $limit): array
    {
        return self::safe(static function () use ($limit): array {
            Consignor::ensureSchema();

            $capped = max(1, min($limit, 25));
            $sql = 'SELECT c.id,
                           c.consignor_number,
                           c.first_name,
                           c.last_name,
                           c.business_name,
                           c.payment_schedule,
                           c.next_payment_due_date
                    FROM consignors c
                    WHERE c.deleted_at IS NULL
                      AND COALESCE(c.active, 1) = 1
                      AND c.next_payment_due_date IS NOT NULL
                    ORDER BY c.next_payment_due_date ASC, c.id DESC
                    LIMIT ' . $capped;

            $rows = Database::connection()->query($sql)->fetchAll();

            $countSql = 'SELECT
                            COALESCE(SUM(CASE WHEN c.next_payment_due_date <= CURDATE() THEN 1 ELSE 0 END), 0) AS due_now_count,
                            COALESCE(SUM(CASE WHEN c.next_payment_due_date > CURDATE() THEN 1 ELSE 0 END), 0) AS upcoming_count
                         FROM consignors c
                         WHERE c.deleted_at IS NULL
                           AND COALESCE(c.active, 1) = 1
                           AND c.next_payment_due_date IS NOT NULL';
            $summary = Database::connection()->query($countSql)->fetch();

            return [
                'rows' => $rows,
                'summary' => $summary ?: ['due_now_count' => 0, 'upcoming_count' => 0],
            ];
        }, [
            'rows' => [],
            'summary' => ['due_now_count' => 0, 'upcoming_count' => 0],
        ]);
    }

    private static function completedUnbilledJobs(int $limit): array
    {
        return self::safe(static function () use ($limit): array {
            $capped = max(1, min($limit, 25));
            $sql = 'SELECT j.id,
                           j.name,
                           j.updated_at,
                           j.scheduled_date,
                           j.total_quote,
                           j.total_billed,
                           j.billed_date,
                           COALESCE(
                               NULLIF(c.business_name, \'\'),
                               NULLIF(TRIM(CONCAT_WS(\' \', c.first_name, c.last_name)), \'\'),
                               CONCAT(\'Client #\', c.id)
                           ) AS client_name
                    FROM jobs j
                    LEFT JOIN clients c ON c.id = j.client_id
                    WHERE j.deleted_at IS NULL
                      AND COALESCE(j.active, 1) = 1
                      AND j.job_status = \'complete\'
                      AND (
                           j.billed_date IS NULL
                           OR COALESCE(j.total_billed, 0) <= 0
                      )
                    ORDER BY COALESCE(j.updated_at, j.end_date, j.scheduled_date, j.created_at) DESC, j.id DESC
                    LIMIT ' . $capped;

            $rows = Database::connection()->query($sql)->fetchAll();
            $summarySql = 'SELECT COUNT(*) AS count_total
                           FROM jobs j
                           WHERE j.deleted_at IS NULL
                             AND COALESCE(j.active, 1) = 1
                             AND j.job_status = \'complete\'
                             AND (
                                  j.billed_date IS NULL
                                  OR COALESCE(j.total_billed, 0) <= 0
                             )';
            $summary = Database::connection()->query($summarySql)->fetch();

            return [
                'rows' => $rows,
                'summary' => $summary ?: ['count_total' => 0],
            ];
        }, [
            'rows' => [],
            'summary' => ['count_total' => 0],
        ]);
    }

    private static function buildAlertQueue(array $tasksOutstanding, array $completedUnbilled, array $consignorPayments, array $outstandingInvites): array
    {
        $alerts = [];

        foreach (array_slice(is_array($tasksOutstanding['overdue'] ?? null) ? $tasksOutstanding['overdue'] : [], 0, 5) as $row) {
            $taskId = (int) ($row['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $alerts[] = [
                'type' => 'task_overdue',
                'label' => (string) (($row['title'] ?? '') !== '' ? $row['title'] : ('Task #' . $taskId)),
                'meta' => 'Overdue task',
                'url' => '/tasks/' . $taskId,
            ];
        }

        foreach (array_slice(is_array($completedUnbilled['rows'] ?? null) ? $completedUnbilled['rows'] : [], 0, 5) as $row) {
            $jobId = (int) ($row['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }
            $jobName = trim((string) ($row['name'] ?? ''));
            $label = $jobName !== '' ? $jobName : ('Job #' . $jobId);

            $alerts[] = [
                'type' => 'job_unbilled',
                'label' => $label,
                'meta' => 'Completed but not billed',
                'url' => '/jobs/' . $jobId,
            ];
        }

        foreach (array_slice(is_array($consignorPayments['rows'] ?? null) ? $consignorPayments['rows'] : [], 0, 5) as $row) {
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
                'type' => 'consignor_due',
                'label' => $name,
                'meta' => 'Consignor payment due ' . format_date((string) ($row['next_payment_due_date'] ?? null)),
                'url' => '/consignors/' . $consignorId,
            ];
        }

        foreach (array_slice($outstandingInvites, 0, 5) as $invite) {
            $userId = (int) ($invite['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $name = trim((string) ($invite['first_name'] ?? '') . ' ' . (string) ($invite['last_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($invite['email'] ?? ''));
            }
            if ($name === '') {
                $name = 'User #' . $userId;
            }

            $inviteMeta = is_array($invite['invite'] ?? null) ? $invite['invite'] : User::inviteStatus($invite);
            $meta = (string) ($inviteMeta['label'] ?? 'Invited');
            if (!empty($inviteMeta['expires_at'])) {
                $meta .= ' · Expires ' . format_datetime((string) $inviteMeta['expires_at']);
            }

            $alerts[] = [
                'type' => 'user_invite',
                'label' => $name,
                'meta' => $meta,
                'url' => '/users/' . $userId,
            ];
        }

        return $alerts;
    }

    private static function storeSnapshot(string $today, array $overview): void
    {
        $metrics = [
            'counts' => [
                'prospects_active' => (int) ($overview['counts']['prospects_active'] ?? 0),
                'prospects_follow_up_due' => (int) ($overview['counts']['prospects_follow_up_due'] ?? 0),
                'jobs_pending' => (int) ($overview['counts']['jobs_pending'] ?? 0),
                'jobs_active' => (int) ($overview['counts']['jobs_active'] ?? 0),
                'pending_invites' => (int) ($overview['invites']['summary']['outstanding_count'] ?? 0),
                'expired_invites' => (int) ($overview['invites']['summary']['expired_count'] ?? 0),
            ],
            'totals' => [
                'sales_gross_mtd' => (float) ($overview['revenue']['sales']['gross_mtd'] ?? 0),
                'sales_net_mtd' => (float) ($overview['revenue']['sales']['net_mtd'] ?? 0),
                'jobs_gross_mtd' => (float) ($overview['revenue']['jobs']['gross_mtd'] ?? 0),
                'expenses_mtd' => (float) ($overview['revenue']['expenses']['mtd'] ?? 0),
                'open_tasks' => (int) ($overview['tasks']['open_count'] ?? 0),
                'overdue_tasks' => (int) ($overview['tasks']['overdue_count'] ?? 0),
            ],
            'recorded_at' => date('c'),
        ];

        try {
            DashboardKpiSnapshot::record($today, $metrics);
        } catch (\Throwable) {
            // Snapshot persistence should not affect dashboard rendering.
        }
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
