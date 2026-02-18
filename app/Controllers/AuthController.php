<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Support\Mailer;
use Core\Controller;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        $this->render('auth/login', [
            'pageTitle' => 'Login',
        ], 'auth');

        clear_old();
    }

    public function authenticate(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/login');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if ($email === '' || $password === '') {
            flash('error', 'Please enter your email and password.');
            flash_old(['email' => $email]);
            redirect('/login');
        }

        $user = User::findByEmail($email);
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            flash('error', 'Invalid credentials.');
            flash_old(['email' => $email]);
            redirect('/login');
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        if ($passwordHash === '') {
            flash('error', 'Your account setup is incomplete. Use your invite email to set a password.');
            flash_old(['email' => $email]);
            redirect('/login');
        }

        if (!password_verify($password, $passwordHash)) {
            flash('error', 'Invalid credentials.');
            flash_old(['email' => $email]);
            redirect('/login');
        }

        if (has_valid_two_factor_trust_cookie($user)) {
            User::clearTwoFactorCode((int) $user['id'], false);
            $this->finalizeLogin($user, $remember, false);
            return;
        }

        $started = $this->beginTwoFactorChallenge($user, $remember);
        if (!$started) {
            flash('error', 'Unable to send your verification code. Please try again.');
            redirect('/login');
        }

        redirect('/login/2fa');
    }

    public function twoFactor(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!is_array($pending) || empty($pending['user_id'])) {
            flash('error', 'Please login to continue.');
            redirect('/login');
        }

        $this->render('auth/two_factor', [
            'pageTitle' => 'Two-Factor Verification',
            'maskedEmail' => $this->maskEmail((string) ($pending['email'] ?? '')),
            'attemptsLeft' => max(0, 5 - (int) ($pending['attempts'] ?? 0)),
        ], 'auth');
    }

    public function verifyTwoFactor(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/login/2fa');
        }

        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!is_array($pending) || empty($pending['user_id'])) {
            flash('error', 'Your login session expired. Please login again.');
            redirect('/login');
        }

        $userId = (int) ($pending['user_id'] ?? 0);
        $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));
        if ($userId <= 0 || $code === null || strlen($code) !== 6) {
            flash('error', 'Enter the 6-digit code from your email.');
            redirect('/login/2fa');
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= 5) {
            unset($_SESSION['pending_2fa']);
            flash('error', 'Too many failed attempts. Please login again.');
            redirect('/login');
        }

        if (!User::verifyTwoFactorCode($userId, $code)) {
            $pending['attempts'] = $attempts + 1;
            $_SESSION['pending_2fa'] = $pending;
            flash('error', 'Invalid or expired code.');
            redirect('/login/2fa');
        }

        $user = User::findByIdWithPassword($userId);
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            unset($_SESSION['pending_2fa']);
            flash('error', 'Account unavailable.');
            redirect('/login');
        }

        User::clearTwoFactorCode($userId, true);

        $remember = !empty($pending['remember']);
        unset($_SESSION['pending_2fa']);
        $this->finalizeLogin($user, $remember, true);
    }

    public function resendTwoFactor(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/login/2fa');
        }

        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!is_array($pending) || empty($pending['user_id'])) {
            flash('error', 'Your login session expired. Please login again.');
            redirect('/login');
        }

        $user = User::findByIdWithPassword((int) ($pending['user_id'] ?? 0));
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            unset($_SESSION['pending_2fa']);
            flash('error', 'Account unavailable.');
            redirect('/login');
        }

        if (!$this->sendTwoFactorCode($user)) {
            flash('error', 'Unable to resend verification code right now.');
            redirect('/login/2fa');
        }

        flash('success', 'A new verification code was sent.');
        redirect('/login/2fa');
    }

    public function showSetPassword(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        $token = trim((string) ($_GET['token'] ?? ''));
        $inviteUser = $token !== '' ? User::findByPasswordSetupToken($token) : null;

        $this->render('auth/set_password', [
            'pageTitle' => 'Set Password',
            'token' => $token,
            'inviteUser' => $inviteUser,
        ], 'auth');

        clear_old();
    }

    public function storeSetPassword(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/set-password');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($token === '') {
            flash('error', 'Password setup link is missing.');
            redirect('/set-password');
        }

        $user = User::findByPasswordSetupToken($token);
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            flash('error', 'That setup link is invalid or expired.');
            redirect('/set-password');
        }

        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/set-password?token=' . urlencode($token));
        }

        User::completePasswordSetup((int) $user['id'], $password);
        flash('success', 'Password set. You can now login.');
        redirect('/login');
    }

    public function register(): void
    {
        $this->render('auth/register', [
            'pageTitle' => 'Register',
        ], 'auth');
    }

    public function forgot(): void
    {
        $this->render('auth/forgot', [
            'pageTitle' => 'Password Recovery',
        ], 'auth');
    }

    public function logout(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect('/');
        }

        $_SESSION = [];
        clear_remember_cookie();
        clear_two_factor_trust_cookie();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        redirect('/login');
    }

    private function beginTwoFactorChallenge(array $user, bool $remember): bool
    {
        if (!$this->sendTwoFactorCode($user)) {
            return false;
        }

        $_SESSION['pending_2fa'] = [
            'user_id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'remember' => $remember ? 1 : 0,
            'attempts' => 0,
        ];

        return true;
    }

    private function sendTwoFactorCode(array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = trim((string) ($user['email'] ?? ''));
        if ($userId <= 0 || $email === '') {
            return false;
        }

        $code = (string) random_int(100000, 999999);
        User::saveTwoFactorCode($userId, $code, 15);

        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        if ($name === '') {
            $name = 'there';
        }

        $subject = 'Your login verification code';
        $body = "Hi {$name},\n\n"
            . "Your JunkTracker verification code is: {$code}\n\n"
            . "This code expires in 15 minutes.\n"
            . "If you did not request this login, you can ignore this email.\n";

        return Mailer::send($email, $subject, $body);
    }

    private function finalizeLogin(array $user, bool $remember, bool $setTrustCookie): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'role' => $user['role'] ?? null,
        ];

        remember_login($user, $remember);
        if ($setTrustCookie) {
            set_two_factor_trust_cookie($user, 30);
        }

        redirect('/');
    }

    private function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            return 'your email';
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '') {
            return 'your email';
        }

        $visible = substr($local, 0, 2);
        return $visible . str_repeat('*', max(2, strlen($local) - 2)) . '@' . $domain;
    }
}
