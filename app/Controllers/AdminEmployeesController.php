<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BusinessLocation;
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
        $locationContext = $this->locationFormContext($businessId);

        $this->render('admin/employees/form', [
            'pageTitle' => 'Add Employee',
            'mode' => 'create',
            'actionUrl' => url('/admin/employees'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'userOptions' => Employee::businessUserOptions($businessId, '', 300),
            'locationPickers' => $locationContext['locationPickers'],
            'locationsAvailable' => $locationContext['locationsAvailable'],
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
        $locationContext = $this->locationFormContext($businessId);
        $errors = $this->validateForm($form, $userOptions, $businessId, null, $locationContext);

        if ($errors !== []) {
            $this->render('admin/employees/form', [
                'pageTitle' => 'Add Employee',
                'mode' => 'create',
                'actionUrl' => url('/admin/employees'),
                'form' => $form,
                'errors' => $errors,
                'userOptions' => $userOptions,
                'locationPickers' => $locationContext['locationPickers'],
                'locationsAvailable' => $locationContext['locationsAvailable'],
            ]);
            return;
        }

        $employeeId = Employee::create($businessId, $this->payloadForSave($form, $businessId), auth_user_id() ?? 0);
        audit('employee_created', 'employees', $employeeId);
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

        $businessId = current_business_id();
        $locationContext = $this->locationFormContext($businessId);
        $operatingLocations = $this->resolvedOperatingLocations($businessId, $employee);

        $this->render('admin/employees/show', [
            'pageTitle' => 'Employee',
            'employee' => $employee,
            'operatingLocations' => $operatingLocations,
            'locationPickers' => $locationContext['locationPickers'],
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

        $locationContext = $this->locationFormContext($businessId);

        $this->render('admin/employees/form', [
            'pageTitle' => 'Edit Employee',
            'mode' => 'edit',
            'actionUrl' => url('/admin/employees/' . (string) $employeeId . '/update'),
            'form' => $this->formFromModel($employee),
            'errors' => [],
            'userOptions' => Employee::businessUserOptions($businessId, '', 300),
            'employeeId' => $employeeId,
            'locationPickers' => $locationContext['locationPickers'],
            'locationsAvailable' => $locationContext['locationsAvailable'],
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
        $locationContext = $this->locationFormContext($businessId);
        $errors = $this->validateForm($form, $userOptions, $businessId, $employeeId, $locationContext);

        if ($errors !== []) {
            $this->render('admin/employees/form', [
                'pageTitle' => 'Edit Employee',
                'mode' => 'edit',
                'actionUrl' => url('/admin/employees/' . (string) $employeeId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'userOptions' => $userOptions,
                'employeeId' => $employeeId,
                'locationPickers' => $locationContext['locationPickers'],
                'locationsAvailable' => $locationContext['locationsAvailable'],
            ]);
            return;
        }

        Employee::update($businessId, $employeeId, $this->payloadForSave($form, $businessId), auth_user_id() ?? 0);
        audit('employee_updated', 'employees', $employeeId);
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
            'default_store_location_id' => '',
            'default_warehouse_location_id' => '',
            'default_terminal_location_id' => '',
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
            'default_store_location_id' => (string) ((int) ($employee['default_store_location_id'] ?? 0)),
            'default_warehouse_location_id' => (string) ((int) ($employee['default_warehouse_location_id'] ?? 0)),
            'default_terminal_location_id' => (string) ((int) ($employee['default_terminal_location_id'] ?? 0)),
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
            'default_store_location_id' => trim((string) ($input['default_store_location_id'] ?? '')),
            'default_warehouse_location_id' => trim((string) ($input['default_warehouse_location_id'] ?? '')),
            'default_terminal_location_id' => trim((string) ($input['default_terminal_location_id'] ?? '')),
        ];
    }

    private function validateForm(array $form, array $userOptions, int $businessId, ?int $currentEmployeeId = null, ?array $locationContext = null): array
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

        $locationContext ??= $this->locationFormContext($businessId);
        foreach ($locationContext['locationPickers'] as $picker) {
            if (!is_array($picker)) {
                continue;
            }

            $field = trim((string) ($picker['field'] ?? ''));
            if ($field === '') {
                continue;
            }

            $locationId = (int) ($form[$field] ?? 0);
            if ($locationId <= 0) {
                continue;
            }

            $options = is_array($picker['options'] ?? null) ? $picker['options'] : [];
            $validIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $options);
            if (!in_array($locationId, $validIds, true)) {
                $label = trim((string) ($picker['label'] ?? 'location'));
                $errors[$field] = 'Choose a valid ' . strtolower($label) . ' location.';
            }
        }

        return $errors;
    }

    private function payloadForSave(array $form, int $businessId): array
    {
        $payload = [
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

        foreach ([
            BusinessLocation::TYPE_STORE => 'default_store_location_id',
            BusinessLocation::TYPE_WAREHOUSE => 'default_warehouse_location_id',
            BusinessLocation::TYPE_TERMINAL => 'default_terminal_location_id',
        ] as $type => $field) {
            if (BusinessLocation::needsEmployeeDefaultPicker($businessId, $type)) {
                $locationId = (int) ($form[$field] ?? 0);
                $payload[$field] = $locationId > 0 ? $locationId : null;
            } else {
                $payload[$field] = null;
            }
        }

        return $payload;
    }

    /**
     * @return array{locationsAvailable: bool, locationPickers: list<array<string, mixed>>}
     */
    private function locationFormContext(int $businessId): array
    {
        if (!BusinessLocation::isAvailable()) {
            return [
                'locationsAvailable' => false,
                'locationPickers' => [],
            ];
        }

        $pickers = [];
        foreach ([BusinessLocation::TYPE_STORE, BusinessLocation::TYPE_WAREHOUSE, BusinessLocation::TYPE_TERMINAL] as $type) {
            if (!BusinessLocation::needsEmployeeDefaultPicker($businessId, $type)) {
                continue;
            }

            $field = BusinessLocation::defaultColumnForType($type);
            if ($field === null) {
                continue;
            }

            $pickers[] = [
                'type' => $type,
                'label' => BusinessLocation::labelForType($type),
                'field' => $field,
                'options' => BusinessLocation::activeByType($businessId, $type),
            ];
        }

        return [
            'locationsAvailable' => true,
            'locationPickers' => $pickers,
        ];
    }

    /**
     * @param array<string, mixed> $employee
     * @return list<array<string, mixed>>
     */
    private function resolvedOperatingLocations(int $businessId, array $employee): array
    {
        if (!BusinessLocation::isAvailable()) {
            return [];
        }

        $rows = [];
        foreach ([BusinessLocation::TYPE_STORE, BusinessLocation::TYPE_WAREHOUSE, BusinessLocation::TYPE_TERMINAL] as $type) {
            if (BusinessLocation::activeCountByType($businessId, $type) <= 0) {
                continue;
            }

            $resolved = BusinessLocation::resolveForEmployee($businessId, $employee, $type);
            $resolved['type_label'] = BusinessLocation::labelForType($type);
            $resolved['needs_default_picker'] = BusinessLocation::needsEmployeeDefaultPicker($businessId, $type);
            $rows[] = $resolved;
        }

        return $rows;
    }
}
