<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DisposalLocation;
use App\Models\Job;
use App\Models\Sale;
use App\Models\Schema;
use Core\Controller;

final class SalesController extends Controller
{
    private const TYPES = ['all', 'shop', 'scrap', 'ebay', 'other'];
    private const RECORD_STATUSES = ['active', 'deleted', 'all'];

    public function index(): void
    {
        $this->authorize('view');

        $startDate = trim((string) ($_GET['start_date'] ?? ''));
        $endDate = trim((string) ($_GET['end_date'] ?? ''));

        // Default Sales index to current month unless a date range is explicitly provided.
        if ($startDate === '' && $endDate === '') {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'type' => (string) ($_GET['type'] ?? 'all'),
            'record_status' => (string) ($_GET['record_status'] ?? 'active'),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if (!in_array($filters['type'], self::TYPES, true)) {
            $filters['type'] = 'all';
        }
        if (!in_array($filters['record_status'], self::RECORD_STATUSES, true)) {
            $filters['record_status'] = 'active';
        }

        $sales = Sale::filter($filters);
        $summary = Sale::summarize($sales);

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/sales-table.js') . '"></script>',
        ]);

        $this->render('sales/index', [
            'pageTitle' => 'Sales',
            'sales' => $sales,
            'summary' => $summary,
            'filters' => $filters,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $this->authorize('view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/sales');
        }

        $sale = Sale::findById($id);
        if (!$sale) {
            $this->renderNotFound();
            return;
        }

        $this->render('sales/show', [
            'pageTitle' => 'Sale Details',
            'sale' => $sale,
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $this->render('sales/create', [
            'pageTitle' => 'Add Sale',
            'sale' => null,
            'types' => Sale::validTypes(),
            'supportsDisposalLocation' => Schema::hasColumn('sales', 'disposal_location_id'),
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function store(): void
    {
        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/sales/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/sales/new');
        }

        $saleId = Sale::create($data, auth_user_id());
        flash('success', 'Sale added.');
        redirect('/sales/' . $saleId);
    }

    public function edit(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/sales');
        }

        $sale = Sale::findById($id);
        if (!$sale) {
            $this->renderNotFound();
            return;
        }

        $this->render('sales/edit', [
            'pageTitle' => 'Edit Sale',
            'sale' => $sale,
            'types' => Sale::validTypes(),
            'supportsDisposalLocation' => Schema::hasColumn('sales', 'disposal_location_id'),
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/sales');
        }

        $sale = Sale::findById($id);
        if (!$sale) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/sales/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/sales/' . $id . '/edit');
        }

        Sale::update($id, $data, auth_user_id());
        flash('success', 'Sale updated.');
        redirect('/sales/' . $id);
    }

    public function delete(array $params): void
    {
        $this->authorize('delete');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/sales');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/sales/' . $id);
        }

        $sale = Sale::findById($id);
        if (!$sale) {
            $this->renderNotFound();
            return;
        }

        if (empty($sale['deleted_at']) && !empty($sale['active'])) {
            Sale::softDelete($id, auth_user_id());
            flash('success', 'Sale deleted.');
        } else {
            flash('success', 'Sale is already inactive.');
        }

        redirect('/sales');
    }

    public function scrapYardLookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(DisposalLocation::lookupActiveByType($term, 'scrap'));
    }

    public function jobLookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Job::lookupForSales($term));
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

    private function formScripts(): string
    {
        return implode("\n", [
            '<script src="' . asset('js/sale-scrap-yard-lookup.js') . '"></script>',
        ]);
    }

    private function collectFormData(): array
    {
        $type = trim((string) ($_POST['type'] ?? 'shop'));
        if (!in_array($type, Sale::validTypes(), true)) {
            $type = 'shop';
        }

        $grossAmount = $this->toDecimalOrNull($_POST['gross_amount'] ?? null);
        $netRaw = trim((string) ($_POST['net_amount'] ?? ''));
        $netAmount = $netRaw === '' ? $grossAmount : $this->toDecimalOrNull($netRaw);

        return [
            'job_id' => $this->toIntOrNull($_POST['job_id'] ?? null),
            'type' => $type,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'start_date' => $this->toDateOrNull($_POST['start_date'] ?? null),
            'end_date' => $this->toDateOrNull($_POST['end_date'] ?? null),
            'gross_amount' => $grossAmount,
            'net_amount' => $netAmount,
            'disposal_location_id' => $type === 'scrap'
                ? $this->toIntOrNull($_POST['disposal_location_id'] ?? null)
                : null,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Sale name is required.';
        }
        if (!in_array($data['type'], Sale::validTypes(), true)) {
            $errors[] = 'Sale type is invalid.';
        }
        if ($data['gross_amount'] === null) {
            $errors[] = 'Gross amount is required.';
        } elseif ($data['gross_amount'] < 0) {
            $errors[] = 'Gross amount must be 0 or greater.';
        }
        $netRaw = trim((string) ($_POST['net_amount'] ?? ''));
        if ($netRaw !== '' && $data['net_amount'] === null) {
            $errors[] = 'Net amount must be numeric.';
        }
        if ($data['net_amount'] !== null && $data['net_amount'] < 0) {
            $errors[] = 'Net amount must be 0 or greater.';
        }
        if ($data['start_date'] !== null && $data['end_date'] !== null && $data['end_date'] < $data['start_date']) {
            $errors[] = 'End date must be on or after start date.';
        }
        if ($data['type'] === 'scrap') {
            $jobSearch = trim((string) ($_POST['job_search'] ?? ''));
            if ($jobSearch !== '' && $data['job_id'] === null) {
                $errors[] = 'Select a job from the suggestions.';
            }
        }
        if ($data['job_id'] !== null && !Job::findById($data['job_id'])) {
            $errors[] = 'Selected job is invalid.';
        }
        if ($data['type'] === 'scrap' && Schema::hasColumn('sales', 'disposal_location_id')) {
            if ($data['disposal_location_id'] === null) {
                $errors[] = 'Select a scrap yard from the suggestions.';
            } elseif (!DisposalLocation::findActiveByIdAndType($data['disposal_location_id'], 'scrap')) {
                $errors[] = 'Selected scrap yard is invalid.';
            }
        }

        return $errors;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function toDateOrNull(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function toDecimalOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    private function authorize(string $action): void
    {
        require_permission('sales', $action);
    }
}
