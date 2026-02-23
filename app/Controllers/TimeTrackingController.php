<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Employee;
use App\Models\Job;
use App\Models\TimeEntry;
use App\Models\UserFilterPreset;
use Core\Controller;
use Throwable;

final class TimeTrackingController extends Controller
{
    private bool $selfScopedEmployeeResolved = false;
    private ?array $selfScopedEmployee = null;

    public function index(): void
    {
        if ($this->redirectPunchOnlyToLanding(false)) {
            return;
        }

        $this->authorize('view');
        $isSelfScoped = $this->isSelfScopedRole();
        $selfEmployeeId = $isSelfScoped ? $this->selfScopedEmployeeId() : null;
        if ($isSelfScoped && $selfEmployeeId === null) {
            flash('error', 'Your user is not linked to an active employee profile.');
            redirect('/');
        }

        $moduleKey = 'time_tracking';
        $userId = auth_user_id() ?? 0;
        $savedPresets = $userId > 0 ? UserFilterPreset::forUser($userId, $moduleKey) : [];
        $selectedPresetId = $this->toIntOrNull($_GET['preset_id'] ?? null);
        $presetFilters = [];
        if ($selectedPresetId !== null && $selectedPresetId > 0 && $userId > 0) {
            $preset = UserFilterPreset::findForUser($selectedPresetId, $userId, $moduleKey);
            if ($preset) {
                $presetFilters = is_array($preset['filters'] ?? null) ? $preset['filters'] : [];
            } else {
                $selectedPresetId = null;
            }
        }

        $filters = [
            'q' => trim((string) ($presetFilters['q'] ?? '')),
            'employee_id' => $this->toIntOrNull($presetFilters['employee_id'] ?? null),
            'job_id' => $this->toIntOrNull($presetFilters['job_id'] ?? null),
            'start_date' => trim((string) ($presetFilters['start_date'] ?? '')),
            'end_date' => trim((string) ($presetFilters['end_date'] ?? '')),
            'record_status' => (string) ($presetFilters['record_status'] ?? 'active'),
        ];

        if (array_key_exists('q', $_GET)) {
            $filters['q'] = trim((string) ($_GET['q'] ?? ''));
        }
        if (array_key_exists('employee_id', $_GET)) {
            $filters['employee_id'] = $this->toIntOrNull($_GET['employee_id'] ?? null);
        }
        if (array_key_exists('job_id', $_GET)) {
            $filters['job_id'] = $this->toIntOrNull($_GET['job_id'] ?? null);
        }
        if (array_key_exists('start_date', $_GET)) {
            $filters['start_date'] = trim((string) ($_GET['start_date'] ?? ''));
        }
        if (array_key_exists('end_date', $_GET)) {
            $filters['end_date'] = trim((string) ($_GET['end_date'] ?? ''));
        }
        if (array_key_exists('record_status', $_GET)) {
            $filters['record_status'] = (string) ($_GET['record_status'] ?? 'active');
        }

        if ($filters['start_date'] === '' && $filters['end_date'] === '') {
            $filters['start_date'] = date('Y-m-01');
            $filters['end_date'] = date('Y-m-t');
        }

        if (!in_array($filters['record_status'], ['active', 'deleted', 'all'], true)) {
            $filters['record_status'] = 'active';
        }
        if ($isSelfScoped && $selfEmployeeId !== null) {
            $filters['employee_id'] = $selfEmployeeId;
        }

        $entries = TimeEntry::filter($filters);
        if ((string) ($_GET['export'] ?? '') === 'csv') {
            $this->downloadIndexCsv($entries);
            return;
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/time-tracking-table.js') . '"></script>',
        ]);

        $summary = TimeEntry::summary($filters);
        $employees = TimeEntry::employees();
        if ($isSelfScoped && $selfEmployeeId !== null) {
            $employees = $this->filterEmployeesById($employees, $selfEmployeeId);
        }

