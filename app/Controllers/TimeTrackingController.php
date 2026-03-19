<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Employee;
use App\Models\Job;
use App\Models\TimeEntry;
use Core\Controller;

final class TimeTrackingController extends Controller
{
    public function index(): void
    {
        require_business_role(['punch_only', 'general_user', 'admin']);

        if (workspace_role() === 'punch_only') {
            redirect('/time-tracking/punch-board');
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $state = strtolower(trim((string) ($_GET['state'] ?? '')));
        $allowedStates = ['open', 'closed'];
        if ($state !== '' && !in_array($state, $allowedStates, true)) {
            $state = '';
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        if (!$canManageEmployees && $selfEmployee === null) {
            flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
            redirect('/');
        }
        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = TimeEntry::indexCount($businessId, $search, $state, $scopeEmployeeId);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $entries = TimeEntry::indexList($businessId, $search, $state, $perPage, $offset, $scopeEmployeeId);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($entries));
        $summary = TimeEntry::summary($businessId, $scopeEmployeeId);

        $this->render('time_tracking/index', [
            'pageTitle' => 'Time Tracking',
            'search' => $search,
            'state' => $state,
            'entries' => $entries,
            'summary' => $summary,
            'pagination' => $pagination,
            'canManageEmployees' => $canManageEmployees,
            'selfEmployee' => $selfEmployee,
        ]);
    }

    public function punchBoard(): void
    {
        require_business_role(['punch_only', 'general_user', 'admin']);

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        if (!$canManageEmployees && $selfEmployee === null) {
            flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
            redirect('/');
        }
        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;
        $punchEmployees = TimeEntry::punchBoardEmployees($businessId, $scopeEmployeeId);
        $recentEntries = [];
        if ($scopeEmployeeId !== null && $scopeEmployeeId > 0) {
            $recentEntries = TimeEntry::indexList($businessId, '', '', 25, 0, $scopeEmployeeId);
        }

        $this->render('time_tracking/punch_board', [
            'pageTitle' => 'Punch Board',
            'punchEmployees' => $punchEmployees,
            'canManageEmployees' => $canManageEmployees,
            'selfEmployee' => $selfEmployee,
            'recentEntries' => $recentEntries,
            'isPunchOnly' => workspace_role() === 'punch_only',
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $returnTo = $this->resolveReturnPath((string) ($_GET['return_to'] ?? ''));
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        if (!$canManageEmployees && $selfEmployee === null) {
            flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
            redirect('/time-tracking');
        }

        $form = $this->defaultForm();
        if (!$canManageEmployees && $selfEmployee !== null) {
            $form['employee_id'] = (string) ((int) ($selfEmployee['id'] ?? 0));
            $form['employee_name'] = (string) (($selfEmployee['display_name'] ?? '') !== '' ? $selfEmployee['display_name'] : ($selfEmployee['employee_name'] ?? ''));
        }

        if ($canManageEmployees) {
            $requestedEmployeeId = (int) ($_GET['employee_id'] ?? 0);
            if ($requestedEmployeeId > 0) {
                $employee = Employee::findForBusiness($businessId, $requestedEmployeeId);
                if ($employee !== null) {
                    $form['employee_id'] = (string) $requestedEmployeeId;
                    $form['employee_name'] = (string) ($employee['display_name'] ?? $employee['employee_name'] ?? '');
                }
            }
        }

        $requestedJobId = (int) ($_GET['job_id'] ?? 0);
        if ($requestedJobId > 0 && TimeEntry::jobExistsForBusiness($businessId, $requestedJobId)) {
            $form['job_id'] = (string) $requestedJobId;
            $form['job_title'] = (string) (TimeEntry::jobLabelForBusiness($businessId, $requestedJobId) ?? '');
            if ($returnTo === '') {
                $returnTo = '/jobs/' . (string) $requestedJobId;
            }
        }

        if (trim((string) ($form['clock_in_at'] ?? '')) === '') {
            $form['clock_in_at'] = date('Y-m-d\TH:i');
        }

        $this->render('time_tracking/form', [
            'pageTitle' => 'Add Time Entry',
            'mode' => 'create',
            'actionUrl' => url('/time-tracking'),
            'form' => $form,
            'errors' => [],
            'employeeOptions' => $this->employeeAutosuggestOptions($businessId, $canManageEmployees, $selfEmployee),
            'canManageEmployees' => $canManageEmployees,
            'selfEmployee' => $selfEmployee,
            'jobSearchUrl' => url('/time-tracking/job-search'),
            'returnTo' => $returnTo,
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        $returnTo = $this->resolveReturnPath((string) ($_POST['return_to'] ?? ''));

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            if ($returnTo !== '') {
                redirect($returnTo);
            }
            redirect('/time-tracking/create');
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        $form = $this->formFromPost($_POST);
        if (!$canManageEmployees && $selfEmployee !== null) {
            $form['employee_id'] = (string) ((int) ($selfEmployee['id'] ?? 0));
            $form['employee_name'] = (string) (($selfEmployee['display_name'] ?? '') !== '' ? $selfEmployee['display_name'] : ($selfEmployee['employee_name'] ?? ''));
        }

        $errors = $this->validateForm($form, $businessId, $canManageEmployees, $selfEmployee);
        if ($errors !== []) {
            $this->render('time_tracking/form', [
                'pageTitle' => 'Add Time Entry',
                'mode' => 'create',
                'actionUrl' => url('/time-tracking'),
                'form' => $form,
                'errors' => $errors,
                'employeeOptions' => $this->employeeAutosuggestOptions($businessId, $canManageEmployees, $selfEmployee),
                'canManageEmployees' => $canManageEmployees,
                'selfEmployee' => $selfEmployee,
                'jobSearchUrl' => url('/time-tracking/job-search'),
                'returnTo' => $returnTo,
            ]);
            return;
        }

        $payload = $this->payloadForSave($form);
        $entryId = TimeEntry::create($businessId, $payload, auth_user_id() ?? 0);
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId > 0) {
            Job::assignEmployee($businessId, (int) $jobId, (int) ($payload['employee_id'] ?? 0), auth_user_id() ?? 0);
        }
        flash('success', 'Time entry created.');
        if ($returnTo !== '') {
            redirect($returnTo);
        }
        redirect('/time-tracking/' . (string) $entryId);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $entryId = (int) ($params['id'] ?? 0);
        if ($entryId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        if (!$canManageEmployees && $selfEmployee === null) {
            flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
            redirect('/time-tracking');
        }

        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;
        $entry = TimeEntry::findForBusiness($businessId, $entryId, $scopeEmployeeId);
        if ($entry === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('time_tracking/form', [
            'pageTitle' => 'Edit Time Entry',
            'mode' => 'edit',
            'actionUrl' => url('/time-tracking/' . (string) $entryId . '/update'),
            'form' => $this->formFromModel($entry, $businessId),
            'errors' => [],
            'employeeOptions' => $this->employeeAutosuggestOptions($businessId, $canManageEmployees, $selfEmployee),
            'canManageEmployees' => $canManageEmployees,
            'selfEmployee' => $selfEmployee,
            'jobSearchUrl' => url('/time-tracking/job-search'),
            'entryId' => $entryId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $entryId = (int) ($params['id'] ?? 0);
        if ($entryId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/time-tracking/' . (string) $entryId . '/edit');
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        if (!$canManageEmployees && $selfEmployee === null) {
            flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
            redirect('/time-tracking');
        }

        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;
        $entry = TimeEntry::findForBusiness($businessId, $entryId, $scopeEmployeeId);
        if ($entry === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        if (!$canManageEmployees && $selfEmployee !== null) {
            $form['employee_id'] = (string) ((int) ($selfEmployee['id'] ?? 0));
            $form['employee_name'] = (string) (($selfEmployee['display_name'] ?? '') !== '' ? $selfEmployee['display_name'] : ($selfEmployee['employee_name'] ?? ''));
        }

        $existingClockOut = trim((string) ($entry['clock_out_at'] ?? ''));
        $requestedClockOut = trim((string) ($form['clock_out_at'] ?? ''));
        if (!$canManageEmployees && $existingClockOut === '' && $requestedClockOut !== '') {
            $errors = ['clock_out_at' => 'Only admins can manually close an open time entry. Use Punch Out.'];
            $this->render('time_tracking/form', [
                'pageTitle' => 'Edit Time Entry',
                'mode' => 'edit',
                'actionUrl' => url('/time-tracking/' . (string) $entryId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'employeeOptions' => $this->employeeAutosuggestOptions($businessId, $canManageEmployees, $selfEmployee),
                'canManageEmployees' => $canManageEmployees,
                'selfEmployee' => $selfEmployee,
                'jobSearchUrl' => url('/time-tracking/job-search'),
                'entryId' => $entryId,
            ]);
            return;
        }

        $errors = $this->validateForm($form, $businessId, $canManageEmployees, $selfEmployee, $entryId);
        if ($errors !== []) {
            $this->render('time_tracking/form', [
                'pageTitle' => 'Edit Time Entry',
                'mode' => 'edit',
                'actionUrl' => url('/time-tracking/' . (string) $entryId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'employeeOptions' => $this->employeeAutosuggestOptions($businessId, $canManageEmployees, $selfEmployee),
                'canManageEmployees' => $canManageEmployees,
                'selfEmployee' => $selfEmployee,
                'jobSearchUrl' => url('/time-tracking/job-search'),
                'entryId' => $entryId,
            ]);
            return;
        }

        $payload = $this->payloadForSave($form);
        TimeEntry::update($businessId, $entryId, $payload, auth_user_id() ?? 0);
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId > 0) {
            Job::assignEmployee($businessId, (int) $jobId, (int) ($payload['employee_id'] ?? 0), auth_user_id() ?? 0);
        }
        flash('success', 'Time entry updated.');
        redirect('/time-tracking/' . (string) $entryId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $entryId = (int) ($params['id'] ?? 0);
        if ($entryId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        if (!$canManageEmployees && $selfEmployee === null) {
            flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
            redirect('/');
        }
        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;
        $entry = TimeEntry::findForBusiness($businessId, $entryId, $scopeEmployeeId);
        if ($entry === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('time_tracking/show', [
            'pageTitle' => 'Time Entry',
            'entry' => $entry,
            'canManageEmployees' => $canManageEmployees,
        ]);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $entryId = (int) ($params['id'] ?? 0);
        if ($entryId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/time-tracking/' . (string) $entryId);
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);
        $scopeEmployeeId = (!$canManageEmployees && $selfEmployee !== null) ? (int) ($selfEmployee['id'] ?? 0) : null;
        $entry = TimeEntry::findForBusiness($businessId, $entryId, $scopeEmployeeId);
        if ($entry === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = TimeEntry::softDelete($businessId, $entryId, auth_user_id() ?? 0, $scopeEmployeeId);
        if ($deleted) {
            flash('success', 'Time entry deleted.');
            redirect('/time-tracking');
        }

        flash('error', 'Unable to delete time entry.');
        redirect('/time-tracking/' . (string) $entryId);
    }

    public function jobSearch(): void
    {
        require_business_role(['punch_only', 'general_user', 'admin']);

        $items = TimeEntry::jobSearchOptions(current_business_id(), trim((string) ($_GET['q'] ?? '')), 10);
        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $results[] = [
                'id' => $id,
                'title' => (string) ($item['title'] ?? ('Job #' . (string) $id)),
                'city' => (string) ($item['city'] ?? ''),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function punchIn(): void
    {
        require_business_role(['punch_only', 'general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/time-tracking/punch-board');
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if (!$canManageEmployees) {
            if ($selfEmployee === null) {
                flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
                redirect('/time-tracking/punch-board');
            }
            $employeeId = (int) ($selfEmployee['id'] ?? 0);
        }

        if ($employeeId <= 0) {
            flash('error', 'Choose an employee before punching in.');
            redirect('/time-tracking/punch-board');
        }

        $employee = Employee::findForBusiness($businessId, $employeeId);
        if ($employee === null || trim((string) ($employee['status'] ?? 'active')) === 'inactive') {
            flash('error', 'Employee is not available for punch in.');
            redirect('/time-tracking/punch-board');
        }

        if (TimeEntry::openEntryForEmployee($businessId, $employeeId) !== null) {
            flash('error', 'This employee is already punched in.');
            redirect('/time-tracking/punch-board');
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        $isNonJob = $jobId <= 0;

        if (!$isNonJob && !TimeEntry::jobExistsForBusiness($businessId, $jobId)) {
            flash('error', 'Choose a valid job from suggestions or leave Job blank for non-job time.');
            redirect('/time-tracking/punch-board');
        }

        $clockInAt = date('Y-m-d H:i:s');
        if (TimeEntry::hasOverlapForEmployee($businessId, $employeeId, $clockInAt, null)) {
            flash('error', 'This employee already has overlapping time. Close the open entry first.');
            redirect('/time-tracking/punch-board');
        }

        $entryId = TimeEntry::punchInNow(
            $businessId,
            $employeeId,
            $isNonJob ? null : $jobId,
            $isNonJob,
            auth_user_id() ?? 0,
            trim((string) ($_POST['notes'] ?? ''))
        );
        if ($entryId > 0 && !$isNonJob && $jobId > 0) {
            Job::assignEmployee($businessId, $jobId, $employeeId, auth_user_id() ?? 0);
        }

        flash('success', 'Punched in.');
        redirect('/time-tracking/punch-board');
    }

    public function punchOut(): void
    {
        require_business_role(['punch_only', 'general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/time-tracking/punch-board');
        }

        $businessId = current_business_id();
        $canManageEmployees = $this->canManageEmployees();
        $selfEmployee = $this->currentUserEmployee($businessId);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if (!$canManageEmployees) {
            if ($selfEmployee === null) {
                flash('error', 'No employee profile is linked to your user yet. Ask an admin to link one.');
                redirect('/time-tracking/punch-board');
            }
            $employeeId = (int) ($selfEmployee['id'] ?? 0);
        }

        if ($employeeId <= 0) {
            flash('error', 'Choose an employee before punching out.');
            redirect('/time-tracking/punch-board');
        }

        $entryId = TimeEntry::punchOutOpenEntry($businessId, $employeeId, auth_user_id() ?? 0);
        if (($entryId ?? 0) <= 0) {
            flash('error', 'No open time entry found for this employee.');
            redirect('/time-tracking/punch-board');
        }

        flash('success', 'Punched out.');
        redirect('/time-tracking/punch-board');
    }

    private function defaultForm(): array
    {
        return [
            'employee_id' => '',
            'employee_name' => '',
            'job_id' => '',
            'job_title' => '',
            'clock_in_at' => '',
            'clock_out_at' => '',
            'clock_in_lat' => '',
            'clock_in_lng' => '',
            'clock_out_lat' => '',
            'clock_out_lng' => '',
            'notes' => '',
        ];
    }

    private function formFromModel(array $entry, int $businessId): array
    {
        $jobId = (int) ($entry['job_id'] ?? 0);

        return [
            'employee_id' => (string) ((int) ($entry['employee_id'] ?? 0)),
            'employee_name' => trim((string) ($entry['employee_name'] ?? '')),
            'job_id' => (string) $jobId,
            'job_title' => trim((string) ($entry['job_title'] ?? '')),
            'clock_in_at' => $this->toInputDatetime((string) ($entry['clock_in_at'] ?? '')),
            'clock_out_at' => $this->toInputDatetime((string) ($entry['clock_out_at'] ?? '')),
            'clock_in_lat' => trim((string) ($entry['clock_in_lat'] ?? '')),
            'clock_in_lng' => trim((string) ($entry['clock_in_lng'] ?? '')),
            'clock_out_lat' => trim((string) ($entry['clock_out_lat'] ?? '')),
            'clock_out_lng' => trim((string) ($entry['clock_out_lng'] ?? '')),
            'notes' => trim((string) ($entry['notes'] ?? '')),
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'employee_id' => trim((string) ($input['employee_id'] ?? '')),
            'employee_name' => trim((string) ($input['employee_name'] ?? '')),
            'job_id' => trim((string) ($input['job_id'] ?? '')),
            'job_title' => trim((string) ($input['job_title'] ?? '')),
            'clock_in_at' => trim((string) ($input['clock_in_at'] ?? '')),
            'clock_out_at' => trim((string) ($input['clock_out_at'] ?? '')),
            'clock_in_lat' => trim((string) ($input['clock_in_lat'] ?? '')),
            'clock_in_lng' => trim((string) ($input['clock_in_lng'] ?? '')),
            'clock_out_lat' => trim((string) ($input['clock_out_lat'] ?? '')),
            'clock_out_lng' => trim((string) ($input['clock_out_lng'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    private function validateForm(array $form, int $businessId, bool $canManageEmployees, ?array $selfEmployee, ?int $excludeEntryId = null): array
    {
        $errors = [];

        $employeeId = (int) ($form['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            $errors['employee_id'] = 'Choose a valid employee.';
        } else {
            $employee = Employee::findForBusiness($businessId, $employeeId);
            if ($employee === null || trim((string) ($employee['status'] ?? 'active')) === 'inactive') {
                $errors['employee_id'] = 'Choose a valid active employee.';
            }

            if (!$canManageEmployees) {
                $selfEmployeeId = (int) ($selfEmployee['id'] ?? 0);
                if ($selfEmployeeId <= 0 || $selfEmployeeId !== $employeeId) {
                    $errors['employee_id'] = 'You can only log time for your own employee profile.';
                }
            }
        }

        $jobId = (int) ($form['job_id'] ?? 0);
        $isNonJob = $jobId <= 0;
        if (!$isNonJob && !TimeEntry::jobExistsForBusiness($businessId, $jobId)) {
            $errors['job_id'] = 'Choose a valid job from suggestions or leave Job blank for non-job time.';
        }

        if ($form['clock_in_at'] === '' || $this->asTimestamp($form['clock_in_at']) === null) {
            $errors['clock_in_at'] = 'Clock in is required.';
        }

        if ($form['clock_out_at'] !== '' && $this->asTimestamp($form['clock_out_at']) === null) {
            $errors['clock_out_at'] = 'Clock out must be a valid date/time.';
        }

        $clockInTs = $this->asTimestamp($form['clock_in_at']);
        $clockOutTs = $this->asTimestamp($form['clock_out_at']);
        if ($clockInTs !== null && $clockOutTs !== null && $clockOutTs < $clockInTs) {
            $errors['clock_out_at'] = 'Clock out must be after clock in.';
        }

        if (!isset($errors['clock_in_at']) && !isset($errors['clock_out_at']) && !isset($errors['employee_id'])) {
            $employeeId = (int) ($form['employee_id'] ?? 0);
            if ($employeeId > 0 && $clockInTs !== null) {
                $hasOverlap = TimeEntry::hasOverlapForEmployee(
                    $businessId,
                    $employeeId,
                    date('Y-m-d H:i:s', $clockInTs),
                    $clockOutTs !== null ? date('Y-m-d H:i:s', $clockOutTs) : null,
                    $excludeEntryId
                );
                if ($hasOverlap) {
                    $errors['clock_in_at'] = 'This time overlaps an existing entry for the employee.';
                }
            }
        }

        foreach (['clock_in_lat', 'clock_in_lng', 'clock_out_lat', 'clock_out_lng'] as $field) {
            if ($form[$field] !== '' && !is_numeric($form[$field])) {
                $errors[$field] = 'Must be a numeric value.';
            }
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        $clockInTs = $this->asTimestamp($form['clock_in_at']);
        $clockOutTs = $this->asTimestamp($form['clock_out_at']);
        $durationMinutes = null;
        if ($clockInTs !== null && $clockOutTs !== null && $clockOutTs >= $clockInTs) {
            $durationMinutes = (int) floor(($clockOutTs - $clockInTs) / 60);
        }

        $jobId = (int) ($form['job_id'] ?? 0);
        $isNonJob = $jobId <= 0;

        return [
            'employee_id' => (int) $form['employee_id'],
            'job_id' => $isNonJob ? null : ($jobId > 0 ? $jobId : null),
            'is_non_job' => $isNonJob ? 1 : 0,
            'clock_in_at' => $this->toDatabaseDatetime($form['clock_in_at']),
            'clock_out_at' => $this->toDatabaseDatetime($form['clock_out_at']),
            'duration_minutes' => $durationMinutes,
            'clock_in_lat' => $form['clock_in_lat'] !== '' ? (float) $form['clock_in_lat'] : null,
            'clock_in_lng' => $form['clock_in_lng'] !== '' ? (float) $form['clock_in_lng'] : null,
            'clock_out_lat' => $form['clock_out_lat'] !== '' ? (float) $form['clock_out_lat'] : null,
            'clock_out_lng' => $form['clock_out_lng'] !== '' ? (float) $form['clock_out_lng'] : null,
            'notes' => $form['notes'],
        ];
    }

    private function canManageEmployees(): bool
    {
        return is_site_admin() || workspace_role() === 'admin';
    }

    private function currentUserEmployee(int $businessId): ?array
    {
        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            return null;
        }

        return Employee::findByUserForBusiness($businessId, $userId);
    }

    private function employeeAutosuggestOptions(int $businessId, bool $canManageEmployees, ?array $selfEmployee): array
    {
        if ($canManageEmployees) {
            $rows = Employee::activeOptions($businessId, '', 300);
            $options = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $metaParts = [];
                $employeeName = trim((string) ($row['employee_name'] ?? ''));
                $linkedUserName = trim((string) ($row['linked_user_name'] ?? ''));
                if ($linkedUserName !== '') {
                    $metaParts[] = 'Linked user: ' . $linkedUserName;
                }
                if ($employeeName !== '' && strcasecmp($employeeName, $name) !== 0) {
                    $metaParts[] = 'Employee: ' . $employeeName;
                }
                $linkedUserEmail = trim((string) ($row['linked_user_email'] ?? ''));
                if ($linkedUserEmail !== '') {
                    $metaParts[] = $linkedUserEmail;
                }

                $options[] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : ('Employee #' . (string) $id),
                    'meta' => implode(' · ', $metaParts),
                ];
            }

            return $options;
        }

        if ($selfEmployee === null) {
            return [];
        }

        return [[
            'id' => (int) ($selfEmployee['id'] ?? 0),
            'name' => trim((string) (($selfEmployee['display_name'] ?? '') !== '' ? $selfEmployee['display_name'] : ($selfEmployee['employee_name'] ?? ''))) !== ''
                ? trim((string) (($selfEmployee['display_name'] ?? '') !== '' ? $selfEmployee['display_name'] : ($selfEmployee['employee_name'] ?? '')))
                : ('Employee #' . (string) ((int) ($selfEmployee['id'] ?? 0))),
            'meta' => 'My employee profile',
        ]];
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

    private function resolveReturnPath(string $raw): string
    {
        $path = trim($raw);
        if ($path === '') {
            return '';
        }
        if (!str_starts_with($path, '/')) {
            return '';
        }
        if (str_starts_with($path, '//')) {
            return '';
        }

        return $path;
    }
}
