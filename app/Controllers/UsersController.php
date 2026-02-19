<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserAction;
use App\Models\UserLoginRecord;
use App\Support\Mailer;

final class UsersController extends Controller
{
    public function index(): void
    {
        require_permission('users', 'view');

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
        require_permission('users', 'view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $user = $id > 0 ? User::findById($id) : null;
        $lastLogin = $id > 0 ? UserLoginRecord::latestForUser($id) : null;
        $canManageEmployeeLink = has_role(2);
        $employeeLinkSupported = Employee::supportsUserLinking();
        $linkedEmployee = ($canManageEmployeeLink && $employeeLinkSupported && $id > 0)
            ? Employee::linkedToUser($id)
            : null;
        $viewer = auth_user();
        $viewerRole = (int) ($viewer['role'] ?? 0);
        $viewerId = auth_user_id();
        $canDeactivate = !($viewerId !== null && $viewerId === $id && $viewerRole !== 99);

        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
        }

        $pageScripts = '';
        if ($canManageEmployeeLink && $employeeLinkSupported) {
            $pageScripts = '<script src="' . asset('js/user-employee-link.js') . '"></script>';
        }

        $this->render('users/show', [
            'pageTitle' => 'User Details',
            'user' => $user,
            'lastLogin' => $lastLogin,
            'canManageEmployeeLink' => $canManageEmployeeLink,
            'employeeLinkSupported' => $employeeLinkSupported,
            'linkedEmployee' => $linkedEmployee,
            'canDeactivate' => $canDeactivate,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function employeeLookup(): void
    {
        $this->authorizeEmployeeLinking();

        if (!Employee::supportsUserLinking()) {
            json_response([]);
            return;
        }

        $term = trim((string) ($_GET['q'] ?? ''));
        $rows = Employee::lookupForUserLink($term);
        $payload = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'linked_user_id' => isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
                'linked_user_name' => trim((string) ($row['linked_user_name'] ?? '')),
            ];
        }, $rows);

        json_response($payload);
    }

    public function linkEmployee(array $params): void
    {
        $this->authorizeEmployeeLinking();

        $userId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($userId <= 0) {
            redirect('/users');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . $userId);
        }

        $user = User::findById($userId);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        if (!Employee::supportsUserLinking()) {
            flash('error', 'Employee-to-user linking is not enabled yet. Run the user/employee link migration first.');
            redirect('/users/' . $userId);
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            flash('error', 'Select an employee to link.');
            redirect('/users/' . $userId);
        }

        $employee = Employee::findActiveById($employeeId);
        if (!$employee) {
            flash('error', 'Selected employee is invalid or inactive.');
            redirect('/users/' . $userId);
        }

        try {
            Employee::assignToUser($employeeId, $userId, auth_user_id());
        } catch (\Throwable) {
            flash('error', 'Unable to link employee right now. Please try again.');
            redirect('/users/' . $userId);
        }

        $employeeName = trim((string) ($employee['name'] ?? ''));
        $label = $employeeName !== '' ? $employeeName : ('Employee #' . $employeeId);
        log_user_action(
            'user_employee_linked',
            'employees',
            $employeeId,
            'Linked ' . $label . ' to user #' . $userId . '.'
        );

