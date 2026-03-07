<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Employee;
use Core\Controller;

final class AdminEmployeesController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Employee::indexCount($businessId, $search);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $employees = Employee::indexList($businessId, $search, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($employees));

        $this->render('admin/employees/index', [
            'pageTitle' => 'Employees',
            'search' => $search,
            'employees' => $employees,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();

        $this->render('admin/employees/form', [
            'pageTitle' => 'Add Employee',
            'mode' => 'create',
            'actionUrl' => url('/admin/employees'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'userOptions' => Employee::businessUserOptions($businessId, '', 300),
        ]);
    }

    public function store(): void
    {
        require_business_role(['admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/employees/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $userOptions = Employee::businessUserOptions($businessId, '', 500);
        $errors = $this->validateForm($form, $userOptions, $businessId);

        if ($errors !== []) {
            $this->render('admin/employees/form', [
                'pageTitle' => 'Add Employee',
                'mode' => 'create',
                'actionUrl' => url('/admin/employees'),
                'form' => $form,
                'errors' => $errors,
                'userOptions' => $userOptions,
            ]);
            return;
        }

        $employeeId = Employee::create($businessId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Employee added.');
        redirect('/admin/employees/' . (string) $employeeId);
    }

    public function show(array $params): void
    {
        require_business_role(['admin']);

        $employeeId = (int) ($params['id'] ?? 0);
        if ($employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $employee = Employee::findForBusiness(current_business_id(), $employeeId);
        if ($employee === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/employees/show', [
            'pageTitle' => 'Employee',
            'employee' => $employee,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['admin']);

        $employeeId = (int) ($params['id'] ?? 0);
        if ($employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $employee = Employee::findForBusiness($businessId, $employeeId);
        if ($employee === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/employees/form', [
            'pageTitle' => 'Edit Employee',
            'mode' => 'edit',
            'actionUrl' => url('/admin/employees/' . (string) $employeeId . '/update'),
            'form' => $this->formFromModel($employee),
            'errors' => [],
            'userOptions' => Employee::businessUserOptions($businessId, '', 300),
            'employeeId' => $employeeId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['admin']);

        $employeeId = (int) ($params['id'] ?? 0);
        if ($employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/employees/' . (string) $employeeId . '/edit');
        }

        $businessId = current_business_id();
        $employee = Employee::findForBusiness($businessId, $employeeId);
        if ($employee === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $userOptions = Employee::businessUserOptions($businessId, '', 500);
        $errors = $this->validateForm($form, $userOptions, $businessId, $employeeId);

        if ($errors !== []) {
            $this->render('admin/employees/form', [
                'pageTitle' => 'Edit Employee',
                'mode' => 'edit',
                'actionUrl' => url('/admin/employees/' . (string) $employeeId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'userOptions' => $userOptions,
                'employeeId' => $employeeId,
            ]);
            return;
        }

        Employee::update($businessId, $employeeId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Employee updated.');
        redirect('/admin/employees/' . (string) $employeeId);
    }

    private function defaultForm(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'suffix' => '',
            'hourly_rate' => '',
            'phone' => '',
            'email' => '',
            'note' => '',
            'user_id' => '',
            'user_name' => '',
            'status' => 'active',
        ];
    }

    private function formFromModel(array $employee): array
    {
        return [
            'first_name' => trim((string) ($employee['first_name'] ?? '')),
            'last_name' => trim((string) ($employee['last_name'] ?? '')),
            'suffix' => trim((string) ($employee['suffix'] ?? '')),
            'hourly_rate' => ($employee['hourly_rate'] ?? null) !== null ? number_format((float) ($employee['hourly_rate'] ?? 0), 2, '.', '') : '',
            'phone' => trim((string) ($employee['phone'] ?? '')),
            'email' => trim((string) ($employee['email'] ?? '')),
            'note' => trim((string) ($employee['note'] ?? '')),
            'user_id' => (string) ((int) ($employee['user_id'] ?? 0)),
            'user_name' => trim((string) ($employee['linked_user_name'] ?? '')),
            'status' => trim((string) ($employee['status'] ?? 'active')),
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'suffix' => trim((string) ($input['suffix'] ?? '')),
            'hourly_rate' => trim((string) ($input['hourly_rate'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
            'user_id' => trim((string) ($input['user_id'] ?? '')),
            'user_name' => trim((string) ($input['user_name'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'active')),
        ];
    }

    private function validateForm(array $form, array $userOptions, int $businessId, ?int $currentEmployeeId = null): array
    {
        $errors = [];

        $firstName = trim((string) ($form['first_name'] ?? ''));
        $lastName = trim((string) ($form['last_name'] ?? ''));
        $linkedUserId = (int) ($form['user_id'] ?? 0);

        if ($firstName === '' && $lastName === '' && $linkedUserId <= 0) {
            $errors['first_name'] = 'Enter at least a first/last name or link a user.';
        }

        $hourlyRate = trim((string) ($form['hourly_rate'] ?? ''));
        if ($hourlyRate !== '' && (!is_numeric($hourlyRate) || (float) $hourlyRate < 0)) {
            $errors['hourly_rate'] = 'Hourly rate must be 0 or greater.';
        }

        $email = trim((string) ($form['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (strlen((string) ($form['suffix'] ?? '')) > 20) {
            $errors['suffix'] = 'Suffix must be 20 characters or less.';
        }

        $validUserIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $userOptions);
        if ($linkedUserId > 0 && !in_array($linkedUserId, $validUserIds, true)) {
            $errors['user_id'] = 'Choose a valid linked user from this business.';
        } elseif ($linkedUserId > 0) {
            $existing = Employee::findByUserForBusiness($businessId, $linkedUserId);
            $existingId = (int) ($existing['id'] ?? 0);
            if ($existingId > 0 && ($currentEmployeeId === null || $existingId !== $currentEmployeeId)) {
                $errors['user_id'] = 'That user is already linked to another employee profile.';
            }
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'first_name' => trim((string) ($form['first_name'] ?? '')),
            'last_name' => trim((string) ($form['last_name'] ?? '')),
            'suffix' => trim((string) ($form['suffix'] ?? '')),
            'hourly_rate' => trim((string) ($form['hourly_rate'] ?? '')),
            'phone' => trim((string) ($form['phone'] ?? '')),
            'email' => trim((string) ($form['email'] ?? '')),
            'note' => trim((string) ($form['note'] ?? '')),
            'user_id' => (int) ($form['user_id'] ?? 0) > 0 ? (int) $form['user_id'] : null,
            'status' => 'active',
        ];
    }
}
