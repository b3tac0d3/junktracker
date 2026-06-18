<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\ClientBoloProfile;
use App\Models\ClientContact;
use App\Models\ClientFamilyMember;
use App\Models\ClientFollowUpReminder;
use App\Models\EstateSale;
use App\Models\FormSelectValue;
use App\Models\Quote;
use App\Models\SchemaInspector;
use Core\AppCache;
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

    public function quickAdd(): void
    {
        require_business_role(['general_user', 'admin']);

        $this->render('clients/quick_add', [
            'pageTitle' => 'Quick Add Client',
            'actionUrl' => url('/clients/quick-add'),
            'form' => $this->defaultQuickAddForm(),
            'errors' => [],
            'followUpOptions' => ClientFollowUpReminder::reminderTypeOptions(),
        ]);
    }

    public function storeQuickAdd(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/quick-add');
        }

        $businessId = current_business_id();
        $form = $this->quickAddFormFromPost($_POST);
        $errors = $this->validateQuickAddForm($form);
        if ($errors !== []) {
            $this->render('clients/quick_add', [
                'pageTitle' => 'Quick Add Client',
                'actionUrl' => url('/clients/quick-add'),
                'form' => $form,
                'errors' => $errors,
                'followUpOptions' => ClientFollowUpReminder::reminderTypeOptions(),
            ]);
            return;
        }

        $nameParts = $this->parseQuickAddName($form['name']);
        $payload = [
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'company_name' => $nameParts['company_name'],
            'client_type' => 'client',
            'phone' => $form['phone'],
            'primary_note' => $form['note'],
            'status' => 'active',
            'can_text' => $form['can_text'] === '1' ? 1 : 0,
            'secondary_can_text' => 0,
        ];

        $duplicate = Client::findPotentialDuplicate($businessId, $payload);
        if ($duplicate !== null) {
            flash('warning', 'Possible duplicate detected. Opened the existing client instead.');
            $clientId = (int) ($duplicate['id'] ?? 0);
            if ($clientId > 0) {
                $this->redirectToClient($clientId);
            }
            redirect('/clients');
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $clientId = Client::create($businessId, $payload, $actorUserId);
        if ($clientId <= 0) {
            flash('error', 'Unable to create client.');
            redirect('/clients/quick-add');
        }

        if (ClientContact::isAvailable()) {
            ClientContact::create(
                $businessId,
                $clientId,
                [
                    'contacted_at' => date('Y-m-d H:i:s'),
                    'contact_type' => $form['contact_type'],
                    'note' => $form['note'],
                ],
                $actorUserId
            );
        }

        if (ClientFollowUpReminder::isAvailable() && $form['follow_up_reminders'] !== []) {
            ClientFollowUpReminder::createManyForClient(
                $businessId,
                $clientId,
                $form['follow_up_reminders'],
                $actorUserId,
                $actorUserId
            );
        }

        audit('client_quick_add', 'clients', $clientId, [
            'name' => $form['name'],
            'follow_up_reminders' => $form['follow_up_reminders'],
            'contact_type' => $form['contact_type'],
        ]);
        AppCache::bumpBusiness($businessId);

        flash('success', 'Client added.');
        $this->redirectToClient($clientId);
    }

    public function completeFollowUpReminder(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $reminderId = (int) ($params['id'] ?? 0);
        if ($reminderId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/');
        }

        if (!ClientFollowUpReminder::isAvailable()) {
            flash('error', 'Follow-up reminders require the latest migration.');
            redirect('/');
        }

        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $completed = ClientFollowUpReminder::complete($businessId, $reminderId, $actorUserId);
        if ($completed) {
            AppCache::bumpBusiness($businessId);
            flash('success', 'Follow-up marked complete.');
        } else {
            flash('warning', 'Follow-up was not found or is already complete.');
        }

        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
            redirect($returnTo);
        }
        redirect('/');
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
        audit('client_created', 'clients', $clientId, ['name' => trim(((string) ($form['first_name'] ?? '')) . ' ' . ((string) ($form['last_name'] ?? ''))) ?: ((string) ($form['company_name'] ?? ''))]);
        flash('success', 'Client created.');
        $nextAction = strtolower(trim((string) ($form['next_action'] ?? '')));
        if ($nextAction === 'job') {
            redirect('/jobs/create?client_id=' . (string) $clientId);
        }
        if ($nextAction === 'quote') {
            redirect('/quotes/create?client_id=' . (string) $clientId);
        }
        $this->redirectToClient($clientId);
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

        $form = $this->formFromModel($client);
        $openAddClientDataReminder = ClientFollowUpReminder::isAvailable()
            ? ClientFollowUpReminder::openForClientByType($businessId, $clientId, ClientFollowUpReminder::TYPE_ADD_CLIENT_DATA)
            : null;
        if ($openAddClientDataReminder !== null) {
            $form['complete_add_client_data'] = ClientFollowUpReminder::shouldAutoCheckCompleteOnEdit(
                $businessId,
                $clientId,
                $openAddClientDataReminder
            ) ? '1' : '';
        }

        $this->render('clients/form', [
            'pageTitle' => 'Edit Client',
            'mode' => 'edit',
            'actionUrl' => url('/clients/' . (string) $clientId . '/update'),
            'form' => $form,
            'errors' => [],
            'hasClientType' => SchemaInspector::hasColumn('clients', 'client_type'),
            'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'clientId' => $clientId,
            'referralsSent' => $referralsSent,
            'returnTab' => request_detail_tab($this->clientAllowedTabs()),
            'openAddClientDataReminder' => $openAddClientDataReminder !== null,
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
                'returnTab' => $this->clientReturnTab(),
                'openAddClientDataReminder' => ClientFollowUpReminder::isAvailable()
                    && ClientFollowUpReminder::openForClientByType(
                        $businessId,
                        $clientId,
                        ClientFollowUpReminder::TYPE_ADD_CLIENT_DATA
                    ) !== null,
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        Client::update($businessId, $clientId, $this->payloadForSave($form, false, $client), $actorUserId);

        if (ClientFollowUpReminder::isAvailable()) {
            $openAddClientDataReminder = ClientFollowUpReminder::openForClientByType(
                $businessId,
                $clientId,
                ClientFollowUpReminder::TYPE_ADD_CLIENT_DATA
            );
            if ($openAddClientDataReminder !== null) {
                $didFollowUpAction = false;
                if (isset($_POST['complete_add_client_data'])) {
                    if (ClientFollowUpReminder::completeOpenForClientByType(
                        $businessId,
                        $clientId,
                        ClientFollowUpReminder::TYPE_ADD_CLIENT_DATA,
                        $actorUserId
                    )) {
                        $didFollowUpAction = true;
                    }
                } elseif (ClientFollowUpReminder::dismissCompletePromptForClientByType(
                    $businessId,
                    $clientId,
                    ClientFollowUpReminder::TYPE_ADD_CLIENT_DATA
                )) {
                    $didFollowUpAction = true;
                }
                if ($didFollowUpAction) {
                    AppCache::bumpBusiness($businessId);
                }
            }
        }

        audit('client_updated', 'clients', $clientId);
        flash('success', 'Client updated.');
        $this->redirectToClient($clientId);
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
        $quotes = SchemaInspector::hasTable('quotes')
            ? Quote::forClient($businessId, $clientId, 50)
            : [];
        $quoteStatusSummary = SchemaInspector::hasTable('quotes')
            ? Quote::statusSummaryForClient($businessId, $clientId)
            : [];
        $hasEstateSales = SchemaInspector::hasTable('estate_sales')
            && SchemaInspector::hasColumn('estate_sales', 'client_id');
        $estateSales = $hasEstateSales
            ? EstateSale::forClient($businessId, $clientId, 50)
            : [];
        $estateSaleStatusSummary = $hasEstateSales
            ? EstateSale::statusSummaryForClient($businessId, $clientId)
            : [];
        $sales = Client::salesHistory($businessId, $clientId, 50);
        $purchases = Client::purchaseHistory($businessId, $clientId, 50);
        $contacts = ClientContact::forClient($businessId, $clientId, 50);
        $familyMembers = ClientFamilyMember::isAvailable()
            ? ClientFamilyMember::forClient($businessId, $clientId, 100)
            : [];
        $bolo = ClientBoloProfile::isAvailable() ? ClientBoloProfile::findForClient($businessId, $clientId) : null;

        $referralsSent = [];
        if (SchemaInspector::hasColumn('clients', 'referred_by_client_id')) {
            $referralsSent = Client::referralsSentBy($businessId, $clientId, 100);
        }

        $hasBoloFeature = ClientBoloProfile::isAvailable();
        $canViewFinancials = can_view_financials();
        $activeTab = strtolower(trim((string) ($_GET['tab'] ?? 'details')));
        $allowedTabs = ['details', 'jobs', 'contacts'];
        if ($canViewFinancials) {
            $allowedTabs[] = 'financial';
            $allowedTabs[] = 'transactions';
        }
        if ($hasBoloFeature) {
            $allowedTabs[] = 'bolo';
        }
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'details';
        }

        $this->render('clients/show', [
            'pageTitle' => 'Client',
            'client' => $client,
            'financial' => $financial,
            'jobStatusSummary' => $jobStatusSummary,
            'jobs' => $jobs,
            'quotes' => $quotes,
            'quoteStatusSummary' => $quoteStatusSummary,
            'hasQuotes' => SchemaInspector::hasTable('quotes'),
            'hasEstateSales' => $hasEstateSales,
            'estateSales' => $estateSales,
            'estateSaleStatusSummary' => $estateSaleStatusSummary,
            'sales' => $sales,
            'purchases' => $purchases,
            'contacts' => $contacts,
            'familyMembers' => $familyMembers,
            'hasFamilyMembers' => ClientFamilyMember::isAvailable(),
            'bolo' => $bolo,
            'hasNewsletter' => SchemaInspector::hasColumn('clients', 'newsletter_subscribed'),
            'hasBolo' => $hasBoloFeature,
            'boloHasActiveFlag' => ClientBoloProfile::hasActiveFlag(),
            'hasReferrals' => SchemaInspector::hasColumn('clients', 'referred_by_client_id'),
            'referralsSent' => $referralsSent,
            'activeTab' => $activeTab,
            'canViewFinancials' => $canViewFinancials,
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
            $this->redirectToClient($clientId);
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        Client::deactivate($businessId, $clientId, auth_user_id() ?? 0);
        audit('client_deactivated', 'clients', $clientId);
        flash('success', 'Client deactivated.');
        $this->redirectToClient($clientId);
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
            $this->redirectToClient($clientId);
        }

        $this->render('clients/contact_form', [
            'pageTitle' => 'Add Contact',
            'client' => $client,
            'clientId' => $clientId,
            'actionUrl' => url('/clients/' . (string) $clientId . '/contacts'),
            'form' => $this->defaultContactForm(),
            'errors' => [],
            'returnTab' => request_detail_tab($this->clientAllowedTabs(), 'contacts'),
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
            redirect('/clients/' . (string) $clientId . '/contacts/create' . detail_return_tab_query($this->clientReturnTab('contacts')));
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
            $this->redirectToClient($clientId);
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
                'returnTab' => $this->clientReturnTab('contacts'),
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

        audit('client_contact_created', 'clients', $clientId, ['contact_type' => $form['contact_type']]);
        flash('success', 'Contact added.');
        $this->redirectToClient($clientId, null, 'contacts');
    }

    public function createFamilyMember(array $params): void
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

        if (!ClientFamilyMember::isAvailable()) {
            flash('error', 'Family members require the latest migration.');
            $this->redirectToClient($clientId);
        }

        $this->render('clients/family_member_form', [
            'pageTitle' => 'Add Family Member',
            'mode' => 'create',
            'client' => $client,
            'clientId' => $clientId,
            'memberId' => 0,
            'actionUrl' => url('/clients/' . (string) $clientId . '/family'),
            'form' => $this->defaultFamilyMemberForm(),
            'errors' => [],
            'relationshipOptions' => ClientFamilyMember::relationshipOptions(),
            'returnTab' => request_detail_tab($this->clientAllowedTabs(), 'details'),
        ]);
    }

    public function storeFamilyMember(array $params): void
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
            redirect('/clients/' . (string) $clientId . '/family/create' . detail_return_tab_query($this->clientReturnTab('details')));
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!ClientFamilyMember::isAvailable()) {
            flash('error', 'Family members require the latest migration.');
            $this->redirectToClient($clientId);
        }

        $form = $this->familyMemberFormFromPost($_POST, $businessId, $clientId);
        $errors = $this->validateFamilyMemberForm($form, $businessId, $clientId);
        if ($errors !== []) {
            $this->render('clients/family_member_form', [
                'pageTitle' => 'Add Family Member',
                'mode' => 'create',
                'client' => $client,
                'clientId' => $clientId,
                'memberId' => 0,
                'actionUrl' => url('/clients/' . (string) $clientId . '/family'),
                'form' => $form,
                'errors' => $errors,
                'relationshipOptions' => ClientFamilyMember::relationshipOptions(),
                'returnTab' => $this->clientReturnTab('details'),
            ]);
            return;
        }

        $memberId = ClientFamilyMember::create($businessId, $clientId, $form, auth_user_id() ?? 0);
        if ($memberId <= 0) {
            flash('error', 'Could not save family member.');
            redirect('/clients/' . (string) $clientId . '/family/create' . detail_return_tab_query($this->clientReturnTab('details')));
        }

        audit('client_family_member_created', 'client_family_members', $memberId, [
            'client_id' => $clientId,
            'name' => trim(((string) ($form['first_name'] ?? '')) . ' ' . ((string) ($form['last_name'] ?? ''))),
        ]);
        flash('success', 'Family member added.');
        $this->redirectToClient($clientId, null, 'details');
    }

    public function editFamilyMember(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? 0);
        if ($clientId <= 0 || $memberId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        $member = ClientFamilyMember::findForClient($businessId, $clientId, $memberId);
        if ($client === null || $member === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('clients/family_member_form', [
            'pageTitle' => 'Edit Family Member',
            'mode' => 'edit',
            'client' => $client,
            'clientId' => $clientId,
            'memberId' => $memberId,
            'actionUrl' => url('/clients/' . (string) $clientId . '/family/' . (string) $memberId . '/update'),
            'form' => $this->familyMemberFormFromRow($member, $businessId),
            'errors' => [],
            'relationshipOptions' => ClientFamilyMember::relationshipOptions(),
            'returnTab' => request_detail_tab($this->clientAllowedTabs(), 'details'),
        ]);
    }

    public function updateFamilyMember(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? 0);
        if ($clientId <= 0 || $memberId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/clients/' . (string) $clientId . '/family/' . (string) $memberId . '/edit' . detail_return_tab_query($this->clientReturnTab('details')));
        }

        $businessId = current_business_id();
        $client = Client::findForBusiness($businessId, $clientId);
        $member = ClientFamilyMember::findForClient($businessId, $clientId, $memberId);
        if ($client === null || $member === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->familyMemberFormFromPost($_POST, $businessId, $clientId);
        $errors = $this->validateFamilyMemberForm($form, $businessId, $clientId);
        if ($errors !== []) {
            $this->render('clients/family_member_form', [
                'pageTitle' => 'Edit Family Member',
                'mode' => 'edit',
                'client' => $client,
                'clientId' => $clientId,
                'memberId' => $memberId,
                'actionUrl' => url('/clients/' . (string) $clientId . '/family/' . (string) $memberId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'relationshipOptions' => ClientFamilyMember::relationshipOptions(),
                'returnTab' => $this->clientReturnTab('details'),
            ]);
            return;
        }

        if (!ClientFamilyMember::update($businessId, $clientId, $memberId, $form, auth_user_id() ?? 0)) {
            flash('error', 'Could not update family member.');
            redirect('/clients/' . (string) $clientId . '/family/' . (string) $memberId . '/edit' . detail_return_tab_query($this->clientReturnTab('details')));
        }

        audit('client_family_member_updated', 'client_family_members', $memberId, ['client_id' => $clientId]);
        flash('success', 'Family member updated.');
        $this->redirectToClient($clientId, null, 'details');
    }

    public function deleteFamilyMember(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $clientId = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? 0);
        if ($clientId <= 0 || $memberId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            $this->redirectToClient($clientId);
        }

        $businessId = current_business_id();
        if (Client::findForBusiness($businessId, $clientId) === null || ClientFamilyMember::findForClient($businessId, $clientId, $memberId) === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        ClientFamilyMember::delete($businessId, $clientId, $memberId, auth_user_id() ?? 0);
        audit('client_family_member_deleted', 'client_family_members', $memberId, ['client_id' => $clientId]);
        flash('success', 'Family member removed.');
        $this->redirectToClient($clientId, null, 'details');
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
            $this->redirectToClient($clientId);
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
            'returnTab' => request_detail_tab($this->clientAllowedTabs(), 'bolo'),
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
            redirect('/clients/' . (string) $clientId . '/bolo/edit' . detail_return_tab_query($this->clientReturnTab('bolo')));
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
            $this->redirectToClient($clientId);
        }

        $form = $this->boloFormFromPost($_POST);
        $lines = $this->parseBoloItemLines((string) ($form['items_text'] ?? ''));
        ClientBoloProfile::save(
            $businessId,
            $clientId,
            (string) ($form['notes'] ?? ''),
            $lines
        );

        audit('client_bolo_saved', 'clients', $clientId);
        flash('success', 'BOLO profile saved.');
        $this->redirectToClient($clientId, null, 'bolo');
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
            $this->redirectToClient($clientId);
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
            $this->redirectToClient($clientId);
        }

        ClientBoloProfile::setProfileActive($businessId, $clientId, false);
        audit('client_bolo_deactivated', 'clients', $clientId);
        flash('success', 'BOLO profile deactivated. It will not appear in the BOLO list or search until reactivated.');
        $this->redirectToClient($clientId, null, 'bolo');
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
            $this->redirectToClient($clientId);
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
            $this->redirectToClient($clientId);
        }

        ClientBoloProfile::setProfileActive($businessId, $clientId, true);
        audit('client_bolo_reactivated', 'clients', $clientId);
        flash('success', 'BOLO profile reactivated.');
        $this->redirectToClient($clientId, null, 'bolo');
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
            'complete_add_client_data' => isset($input['complete_add_client_data']) ? '1' : '',
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

    private function defaultFamilyMemberForm(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'relationship' => '',
            'phone' => '',
            'linked_client_id' => '',
            'linked_client_display_name' => '',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function familyMemberFormFromRow(array $row, int $businessId): array
    {
        $linkedId = (int) ($row['linked_client_id'] ?? 0);
        $displayName = trim((string) ($row['linked_client_name'] ?? ''));
        if ($linkedId > 0 && $displayName === '') {
            $linked = Client::findForBusiness($businessId, $linkedId);
            if (is_array($linked)) {
                $displayName = trim(((string) ($linked['first_name'] ?? '')) . ' ' . ((string) ($linked['last_name'] ?? '')));
                if ($displayName === '') {
                    $displayName = trim((string) ($linked['company_name'] ?? ''));
                }
            }
        }

        return [
            'first_name' => trim((string) ($row['first_name'] ?? '')),
            'last_name' => trim((string) ($row['last_name'] ?? '')),
            'relationship' => ClientFamilyMember::normalizeRelationship($row['relationship'] ?? '') ?? '',
            'phone' => trim((string) ($row['phone'] ?? '')),
            'linked_client_id' => $linkedId > 0 ? (string) $linkedId : '',
            'linked_client_display_name' => $displayName,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function familyMemberFormFromPost(array $input, int $businessId, int $clientId): array
    {
        $linkedId = (int) ($input['linked_client_id'] ?? 0);
        $displayName = trim((string) ($input['linked_client_display_name'] ?? ''));
        if ($linkedId > 0 && $displayName === '') {
            $linked = Client::findForBusiness($businessId, $linkedId);
            if (is_array($linked)) {
                $displayName = trim(((string) ($linked['first_name'] ?? '')) . ' ' . ((string) ($linked['last_name'] ?? '')));
                if ($displayName === '') {
                    $displayName = trim((string) ($linked['company_name'] ?? ''));
                }
            }
        }

        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'relationship' => strtolower(trim((string) ($input['relationship'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'linked_client_id' => $linkedId > 0 ? (string) $linkedId : '',
            'linked_client_display_name' => $displayName,
        ];
    }

    /**
     * @param array<string, string> $form
     * @return array<string, string>
     */
    private function validateFamilyMemberForm(array $form, int $businessId, int $clientId): array
    {
        $errors = ClientFamilyMember::validate($form);
        $linkedId = (int) ($form['linked_client_id'] ?? 0);
        if ($linkedId > 0) {
            if ($linkedId === $clientId) {
                $errors['linked_client_id'] = 'Cannot link the client to themselves.';
            } elseif (Client::findForBusiness($businessId, $linkedId) === null) {
                $errors['linked_client_id'] = 'Linked client not found.';
            }
        }

        return $errors;
    }

    private function defaultContactForm(): array
    {
        return [
            'contacted_at' => date('Y-m-d\\TH:i'),
            'contact_type' => 'call',
            'note' => '',
        ];
    }

    /**
     * @return array{name: string, phone: string, note: string, contact_type: string, can_text: string, follow_up_reminders: list<string>}
     */
    private function defaultQuickAddForm(): array
    {
        return [
            'name' => '',
            'phone' => '',
            'note' => '',
            'contact_type' => 'call',
            'can_text' => '',
            'follow_up_reminders' => [],
        ];
    }

    /**
     * @return array{name: string, phone: string, note: string, contact_type: string, can_text: string, follow_up_reminders: list<string>}
     */
    private function quickAddFormFromPost(array $input): array
    {
        $allowedTypes = ['call', 'text', 'email', 'in_person', 'other'];
        $contactType = strtolower(trim((string) ($input['contact_type'] ?? 'call')));
        if (!in_array($contactType, $allowedTypes, true)) {
            $contactType = 'call';
        }

        $followUps = [];
        $rawFollowUps = $input['follow_up_reminders'] ?? [];
        if (is_string($rawFollowUps)) {
            $rawFollowUps = [$rawFollowUps];
        }
        if (is_array($rawFollowUps)) {
            $allowedFollowUps = array_keys(ClientFollowUpReminder::reminderTypeOptions());
            foreach ($rawFollowUps as $value) {
                $type = strtolower(trim((string) $value));
                if (in_array($type, $allowedFollowUps, true)) {
                    $followUps[] = $type;
                }
            }
        }
        $followUps = array_values(array_unique($followUps));

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
            'contact_type' => $contactType,
            'can_text' => isset($input['can_text']) ? '1' : '',
            'follow_up_reminders' => $followUps,
        ];
    }

    /**
     * @param array{name: string, phone: string, note: string, contact_type: string, can_text: string, follow_up_reminders: list<string>} $form
     * @return array<string, string>
     */
    private function validateQuickAddForm(array $form): array
    {
        $errors = [];
        if ($form['name'] === '') {
            $errors['name'] = 'Enter a client name.';
        }

        return $errors;
    }

    /**
     * @return array{first_name: string, last_name: string, company_name: string}
     */
    private function parseQuickAddName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['first_name' => '', 'last_name' => '', 'company_name' => ''];
        }

        $parts = preg_split('/\s+/', $name, 2);
        if (!is_array($parts) || count($parts) === 1) {
            return ['first_name' => $name, 'last_name' => '', 'company_name' => ''];
        }

        return [
            'first_name' => trim((string) $parts[0]),
            'last_name' => trim((string) $parts[1]),
            'company_name' => '',
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

    /**
     * @return list<string>
     */
    private function clientAllowedTabs(): array
    {
        return ['details', 'jobs', 'financial', 'transactions', 'bolo', 'contacts'];
    }

    private function clientReturnTab(string $fallbackTab = 'details'): string
    {
        return request_detail_tab($this->clientAllowedTabs(), $fallbackTab);
    }

    private function redirectToClient(int $clientId, ?string $tab = null, string $fallbackTab = 'details'): never
    {
        $allowed = $this->clientAllowedTabs();
        if ($tab === null) {
            $tab = request_detail_tab($allowed, $fallbackTab);
        } else {
            $tab = sanitize_detail_tab($tab, $allowed, $fallbackTab);
        }
        redirect_to_detail('/clients/' . (string) $clientId, $tab);
    }
}
