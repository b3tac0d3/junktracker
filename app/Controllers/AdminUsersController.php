<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BusinessMembership;
use App\Models\User;
use Core\Controller;
use Core\Mailer;

final class AdminUsersController extends Controller
{
    public function index(): void
    {
        $this->requireUsersAdminAccess();

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);

        $isSiteAdminGlobal = $this->isGlobalSiteAdminContext();
        $businessId = current_business_id();

        if ($isSiteAdminGlobal) {
            $totalRows = User::indexCountGlobal($search, $status);
            $totalPages = pagination_total_pages($totalRows, $perPage);
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = pagination_offset($page, $perPage);
            $users = User::indexListGlobal($search, $status, $perPage, $offset);
        } else {
            $totalRows = User::indexCountForBusiness($businessId, $search, $status);
            $totalPages = pagination_total_pages($totalRows, $perPage);
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = pagination_offset($page, $perPage);
            $users = User::indexListForBusiness($businessId, $search, $status, $perPage, $offset);
        }

        $pagination = pagination_meta($page, $perPage, $totalRows, count($users));

        $this->render('admin/users/index', [
            'pageTitle' => 'Users',
            'search' => $search,
            'status' => $status,
            'users' => $users,
            'pagination' => $pagination,
            'isSiteAdminGlobal' => $isSiteAdminGlobal,
        ]);
    }

    public function create(): void
    {
        $this->requireUsersAdminAccess();

        $isSiteAdminGlobal = $this->isGlobalSiteAdminContext();
        $pageTitle = $isSiteAdminGlobal ? 'Add Site Admin' : 'Add User';

        $this->render('admin/users/form', [
            'pageTitle' => $pageTitle,
            'mode' => 'create',
            'actionUrl' => url('/admin/users'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'targetUser' => [],
            'isSiteAdminGlobal' => $isSiteAdminGlobal,
            'workspaceRoleOptions' => $this->workspaceRoleOptions(),
            'canToggleActive' => false,
        ]);
    }

    public function store(): void
    {
        $this->requireUsersAdminAccess();

        $isSiteAdminGlobal = $this->isGlobalSiteAdminContext();
        $pageTitle = $isSiteAdminGlobal ? 'Add Site Admin' : 'Add User';

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/users/create');
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, null, true, $isSiteAdminGlobal);
        if ($errors !== []) {
            $this->render('admin/users/form', [
                'pageTitle' => $pageTitle,
                'mode' => 'create',
                'actionUrl' => url('/admin/users'),
                'form' => $form,
                'errors' => $errors,
                'targetUser' => [],
                'isSiteAdminGlobal' => $isSiteAdminGlobal,
                'workspaceRoleOptions' => $this->workspaceRoleOptions(),
                'canToggleActive' => false,
            ]);
            return;
        }

        $actorId = (int) (auth_user_id() ?? 0);
        $temporaryPassword = $this->generateTemporaryPassword();
        $userId = User::create([
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'],
            'email' => $form['email'],
            'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
            'role' => $isSiteAdminGlobal ? 'site_admin' : 'general_user',
            'is_active' => 1,
            'must_change_password' => 1,
        ], $actorId);

        if (!$isSiteAdminGlobal) {
            $businessId = current_business_id();
            if ($businessId > 0) {
                BusinessMembership::assignRole($businessId, $userId, (string) ($form['workspace_role'] ?? 'general_user'), $actorId);
            }
        }

        $inviteSent = $this->sendInviteEmail($form, $temporaryPassword, $isSiteAdminGlobal);
        if ($inviteSent) {
            flash('success', $isSiteAdminGlobal
                ? 'Site admin added and invite email sent.'
                : 'User added and invite email sent.');
        } else {
            flash('error', $isSiteAdminGlobal
                ? 'Site admin added, but the invite email could not be sent. Use Resend Invite after mail is configured.'
                : 'User added, but the invite email could not be sent. Use Resend Invite after mail is configured.');
        }
        redirect('/admin/users');
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

        $isSiteAdminGlobal = $this->isGlobalSiteAdminContext();

        $this->render('admin/users/form', [
            'pageTitle' => 'Edit User',
            'mode' => 'edit',
            'actionUrl' => url('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/update'),
            'form' => $this->formFromUser($targetUser),
            'errors' => [],
            'targetUser' => $targetUser,
            'isSiteAdminGlobal' => $isSiteAdminGlobal,
            'workspaceRoleOptions' => $this->workspaceRoleOptions(),
            'canToggleActive' => ((int) (auth_user_id() ?? 0)) !== (int) ($targetUser['id'] ?? 0),
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

        $isSiteAdminGlobal = $this->isGlobalSiteAdminContext();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, (int) ($targetUser['id'] ?? 0), false, $isSiteAdminGlobal);
        if ($errors !== []) {
            $this->render('admin/users/form', [
                'pageTitle' => 'Edit User',
                'mode' => 'edit',
                'actionUrl' => url('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/update'),
                'form' => $form,
                'errors' => $errors,
                'targetUser' => $targetUser,
                'isSiteAdminGlobal' => $isSiteAdminGlobal,
                'workspaceRoleOptions' => $this->workspaceRoleOptions(),
                'canToggleActive' => ((int) (auth_user_id() ?? 0)) !== (int) ($targetUser['id'] ?? 0),
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
        if (!$isSiteAdminGlobal) {
            $businessId = current_business_id();
            if ($businessId > 0) {
                BusinessMembership::setRoleForBusiness($businessId, (int) ($targetUser['id'] ?? 0), (string) ($form['workspace_role'] ?? 'general_user'), $actorId);
            }
        }

        if ($actorId > 0 && $actorId === (int) ($targetUser['id'] ?? 0) && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['first_name'] = $form['first_name'];
            $_SESSION['user']['last_name'] = $form['last_name'];
            $_SESSION['user']['email'] = strtolower($form['email']);
            if (!$isSiteAdminGlobal) {
                $_SESSION['user']['workspace_role'] = (string) ($form['workspace_role'] ?? ($_SESSION['user']['workspace_role'] ?? 'general_user'));
            }
        }

        flash('success', 'User updated.');
        redirect('/admin/users');
    }

    public function toggleActive(array $params): void
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

        $actorId = (int) (auth_user_id() ?? 0);
        $targetId = (int) ($targetUser['id'] ?? 0);
        if ($actorId > 0 && $actorId === $targetId) {
            flash('error', 'You cannot deactivate your own account.');
            redirect('/admin/users/' . (string) $targetId . '/edit');
        }

        $setActive = (int) ($_POST['set_active'] ?? 0) === 1;
        if ($this->isGlobalSiteAdminContext()) {
            User::setActiveState($targetId, $setActive, $actorId);
            flash('success', $setActive ? 'Site admin reactivated.' : 'Site admin deactivated.');
            redirect('/admin/users/' . (string) $targetId . '/edit');
        }

        $businessId = current_business_id();
        if ($businessId <= 0) {
            flash('error', 'Business context is required.');
            redirect('/admin/users');
        }

        BusinessMembership::setActiveForBusiness($businessId, $targetId, $setActive, $actorId);
        if ($setActive) {
            User::setActiveState($targetId, true, $actorId);
        }

        flash('success', $setActive ? 'User reactivated.' : 'User deactivated.');
        redirect('/admin/users/' . (string) $targetId . '/edit');
    }

    public function resendInvite(array $params): void
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

        $temporaryPassword = $this->generateTemporaryPassword();
        $actorId = (int) (auth_user_id() ?? 0);
        User::resendInvitation((int) ($targetUser['id'] ?? 0), password_hash($temporaryPassword, PASSWORD_DEFAULT), $actorId);
        $inviteSent = $this->sendInviteEmail($targetUser, $temporaryPassword, $this->isGlobalSiteAdminContext());
        if ($inviteSent) {
            flash('success', 'Invite resent.');
        } else {
            flash('error', 'Invite was reset, but the email could not be sent. Check mail configuration and try again.');
        }
        redirect('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/edit');
    }

    public function autoAccept(array $params): void
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

        $temporaryPassword = $this->generateTemporaryPassword();
        $actorId = (int) (auth_user_id() ?? 0);
        User::autoAcceptInvitation((int) ($targetUser['id'] ?? 0), password_hash($temporaryPassword, PASSWORD_DEFAULT), $actorId);

        flash('success', 'Invite accepted on behalf of the user. Temporary password: ' . $temporaryPassword . '. The user must change it at first login.');
        redirect('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/edit');
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

        if ($this->isGlobalSiteAdminContext()) {
            if ((string) ($targetUser['role'] ?? '') !== 'site_admin') {
                return null;
            }
            $targetUser['effective_active'] = (int) ($targetUser['is_active'] ?? 1) === 1 ? 1 : 0;
            return $targetUser;
        }

        if ((string) ($targetUser['role'] ?? '') === 'site_admin') {
            return null;
        }

        $businessId = current_business_id();
        if ($businessId <= 0) {
            return null;
        }

        $membership = BusinessMembership::findForBusiness($businessId, $userId);
        if ($membership === null || (string) ($membership['deleted_at'] ?? '') !== '') {
            return null;
        }

        $membershipActive = (int) ($membership['is_active'] ?? 1) === 1 ? 1 : 0;
        $userActive = (int) ($targetUser['is_active'] ?? 1) === 1 ? 1 : 0;
        $targetUser['workspace_role'] = trim((string) ($membership['role'] ?? 'general_user'));
        $targetUser['membership_active'] = $membershipActive;
        $targetUser['effective_active'] = ($membershipActive === 1 && $userActive === 1) ? 1 : 0;

        return $targetUser;
    }

    private function defaultForm(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'password' => '',
            'password_confirm' => '',
            'workspace_role' => 'general_user',
        ];
    }

    private function formFromUser(array $user): array
    {
        return [
            'first_name' => trim((string) ($user['first_name'] ?? '')),
            'last_name' => trim((string) ($user['last_name'] ?? '')),
            'email' => trim((string) ($user['email'] ?? '')),
            'password' => '',
            'password_confirm' => '',
            'workspace_role' => trim((string) ($user['workspace_role'] ?? 'general_user')),
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
            'workspace_role' => trim((string) ($input['workspace_role'] ?? 'general_user')),
        ];
    }

    private function validateForm(array $form, ?int $targetUserId, bool $isCreate, bool $isSiteAdminGlobal): array
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

        $password = (string) ($form['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            }
            if (!hash_equals($password, (string) ($form['password_confirm'] ?? ''))) {
                $errors['password_confirm'] = 'Passwords do not match.';
            }
        }

        if (!$isSiteAdminGlobal && !array_key_exists($form['workspace_role'] ?? '', $this->workspaceRoleOptions())) {
            $errors['workspace_role'] = 'Select a valid workspace role.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function workspaceRoleOptions(): array
    {
        return [
            'general_user' => 'General User',
            'punch_only' => 'Punch Only',
            'admin' => 'Admin',
        ];
    }

    private function isGlobalSiteAdminContext(): bool
    {
        return is_site_admin() && current_business_id() <= 0;
    }

    private function generateTemporaryPassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@$%';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }

    private function sendInviteEmail(array $user, string $temporaryPassword, bool $isSiteAdminGlobal): bool
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return false;
        }

        $fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        $greeting = $fullName !== '' ? $fullName : $email;
        $workspaceLine = $isSiteAdminGlobal ? 'Access: Site Admin' : 'Access: Business Workspace';
        $subject = (string) config('mail.invite_subject', 'Your JunkTracker invite');
        $body = implode("\n", [
            'Hello ' . $greeting . ',',
            '',
            'You have been invited to JunkTracker.',
            $workspaceLine,
            'Login URL: ' . absolute_url('/login'),
            'Email: ' . $email,
            'Temporary Password: ' . $temporaryPassword,
            '',
            'This invite expires in 24 hours.',
            'You will be required to change your password after your first login.',
            '',
            'If you were not expecting this message, you can ignore it.',
        ]);

        return Mailer::send($email, $subject, $body);
    }
}
