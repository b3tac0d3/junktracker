<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use Core\Controller;

final class ClientsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $businessId = current_business_id();

        $clients = Client::indexList($businessId, $search);

        $this->render('clients/index', [
            'pageTitle' => 'Clients',
            'search' => $search,
            'clients' => $clients,
        ]);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        if ($clientId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $financial = Client::financialSummary($businessId, $clientId);
        $jobStatusSummary = Client::jobsByStatus($businessId, $clientId);
        $jobs = Client::jobHistory($businessId, $clientId, 50);

        $this->render('clients/show', [
            'pageTitle' => 'Client',
            'client' => $client,
            'financial' => $financial,
            'jobStatusSummary' => $jobStatusSummary,
            'jobs' => $jobs,
        ]);
    }
}
