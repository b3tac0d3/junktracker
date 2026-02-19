<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Task;
use Core\Controller;

final class ClientsController extends Controller
{
    private const CLIENT_TYPES = ['client', 'realtor', 'other'];

    public function index(): void
    {
        $this->authorize('view');

        $query = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'active');

        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/clients-table.js') . '"></script>',
            '<script src="' . asset('js/clients-search-autocomplete.js') . '"></script>',
        ]);

        $this->render('clients/index', [
            'pageTitle' => 'Clients',
            'clients' => Client::search($query, $status),
            'query' => $query,
            'status' => $status,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $this->authorize('view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/clients');
        }

        $client = Client::findById($id);
        if (!$client) {
            $this->renderNotFound();
            return;
        }

        $this->render('clients/show', [
            'pageTitle' => 'Client Details',
            'client' => $client,
            'companies' => Client::linkedCompanies($id),
            'tasks' => Task::forLinkedRecord('client', $id),
            'contacts' => ClientContact::filter([
                'client_id' => $id,
                'record_status' => 'all',
                'q' => '',
            ]),
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $this->render('clients/create', [
            'pageTitle' => 'Add Client',
            'client' => null,
            'clientTypes' => self::CLIENT_TYPES,
            'duplicateMatches' => [],
            'requireDuplicateConfirm' => false,
            'selectedCompany' => $this->resolveSelectedCompany(null),
            'pageScripts' => $this->createFormScripts(),
        ]);

        clear_old();
    }

    public function store(): void
    {
        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/clients/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/clients/new');
        }

        $companyId = $this->toIntOrNull($_POST['company_id'] ?? null);
        $actorId = auth_user_id();
        $data['active'] = 1;
        $forceCreate = isset($_POST['force_create']) && (string) $_POST['force_create'] === '1';

        $duplicateMatches = Client::findPotentialDuplicates($data);
        if (!$forceCreate && !empty($duplicateMatches)) {
            $this->render('clients/create', [
                'pageTitle' => 'Add Client',
                'client' => $data,
                'clientTypes' => self::CLIENT_TYPES,
                'duplicateMatches' => $duplicateMatches,
                'requireDuplicateConfirm' => true,
                'selectedCompany' => $companyId !== null ? Company::findById($companyId) : null,
                'pageScripts' => $this->createFormScripts(),
            ]);
            return;
        }

        $clientId = Client::create($data, $actorId);
        Client::syncCompanyLink($clientId, $companyId, $actorId);

        flash('success', 'Client added.');
        redirect('/clients/' . $clientId);
    }

    public function edit(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $client = $id > 0 ? Client::findById($id) : null;

        if (!$client) {
            $this->renderNotFound();
            return;
        }

        $this->render('clients/edit', [
            'pageTitle' => 'Edit Client',
            'client' => $client,
            'clientTypes' => self::CLIENT_TYPES,
            'selectedCompany' => $this->resolveSelectedCompany($id),
            'pageScripts' => '<script src="' . asset('js/client-company-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/clients');
        }

        $client = Client::findById($id);
        if (!$client) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/clients/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/clients/' . $id . '/edit');
        }

        $companyId = $this->toIntOrNull($_POST['company_id'] ?? null);
        $actorId = auth_user_id();
        $data['active'] = (int) ($client['active'] ?? 1);

        Client::update($id, $data, $actorId);
        Client::syncCompanyLink($id, $companyId, $actorId);

        flash('success', 'Client updated.');
        redirect('/clients/' . $id);
    }

    public function deactivate(array $params): void
    {
        $this->authorize('delete');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/clients');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/clients/' . $id);
        }

        $client = Client::findById($id);
        if (!$client) {
            $this->renderNotFound();
            return;
        }

        if (empty($client['deleted_at']) && !empty($client['active'])) {
            $actorId = auth_user_id();
            Client::deactivate($id, $actorId);
            flash('success', 'Client deactivated.');
        } else {
            flash('success', 'Client is already inactive.');
        }

        redirect('/clients/' . $id);
    }

    public function lookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Client::lookupByName($term));
    }

    public function companyLookup(): void
    {
        $canLookup = can_access('clients', 'view')
            || can_access('clients', 'create')
            || can_access('clients', 'edit');
        if (!$canLookup) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([]);
            return;
        }

        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Company::lookupByName($term));
    }

    public function duplicateCheck(): void
    {
        $this->authorize('view');

        $payload = [
            'first_name' => trim((string) ($_GET['first_name'] ?? '')),
            'last_name' => trim((string) ($_GET['last_name'] ?? '')),
            'phone' => trim((string) ($_GET['phone'] ?? '')),
            'email' => trim((string) ($_GET['email'] ?? '')),
            'zip' => trim((string) ($_GET['zip'] ?? '')),
        ];

        $excludeId = $this->toIntOrNull($_GET['exclude_id'] ?? null);
        $matches = Client::findPotentialDuplicates($payload, $excludeId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'matches' => $matches,
        ]);
    }

    public function quickCreate(): void
    {
        $this->authorize('create');

        header('Content-Type: application/json; charset=utf-8');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode([
                'ok' => false,
                'message' => 'Session expired. Refresh and try again.',
                'csrf_token' => csrf_token(),
            ]);
            return;
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($firstName === '' && $lastName === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Provide at least a first or last name.',
                'csrf_token' => csrf_token(),
            ]);
            return;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Email is invalid.',
                'csrf_token' => csrf_token(),
            ]);
            return;
        }

        $clientId = Client::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'business_name' => '',
            'phone' => $phone,
            'can_text' => 0,
            'email' => $email,
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'client_type' => 'client',
            'note' => '',
            'active' => 1,
        ], auth_user_id());

        $client = Client::findById($clientId);
        $label = trim((string) (($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
        if ($label === '') {
            $label = 'Client #' . $clientId;
        }

        echo json_encode([
            'ok' => true,
            'created' => true,
            'client' => [
                'id' => $clientId,
                'label' => $label,
            ],
            'csrf_token' => csrf_token(),
        ]);
    }

    private function resolveSelectedCompany(?int $clientId): ?array
    {
        $oldCompanyId = $this->toIntOrNull(old('company_id'));
        if ($oldCompanyId !== null && $oldCompanyId > 0) {
            return Company::findById($oldCompanyId);
        }

        if ($clientId !== null && $clientId > 0) {
            return Client::primaryCompany($clientId);
        }

        return null;
    }

    private function authorize(string $action): void
    {
        require_permission('clients', $action);
    }

    private function collectFormData(): array
    {
        $clientType = trim((string) ($_POST['client_type'] ?? 'client'));
        if (!in_array($clientType, self::CLIENT_TYPES, true)) {
            $clientType = 'client';
        }

        return [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'business_name' => '',
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'can_text' => isset($_POST['can_text']) ? 1 : 0,
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'client_type' => $clientType,
            'note' => trim((string) ($_POST['note'] ?? '')),
            'active' => 1,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['first_name'] === '' && $data['last_name'] === '') {
            $errors[] = 'Provide at least a first name or last name.';
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }

        if ($data['state'] !== '' && strlen($data['state']) !== 2) {
            $errors[] = 'State must be a 2-letter value.';
        }

        if (!in_array($data['client_type'], self::CLIENT_TYPES, true)) {
            $errors[] = 'Client type is invalid.';
        }

        $companyId = $this->toIntOrNull($_POST['company_id'] ?? null);
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        if ($companyName !== '' && ($companyId === null || $companyId <= 0)) {
            $errors[] = 'Select a company from the suggestions.';
        } elseif ($companyId !== null && $companyId > 0 && !Company::findById($companyId)) {
            $errors[] = 'Select a valid company.';
        }

        return $errors;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
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

    private function createFormScripts(): string
    {
        return implode("\n", [
            '<script src="' . asset('js/client-company-lookup.js') . '"></script>',
            '<script src="' . asset('js/client-duplicate-check.js') . '"></script>',
        ]);
    }
}
