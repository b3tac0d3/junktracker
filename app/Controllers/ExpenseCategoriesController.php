<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ExpenseCategory;
use Core\Controller;

final class ExpenseCategoriesController extends Controller
{
    public function index(): void
    {
        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/admin-tables.js') . '"></script>',
        ]);

        $this->render('admin/expense_categories/index', [
            'pageTitle' => 'Expense Categories',
            'categories' => ExpenseCategory::allActive(),
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        $this->render('admin/expense_categories/create', [
            'pageTitle' => 'Add Expense Category',
            'category' => null,
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/expense-categories/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/admin/expense-categories/new');
        }

        ExpenseCategory::create($data, auth_user_id());
        flash('success', 'Expense category added.');
        redirect('/admin/expense-categories');
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $category = $id > 0 ? ExpenseCategory::findById($id) : null;
        if (!$category) {
            $this->renderNotFound();
            return;
        }

        $this->render('admin/expense_categories/edit', [
            'pageTitle' => 'Edit Expense Category',
            'category' => $category,
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/admin/expense-categories');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/expense-categories/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/admin/expense-categories/' . $id . '/edit');
        }

        ExpenseCategory::update($id, $data, auth_user_id());
        flash('success', 'Expense category updated.');
        redirect('/admin/expense-categories');
    }

    public function delete(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/admin/expense-categories');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/expense-categories');
        }

        ExpenseCategory::softDelete($id, auth_user_id());
        flash('success', 'Expense category deleted.');
        redirect('/admin/expense-categories');
    }

    private function collectFormData(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Category name is required.';
        }

        return $errors;
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
}
