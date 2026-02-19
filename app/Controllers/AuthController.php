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

        $errorCode = trim((string) ($_GET['error'] ?? ''));
        $this->render('auth/login', [
            'pageTitle' => 'Login',
            'fallbackError' => $this->errorMessageForCode($errorCode),
        ], 'auth');

        clear_old();
    }

    public function authenticate(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        $csrfValid = verify_csrf($_POST['csrf_token'] ?? null);
        if (!$csrfValid && !$this->isSameOriginRequest()) {
            $this->redirectLoginError('session_expired');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if ($email === '' || $password === '') {
            $this->redirectLoginError('missing_credentials', $email);
        }

        try {
            $user = User::findByEmail($email);
        } catch (\Throwable) {
            $this->redirectLoginError('login_unavailable', $email);
        }
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            $this->redirectLoginError('invalid_credentials', $email);
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        if ($passwordHash === '') {
            $this->redirectLoginError('password_not_set', $email);
        }

        if (!password_verify($password, $passwordHash)) {
            $this->redirectLoginError('invalid_credentials', $email);
        }

        $twoFactorEnabled = function_exists('is_two_factor_enabled')
            ? is_two_factor_enabled()
            : (bool) config('app.two_factor_enabled', true);

        if (!$twoFactorEnabled) {
            User::clearTwoFactorCode((int) $user['id'], false);
            $this->finalizeLogin($user, $remember, false);
            return;
        }

        if (has_valid_two_factor_trust_cookie($user)) {
            User::clearTwoFactorCode((int) $user['id'], false);
            $this->finalizeLogin($user, $remember, false);
            return;
        }

        $started = $this->beginTwoFactorChallenge($user, $remember);
        if (!$started) {
            $this->redirectLoginError('two_factor_unavailable', $email);
        }

        redirect('/login/2fa');
    }

    public function twoFactor(): void
    {
        if (is_authenticated()) {
            redirect('/');
        }

        $twoFactorEnabled = function_exists('is_two_factor_enabled')
            ? is_two_factor_enabled()
            : (bool) config('app.two_factor_enabled', true);
        if (!$twoFactorEnabled) {
            redirect('/login');
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

        $twoFactorEnabled = function_exists('is_two_factor_enabled')
            ? is_two_factor_enabled()
            : (bool) config('app.two_factor_enabled', true);
        if (!$twoFactorEnabled) {
            redirect('/login');
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

        $twoFactorEnabled = function_exists('is_two_factor_enabled')
            ? is_two_factor_enabled()
            : (bool) config('app.two_factor_enabled', true);
        if (!$twoFactorEnabled) {
            redirect('/login');
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
        // Keep logout functional even if the session token is unavailable.
        verify_csrf($_POST['csrf_token'] ?? null);

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

    private function redirectLoginError(string $code, string $email = ''): never
    {
        $message = $this->errorMessageForCode($code);
        if ($message !== '') {
            flash('error', $message);
        }

        $cleanEmail = trim($email);
        if ($cleanEmail !== '') {
            flash_old(['email' => $cleanEmail]);
        }

        $query = '?error=' . urlencode($code);
        if ($cleanEmail !== '') {
            $query .= '&email=' . urlencode($cleanEmail);
        }

        redirect('/login' . $query);
    }

    private function errorMessageForCode(string $code): string
    {
        return match ($code) {
            'session_expired' => 'Your session expired. Please try again.',
            'missing_credentials' => 'Please enter your email and password.',
            'invalid_credentials' => 'Invalid credentials.',
            'password_not_set' => 'Your account setup is incomplete. Use your invite email to set a password.',
            'two_factor_unavailable' => 'Unable to send your verification code. Please try again.',
            'login_unavailable' => 'Login is temporarily unavailable. Please try again in a moment.',
            default => '',
        };
    }

    private function isSameOriginRequest(): bool
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === '') {
            return false;
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '') {
            $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?? ''));
            if ($originHost !== '') {
                return $originHost === $host;
            }
        }

        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '') {
            $refererHost = strtolower((string) (parse_url($referer, PHP_URL_HOST) ?? ''));
            if ($refererHost !== '') {
                return $refererHost === $host;
            }
        }

        return false;
    }
}
