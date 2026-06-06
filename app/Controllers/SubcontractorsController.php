<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\JobSubcontractorAssignment;
use App\Models\Subcontractor;
use Core\Controller;

final class SubcontractorsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!Subcontractor::isAvailable()) {
            flash('error', 'Sub-contractors are not available yet. Run the latest migrations.');
            redirect('/clients');
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Subcontractor::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $subcontractors = Subcontractor::indexList($businessId, $search, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($subcontractors));

        $this->render('subcontractors/index', [
            'pageTitle' => 'Sub-Contractors',
            'search' => $search,
            'status' => $status,
            'subcontractors' => $subcontractors,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!Subcontractor::isAvailable()) {
            flash('error', 'Sub-contractors are not available yet. Run the latest migrations.');
            redirect('/clients');
        }

        $this->render('subcontractors/form', [
            'pageTitle' => 'Add Sub-Contractor',
            'mode' => 'create',
            'actionUrl' => url('/subs'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'hasAddressFields' => Subcontractor::hasAddressFields(),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/subs/create');
        }

        if (!Subcontractor::isAvailable()) {
            flash('error', 'Sub-contractors are not available yet. Run the latest migrations.');
            redirect('/clients');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = Subcontractor::validate($form);
        if ($errors !== []) {
            $this->render('subcontractors/form', [
                'pageTitle' => 'Add Sub-Contractor',
                'mode' => 'create',
                'actionUrl' => url('/subs'),
                'form' => $form,
                'errors' => $errors,
                'hasAddressFields' => Subcontractor::hasAddressFields(),
            ]);
            return;
        }

        $id = Subcontractor::create($businessId, $form, (int) (auth_user_id() ?? 0));
        if ($id <= 0) {
            flash('error', 'Unable to create sub-contractor.');
            redirect('/subs/create');
        }

        audit('subcontractor_created', 'subcontractors', $id);
        flash('success', 'Sub-contractor created.');
        redirect('/subs/' . (string) $id);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!Subcontractor::isAvailable()) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $subcontractorId = (int) ($params['id'] ?? 0);
        $subcontractor = Subcontractor::findForBusiness($businessId, $subcontractorId);
        if ($subcontractor === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $assignments = JobSubcontractorAssignment::listBySubcontractor($businessId, $subcontractorId, 200, 0);
        $earnings = JobSubcontractorAssignment::earningsSummary($businessId, $subcontractorId);
        $allowedTabs = ['details', 'jobs', 'earnings'];
        $activeTab = sanitize_detail_tab((string) ($_GET['tab'] ?? 'details'), $allowedTabs, 'details');

        $this->render('subcontractors/show', [
            'pageTitle' => 'Sub-Contractor',
            'subcontractor' => $subcontractor,
            'assignments' => $assignments,
            'earnings' => $earnings,
            'activeTab' => $activeTab,
            'jobsTabCount' => count($assignments),
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!Subcontractor::isAvailable()) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $subcontractorId = (int) ($params['id'] ?? 0);
        $subcontractor = Subcontractor::findForBusiness($businessId, $subcontractorId);
        if ($subcontractor === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('subcontractors/form', [
            'pageTitle' => 'Edit Sub-Contractor',
            'mode' => 'edit',
            'actionUrl' => url('/subs/' . (string) $subcontractorId . '/update'),
            'form' => $this->formFromModel($subcontractor),
            'errors' => [],
            'hasAddressFields' => Subcontractor::hasAddressFields(),
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/subs');
        }

        if (!Subcontractor::isAvailable()) {
            flash('error', 'Sub-contractors are not available yet. Run the latest migrations.');
            redirect('/subs');
        }

        $businessId = current_business_id();
        $subcontractorId = (int) ($params['id'] ?? 0);
        $existing = Subcontractor::findForBusiness($businessId, $subcontractorId);
        if ($existing === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = Subcontractor::validate($form);
        if ($errors !== []) {
            $this->render('subcontractors/form', [
                'pageTitle' => 'Edit Sub-Contractor',
                'mode' => 'edit',
                'actionUrl' => url('/subs/' . (string) $subcontractorId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'hasAddressFields' => Subcontractor::hasAddressFields(),
            ]);
            return;
        }

        Subcontractor::update($businessId, $subcontractorId, $form, (int) (auth_user_id() ?? 0));
        audit('subcontractor_updated', 'subcontractors', $subcontractorId);
        flash('success', 'Sub-contractor updated.');
        redirect('/subs/' . (string) $subcontractorId);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/subs');
        }

        if (!Subcontractor::isAvailable()) {
            flash('error', 'Sub-contractors are not available yet. Run the latest migrations.');
            redirect('/subs');
        }

        $businessId = current_business_id();
        $subcontractorId = (int) ($params['id'] ?? 0);
        $subcontractor = Subcontractor::findForBusiness($businessId, $subcontractorId);
        if ($subcontractor === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = Subcontractor::softDelete($businessId, $subcontractorId, (int) (auth_user_id() ?? 0));
        if ($deleted) {
            audit('subcontractor_deleted', 'subcontractors', $subcontractorId);
            flash('success', 'Sub-contractor deleted.');
            redirect('/subs');
        }

        flash('error', 'Unable to delete sub-contractor.');
        redirect('/subs/' . (string) $subcontractorId);
    }

    public function assignJob(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!Subcontractor::isAvailable() || !JobSubcontractorAssignment::isAvailable()) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $subcontractorId = (int) ($params['id'] ?? 0);
        $subcontractor = Subcontractor::findForBusiness($businessId, $subcontractorId);
        if ($subcontractor === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('subcontractors/assign_job', [
            'pageTitle' => 'Add Job',
            'subcontractor' => $subcontractor,
            'actionUrl' => url('/subs/' . (string) $subcontractorId . '/jobs'),
            'availableJobs' => JobSubcontractorAssignment::jobsAvailableForSubOut($businessId, 200),
            'errors' => [],
            'form' => [
                'job_id' => '',
                'notes' => '',
            ],
        ]);
    }

    public function storeJob(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $subcontractorId = (int) ($params['id'] ?? 0);
        if ($subcontractorId <= 0) {
            flash('error', 'Sub-contractor not found.');
            redirect('/subs');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/subs/' . (string) $subcontractorId . '/jobs/assign');
        }

        if (!Subcontractor::isAvailable() || !JobSubcontractorAssignment::isAvailable()) {
            flash('error', 'Sub-contractors are not available yet. Run the latest migrations.');
            redirect('/subs/' . (string) $subcontractorId);
        }

        $businessId = current_business_id();
        $subcontractor = Subcontractor::findForBusiness($businessId, $subcontractorId);
        if ($subcontractor === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $errors = [];
        if ($jobId <= 0) {
            $errors['job_id'] = 'Choose a job.';
        } elseif (JobSubcontractorAssignment::findForJob($businessId, $jobId) !== null) {
            $errors['job_id'] = 'That job is already subbed out.';
        }

        $form = ['job_id' => $jobId > 0 ? (string) $jobId : '', 'notes' => $notes];
        if ($errors !== []) {
            $this->render('subcontractors/assign_job', [
                'pageTitle' => 'Add Job',
                'subcontractor' => $subcontractor,
                'actionUrl' => url('/subs/' . (string) $subcontractorId . '/jobs'),
                'availableJobs' => JobSubcontractorAssignment::jobsAvailableForSubOut($businessId, 200),
                'errors' => $errors,
                'form' => $form,
            ]);
            return;
        }

        $assignmentId = JobSubcontractorAssignment::create($businessId, $jobId, [
            'subcontractor_id' => $subcontractorId,
            'status' => 'assigned',
            'notes' => $notes,
        ], (int) (auth_user_id() ?? 0));

        if ($assignmentId <= 0) {
            flash('error', 'Unable to assign this job.');
            redirect('/subs/' . (string) $subcontractorId . '/jobs/assign');
        }

        audit('job_subbed_out', 'job_subcontractor_assignments', $assignmentId, [
            'job_id' => $jobId,
            'subcontractor_id' => $subcontractorId,
        ]);
        flash('success', 'Job assigned to sub-contractor.');
        redirect('/subs/' . (string) $subcontractorId . '?tab=jobs');
    }

    private function defaultForm(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'phone' => '',
            'email' => '',
            'notes' => '',
            'status' => 'active',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'company' => trim((string) ($input['company'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'status' => strtolower(trim((string) ($input['status'] ?? 'active'))) ?: 'active',
        ];
    }

    private function formFromModel(array $subcontractor): array
    {
        return [
            'first_name' => trim((string) ($subcontractor['first_name'] ?? '')),
            'last_name' => trim((string) ($subcontractor['last_name'] ?? '')),
            'company' => trim((string) ($subcontractor['company'] ?? '')),
            'address_line1' => trim((string) ($subcontractor['address_line1'] ?? '')),
            'address_line2' => trim((string) ($subcontractor['address_line2'] ?? '')),
            'city' => trim((string) ($subcontractor['city'] ?? '')),
            'state' => trim((string) ($subcontractor['state'] ?? '')),
            'postal_code' => trim((string) ($subcontractor['postal_code'] ?? '')),
            'phone' => trim((string) ($subcontractor['phone'] ?? '')),
            'email' => trim((string) ($subcontractor['email'] ?? '')),
            'notes' => trim((string) ($subcontractor['notes'] ?? '')),
            'status' => strtolower(trim((string) ($subcontractor['status'] ?? 'active'))) ?: 'active',
        ];
    }
}
