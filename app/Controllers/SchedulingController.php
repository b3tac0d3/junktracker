<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Job;
use Core\Controller;

final class SchedulingController extends Controller
{
    public function index(): void
    {
        require_permission('jobs', 'view');

        $statusScope = $this->normalizeStatusScope($_GET['status_scope'] ?? 'dispatch');
        $search = trim((string) ($_GET['q'] ?? ''));

        $statuses = $this->scopeToStatuses($statusScope);
        $unscheduledJobs = Job::unscheduledForBoard($statuses, 'active', 250, $search);

        $pageStyles = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" />';
        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>',
            '<script src="' . asset('js/jobs-schedule-board.js') . '?v=' . rawurlencode((string) config('app.version', 'dev')) . '"></script>',
        ]);

        $this->render('jobs/schedule', [
            'pageTitle' => 'Scheduling Board',
            'statusScope' => $statusScope,
            'search' => $search,
            'unscheduledJobs' => $unscheduledJobs,
            'pageStyles' => $pageStyles,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function events(): void
    {
        require_permission('jobs', 'view');

        $statusScope = $this->normalizeStatusScope($_GET['status_scope'] ?? 'dispatch');
        $statuses = $this->scopeToStatuses($statusScope);
        $start = trim((string) ($_GET['start'] ?? ''));
        $end = trim((string) ($_GET['end'] ?? ''));

        $events = Job::calendarEvents($start, $end, $statuses, 'active');
        json_response(['events' => $events]);
    }

    public function update(): void
    {
        require_permission('jobs', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            json_response([
                'success' => false,
                'message' => 'Session expired. Refresh and try again.',
            ], 419);
            return;
        }

        $jobId = $this->toIntOrNull($_POST['job_id'] ?? null);
        if ($jobId === null || $jobId <= 0) {
            json_response([
                'success' => false,
                'message' => 'Invalid job.',
            ], 422);
            return;
        }

        $scheduledAt = $this->toDateTimeOrNull($_POST['scheduled_date'] ?? null);
        if ($scheduledAt === null) {
            json_response([
                'success' => false,
                'message' => 'A valid date/time is required.',
            ], 422);
            return;
        }

        $scheduledEndAt = $this->toDateTimeOrNull($_POST['end_date'] ?? null);
        if ($scheduledEndAt !== null) {
            $startTs = strtotime($scheduledAt);
            $endTs = strtotime($scheduledEndAt);
            if ($startTs === false || $endTs === false || $endTs <= $startTs) {
                json_response([
                    'success' => false,
                    'message' => 'End time must be after start time.',
                ], 422);
                return;
            }
        }

        $job = Job::findById($jobId);
        if (!$job || !empty($job['deleted_at']) || (int) ($job['active'] ?? 1) !== 1) {
            json_response([
                'success' => false,
                'message' => 'Job is unavailable.',
            ], 404);
            return;
        }

        $updated = Job::updateScheduledDate($jobId, $scheduledAt, auth_user_id(), $scheduledEndAt);
        if (!$updated) {
            json_response([
                'success' => false,
                'message' => 'No scheduling changes were applied.',
            ], 409);
            return;
        }

        $jobName = trim((string) ($job['name'] ?? ('Job #' . $jobId)));
        $windowNote = $scheduledEndAt !== null
            ? ('Scheduled date set to ' . $scheduledAt . ' - ' . $scheduledEndAt . ' from scheduling board.')
            : ('Scheduled date set to ' . $scheduledAt . ' from scheduling board.');
        Job::createAction($jobId, [
            'action_type' => 'schedule_updated',
            'action_at' => date('Y-m-d H:i:s'),
            'amount' => null,
            'ref_table' => 'jobs',
            'ref_id' => $jobId,
            'note' => $windowNote,
        ], auth_user_id());
        log_user_action(
            'job_rescheduled',
            'jobs',
            $jobId,
            'Rescheduled ' . $jobName . ' to ' . $scheduledAt . ($scheduledEndAt !== null ? (' - ' . $scheduledEndAt) : '') . '.'
        );

        json_response([
            'success' => true,
            'scheduled_date' => $scheduledAt,
            'end_date' => $scheduledEndAt,
            'message' => 'Job rescheduled.',
        ]);
    }

    private function normalizeStatusScope(mixed $value): string
    {
        $scope = strtolower(trim((string) ($value ?? 'dispatch')));
        return match ($scope) {
            'dispatch', 'all', 'pending', 'active', 'complete', 'cancelled' => $scope,
            default => 'dispatch',
        };
    }

    private function scopeToStatuses(string $scope): array
    {
        return match ($scope) {
            'dispatch' => ['pending', 'active'],
            'pending' => ['pending'],
            'active' => ['active'],
            'complete' => ['complete'],
            'cancelled' => ['cancelled'],
            default => [],
        };
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function toDateTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
