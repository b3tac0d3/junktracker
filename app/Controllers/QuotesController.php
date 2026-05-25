<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FormSelectValue;
use App\Models\Quote;
use Core\Controller;

final class QuotesController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'dispatch')));
        $businessId = current_business_id();
        $statusOptions = Quote::statusOptions();
        $allowed = array_merge(['dispatch', ''], $statusOptions);
        if (!in_array($status, $allowed, true)) {
            $status = 'dispatch';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Quote::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $quotes = Quote::indexList($businessId, $search, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($quotes));

        $this->render('quotes/index', [
            'pageTitle' => 'Quotes',
            'search' => $search,
            'status' => $status,
            'statusOptions' => $statusOptions,
            'quotes' => $quotes,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $form = $this->defaultForm();
        $prefillAt = calendar_slot_prefill_at();
        if ($prefillAt !== '') {
            $form['next_follow_up_at'] = $prefillAt;
        }
        $businessId = current_business_id();
        $requestedClientId = (int) ($_GET['client_id'] ?? 0);
        if ($requestedClientId > 0) {
            $client = Client::findForBusiness($businessId, $requestedClientId);
            if ($client !== null) {
                $form['client_id'] = (string) $requestedClientId;
                $form['client_name'] = Client::displayName($client);
                $form['address_line1'] = trim((string) ($client['address_line1'] ?? ''));
                $form['address_line2'] = trim((string) ($client['address_line2'] ?? ''));
                $form['city'] = trim((string) ($client['city'] ?? ''));
                $form['state'] = trim((string) ($client['state'] ?? ''));
                $form['postal_code'] = trim((string) ($client['postal_code'] ?? ''));
            }
        }

        $this->render('quotes/form', [
            'pageTitle' => 'Add Quote',
            'mode' => 'create',
            'actionUrl' => url('/quotes'),
            'form' => $form,
            'errors' => [],
            'statusOptions' => Quote::statusOptions(),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'serviceTypeOptions' => FormSelectValue::optionsForSection($businessId, 'job_type'),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/quotes/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = Quote::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('quotes/form', [
                'pageTitle' => 'Add Quote',
                'mode' => 'create',
                'actionUrl' => url('/quotes'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => Quote::statusOptions(),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
                'serviceTypeOptions' => FormSelectValue::optionsForSection($businessId, 'job_type'),
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        $quoteId = Quote::create($businessId, $form, $actorUserId);
        if ($quoteId <= 0) {
            flash('error', 'Unable to create quote.');
            redirect('/quotes/create');
        }
        AuditLog::write('quote_created', 'quotes', $quoteId, $businessId, $actorUserId, []);
        flash('success', 'Quote created.');
        redirect('/quotes/' . (string) $quoteId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        $quoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $quote = Quote::findForBusiness($businessId, $quoteId);
        if ($quote === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $estimates = Quote::estimatesByQuote($businessId, $quoteId);
        $statusOptions = Quote::statusOptions();
        $currentStatus = strtolower(trim((string) ($quote['status'] ?? 'new')));
        if ($currentStatus !== '' && !in_array($currentStatus, $statusOptions, true)) {
            $statusOptions = array_values(array_unique(array_merge([$currentStatus], $statusOptions)));
        }
        $this->render('quotes/show', [
            'pageTitle' => 'Quote',
            'quote' => $quote,
            'estimates' => $estimates,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        $quoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $quote = Quote::findForBusiness($businessId, $quoteId);
        if ($quote === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromModel($quote);
        $clientIdForName = (int) ($form['client_id'] ?? 0);
        if ($clientIdForName > 0) {
            $clientRow = Client::findForBusiness($businessId, $clientIdForName);
            if ($clientRow !== null) {
                $form['client_name'] = Client::displayName($clientRow);
            }
        }

        $this->render('quotes/form', [
            'pageTitle' => 'Edit Quote',
            'mode' => 'edit',
            'actionUrl' => url('/quotes/' . (string) $quoteId . '/update'),
            'form' => $form,
            'errors' => [],
            'statusOptions' => Quote::statusOptions(),
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            'serviceTypeOptions' => FormSelectValue::optionsForSection($businessId, 'job_type'),
            'quoteId' => $quoteId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/quotes');
        }

        $quoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $quote = Quote::findForBusiness($businessId, $quoteId);
        if ($quote === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = Quote::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('quotes/form', [
                'pageTitle' => 'Edit Quote',
                'mode' => 'edit',
                'actionUrl' => url('/quotes/' . (string) $quoteId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => Quote::statusOptions(),
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
                'serviceTypeOptions' => FormSelectValue::optionsForSection($businessId, 'job_type'),
                'quoteId' => $quoteId,
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        Quote::update($businessId, $quoteId, $form, $actorUserId);
        AuditLog::write('quote_updated', 'quotes', $quoteId, $businessId, $actorUserId, []);
        flash('success', 'Quote updated.');
        redirect('/quotes/' . (string) $quoteId);
    }

    public function convertToJob(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/quotes');
        }

        $quoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $jobId = Quote::convertToJob($businessId, $quoteId, $actorUserId);
        if ($jobId <= 0) {
            flash('error', 'Unable to convert quote to job.');
            redirect('/quotes/' . (string) $quoteId);
        }
        AuditLog::write('quote_converted_to_job', 'quotes', $quoteId, $businessId, $actorUserId, ['job_id' => $jobId]);
        flash('success', 'Quote converted to job.');
        redirect('/jobs/' . (string) $jobId);
    }

    public function convertToEstateSale(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/quotes');
        }

        $quoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $estateSaleId = Quote::convertToEstateSale($businessId, $quoteId, $actorUserId);
        if ($estateSaleId <= 0) {
            flash('error', 'Unable to convert quote to estate sale.');
            redirect('/quotes/' . (string) $quoteId);
        }
        AuditLog::write('quote_converted_to_estate_sale', 'quotes', $quoteId, $businessId, $actorUserId, ['estate_sale_id' => $estateSaleId]);
        flash('success', 'Quote converted to estate sale.');
        redirect('/estate-sales/' . (string) $estateSaleId);
    }

    public function convertToPurchase(array $params): void
    {
        require_business_role(['general_user', 'admin']);
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/quotes');
        }

        $quoteId = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorUserId = (int) (auth_user_id() ?? 0);
        $purchaseId = Quote::convertToPurchase($businessId, $quoteId, $actorUserId);
        if ($purchaseId <= 0) {
            flash('error', 'Unable to convert quote to purchase.');
            redirect('/quotes/' . (string) $quoteId);
        }
        AuditLog::write('quote_converted_to_purchase', 'quotes', $quoteId, $businessId, $actorUserId, ['purchase_id' => $purchaseId]);
        flash('success', 'Quote converted to purchase.');
        redirect('/purchases/' . (string) $purchaseId);
    }

    public function quickStatus(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $quoteId = (int) ($params['id'] ?? 0);
        if ($quoteId <= 0) {
            flash('error', 'Quote not found.');
            redirect('/quotes');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/quotes/' . (string) $quoteId);
        }

        $businessId = current_business_id();
        $quote = Quote::findForBusiness($businessId, $quoteId);
        if ($quote === null) {
            flash('error', 'Quote not found.');
            redirect('/quotes');
        }

        $status = strtolower(trim((string) ($_POST['status'] ?? '')));
        $allowed = Quote::statusOptions();
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Choose a valid status.');
            redirect('/quotes/' . (string) $quoteId);
        }

        $current = strtolower(trim((string) ($quote['status'] ?? 'new')));
        if ($status === $current) {
            redirect('/quotes/' . (string) $quoteId);
        }

        $actor = (int) (auth_user_id() ?? 0);
        if (Quote::updateStatus($businessId, $quoteId, $status, $actor)) {
            AuditLog::write('quote_status_updated', 'quotes', $quoteId, $businessId, $actor, [
                'from_status' => $current,
                'to_status' => $status,
            ]);
            flash('success', 'Quote status updated.');
        } else {
            flash('error', 'Could not update status.');
        }

        redirect('/quotes/' . (string) $quoteId);
    }

    private function defaultForm(): array
    {
        return [
            'client_id' => '',
            'client_name' => '',
            'title' => '',
            'status' => 'new',
            'service_type' => '',
            'notes' => '',
            'next_follow_up_at' => '',
            'lost_reason' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'converted_job_id' => '',
            'converted_estate_sale_id' => '',
            'converted_purchase_id' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'client_name' => trim((string) ($input['client_name'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'status' => strtolower(trim((string) ($input['status'] ?? 'new'))),
            'service_type' => trim((string) ($input['service_type'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'next_follow_up_at' => trim((string) ($input['next_follow_up_at'] ?? '')),
            'lost_reason' => trim((string) ($input['lost_reason'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'converted_job_id' => trim((string) ($input['converted_job_id'] ?? '')),
            'converted_estate_sale_id' => trim((string) ($input['converted_estate_sale_id'] ?? '')),
            'converted_purchase_id' => trim((string) ($input['converted_purchase_id'] ?? '')),
        ];
    }

    private function formFromModel(array $quote): array
    {
        return [
            'client_id' => (string) ((int) ($quote['client_id'] ?? 0)),
            'title' => trim((string) ($quote['title'] ?? '')),
            'status' => strtolower(trim((string) ($quote['status'] ?? 'new'))),
            'service_type' => trim((string) ($quote['service_type'] ?? '')),
            'notes' => trim((string) ($quote['notes'] ?? '')),
            'next_follow_up_at' => $this->toInputDateTimeLocal((string) ($quote['next_follow_up_at'] ?? '')),
            'lost_reason' => trim((string) ($quote['lost_reason'] ?? '')),
            'address_line1' => trim((string) ($quote['address_line1'] ?? '')),
            'address_line2' => trim((string) ($quote['address_line2'] ?? '')),
            'city' => trim((string) ($quote['city'] ?? '')),
            'state' => trim((string) ($quote['state'] ?? '')),
            'postal_code' => trim((string) ($quote['postal_code'] ?? '')),
            'converted_job_id' => (string) ((int) ($quote['converted_job_id'] ?? 0)),
            'converted_estate_sale_id' => (string) ((int) ($quote['converted_estate_sale_id'] ?? 0)),
            'converted_purchase_id' => (string) ((int) ($quote['converted_purchase_id'] ?? 0)),
        ];
    }

    private function toInputDateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }
}

