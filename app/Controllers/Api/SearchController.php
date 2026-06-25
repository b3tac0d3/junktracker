<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Client;
use App\Models\Job;
use Core\ApiController;

final class SearchController extends ApiController
{
    public function jobs(): void
    {
        $this->requireBusinessRole(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $q = trim((string) ($_GET['q'] ?? ''));
        $limit = min(max((int) ($_GET['limit'] ?? 20), 1), 50);

        $jobs = Job::indexList($businessId, $q, '', '', $limit, 0);
        $results = [];
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
            $results[] = [
                'id' => $id,
                'title' => $label,
                'status' => (string) ($job['status'] ?? ''),
            ];
        }

        $this->ok(['results' => $results]);
    }

    public function clients(): void
    {
        $this->requireBusinessRole(['general_user', 'admin']);

        $businessId = current_business_id();
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            $this->ok(['results' => []]);
        }

        $clients = Client::searchOptions($businessId, $q, 20);
        $results = [];
        foreach ($clients as $client) {
            if (!is_array($client)) {
                continue;
            }
            $id = (int) ($client['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $results[] = [
                'id' => $id,
                'name' => trim((string) ($client['name'] ?? $client['company_name'] ?? '')),
                'phone' => trim((string) ($client['phone'] ?? '')),
            ];
        }

        $this->ok(['results' => $results]);
    }
}
