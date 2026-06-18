<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Employee;
use App\Models\Job;
use App\Models\TimeEntry;
use Core\ApiController;

final class TimeTrackingController extends ApiController
{
    public function punchBoard(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        if (!$canManageEmployees && $selfEmployee === null) {
            $this->ok([
                'employees' => [],
                'jobs' => $this->serializeJobOptions(TimeEntry::punchBoardJobOptions($businessId, 200)),
                'recent_entries' => [],
                'employee_link_missing' => true,
            ]);
        }

        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;
        $employees = TimeEntry::punchBoardEmployees($businessId, $scopeEmployeeId);
        $recentEntries = [];
        if ($scopeEmployeeId !== null && $scopeEmployeeId > 0) {
            $recentEntries = TimeEntry::indexList($businessId, '', '', 25, 0, $scopeEmployeeId);
        }

        $this->ok([
            'employees' => array_map([$this, 'serializePunchEmployee'], $employees),
            'jobs' => $this->serializeJobOptions(TimeEntry::punchBoardJobOptions($businessId, 200)),
            'recent_entries' => array_map([$this, 'serializeRecentEntry'], $recentEntries),
            'employee_link_missing' => false,
            'can_manage_employees' => $canManageEmployees,
        ]);
    }

    public function punchIn(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $input = $this->input();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        $employeeId = (int) ($input['employee_id'] ?? 0);
        if (!$canManageEmployees) {
            if ($selfEmployee === null) {
                $this->fail('No employee profile is linked to your user.', 422);
            }
            $employeeId = (int) ($selfEmployee['id'] ?? 0);
        }

        if ($employeeId <= 0) {
            $this->fail('Employee is required.', 422);
        }

        $employee = Employee::findForBusiness($businessId, $employeeId);
        if ($employee === null || trim((string) ($employee['status'] ?? 'active')) === 'inactive') {
            $this->fail('Employee is not available.', 422);
        }

        if (TimeEntry::openEntryForEmployee($businessId, $employeeId) !== null) {
            $this->fail('Employee is already punched in.', 409);
        }

        $resolved = $this->resolvePunchJobSelection(
            $businessId,
            (string) ($input['job_selection'] ?? ''),
            (string) ($input['notes'] ?? '')
        );
        if (isset($resolved['error'])) {
            $this->fail('Choose a valid active job or non-job type.', 422);
        }

        $jobId = (int) $resolved['job_id'];
        $isNonJob = (bool) $resolved['is_non_job'];
        $notes = (string) $resolved['notes'];
        $clockInAt = date('Y-m-d H:i:s');

        if (TimeEntry::hasOverlapForEmployee($businessId, $employeeId, $clockInAt, null)) {
            $this->fail('Employee already has overlapping time.', 409);
        }

        $entryId = TimeEntry::punchInNow(
            $businessId,
            $employeeId,
            $isNonJob ? null : $jobId,
            $isNonJob,
            auth_user_id() ?? 0,
            $notes
        );
        if ($entryId <= 0) {
            $this->fail('Could not punch in.', 500);
        }

        if (!$isNonJob && $jobId > 0) {
            Job::assignEmployee($businessId, $jobId, $employeeId, auth_user_id() ?? 0);
        }

        $this->ok(['entry_id' => $entryId, 'punched_in' => true]);
    }

    public function punchOut(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $input = $this->input();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        $employeeId = (int) ($input['employee_id'] ?? 0);
        if (!$canManageEmployees) {
            if ($selfEmployee === null) {
                $this->fail('No employee profile is linked to your user.', 422);
            }
            $employeeId = (int) ($selfEmployee['id'] ?? 0);
        }

        if ($employeeId <= 0) {
            $this->fail('Employee is required.', 422);
        }

        $entryId = TimeEntry::punchOutOpenEntry($businessId, $employeeId, auth_user_id() ?? 0);
        if (($entryId ?? 0) <= 0) {
            $this->fail('No open time entry found.', 404);
        }

        $this->ok(['entry_id' => (int) $entryId, 'punched_out' => true]);
    }

