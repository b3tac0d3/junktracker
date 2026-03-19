<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\BusinessMembership;
use App\Models\User;
use Core\Controller;

final class AuthController extends Controller
{
    public function resetPasswordForm(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        $user = $token !== '' ? User::findByPasswordResetToken($token) : null;

        $this->render('auth/reset_password', [
            'pageTitle' => 'Reset Password',
            'publicPage' => true,
            'token' => $token,
            'targetUser' => $user,
            'errors' => [],
            'form' => [
                'password' => '',
                'password_confirm' => '',
            ],
            'linkExpired' => $user === null,
        ]);
    }

    public function resetPassword(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Try again.');
            redirect('/reset-password/' . rawurlencode($token));
        }

        $user = $token !== '' ? User::findByPasswordResetToken($token) : null;
        if ($user === null) {
            $this->render('auth/reset_password', [
                'pageTitle' => 'Reset Password',
                'publicPage' => true,
                'token' => $token,
                'targetUser' => null,
                'errors' => [],
                'form' => [
                    'password' => '',
                    'password_confirm' => '',
                ],
                'linkExpired' => true,
            ]);
            return;
        }

        $form = [
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ];
        $errors = [];
        if ($form['password'] === '') {
            $errors['password'] = 'A new password is required.';
        } elseif (strlen($form['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (!hash_equals($form['password'], $form['password_confirm'])) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $this->render('auth/reset_password', [
                'pageTitle' => 'Reset Password',
                'publicPage' => true,
                'token' => $token,
                'targetUser' => $user,
                'errors' => $errors,
                'form' => $form,
                'linkExpired' => false,
            ]);
            return;
        }

        $userId = (int) ($user['id'] ?? 0);
        User::updateProfile($userId, [
            'password_hash' => password_hash($form['password'], PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ], $userId);
        User::clearPasswordReset($userId, $userId);
        User::markInvitationAccepted($userId, $userId);

        flash('success', 'Password updated. You can log in now.');
        redirect('/login');
    }

    public function login(): void
    {
        if (auth_user() !== null) {
            if (auth_requires_password_change()) {
                redirect('/settings');
            }

            redirect('/');
        }

        $this->render('auth/login', [
            'pageTitle' => 'Login',
            'publicPage' => true,
        ]);
    }

    public function authenticate(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Try again.');
            redirect('/login');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('error', 'Email and password are required.');
            redirect('/login');
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            flash('error', 'Invalid credentials.');
            redirect('/login');
        }

        $userId = (int) ($user['id'] ?? 0);
        $globalRole = trim((string) ($user['role'] ?? 'general_user'));
        $invitedAt = trim((string) ($user['invited_at'] ?? ''));
        $invitationAcceptedAt = trim((string) ($user['invitation_accepted_at'] ?? ''));
        $invitationExpiresAt = trim((string) ($user['invitation_expires_at'] ?? ''));
        $invitationExpired = $invitedAt !== ''
            && $invitationAcceptedAt === ''
            && $invitationExpiresAt !== ''
            && strtotime($invitationExpiresAt) !== false
            && strtotime($invitationExpiresAt) < time();

        if ($invitationExpired) {
            flash('error', 'This invite has expired. Ask an admin to resend it.');
            redirect('/login');
        }

        $invitationPending = $invitedAt !== '' && $invitationAcceptedAt === '';

        $sessionUser = [
            'id' => $userId,
            'email' => strtolower($email),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'role' => $globalRole,
            'must_change_password' => (int) ($user['must_change_password'] ?? 0) === 1,
            'invitation_pending' => $invitationPending,
            'workspace_role' => $globalRole === 'site_admin' ? 'site_admin' : 'general_user',
            'business_id' => 0,
        ];

        if ($globalRole !== 'site_admin') {
            $membership = BusinessMembership::firstActiveMembership($userId);
            if (!$membership) {
                flash('error', 'No active business membership found for this account.');
                redirect('/login');
            }

            $sessionUser['business_id'] = (int) ($membership['business_id'] ?? 0);
            $sessionUser['workspace_role'] = (string) ($membership['role'] ?? 'general_user');
            $_SESSION['active_business_id'] = $sessionUser['business_id'];
        } else {
            unset($_SESSION['active_business_id']);
        }

        $_SESSION['user'] = $sessionUser;

        if (!empty($sessionUser['must_change_password']) || !empty($sessionUser['invitation_pending'])) {
            flash('error', 'Temporary password detected. Set a new password before continuing.');
            redirect('/settings');
        }

        AuditLog::write(
            action: 'user_login',
            entity: 'users',
            entityId: $userId,
            businessId: $sessionUser['business_id'] > 0 ? (int) $sessionUser['business_id'] : null,
            userId: $userId,
            meta: ['role' => $sessionUser['role']]
        );

        if ($globalRole === 'site_admin' && current_business_id() <= 0) {
            redirect('/site-admin/businesses');
        }

        if (($sessionUser['workspace_role'] ?? '') === 'punch_only') {
            redirect('/time-tracking/punch-board');
        }

        redirect('/');
    }

    public function logout(): void
    {
        $userId = auth_user_id();
        if ($userId !== null) {
            AuditLog::write('user_logout', 'users', $userId, current_business_id() ?: null, $userId);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();

        redirect('/login');
    }
}