        $this->render('time_tracking/index', [
            'pageTitle' => 'Time Tracking',
            'filters' => $filters,
            'entries' => $entries,
            'summary' => $summary,
            'byEmployee' => TimeEntry::summaryByEmployee($filters),
            'employees' => $employees,
            'jobs' => TimeEntry::jobs(),
            'savedPresets' => $savedPresets,
            'selectedPresetId' => $selectedPresetId,
            'filterPresetModule' => $moduleKey,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function open(): void
    {
        $this->authorize('view');

        if (is_punch_only_role()) {
            $this->renderPunchOnlyClock();
            return;
        }

        $requestPath = strtolower((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
        $usePunchClockPath = str_contains($requestPath, '/punch-clock');
        $openBasePath = $usePunchClockPath ? '/punch-clock' : '/time-tracking/open';
        $isSelfScoped = $this->isSelfScopedRole();
        $selfEmployeeId = $isSelfScoped ? $this->selfScopedEmployeeId() : null;
        $missingSelfEmployee = $isSelfScoped && $selfEmployeeId === null;

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        if ($isSelfScoped && $selfEmployeeId !== null) {
            $filters['employee_id'] = $selfEmployeeId;
        }

        $entries = $missingSelfEmployee ? [] : TimeEntry::openEntries($filters);
        $employees = $missingSelfEmployee ? [] : TimeEntry::employees();
        if (!$missingSelfEmployee && $isSelfScoped && $selfEmployeeId !== null) {
            $employees = $this->filterEmployeesById($employees, $selfEmployeeId);
        }
        $openEmployeeIds = [];
        foreach ($entries as $entry) {
            $employeeId = (int) ($entry['employee_id'] ?? 0);
            if ($employeeId > 0) {
                $openEmployeeIds[$employeeId] = true;
            }
        }

        $query = trim((string) ($filters['q'] ?? ''));
        $searchTerm = function_exists('mb_strtolower')
            ? mb_strtolower($query)
            : strtolower($query);
        $punchedOutEmployees = [];
        foreach ($employees as $employee) {
            $employeeId = (int) ($employee['id'] ?? 0);
            if ($employeeId <= 0 || isset($openEmployeeIds[$employeeId])) {
                continue;
            }
            if ($searchTerm !== '') {
                $name = trim((string) ($employee['name'] ?? ''));
                $haystack = function_exists('mb_strtolower')
                    ? mb_strtolower($name . ' ' . (string) $employeeId)
                    : strtolower($name . ' ' . (string) $employeeId);
                if (!str_contains($haystack, $searchTerm)) {
                    continue;
                }
            }
            $punchedOutEmployees[] = $employee;
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/time-open-table.js') . '"></script>',
            '<script src="' . asset('js/time-open-punch-modal.js') . '"></script>',
        ]);

        $this->render('time_tracking/open', [
            'pageTitle' => 'Currently Punched In',
            'filters' => $filters,
            'entries' => $entries,
            'summary' => $missingSelfEmployee
                ? ['active_count' => 0, 'total_open_minutes' => 0, 'total_open_paid' => 0]
                : TimeEntry::openSummary($filters),
            'punchedOutEmployees' => $punchedOutEmployees,
            'openBasePath' => $openBasePath,
            'isPunchOnlyRole' => is_punch_only_role(),
            'pageScripts' => $pageScripts,
        ]);
    }

    private function renderPunchOnlyClock(): void
    {
        $employeeId = $this->selfScopedEmployeeId();
        if ($employeeId === null) {
            flash('error', 'Your user is not linked to an active employee profile.');
            redirect('/login');
        }

        $employee = Employee::findById($employeeId);
        $openEntry = TimeEntry::findOpenForEmployee($employeeId);
        $historyStartDate = date('Y-m-d', strtotime('-30 days'));
        $historyEndDate = date('Y-m-d');
        $historyFilters = [
            'employee_id' => $employeeId,
            'start_date' => $historyStartDate,
            'end_date' => $historyEndDate,
            'record_status' => 'active',
            'q' => '',
            'job_id' => null,
        ];
        $historyEntries = TimeEntry::filter($historyFilters);
        $historySummary = TimeEntry::summary($historyFilters);

        $this->render('time_tracking/punch_only', [
            'pageTitle' => 'Punch Clock',
            'employee' => $employee,
            'openEntry' => $openEntry,
            'historyEntries' => $historyEntries,
            'historySummary' => $historySummary,
            'historyStartDate' => $historyStartDate,
            'historyEndDate' => $historyEndDate,
            'openBasePath' => '/punch-clock',
        ]);
    }

    public function openPunchIn(): void
    {
        $this->authorize('edit');

        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? '/time-tracking/open');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($returnTo);
        }

        $isSelfScoped = $this->isSelfScopedRole();
        $selfEmployeeId = $isSelfScoped ? $this->selfScopedEmployeeId() : null;
        if ($isSelfScoped && $selfEmployeeId === null) {
            flash('error', 'Your user is not linked to an active employee profile.');
            redirect($returnTo);
        }

        if ($isSelfScoped) {
            $employeeId = $selfEmployeeId;
        } else {
            $employeeId = $this->toIntOrNull($_POST['employee_id'] ?? null);
            if ($employeeId === null || $employeeId <= 0) {
                flash('error', 'Select a valid employee.');
                redirect($returnTo);
            }
        }

        $employee = Employee::findById($employeeId);
        if (
            !$employee
            || !empty($employee['deleted_at'])
            || (int) ($employee['active'] ?? 1) !== 1
        ) {
            flash('error', 'This employee is inactive and cannot be punched in.');
            redirect($returnTo);
        }

        $openEntry = TimeEntry::findOpenForEmployee($employeeId);
        if ($openEntry) {
            $openJobId = (int) ($openEntry['job_id'] ?? 0);
            $openJobName = (string) ($openEntry['job_name'] ?? '');
            flash('error', 'This employee is already punched in on ' . $this->resolveJobLabel($openJobId, $openJobName) . '.');
            redirect($returnTo);
        }

        $jobId = null;
        $jobInput = $this->toIntOrNull($_POST['job_id'] ?? null);
        if ($jobInput !== null && $jobInput > 0) {
            if (!$this->optionExists($jobInput, TimeEntry::jobs())) {
                flash('error', 'Select a valid job before punching in.');
                redirect($returnTo);
            }
            $jobId = $jobInput;
        }

        $payRate = TimeEntry::employeeRate($employeeId) ?? 0.0;
        $geo = request_geo_payload($_POST);
        try {
            $entryId = TimeEntry::create([
                'employee_id' => $employeeId,
                'job_id' => $jobId,
                'work_date' => date('Y-m-d'),
                'start_time' => date('H:i:s'),
                'end_time' => null,
                'minutes_worked' => null,
                'pay_rate' => $payRate,
                'total_paid' => null,
                'punch_in_lat' => $geo['lat'],
                'punch_in_lng' => $geo['lng'],
                'punch_in_accuracy_m' => $geo['accuracy'],
                'punch_in_source' => $geo['source'],
                'punch_in_captured_at' => $geo['captured_at'],
                'note' => null,
            ], auth_user_id());
        } catch (Throwable) {
            flash('error', 'Unable to punch in with the selected values. Please verify employee/job selection.');
            redirect($returnTo);
        }

        $employeeName = trim((string) ($employee['display_name'] ?? $employee['name'] ?? ''));
        if ($employeeName === '') {
            $employeeName = trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')));
        }
        if ($employeeName === '') {
            $employeeName = 'Employee #' . $employeeId;
        }

        if (($jobId ?? 0) > 0) {
            Job::createAction((int) $jobId, [
                'action_type' => 'time_punched_in',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => null,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $entryId,
                'note' => $employeeName . ' punched in from Open Clock.',
            ], auth_user_id());
        }

        $jobLabel = $this->resolveJobLabel((int) ($jobId ?? 0), '');
        log_user_action('time_punched_in', 'employee_time_entries', $entryId, $employeeName . ' punched in on ' . $jobLabel . '.');

        flash('success', $employeeName . ' punched in on ' . $jobLabel . '.');
        redirect($returnTo);
    }

    public function create(): void
    {
        if ($this->redirectPunchOnlyToLanding()) {
            return;
        }

        $this->authorize('create');
        $isSelfScoped = $this->isSelfScopedRole();
        $selfEmployeeId = $isSelfScoped ? $this->selfScopedEmployeeId() : null;
        if ($isSelfScoped && $selfEmployeeId === null) {
            flash('error', 'Your user is not linked to an active employee profile.');
            redirect('/time-tracking');
        }

        $jobId = $this->toIntOrNull($_GET['job_id'] ?? null);
        $employees = TimeEntry::employees();
        if ($isSelfScoped && $selfEmployeeId !== null) {
            $employees = $this->filterEmployeesById($employees, $selfEmployeeId);
        }
        $jobs = TimeEntry::jobs();

        $selectedJobId = null;
        if ($jobId !== null && $jobId > 0) {
            if ($this->optionExists($jobId, $jobs)) {
                $selectedJobId = $jobId;
            } else {
                $job = Job::findById($jobId);
                if ($job && empty($job['deleted_at']) && (int) ($job['active'] ?? 1) === 1) {
                    $jobs[] = [
                        'id' => $jobId,
                        'name' => (string) ($job['name'] ?? ('Job #' . $jobId)),
                        'job_status' => (string) ($job['job_status'] ?? ''),
                    ];
                    $selectedJobId = $jobId;
                }
            }
        }

        $returnTo = $this->sanitizeReturnTo($_GET['return_to'] ?? null, $selectedJobId);

        $this->render('time_tracking/create', [
            'pageTitle' => 'Add Time Entry',
            'entry' => [
                'employee_id' => $selfEmployeeId,
                'job_id' => $selectedJobId,
                'work_date' => date('Y-m-d'),
            ],
            'employees' => $employees,
            'jobs' => $jobs,
            'returnTo' => $returnTo,
            'formAction' => '/time-tracking/new',
            'cancelUrl' => $returnTo,
            'pageScripts' => implode("\n", [
                '<script src="' . asset('js/time-entry-lookup.js') . '"></script>',
                '<script src="' . asset('js/time-entry-form.js') . '"></script>',
            ]),
        ]);

        clear_old();
    }

    public function store(): void
    {
        if ($this->redirectPunchOnlyToLanding()) {
            return;
        }

        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/time-tracking/new');
        }

        $isSelfScoped = $this->isSelfScopedRole();
        $selfEmployeeId = $isSelfScoped ? $this->selfScopedEmployeeId() : null;
        if ($isSelfScoped && $selfEmployeeId === null) {
            flash('error', 'Your user is not linked to an active employee profile.');
            redirect('/time-tracking');
        }

        $entryMode = (string) ($_POST['entry_mode'] ?? 'save');
        if (!in_array($entryMode, ['save', 'punch_in_now'], true)) {
            $entryMode = 'save';
        }

        $employees = TimeEntry::employees();
        if ($isSelfScoped && $selfEmployeeId !== null) {
            $employees = $this->filterEmployeesById($employees, $selfEmployeeId);
            $_POST['employee_id'] = (string) $selfEmployeeId;
        }
        $jobs = TimeEntry::jobs();
        $data = $this->collectFormData($_POST, $employees, $jobs, $entryMode);
        $errors = $data['errors'];
        $returnTo = (string) $data['return_to'];
        $jobId = $data['job_id'] !== null ? (int) $data['job_id'] : null;

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            $query = ($jobId !== null && $jobId > 0) ? '?job_id=' . $jobId : '';
            redirect('/time-tracking/new' . $query);
        }

