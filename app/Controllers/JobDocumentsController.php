<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
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
        $businessId = $this->businessIdForJob($job);
        $itemTypes = JobDocument::itemTypesForBusiness($businessId);
        $defaultTaxRate = $this->businessDefaultTaxRate($businessId);

        $this->render('jobs/documents/create', [
            'pageTitle' => 'Add Job Document',
            'job' => $job,
            'document' => [
                'document_type' => $defaultType,
                'status' => 'draft',
                'tax_rate' => number_format($defaultTaxRate, 2, '.', ''),
            ],
            'types' => JobDocument::TYPES,
            'statuses' => JobDocument::STATUSES,
            'lineItems' => [$this->emptyLineItem()],
            'itemTypes' => $itemTypes,
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

        $businessId = $this->businessIdForJob($job);
        $data = $this->collectFormData($businessId, null, $this->businessDefaultTaxRate($businessId));
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
            'note' => $docTypeLabel . ' "' . $title . '" created (' . $statusLabel . '). ' . count($data['line_items']) . ' line item(s).',
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
            'lineItems' => JobDocument::lineItems($jobId, $documentId),
            'events' => JobDocument::events($jobId, $documentId),
            'statuses' => JobDocument::statusesForType((string) ($document['document_type'] ?? '')),
            'canConvertToInvoice' => strtolower((string) ($document['document_type'] ?? '')) === 'estimate'
                && !JobDocument::estimateAlreadyConverted($documentId),
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
        $lineItems = JobDocument::lineItems($jobId, $documentId);
        if (empty($lineItems) && isset($document['amount']) && (float) $document['amount'] > 0) {
            $lineItems = [[
                'item_type_id' => '',
                'item_type_label' => 'Service',
                'item_description' => (string) (($document['title'] ?? '') !== '' ? $document['title'] : 'Service'),
                'line_note' => '',
                'quantity' => '1',
                'unit_price' => (string) number_format((float) $document['amount'], 2, '.', ''),
                'is_taxable' => '1',
            ]];
        }

        $this->render('jobs/documents/edit', [
            'pageTitle' => 'Edit ' . JobDocument::typeLabel((string) ($document['document_type'] ?? 'document')),
            'job' => $job,
            'document' => $document,
            'types' => JobDocument::TYPES,
            'statuses' => JobDocument::STATUSES,
            'lineItems' => $lineItems,
            'itemTypes' => JobDocument::itemTypesForBusiness($this->businessIdForJob($job)),
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

        $data = $this->collectFormData(
            $this->businessIdForJob($job),
            $current,
            $this->businessDefaultTaxRate($this->businessIdForJob($job))
        );
        $data['document_type'] = (string) ($current['document_type'] ?? ($data['document_type'] ?? 'estimate'));
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

    public function convertToInvoice(array $params): void
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

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/jobs/' . $jobId . '/documents/' . $documentId);
        }

        $invoiceId = JobDocument::convertEstimateToInvoice($jobId, $documentId, auth_user_id());
        if ($invoiceId === null || $invoiceId <= 0) {
            flash('error', 'Estimate was already converted or cannot be converted right now.');
            redirect('/jobs/' . $jobId . '/documents/' . $documentId);
        }

        Job::createAction($jobId, [
            'action_type' => 'bill_sent',
            'action_at' => date('Y-m-d H:i:s'),
            'amount' => null,
            'ref_table' => 'job_estimate_invoices',
            'ref_id' => $invoiceId,
            'note' => 'Estimate #' . $documentId . ' converted to invoice #' . $invoiceId . '.',
        ], auth_user_id());
        log_user_action(
            'job_estimate_converted',
            'job_estimate_invoices',
            $invoiceId,
            'Converted estimate #' . $documentId . ' to invoice #' . $invoiceId . ' for job #' . $jobId . '.'
        );

        flash('success', 'Estimate converted to invoice.');
        redirect('/jobs/' . $jobId . '/documents/' . $invoiceId);
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
            'lineItems' => JobDocument::lineItems($jobId, $documentId),
            'businessLogoDataUri' => JobDocument::businessLogoDataUri($document),
        ], 'print');
    }

    private function collectFormData(int $businessId, ?array $currentDocument = null, ?float $defaultTaxRate = null): array
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

        $itemTypeLabelMap = [];
        foreach (JobDocument::itemTypesForBusiness($businessId) as $itemType) {
            $id = (int) ($itemType['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $itemTypeLabelMap[$id] = trim((string) ($itemType['item_label'] ?? ''));
        }
        $lineItems = $this->normalizeLineItems($_POST['line_items'] ?? [], $itemTypeLabelMap);
        $subtotalAmount = 0.0;
        $taxableSubtotal = 0.0;
        foreach ($lineItems as $row) {
            $lineTotal = (float) ($row['line_total'] ?? 0.0);
            $subtotalAmount += $lineTotal;
            if ((int) ($row['is_taxable'] ?? 1) === 1) {
                $taxableSubtotal += $lineTotal;
            }
        }

        $fallbackTaxRate = $defaultTaxRate ?? 0.0;
        if ($currentDocument !== null && array_key_exists('tax_rate', $currentDocument) && $currentDocument['tax_rate'] !== null) {
            $fallbackTaxRate = (float) $currentDocument['tax_rate'];
        }
        $taxRate = $this->normalizeTaxRate($_POST['tax_rate'] ?? null, $fallbackTaxRate);
        $taxAmount = round($taxableSubtotal * ($taxRate / 100), 2);
        $grossAmount = round($subtotalAmount + $taxAmount, 2);

        return [
            'document_type' => $type,
            'title' => trim((string) ($_POST['title'] ?? '')),
            'status' => $status,
            'amount' => $grossAmount,
            'subtotal_amount' => round($subtotalAmount, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'sent_at' => $sentAt,
            'approved_at' => $approvedAt,
            'paid_at' => $paidAt,
            'note' => trim((string) ($_POST['note'] ?? '')),
            'customer_note' => trim((string) ($_POST['customer_note'] ?? '')),
            'line_items' => $lineItems,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (!in_array((string) ($data['document_type'] ?? ''), JobDocument::TYPES, true)) {
            $errors[] = 'Document type is invalid.';
        }

        if (!in_array((string) ($data['status'] ?? ''), JobDocument::statusesForType((string) ($data['document_type'] ?? '')), true)) {
            $errors[] = 'Document status is invalid.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Document title is required.';
        }

        $taxRate = (float) ($data['tax_rate'] ?? 0);
        if ($taxRate < 0 || $taxRate > 100) {
            $errors[] = 'Tax rate must be between 0 and 100.';
        }

        $lineItems = is_array($data['line_items'] ?? null) ? $data['line_items'] : [];
        if (empty($lineItems)) {
            $errors[] = 'Add at least one line item.';
        }
        foreach ($lineItems as $index => $line) {
            $description = trim((string) ($line['item_description'] ?? ''));
            if ($description === '') {
                $errors[] = 'Line item #' . ($index + 1) . ' needs a description.';
            }
            $quantity = (float) ($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                $errors[] = 'Line item #' . ($index + 1) . ' quantity must be greater than zero.';
            }
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            if ($unitPrice < 0) {
                $errors[] = 'Line item #' . ($index + 1) . ' unit price cannot be negative.';
            }
            if (!isset($line['is_taxable']) || !in_array((int) $line['is_taxable'], [0, 1], true)) {
                $errors[] = 'Line item #' . ($index + 1) . ' taxable value is invalid.';
            }
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

    private function businessIdForJob(array $job): int
    {
        $businessId = isset($job['business_id']) ? (int) $job['business_id'] : 0;
        if ($businessId <= 0) {
            $businessId = current_business_id();
        }

        return max(1, $businessId);
    }

    private function normalizeLineItems(mixed $rawItems, array $itemTypeLabelMap): array
    {
        if (!is_array($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $description = trim((string) ($rawItem['item_description'] ?? ''));
            $itemTypeId = $this->toIntOrNull($rawItem['item_type_id'] ?? null);
            $itemTypeLabel = $itemTypeId !== null && isset($itemTypeLabelMap[$itemTypeId])
                ? $itemTypeLabelMap[$itemTypeId]
                : trim((string) ($rawItem['item_type_label'] ?? ''));
            if ($itemTypeLabel === '') {
                $itemTypeLabel = 'Service';
            }

            $quantity = $this->toDecimalOrNull($rawItem['quantity'] ?? null);
            if ($quantity === null || $quantity <= 0) {
                $quantity = 1.0;
            }

            $unitPrice = $this->toDecimalOrNull($rawItem['unit_price'] ?? null);
            if ($unitPrice === null || $unitPrice < 0) {
                $unitPrice = 0.0;
            }

            if ($description === '' && $unitPrice <= 0) {
                continue;
            }

            $items[] = [
                'item_type_id' => $itemTypeId,
                'item_type_label' => $itemTypeLabel,
                'item_description' => $description,
                'line_note' => trim((string) ($rawItem['line_note'] ?? '')),
                'quantity' => round((float) $quantity, 2),
                'unit_price' => round((float) $unitPrice, 2),
                'is_taxable' => (int) (($rawItem['is_taxable'] ?? 1) ? 1 : 0),
                'line_total' => round((float) $quantity * (float) $unitPrice, 2),
            ];
        }

        return $items;
    }

    private function emptyLineItem(): array
    {
        return [
            'item_type_id' => '',
            'item_type_label' => '',
            'item_description' => '',
            'line_note' => '',
            'quantity' => '1',
            'unit_price' => '',
            'is_taxable' => '1',
        ];
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    private function normalizeTaxRate(mixed $value, float $fallback): float
    {
        $parsed = $this->toDecimalOrNull($value);
        if ($parsed === null) {
            $parsed = $fallback;
        }

        if ($parsed < 0) {
            $parsed = 0.0;
        } elseif ($parsed > 100) {
            $parsed = 100.0;
        }

        return round($parsed, 4);
    }

    private function businessDefaultTaxRate(int $businessId): float
    {
        if ($businessId <= 0) {
            return 0.0;
        }

        $business = Business::findById($businessId);
        if (!is_array($business)) {
            return 0.0;
        }

        return $this->normalizeTaxRate($business['invoice_default_tax_rate'] ?? null, 0.0);
    }
}
