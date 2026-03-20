<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\FormSelectValue;
use App\Models\InvoiceItemType;
use App\Models\Invoice;
use Core\Controller;

final class BillingController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'date')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'desc')));
        if (!in_array($sortBy, ['date', 'id', 'client_name'], true)) {
            $sortBy = 'date';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }
        $statusOptions = $this->billingFilterStatusOptions();
        $allowedStatuses = array_values(array_filter(array_keys($statusOptions), static fn (string $key): bool => $key !== ''));
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Invoice::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $invoices = Invoice::indexList($businessId, $search, $status, $perPage, $offset, $sortBy, $sortDir);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($invoices));
        $summary = Invoice::summary($businessId);

        $this->render('billing/index', [
            'pageTitle' => 'Billing',
            'search' => $search,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'statusOptions' => $statusOptions,
            'invoices' => $invoices,
            'summary' => $summary,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $clientOptions = Invoice::clientOptions($businessId);
        $jobOptions = Invoice::jobOptions($businessId);
        $form = $this->defaultForm();
        $documentType = 'invoice';

        $requestedType = strtolower(trim((string) ($_GET['type'] ?? '')));
        if (in_array($requestedType, ['estimate', 'invoice'], true)) {
            $form['type'] = $requestedType;
            $documentType = $requestedType;
            $form['status'] = $requestedType === 'estimate'
                ? (string) (array_key_first($this->estimateStatusOptions()) ?? 'draft')
                : (string) (array_key_first($this->invoiceStatusOptions()) ?? 'unsent');
            if ($requestedType === 'estimate') {
                $form['issue_date'] = date('Y-m-d');
                $form['due_date'] = date('Y-m-d', strtotime('+30 days'));
            } else {
                $form['issue_date'] = date('Y-m-d');
                $form['due_date'] = $form['issue_date'];
            }
        }

        $requestedClientId = (int) ($_GET['client_id'] ?? 0);
        if ($requestedClientId > 0) {
            foreach ($clientOptions as $row) {
                if ((int) ($row['id'] ?? 0) === $requestedClientId) {
                    $form['client_id'] = (string) $requestedClientId;
                    break;
                }
            }
        }

        $requestedJobId = (int) ($_GET['job_id'] ?? 0);
        if ($requestedJobId > 0) {
            foreach ($jobOptions as $row) {
                if ((int) ($row['id'] ?? 0) === $requestedJobId) {
                    $form['job_id'] = (string) $requestedJobId;
                    break;
                }
            }
        }

        $sourceEstimateId = (int) ($_GET['from_estimate_id'] ?? 0);
        if ($sourceEstimateId > 0) {
            $sourceEstimate = Invoice::findForBusiness($businessId, $sourceEstimateId);
            if ($sourceEstimate === null) {
                flash('error', 'Estimate not found.');
                redirect('/billing');
            }

            $sourceType = strtolower(trim((string) ($sourceEstimate['type'] ?? 'invoice')));
            if ($sourceType !== 'estimate') {
                flash('error', 'Only estimates can be converted to invoices.');
                redirect('/billing/' . (string) $sourceEstimateId);
            }

            $form = $this->formFromModel($sourceEstimate, Invoice::lineItems($businessId, $sourceEstimateId));
            $form['type'] = 'invoice';
            $form['status'] = (string) (array_key_first($this->invoiceStatusOptions()) ?? 'unsent');
            $form['invoice_number'] = '';
            $form['issue_date'] = date('Y-m-d');
            $form['due_date'] = $form['issue_date'];
            $documentType = 'invoice';
        }

        $this->render('billing/form', [
            'pageTitle' => $documentType === 'estimate' ? 'Add Estimate' : 'Add Invoice',
            'mode' => 'create',
            'actionUrl' => url('/billing'),
            'form' => $form,
            'errors' => [],
            'clientOptions' => $clientOptions,
            'jobOptions' => $jobOptions,
            'invoiceItemTypes' => InvoiceItemType::activeOptions($businessId),
            'documentType' => $documentType,
            'estimateStatusOptions' => $this->estimateStatusOptions(),
            'invoiceStatusOptions' => $this->invoiceStatusOptions(),
            'paymentCategoryOptions' => $this->paymentCategoryOptions(),
            'paymentTypeOptions' => $this->paymentTypeOptions(),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/create');
        }

        $businessId = current_business_id();
        $clientOptions = Invoice::clientOptions($businessId);
        $jobOptions = Invoice::jobOptions($businessId);
        $invoiceItemTypes = InvoiceItemType::activeOptions($businessId);
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $clientOptions, $jobOptions, $invoiceItemTypes);
        if ($errors !== []) {
            $this->render('billing/form', [
                'pageTitle' => $form['type'] === 'estimate' ? 'Add Estimate' : 'Add Invoice',
                'mode' => 'create',
                'actionUrl' => url('/billing'),
                'form' => $form,
                'errors' => $errors,
                'clientOptions' => $clientOptions,
                'jobOptions' => $jobOptions,
                'invoiceItemTypes' => $invoiceItemTypes,
                'documentType' => $form['type'],
                'estimateStatusOptions' => $this->estimateStatusOptions(),
                'invoiceStatusOptions' => $this->invoiceStatusOptions(),
                'paymentCategoryOptions' => $this->paymentCategoryOptions(),
                'paymentTypeOptions' => $this->paymentTypeOptions(),
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $invoiceId = Invoice::create($businessId, $this->payloadForSave($form), $actorUserId);
        Invoice::replaceLineItems($businessId, $invoiceId, $form['items'], $actorUserId);
        if ($form['type'] === 'invoice') {
            $syncJobId = (int) ($form['job_id'] ?? 0);
            if ($this->hasInlinePayment($form)) {
                Invoice::createPayment($businessId, $this->inlinePaymentPayloadForSave($form, $invoiceId), $actorUserId);
            }
            if ($syncJobId > 0) {
                Invoice::syncInvoicePaymentStatusesForJob($businessId, $syncJobId, $actorUserId);
            }
        }
        flash('success', ucfirst($form['type']) . ' created.');
        redirect('/billing/' . (string) $invoiceId . $this->billingBackSuffix($_POST));
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $invoiceId = (int) ($params['id'] ?? 0);
        if ($invoiceId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('billing/form', [
            'pageTitle' => strtolower((string) ($invoice['type'] ?? 'invoice')) === 'estimate' ? 'Edit Estimate' : 'Edit Invoice',
            'mode' => 'edit',
            'actionUrl' => url('/billing/' . (string) $invoiceId . '/update'),
            'form' => $this->formFromModel($invoice, Invoice::lineItems($businessId, $invoiceId)),
            'errors' => [],
            'clientOptions' => Invoice::clientOptions($businessId),
            'jobOptions' => Invoice::jobOptions($businessId),
            'invoiceItemTypes' => InvoiceItemType::activeOptions($businessId),
            'invoiceId' => $invoiceId,
            'documentType' => strtolower((string) ($invoice['type'] ?? 'invoice')) === 'estimate' ? 'estimate' : 'invoice',
            'estimateStatusOptions' => $this->estimateStatusOptions(),
            'invoiceStatusOptions' => $this->invoiceStatusOptions(),
            'paymentCategoryOptions' => $this->paymentCategoryOptions(),
            'paymentTypeOptions' => $this->paymentTypeOptions(),
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $invoiceId = (int) ($params['id'] ?? 0);
        if ($invoiceId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $backSuffix = $this->billingBackSuffix($_POST);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/' . (string) $invoiceId . '/edit' . $backSuffix);
        }

        $businessId = current_business_id();
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $clientOptions = Invoice::clientOptions($businessId);
        $jobOptions = Invoice::jobOptions($businessId);
        $invoiceItemTypes = InvoiceItemType::activeOptions($businessId);
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $clientOptions, $jobOptions, $invoiceItemTypes);
        if ($errors !== []) {
            $this->render('billing/form', [
                'pageTitle' => $form['type'] === 'estimate' ? 'Edit Estimate' : 'Edit Invoice',
                'mode' => 'edit',
                'actionUrl' => url('/billing/' . (string) $invoiceId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'clientOptions' => $clientOptions,
                'jobOptions' => $jobOptions,
                'invoiceItemTypes' => $invoiceItemTypes,
                'invoiceId' => $invoiceId,
                'documentType' => $form['type'],
                'estimateStatusOptions' => $this->estimateStatusOptions(),
                'invoiceStatusOptions' => $this->invoiceStatusOptions(),
                'paymentCategoryOptions' => $this->paymentCategoryOptions(),
                'paymentTypeOptions' => $this->paymentTypeOptions(),
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $previousType = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
        $previousJobId = max(0, (int) ($invoice['job_id'] ?? 0));
        Invoice::update($businessId, $invoiceId, $this->payloadForSave($form), $actorUserId);
        Invoice::replaceLineItems($businessId, $invoiceId, $form['items'], $actorUserId);
        if ($form['type'] === 'invoice' && $this->hasInlinePayment($form)) {
            Invoice::createPayment($businessId, $this->inlinePaymentPayloadForSave($form, $invoiceId), $actorUserId);
        }
        if ($previousType === 'invoice' && $previousJobId > 0) {
            Invoice::syncInvoicePaymentStatusesForJob($businessId, $previousJobId, $actorUserId);
        }
        if ($form['type'] === 'invoice') {
            $currentJobId = (int) ($form['job_id'] ?? 0);
            if ($currentJobId > 0 && ($previousType !== 'invoice' || $currentJobId !== $previousJobId)) {
                Invoice::syncInvoicePaymentStatusesForJob($businessId, $currentJobId, $actorUserId);
            }
        }
        flash('success', ucfirst($form['type']) . ' updated.');
        redirect('/billing/' . (string) $invoiceId . $backSuffix);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $invoiceId = (int) ($params['id'] ?? 0);
        if ($invoiceId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $items = Invoice::lineItems($businessId, $invoiceId);
        $payments = Invoice::payments($businessId, $invoiceId);
        $business = Business::findById($businessId);

        $this->render('billing/show', [
            'pageTitle' => 'Billing Record',
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments,
            'business' => $business,
            'quickStatusOptions' => strtolower(trim((string) ($invoice['type'] ?? 'invoice'))) === 'estimate'
                ? $this->estimateStatusOptions()
                : $this->invoiceStatusOptions(),
        ]);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $invoiceId = (int) ($params['id'] ?? 0);
        if ($invoiceId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/' . (string) $invoiceId);
        }

        $businessId = current_business_id();
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $jobId = max(0, (int) ($_POST['job_id'] ?? 0));
        if ($jobId <= 0) {
            $jobId = max(0, (int) ($invoice['job_id'] ?? 0));
        }
        $from = strtolower(trim((string) ($_POST['from'] ?? '')));
        $actorUserId = (int) (auth_user_id() ?? 0);
        $deleted = Invoice::softDelete($businessId, $invoiceId, $actorUserId);
        if ($deleted && strtolower(trim((string) ($invoice['type'] ?? 'invoice'))) === 'invoice' && $jobId > 0) {
            Invoice::syncInvoicePaymentStatusesForJob($businessId, $jobId, $actorUserId);
        }

        if ($deleted) {
            flash('success', 'Record deleted.');
        } else {
            flash('error', 'Unable to delete record.');
        }

        if ($from === 'job' && $jobId > 0) {
            redirect('/jobs/' . (string) $jobId);
        }
        redirect('/billing');
    }

    public function quickStatus(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $invoiceId = (int) ($params['id'] ?? 0);
        if ($invoiceId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $backSuffix = $this->billingBackSuffix($_POST);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/' . (string) $invoiceId . $backSuffix);
        }

        $businessId = current_business_id();
        $invoice = Invoice::findForBusiness($businessId, $invoiceId);
        if ($invoice === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $type = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }

        $status = $this->normalizeOptionKey((string) ($_POST['status'] ?? ''));
        $allowedStatuses = $type === 'estimate'
            ? array_keys($this->estimateStatusOptions())
            : array_keys($this->invoiceStatusOptions());
        if (!in_array($status, $allowedStatuses, true)) {
            flash('error', 'Choose a valid status.');
            redirect('/billing/' . (string) $invoiceId . $backSuffix);
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $updated = Invoice::updateStatus($businessId, $invoiceId, $type, $status, $actorUserId);
        if ($updated && $type === 'invoice') {
            $jobId = max(0, (int) ($invoice['job_id'] ?? 0));
            if ($jobId > 0) {
                Invoice::syncInvoicePaymentStatusesForJob($businessId, $jobId, $actorUserId);
            }
        }

        if ($updated) {
            flash('success', 'Status updated.');
        } else {
            flash('error', 'Unable to update status.');
        }

        redirect('/billing/' . (string) $invoiceId . $backSuffix);
    }

    public function createPayment(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $jobId = max(0, (int) ($_GET['job_id'] ?? 0));
        $invoiceId = max(0, (int) ($_GET['invoice_id'] ?? 0));

        if ($invoiceId > 0) {
            $seedInvoice = Invoice::findForBusiness($businessId, $invoiceId);
            if ($seedInvoice !== null && $jobId <= 0) {
                $jobId = max(0, (int) ($seedInvoice['job_id'] ?? 0));
            }
        }

        $invoiceOptions = Invoice::paymentInvoiceOptions($businessId, $jobId);

        if ($invoiceId <= 0 && count($invoiceOptions) === 1) {
            $invoiceId = (int) ($invoiceOptions[0]['id'] ?? 0);
        }

        $defaultAmount = '';
        if ($invoiceId > 0) {
            $defaultAmount = number_format(Invoice::remainingBalanceForInvoice($businessId, $invoiceId), 2, '.', '');
        }

        $this->render('billing/payment_form', [
            'pageTitle' => 'Add Payment',
            'mode' => 'create',
            'actionUrl' => url('/billing/payments'),
            'form' => $this->defaultPaymentForm($invoiceId, $defaultAmount),
            'errors' => [],
            'invoiceOptions' => $invoiceOptions,
            'paymentCategoryOptions' => $this->paymentCategoryOptions(),
            'paymentTypeOptions' => $this->paymentTypeOptions(),
            'backUrl' => $this->paymentBackUrl($jobId, $invoiceId),
            'backLabel' => $this->paymentBackLabel($jobId, $invoiceId),
            'returnJobId' => $jobId,
            'returnInvoiceId' => $invoiceId > 0 ? $invoiceId : 0,
        ]);
    }

    public function storePayment(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/payments/create');
        }

        $businessId = current_business_id();
        $returnJobId = max(0, (int) ($_POST['return_job_id'] ?? 0));
        $returnInvoiceId = max(0, (int) ($_POST['return_invoice_id'] ?? 0));
        if ($returnJobId <= 0 && $returnInvoiceId > 0) {
            $seedInvoice = Invoice::findForBusiness($businessId, $returnInvoiceId);
            if ($seedInvoice !== null) {
                $returnJobId = max(0, (int) ($seedInvoice['job_id'] ?? 0));
            }
        }

        $form = $this->paymentFormFromPost($_POST);
        $invoiceOptions = Invoice::paymentInvoiceOptions($businessId, $returnJobId);

        $errors = $this->validatePaymentForm($businessId, $form, $invoiceOptions);
        if ($errors !== []) {
            $this->render('billing/payment_form', [
                'pageTitle' => 'Add Payment',
                'mode' => 'create',
                'actionUrl' => url('/billing/payments'),
                'form' => $form,
                'errors' => $errors,
                'invoiceOptions' => $invoiceOptions,
                'paymentCategoryOptions' => $this->paymentCategoryOptions(),
                'paymentTypeOptions' => $this->paymentTypeOptions(),
                'backUrl' => $this->paymentBackUrl($returnJobId, $returnInvoiceId),
                'backLabel' => $this->paymentBackLabel($returnJobId, $returnInvoiceId),
                'returnJobId' => $returnJobId,
                'returnInvoiceId' => $returnInvoiceId,
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $paymentId = Invoice::createPayment($businessId, $this->paymentPayloadForSave($form), $actorUserId);
        $invoice = Invoice::findForBusiness($businessId, (int) ($form['invoice_id'] ?? 0));
        $syncJobId = max(0, (int) ($invoice['job_id'] ?? 0));
        if ($syncJobId > 0) {
            Invoice::syncInvoicePaymentStatusesForJob($businessId, $syncJobId, $actorUserId);
        }
        flash('success', 'Payment added.');

        $redirect = '/billing/payments/' . (string) $paymentId;
        $query = [];
        if ($returnJobId > 0) {
            $query['job_id'] = (string) $returnJobId;
        }
        if ($returnInvoiceId > 0) {
            $query['invoice_id'] = (string) $returnInvoiceId;
        }
        if ($query !== []) {
            $redirect .= '?' . http_build_query($query);
        }
        redirect($redirect);
    }

    public function showPayment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $paymentId = (int) ($params['id'] ?? 0);
        if ($paymentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $payment = Invoice::findPaymentForBusiness($businessId, $paymentId);
        if ($payment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $jobId = max(0, (int) ($_GET['job_id'] ?? 0));
        $invoiceId = max(0, (int) ($_GET['invoice_id'] ?? 0));
        if ($invoiceId <= 0) {
            $invoiceId = (int) ($payment['invoice_id'] ?? 0);
        }

        $this->render('billing/payment_show', [
            'pageTitle' => 'Payment',
            'payment' => $payment,
            'backUrl' => $this->paymentBackUrl($jobId, $invoiceId),
            'backLabel' => $this->paymentBackLabel($jobId, $invoiceId),
            'jobId' => $jobId,
            'invoiceId' => $invoiceId,
        ]);
    }

    public function deletePayment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $paymentId = (int) ($params['id'] ?? 0);
        if ($paymentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/payments/' . (string) $paymentId);
        }

        $businessId = current_business_id();
        $payment = Invoice::findPaymentForBusiness($businessId, $paymentId);
        if ($payment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $jobId = max(0, (int) ($_POST['return_job_id'] ?? 0));
        if ($jobId <= 0) {
            $jobId = max(0, (int) ($payment['job_id'] ?? 0));
        }
        $invoiceId = max(0, (int) ($_POST['return_invoice_id'] ?? 0));
        if ($invoiceId <= 0) {
            $invoiceId = (int) ($payment['invoice_id'] ?? 0);
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $deleted = Invoice::softDeletePayment($businessId, $paymentId, $actorUserId);
        if ($deleted && $jobId > 0) {
            Invoice::syncInvoicePaymentStatusesForJob($businessId, $jobId, $actorUserId);
        }

        if ($deleted) {
            flash('success', 'Payment deleted.');
        } else {
            flash('error', 'Unable to delete payment.');
        }

        redirect($this->paymentBackUrl($jobId, $invoiceId));
    }

    public function editPayment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $paymentId = (int) ($params['id'] ?? 0);
        if ($paymentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $payment = Invoice::findPaymentForBusiness($businessId, $paymentId);
        if ($payment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $jobId = max(0, (int) ($_GET['job_id'] ?? 0));
        $invoiceId = max(0, (int) ($_GET['invoice_id'] ?? 0));
        if ($invoiceId <= 0) {
            $invoiceId = (int) ($payment['invoice_id'] ?? 0);
        }
        if ($jobId <= 0) {
            $jobId = max(0, (int) ($payment['job_id'] ?? 0));
        }

        $invoiceOptions = Invoice::paymentInvoiceOptions($businessId, $jobId);

        $this->render('billing/payment_form', [
            'pageTitle' => 'Edit Payment',
            'mode' => 'edit',
            'actionUrl' => url('/billing/payments/' . (string) $paymentId . '/update'),
            'form' => $this->paymentFormFromModel($payment),
            'errors' => [],
            'invoiceOptions' => $invoiceOptions,
            'paymentCategoryOptions' => $this->paymentCategoryOptions(),
            'paymentTypeOptions' => $this->paymentTypeOptions(),
            'backUrl' => $this->paymentBackUrl($jobId, $invoiceId),
            'backLabel' => $this->paymentBackLabel($jobId, $invoiceId),
            'returnJobId' => $jobId,
            'returnInvoiceId' => $invoiceId,
        ]);
    }

    public function updatePayment(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $paymentId = (int) ($params['id'] ?? 0);
        if ($paymentId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/billing/payments/' . (string) $paymentId . '/edit');
        }

        $businessId = current_business_id();
        $payment = Invoice::findPaymentForBusiness($businessId, $paymentId);
        if ($payment === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $returnJobId = max(0, (int) ($_POST['return_job_id'] ?? 0));
        $returnInvoiceId = max(0, (int) ($_POST['return_invoice_id'] ?? 0));
        if ($returnJobId <= 0) {
            $returnJobId = max(0, (int) ($payment['job_id'] ?? 0));
        }
        if ($returnInvoiceId <= 0) {
            $returnInvoiceId = (int) ($payment['invoice_id'] ?? 0);
        }

        $form = $this->paymentFormFromPost($_POST);
        $invoiceOptions = Invoice::paymentInvoiceOptions($businessId, $returnJobId);

        $errors = $this->validatePaymentForm($businessId, $form, $invoiceOptions);
        if ($errors !== []) {
            $this->render('billing/payment_form', [
                'pageTitle' => 'Edit Payment',
                'mode' => 'edit',
                'actionUrl' => url('/billing/payments/' . (string) $paymentId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'invoiceOptions' => $invoiceOptions,
                'paymentCategoryOptions' => $this->paymentCategoryOptions(),
                'paymentTypeOptions' => $this->paymentTypeOptions(),
                'backUrl' => $this->paymentBackUrl($returnJobId, $returnInvoiceId),
                'backLabel' => $this->paymentBackLabel($returnJobId, $returnInvoiceId),
                'returnJobId' => $returnJobId,
                'returnInvoiceId' => $returnInvoiceId,
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        Invoice::updatePayment($businessId, $paymentId, $this->paymentPayloadForSave($form), $actorUserId);
        $oldJobId = max(0, (int) ($payment['job_id'] ?? 0));
        if ($oldJobId > 0) {
            Invoice::syncInvoicePaymentStatusesForJob($businessId, $oldJobId, $actorUserId);
        }
        $invoice = Invoice::findForBusiness($businessId, (int) ($form['invoice_id'] ?? 0));
        $newJobId = max(0, (int) ($invoice['job_id'] ?? 0));
        if ($newJobId > 0 && $newJobId !== $oldJobId) {
            Invoice::syncInvoicePaymentStatusesForJob($businessId, $newJobId, $actorUserId);
        }
        flash('success', 'Payment updated.');

        $redirect = '/billing/payments/' . (string) $paymentId;
        $query = [];
        if ($returnJobId > 0) {
            $query['job_id'] = (string) $returnJobId;
        }
        if ($returnInvoiceId > 0) {
            $query['invoice_id'] = (string) $returnInvoiceId;
        }
        if ($query !== []) {
            $redirect .= '?' . http_build_query($query);
        }
        redirect($redirect);
    }

    private function defaultForm(): array
    {
        $invoiceStatuses = array_keys($this->invoiceStatusOptions());
        $today = date('Y-m-d');
        return [
            'type' => 'invoice',
            'status' => (string) ($invoiceStatuses[0] ?? 'unsent'),
            'invoice_number' => '',
            'client_id' => '',
            'job_id' => '',
            'issue_date' => $today,
            'due_date' => $today,
            'subtotal' => '0.00',
            'tax_rate' => '0',
            'tax_amount' => '0.00',
            'total' => '0.00',
            'customer_note' => '',
            'internal_note' => '',
            'payment_paid_date' => $today,
            'payment_type' => 'payment',
            'payment_method' => 'cash',
            'payment_reference_number' => '',
            'payment_amount' => '',
            'payment_note' => '',
            'items' => [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function estimateStatusOptions(): array
    {
        $fallback = ['draft', 'sent', 'approved', 'declined'];
        return $this->optionLabelMap($this->selectValueList('estimate_status', $fallback));
    }

    /**
     * @return array<string, string>
     */
    private function invoiceStatusOptions(): array
    {
        $fallback = ['unsent', 'sent', 'partially_paid', 'paid_in_full'];
        return $this->optionLabelMap($this->selectValueList('invoice_status', $fallback));
    }

    /**
     * @return array<string, string>
     */
    private function billingFilterStatusOptions(): array
    {
        $options = ['' => 'All'];
        foreach ($this->invoiceStatusOptions() as $value => $label) {
            $options[$value] = $label;
        }
        foreach ($this->estimateStatusOptions() as $value => $label) {
            $options[$value] = $label;
        }
        return $options;
    }

    private function paymentTypeOptions(): array
    {
        $fallback = [
            'check' => 'Check',
            'cc' => 'CC',
            'cash' => 'Cash',
            'venmo' => 'Venmo',
            'cashapp' => 'Cashapp',
            'other' => 'Other',
        ];

        return $this->selectValueMap('payment_method', $fallback);
    }

    private function paymentCategoryOptions(): array
    {
        $fallback = [
            'deposit' => 'Deposit',
            'payment' => 'Payment',
        ];

        return $this->selectValueMap('payment_type', $fallback);
    }

    private function defaultPaymentForm(int $invoiceId = 0, string $amount = ''): array
    {
        $normalizedAmount = trim($amount);
        return [
            'invoice_id' => $invoiceId > 0 ? (string) $invoiceId : '',
            'paid_date' => date('Y-m-d'),
            'payment_type' => 'payment',
            'method' => 'cash',
            'reference_number' => '',
            'amount' => $normalizedAmount,
            'note' => '',
        ];
    }

    private function paymentFormFromModel(array $payment): array
    {
        $paidDate = trim((string) ($payment['paid_at'] ?? ''));
        $stamp = $paidDate !== '' ? strtotime($paidDate) : false;
        if ($stamp !== false) {
            $paidDate = date('Y-m-d', $stamp);
        }

        return [
            'invoice_id' => (string) ((int) ($payment['invoice_id'] ?? 0)),
            'paid_date' => $paidDate,
            'payment_type' => $this->normalizeOptionKey((string) ($payment['payment_type'] ?? 'payment')),
            'method' => $this->normalizeOptionKey((string) ($payment['method'] ?? 'cash')),
            'reference_number' => trim((string) ($payment['reference_number'] ?? '')),
            'amount' => number_format((float) ($payment['amount'] ?? 0), 2, '.', ''),
            'note' => trim((string) ($payment['note'] ?? '')),
        ];
    }

    private function paymentFormFromPost(array $input): array
    {
        return [
            'invoice_id' => trim((string) ($input['invoice_id'] ?? '')),
            'paid_date' => trim((string) ($input['paid_date'] ?? '')),
            'payment_type' => $this->normalizeOptionKey((string) ($input['payment_type'] ?? 'payment')),
            'method' => $this->normalizeOptionKey((string) ($input['method'] ?? 'cash')),
            'reference_number' => trim((string) ($input['reference_number'] ?? '')),
            'amount' => trim((string) ($input['amount'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
        ];
    }

    private function hasInlinePayment(array $form): bool
    {
        $amountRaw = trim((string) ($form['payment_amount'] ?? ''));
        if ($amountRaw === '' || !is_numeric($amountRaw)) {
            return false;
        }

        return (float) $amountRaw > 0;
    }

    private function inlinePaymentPayloadForSave(array $form, int $invoiceId): array
    {
        $paidDate = trim((string) ($form['payment_paid_date'] ?? ''));
        return [
            'invoice_id' => $invoiceId,
            'paid_at' => $paidDate !== '' ? ($paidDate . ' 12:00:00') : null,
            'payment_type' => $this->normalizeOptionKey((string) ($form['payment_type'] ?? 'payment')),
            'method' => $this->normalizeOptionKey((string) ($form['payment_method'] ?? 'cash')),
            'reference_number' => trim((string) ($form['payment_reference_number'] ?? '')),
            'amount' => max(0.0, (float) ($form['payment_amount'] ?? 0)),
            'note' => trim((string) ($form['payment_note'] ?? '')),
        ];
    }

    private function validatePaymentForm(int $businessId, array $form, array $invoiceOptions): array
    {
        $errors = [];

        $invoiceId = (int) ($form['invoice_id'] ?? 0);
        $invoiceIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $invoiceOptions);
        if ($invoiceId <= 0 || !in_array($invoiceId, $invoiceIds, true)) {
            $errors['invoice_id'] = 'Choose a valid invoice.';
        } else {
            $invoice = Invoice::findForBusiness($businessId, $invoiceId);
            if ($invoice === null || strtolower(trim((string) ($invoice['type'] ?? 'invoice'))) !== 'invoice') {
                $errors['invoice_id'] = 'Payments can only be linked to invoices.';
            }
        }

        if (!$this->isValidDate($form['paid_date'] ?? '')) {
            $errors['paid_date'] = 'Enter a valid payment date.';
        }

        if (!array_key_exists((string) ($form['payment_type'] ?? ''), $this->paymentCategoryOptions())) {
            $errors['payment_type'] = 'Choose a valid payment category.';
        }

        if (!array_key_exists((string) ($form['method'] ?? ''), $this->paymentTypeOptions())) {
            $errors['method'] = 'Choose a valid payment type.';
        }

        if (!is_numeric($form['amount'] ?? '') || (float) ($form['amount'] ?? 0) <= 0) {
            $errors['amount'] = 'Amount must be greater than zero.';
        }

        if (strlen((string) ($form['reference_number'] ?? '')) > 120) {
            $errors['reference_number'] = 'Reference number is too long.';
        }
        if (strlen((string) ($form['note'] ?? '')) > 255) {
            $errors['note'] = 'Note is too long.';
        }

        return $errors;
    }

    private function paymentPayloadForSave(array $form): array
    {
        $paidDate = trim((string) ($form['paid_date'] ?? ''));
        return [
            'invoice_id' => (int) ($form['invoice_id'] ?? 0),
            'paid_at' => $paidDate !== '' ? ($paidDate . ' 12:00:00') : null,
            'payment_type' => $this->normalizeOptionKey((string) ($form['payment_type'] ?? 'payment')),
            'method' => $this->normalizeOptionKey((string) ($form['method'] ?? 'cash')),
            'reference_number' => trim((string) ($form['reference_number'] ?? '')),
            'amount' => max(0.0, (float) ($form['amount'] ?? 0)),
            'note' => trim((string) ($form['note'] ?? '')),
        ];
    }

    private function paymentBackUrl(int $jobId, int $invoiceId): string
    {
        if ($jobId > 0) {
            return url('/jobs/' . (string) $jobId);
        }
        if ($invoiceId > 0) {
            return url('/billing/' . (string) $invoiceId);
        }
        return url('/billing');
    }

    private function paymentBackLabel(int $jobId, int $invoiceId): string
    {
        if ($jobId > 0) {
            return 'Back to Job';
        }
        if ($invoiceId > 0) {
            return 'Back to Invoice';
        }
        return 'Back to Billing';
    }

    private function billingBackSuffix(array $input): string
    {
        $from = strtolower(trim((string) ($input['from'] ?? '')));
        $jobId = max(0, (int) ($input['job_id'] ?? 0));
        if ($from === 'job' && $jobId > 0) {
            return '?from=job&job_id=' . (string) $jobId;
        }
        return '';
    }

    /**
     * @param array<string, string> $fallback
     * @return array<string, string>
     */
    private function selectValueMap(string $sectionKey, array $fallback): array
    {
        $businessId = current_business_id();
        $options = FormSelectValue::optionsForSection($businessId, $sectionKey);
        if ($options === []) {
            return $fallback;
        }

        $map = [];
        foreach ($options as $optionRaw) {
            $value = $this->normalizeOptionKey((string) $optionRaw);
            if ($value === '') {
                continue;
            }
            $map[$value] = ucwords(str_replace('_', ' ', $value));
        }

        return $map !== [] ? $map : $fallback;
    }

    /**
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function selectValueList(string $sectionKey, array $fallback): array
    {
        $businessId = current_business_id();
        $options = FormSelectValue::optionsForSection($businessId, $sectionKey);
        if ($options === []) {
            return $fallback;
        }

        $normalized = [];
        foreach ($options as $optionRaw) {
            $value = $this->normalizeOptionKey((string) $optionRaw);
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized !== [] ? $normalized : $fallback;
    }

    /**
     * @param array<int, string> $values
     * @return array<string, string>
     */
    private function optionLabelMap(array $values): array
    {
        $map = [];
        foreach ($values as $valueRaw) {
            $value = $this->normalizeOptionKey((string) $valueRaw);
            if ($value === '' || array_key_exists($value, $map)) {
                continue;
            }
            $map[$value] = ucwords(str_replace('_', ' ', $value));
        }

        return $map;
    }

    private function formFromModel(array $invoice, array $lineItems = []): array
    {
        $type = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }

        $status = strtolower(trim((string) ($invoice['status'] ?? 'draft')));
        $estimateStatuses = array_keys($this->estimateStatusOptions());
        $invoiceStatuses = array_keys($this->invoiceStatusOptions());
        if ($type === 'estimate') {
            if (!in_array($status, $estimateStatuses, true)) {
                $status = (string) ($estimateStatuses[0] ?? 'draft');
            }
        } else {
            if ($status === 'draft') {
                $status = 'unsent';
            } elseif ($status === 'partial') {
                $status = 'partially_paid';
            } elseif ($status === 'paid') {
                $status = 'paid_in_full';
            }
            if (!in_array($status, $invoiceStatuses, true)) {
                $status = (string) ($invoiceStatuses[0] ?? 'unsent');
            }
        }

        $normalizedItems = [];
        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['item_type'] ?? $item['description'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalizedItems[] = [
                'name' => $name,
                'note' => trim((string) ($item['note'] ?? '')),
                'quantity' => number_format((float) ($item['quantity'] ?? 1), 2, '.', ''),
                'rate' => number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
                'taxable' => ((int) ($item['taxable'] ?? 0)) === 1 ? '1' : '0',
            ];
        }

        return [
            'type' => $type,
            'status' => $status,
            'invoice_number' => trim((string) ($invoice['invoice_number'] ?? '')),
            'client_id' => (string) ((int) ($invoice['client_id'] ?? 0)),
            'job_id' => (string) ((int) ($invoice['job_id'] ?? 0)),
            'issue_date' => trim((string) ($invoice['issue_date'] ?? '')),
            'due_date' => trim((string) ($invoice['due_date'] ?? '')),
            'subtotal' => number_format((float) ($invoice['subtotal'] ?? 0), 2, '.', ''),
            'tax_rate' => (string) ($invoice['tax_rate'] ?? '0'),
            'tax_amount' => number_format((float) ($invoice['tax_amount'] ?? 0), 2, '.', ''),
            'total' => number_format((float) ($invoice['total'] ?? 0), 2, '.', ''),
            'customer_note' => trim((string) ($invoice['customer_note'] ?? '')),
            'internal_note' => trim((string) ($invoice['internal_note'] ?? '')),
            'payment_paid_date' => date('Y-m-d'),
            'payment_type' => 'payment',
            'payment_method' => 'cash',
            'payment_reference_number' => '',
            'payment_amount' => '',
            'payment_note' => '',
            'items' => $normalizedItems,
        ];
    }

    private function formFromPost(array $input): array
    {
        $type = strtolower(trim((string) ($input['type'] ?? 'invoice')));
        if (!in_array($type, ['estimate', 'invoice'], true)) {
            $type = 'invoice';
        }

        $itemsInput = $input['items'] ?? [];
        $items = [];
        if (is_array($itemsInput)) {
            foreach ($itemsInput as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['name'] ?? ''));
                $note = trim((string) ($item['note'] ?? ''));
                $quantity = trim((string) ($item['quantity'] ?? ''));
                $rate = trim((string) ($item['rate'] ?? ''));
                $taxable = ((string) ($item['taxable'] ?? '0')) === '1' ? '1' : '0';

                if ($name === '' && $note === '' && $quantity === '' && $rate === '') {
                    continue;
                }

                $items[] = [
                    'name' => $name,
                    'note' => $note,
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'taxable' => $taxable,
                ];
            }
        }

        $defaultStatus = $type === 'estimate'
            ? ((string) (array_key_first($this->estimateStatusOptions()) ?? 'draft'))
            : ((string) (array_key_first($this->invoiceStatusOptions()) ?? 'unsent'));

        return [
            'type' => $type,
            'status' => $this->normalizeOptionKey((string) ($input['status'] ?? $defaultStatus)),
            'invoice_number' => trim((string) ($input['invoice_number'] ?? '')),
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'job_id' => trim((string) ($input['job_id'] ?? '')),
            'issue_date' => trim((string) ($input['issue_date'] ?? '')),
            'due_date' => trim((string) ($input['due_date'] ?? '')),
            'subtotal' => trim((string) ($input['subtotal'] ?? '0')),
            'tax_rate' => trim((string) ($input['tax_rate'] ?? '0')),
            'tax_amount' => trim((string) ($input['tax_amount'] ?? '0')),
            'total' => trim((string) ($input['total'] ?? '0')),
            'customer_note' => trim((string) ($input['customer_note'] ?? '')),
            'internal_note' => trim((string) ($input['internal_note'] ?? '')),
            'payment_paid_date' => trim((string) ($input['payment_paid_date'] ?? date('Y-m-d'))),
            'payment_type' => $this->normalizeOptionKey((string) ($input['payment_type'] ?? 'payment')),
            'payment_method' => $this->normalizeOptionKey((string) ($input['payment_method'] ?? 'cash')),
            'payment_reference_number' => trim((string) ($input['payment_reference_number'] ?? '')),
            'payment_amount' => trim((string) ($input['payment_amount'] ?? '')),
            'payment_note' => trim((string) ($input['payment_note'] ?? '')),
            'items' => $items,
        ];
    }

    private function validateForm(array $form, array $clientOptions, array $jobOptions, array $invoiceItemTypes = []): array
    {
        $errors = [];
        $allowedTypes = ['estimate', 'invoice'];
        $allowedStatusesByType = [
            'estimate' => array_keys($this->estimateStatusOptions()),
            'invoice' => array_keys($this->invoiceStatusOptions()),
        ];
        $clientIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $clientOptions);
        $jobIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $jobOptions);

        if (!in_array($form['type'], $allowedTypes, true)) {
            $errors['type'] = 'Choose a valid type.';
        }

        $allowedStatuses = $allowedStatusesByType[$form['type']] ?? [];
        if (!in_array($form['status'], $allowedStatuses, true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        $clientId = (int) $form['client_id'];
        if ($clientId <= 0 || !in_array($clientId, $clientIds, true)) {
            $errors['client_id'] = 'Choose a valid client.';
        }

        $jobId = (int) $form['job_id'];
        if ($jobId <= 0 || !in_array($jobId, $jobIds, true)) {
            $errors['job_id'] = 'This record must be linked to a valid job.';
        }

        if ($form['issue_date'] !== '' && !$this->isValidDate($form['issue_date'])) {
            $errors['issue_date'] = 'Enter a valid issue date.';
        }

        if ($form['due_date'] !== '' && !$this->isValidDate($form['due_date'])) {
            $errors['due_date'] = 'Enter a valid due date.';
        }

        if ($form['issue_date'] !== '' && $form['due_date'] !== '' && strtotime($form['due_date']) < strtotime($form['issue_date'])) {
            $errors['due_date'] = 'Due date must be on or after issue date.';
        }

        if (!is_numeric($form['tax_rate']) || (float) $form['tax_rate'] < 0) {
            $errors['tax_rate'] = 'Tax rate must be zero or greater.';
        }

        $itemErrors = [];
        $hasAtLeastOneItem = false;
        $allowedItemTypeNames = [];
        foreach ($invoiceItemTypes as $typeRow) {
            if (!is_array($typeRow)) {
                continue;
            }
            $typeName = mb_strtolower(trim((string) ($typeRow['name'] ?? '')));
            if ($typeName === '') {
                continue;
            }
            $allowedItemTypeNames[$typeName] = true;
        }

        if ($allowedItemTypeNames === []) {
            $errors['items'] = 'Add invoice item types in Admin before creating estimates or invoices.';
        }

        foreach ($form['items'] as $index => $item) {
            $row = $index + 1;
            $name = trim((string) ($item['name'] ?? ''));
            $qtyRaw = trim((string) ($item['quantity'] ?? ''));
            $rateRaw = trim((string) ($item['rate'] ?? ''));
            if ($name !== '') {
                $hasAtLeastOneItem = true;
            }

            if ($name === '') {
                $itemErrors[] = 'Line ' . (string) $row . ': name is required.';
            } elseif (!isset($allowedItemTypeNames[mb_strtolower($name)])) {
                $itemErrors[] = 'Line ' . (string) $row . ': choose an item from the saved invoice item type list.';
            }
            if ($qtyRaw === '' || !is_numeric($qtyRaw) || (float) $qtyRaw < 0) {
                $itemErrors[] = 'Line ' . (string) $row . ': quantity must be zero or greater.';
            }
            if ($rateRaw === '' || !is_numeric($rateRaw) || (float) $rateRaw < 0) {
                $itemErrors[] = 'Line ' . (string) $row . ': rate must be zero or greater.';
            }
        }
        if (!isset($errors['items'])) {
            if (!$hasAtLeastOneItem) {
                $errors['items'] = 'Add at least one line item.';
            } elseif ($itemErrors !== []) {
                $errors['items'] = implode(' ', $itemErrors);
            }
        }

        if ($form['type'] === 'invoice' && $this->hasInlinePayment($form)) {
            if (!$this->isValidDate((string) ($form['payment_paid_date'] ?? ''))) {
                $errors['payment_paid_date'] = 'Enter a valid payment date.';
            }
            if (!array_key_exists((string) ($form['payment_type'] ?? ''), $this->paymentCategoryOptions())) {
                $errors['payment_type'] = 'Choose a valid payment category.';
            }
            if (!array_key_exists((string) ($form['payment_method'] ?? ''), $this->paymentTypeOptions())) {
                $errors['payment_method'] = 'Choose a valid payment method.';
            }
            if (!is_numeric($form['payment_amount'] ?? '') || (float) ($form['payment_amount'] ?? 0) <= 0) {
                $errors['payment_amount'] = 'Payment amount must be greater than zero.';
            }
            if (strlen((string) ($form['payment_reference_number'] ?? '')) > 120) {
                $errors['payment_reference_number'] = 'Reference number is too long.';
            }
            if (strlen((string) ($form['payment_note'] ?? '')) > 255) {
                $errors['payment_note'] = 'Payment note is too long.';
            }
        }

        return $errors;
    }

    private function normalizeOptionKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[\s\-]+/', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
        return trim($normalized, '_');
    }

    private function payloadForSave(array $form): array
    {
        $subtotal = 0.0;
        $taxableSubtotal = 0.0;
        foreach ($form['items'] as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = max(0.0, (float) ($item['quantity'] ?? 0));
            $rate = max(0.0, (float) ($item['rate'] ?? 0));
            $lineTotal = round($qty * $rate, 2);
            $subtotal += $lineTotal;
            if (((string) ($item['taxable'] ?? '0')) === '1') {
                $taxableSubtotal += $lineTotal;
            }
        }
        $subtotal = round($subtotal, 2);
        $taxRate = max(0.0, (float) $form['tax_rate']);
        $taxAmount = round($taxableSubtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'type' => $form['type'],
            'status' => $form['status'],
            'invoice_number' => $form['invoice_number'],
            'client_id' => (int) $form['client_id'],
            'job_id' => (int) $form['job_id'],
            'issue_date' => $form['issue_date'] !== '' ? $form['issue_date'] : null,
            'due_date' => $form['due_date'] !== '' ? $form['due_date'] : null,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'customer_note' => $form['customer_note'],
            'internal_note' => $form['internal_note'],
        ];
    }

    private function isValidDate(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