        if ($entryMode === 'punch_in_now') {
            $employeeId = (int) $data['employee_id'];
            $openEntry = TimeEntry::findOpenForEmployee($employeeId);
            if ($openEntry) {
                $openJobId = (int) ($openEntry['job_id'] ?? 0);
                $openJobLabel = $this->resolveJobLabel($openJobId, (string) ($openEntry['job_name'] ?? ''));
                flash('error', 'This employee is already punched in on ' . $openJobLabel . '.');
                flash_old($_POST);
                $query = ($jobId !== null && $jobId > 0) ? '?job_id=' . $jobId : '';
                redirect('/time-tracking/new' . $query);
            }

            $nowDate = date('Y-m-d');
            $nowTime = date('H:i:s');
            $geo = request_geo_payload($_POST);
            $payRate = $data['pay_rate'] !== null
                ? (float) $data['pay_rate']
                : (TimeEntry::employeeRate($employeeId) ?? 0.0);

            try {
                $entryId = TimeEntry::create([
                    'employee_id' => $employeeId,
                    'job_id' => $jobId,
                    'work_date' => $nowDate,
                    'start_time' => $nowTime,
                    'end_time' => null,
                    'minutes_worked' => null,
                    'pay_rate' => $payRate,
                    'total_paid' => null,
                    'punch_in_lat' => $geo['lat'],
                    'punch_in_lng' => $geo['lng'],
                    'punch_in_accuracy_m' => $geo['accuracy'],
                    'punch_in_source' => $geo['source'],
                    'punch_in_captured_at' => $geo['captured_at'],
                    'note' => $data['note'],
                ], auth_user_id());
            } catch (Throwable) {
                flash('error', 'Unable to punch in with the selected values. Please verify employee/job selection.');
                flash_old($_POST);
                $query = ($jobId !== null && $jobId > 0) ? '?job_id=' . $jobId : '';
                redirect('/time-tracking/new' . $query);
            }

            if (($jobId ?? 0) > 0) {
                Job::createAction((int) $jobId, [
                    'action_type' => 'time_punched_in',
                    'action_at' => $nowDate . ' ' . $nowTime,
                    'amount' => null,
                    'ref_table' => 'employee_time_entries',
                    'ref_id' => $entryId,
                    'note' => 'Employee punched in from Time Tracking.',
                ], auth_user_id());
            }

            flash('success', 'Employee punched in on ' . $this->resolveJobLabel((int) ($jobId ?? 0), '') . '.');
            redirect($returnTo);
        }

