<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DisposalLocation;
use Core\Controller;

final class DisposalLocationsController extends Controller
{
    public function index(): void
    {
        require_permission('disposal_locations', 'view');

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/admin-tables.js') . '"></script>',
        ]);

        $this->render('admin/disposal_locations/index', [
            'pageTitle' => 'Disposal Locations',
            'locations' => DisposalLocation::allActive(),
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        require_permission('disposal_locations', 'create');

        $this->render('admin/disposal_locations/create', [
            'pageTitle' => 'Add Disposal Location',
            'location' => null,
        ]);

        clear_old();
    }

    public function store(): void
    {
        require_permission('disposal_locations', 'create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/disposal-locations/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/admin/disposal-locations/new');
        }

        DisposalLocation::create($data, auth_user_id());
        flash('success', 'Disposal location added.');
        redirect('/admin/disposal-locations');
    }

    public function edit(array $params): void
    {
        require_permission('disposal_locations', 'edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $location = $id > 0 ? DisposalLocation::findById($id) : null;
        if (!$location) {
            $this->renderNotFound();
            return;
        }

        $this->render('admin/disposal_locations/edit', [
            'pageTitle' => 'Edit Disposal Location',
            'location' => $location,
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        require_permission('disposal_locations', 'edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/admin/disposal-locations');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/disposal-locations/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/admin/disposal-locations/' . $id . '/edit');
        }

        DisposalLocation::update($id, $data, auth_user_id());
        flash('success', 'Disposal location updated.');
        redirect('/admin/disposal-locations');
    }

    public function delete(array $params): void
    {
        require_permission('disposal_locations', 'delete');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/admin/disposal-locations');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/disposal-locations');
        }

        DisposalLocation::softDelete($id, auth_user_id());
        flash('success', 'Disposal location deleted.');
        redirect('/admin/disposal-locations');
    }

    private function collectFormData(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'type' => trim((string) ($_POST['type'] ?? 'dump')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => strtoupper(trim((string) ($_POST['state'] ?? ''))),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Name is required.';
        }
        if (!in_array($data['type'], ['dump', 'scrap'], true)) {
            $errors[] = 'Type must be dump or scrap.';
        }
        if ($data['address_1'] === '') {
            $errors[] = 'Address is required.';
        }
        if ($data['city'] === '') {
            $errors[] = 'City is required.';
        }
        if ($data['state'] === '' || strlen($data['state']) !== 2) {
            $errors[] = 'State must be a 2-letter value.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
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
