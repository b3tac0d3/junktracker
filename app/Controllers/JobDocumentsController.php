<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Job;
use App\Models\JobDocument;
use Core\Controller;

final class JobDocumentsController extends Controller
{
    public function create(array $params): void
    {
        require_permission('jobs', 'create');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $defaultType = trim((string) ($_GET['type'] ?? 'estimate'));
        if (!in_array($defaultType, JobDocument::TYPES, true)) {
            $defaultType = 'estimate';
        }

        $this->render('jobs/documents/create', [
            'pageTitle' => 'Add Job Document',
            'job' => $job,
            'document' => [
                'document_type' => $defaultType,
                'status' => 'draft',
            ],
            'types' => JobDocument::TYPES,
            'statuses' => JobDocument::STATUSES,
        ]);

        clear_old();
    }

    public function store(array $params): void
    {
        require_permission('jobs', 'create');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/jobs/' . $jobId . '/documents/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/documents/new');
        }

        $actorId = auth_user_id();
        $documentId = JobDocument::create($jobId, $data, $actorId);

        $docTypeLabel = JobDocument::typeLabel((string) $data['document_type']);
        $statusLabel = JobDocument::statusLabel((string) $data['status']);
        $title = trim((string) $data['title']);

        Job::createAction($jobId, [
            'action_type' => $this->jobActionTypeForStatus((string) $data['status']),
            'action_at' => date('Y-m-d H:i:s'),
            'amount' => $data['amount'],
            'ref_table' => 'job_estimate_invoices',
            'ref_id' => $documentId,
            'note' => $docTypeLabel . ' "' . $title . '" created (' . $statusLabel . ').',
        ], $actorId);

        log_user_action(
            'job_document_created',
            'job_estimate_invoices',
            $documentId,
            'Created ' . strtolower($docTypeLabel) . ' for job #' . $jobId . '.',
            'Status: ' . $statusLabel
        );

