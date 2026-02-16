<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\Estate;
use Core\Controller;

final class EstatesController extends Controller
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
            '<script src="' . asset('js/estates-table.js') . '"></script>',
            '<script src="' . asset('js/estates-search-autocomplete.js') . '"></script>',
        ]);

        $this->render('estates/index', [
            'pageTitle' => 'Estates',
            'estates' => Estate::search($query, $status),
            'query' => $query,
            'status' => $status,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/estates');
        }

        $estate = Estate::findById($id);
        if (!$estate) {
            $this->renderNotFound();
            return;
        }

        $this->render('estates/show', [
            'pageTitle' => 'Estate Details',
            'estate' => $estate,
            'relatedClients' => Estate::relatedClients($id),
        ]);
    }

    public function create(): void
    {
        $this->render('estates/create', [
            'pageTitle' => 'Add Estate',
            'estate' => null,
            'selectedClient' => $this->resolveSelectedClient(null),
            'pageScripts' => '<script src="' . asset('js/estate-client-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/estates/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/estates/new');
        }

        $estateId = Estate::create($data, auth_user_id());

        flash('success', 'Estate added.');
        redirect('/estates/' . $estateId);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $estate = $id > 0 ? Estate::findById($id) : null;

        if (!$estate) {
            $this->renderNotFound();
            return;
        }

        $this->render('estates/edit', [
            'pageTitle' => 'Edit Estate',
            'estate' => $estate,
            'selectedClient' => $this->resolveSelectedClient($id),
            'pageScripts' => '<script src="' . asset('js/estate-client-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/estates');
        }

        $estate = Estate::findById($id);
        if (!$estate) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/estates/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/estates/' . $id . '/edit');
        }

        Estate::update($id, $data, auth_user_id());

        flash('success', 'Estate updated.');
        redirect('/estates/' . $id);
    }

    public function lookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Estate::lookupByName($term));
    }

    private function resolveSelectedClient(?int $estateId): ?array
    {
        $oldClientId = $this->toIntOrNull(old('client_id'));
        if ($oldClientId !== null && $oldClientId > 0) {
            return Client::findById($oldClientId);
        }

        if ($estateId !== null && $estateId > 0) {
            return Estate::primaryClient($estateId);
        }

        return null;
    }

    private function collectFormData(): array
    {
        return [
            'client_id' => $this->toIntOrNull($_POST['client_id'] ?? null),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'can_text' => isset($_POST['can_text']) ? 1 : 0,
            'email' => trim((string) ($_POST['email'] ?? '')),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Estate name is required.';
        }

        if (!is_int($data['client_id']) || $data['client_id'] <= 0) {
            $errors[] = 'Primary client is required.';
        }

        if (is_int($data['client_id']) && $data['client_id'] > 0 && !Client::findById($data['client_id'])) {
            $errors[] = 'Select a valid primary client.';
        }

        $clientName = trim((string) ($_POST['client_name'] ?? ''));
        if ($clientName !== '' && (!is_int($data['client_id']) || $data['client_id'] <= 0)) {
            $errors[] = 'Select a primary client from suggestions.';
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }

        if ($data['state'] !== '' && strlen($data['state']) !== 2) {
            $errors[] = 'State must be a 2-letter value.';
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
}
