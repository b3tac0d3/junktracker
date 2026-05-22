<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstateSale;
use App\Models\FormSelectValue;
use App\Models\Sale;
use App\Models\TimeEntry;
use Core\Controller;

final class EstateSalesController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'dispatch')));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'date')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'desc')));
        if (!in_array($sortBy, ['date', 'id', 'title'], true)) {
            $sortBy = 'date';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $fromDate = $this->normalizeDateFilter((string) ($_GET['from'] ?? date('Y-01-01')));
        $toDate = $this->normalizeDateFilter((string) ($_GET['to'] ?? date('Y-12-31')));
        if ($fromDate !== '' && $toDate !== '' && strtotime($fromDate) > strtotime($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $businessId = current_business_id();
        $statusOptions = EstateSale::statusOptions($businessId);
        $allowedFilterStatuses = array_merge(['dispatch', ''], $statusOptions);
        if (!in_array($status, $allowedFilterStatuses, true)) {
            $status = 'dispatch';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = EstateSale::indexCount($businessId, $search, $status, $fromDate, $toDate);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $estateSales = EstateSale::indexList(
            $businessId,
            $search,
            $status,
            $fromDate,
            $toDate,
            $perPage,
            $offset,
            $sortBy,
            $sortDir
        );
        $pagination = pagination_meta($page, $perPage, $totalRows, count($estateSales));

        $this->render('estate-sales/index', [
            'pageTitle' => 'Estate Sales',
            'search' => $search,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusOptions' => $statusOptions,
            'estateSales' => $estateSales,
            'pagination' => $pagination,
        ]);
    }

    public function records(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'date')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'desc')));
        if (!in_array($sortBy, ['date', 'id', 'customer_name', 'estate_sale_title'], true)) {
            $sortBy = 'date';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $fromDate = $this->normalizeDateFilter((string) ($_GET['from'] ?? date('Y-01-01')));
        $toDate = $this->normalizeDateFilter((string) ($_GET['to'] ?? date('Y-m-d')));
        if ($fromDate !== '' && $toDate !== '' && strtotime($fromDate) > strtotime($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Sale::indexCount($businessId, $search, '', $fromDate, $toDate, Sale::ESTATE_SCOPE_ESTATE_ONLY);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $records = Sale::indexList(
            $businessId,
            $search,
            '',
            $fromDate,
            $toDate,
            $perPage,
            $offset,
            $sortBy,
            $sortDir,
            Sale::ESTATE_SCOPE_ESTATE_ONLY
        );
        $summary = Sale::summary($businessId, Sale::ESTATE_SCOPE_ESTATE_ONLY);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($records));

        $this->render('estate-sales/records', [
            'pageTitle' => 'Estate Sale Records',
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'records' => $records,
            'summary' => $summary,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $form = $this->defaultForm();
        $statusOptions = EstateSale::statusOptions($businessId);
        if (!in_array((string) ($form['status'] ?? ''), $statusOptions, true)) {
            $form['status'] = (string) ($statusOptions[0] ?? 'scheduled');
        }

        $this->render('estate-sales/form', [
            'pageTitle' => 'Add Estate Sale',
            'mode' => 'create',
            'actionUrl' => url('/estate-sales'),
            'form' => $form,
            'errors' => [],
            'statusOptions' => $statusOptions,
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = EstateSale::validate($form, $businessId);
        $statusOptions = EstateSale::statusOptions($businessId);
        if ($errors !== []) {
            $this->render('estate-sales/form', [
                'pageTitle' => 'Add Estate Sale',
                'mode' => 'create',
                'actionUrl' => url('/estate-sales'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => $statusOptions,
            ]);

            return;
        }

        $id = EstateSale::create($businessId, EstateSale::payloadFromForm($form), auth_user_id() ?? 0);
        if ($id <= 0) {
            flash('error', 'Could not save estate sale.');
            redirect('/estate-sales/create');
        }

        flash('success', 'Estate sale added.');
        redirect('/estate-sales/' . (string) $id);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);

        $activeTab = strtolower(trim((string) ($_GET['tab'] ?? 'details')));
        if (!in_array($activeTab, ['details', 'customers', 'sales', 'expenses', 'labor'], true)) {
            $activeTab = 'details';
        }

        $customersPerPage = pagination_per_page($_GET['customers_per_page'] ?? null);
        $customersPage = pagination_current_page($_GET['customers_page'] ?? null);
        $customersStatusFilter = EstateSale::normalizeCustomersStatusFilter($_GET['customers_status'] ?? null);
        $customersTotal = EstateSale::customersCount(
            $businessId,
            $estateSaleId,
            $customersStatusFilter === 'all' ? null : $customersStatusFilter
        );
        $customersTotalPages = pagination_total_pages($customersTotal, $customersPerPage);
        if ($customersPage > $customersTotalPages) {
            $customersPage = $customersTotalPages;
        }
        $customersOffset = pagination_offset($customersPage, $customersPerPage);
        $customers = EstateSale::customers(
            $businessId,
            $estateSaleId,
            $customersPerPage,
            $customersOffset,
            $customersStatusFilter === 'all' ? null : $customersStatusFilter
        );
        $customersPagination = pagination_meta($customersPage, $customersPerPage, $customersTotal, count($customers));

        $salesPerPage = pagination_per_page($_GET['sales_per_page'] ?? null);
        $salesPage = pagination_current_page($_GET['sales_page'] ?? null);
        $salesTotalCount = EstateSale::salesCount($businessId, $estateSaleId);
        $salesTotalPages = pagination_total_pages($salesTotalCount, $salesPerPage);
        if ($salesPage > $salesTotalPages) {
            $salesPage = $salesTotalPages;
        }
        $salesOffset = pagination_offset($salesPage, $salesPerPage);
        $salesData = EstateSale::salesSummary($businessId, $estateSaleId, $salesPerPage, $salesOffset);
        $salesPagination = pagination_meta($salesPage, $salesPerPage, $salesTotalCount, count($salesData['sales']));

        $timeSummary = EstateSale::timeSummary($businessId, $estateSaleId);
        $timeLogs = EstateSale::timeLogsByEstateSale($businessId, $estateSaleId);
        $laborCost = EstateSale::laborCostByEstateSale($businessId, $estateSaleId);
        $assignedEmployees = EstateSale::assignedEmployees($businessId, $estateSaleId);
        foreach ($assignedEmployees as $index => $employee) {
            if (!is_array($employee)) {
                continue;
            }
            $employeeId = (int) ($employee['employee_id'] ?? 0);
            $openEntry = $employeeId > 0 ? TimeEntry::openEntryForEmployee($businessId, $employeeId) : null;
            $assignedEmployees[$index]['open_entry_id'] = (int) ($openEntry['id'] ?? 0);
            $assignedEmployees[$index]['open_clock_in_at'] = (string) ($openEntry['clock_in_at'] ?? '');
            $assignedEmployees[$index]['open_job_id'] = (int) ($openEntry['job_id'] ?? 0);
            $assignedEmployees[$index]['open_estate_sale_id'] = (int) ($openEntry['estate_sale_id'] ?? 0);
            $assignedEmployees[$index]['open_job_title'] = (string) ($openEntry['job_title'] ?? '');
            $assignedEmployees[$index]['is_open_for_this_estate_sale'] = ((int) ($openEntry['estate_sale_id'] ?? 0)) === $estateSaleId
                && ((int) ($openEntry['is_non_job'] ?? 0)) !== 1;
        }

        $this->render('estate-sales/show', [
            'pageTitle' => trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId),
            'estateSale' => $estateSale,
            'customers' => $customers,
            'customersPagination' => $customersPagination,
            'customerPresence' => EstateSale::customerPresenceSummary($businessId, $estateSaleId),
            'customersStatusFilter' => $customersStatusFilter,
            'sales' => $salesData['sales'],
            'salesCount' => (int) ($salesData['count'] ?? 0),
            'salesTotal' => (float) ($salesData['total_amount'] ?? 0),
            'salesPagination' => $salesPagination,
            'expenses' => EstateSale::expenses($businessId, $estateSaleId),
            'expenseCategoryOptions' => EstateSale::expenseCategoryOptions($businessId),
            'financialSummary' => EstateSale::financialSummary($businessId, $estateSaleId, $estateSale),
            'statusOptions' => EstateSale::statusOptions($businessId),
            'canRemoveCustomers' => is_site_admin() || workspace_role() === 'admin',
            'activeTab' => $activeTab,
            'timeSummary' => $timeSummary,
            'timeLogs' => $timeLogs,
            'laborCost' => $laborCost,
            'assignedEmployees' => $assignedEmployees,
        ]);
    }

    public function addEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);

        $this->render('estate-sales/employee_add', [
            'pageTitle' => 'Add Employee',
            'estateSale' => $estateSale,
            'actionUrl' => url('/estate-sales/' . (string) $estateSaleId . '/employees'),
            'searchUrl' => url('/estate-sales/' . (string) $estateSaleId . '/employees/search'),
            'assignedEmployees' => EstateSale::assignedEmployees($businessId, $estateSaleId),
            'availableEmployees' => EstateSale::unassignedEmployeesForEstateSale($businessId, $estateSaleId),
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

        $estateSaleId = (int) ($params['id'] ?? 0);
        if ($estateSaleId <= 0) {
            $this->json(['ok' => false, 'results' => []], 404);
        }

        $businessId = current_business_id();
        if (EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            $this->json(['ok' => false, 'results' => []], 404);
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $items = EstateSale::employeeSearchOptions($businessId, $estateSaleId, $query, 10);
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

        $this->json(['ok' => true, 'results' => $results]);
    }

    public function storeEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales/' . (string) $estateSaleId . '/employees/add');
        }

        $businessId = current_business_id();
        $actorUserId = auth_user_id() ?? 0;
        $bulkRaw = $_POST['employee_ids'] ?? [];
        $employeeIds = [];
        if (is_array($bulkRaw)) {
            foreach ($bulkRaw as $raw) {
                $eid = (int) $raw;
                if ($eid > 0) {
                    $employeeIds[$eid] = true;
                }
            }
        }
        $employeeIds = array_keys($employeeIds);
        if ($employeeIds === []) {
            $employeeIds = [(int) ($_POST['employee_id'] ?? 0)];
            $employeeIds = array_values(array_filter($employeeIds, static fn (int $id): bool => $id > 0));
        }

        if ($employeeIds === []) {
            $this->render('estate-sales/employee_add', [
                'pageTitle' => 'Add Employee',
                'estateSale' => $estateSale,
                'actionUrl' => url('/estate-sales/' . (string) $estateSaleId . '/employees'),
                'searchUrl' => url('/estate-sales/' . (string) $estateSaleId . '/employees/search'),
                'assignedEmployees' => EstateSale::assignedEmployees($businessId, $estateSaleId),
                'availableEmployees' => EstateSale::unassignedEmployeesForEstateSale($businessId, $estateSaleId),
                'errors' => ['employee_ids' => 'Select at least one employee, or pick one from the search field.'],
                'form' => [
                    'employee_id' => '',
                    'employee_name' => trim((string) ($_POST['employee_name'] ?? '')),
                ],
            ]);
            return;
        }

        $ok = 0;
        $fail = 0;
        foreach ($employeeIds as $employeeId) {
            if (EstateSale::assignEmployee($businessId, $estateSaleId, $employeeId, $actorUserId)) {
                $ok++;
            } else {
                $fail++;
            }
        }

        if ($ok > 0 && $fail === 0) {
            flash('success', $ok === 1 ? 'Employee added to estate sale.' : ((string) $ok . ' employees added to estate sale.'));
            redirect($this->laborReturnUrl($estateSaleId));
        }
        if ($ok > 0 && $fail > 0) {
            flash('success', 'Added ' . (string) $ok . ' employee(s). ' . (string) $fail . ' could not be added (inactive or invalid).');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        flash('error', 'Unable to add employee(s) to this estate sale.');
        redirect('/estate-sales/' . (string) $estateSaleId . '/employees/add');
    }

    public function punchInEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSaleId = (int) ($params['id'] ?? 0);
        $employeeId = (int) ($params['employeeId'] ?? 0);
        if ($estateSaleId <= 0 || $employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        $businessId = current_business_id();
        if (EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $employee = EstateSale::findAssignedEmployee($businessId, $estateSaleId, $employeeId);
        if ($employee === null) {
            flash('error', 'Employee is not assigned to this estate sale.');
            redirect($this->laborReturnUrl($estateSaleId));
        }
        if (strtolower(trim((string) ($employee['employee_status'] ?? 'active'))) === 'inactive') {
            flash('error', 'Inactive employees cannot be punched in.');
            redirect($this->laborReturnUrl($estateSaleId));
        }
        if (TimeEntry::openEntryForEmployee($businessId, $employeeId) !== null) {
            flash('error', 'This employee is already punched in.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        TimeEntry::punchInNow($businessId, $employeeId, null, false, auth_user_id() ?? 0, trim((string) ($_POST['notes'] ?? '')), $estateSaleId);
        flash('success', 'Employee punched in.');
        redirect($this->laborReturnUrl($estateSaleId));
    }

    public function punchOutEmployee(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSaleId = (int) ($params['id'] ?? 0);
        $employeeId = (int) ($params['employeeId'] ?? 0);
        if ($estateSaleId <= 0 || $employeeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        $businessId = current_business_id();
        if (EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (EstateSale::findAssignedEmployee($businessId, $estateSaleId, $employeeId) === null) {
            flash('error', 'Employee is not assigned to this estate sale.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        $entryId = TimeEntry::punchOutOpenEntry($businessId, $employeeId, auth_user_id() ?? 0);
        if (($entryId ?? 0) <= 0) {
            flash('error', 'No open time entry found for this employee.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        flash('success', 'Employee punched out.');
        redirect($this->laborReturnUrl($estateSaleId));
    }

    public function bulkPunchEmployees(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSaleId = (int) ($params['id'] ?? 0);
        if ($estateSaleId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        $action = strtolower(trim((string) ($_POST['bulk_action'] ?? '')));
        if ($action !== 'in' && $action !== 'out') {
            flash('error', 'Choose a valid bulk punch action.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        $rawIds = $_POST['employee_ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $employeeIds = [];
        foreach ($rawIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $employeeIds[$id] = true;
            }
        }
        $employeeIds = array_keys($employeeIds);
        if ($employeeIds === []) {
            flash('error', 'Select at least one employee.');
            redirect($this->laborReturnUrl($estateSaleId));
        }

        $businessId = current_business_id();
        if (EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $actorUserId = auth_user_id() ?? 0;
        $done = 0;
        $skipped = 0;
        foreach ($employeeIds as $employeeId) {
            $employee = EstateSale::findAssignedEmployee($businessId, $estateSaleId, $employeeId);
            if ($employee === null) {
                $skipped++;
                continue;
            }

            if ($action === 'in') {
                if (strtolower(trim((string) ($employee['employee_status'] ?? 'active'))) === 'inactive') {
                    $skipped++;
                    continue;
                }
                if (TimeEntry::openEntryForEmployee($businessId, $employeeId) !== null) {
                    $skipped++;
                    continue;
                }
                TimeEntry::punchInNow($businessId, $employeeId, null, false, $actorUserId, '', $estateSaleId);
                $done++;
            } else {
                $entryId = TimeEntry::punchOutOpenEntry($businessId, $employeeId, $actorUserId);
                if (($entryId ?? 0) <= 0) {
                    $skipped++;
                    continue;
                }
                $done++;
            }
        }

        if ($done > 0 && $skipped > 0) {
            $verb = $action === 'in' ? 'in' : 'out';
            flash('success', 'Punched ' . $verb . ' ' . (string) $done . ' employee(s). ' . (string) $skipped . ' skipped (already in that state or not applicable).');
        } elseif ($done > 0) {
            $verb = $action === 'in' ? 'in' : 'out';
            flash('success', 'Punched ' . $verb . ' ' . (string) $done . ' employee(s).');
        } else {
            flash('success', 'No changes were needed for the selected employees.');
        }

        redirect($this->laborReturnUrl($estateSaleId));
    }

    private function laborReturnUrl(int $estateSaleId): string
    {
        return '/estate-sales/' . (string) $estateSaleId . '?tab=labor';
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);

        $this->render('estate-sales/form', [
            'pageTitle' => 'Edit Estate Sale',
            'mode' => 'edit',
            'actionUrl' => url('/estate-sales/' . (string) $estateSaleId . '/update'),
            'form' => $this->formFromRow($estateSale),
            'errors' => [],
            'estateSaleId' => $estateSaleId,
            'statusOptions' => EstateSale::statusOptions($businessId),
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales');
        }

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $estateSale = EstateSale::findForBusiness($businessId, $id);
        if ($estateSale === null) {
            flash('error', 'Estate sale not found.');
            redirect('/estate-sales');
        }

        $form = $this->formFromPost($_POST);
        $errors = EstateSale::validate($form, $businessId);
        $statusOptions = EstateSale::statusOptions($businessId);
        if ($errors !== []) {
            $this->render('estate-sales/form', [
                'pageTitle' => 'Edit Estate Sale',
                'mode' => 'edit',
                'actionUrl' => url('/estate-sales/' . (string) $id . '/update'),
                'form' => $form,
                'errors' => $errors,
                'estateSaleId' => $id,
                'statusOptions' => $statusOptions,
            ]);

            return;
        }

        EstateSale::update($businessId, $id, EstateSale::payloadFromForm($form), auth_user_id() ?? 0);
        flash('success', 'Estate sale updated.');
        redirect('/estate-sales/' . (string) $id);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales');
        }

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if (EstateSale::softDelete($businessId, $id, $actorId)) {
            flash('success', 'Estate sale removed.');
        } else {
            flash('error', 'Could not remove estate sale.');
        }

        redirect('/estate-sales');
    }

    public function quickCreateCustomer(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        $actorId = (int) (auth_user_id() ?? 0);

        if ($estateSaleId <= 0 || EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            $this->json(['ok' => false, 'error' => 'Estate sale not found.'], 404);
            return;
        }

        $payload = EstateSale::customerPayloadFromInput($_POST);
        $errors = EstateSale::validateCustomer($payload);
        if ($errors !== []) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
            return;
        }

        $customerId = EstateSale::createCustomer($businessId, $estateSaleId, $payload, $actorId);
        if ($customerId <= 0) {
            $this->json(['ok' => false, 'error' => 'Could not save customer. Run latest migrations if needed.'], 422);
            return;
        }

        $customer = EstateSale::findCustomerForSale($businessId, $estateSaleId, $customerId);
        if (!is_array($customer)) {
            $this->json(['ok' => false, 'error' => 'Could not load saved customer.'], 422);
            return;
        }

        $this->json([
            'ok' => true,
            'message' => 'Customer added.',
            'customer' => EstateSale::customerPayloadForJson($customer),
            'presence' => EstateSale::customerPresenceSummary($businessId, $estateSaleId),
        ], 201);
    }

    public function showCustomer(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $customerId = (int) ($params['customerId'] ?? 0);
        $customer = EstateSale::findCustomerForSale($businessId, $estateSaleId, $customerId);
        if ($customer === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Customer not found']);

            return;
        }

        $this->render('estate-sales/customer_show', [
            'pageTitle' => $this->customerPageTitle($customer),
            'estateSale' => $estateSale,
            'customer' => $customer,
            'visits' => EstateSale::customerVisits($businessId, $estateSaleId, $customerId),
            'sales' => EstateSale::customerSales($businessId, $estateSaleId, $customerId),
            'canRemoveCustomers' => is_site_admin() || workspace_role() === 'admin',
        ]);
    }

    public function checkInCustomer(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        $customerId = (int) ($params['customerId'] ?? 0);
        $actorId = (int) (auth_user_id() ?? 0);

        if ($estateSaleId <= 0 || EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            $this->json(['ok' => false, 'error' => 'Estate sale not found.'], 404);
            return;
        }

        $customer = EstateSale::checkInCustomer($businessId, $estateSaleId, $customerId, $actorId);
        if ($customer === null) {
            $this->json(['ok' => false, 'error' => 'Could not check in customer. They may already be inside.'], 422);
            return;
        }

        $this->json([
            'ok' => true,
            'message' => 'Customer checked in.',
            'customer' => EstateSale::customerPayloadForJson($customer),
            'presence' => EstateSale::customerPresenceSummary($businessId, $estateSaleId),
        ]);
    }

    public function checkOutCustomer(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        $customerId = (int) ($params['customerId'] ?? 0);
        $actorId = (int) (auth_user_id() ?? 0);

        if ($estateSaleId <= 0 || EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            $this->json(['ok' => false, 'error' => 'Estate sale not found.'], 404);
            return;
        }

        $customer = EstateSale::checkOutCustomer($businessId, $estateSaleId, $customerId, $actorId);
        if ($customer === null) {
            $this->json(['ok' => false, 'error' => 'Could not check out customer. They may not be checked in.'], 422);
            return;
        }

        $this->json([
            'ok' => true,
            'message' => 'Customer checked out.',
            'customer' => EstateSale::customerPayloadForJson($customer),
            'presence' => EstateSale::customerPresenceSummary($businessId, $estateSaleId),
        ]);
    }

    public function removeCustomer(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!is_site_admin() && workspace_role() !== 'admin') {
            http_response_code(403);
            flash('error', 'Only admins can remove customers from an estate sale.');
            $estateSaleId = (int) ($params['id'] ?? 0);
            redirect($estateSaleId > 0 ? '/estate-sales/' . (string) $estateSaleId : '/estate-sales');
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales');
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        $customerId = (int) ($params['customerId'] ?? 0);
        $actorId = (int) (auth_user_id() ?? 0);

        if (EstateSale::removeCustomer($businessId, $estateSaleId, $customerId, $actorId)) {
            flash('success', 'Customer removed from this estate sale.');
        } else {
            flash('error', 'Could not remove customer.');
        }

        redirect('/estate-sales/' . (string) $estateSaleId . '?tab=customers');
    }

    public function quickCreateExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        $actorId = (int) (auth_user_id() ?? 0);

        if ($estateSaleId <= 0 || EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            $this->json(['ok' => false, 'error' => 'Estate sale not found.'], 404);
            return;
        }

        $payload = EstateSale::expensePayloadFromInput($_POST);
        $errors = EstateSale::validateExpense($payload);
        if ($errors !== []) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
            return;
        }

        $expenseId = EstateSale::createExpense($businessId, $estateSaleId, $payload, $actorId);
        if ($expenseId <= 0) {
            $this->json(['ok' => false, 'error' => 'Could not save expense. Run latest migrations if needed.'], 422);
            return;
        }

        $expenseRows = EstateSale::expenses($businessId, $estateSaleId, 1);
        $expense = is_array($expenseRows[0] ?? null) ? $expenseRows[0] : null;
        if ($expense === null || (int) ($expense['id'] ?? 0) !== $expenseId) {
            foreach (EstateSale::expenses($businessId, $estateSaleId) as $row) {
                if ((int) ($row['id'] ?? 0) === $expenseId) {
                    $expense = $row;
                    break;
                }
            }
        }

        $expenseDate = trim((string) ($expense['expense_date'] ?? ''));
        if ($expenseDate !== '') {
            $ts = strtotime($expenseDate);
            $expenseDate = $ts === false ? $expenseDate : date('m/d/Y', $ts);
        }

        $this->json([
            'ok' => true,
            'message' => 'Expense added.',
            'expense' => [
                'id' => $expenseId,
                'category' => trim((string) ($expense['category'] ?? $payload['category'])),
                'amount' => round((float) ($expense['amount'] ?? $payload['amount']), 2),
                'expense_date' => $expenseDate,
                'note' => trim((string) ($expense['note'] ?? $payload['note'])),
            ],
            'financialSummary' => EstateSale::financialSummary($businessId, $estateSaleId, EstateSale::findForBusiness($businessId, $estateSaleId) ?? []),
        ], 201);
    }

    public function removeExpense(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales');
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        $expenseId = (int) ($params['expenseId'] ?? 0);
        $actorId = (int) (auth_user_id() ?? 0);

        if (EstateSale::removeExpense($businessId, $estateSaleId, $expenseId, $actorId)) {
            flash('success', 'Expense removed.');
        } else {
            flash('error', 'Could not remove expense.');
        }

        redirect('/estate-sales/' . (string) $estateSaleId . '?tab=expenses');
    }

    public function customerSearch(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $estateSaleId = (int) ($params['id'] ?? 0);
        if ($estateSaleId <= 0 || EstateSale::findForBusiness($businessId, $estateSaleId) === null) {
            $this->json(['ok' => false, 'error' => 'Estate sale not found.'], 404);
            return;
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $items = EstateSale::searchCustomers($businessId, $estateSaleId, $query, 8);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            $name = EstateSale::customerDisplayName($item);
            if ($id <= 0 || $name === '') {
                continue;
            }

            $results[] = [
                'id' => $id,
                'name' => $name,
                'phone' => trim((string) ($item['phone'] ?? '')),
                'city' => trim((string) ($item['city'] ?? '')),
                'state' => trim((string) ($item['state'] ?? '')),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function createSale(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $estateTitle = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
        $form = $this->defaultEstateSaleForm();

        $customerIdPrefill = (int) ($_GET['customer_id'] ?? $_GET['estate_sale_customer_id'] ?? 0);
        if ($customerIdPrefill > 0) {
            $customer = EstateSale::findCustomerForSale($businessId, $estateSaleId, $customerIdPrefill);
            if ($customer !== null) {
                $customerName = EstateSale::customerDisplayName($customer);
                $form['estate_sale_customer_id'] = (string) $customerIdPrefill;
                $form['estate_sale_customer_name'] = $customerName;
                $form['name'] = $customerName . ' — ' . $estateTitle;
            }
        }

        $this->renderSaleForm($estateSale, $form, [], 'create');
    }

    public function storeSale(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales/' . (string) ((int) ($estateSale['id'] ?? 0)) . '/sales/create');
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $estateTitle = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
        $form = $this->estateSaleFormFromPost($_POST);
        $errors = $this->validateEstateSaleForm($form, $businessId, $estateSaleId);

        if ($errors !== []) {
            $this->renderSaleForm($estateSale, $form, $errors, 'create');
            return;
        }

        $gross = round((float) $form['gross_amount'], 2);

        Sale::create($businessId, [
            'name' => $form['name'],
            'gross_amount' => $gross,
            'net_amount' => $gross,
            'sale_type' => EstateSale::ON_SITE_SALE_TYPE,
            'sale_date' => $this->saleDateToDatabase($form['sale_date']),
            'estate_sale_id' => $estateSaleId,
            'estate_sale_customer_id' => (int) $form['estate_sale_customer_id'],
            'notes' => $form['notes'],
            'client_id' => null,
            'job_id' => null,
            'purchase_id' => null,
        ], auth_user_id() ?? 0);

        flash('success', 'Sale added.');
        redirect('/estate-sales/' . (string) $estateSaleId . '?tab=sales');
    }

    public function editSale(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $saleId = (int) ($params['saleId'] ?? 0);
        $sale = $this->saleForEstateOr404($businessId, $estateSaleId, $saleId);
        if ($sale === null) {
            return;
        }

        $this->renderSaleForm($estateSale, $this->estateSaleFormFromSale($sale, $businessId, $estateSaleId), [], 'edit', $saleId);
    }

    public function updateSale(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $estateSale = $this->estateSaleOr404((int) ($params['id'] ?? 0));
        if ($estateSale === null) {
            return;
        }

        $businessId = current_business_id();
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $saleId = (int) ($params['saleId'] ?? 0);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/estate-sales/' . (string) $estateSaleId . '/sales/' . (string) $saleId . '/edit');
        }

        $sale = $this->saleForEstateOr404($businessId, $estateSaleId, $saleId);
        if ($sale === null) {
            return;
        }

        $form = $this->estateSaleFormFromPost($_POST);
        $errors = $this->validateEstateSaleForm($form, $businessId, $estateSaleId);

        if ($errors !== []) {
            $this->renderSaleForm($estateSale, $form, $errors, 'edit', $saleId);
            return;
        }

        $gross = round((float) $form['gross_amount'], 2);

        Sale::update($businessId, $saleId, [
            'name' => $form['name'],
            'gross_amount' => $gross,
            'net_amount' => $gross,
            'sale_type' => EstateSale::ON_SITE_SALE_TYPE,
            'sale_date' => $this->saleDateToDatabase($form['sale_date']),
            'estate_sale_id' => $estateSaleId,
            'estate_sale_customer_id' => (int) $form['estate_sale_customer_id'],
            'notes' => $form['notes'],
            'client_id' => null,
            'job_id' => null,
            'purchase_id' => null,
        ], auth_user_id() ?? 0);

        flash('success', 'Sale updated.');
        redirect('/estate-sales/' . (string) $estateSaleId . '?tab=sales');
    }

    private function normalizeDateFilter(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return ($date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private function defaultForm(): array
    {
        return [
            'title' => '',
            'status' => 'scheduled',
            'start_at' => '',
            'end_at' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'notes' => '',
            'client_percentage' => '',
            'client_split_type' => EstateSale::SPLIT_GROSS_TOTAL,
            'client_id' => '',
            'client_name' => '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function formFromPost(array $post): array
    {
        return [
            'title' => trim((string) ($post['title'] ?? '')),
            'status' => strtolower(trim((string) ($post['status'] ?? 'scheduled'))),
            'start_at' => trim((string) ($post['start_at'] ?? '')),
            'end_at' => trim((string) ($post['end_at'] ?? '')),
            'address_line1' => trim((string) ($post['address_line1'] ?? '')),
            'address_line2' => trim((string) ($post['address_line2'] ?? '')),
            'city' => trim((string) ($post['city'] ?? '')),
            'state' => trim((string) ($post['state'] ?? '')),
            'postal_code' => trim((string) ($post['postal_code'] ?? '')),
            'notes' => trim((string) ($post['notes'] ?? '')),
            'client_percentage' => trim((string) ($post['client_percentage'] ?? '')),
            'client_split_type' => trim((string) ($post['client_split_type'] ?? EstateSale::SPLIT_GROSS_TOTAL)),
            'client_id' => trim((string) ($post['client_id'] ?? '')),
            'client_name' => trim((string) ($post['client_name'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function formFromRow(array $row): array
    {
        return [
            'title' => trim((string) ($row['title'] ?? '')),
            'status' => strtolower(trim((string) ($row['status'] ?? 'scheduled'))),
            'start_at' => $this->datetimeLocalValue((string) ($row['start_at'] ?? '')),
            'end_at' => $this->datetimeLocalValue((string) ($row['end_at'] ?? '')),
            'address_line1' => trim((string) ($row['address_line1'] ?? '')),
            'address_line2' => trim((string) ($row['address_line2'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'state' => trim((string) ($row['state'] ?? '')),
            'postal_code' => trim((string) ($row['postal_code'] ?? '')),
            'notes' => trim((string) ($row['notes'] ?? '')),
            'client_percentage' => trim((string) ($row['client_percentage'] ?? '')),
            'client_split_type' => EstateSale::normalizeClientSplitType($row['client_split_type'] ?? null),
            'client_id' => trim((string) ($row['client_id'] ?? '')),
            'client_name' => trim((string) ($row['client_name'] ?? '')),
        ];
    }

    private function datetimeLocalValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ts = strtotime($value);

        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }

    private function estateSaleOr404(int $id): ?array
    {
        $businessId = current_business_id();
        if ($id <= 0) {
            flash('error', 'Estate sale not found.');
            redirect('/estate-sales');
        }

        $estateSale = EstateSale::findForBusiness($businessId, $id);
        if ($estateSale === null) {
            flash('error', 'Estate sale not found.');
            redirect('/estate-sales');
        }

        return $estateSale;
    }

    /**
     * @return array<string, string>
     */
    private function defaultEstateSaleForm(): array
    {
        return [
            'name' => '',
            'gross_amount' => '',
            'sale_date' => date('Y-m-d'),
            'estate_sale_customer_id' => '',
            'estate_sale_customer_name' => '',
            'notes' => '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function estateSaleFormFromPost(array $post): array
    {
        $customerIdRaw = trim((string) ($post['estate_sale_customer_id'] ?? ''));
        $customerId = ((int) $customerIdRaw) > 0 ? (string) ((int) $customerIdRaw) : '';

        return [
            'name' => trim((string) ($post['name'] ?? '')),
            'gross_amount' => trim((string) ($post['gross_amount'] ?? '')),
            'sale_date' => trim((string) ($post['sale_date'] ?? '')),
            'estate_sale_customer_id' => $customerId,
            'estate_sale_customer_name' => trim((string) ($post['estate_sale_customer_name'] ?? '')),
            'notes' => trim((string) ($post['notes'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $form
     * @return array<string, string>
     */
    private function validateEstateSaleForm(array $form, int $businessId, int $estateSaleId): array
    {
        $errors = [];

        $customerId = (int) $form['estate_sale_customer_id'];
        if ($customerId <= 0) {
            $errors['estate_sale_customer_id'] = 'Select a customer from this estate sale.';
        } elseif (EstateSale::findCustomerForSale($businessId, $estateSaleId, $customerId) === null) {
            $errors['estate_sale_customer_id'] = 'Selected customer was not found for this estate sale.';
        }

        if ($form['name'] === '') {
            $errors['name'] = 'Name is required.';
        }

        if (!$this->isValidSaleMoney($form['gross_amount'])) {
            $errors['gross_amount'] = 'Sale price must be a valid amount.';
        }

        if ($form['sale_date'] === '') {
            $errors['sale_date'] = 'Date is required.';
        } elseif (strtotime($form['sale_date']) === false) {
            $errors['sale_date'] = 'Date is invalid.';
        }

        return $errors;
    }

    private function isValidSaleMoney(string $value): bool
    {
        if ($value === '' || !is_numeric($value)) {
            return false;
        }

        return (float) $value >= 0;
    }

    private function saleDateToDatabase(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<string, string> $form
     * @param array<string, string> $errors
     */
    private function renderSaleForm(array $estateSale, array $form, array $errors, string $mode, ?int $saleId = null): void
    {
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $estateTitle = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
        $isEdit = $mode === 'edit';
        $resolvedSaleId = (int) ($saleId ?? 0);

        $this->render('estate-sales/sale_form', [
            'pageTitle' => $isEdit ? 'Edit Sale' : 'Add Sale',
            'mode' => $mode,
            'saleId' => $resolvedSaleId,
            'estateSale' => $estateSale,
            'estateSaleTitle' => $estateTitle,
            'form' => $form,
            'errors' => $errors,
            'backUrl' => url('/estate-sales/' . (string) $estateSaleId . '?tab=sales'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function saleForEstateOr404(int $businessId, int $estateSaleId, int $saleId): ?array
    {
        if ($saleId <= 0) {
            flash('error', 'Sale not found for this estate sale.');
            redirect('/estate-sales/' . (string) $estateSaleId . '?tab=sales');

            return null;
        }

        $sale = Sale::findForBusiness($businessId, $saleId);
        if ($sale === null || (int) ($sale['estate_sale_id'] ?? 0) !== $estateSaleId) {
            flash('error', 'Sale not found for this estate sale.');
            redirect('/estate-sales/' . (string) $estateSaleId . '?tab=sales');

            return null;
        }

        return $sale;
    }

    /**
     * @param array<string, mixed> $sale
     * @return array<string, string>
     */
    private function estateSaleFormFromSale(array $sale, int $businessId, int $estateSaleId): array
    {
        $customerId = (int) ($sale['estate_sale_customer_id'] ?? 0);
        $customerName = '';
        if ($customerId > 0) {
            $customer = EstateSale::findCustomerForSale($businessId, $estateSaleId, $customerId);
            if ($customer !== null) {
                $customerName = EstateSale::customerDisplayName($customer);
            }
        }

        $saleDate = trim((string) ($sale['sale_date'] ?? ''));
        if ($saleDate !== '') {
            $timestamp = strtotime($saleDate);
            $saleDate = $timestamp === false ? '' : date('Y-m-d', $timestamp);
        }

        return [
            'name' => trim((string) ($sale['name'] ?? '')),
            'gross_amount' => number_format((float) ($sale['gross_amount'] ?? 0), 2, '.', ''),
            'sale_date' => $saleDate,
            'estate_sale_customer_id' => $customerId > 0 ? (string) $customerId : '',
            'estate_sale_customer_name' => $customerName,
            'notes' => trim((string) ($sale['notes'] ?? '')),
        ];
    }

    private function customerPageTitle(array $customer): string
    {
        $queueNumber = (int) ($customer['queue_number'] ?? 0);
        $name = EstateSale::customerDisplayName($customer);
        if ($queueNumber > 0) {
            return '#' . (string) $queueNumber . ' · ' . $name;
        }

        return $name;
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
