<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\LookupOption;
use Core\Controller;

final class AdminLookupsController extends Controller
{
    public function index(): void
    {
        require_permission('lookups', 'view');

        $groups = LookupOption::groups();
        $group = trim((string) ($_GET['group'] ?? 'job_status'));
        if (!array_key_exists($group, $groups)) {
            $group = 'job_status';
        }

        LookupOption::seedDefaults();

        $this->render('admin/lookups/index', [
            'pageTitle' => 'Lookups',
            'groups' => $groups,
            'selectedGroup' => $group,
            'rows' => LookupOption::allForAdmin($group),
            'isReady' => LookupOption::isAvailable(),
        ]);
    }

    public function create(): void
    {
        require_permission('lookups', 'create');

        if (!LookupOption::isAvailable()) {
            flash('error', 'Lookup table is not available yet.');
            redirect('/admin/lookups');
        }

        $groups = LookupOption::groups();
        $group = trim((string) ($_GET['group'] ?? 'job_status'));
        if (!array_key_exists($group, $groups)) {
            $group = 'job_status';
        }

        $this->render('admin/lookups/create', [
            'pageTitle' => 'Add Lookup Option',
            'groups' => $groups,
            'selectedGroup' => $group,
        ]);
        clear_old();
    }

    public function store(): void
    {
        require_permission('lookups', 'create');

        if (!LookupOption::isAvailable()) {
            flash('error', 'Lookup table is not available yet.');
            redirect('/admin/lookups');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/lookups');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/admin/lookups/new?group=' . urlencode($data['group_key']));
        }

        $id = LookupOption::create($data, auth_user_id());
        log_user_action('lookup_option_created', 'app_lookups', $id, 'Created lookup option ' . $data['group_key'] . ':' . $data['value_key'] . '.');
        flash('success', 'Lookup option added.');
        redirect('/admin/lookups?group=' . urlencode($data['group_key']));
    }

    public function edit(array $params): void
    {
        require_permission('lookups', 'edit');

        if (!LookupOption::isAvailable()) {
            flash('error', 'Lookup table is not available yet.');
            redirect('/admin/lookups');
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $row = LookupOption::findById($id);
        if (!$row) {
            flash('error', 'Lookup option not found.');
            redirect('/admin/lookups');
        }

        $this->render('admin/lookups/edit', [
            'pageTitle' => 'Edit Lookup Option',
            'groups' => LookupOption::groups(),
            'row' => $row,
        ]);
        clear_old();
    }

    public function update(array $params): void
    {
        require_permission('lookups', 'edit');

        if (!LookupOption::isAvailable()) {
            flash('error', 'Lookup table is not available yet.');
            redirect('/admin/lookups');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/lookups');
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            flash('error', 'Invalid lookup option.');
            redirect('/admin/lookups');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/admin/lookups/' . $id . '/edit');
        }

        LookupOption::update($id, $data, auth_user_id());
        log_user_action('lookup_option_updated', 'app_lookups', $id, 'Updated lookup option #' . $id . '.');
        flash('success', 'Lookup option updated.');
        redirect('/admin/lookups?group=' . urlencode($data['group_key']));
    }

    public function delete(array $params): void
    {
        require_permission('lookups', 'delete');

        if (!LookupOption::isAvailable()) {
            flash('error', 'Lookup table is not available yet.');
            redirect('/admin/lookups');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/lookups');
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $row = LookupOption::findById($id);
        if (!$row) {
            flash('error', 'Lookup option not found.');
            redirect('/admin/lookups');
        }

        LookupOption::softDelete($id, auth_user_id());
        log_user_action('lookup_option_deleted', 'app_lookups', $id, 'Deleted lookup option #' . $id . '.');
        flash('success', 'Lookup option deleted.');
        redirect('/admin/lookups?group=' . urlencode((string) ($row['group_key'] ?? '')));
    }

    private function collectFormData(): array
    {
        return [
            'group_key' => trim((string) ($_POST['group_key'] ?? '')),
            'value_key' => trim((string) ($_POST['value_key'] ?? '')),
            'label' => trim((string) ($_POST['label'] ?? '')),
            'sort_order' => (int) ($_POST['sort_order'] ?? 100),
            'active' => !empty($_POST['active']) ? 1 : 0,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (!array_key_exists((string) $data['group_key'], LookupOption::groups())) {
            $errors[] = 'Lookup group is invalid.';
        }
        if ((string) $data['value_key'] === '') {
            $errors[] = 'Value key is required.';
        }
        if ((string) $data['label'] === '') {
            $errors[] = 'Label is required.';
        }
        if ((int) $data['sort_order'] < 0) {
            $errors[] = 'Sort order must be zero or greater.';
        }

        return $errors;
    }
}
