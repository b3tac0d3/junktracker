<?php

declare(strict_types=1);

function base_url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base === '/') {
        $base = '';
    }

    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function url(string $path = ''): string
{
    return base_url($path);
}

function asset(string $path = ''): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $valid;
}

function app_key(): string
{
    return (string) config('app.key', '');
}

function flash(string $key, ?string $value = null): ?string
{
    if (!isset($_SESSION)) {
        return null;
    }

    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    return null;
}

function flash_old(array $data): void
{
    if (!isset($_SESSION)) {
        return;
    }

    $_SESSION['flash']['old'] = $data;
}

function old(string $key, mixed $default = ''): mixed
{
    if (!isset($_SESSION['flash']['old'])) {
        return $default;
    }

    return $_SESSION['flash']['old'][$key] ?? $default;
}

function clear_old(): void
{
    if (isset($_SESSION['flash']['old'])) {
        unset($_SESSION['flash']['old']);
    }
}

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_user_id(): ?int
{
    $user = auth_user();
    if (!$user) {
        return null;
    }

    $id = isset($user['id']) ? (int) $user['id'] : 0;
    return $id > 0 ? $id : null;
}

function is_authenticated(): bool
{
    return auth_user() !== null;
}

function has_role(int $minimumRole): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    return (int) ($user['role'] ?? 0) >= $minimumRole;
}

function require_role(int $minimumRole): void
{
    if (!is_authenticated()) {
        redirect('/login');
    }

    if (!has_role($minimumRole)) {
        redirect('/401');
    }
}

function role_label(?int $role): string
{
    return match ($role) {
        1 => 'User',
        2 => 'Manager',
        3 => 'Admin',
        99 => 'Dev',
        default => 'Unknown',
    };
}

function format_datetime(?string $value): string
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('m/d/Y g:i A', $timestamp);
}

function format_datetime_local(?string $value): string
{
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\\TH:i', $timestamp);
}

function format_date(?string $value): string
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('m/d/Y', $timestamp);
}

function format_phone(?string $value): string
{
    if (empty($value)) {
        return '—';
    }

    $digits = preg_replace('/\\D+/', '', $value);
    if ($digits === null) {
        return $value;
    }

    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }

    if (strlen($digits) === 7) {
        return sprintf('%s-%s', substr($digits, 0, 3), substr($digits, 3));
    }

    return $value;
}

function remember_login(array $user, bool $remember): void
{
    if (!$remember) {
        clear_remember_cookie();
        return;
    }

    $userId = (int) ($user['id'] ?? 0);
    $passwordHash = (string) ($user['password_hash'] ?? '');
    if ($userId <= 0 || $passwordHash === '') {
        return;
    }

    $signature = hash_hmac('sha256', $userId . '|' . $passwordHash, app_key());
    $payload = base64_encode($userId . ':' . $signature);

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('remember_token', $payload, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function attempt_remember_login(): void
{
    if (is_authenticated() || empty($_COOKIE['remember_token'])) {
        return;
    }

    $decoded = base64_decode($_COOKIE['remember_token'], true);
    if ($decoded === false || !str_contains($decoded, ':')) {
        clear_remember_cookie();
        return;
    }

    [$id, $signature] = explode(':', $decoded, 2);
    if (!ctype_digit($id)) {
        clear_remember_cookie();
        return;
    }

    $user = \App\Models\User::findByIdWithPassword((int) $id);
    if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
        clear_remember_cookie();
        return;
    }

    $expected = hash_hmac('sha256', $id . '|' . ($user['password_hash'] ?? ''), app_key());
    if (!hash_equals($expected, $signature)) {
        clear_remember_cookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'role' => $user['role'] ?? null,
    ];
}

function config(string $key, mixed $default = null): mixed
{
    static $configs = [];

    $segments = explode('.', $key);
    $file = array_shift($segments);

    if (!isset($configs[$file])) {
        $path = CONFIG_PATH . '/' . $file . '.php';
        $configs[$file] = file_exists($path) ? require $path : [];
    }

    $value = $configs[$file] ?? [];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}
