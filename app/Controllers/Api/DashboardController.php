<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\DashboardSummary;
use App\Models\Employee;
use App\Models\TimeEntry;
use Core\ApiController;

final class DashboardController extends ApiController
{
    public function today(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $userId = auth_user_id() ?? 0;
        $summary = DashboardSummary::byBusiness($businessId, $userId);

        $selfEmployee = Employee::findByUserForBusiness($businessId, $userId);
        $selfOpenEntry = null;
        if (is_array($selfEmployee) && ((int) ($selfEmployee['id'] ?? 0)) > 0) {
            $selfOpenEntry = TimeEntry::openEntryForEmployee($businessId, (int) $selfEmployee['id']);
        }

        $lists = is_array($summary['lists'] ?? null) ? $summary['lists'] : [];

        $this->ok([
            'date' => date('Y-m-d'),
            'upcoming_schedule' => $lists['upcoming_schedule'] ?? [],
            'past_due_schedule' => $lists['past_due_schedule'] ?? [],
            'my_tasks_due' => workspace_role() === 'punch_only' ? [] : ($lists['my_tasks_due'] ?? []),
            'jobs' => $summary['jobs'] ?? [],
            'self_employee' => $this->serializeEmployee($selfEmployee),
            'open_entry' => $this->serializeOpenEntry($selfOpenEntry),
        ]);
    }

    /**
     * @param array<string, mixed>|null $employee
     * @return array<string, mixed>|null
     */
    private function serializeEmployee(?array $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => (int) ($employee['id'] ?? 0),
            'display_name' => trim((string) ($employee['display_name'] ?? $employee['employee_name'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>|null
     */
    private function serializeOpenEntry(?array $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        return [
            'id' => (int) ($entry['id'] ?? 0),
            'clock_in_at' => (string) ($entry['clock_in_at'] ?? ''),
            'job_id' => (int) ($entry['job_id'] ?? 0) ?: null,
            'job_title' => trim((string) ($entry['job_title'] ?? '')) ?: null,
            'notes' => trim((string) ($entry['notes'] ?? '')),
        ];
    }
}
