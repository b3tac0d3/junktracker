<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Job;
use App\Models\UserFilterPreset;
use Core\Controller;

final class ExpensesController extends Controller
{
    public function index(): void
    {
        $this->authorize('view');

        $moduleKey = 'expenses';
        $userId = auth_user_id() ?? 0;
        $savedPresets = $userId > 0 ? UserFilterPreset::forUser($userId, $moduleKey) : [];
        $selectedPresetId = $this->toIntOrNull($_GET['preset_id'] ?? null);
        $presetFilters = [];
        if ($selectedPresetId !== null && $selectedPresetId > 0 && $userId > 0) {
            $preset = UserFilterPreset::findForUser($selectedPresetId, $userId, $moduleKey);
            if ($preset) {
                $presetFilters = is_array($preset['filters'] ?? null) ? $preset['filters'] : [];
            } else {
                $selectedPresetId = null;
            }
        }

        $filters = [
            'q' => trim((string) ($presetFilters['q'] ?? '')),
            'category_id' => $this->toIntOrNull($presetFilters['category_id'] ?? null),
            'job_link' => (string) ($presetFilters['job_link'] ?? 'all'),
            'record_status' => (string) ($presetFilters['record_status'] ?? 'active'),
            'start_date' => trim((string) ($presetFilters['start_date'] ?? '')),
            'end_date' => trim((string) ($presetFilters['end_date'] ?? '')),
        ];

        if (array_key_exists('q', $_GET)) {
            $filters['q'] = trim((string) ($_GET['q'] ?? ''));
        }
        if (array_key_exists('category_id', $_GET)) {
            $filters['category_id'] = $this->toIntOrNull($_GET['category_id'] ?? null);
        }
        if (array_key_exists('job_link', $_GET)) {
            $filters['job_link'] = (string) ($_GET['job_link'] ?? 'all');
        }
        if (array_key_exists('record_status', $_GET)) {
            $filters['record_status'] = (string) ($_GET['record_status'] ?? 'active');
        }
        if (array_key_exists('start_date', $_GET)) {
            $filters['start_date'] = trim((string) ($_GET['start_date'] ?? ''));
        }
        if (array_key_exists('end_date', $_GET)) {
            $filters['end_date'] = trim((string) ($_GET['end_date'] ?? ''));
        }

        if ($filters['start_date'] === '' && $filters['end_date'] === '') {
            $filters['start_date'] = date('Y-m-01');
            $filters['end_date'] = date('Y-m-t');
        }

        if (!in_array($filters['job_link'], ['all', 'linked', 'unlinked'], true)) {
            $filters['job_link'] = 'all';
        }
        if (!in_array($filters['record_status'], ['active', 'deleted', 'all'], true)) {
            $filters['record_status'] = 'active';
        }

        $expenses = Expense::filter($filters);
        if ((string) ($_GET['export'] ?? '') === 'csv') {
            $this->downloadIndexCsv($expenses);
            return;
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/expenses-table.js') . '"></script>',
        ]);

        $this->render('expenses/index', [
            'pageTitle' => 'Expenses',
            'filters' => $filters,
            'categories' => Expense::categories(),
            'expenses' => $expenses,
            'summary' => Expense::summary($filters),
            'byJob' => Expense::summaryByJob($filters),
            'savedPresets' => $savedPresets,
            'selectedPresetId' => $selectedPresetId,
            'filterPresetModule' => $moduleKey,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $this->render('expenses/create', [
            'pageTitle' => 'Add Expense',
            'expense' => null,
            'categories' => ExpenseCategory::allActive(),
            'pageScripts' => '<script src="' . asset('js/expense-job-lookup.js') . '"></script>',
        ]);

        clear_old();
    }

    public function store(): void
    {
        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/expenses/new');
        }

        $jobId = $this->toIntOrNull($_POST['job_id'] ?? null);
        $categoryId = $this->toIntOrNull($_POST['expense_category_id'] ?? null);
        $expenseDate = $this->toDateOrNull($_POST['expense_date'] ?? null);
        $amount = $this->toDecimalOrNull($_POST['amount'] ?? null);
        $description = trim((string) ($_POST['description'] ?? ''));
        $jobSearch = trim((string) ($_POST['job_search'] ?? ''));

        $errors = [];

        if ($expenseDate === null) {
            $errors[] = 'Expense date is required.';
        }
        if ($amount === null || $amount < 0) {
            $errors[] = 'Enter a valid expense amount.';
        }

        $category = ($categoryId !== null && $categoryId > 0) ? ExpenseCategory::findById($categoryId) : null;
        if (!$category) {
            $errors[] = 'Select a valid expense category.';
        }

        if ($jobSearch !== '' && $jobId === null) {
            $errors[] = 'Select a job from the suggestions or clear the job field.';
        }

        $job = null;
        if ($jobId !== null && $jobId > 0) {
            $job = Job::findById($jobId);
            if (!$job || !empty($job['deleted_at']) || (isset($job['active']) && (int) $job['active'] === 0)) {
                $errors[] = 'Selected job is invalid.';
            }
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/expenses/new');
        }

        $normalizedJobId = ($jobId !== null && $jobId > 0) ? $jobId : null;

        $expenseId = Job::createExpense($normalizedJobId, [
            'disposal_location_id' => null,
            'expense_category_id' => (int) $categoryId,
            'category' => (string) ($category['name'] ?? ''),
            'description' => $description !== '' ? $description : null,
            'amount' => (float) $amount,
            'expense_date' => $expenseDate,
        ], auth_user_id());

        if ($jobId !== null && $jobId > 0) {
            Job::createAction($jobId, [
                'action_type' => 'expense_added',
                'action_at' => date('Y-m-d H:i:s'),
                'amount' => (float) $amount,
                'ref_table' => 'expenses',
                'ref_id' => $expenseId,
                'note' => 'Expense added from expense index.',
            ], auth_user_id());
        }

        flash('success', 'Expense added.');
        redirect('/expenses');
    }

    public function jobLookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Job::lookupForSales($term));
    }

    private function toIntOrNull(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
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

    private function authorize(string $action): void
    {
        require_permission('expenses', $action);
    }

    private function downloadIndexCsv(array $expenses): void
    {
        $rows = [];
        foreach ($expenses as $expense) {
            $jobId = isset($expense['job_id']) ? (int) $expense['job_id'] : 0;
            $rows[] = [
                (string) ($expense['id'] ?? ''),
                format_date($expense['expense_date'] ?? null),
                (string) ($expense['category_label'] ?? ''),
                (string) ($expense['description'] ?? ''),
                $jobId > 0 ? (string) ($expense['job_name'] ?? ('Job #' . $jobId)) : 'Unlinked',
                number_format((float) ($expense['amount'] ?? 0), 2),
                format_datetime($expense['updated_at'] ?? null),
            ];
        }

        stream_csv_download(
            'expenses-' . date('Ymd-His') . '.csv',
            ['ID', 'Date', 'Category', 'Description', 'Job', 'Amount', 'Last Activity'],
            $rows
        );
    }
}
