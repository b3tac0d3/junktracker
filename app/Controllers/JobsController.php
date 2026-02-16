<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Job;
use App\Models\Prospect;
use App\Models\TimeEntry;

final class JobsController extends Controller
{
    private const JOB_STATUSES = ['pending', 'active', 'complete', 'cancelled'];
    private const BILLING_TYPES = ['deposit', 'bill_sent', 'payment', 'adjustment', 'other'];
    private const OWNER_TYPES = ['client', 'estate', 'company'];

    public function index(): void
    {
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => $_GET['status'] ?? 'all',
            'record_status' => $_GET['record_status'] ?? 'active',
            'start_date' => trim($_GET['start_date'] ?? ''),
            'end_date' => trim($_GET['end_date'] ?? ''),
        ];

        if (!in_array($filters['status'], ['all', 'pending', 'active', 'complete', 'cancelled'], true)) {
            $filters['status'] = 'all';
        }
        if (!in_array($filters['record_status'], ['active', 'deleted', 'all'], true)) {
            $filters['record_status'] = 'active';
        }

        $jobs = Job::filter($filters);

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/jobs-table.js') . '"></script>',
        ]);

        $this->render('jobs/index', [
            'pageTitle' => 'Jobs',
            'jobs' => $jobs,
            'filters' => $filters,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        $job = [];
        $fromProspectId = $this->toIntOrNull($_GET['from_prospect'] ?? null);
        if ($fromProspectId !== null && $fromProspectId > 0) {
            $prospect = Prospect::findById($fromProspectId);
            if (!$prospect) {
                flash('error', 'Prospect not found.');
                redirect('/prospects');
            }

            $isProspectActive = empty($prospect['deleted_at']) && !empty($prospect['active']);
            if (!$isProspectActive) {
                flash('error', 'This prospect is already inactive.');
                redirect('/prospects/' . $fromProspectId);
            }

            $job = $this->jobPrefillFromProspect($prospect);
        }

        $this->render('jobs/create', [
            'pageTitle' => 'Add Job',
            'job' => $job,
            'pageScripts' => '<script src="' . asset('js/job-owner-contact-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!$this->checkCsrf('/jobs/new')) {
            return;
        }

        $status = (string) ($_POST['job_status'] ?? 'pending');
        if (!in_array($status, self::JOB_STATUSES, true)) {
            $status = 'pending';
        }

        $ownerType = (string) ($_POST['job_owner_type'] ?? 'client');
        if (!in_array($ownerType, self::OWNER_TYPES, true)) {
            $ownerType = 'client';
        }

        $ownerId = $this->toIntOrNull($_POST['job_owner_id'] ?? null);
        $contactClientId = $this->toIntOrNull($_POST['contact_client_id'] ?? null);

        $ownerSummary = ($ownerId !== null && $ownerId > 0)
            ? Job::findOwnerSummary($ownerType, $ownerId)
            : null;

        if ($ownerSummary && $contactClientId === null) {
            if ($ownerType === 'client') {
                $contactClientId = (int) ($ownerSummary['related_client_id'] ?? 0);
            } elseif ($ownerType === 'estate' && !empty($ownerSummary['related_client_id'])) {
                $contactClientId = (int) $ownerSummary['related_client_id'];
            }
        }

        $contactSummary = ($contactClientId !== null && $contactClientId > 0)
            ? Job::findClientSummary($contactClientId)
            : null;

        $estateId = $ownerType === 'estate' ? $ownerId : null;

        $data = [
            'client_id' => $contactClientId,
            'estate_id' => $estateId,
            'job_owner_type' => $ownerType,
            'job_owner_id' => $ownerId,
            'contact_client_id' => $contactClientId,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => trim((string) ($_POST['state'] ?? '')),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'quote_date' => $this->toDateTimeOrNull($_POST['quote_date'] ?? null),
            'scheduled_date' => $this->toDateTimeOrNull($_POST['scheduled_date'] ?? null),
            'start_date' => $this->toDateTimeOrNull($_POST['start_date'] ?? null),
            'end_date' => $this->toDateTimeOrNull($_POST['end_date'] ?? null),
            'billed_date' => $this->toDateTimeOrNull($_POST['billed_date'] ?? null),
            'paid_date' => $this->toDateTimeOrNull($_POST['paid_date'] ?? null),
            'job_status' => $status,
            'total_quote' => $this->toDecimalOrNull($_POST['total_quote'] ?? null),
            'total_billed' => $this->toDecimalOrNull($_POST['total_billed'] ?? null),
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Job name is required.';
        }
        if (!$ownerSummary) {
            $errors[] = 'Select a valid job owner.';
        }
        if (!$contactSummary) {
            $errors[] = 'Select a valid contact.';
        }
        if ($data['client_id'] === null || $data['client_id'] <= 0) {
            $errors[] = 'A client contact is required.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/jobs/new');
        }

        $jobId = Job::create($data, $this->actorId());
        $this->logJobAction($jobId, 'job_created', 'Job created.');
        if (!empty($data['quote_date'])) {
            $this->logJobAction($jobId, 'quote_done', 'Quote date has been set.');
        }
        if ($data['note'] !== '') {
            $this->logJobAction($jobId, 'note_updated', 'Job note was added.');
        }

        $sourceProspectId = $this->toIntOrNull($_POST['source_prospect_id'] ?? null);
        if ($sourceProspectId !== null && $sourceProspectId > 0) {
            $prospect = Prospect::findById($sourceProspectId);
            if ($prospect && empty($prospect['deleted_at']) && !empty($prospect['active'])) {
                Prospect::convertToJob($sourceProspectId, $jobId, $this->actorId());
                $this->logJobAction(
                    $jobId,
                    'prospect_converted',
                    'Converted from prospect #' . $sourceProspectId . '.',
                    null,
                    'prospects',
                    $sourceProspectId
                );
            }
        }

        flash('success', 'Job created.');
        redirect('/jobs/' . $jobId);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($id);
        if (!$job) {
            return;
        }

        $crewEmployees = Job::crewMembers($id);
        $openEntriesForJob = TimeEntry::openEntriesForJob($id);
        $openByEmployee = [];
        foreach ($openEntriesForJob as $entry) {
            $employeeId = (int) ($entry['employee_id'] ?? 0);
            if ($employeeId > 0) {
                $openByEmployee[$employeeId] = $entry;
            }
        }

        $employeeIds = array_map(static fn (array $employee): int => (int) ($employee['employee_id'] ?? 0), $crewEmployees);
        $openEntriesElsewhere = TimeEntry::openEntriesOutsideJob($id, $employeeIds);
        $openElsewhereByEmployee = [];
        foreach ($openEntriesElsewhere as $entry) {
            $employeeId = (int) ($entry['employee_id'] ?? 0);
            if ($employeeId > 0 && !isset($openElsewhereByEmployee[$employeeId])) {
                $openElsewhereByEmployee[$employeeId] = $entry;
            }
        }

        $this->render('jobs/show', [
            'pageTitle' => 'Job Details',
            'job' => $job,
            'actions' => Job::actions($id),
            'disposals' => Job::disposals($id),
            'expenses' => Job::expenses($id),
            'summary' => Job::summary($id),
            'billingEntries' => Job::billingEntries($id),
            'timeEntries' => TimeEntry::forJob($id),
            'timeSummary' => TimeEntry::summaryForJob($id),
            'crewEmployees' => $crewEmployees,
            'openByEmployee' => $openByEmployee,
            'openElsewhereByEmployee' => $openElsewhereByEmployee,
            'pageScripts' => '<script src="' . asset('js/job-crew-lookup.js') . '"></script>',
        ]);
    }

    public function punchIn(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $employeeId = $this->toIntOrNull($_POST['employee_id'] ?? null);
        if ($employeeId === null || $employeeId <= 0) {
            flash('error', 'Select a valid employee.');
            redirect('/jobs/' . $jobId);
        }

        if (!Job::isCrewMember($jobId, $employeeId)) {
            flash('error', 'Employee must be added to the crew before punch in.');
            redirect('/jobs/' . $jobId);
        }

        $openEntry = TimeEntry::findOpenForEmployee($employeeId);
        if ($openEntry) {
            $openJobId = (int) ($openEntry['job_id'] ?? 0);
            if ($openJobId === $jobId) {
                flash('error', 'This employee is already punched in on this job.');
                redirect('/jobs/' . $jobId);
            }

            flash('error', 'This employee is currently punched in on job #' . $openJobId . '.');
            redirect('/jobs/' . $jobId);
        }

        $employee = null;
        foreach (Job::crewMembers($jobId) as $candidate) {
            if ((int) ($candidate['employee_id'] ?? 0) === $employeeId) {
                $employee = $candidate;
                break;
            }
        }

        $payRate = isset($employee['pay_rate']) && $employee['pay_rate'] !== null
            ? (float) $employee['pay_rate']
            : (TimeEntry::employeeRate($employeeId) ?? 0.0);

        $entryId = TimeEntry::create([
            'employee_id' => $employeeId,
            'job_id' => $jobId,
            'work_date' => date('Y-m-d'),
            'start_time' => date('H:i:s'),
            'end_time' => null,
            'minutes_worked' => null,
            'pay_rate' => $payRate,
            'total_paid' => null,
            'note' => null,
        ], $this->actorId());

        $employeeName = trim((string) (($employee['employee_name'] ?? '') !== '' ? $employee['employee_name'] : ('Employee #' . $employeeId)));
        $this->logJobAction(
            $jobId,
            'time_punched_in',
            $employeeName . ' punched in.',
            null,
            'employee_time_entries',
            $entryId
        );

        flash('success', $employeeName . ' punched in.');
        redirect('/jobs/' . $jobId);
    }

    public function punchOut(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $entryId = $this->toIntOrNull($_POST['time_entry_id'] ?? null);
        if ($entryId === null || $entryId <= 0) {
            flash('error', 'Invalid time entry.');
            redirect('/jobs/' . $jobId);
        }

        $entry = TimeEntry::findById($entryId);
        if (
            !$entry
            || (int) ($entry['job_id'] ?? 0) !== $jobId
            || !empty($entry['deleted_at'])
            || (int) ($entry['active'] ?? 1) !== 1
            || empty($entry['start_time'])
            || !empty($entry['end_time'])
        ) {
            flash('error', 'This time entry is not available for punch out.');
            redirect('/jobs/' . $jobId);
        }

        $minutesWorked = $this->calculateOpenMinutes(
            (string) ($entry['work_date'] ?? date('Y-m-d')),
            (string) ($entry['start_time'] ?? date('H:i:s'))
        );
        $payRate = isset($entry['pay_rate']) && $entry['pay_rate'] !== null
            ? (float) $entry['pay_rate']
            : (TimeEntry::employeeRate((int) ($entry['employee_id'] ?? 0)) ?? 0.0);
        $totalPaid = round(($payRate * $minutesWorked) / 60, 2);

        TimeEntry::punchOut($entryId, [
            'end_time' => date('H:i:s'),
            'minutes_worked' => $minutesWorked,
            'pay_rate' => $payRate,
            'total_paid' => $totalPaid,
        ], $this->actorId());

        $employeeName = trim((string) ($entry['employee_name'] ?? ('Employee #' . (string) ($entry['employee_id'] ?? ''))));
        $this->logJobAction(
            $jobId,
            'time_punched_out',
            $employeeName . ' punched out (' . $this->formatDuration($minutesWorked) . ').',
            $totalPaid,
            'employee_time_entries',
            $entryId
        );

        flash('success', $employeeName . ' punched out.');
        redirect('/jobs/' . $jobId);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($id);
        if (!$job) {
            return;
        }

        $this->render('jobs/edit', [
            'pageTitle' => 'Edit Job',
            'job' => $job,
            'pageScripts' => '<script src="' . asset('js/job-owner-contact-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function ownerLookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $results = Job::searchOwners($term);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($results);
    }

    public function contactLookup(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $results = Job::searchContactClients($term);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($results);
    }

    public function crewLookup(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([]);
            return;
        }

        $term = trim((string) ($_GET['q'] ?? ''));
        $results = Job::searchCrewCandidates($jobId, $term);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($results);
    }

    public function crewAdd(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $employeeId = $this->toIntOrNull($_POST['employee_id'] ?? null);
        if ($employeeId === null || $employeeId <= 0) {
            flash('error', 'Select an employee to add.');
            redirect('/jobs/' . $jobId);
        }

        $isAlreadyCrew = Job::isCrewMember($jobId, $employeeId);
        if ($isAlreadyCrew) {
            flash('error', 'Employee is already on this crew.');
            redirect('/jobs/' . $jobId);
        }

        $activeEmployees = TimeEntry::employees();
        $isActiveEmployee = false;
        foreach ($activeEmployees as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $employeeId) {
                $isActiveEmployee = true;
                break;
            }
        }
        if (!$isActiveEmployee) {
            flash('error', 'Selected employee is not active.');
            redirect('/jobs/' . $jobId);
        }

        Job::addCrewMember($jobId, $employeeId, $this->actorId());

        $crew = Job::crewMembers($jobId);
        $employeeName = 'Employee #' . $employeeId;
        foreach ($crew as $member) {
            if ((int) ($member['employee_id'] ?? 0) === $employeeId) {
                $employeeName = (string) ($member['employee_name'] ?? $employeeName);
                break;
            }
        }

        $this->logJobAction($jobId, 'crew_member_added', $employeeName . ' added to crew.', null, 'employees', $employeeId);
        flash('success', $employeeName . ' added to crew.');
        redirect('/jobs/' . $jobId);
    }

    public function crewRemove(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $employeeId = isset($params['employeeId']) ? (int) $params['employeeId'] : 0;

        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        if ($employeeId <= 0) {
            flash('error', 'Invalid crew member.');
            redirect('/jobs/' . $jobId);
        }

        if (TimeEntry::findOpenForEmployee($employeeId)) {
            flash('error', 'Employee must be punched out before removal from crew.');
            redirect('/jobs/' . $jobId);
        }

        if (!Job::isCrewMember($jobId, $employeeId)) {
            flash('error', 'Employee is not on this crew.');
            redirect('/jobs/' . $jobId);
        }

        Job::removeCrewMember($jobId, $employeeId, $this->actorId());

        $this->logJobAction($jobId, 'crew_member_removed', 'Employee #' . $employeeId . ' removed from crew.', null, 'employees', $employeeId);
        flash('success', 'Crew member removed.');
        redirect('/jobs/' . $jobId);
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $id . '/edit')) {
            return;
        }

        $existing = $this->findJobOr404($id);
        if (!$existing) {
            return;
        }

        $status = (string) ($_POST['job_status'] ?? 'pending');
        if (!in_array($status, self::JOB_STATUSES, true)) {
            $status = 'pending';
        }

        $ownerType = (string) ($_POST['job_owner_type'] ?? ($existing['resolved_owner_type'] ?? 'client'));
        if (!in_array($ownerType, self::OWNER_TYPES, true)) {
            $ownerType = (string) ($existing['resolved_owner_type'] ?? 'client');
        }

        $ownerId = $this->toIntOrNull($_POST['job_owner_id'] ?? null);
        if ($ownerId === null && isset($existing['resolved_owner_id']) && is_numeric((string) $existing['resolved_owner_id'])) {
            $ownerId = (int) $existing['resolved_owner_id'];
        }

        $contactClientId = $this->toIntOrNull($_POST['contact_client_id'] ?? null);
        if ($contactClientId === null && isset($existing['resolved_contact_client_id']) && is_numeric((string) $existing['resolved_contact_client_id'])) {
            $contactClientId = (int) $existing['resolved_contact_client_id'];
        }

        $ownerSummary = ($ownerId !== null && $ownerId > 0)
            ? Job::findOwnerSummary($ownerType, $ownerId)
            : null;

        if ($ownerSummary && $contactClientId === null) {
            if ($ownerType === 'client') {
                $contactClientId = (int) ($ownerSummary['related_client_id'] ?? 0);
            } elseif ($ownerType === 'estate' && !empty($ownerSummary['related_client_id'])) {
                $contactClientId = (int) $ownerSummary['related_client_id'];
            }
        }

        $contactSummary = ($contactClientId !== null && $contactClientId > 0)
            ? Job::findClientSummary($contactClientId)
            : null;

        $estateId = $ownerType === 'estate' ? $ownerId : null;

        $data = [
            'client_id' => $contactClientId,
            'estate_id' => $estateId,
            'job_owner_type' => $ownerType,
            'job_owner_id' => $ownerId,
            'contact_client_id' => $contactClientId,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'address_1' => trim((string) ($_POST['address_1'] ?? '')),
            'address_2' => trim((string) ($_POST['address_2'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'state' => trim((string) ($_POST['state'] ?? '')),
            'zip' => trim((string) ($_POST['zip'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'quote_date' => $this->toDateTimeOrNull($_POST['quote_date'] ?? null),
            'scheduled_date' => $this->toDateTimeOrNull($_POST['scheduled_date'] ?? null),
            'start_date' => $this->toDateTimeOrNull($_POST['start_date'] ?? null),
            'end_date' => $this->toDateTimeOrNull($_POST['end_date'] ?? null),
            'billed_date' => $this->toDateTimeOrNull($_POST['billed_date'] ?? null),
            'paid_date' => $this->toDateTimeOrNull($_POST['paid_date'] ?? null),
            'job_status' => $status,
            'total_quote' => $this->toDecimalOrNull($_POST['total_quote'] ?? null),
            'total_billed' => $this->toDecimalOrNull($_POST['total_billed'] ?? null),
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Job name is required.';
        }
        if (!$ownerSummary) {
            $errors[] = 'Select a valid job owner.';
        }
        if (!$contactSummary) {
            $errors[] = 'Select a valid contact.';
        }
        if ($data['client_id'] === null || $data['client_id'] <= 0) {
            $errors[] = 'A client contact is required.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/jobs/' . $id . '/edit');
        }

        $changeLabels = [];
        $statusChanged = (string) ($existing['job_status'] ?? '') !== (string) $data['job_status'];
        $quoteSet = empty($existing['quote_date']) && !empty($data['quote_date']);
        $ownerChanged = (string) ($existing['owner_display_name'] ?? '') !== (string) ($ownerSummary['label'] ?? '');
        $contactChanged = (string) ($existing['contact_display_name'] ?? '') !== (string) ($contactSummary['label'] ?? '');
        $noteChanged = trim((string) ($existing['note'] ?? '')) !== (string) $data['note'];

        if ($statusChanged) {
            $changeLabels[] = 'status';
        }
        if ($quoteSet) {
            $changeLabels[] = 'quote';
        }
        if ($ownerChanged) {
            $changeLabels[] = 'owner';
        }
        if ($contactChanged) {
            $changeLabels[] = 'contact';
        }
        if ($noteChanged) {
            $changeLabels[] = 'note';
        }

        Job::updateDetails($id, $data, $this->actorId());
        if ($statusChanged) {
            $this->logJobAction(
                $id,
                'status_changed',
                'Status changed from ' . (string) ($existing['job_status'] ?? 'unknown') . ' to ' . (string) $data['job_status'] . '.'
            );
        }
        if ($quoteSet) {
            $this->logJobAction($id, 'quote_done', 'Quote date has been set.');
        }
        if ($noteChanged && $data['note'] !== '') {
            $this->logJobAction($id, 'note_updated', 'Job note was updated.');
        }
        if (empty($changeLabels)) {
            $changeLabels[] = 'details';
        }
        $this->logJobAction($id, 'job_updated', 'Updated: ' . implode(', ', $changeLabels) . '.');
        flash('success', 'Job details updated.');
        redirect('/jobs/' . $id);
    }

    public function delete(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $id)) {
            return;
        }

        Job::softDelete($id, $this->actorId());
        $this->logJobAction($id, 'job_deleted', 'Job was marked deleted.');
        flash('success', 'Job deleted.');
        redirect('/jobs/' . $id);
    }

    public function billingCreate(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($id);
        if (!$job) {
            return;
        }

        $this->render('jobs/billing_create', [
            'pageTitle' => 'Add Billing Entry',
            'job' => $job,
            'billing' => null,
        ]);

        clear_old();
    }

    public function billingStore(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId . '/billing/new')) {
            return;
        }

        $type = (string) ($_POST['entry_type'] ?? 'deposit');
        if (!in_array($type, self::BILLING_TYPES, true)) {
            $type = 'other';
        }

        $actionAt = $this->toDateTimeOrNull($_POST['entry_date'] ?? null);
        if ($actionAt === null) {
            flash('error', 'Billing entry date is required.');
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/billing/new');
        }

        $method = trim((string) ($_POST['method'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($method !== '') {
            $note = $note === '' ? 'Method: ' . $method : ('Method: ' . $method . ' | ' . $note);
        }

        Job::createAction($jobId, [
            'action_type' => $type,
            'action_at' => $actionAt,
            'amount' => $this->toDecimalOrNull($_POST['amount'] ?? null),
            'ref_table' => 'billing_entry',
            'ref_id' => null,
            'note' => $note,
        ], $this->actorId());
        Job::syncPaidStatus($jobId);

        flash('success', 'Billing entry added.');
        redirect('/jobs/' . $jobId);
    }

    public function billingEdit(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $billingId = isset($params['billingId']) ? (int) $params['billingId'] : 0;

        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        $billing = Job::findAction($jobId, $billingId);
        if (!$billing || !in_array((string) ($billing['action_type'] ?? ''), self::BILLING_TYPES, true)) {
            redirect('/jobs/' . $jobId);
        }

        $this->render('jobs/billing_edit', [
            'pageTitle' => 'Edit Billing Entry',
            'job' => $job,
            'billing' => $billing,
        ]);

        clear_old();
    }

    public function billingUpdate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $billingId = isset($params['billingId']) ? (int) $params['billingId'] : 0;

        if ($jobId <= 0 || $billingId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId . '/billing/' . $billingId . '/edit')) {
            return;
        }

        $existing = Job::findAction($jobId, $billingId);
        if (!$existing || !in_array((string) ($existing['action_type'] ?? ''), self::BILLING_TYPES, true)) {
            redirect('/jobs/' . $jobId);
        }

        $type = (string) ($_POST['entry_type'] ?? 'deposit');
        if (!in_array($type, self::BILLING_TYPES, true)) {
            $type = 'other';
        }

        $actionAt = $this->toDateTimeOrNull($_POST['entry_date'] ?? null);
        if ($actionAt === null) {
            flash('error', 'Billing entry date is required.');
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/billing/' . $billingId . '/edit');
        }

        $method = trim((string) ($_POST['method'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($method !== '') {
            $note = $note === '' ? 'Method: ' . $method : ('Method: ' . $method . ' | ' . $note);
        }

        Job::updateAction($jobId, $billingId, [
            'action_type' => $type,
            'action_at' => $actionAt,
            'amount' => $this->toDecimalOrNull($_POST['amount'] ?? null),
            'ref_table' => 'billing_entry',
            'ref_id' => null,
            'note' => $note,
        ], $this->actorId());
        Job::syncPaidStatus($jobId);
        $this->logJobAction(
            $jobId,
            'billing_entry_updated',
            'Billing entry #' . $billingId . ' updated (' . (string) ($existing['action_type'] ?? 'entry') . ' to ' . $type . ').',
            $this->toDecimalOrNull($_POST['amount'] ?? null),
            'job_actions',
            $billingId
        );

        flash('success', 'Billing entry updated.');
        redirect('/jobs/' . $jobId);
    }

    public function billingDelete(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $billingId = isset($params['billingId']) ? (int) $params['billingId'] : 0;

        if ($jobId <= 0 || $billingId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $existing = Job::findAction($jobId, $billingId);
        if (!$existing || !in_array((string) ($existing['action_type'] ?? ''), self::BILLING_TYPES, true)) {
            redirect('/jobs/' . $jobId);
        }

        Job::deleteAction($jobId, $billingId);
        Job::syncPaidStatus($jobId);
        $this->logJobAction(
            $jobId,
            'billing_entry_deleted',
            'Billing entry #' . $billingId . ' deleted (' . (string) ($existing['action_type'] ?? 'entry') . ').',
            isset($existing['amount']) ? (float) $existing['amount'] : null,
            'job_actions',
            $billingId
        );
        flash('success', 'Billing entry deleted.');
        redirect('/jobs/' . $jobId);
    }

    public function markPaid(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        Job::markPaid($jobId, null, $this->actorId());
        $this->logJobAction($jobId, 'job_marked_paid', 'Job manually marked as paid.');
        flash('success', 'Job marked as paid.');
        redirect('/jobs/' . $jobId);
    }

    public function actionCreate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId > 0) {
            $this->manualActionLogOnly($jobId);
        }
        redirect('/jobs');
    }

    public function actionStore(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId > 0) {
            $this->manualActionLogOnly($jobId);
        }
        redirect('/jobs');
    }

    public function actionEdit(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId > 0) {
            $this->manualActionLogOnly($jobId);
        }
        redirect('/jobs');
    }

    public function actionUpdate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId > 0) {
            $this->manualActionLogOnly($jobId);
        }
        redirect('/jobs');
    }

    public function actionDelete(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId > 0) {
            $this->manualActionLogOnly($jobId);
        }
        redirect('/jobs');
    }

    public function disposalCreate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        $this->render('jobs/disposal_create', [
            'pageTitle' => 'Add Disposal Event',
            'job' => $job,
            'disposal' => null,
            'locations' => Job::disposalLocations(),
        ]);

        clear_old();
    }

    public function disposalStore(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId . '/disposals/new')) {
            return;
        }

        $eventDate = $this->toDateOrNull($_POST['event_date'] ?? null);
        $locationId = $this->toIntOrNull($_POST['disposal_location_id'] ?? null);
        $type = (string) ($_POST['type'] ?? 'dump');

        if ($eventDate === null || $locationId === null) {
            flash('error', 'Date and disposal location are required.');
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/disposals/new');
        }

        if (!in_array($type, ['dump', 'transfer_station', 'landfill', 'other'], true)) {
            $type = 'other';
        }

        $disposalId = Job::createDisposal($jobId, [
            'disposal_location_id' => $locationId,
            'event_date' => $eventDate,
            'type' => $type,
            'amount' => (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0),
            'note' => $this->emptyToNull($_POST['note'] ?? null),
        ], $this->actorId());
        $amount = (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0);
        $this->logJobAction($jobId, 'disposal_added', 'Disposal event added (' . $type . ').', $amount, 'job_disposal_events', $disposalId);

        flash('success', 'Disposal event added.');
        redirect('/jobs/' . $jobId);
    }

    public function disposalEdit(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $disposalId = isset($params['disposalId']) ? (int) $params['disposalId'] : 0;

        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        $disposal = Job::findDisposal($jobId, $disposalId);
        if (!$disposal) {
            redirect('/jobs/' . $jobId);
        }

        $this->render('jobs/disposal_edit', [
            'pageTitle' => 'Edit Disposal Event',
            'job' => $job,
            'disposal' => $disposal,
            'locations' => Job::disposalLocations(),
        ]);

        clear_old();
    }

    public function disposalUpdate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $disposalId = isset($params['disposalId']) ? (int) $params['disposalId'] : 0;
        if ($jobId <= 0 || $disposalId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId . '/disposals/' . $disposalId . '/edit')) {
            return;
        }

        $eventDate = $this->toDateOrNull($_POST['event_date'] ?? null);
        $locationId = $this->toIntOrNull($_POST['disposal_location_id'] ?? null);
        $type = (string) ($_POST['type'] ?? 'dump');

        if ($eventDate === null || $locationId === null) {
            flash('error', 'Date and disposal location are required.');
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/disposals/' . $disposalId . '/edit');
        }

        if (!in_array($type, ['dump', 'transfer_station', 'landfill', 'other'], true)) {
            $type = 'other';
        }

        $existingDisposal = Job::findDisposal($jobId, $disposalId);
        if (!$existingDisposal) {
            redirect('/jobs/' . $jobId);
        }

        Job::updateDisposal($jobId, $disposalId, [
            'disposal_location_id' => $locationId,
            'event_date' => $eventDate,
            'type' => $type,
            'amount' => (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0),
            'note' => $this->emptyToNull($_POST['note'] ?? null),
        ], $this->actorId());
        $amount = (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0);
        $this->logJobAction($jobId, 'disposal_updated', 'Disposal event #' . $disposalId . ' updated.', $amount, 'job_disposal_events', $disposalId);

        flash('success', 'Disposal event updated.');
        redirect('/jobs/' . $jobId);
    }

    public function disposalDelete(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $disposalId = isset($params['disposalId']) ? (int) $params['disposalId'] : 0;
        if ($jobId <= 0 || $disposalId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $existingDisposal = Job::findDisposal($jobId, $disposalId);
        if (!$existingDisposal) {
            redirect('/jobs/' . $jobId);
        }

        Job::deleteDisposal($jobId, $disposalId, $this->actorId());
        $this->logJobAction(
            $jobId,
            'disposal_deleted',
            'Disposal event #' . $disposalId . ' deleted.',
            isset($existingDisposal['amount']) ? (float) $existingDisposal['amount'] : null,
            'job_disposal_events',
            $disposalId
        );
        flash('success', 'Disposal event deleted.');
        redirect('/jobs/' . $jobId);
    }

    public function expenseCreate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        $this->render('jobs/expense_create', [
            'pageTitle' => 'Add Expense',
            'job' => $job,
            'expense' => null,
            'categories' => ExpenseCategory::allActive(),
        ]);

        clear_old();
    }

    public function expenseStore(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId . '/expenses/new')) {
            return;
        }

        $expenseDate = $this->toDateOrNull($_POST['expense_date'] ?? null);
        $categoryId = $this->toIntOrNull($_POST['expense_category_id'] ?? null);
        $category = ($categoryId !== null && $categoryId > 0) ? ExpenseCategory::findById($categoryId) : null;

        if ($expenseDate === null || !$category) {
            flash('error', 'Expense date and category are required.');
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/expenses/new');
        }

        $categoryName = (string) ($category['name'] ?? '');

        $expenseId = Job::createExpense($jobId, [
            'disposal_location_id' => null,
            'expense_category_id' => $categoryId,
            'category' => $categoryName,
            'description' => $this->emptyToNull($_POST['description'] ?? null),
            'amount' => (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0),
            'expense_date' => $expenseDate,
        ], $this->actorId());
        $amount = (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0);
        $this->logJobAction($jobId, 'expense_added', 'Expense added (' . $categoryName . ').', $amount, 'expenses', $expenseId);

        flash('success', 'Expense added.');
        redirect('/jobs/' . $jobId);
    }

    public function expenseEdit(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $expenseId = isset($params['expenseId']) ? (int) $params['expenseId'] : 0;

        $job = $this->findJobOr404($jobId);
        if (!$job) {
            return;
        }

        $expense = Job::findExpense($jobId, $expenseId);
        if (!$expense) {
            redirect('/jobs/' . $jobId);
        }

        $this->render('jobs/expense_edit', [
            'pageTitle' => 'Edit Expense',
            'job' => $job,
            'expense' => $expense,
            'categories' => ExpenseCategory::allActive(),
        ]);

        clear_old();
    }

    public function expenseUpdate(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $expenseId = isset($params['expenseId']) ? (int) $params['expenseId'] : 0;
        if ($jobId <= 0 || $expenseId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId . '/expenses/' . $expenseId . '/edit')) {
            return;
        }

        $expenseDate = $this->toDateOrNull($_POST['expense_date'] ?? null);
        $categoryId = $this->toIntOrNull($_POST['expense_category_id'] ?? null);
        $category = ($categoryId !== null && $categoryId > 0) ? ExpenseCategory::findById($categoryId) : null;

        if ($expenseDate === null || !$category) {
            flash('error', 'Expense date and category are required.');
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/expenses/' . $expenseId . '/edit');
        }

        $categoryName = (string) ($category['name'] ?? '');

        $existingExpense = Job::findExpense($jobId, $expenseId);
        if (!$existingExpense) {
            redirect('/jobs/' . $jobId);
        }

        Job::updateExpense($jobId, $expenseId, [
            'disposal_location_id' => null,
            'expense_category_id' => $categoryId,
            'category' => $categoryName,
            'description' => $this->emptyToNull($_POST['description'] ?? null),
            'amount' => (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0),
            'expense_date' => $expenseDate,
        ], $this->actorId());
        $amount = (float) ($this->toDecimalOrNull($_POST['amount'] ?? null) ?? 0);
        $this->logJobAction($jobId, 'expense_updated', 'Expense #' . $expenseId . ' updated.', $amount, 'expenses', $expenseId);

        flash('success', 'Expense updated.');
        redirect('/jobs/' . $jobId);
    }

    public function expenseDelete(array $params): void
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        $expenseId = isset($params['expenseId']) ? (int) $params['expenseId'] : 0;
        if ($jobId <= 0 || $expenseId <= 0) {
            redirect('/jobs');
        }

        if (!$this->checkCsrf('/jobs/' . $jobId)) {
            return;
        }

        $existingExpense = Job::findExpense($jobId, $expenseId);
        if (!$existingExpense) {
            redirect('/jobs/' . $jobId);
        }

        Job::deleteExpense($jobId, $expenseId, $this->actorId());
        $this->logJobAction(
            $jobId,
            'expense_deleted',
            'Expense #' . $expenseId . ' deleted.',
            isset($existingExpense['amount']) ? (float) $existingExpense['amount'] : null,
            'expenses',
            $expenseId
        );
        flash('success', 'Expense deleted.');
        redirect('/jobs/' . $jobId);
    }

    private function manualActionLogOnly(int $jobId): void
    {
        flash('error', 'Manual job action add/edit/delete is disabled. Actions are logged automatically.');
        redirect('/jobs/' . $jobId);
    }

    private function jobPrefillFromProspect(array $prospect): array
    {
        $prospectId = isset($prospect['id']) ? (int) $prospect['id'] : 0;
        $clientId = isset($prospect['client_id']) ? (int) $prospect['client_id'] : 0;
        $client = $clientId > 0 ? Client::findById($clientId) : null;

        $clientLabel = trim((string) ($prospect['client_name'] ?? ''));
        if ($clientLabel === '' && $client) {
            $clientLabel = trim((string) (($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
            if ($clientLabel === '') {
                $clientLabel = trim((string) ($client['business_name'] ?? ''));
            }
        }
        if ($clientLabel === '' && $clientId > 0) {
            $clientLabel = 'Client #' . $clientId;
        }
        if ($clientLabel === '') {
            $clientLabel = 'Prospect';
        }

        $noteLines = ['Converted from prospect #' . $prospectId . '.'];
        $contactedOn = trim((string) ($prospect['contacted_on'] ?? ''));
        $followUpOn = trim((string) ($prospect['follow_up_on'] ?? ''));
        $nextStep = trim((string) ($prospect['next_step'] ?? ''));
        $prospectNote = trim((string) ($prospect['note'] ?? ''));

        if ($contactedOn !== '') {
            $noteLines[] = 'Contacted: ' . $contactedOn;
        }
        if ($followUpOn !== '') {
            $noteLines[] = 'Follow Up: ' . $followUpOn;
        }
        if ($nextStep !== '') {
            $noteLines[] = 'Next Step: ' . ucwords(str_replace('_', ' ', $nextStep));
        }
        if ($prospectNote !== '') {
            $noteLines[] = '';
            $noteLines[] = 'Prospect Notes:';
            $noteLines[] = $prospectNote;
        }

        $jobName = trim((string) ('Job for ' . $clientLabel));
        if ($jobName === 'Job for') {
            $jobName = 'New Job';
        }

        return [
            'source_prospect_id' => $prospectId,
            'name' => $jobName,
            'note' => implode("\n", $noteLines),
            'job_status' => 'pending',
            'resolved_owner_type' => $clientId > 0 ? 'client' : '',
            'resolved_owner_id' => $clientId > 0 ? $clientId : null,
            'owner_display_name' => $clientId > 0 ? $clientLabel : '',
            'resolved_contact_client_id' => $clientId > 0 ? $clientId : null,
            'contact_display_name' => $clientId > 0 ? $clientLabel : '',
            'address_1' => $client['address_1'] ?? '',
            'address_2' => $client['address_2'] ?? '',
            'city' => $client['city'] ?? '',
            'state' => $client['state'] ?? '',
            'zip' => $client['zip'] ?? '',
            'phone' => $client['phone'] ?? '',
            'email' => $client['email'] ?? '',
        ];
    }

    private function actorId(): ?int
    {
        return auth_user_id();
    }

    private function logJobAction(int $jobId, string $actionType, ?string $note = null, ?float $amount = null, ?string $refTable = null, ?int $refId = null): void
    {
        Job::createAction($jobId, [
            'action_type' => $actionType,
            'action_at' => date('Y-m-d H:i:s'),
            'amount' => $amount,
            'ref_table' => $refTable,
            'ref_id' => $refId,
            'note' => $this->emptyToNull($note),
        ], $this->actorId());
    }

    private function findJobOr404(int $id): ?array
    {
        if ($id <= 0) {
            redirect('/jobs');
        }

        $job = Job::findById($id);
        if ($job) {
            return $job;
        }

        http_response_code(404);
        if (class_exists('App\\Controllers\\ErrorController')) {
            (new \App\Controllers\ErrorController())->notFound();
            return null;
        }

        echo '404 Not Found';
        return null;
    }

    private function checkCsrf(string $redirectPath): bool
    {
        if (verify_csrf($_POST['csrf_token'] ?? null)) {
            return true;
        }

        flash('error', 'Your session expired. Please try again.');
        redirect($redirectPath);
        return false;
    }

    private function calculateOpenMinutes(string $workDate, string $startTime): int
    {
        $start = strtotime($workDate . ' ' . $startTime);
        $now = time();
        if ($start === false) {
            return 1;
        }

        if ($now < $start) {
            $now += 86400;
        }

        $minutes = (int) floor(($now - $start) / 60);
        return max(1, $minutes);
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h 00m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours . 'h ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function toDateTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
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

    private function toDecimalOrNull(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function emptyToNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        return $raw === '' ? null : $raw;
    }
}
