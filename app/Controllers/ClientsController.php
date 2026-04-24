<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\ClientBoloProfile;
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
        $activeFilter = strtolower(trim((string) ($_GET['active'] ?? 'active')));
        if (!in_array($sortBy, ['name', 'id'], true)) {
            $sortBy = 'name';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        if (!in_array($activeFilter, ['active', 'inactive', 'all'], true)) {
            $activeFilter = 'active';
        }
        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Client::indexCount($businessId, $search, $activeFilter);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $clients = Client::indexList($businessId, $search, $perPage, $offset, $sortBy, $sortDir, $activeFilter);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($clients));

        $this->render('clients/index', [
            'pageTitle' => 'Clients',
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'activeFilter' => $activeFilter,
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
            'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'clientId' => 0,
            'referralsSent' => [],
        ]);
    }

    public function referrerSearch(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $query = trim((string) ($_GET['q'] ?? ''));
        $exclude = (int) ($_GET['exclude_id'] ?? 0);
        $items = Client::searchOptions($businessId, $query, 12, $exclude);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $results[] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'phone' => (string) ($item['phone'] ?? ''),
                'city' => (string) ($item['city'] ?? ''),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function checkDuplicates(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $candidate = [
            'first_name' => trim((string) ($_GET['first_name'] ?? '')),
            'last_name' => trim((string) ($_GET['last_name'] ?? '')),
            'company_name' => trim((string) ($_GET['company_name'] ?? '')),
            'email' => trim((string) ($_GET['email'] ?? '')),
            'phone' => trim((string) ($_GET['phone'] ?? '')),
        ];
        $exclude = (int) ($_GET['exclude_id'] ?? 0);
        $matches = Client::findDuplicateMatches($businessId, $candidate, $exclude > 0 ? $exclude : null);
        $this->json(['ok' => true, 'matches' => $matches]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/create');
        }

        $businessId = current_business_id();
        $form = $this->formWithReferrerDisplayName($this->formFromPost($_POST), $businessId);
        $errors = $this->validateForm($form, $businessId, null);
        if ($errors !== []) {
            $this->render('clients/form', [
                'pageTitle' => 'Add Client',
                'mode' => 'create',
                'actionUrl' => url('/clients'),
                'form' => $form,
                'errors' => $errors,
                'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
                'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
                'clientId' => 0,
                'referralsSent' => [],
            ]);
            return;
        }

        $clientId = Client::create($businessId, $this->payloadForSave($form, true, null), auth_user_id() ?? 0);
        flash('success', 'Client created.');
        $nextAction = strtolower(trim((string) ($form['next_action'] ?? '')));
        if ($nextAction === 'job') {
            redirect('/jobs/create?client_id=' . (string) $clientId);
        }
        if ($nextAction === 'quote') {
            redirect('/quotes/create?client_id=' . (string) $clientId);
        }
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

        $referralsSent = [];
        if (SchemaInspector::hasColumn('clients', 'referred_by_client_id')) {
            $referralsSent = Client::referralsSentBy($businessId, $clientId, 100);
        }

        $this->render('clients/form', [
            'pageTitle' => 'Edit Client',
            'mode' => 'edit',
            'actionUrl' => url('/clients/' . (string) $clientId . '/update'),
            'form' => $this->formFromModel($client),
            'errors' => [],
            'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
            'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'clientId' => $clientId,
            'referralsSent' => $referralsSent,
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

        $form = $this->formWithReferrerDisplayName($this->formFromPost($_POST), $businessId);
        $errors = $this->validateForm($form, $businessId, $clientId);
        if ($errors !== []) {
            $referralsSent = [];
            if (SchemaInspector::hasColumn('clients', 'referred_by_client_id')) {
                $referralsSent = Client::referralsSentBy($businessId, $clientId, 100);
            }
            $this->render('clients/form', [
                'pageTitle' => 'Edit Client',
                'mode' => 'edit',
                'actionUrl' => url('/clients/' . (string) $clientId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
                'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
                'clientId' => $clientId,
                'referralsSent' => $referralsSent,
            ]);
            return;
        }

        Client::update($businessId, $clientId, $this->payloadForSave($form, false, $client), auth_user_id() ?? 0);
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
        $bolo = ClientBoloProfile::isAvailable() ? ClientBoloProfile::findForClient($businessId, $clientId) : null;

        $referralsSent = [];
        if (SchemaInspector::hasColumn('clients', 'referred_by_client_id')) {
            $referralsSent = Client::referralsSentBy($businessId, $clientId, 100);
        }

        $this->render('clients/show', [
            'pageTitle' => 'Client',
            'client' => $client,
            'financial' => $financial,
            'jobStatusSummary' => $jobStatusSummary,
            'jobs' => $jobs,
            'sales' => $sales,
            'purchases' => $purchases,
            'contacts' => $contacts,
            'bolo' => $bolo,
            'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
            'hasBolo' => ClientBoloProfile::isAvailable(),
            'boloHasActiveFlag' => ClientBoloProfile::hasActiveFlag(),
            'hasReferrals' => SchemaInspector::hasColumn('clients', 'referred_by_client_id'),
            'referralsSent' => $referralsSent,
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

    public function editBolo(array $params): void
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

        if (!ClientBoloProfile::isAvailable()) {
            flash('error', 'BOLO profile tables are missing. Run the latest database migration.');
            redirect('/clients/' . (string) $clientId);
        }

        $bolo = ClientBoloProfile::findForClient($businessId, $clientId);
        $form = $this->boloFormFromData($bolo);
        $boloIsActive = true;
        if ($bolo !== null && ClientBoloProfile::hasActiveFlag()) {
            $boloIsActive = (int) (($bolo['profile'] ?? [])['is_active'] ?? 1) === 1;
        }

        $this->render('clients/bolo_form', [
            'pageTitle' => 'BOLO Profile',
            'client' => $client,
            'clientId' => $clientId,
            'actionUrl' => url('/clients/' . (string) $clientId . '/bolo'),
            'form' => $form,
            'errors' => [],
            'boloHasActiveFlag' => ClientBoloProfile::hasActiveFlag(),
            'boloIsActive' => $boloIsActive,
        ]);
    }

    public function updateBolo(array $params): void
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
            redirect('/clients/' . (string) $clientId . '/bolo/edit');
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!ClientBoloProfile::isAvailable()) {
            flash('error', 'BOLO profile tables are missing. Run the latest database migration.');
            redirect('/clients/' . (string) $clientId);
        }

        $form = $this->boloFormFromPost($_POST);
        $lines = $this->parseBoloItemLines((string) ($form['items_text'] ?? ''));
        ClientBoloProfile::save(
            $businessId,
            $clientId,
            (string) ($form['notes'] ?? ''),
            $lines
        );

        flash('success', 'BOLO profile saved.');
        redirect('/clients/' . (string) $clientId);
    }

    public function deactivateBolo(array $params): void
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

        if (!ClientBoloProfile::hasActiveFlag()) {
            flash('error', 'BOLO active flag is missing. Run the latest database migration.');
            redirect('/clients/' . (string) $clientId);
        }

        ClientBoloProfile::setProfileActive($businessId, $clientId, false);
        flash('success', 'BOLO profile deactivated. It will not appear in the BOLO list or search until reactivated.');
        redirect('/clients/' . (string) $clientId);
    }

    public function reactivateBolo(array $params): void
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

        if (!ClientBoloProfile::hasActiveFlag()) {
            flash('error', 'BOLO active flag is missing. Run the latest database migration.');
            redirect('/clients/' . (string) $clientId);
        }

        ClientBoloProfile::setProfileActive($businessId, $clientId, true);
        flash('success', 'BOLO profile reactivated.');
        redirect('/clients/' . (string) $clientId);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Keeps the referrer search field populated after validation errors.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function formWithReferrerDisplayName(array $form, int $businessId): array
    {
        $rid = (int) trim((string) ($form['referred_by_client_id'] ?? ''));
        if ($rid <= 0) {
            $form['referrer_display_name'] = trim((string) ($form['referrer_display_name'] ?? ''));

            return $form;
        }
        $c = Client::findForBusiness($businessId, $rid);
        $form['referrer_display_name'] = $c !== null ? Client::displayName($c) : '';

        return $form;
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
            'newsletter_subscribed' => '0',
            'referred_by_client_id' => '',
            'referrer_display_name' => '',
            'next_action' => '',
        ];
    }

    private function formFromModel(array $client): array
    {
        $refId = (int) ($client['referred_by_client_id'] ?? 0);
        $refDisplay = '';
        if ($refId > 0) {
            $refDisplay = Client::displayName([
                'id' => $refId,
                'first_name' => (string) ($client['referrer_first_name'] ?? ''),
                'last_name' => (string) ($client['referrer_last_name'] ?? ''),
                'company_name' => (string) ($client['referrer_company_name'] ?? ''),
            ]);
        }

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
            'newsletter_subscribed' => ((int) ($client['newsletter_subscribed'] ?? 0)) === 1 ? '1' : '0',
            'referred_by_client_id' => $refId > 0 ? (string) $refId : '',
            'referrer_display_name' => $refDisplay,
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
            'newsletter_subscribed' => isset($input['newsletter_subscribed']) ? '1' : '0',
            'referred_by_client_id' => trim((string) ($input['referred_by_client_id'] ?? '')),
            'next_action' => strtolower(trim((string) ($input['next_action'] ?? ''))),
        ];
    }

    private function validateForm(array $form, int $businessId, ?int $editingClientId = null): array
    {
        $errors = [];

        if ($form['first_name'] === '' && $form['last_name'] === '' && $form['company_name'] === '') {
            $errors['first_name'] = 'Enter a first/last name or a company name.';
        }

        if (SchemaInspector::hasColumn('clients', 'referred_by_client_id')) {
            $refRaw = trim((string) ($form['referred_by_client_id'] ?? ''));
            $refId = $refRaw === '' ? 0 : (int) $refRaw;
            if ($refId > 0) {
                if ($editingClientId !== null && $refId === $editingClientId) {
                    $errors['referred_by_client_id'] = 'A client cannot refer themselves.';
                } else {
                    $refClient = Client::findForBusiness($businessId, $refId);
                    if ($refClient === null) {
                        $errors['referred_by_client_id'] = 'Choose a valid referring client from the list.';
                    }
                }
            }
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

    /**
     * @param array<string, mixed>|null $existingClient Row from Client::findForBusiness when editing
     */
    private function payloadForSave(array $form, bool $forCreate, ?array $existingClient): array
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

        if (SchemaInspector::hasColumn('clients', 'newsletter_subscribed')) {
            $sub = ((string) ($form['newsletter_subscribed'] ?? '0')) === '1' ? 1 : 0;
            $payload['newsletter_subscribed'] = $sub;
            if (SchemaInspector::hasColumn('clients', 'newsletter_unsubscribe_token') && $sub === 1) {
                $prev = '';
                if ($existingClient !== null) {
                    $prev = trim((string) ($existingClient['newsletter_unsubscribe_token'] ?? ''));
                }
                if ($prev === '') {
                    $payload['newsletter_unsubscribe_token'] = bin2hex(random_bytes(32));
                }
            }
        }

        if (SchemaInspector::hasColumn('clients', 'referred_by_client_id')) {
            $rid = (int) trim((string) ($form['referred_by_client_id'] ?? ''));
            $payload['referred_by_client_id'] = $rid > 0 ? $rid : null;
        }

        return $payload;
    }

    /**
     * @param array{profile: array<string, mixed>, lines: list<array<string, mixed>>}|null $bolo
     * @return array{notes: string, items_text: string}
     */
    private function boloFormFromData(?array $bolo): array
    {
        if ($bolo === null) {
            return [
                'notes' => '',
                'items_text' => '',
            ];
        }

        $profile = $bolo['profile'] ?? [];
        $lines = $bolo['lines'] ?? [];
        $parts = [];
        foreach ($lines as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parts[] = (string) ($row['item_text'] ?? '');
        }

        return [
            'notes' => trim((string) ($profile['notes'] ?? '')),
            'items_text' => implode("\n", array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== '')),
        ];
    }

    /**
     * @return array{notes: string, items_text: string}
     */
    private function boloFormFromPost(array $input): array
    {
        return [
            'notes' => trim((string) ($input['notes'] ?? '')),
            'items_text' => (string) ($input['items_text'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private function parseBoloItemLines(string $raw): array
    {
        $parts = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($parts as $line) {
            $t = trim((string) $line);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
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
