<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\EstateSale;
use App\Models\FormSelectValue;
use App\Models\Job;
use App\Models\Purchase;
use App\Models\Sale;
use Core\Controller;

final class SalesController extends Controller
{
    public function index(): void
    {
        require_financial_access();

        $search = trim((string) ($_GET['q'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? ''));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'date')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'desc')));
        if (!in_array($sortBy, ['date', 'id', 'client_name'], true)) {
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
        $totalRows = Sale::indexCount($businessId, $search, $type, $fromDate, $toDate, Sale::ESTATE_SCOPE_GENERAL);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $sales = Sale::indexList($businessId, $search, $type, $fromDate, $toDate, $perPage, $offset, $sortBy, $sortDir, Sale::ESTATE_SCOPE_GENERAL);
        $summary = Sale::summary($businessId, Sale::ESTATE_SCOPE_GENERAL);
        $typeOptions = FormSelectValue::optionsForSection($businessId, 'sale_type');
        if ($typeOptions === []) {
            $typeOptions = Sale::typeOptions($businessId, Sale::ESTATE_SCOPE_GENERAL);
        }
        $pagination = pagination_meta($page, $perPage, $totalRows, count($sales));

        $this->render('sales/index', [
            'pageTitle' => 'Sales',
            'search' => $search,
            'type' => $type,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'sales' => $sales,
            'summary' => $summary,
            'typeOptions' => $typeOptions,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_financial_access();

        $businessId = current_business_id();
        $form = $this->defaultForm();
        $clientIdPrefill = (int) ($_GET['client_id'] ?? 0);
        if ($clientIdPrefill > 0) {
            $client = Client::findForBusiness($businessId, $clientIdPrefill);
            if ($client !== null) {
                $form['client_id'] = (string) $clientIdPrefill;
                $form['client_name'] = Client::displayName($client);
            }
        }

        $fromJob = strtolower(trim((string) ($_GET['from'] ?? ''))) === 'job';
        $jobIdPrefill = (int) ($_GET['job_id'] ?? 0);
        if ($jobIdPrefill > 0) {
            $job = Job::findForBusiness($businessId, $jobIdPrefill);
            if ($job !== null) {
                $jobTitle = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobIdPrefill);
                $form['job_id'] = (string) $jobIdPrefill;
                $form['job_title'] = $jobTitle;
                if ($fromJob) {
                    $form['from'] = 'job';
                    $form['return_job_id'] = (string) $jobIdPrefill;
                    $form['return_tab'] = $this->saleReturnTabFromRequest();
                }
                $jobClientId = (int) ($job['client_id'] ?? 0);
                if ($jobClientId > 0 && trim((string) ($form['client_id'] ?? '')) === '') {
                    $client = Client::findForBusiness($businessId, $jobClientId);
                    if ($client !== null) {
                        $form['client_id'] = (string) $jobClientId;
                        $form['client_name'] = Client::displayName($client);
                    }
                }
            }
        }

        $this->render('sales/form', [
            'pageTitle' => 'Add Sale',
            'mode' => 'create',
            'actionUrl' => url('/sales'),
            'form' => $form,
            'errors' => [],
            'typeOptions' => $this->saleTypeOptions($businessId),
            'backUrl' => $this->saleFormBackUrl($form),
            'backLabel' => $this->saleFormBackLabel($form),
        ]);
    }

    public function clientSearch(): void
    {
        require_financial_access();

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
                'phone' => (string) ($item['phone'] ?? ''),
                'city' => (string) ($item['city'] ?? ''),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function jobSearch(): void
    {
        require_financial_access();

        $businessId = current_business_id();
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = Sale::jobSearchOptions($businessId, $query, 8);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            $title = trim((string) ($item['title'] ?? ''));
            if ($id <= 0 || $title === '') {
                continue;
            }

            $jobDateRaw = trim((string) ($item['job_date'] ?? ''));
            $jobDate = '';
            if ($jobDateRaw !== '' && $jobDateRaw !== '0000-00-00') {
                $stamp = strtotime($jobDateRaw . ' 12:00:00');
                if ($stamp !== false) {
                    $jobDate = date('m/d/Y', $stamp);
                }
            }

            $results[] = [
                'id' => $id,
                'title' => $title,
                'city' => trim((string) ($item['city'] ?? '')),
                'client_name' => trim((string) ($item['client_name'] ?? '')),
                'job_date' => $jobDate,
                'status' => strtolower(trim((string) ($item['status'] ?? ''))),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function purchaseSearch(): void
    {
        require_financial_access();

        $businessId = current_business_id();
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = Sale::purchaseSearchOptions($businessId, $query, 8);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            $title = trim((string) ($item['title'] ?? ''));
            if ($id <= 0 || $title === '') {
                continue;
            }

            $results[] = [
                'id' => $id,
                'title' => $title,
                'status' => trim((string) ($item['status'] ?? '')),
                'client_name' => trim((string) ($item['client_name'] ?? '')),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function store(): void
    {
        require_financial_access();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/sales/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('sales/form', [
                'pageTitle' => 'Add Sale',
                'mode' => 'create',
                'actionUrl' => url('/sales'),
                'form' => $form,
                'errors' => $errors,
                'typeOptions' => $this->saleTypeOptions($businessId),
                'backUrl' => $this->saleFormBackUrl($form),
                'backLabel' => $this->saleFormBackLabel($form),
            ]);
            return;
        }

        $saleId = Sale::create($businessId, $this->payloadForSave($form), auth_user_id() ?? 0);
        $this->redirectAfterSaleSave($form, $saleId, 'Sale added.');
    }

    public function show(array $params): void
    {
        require_financial_access();

        $saleId = (int) ($params['id'] ?? 0);
        if ($saleId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $sale = Sale::findForBusiness(current_business_id(), $saleId);
        if ($sale === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $clientPercentageMeta = null;
        $estateSaleId = (int) ($sale['estate_sale_id'] ?? 0);
        if ($estateSaleId > 0) {
            $estateSale = EstateSale::findForBusiness(current_business_id(), $estateSaleId);
            if ($estateSale !== null) {
                $clientPercentageMeta = EstateSale::saleClientPercentageMeta($sale, $estateSale);
            }
        }

        $back = sale_detail_back_meta($sale, (string) ($_GET['return_to'] ?? ''));

        $this->render('sales/show', [
            'pageTitle' => 'Sale Details',
            'sale' => $sale,
            'clientPercentageMeta' => $clientPercentageMeta,
            'backUrl' => $back['url'],
            'backLabel' => $back['label'],
            'returnTo' => $back['path'],
        ]);
    }

    public function edit(array $params): void
    {
        require_financial_access();

        $saleId = (int) ($params['id'] ?? 0);
        if ($saleId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $sale = Sale::findForBusiness(current_business_id(), $saleId);
        if ($sale === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $estateSaleId = (int) ($sale['estate_sale_id'] ?? 0);
        if ($estateSaleId > 0) {
            redirect('/estate-sales/' . (string) $estateSaleId . '/sales/' . (string) $saleId . '/edit');
            return;
        }

        $businessId = current_business_id();
        $form = $this->formFromModel($sale);
        $this->render('sales/form', [
            'pageTitle' => 'Edit Sale',
            'mode' => 'edit',
            'actionUrl' => url('/sales/' . (string) $saleId . '/update'),
            'form' => $form,
            'errors' => [],
            'typeOptions' => $this->saleTypeOptions($businessId),
            'backUrl' => $this->saleFormBackUrl($form),
            'backLabel' => $this->saleFormBackLabel($form),
        ]);
    }

    public function update(array $params): void
    {
        require_financial_access();

        $saleId = (int) ($params['id'] ?? 0);
        if ($saleId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/sales/' . (string) $saleId . '/edit');
        }

        $businessId = current_business_id();
        $sale = Sale::findForBusiness($businessId, $saleId);
        if ($sale === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('sales/form', [
                'pageTitle' => 'Edit Sale',
                'mode' => 'edit',
                'actionUrl' => url('/sales/' . (string) $saleId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'typeOptions' => $this->saleTypeOptions($businessId),
                'backUrl' => $this->saleFormBackUrl($form),
                'backLabel' => $this->saleFormBackLabel($form),
            ]);
            return;
        }

        Sale::update($businessId, $saleId, $this->payloadForSave($form), auth_user_id() ?? 0);
        $this->redirectAfterSaleSave($form, $saleId, 'Sale updated.');
    }

    public function delete(array $params): void
    {
        require_financial_access();

        $saleId = (int) ($params['id'] ?? 0);
        if ($saleId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/sales/' . (string) $saleId);
        }

        $businessId = current_business_id();
        $sale = Sale::findForBusiness($businessId, $saleId);
        if ($sale === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = Sale::softDelete($businessId, $saleId, auth_user_id() ?? 0);
        if (!$deleted) {
            flash('error', 'Unable to delete sale.');
            redirect('/sales/' . (string) $saleId);
        }

        audit('sale_deleted', 'sales', $saleId);
        flash('success', 'Sale deleted.');
        $returnTo = safe_return_path((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '') {
            redirect($returnTo);
        }
        $this->redirectAfterSaleDelete($sale);
    }

    private function redirectAfterSaleDelete(array $sale): void
    {
        $estateSaleId = (int) ($sale['estate_sale_id'] ?? 0);
        if ($estateSaleId > 0) {
            redirect('/estate-sales/' . (string) $estateSaleId . '?tab=sales');
        }

        redirect('/sales');
    }

    private function defaultForm(): array
    {
        return [
            'name' => '',
            'gross_amount' => '',
            'net_amount' => '',
            'sale_type' => '',
            'sale_date' => date('Y-m-d'),
            'client_id' => '',
            'client_name' => '',
            'job_id' => '',
            'job_title' => '',
            'purchase_id' => '',
            'purchase_title' => '',
            'estate_sale_id' => '',
            'estate_sale_customer_id' => '',
            'estate_sale_title' => '',
            'estate_sale_customer_name' => '',
            'notes' => '',
            'from' => '',
            'return_job_id' => '',
            'return_tab' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        $clientIdRaw = trim((string) ($input['client_id'] ?? ''));
        $clientId = ((int) $clientIdRaw) > 0 ? (string) ((int) $clientIdRaw) : '';
        $jobIdRaw = trim((string) ($input['job_id'] ?? ''));
        $jobId = ((int) $jobIdRaw) > 0 ? (string) ((int) $jobIdRaw) : '';
        $purchaseIdRaw = trim((string) ($input['purchase_id'] ?? ''));
        $purchaseId = ((int) $purchaseIdRaw) > 0 ? (string) ((int) $purchaseIdRaw) : '';
        $estateSaleIdRaw = trim((string) ($input['estate_sale_id'] ?? ''));
        $estateSaleId = ((int) $estateSaleIdRaw) > 0 ? (string) ((int) $estateSaleIdRaw) : '';
        $estateSaleCustomerIdRaw = trim((string) ($input['estate_sale_customer_id'] ?? ''));
        $estateSaleCustomerId = ((int) $estateSaleCustomerIdRaw) > 0 ? (string) ((int) $estateSaleCustomerIdRaw) : '';
        $returnJobIdRaw = trim((string) ($input['return_job_id'] ?? ''));
        $returnJobId = ((int) $returnJobIdRaw) > 0 ? (string) ((int) $returnJobIdRaw) : '';

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'gross_amount' => trim((string) ($input['gross_amount'] ?? '')),
            'net_amount' => trim((string) ($input['net_amount'] ?? '')),
            'sale_type' => strtolower(trim((string) ($input['sale_type'] ?? ''))),
            'sale_date' => trim((string) ($input['sale_date'] ?? '')),
            'client_id' => $clientId,
            'client_name' => trim((string) ($input['client_name'] ?? '')),
            'job_id' => $jobId,
            'job_title' => trim((string) ($input['job_title'] ?? '')),
            'purchase_id' => $purchaseId,
            'purchase_title' => trim((string) ($input['purchase_title'] ?? '')),
            'estate_sale_id' => $estateSaleId,
            'estate_sale_customer_id' => $estateSaleCustomerId,
            'estate_sale_title' => trim((string) ($input['estate_sale_title'] ?? '')),
            'estate_sale_customer_name' => trim((string) ($input['estate_sale_customer_name'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'from' => strtolower(trim((string) ($input['from'] ?? ''))),
            'return_job_id' => $returnJobId,
            'return_tab' => strtolower(trim((string) ($input['return_tab'] ?? ''))),
        ];
    }

    private function formFromModel(array $sale): array
    {
        $saleDate = trim((string) ($sale['sale_date'] ?? ''));
        if ($saleDate !== '') {
            $timestamp = strtotime($saleDate);
            $saleDate = $timestamp === false ? '' : date('Y-m-d', $timestamp);
        }

        return [
            'name' => trim((string) ($sale['name'] ?? '')),
            'gross_amount' => number_format((float) ($sale['gross_amount'] ?? 0), 2, '.', ''),
            'net_amount' => number_format((float) ($sale['net_amount'] ?? 0), 2, '.', ''),
            'sale_type' => strtolower(trim((string) ($sale['sale_type'] ?? ''))),
            'sale_date' => $saleDate,
            'client_id' => ((int) ($sale['client_id'] ?? 0)) > 0 ? (string) ((int) ($sale['client_id'] ?? 0)) : '',
            'client_name' => trim((string) ($sale['client_name'] ?? '')),
            'job_id' => ((int) ($sale['job_id'] ?? 0)) > 0 ? (string) ((int) ($sale['job_id'] ?? 0)) : '',
            'job_title' => trim((string) ($sale['job_title'] ?? '')),
            'purchase_id' => ((int) ($sale['purchase_id'] ?? 0)) > 0 ? (string) ((int) ($sale['purchase_id'] ?? 0)) : '',
            'purchase_title' => trim((string) ($sale['purchase_title'] ?? '')),
            'estate_sale_id' => ((int) ($sale['estate_sale_id'] ?? 0)) > 0 ? (string) ((int) ($sale['estate_sale_id'] ?? 0)) : '',
            'estate_sale_customer_id' => ((int) ($sale['estate_sale_customer_id'] ?? 0)) > 0 ? (string) ((int) ($sale['estate_sale_customer_id'] ?? 0)) : '',
            'estate_sale_title' => trim((string) ($sale['estate_sale_title'] ?? '')),
            'estate_sale_customer_name' => trim((string) ($sale['estate_sale_customer_name'] ?? '')),
            'notes' => trim((string) ($sale['notes'] ?? '')),
        ];
    }

    private function validateForm(array $form, int $businessId): array
    {
        $errors = [];

        if ($form['name'] === '') {
            $errors['name'] = 'Name is required.';
        }

        if (!$this->isValidMoney($form['gross_amount'])) {
            $errors['gross_amount'] = 'Gross must be a valid amount.';
        }

        $netRaw = trim((string) ($form['net_amount'] ?? ''));
        if ($netRaw !== '' && !$this->isValidMoney($netRaw)) {
            $errors['net_amount'] = 'Net must be a valid amount.';
        }

        $typeOptions = $this->saleTypeOptions($businessId);
        if (!in_array($form['sale_type'], $typeOptions, true)) {
            $errors['sale_type'] = 'Choose a valid type.';
        }

        if ($form['sale_date'] === '') {
            $errors['sale_date'] = 'Date is required.';
        } else {
            $date = strtotime($form['sale_date']);
            if ($date === false) {
                $errors['sale_date'] = 'Date is invalid.';
            }
        }

        $clientId = (int) $form['client_id'];
        if ($form['client_id'] !== '' && $clientId <= 0) {
            $errors['client_id'] = 'Select a valid client from suggestions or leave blank.';
        } elseif ($clientId > 0 && Client::findForBusiness($businessId, $clientId) === null) {
            $errors['client_id'] = 'Selected client was not found.';
        }

        $jobId = (int) $form['job_id'];
        if ($form['job_id'] !== '' && $jobId <= 0) {
            $errors['job_id'] = 'Select a valid job from suggestions or leave blank.';
        } elseif ($jobId > 0 && Job::findForBusiness($businessId, $jobId) === null) {
            $errors['job_id'] = 'Selected job was not found.';
        }

        $purchaseId = (int) $form['purchase_id'];
        if ($form['purchase_id'] !== '' && $purchaseId <= 0) {
            $errors['purchase_id'] = 'Select a valid purchase from suggestions or leave blank.';
        } elseif ($purchaseId > 0 && Purchase::findForBusiness($businessId, $purchaseId) === null) {
            $errors['purchase_id'] = 'Selected purchase was not found.';
        }

        if ($jobId > 0 && $purchaseId > 0) {
            $errors['job_id'] = 'Link this sale to either a job or a purchase, not both.';
            $errors['purchase_id'] = 'Link this sale to either a purchase or a job, not both.';
        }

        $estateSaleId = (int) $form['estate_sale_id'];
        $estateSaleCustomerId = (int) $form['estate_sale_customer_id'];
        if ($form['estate_sale_id'] !== '' && $estateSaleId <= 0) {
            $errors['estate_sale_id'] = 'Invalid estate sale link.';
        }
        if ($form['estate_sale_customer_id'] !== '' && $estateSaleCustomerId <= 0) {
            $errors['estate_sale_customer_id'] = 'Invalid estate sale customer link.';
        }
        if (($form['estate_sale_id'] === '') xor ($form['estate_sale_customer_id'] === '')) {
            $errors['estate_sale_id'] = 'Estate sale and customer must be linked together.';
            $errors['estate_sale_customer_id'] = 'Estate sale and customer must be linked together.';
        } elseif ($estateSaleId > 0 && $estateSaleCustomerId > 0) {
            $estateSale = EstateSale::findForBusiness($businessId, $estateSaleId);
            $estateCustomer = EstateSale::findCustomerForSale($businessId, $estateSaleId, $estateSaleCustomerId);
            if ($estateSale === null) {
                $errors['estate_sale_id'] = 'Selected estate sale was not found.';
            }
            if ($estateCustomer === null) {
                $errors['estate_sale_customer_id'] = 'Selected estate sale customer was not found.';
            }
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        $gross = round((float) $form['gross_amount'], 2);
        $netRaw = trim((string) ($form['net_amount'] ?? ''));
        if ($netRaw === '' || !is_numeric($netRaw)) {
            $net = $gross;
        } else {
            $net = round((float) $netRaw, 2);
        }
        if ($net < 0) {
            $net = 0;
        }

        return [
            'name' => $form['name'],
            'gross_amount' => $gross,
            'net_amount' => $net,
            'sale_type' => $form['sale_type'],
            'sale_date' => $this->toDatabaseDatetime($form['sale_date']),
            'client_id' => ($form['client_id'] === '') ? null : (int) $form['client_id'],
            'job_id' => ($form['job_id'] === '') ? null : (int) $form['job_id'],
            'purchase_id' => ($form['purchase_id'] === '') ? null : (int) $form['purchase_id'],
            'estate_sale_id' => ($form['estate_sale_id'] === '') ? null : (int) $form['estate_sale_id'],
            'estate_sale_customer_id' => ($form['estate_sale_customer_id'] === '') ? null : (int) $form['estate_sale_customer_id'],
            'notes' => $form['notes'],
        ];
    }

    private function isValidMoney(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (!is_numeric($value)) {
            return false;
        }

        return (float) $value >= 0;
    }

    private function toDatabaseDatetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeDateFilter(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '' : date('Y-m-d', $timestamp);
    }

    /**
     * @return array<int, string>
     */
    private function saleTypeOptions(int $businessId): array
    {
        $options = FormSelectValue::optionsForSection($businessId, 'sale_type');
        if ($options !== []) {
            $normalized = array_map(static fn (string $value): string => strtolower(trim($value)), $options);
            $normalized = array_values(array_unique(array_filter($normalized, static fn (string $value): bool => $value !== '')));
            if ($normalized !== []) {
                return $normalized;
            }
        }

        return Sale::typeOptions($businessId);
    }

    private function saleFormBackUrl(array $form): string
    {
        $jobReturnUrl = $this->saleJobReturnUrl($form);
        if ($jobReturnUrl !== null) {
            return $jobReturnUrl;
        }

        $estateSaleId = (int) ($form['estate_sale_id'] ?? 0);
        if ($estateSaleId > 0) {
            return url('/estate-sales/' . (string) $estateSaleId . '?tab=customers');
        }

        return url('/sales');
    }

    private function saleFormBackLabel(array $form): string
    {
        if ($this->saleJobReturnUrl($form) !== null) {
            return 'Back to Job';
        }

        return (int) ($form['estate_sale_id'] ?? 0) > 0 ? 'Back to Estate Sale' : 'Back to Sales';
    }

    private function redirectAfterSaleSave(array $form, int $saleId, string $message): void
    {
        $action = str_contains(strtolower($message), 'updated') ? 'sale_updated' : 'sale_created';
        audit($action, 'sales', $saleId, [
            'name' => trim((string) ($form['name'] ?? '')),
            'amount' => $form['gross_amount'] ?? '',
        ]);
        flash('success', $message);

        $jobReturnPath = $this->saleJobReturnPath($form);
        if ($jobReturnPath !== null) {
            redirect($jobReturnPath);
        }

        $estateSaleId = (int) ($form['estate_sale_id'] ?? 0);
        if ($estateSaleId > 0) {
            redirect('/estate-sales/' . (string) $estateSaleId . '?tab=customers');
        }

        redirect('/sales/' . (string) $saleId);
    }

    private function saleReturnTabFromRequest(): string
    {
        return request_detail_tab(['details', 'financial', 'transactions', 'labor'], 'transactions');
    }

    private function saleJobReturnPath(array $form): ?string
    {
        if (strtolower(trim((string) ($form['from'] ?? ''))) !== 'job') {
            return null;
        }

        $jobId = (int) ($form['return_job_id'] ?? 0);
        if ($jobId <= 0) {
            $jobId = (int) ($form['job_id'] ?? 0);
        }
        if ($jobId <= 0) {
            return null;
        }

        $tab = strtolower(trim((string) ($form['return_tab'] ?? '')));
        $allowedTabs = ['details', 'financial', 'transactions', 'labor'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'transactions';
        }

        return '/jobs/' . (string) $jobId . '?tab=' . $tab;
    }

    private function saleJobReturnUrl(array $form): ?string
    {
        $path = $this->saleJobReturnPath($form);

        return $path !== null ? url($path) : null;
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
