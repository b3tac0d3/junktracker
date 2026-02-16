<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Job;
use App\Models\TimeEntry;
use Core\Controller;

final class TimeTrackingController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'employee_id' => $this->toIntOrNull($_GET['employee_id'] ?? null),
            'job_id' => $this->toIntOrNull($_GET['job_id'] ?? null),
            'start_date' => trim((string) ($_GET['start_date'] ?? '')),
            'end_date' => trim((string) ($_GET['end_date'] ?? '')),
            'record_status' => (string) ($_GET['record_status'] ?? 'active'),
        ];

        if ($filters['start_date'] === '' && $filters['end_date'] === '') {
            $filters['start_date'] = date('Y-m-01');
            $filters['end_date'] = date('Y-m-t');
        }

        if (!in_array($filters['record_status'], ['active', 'deleted', 'all'], true)) {
            $filters['record_status'] = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/time-tracking-table.js') . '"></script>',
        ]);

        $summary = TimeEntry::summary($filters);

        $this->render('time_tracking/index', [
            'pageTitle' => 'Time Tracking',
            'filters' => $filters,
            'entries' => TimeEntry::filter($filters),
            'summary' => $summary,
            'byEmployee' => TimeEntry::summaryByEmployee($filters),
            'employees' => TimeEntry::employees(),
            'jobs' => TimeEntry::jobs(),
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        $jobId = $this->toIntOrNull($_GET['job_id'] ?? null);
        $employees = TimeEntry::employees();
        $jobs = TimeEntry::jobs();

        $selectedJobId = null;
        if ($jobId !== null && $this->optionExists($jobId, $jobs)) {
            $selectedJobId = $jobId;
        }

        $returnTo = $this->sanitizeReturnTo($_GET['return_to'] ?? null, $selectedJobId);

        $this->render('time_tracking/create', [
            'pageTitle' => 'Add Time Entry',
            'entry' => [
                'job_id' => $selectedJobId,
                'work_date' => date('Y-m-d'),
            ],
            'employees' => $employees,
            'jobs' => $jobs,
            'returnTo' => $returnTo,
            'formAction' => '/time-tracking/new',
            'cancelUrl' => $returnTo,
            'pageScripts' => implode("\n", [
                '<script src="' . asset('js/time-entry-lookup.js') . '"></script>',
                '<script src="' . asset('js/time-entry-form.js') . '"></script>',
            ]),
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/time-tracking/new');
        }

        $employees = TimeEntry::employees();
        $jobs = TimeEntry::jobs();
        $data = $this->collectFormData($_POST, $employees, $jobs);
        $errors = $data['errors'];
        $returnTo = (string) $data['return_to'];
        $jobId = $data['job_id'] !== null ? (int) $data['job_id'] : null;

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/time-tracking/new' . ($jobId !== null ? '?job_id=' . $jobId : ''));
        }

        $entryId = TimeEntry::create([
            'employee_id' => (int) $data['employee_id'],
            'job_id' => (int) $data['job_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'minutes_worked' => $data['minutes_worked'],
            'pay_rate' => $data['pay_rate'],
            'total_paid' => $data['total_paid'],
            'note' => $data['note'],
        ], auth_user_id());

        $jobActionAt = date('Y-m-d H:i:s');
        if ($data['work_date'] !== null) {
            $jobActionAt = $data['work_date'] . ' ' . ($data['start_time'] ?? '12:00:00');
        }

        Job::createAction((int) $data['job_id'], [
            'action_type' => 'time_entry_added',
            'action_at' => $jobActionAt,
            'amount' => $data['total_paid'],
            'ref_table' => 'employee_time_entries',
            'ref_id' => $entryId,
            'note' => 'Time entry added (' . $this->formatMinutes((int) $data['minutes_worked']) . ').',
        ], auth_user_id());

        flash('success', 'Time entry added.');
        redirect($returnTo);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }

        $isActive = empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1;

        $this->render('time_tracking/show', [
            'pageTitle' => 'Time Entry',
            'entry' => $entry,
            'isActive' => $isActive,
            'returnTo' => $this->sanitizeReturnTo($_GET['return_to'] ?? null, isset($entry['job_id']) ? (int) $entry['job_id'] : null),
        ]);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }

        $isActive = empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1;
        if (!$isActive) {
            flash('error', 'This time entry is inactive and cannot be edited.');
            redirect('/time-tracking/' . $id);
        }

        $returnTo = $this->sanitizeReturnTo($_GET['return_to'] ?? null, isset($entry['job_id']) ? (int) $entry['job_id'] : null);

        $this->render('time_tracking/edit', [
            'pageTitle' => 'Edit Time Entry',
            'entry' => $entry,
            'employees' => TimeEntry::employees(),
            'jobs' => TimeEntry::jobs(),
            'returnTo' => $returnTo,
            'formAction' => '/time-tracking/' . $id . '/edit',
            'cancelUrl' => '/time-tracking/' . $id . '?return_to=' . urlencode($returnTo),
            'pageScripts' => implode("\n", [
                '<script src="' . asset('js/time-entry-lookup.js') . '"></script>',
                '<script src="' . asset('js/time-entry-form.js') . '"></script>',
            ]),
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $existing = TimeEntry::findById($id);
        if (!$existing) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/time-tracking/' . $id . '/edit');
        }

        $isActive = empty($existing['deleted_at']) && (int) ($existing['active'] ?? 1) === 1;
        if (!$isActive) {
            flash('error', 'This time entry is inactive and cannot be edited.');
            redirect('/time-tracking/' . $id);
        }

        $employees = TimeEntry::employees();
        $jobs = TimeEntry::jobs();
        $data = $this->collectFormData($_POST, $employees, $jobs);
        $errors = $data['errors'];
        $returnTo = (string) $data['return_to'];
        $jobId = $data['job_id'] !== null ? (int) $data['job_id'] : null;

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/time-tracking/' . $id . '/edit' . ($jobId !== null ? '?job_id=' . $jobId : ''));
        }

        TimeEntry::update($id, [
            'employee_id' => (int) $data['employee_id'],
            'job_id' => (int) $data['job_id'],
            'work_date' => $data['work_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'minutes_worked' => $data['minutes_worked'],
            'pay_rate' => $data['pay_rate'],
            'total_paid' => $data['total_paid'],
            'note' => $data['note'],
        ], auth_user_id());

        Job::createAction((int) $data['job_id'], [
            'action_type' => 'time_entry_updated',
            'action_at' => date('Y-m-d H:i:s'),
            'amount' => $data['total_paid'],
            'ref_table' => 'employee_time_entries',
            'ref_id' => $id,
            'note' => 'Time entry updated (' . $this->formatMinutes((int) $data['minutes_worked']) . ').',
        ], auth_user_id());

        flash('success', 'Time entry updated.');
        redirect('/time-tracking/' . $id . '?return_to=' . urlencode($returnTo));
    }

    public function delete(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/time-tracking/' . $id);
        }

        $isActive = empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1;
        if (!$isActive) {
            flash('error', 'This time entry is already inactive.');
            redirect('/time-tracking/' . $id);
        }

        TimeEntry::softDelete($id, auth_user_id());

        $jobId = isset($entry['job_id']) ? (int) $entry['job_id'] : 0;
        if ($jobId > 0) {
            Job::createAction($jobId, [
                'action_type' => 'time_entry_deleted',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => isset($entry['paid_calc']) ? (float) $entry['paid_calc'] : null,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $id,
                'note' => 'Time entry soft deleted.',
            ], auth_user_id());
        }

        flash('success', 'Time entry deleted.');
        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? null, $jobId > 0 ? $jobId : null);
        redirect($returnTo);
    }

    public function employeeLookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(TimeEntry::lookupEmployees($term));
    }

    public function jobLookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(TimeEntry::lookupJobs($term));
    }

    private function sanitizeReturnTo(mixed $value, ?int $jobId = null): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw !== '') {
            if (preg_match('#^/jobs/[0-9]+$#', $raw) || preg_match('#^/time-tracking(?:/.*)?$#', $raw)) {
                return $raw;
            }
        }

        if ($jobId !== null && $jobId > 0) {
            return '/jobs/' . $jobId;
        }

        return '/time-tracking';
    }

    private function collectFormData(array $source, array $employees, array $jobs): array
    {
        $employeeId = $this->toIntOrNull($source['employee_id'] ?? null);
        $jobId = $this->toIntOrNull($source['job_id'] ?? null);
        $workDate = $this->toDateOrNull($source['work_date'] ?? null);
        $startTime = $this->toTimeOrNull($source['start_time'] ?? null);
        $endTime = $this->toTimeOrNull($source['end_time'] ?? null);
        $minutesWorked = $this->toIntOrNull($source['minutes_worked'] ?? null);
        $payRate = $this->toDecimalOrNull($source['pay_rate'] ?? null);
        $totalPaid = $this->toDecimalOrNull($source['total_paid'] ?? null);
        $note = trim((string) ($source['note'] ?? ''));
        $returnTo = $this->sanitizeReturnTo($source['return_to'] ?? null, $jobId);

        $errors = [];
        if ($employeeId === null || !$this->optionExists($employeeId, $employees)) {
            $errors[] = 'Select a valid employee.';
        }
        if ($jobId === null || !$this->optionExists($jobId, $jobs)) {
            $errors[] = 'Select a valid job.';
        }
        if ($workDate === null) {
            $errors[] = 'Work date is required.';
        }
        if (($startTime === null) !== ($endTime === null)) {
            $errors[] = 'Provide both start and end time, or leave both blank.';
        }
        if ($minutesWorked !== null && $minutesWorked <= 0) {
            $errors[] = 'Minutes worked must be greater than zero.';
        }
        if ($minutesWorked === null && $startTime !== null && $endTime !== null) {
            $minutesWorked = $this->minutesBetween($startTime, $endTime);
            if ($minutesWorked <= 0) {
                $errors[] = 'End time must be after start time.';
            }
        }
        if ($minutesWorked === null && $startTime === null && $endTime === null) {
            $errors[] = 'Provide minutes worked or a start/end time.';
        }
        if ($payRate !== null && $payRate < 0) {
            $errors[] = 'Pay rate must be zero or greater.';
        }
        if ($totalPaid !== null && $totalPaid < 0) {
            $errors[] = 'Total paid must be zero or greater.';
        }

        if ($payRate === null && $employeeId !== null) {
            $payRate = TimeEntry::employeeRate($employeeId) ?? 0.0;
        }
        if ($totalPaid === null && $minutesWorked !== null) {
            $totalPaid = round(((float) $payRate * (float) $minutesWorked) / 60, 2);
        }

        return [
            'errors' => $errors,
            'employee_id' => $employeeId,
            'job_id' => $jobId,
            'work_date' => $workDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'minutes_worked' => $minutesWorked,
            'pay_rate' => $payRate,
            'total_paid' => $totalPaid,
            'note' => $note,
            'return_to' => $returnTo,
        ];
    }

    private function optionExists(int $id, array $options): bool
    {
        foreach ($options as $option) {
            if ((int) ($option['id'] ?? 0) === $id) {
                return true;
            }
        }

        return false;
    }

    private function minutesBetween(string $startTime, string $endTime): int
    {
        $start = strtotime('1970-01-01 ' . $startTime);
        $end = strtotime('1970-01-01 ' . $endTime);
        if ($start === false || $end === false) {
            return 0;
        }

        if ($end < $start) {
            $end += 86400;
        }

        return (int) floor(($end - $start) / 60);
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h 00m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours . 'h ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function toDateOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function toTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('H:i:s', $time);
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function toDecimalOrNull(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        if (class_exists('App\\Controllers\\ErrorController')) {
            (new \App\Controllers\ErrorController())->notFound();
            return;
        }

        echo '404 Not Found';
    }
}
