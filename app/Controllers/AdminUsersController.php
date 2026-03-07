<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BusinessMembership;
use App\Models\User;
use Core\Controller;

final class AdminUsersController extends Controller
{
    public function index(): void
    {
        $this->requireUsersAdminAccess();

        $search = trim((string) ($_GET['q'] ?? ''));
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);

        $businessId = current_business_id();
        $isSiteAdminGlobal = is_site_admin() && $businessId <= 0;

        if ($isSiteAdminGlobal) {
            $totalRows = User::indexCountGlobal($search);
            $totalPages = pagination_total_pages($totalRows, $perPage);
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = pagination_offset($page, $perPage);
            $users = User::indexListGlobal($search, $perPage, $offset);
        } else {
            $totalRows = User::indexCountForBusiness($businessId, $search);
            $totalPages = pagination_total_pages($totalRows, $perPage);
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = pagination_offset($page, $perPage);
            $users = User::indexListForBusiness($businessId, $search, $perPage, $offset);
        }

        $pagination = pagination_meta($page, $perPage, $totalRows, count($users));

        $this->render('admin/users/index', [
            'pageTitle' => 'Users',
            'search' => $search,
            'users' => $users,
            'pagination' => $pagination,
            'isSiteAdminGlobal' => $isSiteAdminGlobal,
        ]);
    }

    public function edit(array $params): void
    {
        $this->requireUsersAdminAccess();

        $targetUser = $this->resolveTargetUser((int) ($params['id'] ?? 0));
        if ($targetUser === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/users/form', [
            'pageTitle' => 'Edit User',
            'actionUrl' => url('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/update'),
            'form' => $this->formFromUser($targetUser),
            'errors' => [],
            'targetUser' => $targetUser,
        ]);
    }

    public function update(array $params): void
    {
        $this->requireUsersAdminAccess();

        $targetUser = $this->resolveTargetUser((int) ($params['id'] ?? 0));
        if ($targetUser === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/edit');
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, (int) ($targetUser['id'] ?? 0));
        if ($errors !== []) {
            $this->render('admin/users/form', [
                'pageTitle' => 'Edit User',
                'actionUrl' => url('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/update'),
                'form' => $form,
                'errors' => $errors,
                'targetUser' => $targetUser,
            ]);
            return;
        }

        $payload = [
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'],
            'email' => $form['email'],
        ];
        if ($form['password'] !== '') {
            $payload['password_hash'] = password_hash($form['password'], PASSWORD_DEFAULT);
        }

        $actorId = (int) (auth_user_id() ?? 0);
        User::updateProfile((int) ($targetUser['id'] ?? 0), $payload, $actorId);

        if ($actorId > 0 && $actorId === (int) ($targetUser['id'] ?? 0) && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['first_name'] = $form['first_name'];
            $_SESSION['user']['last_name'] = $form['last_name'];
            $_SESSION['user']['email'] = strtolower($form['email']);
        }

        flash('success', 'User updated.');
        redirect('/admin/users');
    }

    private function requireUsersAdminAccess(): void
    {
        require_auth();
        if (is_site_admin()) {
            return;
        }

        require_business_role(['admin']);
    }

    private function resolveTargetUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $targetUser = User::findById($userId);
        if ($targetUser === null) {
            return null;
        }

        $businessId = current_business_id();

        if (is_site_admin()) {
            if ($businessId <= 0) {
                return (string) ($targetUser['role'] ?? '') === 'site_admin' ? $targetUser : null;
            }

            if ((string) ($targetUser['role'] ?? '') === 'site_admin') {
                return null;
            }

            return BusinessMembership::userHasBusiness($userId, $businessId) ? $targetUser : null;
        }

        if ((string) ($targetUser['role'] ?? '') === 'site_admin') {
            return null;
        }

        if ($businessId <= 0) {
            return null;
        }

        if (!BusinessMembership::userHasBusiness($userId, $businessId)) {
            return null;
        }

        return $targetUser;
    }

    private function formFromUser(array $user): array
    {
        return [
            'first_name' => trim((string) ($user['first_name'] ?? '')),
            'last_name' => trim((string) ($user['last_name'] ?? '')),
            'email' => trim((string) ($user['email'] ?? '')),
            'password' => '',
            'password_confirm' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim(strtolower((string) ($input['email'] ?? ''))),
            'password' => (string) ($input['password'] ?? ''),
            'password_confirm' => (string) ($input['password_confirm'] ?? ''),
        ];
    }

    private function validateForm(array $form, int $targetUserId): array
    {
        $errors = [];

        if ($form['first_name'] === '' && $form['last_name'] === '') {
            $errors['first_name'] = 'First or last name is required.';
        }

        if ($form['email'] === '' || filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email.';
        } elseif (User::emailExists($form['email'], $targetUserId)) {
            $errors['email'] = 'Email is already in use.';
        }

        if ($form['password'] !== '') {
            if (strlen($form['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            }
            if (!hash_equals($form['password'], $form['password_confirm'])) {
                $errors['password_confirm'] = 'Passwords do not match.';
            }
        }

        return $errors;
    }
}
