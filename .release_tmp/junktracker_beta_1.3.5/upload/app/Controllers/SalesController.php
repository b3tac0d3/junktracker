<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\FormSelectValue;
use App\Models\Job;
use App\Models\Purchase;
use App\Models\Sale;
use Core\Controller;

final class SalesController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

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
        $totalRows = Sale::indexCount($businessId, $search, $type, $fromDate, $toDate);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $sales = Sale::indexList($businessId, $search, $type, $fromDate, $toDate, $perPage, $offset, $sortBy, $sortDir);
        $summary = Sale::summary($businessId);
        $typeOptions = FormSelectValue::optionsForSection($businessId, 'sale_type');
        if ($typeOptions === []) {
            $typeOptions = Sale::typeOptions($businessId);
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
        require_business_role(['general_user', 'admin']);

        $this->render('sales/form', [
            'pageTitle' => 'Add Sale',
            'mode' => 'create',
            'actionUrl' => url('/sales'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'typeOptions' => $this->saleTypeOptions(current_business_id()),
        ]);
    }

    public function clientSearch(): void
    {
        require_business_role(['general_user', 'admin']);

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
        require_business_role(['general_user', 'admin']);

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

            $results[] = [
                'id' => $id,
                'title' => $title,
                'city' => trim((string) ($item['city'] ?? '')),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function purchaseSearch(): void
    {
        require_business_role(['general_user', 'admin']);

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
        require_business_role(['general_user', 'admin']);

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
            ]);
            return;
        }

        $saleId = Sale::create($businessId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Sale added.');
        redirect('/sales/' . (string) $saleId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

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

        $this->render('sales/show', [
            'pageTitle' => 'Sale Details',
            'sale' => $sale,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

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

        $this->render('sales/form', [
            'pageTitle' => 'Edit Sale',
            'mode' => 'edit',
            'actionUrl' => url('/sales/' . (string) $saleId . '/update'),
            'form' => $this->formFromModel($sale),
            'errors' => [],
            'typeOptions' => $this->saleTypeOptions(current_business_id()),
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

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
            ]);
            return;
        }

        Sale::update($businessId, $saleId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Sale updated.');
        redirect('/sales/' . (string) $saleId);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

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

        flash('success', 'Sale deleted.');
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
            'notes' => '',
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
            'notes' => trim((string) ($input['notes'] ?? '')),
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

        if (!$this->isValidMoney($form['net_amount'])) {
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

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'name' => $form['name'],
            'gross_amount' => round((float) $form['gross_amount'], 2),
            'net_amount' => round((float) $form['net_amount'], 2),
            'sale_type' => $form['sale_type'],
            'sale_date' => $this->toDatabaseDatetime($form['sale_date']),
            'client_id' => ($form['client_id'] === '') ? null : (int) $form['client_id'],
            'job_id' => ($form['job_id'] === '') ? null : (int) $form['job_id'],
            'purchase_id' => ($form['purchase_id'] === '') ? null : (int) $form['purchase_id'],
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

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
