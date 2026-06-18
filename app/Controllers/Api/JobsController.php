<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\FormSelectValue;
use App\Models\Job;
use App\Services\GoogleCalendarSync;
use Core\ApiController;

final class JobsController extends ApiController
{
    public function index(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $search = trim((string) ($_GET['q'] ?? ''));
        $scope = strtolower(trim((string) ($_GET['scope'] ?? 'active')));
        $limit = min(max((int) ($_GET['limit'] ?? 50), 1), 100);

        $jobs = Job::indexList($businessId, $search, $scope, '', $limit, 0);

        $this->ok([
            'jobs' => array_map([$this, 'serializeJobSummary'], $jobs),
        ]);
    }

    public function show(array $params): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            $this->fail('Job not found.', 404);
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            $this->fail('Job not found.', 404);
        }

        $this->ok([
            'job' => $this->serializeJobDetail($job),
            'status_options' => workspace_role() === 'punch_only'
                ? []
                : $this->jobStatusOptions($businessId),
        ]);
    }

    public function quickStatus(array $params): void
    {
        $this->requireBusinessRole(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            $this->fail('Job not found.', 404);
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            $this->fail('Job not found.', 404);
        }

        $input = $this->input();
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $allowed = $this->jobStatusOptions($businessId);
        if (!in_array($status, $allowed, true)) {
            $this->fail('Invalid status.', 422);
        }

        $current = strtolower(trim((string) ($job['status'] ?? '')));
        if ($status === $current) {
            $this->ok(['job_id' => $jobId, 'status' => $status, 'changed' => false]);
        }

        $actor = auth_user_id() ?? 0;
        if (!Job::updateStatus($businessId, $jobId, $status, $actor)) {
            $this->fail('Could not update status.', 500);
        }

        audit('job_status_updated', 'jobs', $jobId, ['status' => $status]);
        GoogleCalendarSync::syncJob($actor, $businessId, $jobId);

        $this->ok(['job_id' => $jobId, 'status' => $status, 'changed' => true]);
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function serializeJobSummary(array $job): array
    {
        return [
            'id' => (int) ($job['id'] ?? 0),
            'title' => (string) ($job['title'] ?? ''),
            'status' => (string) ($job['status'] ?? ''),
            'city' => trim((string) ($job['city'] ?? '')),
            'scheduled_start_at' => trim((string) ($job['scheduled_start_at'] ?? '')) ?: null,
            'client_name' => trim((string) ($job['client_name'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function serializeJobDetail(array $job): array
    {
        $addressParts = array_filter([
            trim((string) ($job['address_line1'] ?? '')),
            trim((string) ($job['address_line2'] ?? '')),
            trim((string) ($job['city'] ?? '')),
            trim((string) ($job['state'] ?? '')),
            trim((string) ($job['postal_code'] ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return [
            'id' => (int) ($job['id'] ?? 0),
            'title' => (string) ($job['title'] ?? ''),
            'status' => (string) ($job['status'] ?? ''),
            'job_type' => trim((string) ($job['job_type'] ?? '')) ?: null,
            'scheduled_start_at' => trim((string) ($job['scheduled_start_at'] ?? '')) ?: null,
            'scheduled_end_at' => trim((string) ($job['scheduled_end_at'] ?? '')) ?: null,
            'notes' => trim((string) ($job['notes'] ?? '')),
            'client_id' => (int) ($job['client_id'] ?? 0) ?: null,
            'client_name' => trim((string) ($job['client_name'] ?? '')),
            'client_phone' => trim((string) ($job['client_phone'] ?? '')),
            'address' => [
                'line1' => trim((string) ($job['address_line1'] ?? '')),
                'line2' => trim((string) ($job['address_line2'] ?? '')),
                'city' => trim((string) ($job['city'] ?? '')),
                'state' => trim((string) ($job['state'] ?? '')),
                'postal_code' => trim((string) ($job['postal_code'] ?? '')),
                'formatted' => implode(', ', $addressParts),
            ],
            'maps_directions_url' => maps_directions_url_from_parts([
                'address_line1' => (string) ($job['address_line1'] ?? ''),
                'address_line2' => (string) ($job['address_line2'] ?? ''),
                'city' => (string) ($job['city'] ?? ''),
                'state' => (string) ($job['state'] ?? ''),
                'postal_code' => (string) ($job['postal_code'] ?? ''),
            ]),
            'client_phone_url' => trim((string) ($job['client_phone'] ?? '')) !== ''
                ? ('tel:' . preg_replace('/[^\d+]/', '', (string) $job['client_phone']))
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function jobStatusOptions(int $businessId): array
    {
        $options = FormSelectValue::optionsForSection($businessId, 'job_status');
        $normalized = [];
        foreach ($options as $rawOption) {
            $option = strtolower(trim((string) $rawOption));
            if ($option === '' || in_array($option, $normalized, true)) {
                continue;
            }
            $normalized[] = $option;
        }

        return $normalized !== [] ? $normalized : ['prospect', 'pending', 'active', 'complete', 'cancelled'];
    }
}