        try {
            $entryId = TimeEntry::create([
                'employee_id' => (int) $data['employee_id'],
                'job_id' => $data['job_id'],
                'work_date' => $data['work_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'minutes_worked' => $data['minutes_worked'],
                'pay_rate' => $data['pay_rate'],
                'total_paid' => $data['total_paid'],
                'note' => $data['note'],
            ], auth_user_id());
        } catch (Throwable) {
            flash('error', 'Unable to save time entry with the selected values. Please verify employee/job selection.');
            flash_old($_POST);
            $query = ($jobId !== null && $jobId > 0) ? '?job_id=' . $jobId : '';
            redirect('/time-tracking/new' . $query);
        }

        $jobActionAt = date('Y-m-d H:i:s');
        if ($data['work_date'] !== null) {
            $jobActionAt = $data['work_date'] . ' ' . ($data['start_time'] ?? '12:00:00');
        }

        if (($jobId ?? 0) > 0) {
            Job::createAction((int) $jobId, [
                'action_type' => 'time_entry_added',
                'action_at' => $jobActionAt,
                'amount' => $data['total_paid'],
                'ref_table' => 'employee_time_entries',
                'ref_id' => $entryId,
                'note' => 'Time entry added (' . $this->formatMinutes((int) $data['minutes_worked']) . ').',
            ], auth_user_id());
        }

        flash('success', 'Time entry added.');
        redirect($returnTo);
    }

