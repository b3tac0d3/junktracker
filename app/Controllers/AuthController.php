<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Models\User;

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

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if ($email === '' || $password === '') {
            flash('error', 'Please enter your email and password.');
            redirect('/login');
        }

        $user = User::findByEmail($email);
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            flash('error', 'Invalid credentials.');
            redirect('/login');
        }

        if (!password_verify($password, $user['password_hash'] ?? '')) {
            flash('error', 'Invalid credentials.');
            redirect('/login');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'role' => $user['role'] ?? null,
        ];
        remember_login($user, $remember);

        redirect('/');
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

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        redirect('/login');
    }
}
