<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Models\User;

final class UsersController extends Controller
{
    public function index(): void
    {
        $query = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $users = User::search($query, $status);

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/users-table.js') . '"></script>',
        ]);

        $this->render('users/index', [
            'pageTitle' => 'Users',
            'users' => $users,
            'query' => $query,
            'status' => $status,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $user = $id > 0 ? User::findById($id) : null;

        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
        }

        $this->render('users/show', [
            'pageTitle' => 'User Details',
            'user' => $user,
        ]);
    }

    public function create(): void
    {
        $this->render('users/create', [
            'pageTitle' => 'Add User',
        ]);

        clear_old();
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data, true);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/users/new');
        }

        $userId = User::create($data, auth_user_id());
        redirect('/users/' . $userId);
    }

    public function update(array $params): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . ($params['id'] ?? '') . '/edit');
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data, false);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/users/' . $id . '/edit');
        }

        User::update($id, $data, auth_user_id());
        redirect('/users/' . $id);
    }

    public function deactivate(array $params): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect('/users/' . ($params['id'] ?? ''));
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        User::deactivate($id, auth_user_id());
        redirect('/users/' . $id);
    }

    public function edit(array $params): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $user = $id > 0 ? User::findById($id) : null;

        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
        }

        $this->render('users/edit', [
            'pageTitle' => 'Edit User',
            'user' => $user,
        ]);

        clear_old();
    }

    private function collectFormData(): array
    {
        return [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => (int) ($_POST['role'] ?? 1),
            'is_active' => (int) ($_POST['is_active'] ?? 1),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ];
    }

    private function validate(array $data, bool $isCreate): array
    {
        $errors = [];

        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        }
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if ($isCreate && $data['password'] === '') {
            $errors[] = 'Password is required.';
        }
        if ($data['password'] !== '' && $data['password'] !== $data['password_confirm']) {
            $errors[] = 'Password confirmation does not match.';
        }

        return $errors;
    }
}
