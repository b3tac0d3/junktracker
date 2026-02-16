<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Company;
use Core\Controller;

final class CompaniesController extends Controller
{
    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'active');

        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/companies-table.js') . '"></script>',
            '<script src="' . asset('js/companies-search-autocomplete.js') . '"></script>',
        ]);

        $this->render('companies/index', [
            'pageTitle' => 'Companies',
            'companies' => Company::search($query, $status),
            'query' => $query,
            'status' => $status,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/companies');
        }

        $company = Company::findById($id);
        if (!$company) {
            $this->renderNotFound();
            return;
        }

        $this->render('companies/show', [
            'pageTitle' => 'Company Details',
            'company' => $company,
            'linkedClients' => Company::linkedClients($id),
        ]);
    }

    public function create(): void
    {
        $this->render('companies/create', [
            'pageTitle' => 'Add Company',
            'company' => null,
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/companies/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/companies/new');
        }

        $companyId = Company::create($data, auth_user_id());
        flash('success', 'Company added.');
        redirect('/companies/' . $companyId);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $company = $id > 0 ? Company::findById($id) : null;
        if (!$company) {
            $this->renderNotFound();
            return;
        }

        $this->render('companies/edit', [
            'pageTitle' => 'Edit Company',
            'company' => $company,
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/companies');
        }

        $existing = Company::findById($id);
        if (!$existing) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/companies/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/companies/' . $id . '/edit');
        }

        // Preserve active state here. Deactivation is handled by delete action.
        $data['active'] = (int) ($existing['active'] ?? 1);

        Company::update($id, $data, auth_user_id());
        flash('success', 'Company updated.');
        redirect('/companies/' . $id);
    }

    public function delete(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/companies');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/companies/' . $id);
        }

        $company = Company::findById($id);
        if (!$company) {
            $this->renderNotFound();
            return;
        }

        if (empty($company['deleted_at']) && !empty($company['active'])) {
            Company::softDelete($id, auth_user_id());
            flash('success', 'Company deleted.');
        } else {
            flash('success', 'Company is already inactive.');
        }

        redirect('/companies');
    }

    public function lookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Company::lookupByName($term));
    }

    public function quickCreate(): void
    {
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

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Company name is required.',
                'csrf_token' => csrf_token(),
            ]);
            return;
        }

        $existing = Company::findActiveByName($name);
        if ($existing) {
            echo json_encode([
                'ok' => true,
                'created' => false,
                'company' => [
                    'id' => (int) ($existing['id'] ?? 0),
                    'name' => (string) ($existing['name'] ?? $name),
                ],
                'csrf_token' => csrf_token(),
            ]);
            return;
        }

        $companyId = Company::create([
            'name' => $name,
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'web_address' => trim((string) ($_POST['web_address'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'active' => 1,
        ], auth_user_id());

        $company = Company::findById($companyId);
        echo json_encode([
            'ok' => true,
            'created' => true,
            'company' => [
                'id' => $companyId,
                'name' => (string) ($company['name'] ?? $name),
            ],
            'csrf_token' => csrf_token(),
        ]);
    }

    private function collectFormData(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'web_address' => trim((string) ($_POST['web_address'] ?? '')),
            'facebook' => trim((string) ($_POST['facebook'] ?? '')),
            'instagram' => trim((string) ($_POST['instagram'] ?? '')),
            'linkedin' => trim((string) ($_POST['linkedin'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'active' => 1,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Company name is required.';
        }

        if ($data['state'] !== '' && strlen($data['state']) !== 2) {
            $errors[] = 'State must be a 2-letter value.';
        }

        if ($data['web_address'] !== '' && !filter_var($data['web_address'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Website must be a valid URL.';
        }

        return $errors;
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
