<?php

declare(strict_types=1);

/** @var array<string, mixed> $__appConfig */
$__appConfig = [];

function base_path(string $path = ''): string
{
    $root = dirname(__DIR__);
    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

function config(string $key, mixed $default = null): mixed
{
    global $__appConfig;

    if ($key === '') {
        return $default;
    }

    if ($__appConfig === []) {
        $app = require base_path('config/app.php');
        $database = require base_path('config/database.php');
        $__appConfig = [
            'app' => $app,
            'database' => $database,
        ];
    }

    $segments = explode('.', $key);
    $value = $__appConfig;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function url(string $path = ''): string
{
    $base = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base === '/') {
        $base = '';
    }

    return $base . '/' . ltrim($path, '/');
}

function asset(string $path = ''): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    if ($token === null || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $token);
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return is_string($message) ? $message : null;
}

function auth_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function auth_user_id(): ?int
{
    $user = auth_user();
    if (!$user) {
        return null;
    }

    $id = (int) ($user['id'] ?? 0);
    return $id > 0 ? $id : null;
}

function auth_role(): string
{
    $user = auth_user();
    if (!$user) {
        return 'guest';
    }

    return trim((string) ($user['role'] ?? 'general_user')) ?: 'general_user';
}

function is_site_admin(): bool
{
    return auth_role() === 'site_admin';
}

function workspace_role(): string
{
    $user = auth_user();
    if (!$user) {
        return 'guest';
    }

    if (is_site_admin()) {
        $workspaceRole = trim((string) ($user['workspace_role'] ?? ''));
        return $workspaceRole !== '' ? $workspaceRole : 'site_admin';
    }

    $workspaceRole = trim((string) ($user['workspace_role'] ?? $user['role'] ?? 'general_user'));
    return $workspaceRole !== '' ? $workspaceRole : 'general_user';
}

function current_business_id(): int
{
    $active = (int) ($_SESSION['active_business_id'] ?? 0);
    if ($active > 0) {
        return $active;
    }

    $user = auth_user();
    if (!$user) {
        return 0;
    }

    if (is_site_admin()) {
        return 0;
    }

    $businessId = (int) ($user['business_id'] ?? 0);
    return $businessId > 0 ? $businessId : 0;
}

function require_auth(): void
{
    if (auth_user() === null) {
        flash('error', 'Please log in.');
        redirect('/login');
    }
}

function require_role(array $roles): void
{
    require_auth();
    if (!in_array(auth_role(), $roles, true)) {
        http_response_code(403);
        \Core\View::renderFile('errors/403', ['pageTitle' => 'Forbidden']);
        exit;
    }
}

function business_context_required(): void
{
    require_auth();

    if (is_site_admin() && current_business_id() <= 0) {
        flash('error', 'Pick a business workspace first.');
        redirect('/site-admin/businesses');
    }
}

function require_business_role(array $roles): void
{
    business_context_required();

    if (is_site_admin()) {
        return;
    }

    if (!in_array(workspace_role(), $roles, true)) {
        http_response_code(403);
        \Core\View::renderFile('errors/403', ['pageTitle' => 'Forbidden']);
        exit;
    }
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '—';
    }

    return date('m/d/Y g:i A', $timestamp);
}