        flash('success', $docTypeLabel . ' added.');
        redirect('/jobs/' . $jobId . '/documents/' . $documentId);
    }

    public function show(array $params): void
    {
        require_permission('jobs', 'view');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $documentId = isset($params['documentId']) ? (int) $params['documentId'] : 0;
        if ($documentId <= 0) {
            redirect('/jobs/' . $jobId);
        }

        $document = JobDocument::findByIdForJob($jobId, $documentId);
        if (!$document || !empty($document['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        $this->render('jobs/documents/show', [
            'pageTitle' => JobDocument::typeLabel((string) ($document['document_type'] ?? 'document')) . ' Details',
            'job' => $job,
            'document' => $document,
            'events' => JobDocument::events($jobId, $documentId),
            'statuses' => JobDocument::statusesForType((string) ($document['document_type'] ?? '')),
        ]);
    }

    public function edit(array $params): void
    {
        require_permission('jobs', 'edit');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $documentId = isset($params['documentId']) ? (int) $params['documentId'] : 0;
        if ($documentId <= 0) {
            redirect('/jobs/' . $jobId);
        }

        $document = JobDocument::findByIdForJob($jobId, $documentId);
        if (!$document || !empty($document['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        $this->render('jobs/documents/edit', [
            'pageTitle' => 'Edit ' . JobDocument::typeLabel((string) ($document['document_type'] ?? 'document')),
            'job' => $job,
            'document' => $document,
            'types' => JobDocument::TYPES,
            'statuses' => JobDocument::STATUSES,
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        require_permission('jobs', 'edit');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $documentId = isset($params['documentId']) ? (int) $params['documentId'] : 0;
        if ($documentId <= 0) {
            redirect('/jobs/' . $jobId);
        }

        $current = JobDocument::findByIdForJob($jobId, $documentId);
        if (!$current || !empty($current['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/jobs/' . $jobId . '/documents/' . $documentId . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/jobs/' . $jobId . '/documents/' . $documentId . '/edit');
        }

        $actorId = auth_user_id();
        JobDocument::update($jobId, $documentId, $data, $actorId);

        $beforeStatus = (string) ($current['status'] ?? 'draft');
        $afterStatus = (string) ($data['status'] ?? 'draft');
        $statusChanged = strtolower($beforeStatus) !== strtolower($afterStatus);

        if ($statusChanged) {
            JobDocument::createEvent($jobId, $documentId, [
                'event_type' => 'status_changed',
                'from_status' => $beforeStatus,
                'to_status' => $afterStatus,
                'event_note' => 'Status changed from ' . JobDocument::statusLabel($beforeStatus) . ' to ' . JobDocument::statusLabel($afterStatus) . '.',
            ], $actorId);

            Job::createAction($jobId, [
                'action_type' => $this->jobActionTypeForStatus($afterStatus),
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => $data['amount'],
                'ref_table' => 'job_estimate_invoices',
                'ref_id' => $documentId,
                'note' => JobDocument::typeLabel((string) $data['document_type']) . ' status updated to ' . JobDocument::statusLabel($afterStatus) . '.',
            ], $actorId);
        } else {
            JobDocument::createEvent($jobId, $documentId, [
                'event_type' => 'updated',
                'from_status' => $beforeStatus,
                'to_status' => $afterStatus,
                'event_note' => 'Document details updated.',
            ], $actorId);
        }

        log_user_action(
            'job_document_updated',
            'job_estimate_invoices',
            $documentId,
            'Updated job document #' . $documentId . ' for job #' . $jobId . '.',
            $statusChanged
                ? ('Status: ' . JobDocument::statusLabel($beforeStatus) . ' -> ' . JobDocument::statusLabel($afterStatus))
                : 'No status change.'
        );

        flash('success', 'Document updated.');
        redirect('/jobs/' . $jobId . '/documents/' . $documentId);
    }

    public function delete(array $params): void
    {
        require_permission('jobs', 'delete');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $documentId = isset($params['documentId']) ? (int) $params['documentId'] : 0;
        if ($documentId <= 0) {
            redirect('/jobs/' . $jobId);
        }

        $document = JobDocument::findByIdForJob($jobId, $documentId);
        if (!$document || !empty($document['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/jobs/' . $jobId . '/documents/' . $documentId);
        }

        JobDocument::softDelete($jobId, $documentId, auth_user_id());
        Job::createAction($jobId, [
            'action_type' => 'billing_updated',
            'action_at' => date('Y-m-d H:i:s'),
            'amount' => null,
            'ref_table' => 'job_estimate_invoices',
            'ref_id' => $documentId,
            'note' => JobDocument::typeLabel((string) ($document['document_type'] ?? 'document')) . ' removed.',
        ], auth_user_id());

        log_user_action('job_document_deleted', 'job_estimate_invoices', $documentId, 'Deleted job document #' . $documentId . '.');

        flash('success', 'Document deleted.');
        redirect('/jobs/' . $jobId . '#estimate-invoice');
    }

    public function pdf(array $params): void
    {
        require_permission('jobs', 'view');

        $job = $this->findJobOr404($params);
        if ($job === null) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $documentId = isset($params['documentId']) ? (int) $params['documentId'] : 0;
        if ($documentId <= 0) {
            redirect('/jobs/' . $jobId);
        }

        $document = JobDocument::findByIdForJob($jobId, $documentId);
        if (!$document || !empty($document['deleted_at'])) {
            $this->renderNotFound();
            return;
        }

        $this->render('jobs/documents/pdf', [
            'pageTitle' => JobDocument::typeLabel((string) ($document['document_type'] ?? 'document')) . ' PDF',
            'job' => $job,
            'document' => $document,
        ], 'print');
    }

    private function collectFormData(): array
    {
        $type = strtolower(trim((string) ($_POST['document_type'] ?? 'estimate')));
        if (!in_array($type, JobDocument::TYPES, true)) {
            $type = 'estimate';
        }

        $status = strtolower(trim((string) ($_POST['status'] ?? 'draft')));
        if (!in_array($status, JobDocument::STATUSES, true)) {
            $status = 'draft';
        }

        $issuedAt = $this->toDateTimeOrNull($_POST['issued_at'] ?? null);
        $dueAt = $this->toDateTimeOrNull($_POST['due_at'] ?? null);
        $sentAt = $this->toDateTimeOrNull($_POST['sent_at'] ?? null);
        $approvedAt = $this->toDateTimeOrNull($_POST['approved_at'] ?? null);
        $paidAt = $this->toDateTimeOrNull($_POST['paid_at'] ?? null);

        if (in_array($status, ['quote_sent', 'invoiced'], true) && $sentAt === null) {
            $sentAt = date('Y-m-d H:i:s');
        }
        if ($status === 'approved' && $approvedAt === null) {
            $approvedAt = date('Y-m-d H:i:s');
        }
        if (in_array($status, ['paid', 'partially_paid'], true) && $paidAt === null && $status === 'paid') {
            $paidAt = date('Y-m-d H:i:s');
        }

        return [
            'document_type' => $type,
            'title' => trim((string) ($_POST['title'] ?? '')),
            'status' => $status,
            'amount' => $this->toDecimalOrNull($_POST['amount'] ?? null),
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'sent_at' => $sentAt,
            'approved_at' => $approvedAt,
            'paid_at' => $paidAt,
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (!in_array((string) ($data['document_type'] ?? ''), JobDocument::TYPES, true)) {
            $errors[] = 'Document type is invalid.';
        }

        if (!in_array((string) ($data['status'] ?? ''), JobDocument::STATUSES, true)) {
            $errors[] = 'Document status is invalid.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Document title is required.';
        }

        $amount = $data['amount'];
        if ($amount !== null && (!is_numeric($amount) || (float) $amount < 0)) {
            $errors[] = 'Amount must be a positive number.';
        }

        return $errors;
    }

    private function findJobOr404(array $params): ?array
    {
        $jobId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($jobId <= 0) {
            redirect('/jobs');
        }

        $job = Job::findById($jobId);
        if (!$job) {
            $this->renderNotFound();
            return null;
        }

        return $job;
    }

    private function toDecimalOrNull(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace([',', '$'], '', $raw);
        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function toDateTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }

    private function jobActionTypeForStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'quote_sent' => 'quote_done',
            'approved' => 'estimate_approved',
            'invoiced' => 'bill_sent',
            'partially_paid', 'paid' => 'payment',
            'void', 'cancelled' => 'billing_updated',
            default => 'billing_updated',
        };
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        if (class_exists('App\\Controllers\\ErrorController')) {
            (new ErrorController())->notFound();
            return;
        }

        echo '404 Not Found';
    }
}