    public function show(array $params): void
    {
        if ($this->redirectPunchOnlyToLanding()) {
            return;
        }

        $this->authorize('view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }
        if (!$this->canAccessEmployeeId((int) ($entry['employee_id'] ?? 0))) {
            flash('error', 'You can only view your own time entries.');
            redirect('/time-tracking');
        }

        $isActive = empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1;

        $this->render('time_tracking/show', [
            'pageTitle' => 'Time Entry',
            'entry' => $entry,
            'isActive' => $isActive,
            'returnTo' => $this->sanitizeReturnTo($_GET['return_to'] ?? null, isset($entry['job_id']) ? (int) $entry['job_id'] : null),
        ]);
    }

    public function edit(array $params): void
    {
        if ($this->redirectPunchOnlyToLanding()) {
            return;
        }

        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }
        if (!$this->canAccessEmployeeId((int) ($entry['employee_id'] ?? 0))) {
            flash('error', 'You can only edit your own time entries.');
            redirect('/time-tracking');
        }

        $isActive = empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1;
        if (!$isActive) {
            flash('error', 'This time entry is inactive and cannot be edited.');
            redirect('/time-tracking/' . $id);
        }

        $returnTo = $this->sanitizeReturnTo($_GET['return_to'] ?? null, isset($entry['job_id']) ? (int) $entry['job_id'] : null);
        $employees = TimeEntry::employees();
        if ($this->isSelfScopedRole()) {
            $selfEmployeeId = $this->selfScopedEmployeeId();
            if ($selfEmployeeId !== null) {
                $employees = $this->filterEmployeesById($employees, $selfEmployeeId);
            }
        }

        $this->render('time_tracking/edit', [
            'pageTitle' => 'Edit Time Entry',
            'entry' => $entry,
            'employees' => $employees,
            'jobs' => TimeEntry::jobs(),
            'returnTo' => $returnTo,
            'formAction' => '/time-tracking/' . $id . '/edit',
            'cancelUrl' => '/time-tracking/' . $id . '?return_to=' . urlencode($returnTo),
            'pageScripts' => implode("\n", [
                '<script src="' . asset('js/time-entry-lookup.js') . '"></script>',
                '<script src="' . asset('js/time-entry-form.js') . '"></script>',
            ]),
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        if ($this->redirectPunchOnlyToLanding()) {
            return;
        }

        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $existing = TimeEntry::findById($id);
        if (!$existing) {
            $this->renderNotFound();
            return;
        }
        if (!$this->canAccessEmployeeId((int) ($existing['employee_id'] ?? 0))) {
            flash('error', 'You can only edit your own time entries.');
            redirect('/time-tracking');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/time-tracking/' . $id . '/edit');
        }

        $isActive = empty($existing['deleted_at']) && (int) ($existing['active'] ?? 1) === 1;
        if (!$isActive) {
            flash('error', 'This time entry is inactive and cannot be edited.');
            redirect('/time-tracking/' . $id);
        }

        $employees = TimeEntry::employees();
        if ($this->isSelfScopedRole()) {
            $selfEmployeeId = $this->selfScopedEmployeeId();
            if ($selfEmployeeId !== null) {
                $employees = $this->filterEmployeesById($employees, $selfEmployeeId);
                $_POST['employee_id'] = (string) $selfEmployeeId;
            }
        }
        $jobs = TimeEntry::jobs();
        $data = $this->collectFormData($_POST, $employees, $jobs, 'save');
        $errors = $data['errors'];
        $returnTo = (string) $data['return_to'];
        $jobId = $data['job_id'] !== null ? (int) $data['job_id'] : null;

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            $query = ($jobId !== null && $jobId > 0) ? '?job_id=' . $jobId : '';
            redirect('/time-tracking/' . $id . '/edit' . $query);
        }

        try {
            TimeEntry::update($id, [
                'employee_id' => (int) $data['employee_id'],
                'job_id' => $data['job_id'],
                'work_date' => $data['work_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'minutes_worked' => $data['minutes_worked'],
                'pay_rate' => $data['pay_rate'],
                'total_paid' => $data['total_paid'],
                'note' => $data['note'],
            ], auth_user_id());
        } catch (Throwable) {
            flash('error', 'Unable to update time entry with the selected values. Please verify employee/job selection.');
            flash_old($_POST);
            $query = ($jobId !== null && $jobId > 0) ? '?job_id=' . $jobId : '';
            redirect('/time-tracking/' . $id . '/edit' . $query);
        }

        if (($jobId ?? 0) > 0) {
            Job::createAction((int) $jobId, [
                'action_type' => 'time_entry_updated',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => $data['total_paid'],
                'ref_table' => 'employee_time_entries',
                'ref_id' => $id,
                'note' => 'Time entry updated (' . $this->formatMinutes((int) $data['minutes_worked']) . ').',
            ], auth_user_id());
        }

        flash('success', 'Time entry updated.');
        redirect('/time-tracking/' . $id . '?return_to=' . urlencode($returnTo));
    }

    public function punchOut(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking/open');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }
        if (!$this->canAccessEmployeeId((int) ($entry['employee_id'] ?? 0))) {
            flash('error', 'You can only punch out your own open entries.');
            redirect($this->sanitizeReturnTo($_POST['return_to'] ?? null));
        }

        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? null, isset($entry['job_id']) ? (int) $entry['job_id'] : null);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($returnTo);
        }

        if (
            !empty($entry['deleted_at'])
            || (int) ($entry['active'] ?? 1) !== 1
            || empty($entry['start_time'])
            || !empty($entry['end_time'])
        ) {
            flash('error', 'This time entry is not available for punch out.');
            redirect($returnTo);
        }

        $minutesWorked = $this->calculateOpenMinutes(
            (string) ($entry['work_date'] ?? date('Y-m-d')),
            (string) ($entry['start_time'] ?? date('H:i:s'))
        );
        $geo = request_geo_payload($_POST);
        $payRate = isset($entry['pay_rate']) && $entry['pay_rate'] !== null
            ? (float) $entry['pay_rate']
            : (TimeEntry::employeeRate((int) ($entry['employee_id'] ?? 0)) ?? 0.0);
        $totalPaid = round(($payRate * $minutesWorked) / 60, 2);

        TimeEntry::punchOut($id, [
            'end_time' => date('H:i:s'),
            'minutes_worked' => $minutesWorked,
            'pay_rate' => $payRate,
            'total_paid' => $totalPaid,
            'punch_out_lat' => $geo['lat'],
            'punch_out_lng' => $geo['lng'],
            'punch_out_accuracy_m' => $geo['accuracy'],
            'punch_out_source' => $geo['source'],
            'punch_out_captured_at' => $geo['captured_at'],
        ], auth_user_id());

        $jobId = isset($entry['job_id']) ? (int) $entry['job_id'] : 0;
        if ($jobId > 0) {
            Job::createAction($jobId, [
                'action_type' => 'time_punched_out',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => $totalPaid,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $id,
                'note' => 'Employee punched out (' . $this->formatMinutes($minutesWorked) . ').',
            ], auth_user_id());
        }

        $employeeName = trim((string) ($entry['employee_name'] ?? ('Employee #' . (string) ($entry['employee_id'] ?? ''))));
        flash('success', $employeeName . ' punched out.');
        redirect($returnTo);
    }