    public function switchJob(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $input = $this->input();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        $employeeId = (int) ($input['employee_id'] ?? 0);
        if (!$canManageEmployees) {
            if ($selfEmployee === null) {
                $this->fail('No employee profile is linked to your user.', 422);
            }
            $employeeId = (int) ($selfEmployee['id'] ?? 0);
        }

        if ($employeeId <= 0) {
            $this->fail('Employee is required.', 422);
        }

        $employee = Employee::findForBusiness($businessId, $employeeId);
        if ($employee === null || trim((string) ($employee['status'] ?? 'active')) === 'inactive') {
            $this->fail('Employee is not available.', 422);
        }

        $resolved = $this->resolvePunchJobSelection(
            $businessId,
            (string) ($input['job_selection'] ?? ''),
            (string) ($input['notes'] ?? '')
        );
        if (isset($resolved['error'])) {
            $this->fail('Choose a valid active job or non-job type.', 422);
        }

        $jobId = (int) $resolved['job_id'];
        $isNonJob = (bool) $resolved['is_non_job'];
        $notes = (string) $resolved['notes'];

        $result = TimeEntry::punchSwitchJob(
            $businessId,
            $employeeId,
            auth_user_id() ?? 0,
            $jobId > 0 ? $jobId : null,
            $isNonJob,
            $notes
        );

        if (($result['ok'] ?? false) !== true) {
            $err = (string) ($result['error'] ?? '');
            if ($err === 'noop') {
                $this->fail('Already on that job.', 409);
            }
            if ($err === 'no_open') {
                $this->fail('No open punch found. Punch in first.', 404);
            }
            $this->fail('Unable to switch jobs.', 500);
        }

        if (!$isNonJob && $jobId > 0) {
            Job::assignEmployee($businessId, $jobId, $employeeId, auth_user_id() ?? 0);
        }

        $this->ok(['switched' => true]);
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     * @return list<array<string, mixed>>
     */
    private function serializeJobOptions(array $jobs): array
    {
        $options = [
            ['value' => 'shop_time', 'label' => 'Shop Time', 'type' => 'non_job'],
            ['value' => 'general_labor', 'label' => 'General Labor', 'type' => 'non_job'],
        ];

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }
            $id = (int) ($job['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $title = trim((string) ($job['title'] ?? ''));
            $city = trim((string) ($job['city'] ?? ''));
            $label = $title !== '' ? $title : ('Job #' . (string) $id);
            if ($city !== '') {
                $label .= ' - ' . $city;
            }
            $options[] = [
                'value' => (string) $id,
                'label' => $label,
                'type' => 'job',
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $employee
     * @return array<string, mixed>
     */
    private function serializePunchEmployee(array $employee): array
    {
        $openEntryId = (int) ($employee['open_entry_id'] ?? 0);
        return [
            'id' => (int) ($employee['id'] ?? 0),
            'display_name' => trim((string) ($employee['linked_user_name'] ?? $employee['employee_name'] ?? '')),
            'is_punched_in' => $openEntryId > 0,
            'open_entry_id' => $openEntryId > 0 ? $openEntryId : null,
            'open_job_id' => (int) ($employee['open_job_id'] ?? 0) ?: null,
            'open_job_title' => trim((string) ($employee['open_job_title'] ?? '')) ?: null,
            'open_clock_in_at' => trim((string) ($employee['open_clock_in_at'] ?? '')) ?: null,
            'open_notes' => trim((string) ($employee['open_notes'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function serializeRecentEntry(array $entry): array
    {
        $durationMinutes = (int) ($entry['duration_minutes'] ?? 0);
        $clockOutAt = trim((string) ($entry['clock_out_at'] ?? ''));

        return [
            'id' => (int) ($entry['id'] ?? 0),
            'job_title' => trim((string) ($entry['job_title'] ?? '')) ?: 'Non-Job Time',
            'clock_in_at' => (string) ($entry['clock_in_at'] ?? ''),
            'clock_out_at' => $clockOutAt !== '' ? $clockOutAt : null,
            'duration_hours' => $durationMinutes > 0 ? round($durationMinutes / 60, 2) : null,
            'status' => $clockOutAt === '' ? 'open' : 'closed',
        ];
    }

    /**
     * @return array{job_id: int, is_non_job: bool, notes: string}|array{error: string}
     */
    private function resolvePunchJobSelection(int $businessId, string $jobSelection, string $notes): array
    {
        $jobSelection = trim($jobSelection);
        $notes = trim($notes);
        $specialSelections = [
            'shop_time' => 'Shop Time',
            'general_labor' => 'General Labor',
        ];
        $jobId = 0;
        $isNonJob = true;

        if ($jobSelection !== '' && isset($specialSelections[$jobSelection])) {
            $specialLabel = $specialSelections[$jobSelection];
            $notes = $notes !== '' ? ($specialLabel . ' - ' . $notes) : $specialLabel;
        } else {
            $jobId = (int) $jobSelection;
            $isNonJob = $jobId <= 0;
            if (!$isNonJob && !TimeEntry::jobExistsForBusiness($businessId, $jobId)) {
                return ['error' => 'invalid_job'];
            }
        }

        return [
            'job_id' => $jobId,
            'is_non_job' => $isNonJob,
            'notes' => $notes,
        ];
    }

    private function canManageEmployees(): bool
    {
        return is_site_admin() || workspace_role() === 'admin';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentUserEmployee(int $businessId): ?array
    {
        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            return null;
        }

        return Employee::findByUserForBusiness($businessId, $userId);
    }
}
