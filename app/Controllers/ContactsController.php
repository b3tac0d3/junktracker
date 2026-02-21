<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use Core\Controller;

final class ContactsController extends Controller
{
    public function index(): void
    {
        $this->authorize('view');

        $query = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'active');
        $type = strtolower(trim((string) ($_GET['type'] ?? 'all')));

        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $contactTypeOptions = Contact::contactTypeOptions();
        if ($type !== 'all' && !array_key_exists($type, $contactTypeOptions)) {
            $type = 'all';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/contacts-table.js') . '"></script>',
        ]);

        $this->render('contacts/index', [
            'pageTitle' => 'Network Clients',
            'contacts' => Contact::search($query, $status, $type),
            'query' => $query,
            'status' => $status,
            'type' => $type,
            'contactTypeOptions' => $contactTypeOptions,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $this->authorize('view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/network');
        }

        $contact = Contact::findById($id);
        if (!$contact) {
            $this->renderNotFound();
            return;
        }

        $this->render('contacts/show', [
            'pageTitle' => 'Network Client Details',
            'contact' => $contact,
            'canCreateClient' => can_access('clients', 'create'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $this->render('contacts/create', [
            'pageTitle' => 'Add Network Client',
            'contact' => null,
            'contactTypeOptions' => Contact::contactTypeOptions(),
            'canCreateClient' => can_access('clients', 'create'),
            'pageScripts' => '<script src="' . asset('js/contact-company-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function store(): void
    {
        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/network/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data, true);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/network/new');
        }

        $contactId = Contact::create($data, auth_user_id());
        log_user_action('contact_created', 'contacts', $contactId, 'Created network client #' . $contactId . '.');

        $createAsClient = isset($_POST['create_client_now']) && (string) ($_POST['create_client_now'] ?? '') === '1';
        if ($createAsClient && can_access('clients', 'create')) {
            $clientId = Contact::createClientFromContactId($contactId, auth_user_id());
            if ($clientId !== null && $clientId > 0) {
                log_user_action('contact_converted_to_client', 'clients', $clientId, 'Created client #' . $clientId . ' from network client #' . $contactId . '.');
                flash('success', 'Network client added and client created.');
                redirect('/clients/' . $clientId);
            }
        }

        flash('success', 'Network client added.');
        redirect('/network/' . $contactId);
    }

    public function edit(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/network');
        }

        $contact = Contact::findById($id);
        if (!$contact) {
            $this->renderNotFound();
            return;
        }

        $this->render('contacts/edit', [
            'pageTitle' => 'Edit Network Client',
            'contact' => $contact,
            'contactTypeOptions' => Contact::contactTypeOptions(),
            'canCreateClient' => can_access('clients', 'create'),
            'pageScripts' => '<script src="' . asset('js/contact-company-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/network');
        }

        $existing = Contact::findById($id);
        if (!$existing) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/network/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data, false);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/network/' . $id . '/edit');
        }

        Contact::update($id, $data, auth_user_id());
        log_user_action('contact_updated', 'contacts', $id, 'Updated network client #' . $id . '.');

        flash('success', 'Network client updated.');
        redirect('/network/' . $id);
    }

    public function deactivate(array $params): void
    {
        $this->authorize('delete');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/network');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/network/' . $id);
        }

        $contact = Contact::findById($id);
        if (!$contact) {
            $this->renderNotFound();
            return;
        }

        Contact::deactivate($id, auth_user_id());
        log_user_action('contact_deactivated', 'contacts', $id, 'Deactivated network client #' . $id . '.');
        flash('success', 'Network client deactivated.');
        redirect('/network/' . $id);
    }

    public function createClient(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/network');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/network/' . $id);
        }

        if (!can_access('clients', 'create')) {
            flash('error', 'You do not have permission to create clients.');
            redirect('/network/' . $id);
        }

        $contact = Contact::findById($id);
        if (!$contact) {
            $this->renderNotFound();
            return;
        }

        $clientId = Contact::createClientFromContactId($id, auth_user_id());
        if ($clientId === null || $clientId <= 0) {
            flash('error', 'Unable to create client from this network client.');
            redirect('/network/' . $id);
        }

        log_user_action('contact_converted_to_client', 'clients', $clientId, 'Created client #' . $clientId . ' from network client #' . $id . '.');
        flash('success', 'Client created from network client.');
        redirect('/clients/' . $clientId);
    }

    public function companyLookup(): void
    {
        if (!can_access('contacts', 'view') && !can_access('contacts', 'create') && !can_access('contacts', 'edit')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([]);
            return;
        }

        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Company::lookupByName($term));
    }

    private function authorize(string $action): void
    {
        require_permission('contacts', $action);
    }

    private function collectFormData(): array
    {
        $active = isset($_POST['is_active']) ? 1 : 0;

        return [
            'contact_type' => strtolower(trim((string) ($_POST['contact_type'] ?? 'general'))),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'company_id' => $this->toIntOrNull($_POST['company_id'] ?? null),
            'linked_client_id' => $this->toIntOrNull($_POST['linked_client_id'] ?? null),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'is_active' => $active,
            'source_type' => 'manual',
            'source_id' => null,
        ];
    }

    private function validate(array $data, bool $isCreate): array
    {
        $errors = [];

        $name = trim((string) (($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
        if ($name === '' && trim((string) ($data['display_name'] ?? '')) === '' && trim((string) ($data['email'] ?? '')) === '' && trim((string) ($data['phone'] ?? '')) === '') {
            $errors[] = 'Provide at least a name, email, or phone.';
        }

        if ((string) ($data['email'] ?? '') !== '' && !filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }

        if ((string) ($data['state'] ?? '') !== '' && strlen((string) $data['state']) !== 2) {
            $errors[] = 'State must be a 2-letter value.';
        }

        $typeOptions = Contact::contactTypeOptions();
        if (!array_key_exists((string) ($data['contact_type'] ?? ''), $typeOptions)) {
            $errors[] = 'Network type is invalid.';
        }

        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : 0;
        if ($companyName !== '' && $companyId <= 0) {
            $errors[] = 'Select a company from suggestions.';
        } elseif ($companyId > 0 && !Company::findById($companyId)) {
            $errors[] = 'Selected company is invalid.';
        }

        if ($isCreate && isset($_POST['create_client_now']) && (string) ($_POST['create_client_now'] ?? '') === '1' && !can_access('clients', 'create')) {
            $errors[] = 'You do not have permission to create clients.';
        }

        return $errors;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;
        return $id > 0 ? $id : null;
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
