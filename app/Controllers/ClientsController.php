<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\FormSelectValue;
use App\Models\SchemaInspector;
use Core\Controller;

final class ClientsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'name')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'asc')));
        if (!in_array($sortBy, ['name', 'date', 'id'], true)) {
            $sortBy = 'name';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Client::indexCount($businessId, $search);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $clients = Client::indexList($businessId, $search, $perPage, $offset, $sortBy, $sortDir);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($clients));

        $this->render('clients/index', [
            'pageTitle' => 'Clients',
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'clients' => $clients,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $this->render('clients/form', [
            'pageTitle' => 'Add Client',
            'mode' => 'create',
            'actionUrl' => url('/clients'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('clients/form', [
                'pageTitle' => 'Add Client',
                'mode' => 'create',
                'actionUrl' => url('/clients'),
                'form' => $form,
                'errors' => $errors,
                'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            ]);
            return;
        }

        $clientId = Client::create($businessId, $this->payloadForSave($form, true), auth_user_id() ?? 0);
        flash('success', 'Client created.');
        redirect('/clients/' . (string) $clientId);
    }

    public function edit(array $params): void
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

        $this->render('clients/form', [
            'pageTitle' => 'Edit Client',
            'mode' => 'edit',
            'actionUrl' => url('/clients/' . (string) $clientId . '/update'),
            'form' => $this->formFromModel($client),
            'errors' => [],
            'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'clientId' => $clientId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        if ($clientId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/' . (string) $clientId . '/edit');
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('clients/form', [
                'pageTitle' => 'Edit Client',
                'mode' => 'edit',
                'actionUrl' => url('/clients/' . (string) $clientId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
                'clientId' => $clientId,
            ]);
            return;
        }

        Client::update($businessId, $clientId, $this->payloadForSave($form, false), auth_user_id() ?? 0);
        flash('success', 'Client updated.');
        redirect('/clients/' . (string) $clientId);
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
        $sales = Client::salesHistory($businessId, $clientId, 50);
        $purchases = Client::purchaseHistory($businessId, $clientId, 50);
        $contacts = ClientContact::forClient($businessId, $clientId, 50);

        $this->render('clients/show', [
            'pageTitle' => 'Client',
            'client' => $client,
            'financial' => $financial,
            'jobStatusSummary' => $jobStatusSummary,
            'jobs' => $jobs,
            'sales' => $sales,
            'purchases' => $purchases,
            'contacts' => $contacts,
        ]);
    }

    public function deactivate(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        if ($clientId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/' . (string) $clientId);
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        Client::deactivate($businessId, $clientId, auth_user_id() ?? 0);
        flash('success', 'Client deactivated.');
        redirect('/clients/' . (string) $clientId);
    }

    public function createContact(array $params): void
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

        if (!ClientContact::isAvailable()) {
            flash('error', 'Contact log table is missing. Run the latest migration.');
            redirect('/clients/' . (string) $clientId);
        }

        $this->render('clients/contact_form', [
            'pageTitle' => 'Add Contact',
            'client' => $client,
            'clientId' => $clientId,
            'actionUrl' => url('/clients/' . (string) $clientId . '/contacts'),
            'form' => $this->defaultContactForm(),
            'errors' => [],
        ]);
    }

    public function storeContact(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        if ($clientId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/' . (string) $clientId . '/contacts/create');
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!ClientContact::isAvailable()) {
            flash('error', 'Contact log table is missing. Run the latest migration.');
            redirect('/clients/' . (string) $clientId);
        }

        $form = $this->contactFormFromPost($_POST);
        $errors = $this->validateContactForm($form);
        if ($errors !== []) {
            $this->render('clients/contact_form', [
                'pageTitle' => 'Add Contact',
                'client' => $client,
                'clientId' => $clientId,
                'actionUrl' => url('/clients/' . (string) $clientId . '/contacts'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        ClientContact::create(
            $businessId,
            $clientId,
            [
                'contacted_at' => $this->toDatabaseDateTime($form['contacted_at']) ?? date('Y-m-d H:i:s'),
                'contact_type' => $form['contact_type'],
                'note' => $form['note'],
            ],
            auth_user_id() ?? 0
        );

        flash('success', 'Contact added.');
        redirect('/clients/' . (string) $clientId);
    }

    private function defaultForm(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'company_name' => '',
            'email' => '',
            'phone' => '',
            'secondary_phone' => '',
            'can_text' => '0',
            'secondary_can_text' => '0',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'client_type' => 'client',
            'primary_note' => '',
        ];
    }

    private function formFromModel(array $client): array
    {
        return [
            'first_name' => trim((string) ($client['first_name'] ?? '')),
            'last_name' => trim((string) ($client['last_name'] ?? '')),
            'company_name' => trim((string) ($client['company_name'] ?? '')),
            'email' => trim((string) ($client['email'] ?? '')),
            'phone' => trim((string) ($client['phone'] ?? '')),
            'secondary_phone' => trim((string) ($client['secondary_phone'] ?? '')),
            'can_text' => ((int) ($client['can_text'] ?? 0)) === 1 ? '1' : '0',
            'secondary_can_text' => ((int) ($client['secondary_can_text'] ?? 0)) === 1 ? '1' : '0',
            'address_line1' => trim((string) ($client['address_line1'] ?? '')),
            'address_line2' => trim((string) ($client['address_line2'] ?? '')),
            'city' => trim((string) ($client['city'] ?? '')),
            'state' => trim((string) ($client['state'] ?? '')),
            'postal_code' => trim((string) ($client['postal_code'] ?? '')),
            'client_type' => trim((string) ($client['client_type'] ?? 'client')),
            'primary_note' => trim((string) ($client['primary_note'] ?? '')),
        ];
    }

    private function formFromPost(array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $companyName = trim((string) ($input['company_name'] ?? ''));
        $clientType = strtolower(trim((string) ($input['client_type'] ?? '')));
        if ($clientType === '') {
            $clientType = ($firstName === '' && $lastName === '' && $companyName !== '') ? 'company' : 'client';
        }
        if ($firstName === '' && $lastName === '' && $companyName !== '') {
            $clientType = 'company';
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'secondary_phone' => trim((string) ($input['secondary_phone'] ?? '')),
            'can_text' => isset($input['can_text']) ? '1' : '0',
            'secondary_can_text' => isset($input['secondary_can_text']) ? '1' : '0',
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'client_type' => $clientType,
            'primary_note' => trim((string) ($input['primary_note'] ?? '')),
        ];
    }

    private function validateForm(array $form, int $businessId): array
    {
        $errors = [];

        if ($form['first_name'] === '' && $form['last_name'] === '' && $form['company_name'] === '') {
            $errors['first_name'] = 'Enter a first/last name or a company name.';
        }

        $allowedTypes = FormSelectValue::optionsForSection($businessId, 'client_type');
        $allowedTypes = array_map(static fn (string $value): string => strtolower(trim($value)), $allowedTypes);
        $allowedTypes = array_values(array_unique(array_filter($allowedTypes, static fn (string $value): bool => $value !== '')));
        if ($allowedTypes === []) {
            $allowedTypes = ['client', 'realtor', 'other', 'company'];
        }
        if (!in_array($form['client_type'], $allowedTypes, true) && SchemaInspector::hasColumn('clients', 'client_type')) {
            $errors['client_type'] = 'Choose a valid client type.';
        }
        if ($form['email'] !== '' && filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }

    private function payloadForSave(array $form, bool $forCreate): array
    {
        $payload = [
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'],
            'company_name' => $form['company_name'],
            'email' => $form['email'],
            'phone' => $form['phone'],
            'secondary_phone' => $form['secondary_phone'],
            'can_text' => ((int) $form['can_text']) === 1 ? 1 : 0,
            'secondary_can_text' => ((int) $form['secondary_can_text']) === 1 ? 1 : 0,
            'address_line1' => $form['address_line1'],
            'address_line2' => $form['address_line2'],
            'city' => $form['city'],
            'state' => $form['state'],
            'postal_code' => $form['postal_code'],
            'client_type' => $form['client_type'],
            'primary_note' => $form['primary_note'],
            'notes' => $form['primary_note'],
        ];

        if ($forCreate) {
            $payload['status'] = 'active';
        }

        return $payload;
    }

    private function defaultContactForm(): array
    {
        return [
            'contacted_at' => date('Y-m-d\\TH:i'),
            'contact_type' => 'call',
            'note' => '',
        ];
    }

    private function contactFormFromPost(array $input): array
    {
        return [
            'contacted_at' => trim((string) ($input['contacted_at'] ?? '')),
            'contact_type' => strtolower(trim((string) ($input['contact_type'] ?? ''))),
            'note' => trim((string) ($input['note'] ?? '')),
        ];
    }

    private function validateContactForm(array $form): array
    {
        $errors = [];
        $allowedTypes = ['call', 'text', 'email', 'in_person', 'other'];

        if (($this->toDatabaseDateTime($form['contacted_at']) ?? null) === null) {
            $errors['contacted_at'] = 'Contact date/time is invalid.';
        }
        if (!in_array($form['contact_type'], $allowedTypes, true)) {
            $errors['contact_type'] = 'Choose a valid contact type.';
        }

        return $errors;
    }

    private function toDatabaseDateTime(string $value): ?string
    {
        $raw = trim($value);
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
