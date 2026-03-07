<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Expense;
use Core\Controller;

final class ExpensesController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $scope = strtolower(trim((string) ($_GET['scope'] ?? 'all')));
        if (!in_array($scope, ['all', 'general', 'job'], true)) {
            $scope = 'all';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Expense::indexCount($businessId, $search, $scope);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $expenses = Expense::indexList($businessId, $search, $scope, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($expenses));

        $this->render('expenses/index', [
            'pageTitle' => 'Expenses',
            'search' => $search,
            'scope' => $scope,
            'expenses' => $expenses,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $this->render('expenses/form', [
            'pageTitle' => 'Add General Expense',
            'actionUrl' => url('/expenses'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'categoryOptions' => Expense::categoryOptions(current_business_id()),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/expenses/create');
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);
        if ($errors !== []) {
            $this->render('expenses/form', [
                'pageTitle' => 'Add General Expense',
                'actionUrl' => url('/expenses'),
                'form' => $form,
                'errors' => $errors,
                'categoryOptions' => Expense::categoryOptions(current_business_id()),
            ]);
            return;
        }

        $expenseId = Expense::create(current_business_id(), $this->payloadForSave($form), auth_user_id() ?? 0, null);
        if ($expenseId <= 0) {
            flash('error', 'Unable to save expense. Verify the expenses table exists.');
            redirect('/expenses/create');
        }

        flash('success', 'General expense added.');
        redirect('/expenses/' . (string) $expenseId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $expenseId = (int) ($params['id'] ?? 0);
        if ($expenseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $expense = Expense::findForBusiness(current_business_id(), $expenseId);
        if ($expense === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('expenses/show', [
            'pageTitle' => 'Expense Details',
            'expense' => $expense,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $expense = $this->expenseOr404((int) ($params['id'] ?? 0));
        if ($expense === null) {
            return;
        }

        if ((int) ($expense['job_id'] ?? 0) > 0) {
            flash('error', 'This expense is linked to a job. Edit it from the job record.');
            redirect('/jobs/' . (string) ((int) $expense['job_id']) . '/expenses/' . (string) ((int) ($expense['id'] ?? 0)));
        }

        $this->render('expenses/form', [
            'pageTitle' => 'Edit General Expense',
            'actionUrl' => url('/expenses/' . (string) ((int) ($expense['id'] ?? 0)) . '/update'),
            'form' => $this->formFromModel($expense),
            'errors' => [],
            'categoryOptions' => Expense::categoryOptions(current_business_id()),
            'mode' => 'edit',
            'pageHeading' => 'Edit General Expense',
            'cancelUrl' => url('/expenses/' . (string) ((int) ($expense['id'] ?? 0))),
            'submitLabel' => 'Save Changes',
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $expense = $this->expenseOr404((int) ($params['id'] ?? 0));
        if ($expense === null) {
            return;
        }

        if ((int) ($expense['job_id'] ?? 0) > 0) {
            flash('error', 'This expense is linked to a job. Edit it from the job record.');
            redirect('/jobs/' . (string) ((int) $expense['job_id']) . '/expenses/' . (string) ((int) ($expense['id'] ?? 0)));
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/expenses/' . (string) ((int) ($expense['id'] ?? 0)) . '/edit');
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);
        if ($errors !== []) {
            $this->render('expenses/form', [
                'pageTitle' => 'Edit General Expense',
                'actionUrl' => url('/expenses/' . (string) ((int) ($expense['id'] ?? 0)) . '/update'),
                'form' => $form,
                'errors' => $errors,
                'categoryOptions' => Expense::categoryOptions(current_business_id()),
                'mode' => 'edit',
                'pageHeading' => 'Edit General Expense',
                'cancelUrl' => url('/expenses/' . (string) ((int) ($expense['id'] ?? 0))),
                'submitLabel' => 'Save Changes',
            ]);
            return;
        }

        $updated = Expense::updateForBusiness(current_business_id(), (int) ($expense['id'] ?? 0), $this->payloadForSave($form), auth_user_id() ?? 0);
        if (!$updated) {
            flash('error', 'No changes were saved.');
            redirect('/expenses/' . (string) ((int) ($expense['id'] ?? 0)) . '/edit');
        }

        flash('success', 'Expense updated.');
        redirect('/expenses/' . (string) ((int) ($expense['id'] ?? 0)));
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $expense = $this->expenseOr404((int) ($params['id'] ?? 0));
        if ($expense === null) {
            return;
        }

        if ((int) ($expense['job_id'] ?? 0) > 0) {
            flash('error', 'This expense is linked to a job. Delete it from the job record.');
            redirect('/jobs/' . (string) ((int) $expense['job_id']) . '/expenses/' . (string) ((int) ($expense['id'] ?? 0)));
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/expenses/' . (string) ((int) ($expense['id'] ?? 0)));
        }

        $deleted = Expense::softDeleteForBusiness(current_business_id(), (int) ($expense['id'] ?? 0), auth_user_id() ?? 0);
        if (!$deleted) {
            flash('error', 'Unable to delete expense.');
            redirect('/expenses/' . (string) ((int) ($expense['id'] ?? 0)));
        }

        flash('success', 'Expense deleted.');
        redirect('/expenses');
    }

    private function defaultForm(): array
    {
        return [
            'expense_date' => date('Y-m-d'),
            'amount' => '',
            'category' => '',
            'payment_method' => '',
            'note' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'expense_date' => trim((string) ($input['expense_date'] ?? '')),
            'amount' => trim((string) ($input['amount'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'payment_method' => trim((string) ($input['payment_method'] ?? '')),
            'note' => trim((string) ($input['note'] ?? '')),
        ];
    }

    private function formFromModel(array $expense): array
    {
        $dateRaw = trim((string) ($expense['expense_date'] ?? ''));
        $dateStamp = $dateRaw !== '' ? strtotime($dateRaw) : false;

        return [
            'expense_date' => $dateStamp === false ? '' : date('Y-m-d', $dateStamp),
            'amount' => number_format((float) ($expense['amount'] ?? 0), 2, '.', ''),
            'category' => trim((string) ($expense['category'] ?? '')),
            'payment_method' => trim((string) ($expense['payment_method'] ?? '')),
            'note' => trim((string) ($expense['note'] ?? '')),
        ];
    }

    private function validateForm(array $form): array
    {
        $errors = [];

        if ($this->asDate($form['expense_date']) === null) {
            $errors['expense_date'] = 'Enter a valid expense date.';
        }

        if (!is_numeric($form['amount']) || (float) $form['amount'] <= 0) {
            $errors['amount'] = 'Enter an amount greater than 0.';
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'expense_date' => $this->toDatabaseDate($form['expense_date']),
            'amount' => (float) $form['amount'],
            'category' => $form['category'],
            'payment_method' => $form['payment_method'],
            'note' => $form['note'],
        ];
    }

    private function asDate(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function toDatabaseDate(string $value): ?string
    {
        $timestamp = $this->asDate($value);
        return $timestamp === null ? null : date('Y-m-d', $timestamp);
    }

    private function expenseOr404(int $expenseId): ?array
    {
        if ($expenseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        $expense = Expense::findForBusiness(current_business_id(), $expenseId);
        if ($expense === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        return $expense;
    }
}
