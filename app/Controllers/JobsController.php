<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\TimeEntry;
use Core\Controller;

final class JobsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'dispatch')));
        $allowedStatuses = ['dispatch', 'prospect', 'pending', 'active', 'complete', 'cancelled'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = 'dispatch';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Job::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $jobs = Job::indexList($businessId, $search, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($jobs));

        $this->render('jobs/index', [
            'pageTitle' => 'Jobs',
            'search' => $search,
            'status' => $status,
            'jobs' => $jobs,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $this->render('jobs/form', [
            'pageTitle' => 'Add Job',
            'mode' => 'create',
            'actionUrl' => url('/jobs'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'clientOptions' => Job::clientOptions($businessId),
        ]);
    }

    public function clientSearch(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = Client::searchOptions($businessId, $query, 8);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $results[] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'company_name' => (string) ($item['company_name'] ?? ''),
                'phone' => (string) ($item['phone'] ?? ''),
                'city' => (string) ($item['city'] ?? ''),
                'address_line1' => (string) ($item['address_line1'] ?? ''),
                'address_line2' => (string) ($item['address_line2'] ?? ''),
                'state' => (string) ($item['state'] ?? ''),
                'postal_code' => (string) ($item['postal_code'] ?? ''),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function quickCreateClient(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $clientType = strtolower(trim((string) ($_POST['client_type'] ?? '')));
        if ($clientType === '') {
            $clientType = ($firstName === '' && $lastName === '' && $companyName !== '') ? 'company' : 'client';
        }
        if ($firstName === '' && $lastName === '' && $companyName !== '') {
            $clientType = 'company';
        }
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $addressLine1 = trim((string) ($_POST['address_line1'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        $primaryNote = trim((string) ($_POST['primary_note'] ?? ''));

        $errors = [];
        if ($firstName === '' && $lastName === '' && $companyName === '') {
            $errors['first_name'] = 'Enter a first/last name or a company name.';
        }
        if (!in_array($clientType, ['client', 'company', 'realtor', 'other'], true)) {
            $errors['client_type'] = 'Choose a valid client type.';
        }
        if ($errors !== []) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
        }

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'client_type' => $clientType,
            'phone' => $phone,
            'address_line1' => $addressLine1,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'primary_note' => $primaryNote,
            'status' => 'active',
            'can_text' => 0,
            'secondary_can_text' => 0,
        ];

        $clientId = Client::create($businessId, $payload, auth_user_id() ?? 0);
        $client = Client::findForBusiness($businessId, $clientId);
        $displayName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
        if ($displayName === '') {
            $displayName = trim((string) ($client['company_name'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = 'Client #' . (string) $clientId;
        }

        $this->json([
            'ok' => true,
            'client' => [
                'id' => $clientId,
                'name' => $displayName,
                'address_line1' => (string) ($client['address_line1'] ?? ''),
                'address_line2' => (string) ($client['address_line2'] ?? ''),
                'city' => (string) ($client['city'] ?? ''),
                'state' => (string) ($client['state'] ?? ''),
                'postal_code' => (string) ($client['postal_code'] ?? ''),
            ],
        ], 201);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('jobs/form', [
                'pageTitle' => 'Add Job',
                'mode' => 'create',
                'actionUrl' => url('/jobs'),
                'form' => $form,
                'errors' => $errors,
                'clientOptions' => Job::clientOptions($businessId),
            ]);
            return;
        }

        $jobId = Job::create($businessId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Job created.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/form', [
            'pageTitle' => 'Edit Job',
            'mode' => 'edit',
            'actionUrl' => url('/jobs/' . (string) $jobId . '/update'),
            'form' => $this->formFromModel($job),
            'errors' => [],
            'clientOptions' => Job::clientOptions($businessId),
            'jobId' => $jobId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/edit');
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('jobs/form', [
                'pageTitle' => 'Edit Job',
                'mode' => 'edit',
                'actionUrl' => url('/jobs/' . (string) $jobId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'clientOptions' => Job::clientOptions($businessId),
                'jobId' => $jobId,
            ]);
            return;
        }

        Job::update($businessId, $jobId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Job updated.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $financial = Job::financialSummary($businessId, $jobId);
        $timeSummary = Job::timeSummary($businessId, $jobId);
        $timeLogs = Job::timeLogsByJob($businessId, $jobId);
        $expenses = Job::expensesByJob($businessId, $jobId);
        $adjustments = Job::adjustmentsByJob($businessId, $jobId);
        $estimates = Invoice::listByJobAndType($businessId, $jobId, 'estimate');
        $invoices = Invoice::listByJobAndType($businessId, $jobId, 'invoice');
        $payments = Invoice::paymentsByJob($businessId, $jobId);
        $assignedEmployees = Job::assignedEmployees($businessId, $jobId);

        foreach ($assignedEmployees as $index => $employee) {
            if (!is_array($employee)) {
                continue;
            }
            $employeeId = (int) ($employee['employee_id'] ?? 0);
            $openEntry = $employeeId > 0 ? TimeEntry::openEntryForEmployee($businessId, $employeeId) : null;
            $assignedEmployees[$index]['open_entry_id'] = (int) ($openEntry['id'] ?? 0);
            $assignedEmployees[$index]['open_clock_in_at'] = (string) ($openEntry['clock_in_at'] ?? '');
            $assignedEmployees[$index]['open_job_id'] = (int) ($openEntry['job_id'] ?? 0);
            $assignedEmployees[$index]['open_job_title'] = (string) ($openEntry['job_title'] ?? '');
            $assignedEmployees[$index]['is_open_for_this_job'] = ((int) ($openEntry['job_id'] ?? 0)) === $jobId && ((int) ($openEntry['is_non_job'] ?? 0)) !== 1;
        }

        $this->render('jobs/show', [
            'pageTitle' => 'Job',
            'job' => $job,
            'financial' => $financial,
            'timeSummary' => $timeSummary,
            'timeLogs' => $timeLogs,
            'expenses' => $expenses,
            'adjustments' => $adjustments,
            'assignedEmployees' => $assignedEmployees,
            'documents' => [
                'estimates' => $estimates,
                'invoices' => $invoices,
                'payments' => $payments,
            ],
        ]);
    }

    public function addEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/employee_add', [
            'pageTitle' => 'Add Employee',
            'job' => $job,
            'actionUrl' => url('/jobs/' . (string) $jobId . '/employees'),
            'searchUrl' => url('/jobs/' . (string) $jobId . '/employees/search'),
            'assignedEmployees' => Job::assignedEmployees($businessId, $jobId),
            'errors' => [],
            'form' => [
                'employee_id' => '',
                'employee_name' => '',
            ],
        ]);
    }

    public function employeeSearch(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            $this->json(['ok' => false, 'results' => []], 404);
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            $this->json(['ok' => false, 'results' => []], 404);
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $items = Job::employeeSearchOptions($businessId, $jobId, $query, 10);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            $name = trim((string) ($item['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $metaParts = [];
            $employeeName = trim((string) ($item['employee_name'] ?? ''));
            $linkedUserName = trim((string) ($item['linked_user_name'] ?? ''));
            $linkedUserEmail = trim((string) ($item['linked_user_email'] ?? ''));
            if ($linkedUserName !== '') {
                $metaParts[] = 'Linked user: ' . $linkedUserName;
            }
            if ($employeeName !== '' && strcasecmp($employeeName, $name) !== 0) {
                $metaParts[] = 'Employee: ' . $employeeName;
            }
            if ($linkedUserEmail !== '') {
                $metaParts[] = $linkedUserEmail;
            }

            $results[] = [
                'id' => $id,
                'name' => $name,
                'meta' => implode(' · ', $metaParts),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function storeEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/employees/add');
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            $this->render('jobs/employee_add', [
                'pageTitle' => 'Add Employee',
                'job' => $job,
                'actionUrl' => url('/jobs/' . (string) $jobId . '/employees'),
                'searchUrl' => url('/jobs/' . (string) $jobId . '/employees/search'),
                'assignedEmployees' => Job::assignedEmployees($businessId, $jobId),
                'errors' => ['employee_id' => 'Choose a valid employee from suggestions.'],
                'form' => [
                    'employee_id' => '',
                    'employee_name' => trim((string) ($_POST['employee_name'] ?? '')),
                ],
            ]);
            return;
        }

        $assigned = Job::assignEmployee($businessId, $jobId, $employeeId, auth_user_id() ?? 0);
        if ($assigned) {
            flash('success', 'Employee added to job.');
            redirect('/jobs/' . (string) $jobId);
        }

        flash('error', 'Unable to add employee to this job.');
        redirect('/jobs/' . (string) $jobId . '/employees/add');
    }

    public function punchInEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $employeeId = (int) ($params['employeeId'] ?? 0);
        if ($jobId <= 0 || $employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId);
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $employee = Job::findAssignedEmployee($businessId, $jobId, $employeeId);
        if ($employee === null) {
            flash('error', 'Employee is not assigned to this job.');
            redirect('/jobs/' . (string) $jobId);
        }
        $employeeStatus = strtolower(trim((string) ($employee['employee_status'] ?? 'active')));
        if ($employeeStatus === 'inactive') {
            flash('error', 'Inactive employees cannot be punched in.');
            redirect('/jobs/' . (string) $jobId);
        }

        if (TimeEntry::openEntryForEmployee($businessId, $employeeId) !== null) {
            flash('error', 'This employee is already punched in.');
            redirect('/jobs/' . (string) $jobId);
        }

        TimeEntry::punchInNow(
            $businessId,
            $employeeId,
            $jobId,
            false,
            auth_user_id() ?? 0,
            trim((string) ($_POST['notes'] ?? ''))
        );

        flash('success', 'Employee punched in.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function punchOutEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $employeeId = (int) ($params['employeeId'] ?? 0);
        if ($jobId <= 0 || $employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId);
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $employee = Job::findAssignedEmployee($businessId, $jobId, $employeeId);
        if ($employee === null) {
            flash('error', 'Employee is not assigned to this job.');
            redirect('/jobs/' . (string) $jobId);
        }

        $entryId = TimeEntry::punchOutOpenEntry($businessId, $employeeId, auth_user_id() ?? 0);
        if (($entryId ?? 0) <= 0) {
            flash('error', 'No open time entry found for this employee.');
            redirect('/jobs/' . (string) $jobId);
        }

        flash('success', 'Employee punched out.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function createExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/expense_form', [
            'pageTitle' => 'Add Expense',
            'job' => $job,
            'actionUrl' => url('/jobs/' . (string) $jobId . '/expenses'),
            'form' => $this->defaultExpenseForm(),
            'errors' => [],
            'categoryOptions' => Expense::categoryOptions($businessId),
        ]);
    }

    public function storeExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/expenses/create');
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->expenseFormFromPost($_POST);
        $errors = $this->validateExpenseForm($form);
        if ($errors !== []) {
            $this->render('jobs/expense_form', [
                'pageTitle' => 'Add Expense',
                'job' => $job,
                'actionUrl' => url('/jobs/' . (string) $jobId . '/expenses'),
                'form' => $form,
                'errors' => $errors,
                'categoryOptions' => Expense::categoryOptions($businessId),
            ]);
            return;
        }

        $expenseId = Job::createExpense($businessId, $jobId, $this->expensePayloadForSave($form), auth_user_id() ?? 0);
        if ($expenseId <= 0) {
            flash('error', 'Expenses table is missing or unavailable. Run migrations and try again.');
            redirect('/jobs/' . (string) $jobId . '/expenses/create');
        }

        flash('success', 'Expense added.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function editExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $expenseId = (int) ($params['expenseId'] ?? 0);
        if ($jobId <= 0 || $expenseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $expense = Job::findExpenseForJob($businessId, $jobId, $expenseId);
        if ($expense === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/expense_form', [
            'pageTitle' => 'Edit Expense',
            'mode' => 'edit',
            'job' => $job,
            'expenseId' => $expenseId,
            'actionUrl' => url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId . '/update'),
            'form' => $this->expenseFormFromModel($expense),
            'errors' => [],
            'categoryOptions' => Expense::categoryOptions($businessId),
        ]);
    }

    public function updateExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $expenseId = (int) ($params['expenseId'] ?? 0);
        if ($jobId <= 0 || $expenseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId . '/edit');
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $expense = Job::findExpenseForJob($businessId, $jobId, $expenseId);
        if ($expense === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->expenseFormFromPost($_POST);
        $errors = $this->validateExpenseForm($form);
        if ($errors !== []) {
            $this->render('jobs/expense_form', [
                'pageTitle' => 'Edit Expense',
                'mode' => 'edit',
                'job' => $job,
                'expenseId' => $expenseId,
                'actionUrl' => url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'categoryOptions' => Expense::categoryOptions($businessId),
            ]);
            return;
        }

        $updated = Job::updateExpense($businessId, $jobId, $expenseId, $this->expensePayloadForSave($form), auth_user_id() ?? 0);
        if ($updated) {
            flash('success', 'Expense updated.');
        } else {
            flash('error', 'Unable to update expense.');
        }
        redirect('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId);
    }

    public function showExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $expenseId = (int) ($params['expenseId'] ?? 0);
        if ($jobId <= 0 || $expenseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $expense = Job::findExpenseForJob($businessId, $jobId, $expenseId);
        if ($expense === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/expense_show', [
            'pageTitle' => 'Expense',
            'job' => $job,
            'expense' => $expense,
        ]);
    }

    public function deleteExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $expenseId = (int) ($params['expenseId'] ?? 0);
        if ($jobId <= 0 || $expenseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId);
        }

        $businessId = current_business_id();
        $expense = Job::findExpenseForJob($businessId, $jobId, $expenseId);
        if ($expense === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = Job::softDeleteExpense($businessId, $jobId, $expenseId, auth_user_id() ?? 0);
        if ($deleted) {
            flash('success', 'Expense deleted.');
        } else {
            flash('error', 'Unable to delete expense.');
        }
        redirect('/jobs/' . (string) $jobId);
    }

    public function createAdjustment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/adjustment_form', [
            'pageTitle' => 'Add Adjustment',
            'job' => $job,
            'actionUrl' => url('/jobs/' . (string) $jobId . '/adjustments'),
            'form' => $this->defaultAdjustmentForm(),
            'errors' => [],
        ]);
    }

    public function storeAdjustment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/adjustments/create');
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->adjustmentFormFromPost($_POST);
        $errors = $this->validateAdjustmentForm($form);
        if ($errors !== []) {
            $this->render('jobs/adjustment_form', [
                'pageTitle' => 'Add Adjustment',
                'job' => $job,
                'actionUrl' => url('/jobs/' . (string) $jobId . '/adjustments'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        $adjustmentId = Job::createAdjustment($businessId, $jobId, $this->adjustmentPayloadForSave($form), auth_user_id() ?? 0);
        if ($adjustmentId <= 0) {
            flash('error', 'Adjustments table is missing or unavailable. Run migrations and try again.');
            redirect('/jobs/' . (string) $jobId . '/adjustments/create');
        }

        flash('success', 'Adjustment added.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function editAdjustment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $adjustmentId = (int) ($params['adjustmentId'] ?? 0);
        if ($jobId <= 0 || $adjustmentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $adjustment = Job::findAdjustmentForJob($businessId, $jobId, $adjustmentId);
        if ($adjustment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/adjustment_form', [
            'pageTitle' => 'Edit Adjustment',
            'mode' => 'edit',
            'job' => $job,
            'adjustmentId' => $adjustmentId,
            'actionUrl' => url('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId . '/update'),
            'form' => $this->adjustmentFormFromModel($adjustment),
            'errors' => [],
        ]);
    }

    public function updateAdjustment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $adjustmentId = (int) ($params['adjustmentId'] ?? 0);
        if ($jobId <= 0 || $adjustmentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId . '/edit');
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $adjustment = Job::findAdjustmentForJob($businessId, $jobId, $adjustmentId);
        if ($adjustment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->adjustmentFormFromPost($_POST);
        $errors = $this->validateAdjustmentForm($form);
        if ($errors !== []) {
            $this->render('jobs/adjustment_form', [
                'pageTitle' => 'Edit Adjustment',
                'mode' => 'edit',
                'job' => $job,
                'adjustmentId' => $adjustmentId,
                'actionUrl' => url('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId . '/update'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        $updated = Job::updateAdjustment($businessId, $jobId, $adjustmentId, $this->adjustmentPayloadForSave($form), auth_user_id() ?? 0);
        if ($updated) {
            flash('success', 'Adjustment updated.');
        } else {
            flash('error', 'Unable to update adjustment.');
        }
        redirect('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId);
    }

    public function showAdjustment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $adjustmentId = (int) ($params['adjustmentId'] ?? 0);
        if ($jobId <= 0 || $adjustmentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $adjustment = Job::findAdjustmentForJob($businessId, $jobId, $adjustmentId);
        if ($adjustment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('jobs/adjustment_show', [
            'pageTitle' => 'Adjustment',
            'job' => $job,
            'adjustment' => $adjustment,
        ]);
    }

    public function deleteAdjustment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $jobId = (int) ($params['id'] ?? 0);
        $adjustmentId = (int) ($params['adjustmentId'] ?? 0);
        if ($jobId <= 0 || $adjustmentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId);
        }

        $businessId = current_business_id();
        $adjustment = Job::findAdjustmentForJob($businessId, $jobId, $adjustmentId);
        if ($adjustment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = Job::softDeleteAdjustment($businessId, $jobId, $adjustmentId, auth_user_id() ?? 0);
        if ($deleted) {
            flash('success', 'Adjustment deleted.');
        } else {
            flash('error', 'Unable to delete adjustment.');
        }
        redirect('/jobs/' . (string) $jobId);
    }

    private function defaultForm(): array
    {
        return [
            'title' => '',
            'status' => 'pending',
            'client_id' => '',
            'scheduled_start_at' => '',
            'scheduled_end_at' => '',
            'actual_start_at' => '',
            'actual_end_at' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'notes' => '',
        ];
    }

    private function defaultExpenseForm(): array
    {
        return [
            'expense_date' => date('Y-m-d'),
            'amount' => '',
            'category' => '',
            'payment_method' => '',
            'note' => '',
        ];
    }

    private function defaultAdjustmentForm(): array
    {
        return [
            'name' => '',
            'adjustment_date' => date('Y-m-d'),
            'amount' => '',
            'note' => '',
        ];
    }

    private function formFromModel(array $job): array
    {
        return [
            'title' => trim((string) ($job['title'] ?? '')),
            'status' => strtolower(trim((string) ($job['status'] ?? 'pending'))),
            'client_id' => (string) ((int) ($job['client_id'] ?? 0)),
            'scheduled_start_at' => $this->toInputDatetime((string) ($job['scheduled_start_at'] ?? '')),
            'scheduled_end_at' => $this->toInputDatetime((string) ($job['scheduled_end_at'] ?? '')),
            'actual_start_at' => $this->toInputDatetime((string) ($job['actual_start_at'] ?? '')),
            'actual_end_at' => $this->toInputDatetime((string) ($job['actual_end_at'] ?? '')),
            'address_line1' => trim((string) ($job['address_line1'] ?? '')),
            'address_line2' => trim((string) ($job['address_line2'] ?? '')),
            'city' => trim((string) ($job['city'] ?? '')),
            'state' => trim((string) ($job['state'] ?? '')),
            'postal_code' => trim((string) ($job['postal_code'] ?? '')),
            'notes' => trim((string) ($job['notes'] ?? '')),
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'status' => strtolower(trim((string) ($input['status'] ?? 'pending'))),
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'scheduled_start_at' => trim((string) ($input['scheduled_start_at'] ?? '')),
            'scheduled_end_at' => trim((string) ($input['scheduled_end_at'] ?? '')),
            'actual_start_at' => trim((string) ($input['actual_start_at'] ?? '')),
            'actual_end_at' => trim((string) ($input['actual_end_at'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    private function expenseFormFromPost(array $input): array
    {
        return [
            'expense_date' => trim((string) ($input['expense_date'] ?? '')),
            'amount' => trim((string) ($input['amount'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'payment_method' => trim((string) ($input['payment_method'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
        ];
    }

    private function adjustmentFormFromPost(array $input): array
    {
        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'adjustment_date' => trim((string) ($input['adjustment_date'] ?? '')),
            'amount' => trim((string) ($input['amount'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
        ];
    }

    private function expenseFormFromModel(array $expense): array
    {
        $dateRaw = trim((string) ($expense['expense_date'] ?? ''));
        $dateStamp = $dateRaw !== '' ? strtotime($dateRaw) : false;
        $date = $dateStamp === false ? '' : date('Y-m-d', $dateStamp);

        return [
            'expense_date' => $date,
            'amount' => number_format((float) ($expense['amount'] ?? 0), 2, '.', ''),
            'category' => trim((string) ($expense['category'] ?? '')),
            'payment_method' => trim((string) ($expense['payment_method'] ?? '')),
            'note' => trim((string) ($expense['note'] ?? '')),
        ];
    }

    private function adjustmentFormFromModel(array $adjustment): array
    {
        $dateRaw = trim((string) ($adjustment['adjustment_date'] ?? ''));
        $dateStamp = $dateRaw !== '' ? strtotime($dateRaw) : false;
        $date = $dateStamp === false ? '' : date('Y-m-d', $dateStamp);

        return [
            'name' => trim((string) ($adjustment['name'] ?? '')),
            'adjustment_date' => $date,
            'amount' => number_format((float) ($adjustment['amount'] ?? 0), 2, '.', ''),
            'note' => trim((string) ($adjustment['note'] ?? '')),
        ];
    }

    private function validateForm(array $form, int $businessId): array
    {
        $errors = [];
        $allowedStatuses = ['prospect', 'pending', 'active', 'complete', 'cancelled'];

        if ($form['title'] === '') {
            $errors['title'] = 'Job name is required.';
        }

        $clientId = (int) $form['client_id'];
        if ($clientId <= 0 || Client::findForBusiness($businessId, $clientId) === null) {
            $errors['client_id'] = 'Choose a valid client.';
        }

        if (!in_array($form['status'], $allowedStatuses, true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        $this->validateDatetimeInput($form['scheduled_start_at'], 'scheduled_start_at', $errors);
        $this->validateDatetimeInput($form['scheduled_end_at'], 'scheduled_end_at', $errors);
        $this->validateDatetimeInput($form['actual_start_at'], 'actual_start_at', $errors);
        $this->validateDatetimeInput($form['actual_end_at'], 'actual_end_at', $errors);

        $scheduledStart = $this->asTimestamp($form['scheduled_start_at']);
        $scheduledEnd = $this->asTimestamp($form['scheduled_end_at']);
        if ($scheduledStart !== null && $scheduledEnd !== null && $scheduledEnd < $scheduledStart) {
            $errors['scheduled_end_at'] = 'Scheduled end must be after scheduled start.';
        }

        $actualStart = $this->asTimestamp($form['actual_start_at']);
        $actualEnd = $this->asTimestamp($form['actual_end_at']);
        if ($actualStart !== null && $actualEnd !== null && $actualEnd < $actualStart) {
            $errors['actual_end_at'] = 'Actual end must be after actual start.';
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'title' => $form['title'],
            'status' => $form['status'],
            'client_id' => (int) $form['client_id'],
            'scheduled_start_at' => $this->toDatabaseDatetime($form['scheduled_start_at']),
            'scheduled_end_at' => $this->toDatabaseDatetime($form['scheduled_end_at']),
            'actual_start_at' => $this->toDatabaseDatetime($form['actual_start_at']),
            'actual_end_at' => $this->toDatabaseDatetime($form['actual_end_at']),
            'address_line1' => $form['address_line1'],
            'address_line2' => $form['address_line2'],
            'city' => $form['city'],
            'state' => $form['state'],
            'postal_code' => $form['postal_code'],
            'notes' => $form['notes'],
        ];
    }

    private function adjustmentPayloadForSave(array $form): array
    {
        return [
            'name' => $form['name'],
            'adjustment_date' => $this->toDatabaseDate($form['adjustment_date']),
            'amount' => (float) $form['amount'],
            'note' => $form['note'],
        ];
    }

    private function expensePayloadForSave(array $form): array
    {
        return [
            'expense_date' => $this->toDatabaseDate($form['expense_date']),
            'amount' => (float) $form['amount'],
            'category' => $form['category'],
            'payment_method' => $form['payment_method'],
            'note' => $form['note'],
        ];
    }

    private function validateDatetimeInput(string $value, string $field, array &$errors): void
    {
        if ($value === '') {
            return;
        }

        if ($this->asTimestamp($value) === null) {
            $errors[$field] = 'Enter a valid date/time.';
        }
    }

    private function validateExpenseForm(array $form): array
    {
        $errors = [];

        if ($this->asDate($form['expense_date']) === null) {
            $errors['expense_date'] = 'Enter a valid expense date.';
        }

        if (!is_numeric($form['amount']) || (float) $form['amount'] <= 0) {
            $errors['amount'] = 'Enter an amount greater than 0.';
        }

        return $errors;
    }

    private function validateAdjustmentForm(array $form): array
    {
        $errors = [];

        if (trim((string) ($form['name'] ?? '')) === '') {
            $errors['name'] = 'Adjustment name is required.';
        }

        if ($this->asDate($form['adjustment_date']) === null) {
            $errors['adjustment_date'] = 'Enter a valid adjustment date.';
        }

        if (!is_numeric($form['amount']) || (float) $form['amount'] == 0.0) {
            $errors['amount'] = 'Enter a non-zero amount.';
        }

        return $errors;
    }

    private function asTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function toDatabaseDatetime(string $value): ?string
    {
        $timestamp = $this->asTimestamp($value);
        return $timestamp === null ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function asDate(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function toDatabaseDate(string $value): ?string
    {
        $timestamp = $this->asDate($value);
        return $timestamp === null ? null : date('Y-m-d', $timestamp);
    }

    private function toInputDatetime(string $value): string
    {
        $timestamp = $this->asTimestamp($value);
        return $timestamp === null ? '' : date('Y-m-d\TH:i', $timestamp);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