    public function delete(array $params): void
    {
        if ($this->redirectPunchOnlyToLanding()) {
            return;
        }

        $this->authorize('delete');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/time-tracking');
        }

        $entry = TimeEntry::findById($id);
        if (!$entry) {
            $this->renderNotFound();
            return;
        }
        if (!$this->canAccessEmployeeId((int) ($entry['employee_id'] ?? 0))) {
            flash('error', 'You can only delete your own time entries.');
            redirect('/time-tracking');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/time-tracking/' . $id);
        }

        $isActive = empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1;
        if (!$isActive) {
            flash('error', 'This time entry is already inactive.');
            redirect('/time-tracking/' . $id);
        }

        TimeEntry::softDelete($id, auth_user_id());

        $jobId = isset($entry['job_id']) ? (int) $entry['job_id'] : 0;
        if ($jobId > 0) {
            Job::createAction($jobId, [
                'action_type' => 'time_entry_deleted',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => isset($entry['paid_calc']) ? (float) $entry['paid_calc'] : null,
                'ref_table' => 'employee_time_entries',
                'ref_id' => $id,
                'note' => 'Time entry soft deleted.',
            ], auth_user_id());
        }

        flash('success', 'Time entry deleted.');
        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? null, $jobId > 0 ? $jobId : null);
        redirect($returnTo);
    }

    public function employeeLookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));
        $results = TimeEntry::lookupEmployees($term);
        if ($this->isSelfScopedRole()) {
            $selfEmployeeId = $this->selfScopedEmployeeId();
            if ($selfEmployeeId === null) {
                $results = [];
            } else {
                $results = array_values(array_filter(
                    $results,
                    static fn (array $row): bool => (int) ($row['id'] ?? 0) === $selfEmployeeId
                ));
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($results);
    }

    public function jobLookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(TimeEntry::lookupJobs($term));
    }

    private function sanitizeReturnTo(mixed $value, ?int $jobId = null): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw !== '') {
            if (
                preg_match('#^/jobs/[0-9]+$#', $raw)
                || preg_match('#^/time-tracking(?:/.*)?$#', $raw)
                || preg_match('#^/punch-clock(?:/.*)?$#', $raw)
            ) {
                return $raw;
            }
        }

        if ($jobId !== null && $jobId > 0) {
            return '/jobs/' . $jobId;
        }

        if (is_punch_only_role()) {
            return '/punch-clock';
        }

        return '/time-tracking';
    }

    private function collectFormData(array $source, array $employees, array $jobs, string $entryMode = 'save'): array
    {
        $isPunchInNow = $entryMode === 'punch_in_now';
        $nonJobTime = $this->isTruthy($source['non_job_time'] ?? null);
        $employeeId = $this->toIntOrNull($source['employee_id'] ?? null);
        $jobId = $nonJobTime ? null : $this->toIntOrNull($source['job_id'] ?? null);
        $workDate = $this->toDateOrNull($source['work_date'] ?? null);
        $startTime = $this->toTimeOrNull($source['start_time'] ?? null);
        $endTime = $this->toTimeOrNull($source['end_time'] ?? null);
        $minutesWorked = $this->toIntOrNull($source['minutes_worked'] ?? null);
        $payRate = $this->toDecimalOrNull($source['pay_rate'] ?? null);
        $totalPaid = $this->toDecimalOrNull($source['total_paid'] ?? null);
        $note = trim((string) ($source['note'] ?? ''));
        $returnTo = $this->sanitizeReturnTo($source['return_to'] ?? null, $jobId);

        $errors = [];
        if ($employeeId === null || !$this->optionExists($employeeId, $employees)) {
            $errors[] = 'Select a valid employee.';
        }
        $validJob = false;
        if (!$nonJobTime && $jobId !== null) {
            if ($this->optionExists($jobId, $jobs)) {
                $validJob = true;
            } else {
                $job = Job::findById($jobId);
                $validJob = $job && empty($job['deleted_at']) && (int) ($job['active'] ?? 1) === 1;
            }
        }
        if (!$nonJobTime && (!$jobId || !$validJob)) {
            $errors[] = 'Select a valid job.';
        }
        if (!$isPunchInNow && $workDate === null) {
            $errors[] = 'Work date is required.';
        }
        if (!$isPunchInNow && ($startTime === null) !== ($endTime === null)) {
            $errors[] = 'Provide both start and end time, or leave both blank.';
        }
        if (!$isPunchInNow && $minutesWorked !== null && $minutesWorked <= 0) {
            $errors[] = 'Minutes worked must be greater than zero.';
        }
        if (!$isPunchInNow && $minutesWorked === null && $startTime !== null && $endTime !== null) {
            $minutesWorked = $this->minutesBetween($startTime, $endTime);
            if ($minutesWorked <= 0) {
                $errors[] = 'End time must be after start time.';
            }
        }
        if (!$isPunchInNow && $minutesWorked === null && $startTime === null && $endTime === null) {
            $errors[] = 'Provide minutes worked or a start/end time.';
        }
        if ($payRate !== null && $payRate < 0) {
            $errors[] = 'Pay rate must be zero or greater.';
        }
        if ($totalPaid !== null && $totalPaid < 0) {
            $errors[] = 'Total paid must be zero or greater.';
        }

        if ($payRate === null && $employeeId !== null) {
            $payRate = TimeEntry::employeeRate($employeeId) ?? 0.0;
        }
        if (!$isPunchInNow && $totalPaid === null && $minutesWorked !== null) {
            $totalPaid = round(((float) $payRate * (float) $minutesWorked) / 60, 2);
        }

        return [
            'errors' => $errors,
            'employee_id' => $employeeId,
            'job_id' => $jobId,
            'non_job_time' => $nonJobTime,
            'work_date' => $workDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'minutes_worked' => $minutesWorked,
            'pay_rate' => $payRate,
            'total_paid' => $totalPaid,
            'note' => $note,
            'return_to' => $returnTo,
        ];
    }

    private function optionExists(int $id, array $options): bool
    {
        foreach ($options as $option) {
            if ((int) ($option['id'] ?? 0) === $id) {
                return true;
            }
        }

        return false;
    }

    private function minutesBetween(string $startTime, string $endTime): int
    {
        $start = strtotime('1970-01-01 ' . $startTime);
        $end = strtotime('1970-01-01 ' . $endTime);
        if ($start === false || $end === false) {
            return 0;
        }

        if ($end < $start) {
            $end += 86400;
        }

        return (int) floor(($end - $start) / 60);
    }

    private function calculateOpenMinutes(string $workDate, string $startTime): int
    {
        $start = strtotime($workDate . ' ' . $startTime);
        if ($start === false) {
            return 0;
        }

        $minutes = (int) floor((time() - $start) / 60);
        return $minutes > 0 ? $minutes : 0;
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h 00m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours . 'h ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function toDateOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function toTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('H:i:s', $time);
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function toDecimalOrNull(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $raw = strtolower(trim((string) ($value ?? '')));
        return in_array($raw, ['1', 'true', 'on', 'yes'], true);
    }

    private function resolveJobLabel(int $jobId, string $jobName = ''): string
    {
        if ($jobId <= 0) {
            return 'Non-Job Time';
        }

        $name = trim($jobName);
        if ($name !== '') {
            return $name;
        }

        return 'Job #' . $jobId;
    }

    private function authorize(string $action): void
    {
        require_permission('time_tracking', $action);
    }

    private function isSelfScopedRole(): bool
    {
        $role = auth_user_role();
        return $role === 0 || $role === 1;
    }

    private function selfScopedEmployeeId(): ?int
    {
        if (!$this->isSelfScopedRole()) {
            return null;
        }

        if (!$this->selfScopedEmployeeResolved) {
            $this->selfScopedEmployeeResolved = true;
            $user = auth_user();
            $this->selfScopedEmployee = is_array($user) ? Employee::findForUser($user) : null;
        }

        $id = isset($this->selfScopedEmployee['id']) ? (int) $this->selfScopedEmployee['id'] : 0;
        return $id > 0 ? $id : null;
    }

    private function filterEmployeesById(array $employees, int $employeeId): array
    {
        foreach ($employees as $employee) {
            if ((int) ($employee['id'] ?? 0) === $employeeId) {
                return [$employee];
            }
        }

        if ((int) ($this->selfScopedEmployee['id'] ?? 0) === $employeeId) {
            return [[
                'id' => $employeeId,
                'name' => (string) ($this->selfScopedEmployee['name'] ?? ('Employee #' . $employeeId)),
                'pay_rate' => $this->selfScopedEmployee['pay_rate'] ?? null,
            ]];
        }

        return [];
    }

    private function canAccessEmployeeId(int $employeeId): bool
    {
        if (!$this->isSelfScopedRole()) {
            return true;
        }

        $selfEmployeeId = $this->selfScopedEmployeeId();
        return $selfEmployeeId !== null && $employeeId > 0 && $employeeId === $selfEmployeeId;
    }

    private function redirectPunchOnlyToLanding(bool $withMessage = true): bool
    {
        if (!is_punch_only_role()) {
            return false;
        }

        if ($withMessage) {
            flash('error', 'Punch-only users can only access Punch Clock.');
        }

        redirect('/punch-clock');
        return true;
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

    private function downloadIndexCsv(array $entries): void
    {
        $rows = [];
        foreach ($entries as $entry) {
            $minutes = (int) ($entry['minutes_worked'] ?? 0);
            $rows[] = [
                format_date($entry['work_date'] ?? null),
                (string) ($entry['job_name'] ?? ''),
                (string) ($entry['employee_name'] ?? ''),
                (string) ($entry['start_time'] ?? ''),
                (string) ($entry['end_time'] ?? ''),
                number_format($minutes / 60, 2),
                number_format((float) ($entry['pay_rate'] ?? 0), 2),
                number_format((float) ($entry['paid_calc'] ?? 0), 2),
                (string) ($entry['note'] ?? ''),
                format_datetime($entry['updated_at'] ?? null),
            ];
        }

        stream_csv_download(
            'time-tracking-' . date('Ymd-His') . '.csv',
            ['Date', 'Job', 'Employee', 'Start', 'End', 'Hours', 'Rate', 'Owed', 'Note', 'Last Activity'],
            $rows
        );
    }
}