        flash('success', 'Employee linked for punch actions.');
        redirect('/users/' . $userId);
    }

    public function unlinkEmployee(array $params): void
    {
        $this->authorizeEmployeeLinking();

        $userId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($userId <= 0) {
            redirect('/users');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . $userId);
        }

        if (!Employee::supportsUserLinking()) {
            flash('error', 'Employee-to-user linking is not enabled yet. Run the user/employee link migration first.');
            redirect('/users/' . $userId);
        }

        $linked = Employee::linkedToUser($userId);
        $affected = Employee::clearUserLink($userId, auth_user_id());

        if ($affected < 1) {
            flash('error', 'No linked employee found for this user.');
            redirect('/users/' . $userId);
        }

        $employeeId = isset($linked['id']) ? (int) $linked['id'] : null;
        $employeeName = trim((string) ($linked['name'] ?? ''));
        $label = $employeeName !== '' ? $employeeName : ($employeeId !== null ? ('Employee #' . $employeeId) : 'employee');
        log_user_action(
            'user_employee_unlinked',
            'employees',
            $employeeId,
            'Unlinked ' . $label . ' from user #' . $userId . '.'
        );

        flash('success', 'Employee link removed.');
        redirect('/users/' . $userId);
    }

    public function create(): void
    {
        require_permission('users', 'create');

        $this->render('users/create', [
            'pageTitle' => 'Add User',
        ]);

        clear_old();
    }

    public function store(): void
    {
        require_permission('users', 'create');

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

        $data['password'] = '';
        $data['password_confirm'] = '';
        $userId = User::create($data, auth_user_id());
        $inviteSent = $this->sendSetupInvite($userId, $data['email'], trim($data['first_name'] . ' ' . $data['last_name']));
        $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
        log_user_action(
            'user_created',
            'users',
            $userId,
            'Created user #' . $userId . ' (' . ($fullName !== '' ? $fullName : $data['email']) . ').'
        );
        if ($inviteSent) {
            flash('success', 'User created and setup email sent.');
        } else {
            flash('error', 'User created, but setup email could not be sent. Check mail settings/logs.');
        }
        redirect('/users/' . $userId);
    }

    public function update(array $params): void
    {
        require_permission('users', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . ($params['id'] ?? '') . '/edit');
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data, false, $id);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/users/' . $id . '/edit');
        }

        User::update($id, $data, auth_user_id());
        log_user_action('user_updated', 'users', $id, 'Updated user #' . $id . '.');
        redirect('/users/' . $id);
    }

    public function deactivate(array $params): void
    {
        require_permission('users', 'delete');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect('/users/' . ($params['id'] ?? ''));
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $viewer = auth_user();
        $viewerRole = (int) ($viewer['role'] ?? 0);
        $viewerId = auth_user_id();
        if ($viewerId !== null && $viewerId === $id && $viewerRole !== 99) {
            flash('error', 'You cannot deactivate your own account.');
            redirect('/users/' . $id);
        }

        User::deactivate($id, auth_user_id());
        log_user_action('user_deactivated', 'users', $id, 'Deactivated user #' . $id . '.');
        redirect('/users/' . $id);
    }

    public function edit(array $params): void
    {
        require_permission('users', 'edit');

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

    public function myActivity(): void
    {
        $userId = auth_user_id();
        if ($userId === null) {
            redirect('/login');
        }

        $this->renderActivity($userId, true);
    }

    public function activity(array $params): void
    {
        require_permission('users', 'view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $this->renderActivity($id, false);
    }

    public function logins(array $params): void
    {
        require_permission('users', 'view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $user = User::findById($id);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $records = UserLoginRecord::forUser($id, $query);
        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/user-activity-table.js') . '"></script>',
        ]);

        $this->render('users/logins', [
            'pageTitle' => 'User Login Records',
            'user' => $user,
            'records' => $records,
            'query' => $query,
            'isReady' => UserLoginRecord::isAvailable(),
            'pageScripts' => $pageScripts,
        ]);
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

    private function validate(array $data, bool $isCreate, ?int $userId = null): array
    {
        $errors = [];

        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        }
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        } elseif (User::emailInUse($data['email'], $userId)) {
            $errors[] = 'That email is already in use.';
        }
        if ($data['password'] !== '' && $data['password'] !== $data['password_confirm']) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($data['password'] !== '' && strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        return $errors;
    }

    private function sendSetupInvite(int $userId, string $email, string $displayName): bool
    {
        if ($userId <= 0 || trim($email) === '') {
            return false;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Throwable) {
            return false;
        }
        User::issuePasswordSetupToken($userId, $token, 72, auth_user_id());

        $name = trim($displayName);
        if ($name === '') {
            $name = 'there';
        }

        $link = absolute_url('/set-password?token=' . urlencode($token));
        $subject = 'Set your JunkTracker password';
        $body = "Hi {$name},\n\n"
            . "Your JunkTracker account has been created.\n"
            . "Use this link to set your password (expires in 72 hours):\n{$link}\n\n"
            . "If you did not expect this, please contact your administrator.\n";

        return Mailer::send($email, $subject, $body);
    }

    private function renderActivity(int $userId, bool $isOwn): void
    {
        $user = User::findById($userId);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $actions = UserAction::forUser($userId, $query);

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/user-activity-table.js') . '"></script>',
        ]);

        $this->render('users/activity', [
            'pageTitle' => $isOwn ? 'My Activity Log' : 'User Activity Log',
            'user' => $user,
            'actions' => $actions,
            'query' => $query,
            'isOwnActivity' => $isOwn,
            'isLogReady' => UserAction::isAvailable(),
            'pageScripts' => $pageScripts,
        ]);
    }

    private function authorizeEmployeeLinking(): void
    {
        require_permission('users', 'view');

        if (!has_role(2)) {
            redirect('/401');
        }
    }
}
