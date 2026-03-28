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
        $mail = require base_path('config/mail.php');
        $__appConfig = [
            'app' => $app,
            'database' => $database,
            'mail' => $mail,
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

function absolute_url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    if ($base === '') {
        return url($path);
    }

    return $base . '/' . ltrim($path, '/');
}

/** Public client portal URL (magic token). */
function portal_url(string $token): string
{
    return url('/portal/' . rawurlencode($token));
}

function asset(string $path = ''): string
{
    $base = url('assets/' . ltrim($path, '/'));
    $rel = ltrim($path, '/');
    $ver = (string) config('app.version', '');
    $ver = preg_replace('/[^a-zA-Z0-9._-]+/', '', $ver) ?? '';

    $params = [];
    if ($ver !== '') {
        $params['v'] = $ver;
    }

    // Local: bust browser cache when the file changes (edits to CSS/JS otherwise keep the same ?v= URL).
    if ($rel !== '' && (string) config('app.env', 'production') === 'local') {
        $fullPath = base_path('public/assets/' . $rel);
        if (is_file($fullPath)) {
            $params['t'] = (string) filemtime($fullPath);
        }
    }

    if ($params === []) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
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

function write_log_entry(string $channel, array $payload): void
{
    $channel = trim($channel);
    if ($channel === '') {
        $channel = 'app';
    }

    $logDir = base_path('storage/logs');
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $payload['timestamp'] = $payload['timestamp'] ?? date('c');
    $logFile = sprintf('%s/%s-%s.log', $logDir, preg_replace('/[^a-z0-9_-]+/i', '-', $channel), date('Y-m-d'));
    @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
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

function auth_requires_password_change(): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    return !empty($user['must_change_password']) || !empty($user['invitation_pending']);
}

function require_auth(): void
{
    if (auth_user() === null) {
        redirect('/login');
    }

    if (!auth_requires_password_change()) {
        return;
    }

    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $allowedPaths = [
        url('/settings'),
        url('/settings/update'),
        url('/logout'),
    ];

    if (!in_array($path, $allowedPaths, true)) {
        redirect('/settings');
    }
}

function require_role(array $roles): void
{
    require_auth();
    if (!in_array(auth_role(), $roles, true)) {
        \Core\ErrorHandler::renderHttpError(403, 'Access denied', 'You do not have permission to access this area.');
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
        \Core\ErrorHandler::renderHttpError(403, 'Access denied', 'You do not have permission to access this area.');
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

function format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '—';
    }

    return date('m/d/Y', $timestamp);
}

/** USD for display; negatives render as -$75.00 (minus before the dollar sign). */
function format_money_usd(float $amount): string
{
    $sign = $amount < 0 ? '-' : '';

    return $sign . '$' . number_format(abs($amount), 2, '.', '');
}

function format_phone(?string $value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }

    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits) || $digits === '') {
        return $raw;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) !== 10) {
        return $raw;
    }

    return sprintf('(%s) %s - %s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
}

/**
 * @return array<int, int>
 */
function pagination_per_page_options(): array
{
    return [25, 50, 100, 200];
}

/**
 * @return array<string, string>
 */
function us_state_options(): array
{
    return [
        '' => 'Select state',
        'AL' => 'AL',
        'AK' => 'AK',
        'AZ' => 'AZ',
        'AR' => 'AR',
        'CA' => 'CA',
        'CO' => 'CO',
        'CT' => 'CT',
        'DE' => 'DE',
        'FL' => 'FL',
        'GA' => 'GA',
        'HI' => 'HI',
        'ID' => 'ID',
        'IL' => 'IL',
        'IN' => 'IN',
        'IA' => 'IA',
        'KS' => 'KS',
        'KY' => 'KY',
        'LA' => 'LA',
        'ME' => 'ME',
        'MD' => 'MD',
        'MA' => 'MA',
        'MI' => 'MI',
        'MN' => 'MN',
        'MS' => 'MS',
        'MO' => 'MO',
        'MT' => 'MT',
        'NE' => 'NE',
        'NV' => 'NV',
        'NH' => 'NH',
        'NJ' => 'NJ',
        'NM' => 'NM',
        'NY' => 'NY',
        'NC' => 'NC',
        'ND' => 'ND',
        'OH' => 'OH',
        'OK' => 'OK',
        'OR' => 'OR',
        'PA' => 'PA',
        'RI' => 'RI',
        'SC' => 'SC',
        'SD' => 'SD',
        'TN' => 'TN',
        'TX' => 'TX',
        'UT' => 'UT',
        'VT' => 'VT',
        'VA' => 'VA',
        'WA' => 'WA',
        'WV' => 'WV',
        'WI' => 'WI',
        'WY' => 'WY',
        'DC' => 'DC',
    ];
}

function pagination_per_page(mixed $value, int $default = 25): int
{
    $perPage = (int) $value;
    $options = pagination_per_page_options();
    if (in_array($perPage, $options, true)) {
        return $perPage;
    }

    return in_array($default, $options, true) ? $default : $options[0];
}

function pagination_current_page(mixed $value): int
{
    $page = (int) $value;
    return $page > 0 ? $page : 1;
}

function pagination_total_pages(int $totalRows, int $perPage): int
{
    $safePerPage = max(1, $perPage);
    return max(1, (int) ceil(max(0, $totalRows) / $safePerPage));
}

function pagination_offset(int $page, int $perPage): int
{
    return max(0, (max(1, $page) - 1) * max(1, $perPage));
}

/**
 * @return array<int, int>
 */
function pagination_visible_pages(int $currentPage, int $totalPages, int $window = 5): array
{
    $currentPage = max(1, $currentPage);
    $totalPages = max(1, $totalPages);
    $window = max(3, $window);

    if ($totalPages <= $window) {
        return range(1, $totalPages);
    }

    $half = (int) floor($window / 2);
    $start = max(1, $currentPage - $half);
    $end = min($totalPages, $start + $window - 1);

    if ($end - $start + 1 < $window) {
        $start = max(1, $end - $window + 1);
    }

    return range($start, $end);
}

/**
 * @return array<string, string>
 */
function current_query_params(array $remove = []): array
{
    $params = is_array($_GET ?? null) ? $_GET : [];
    $clean = [];

    foreach ($params as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        if (in_array($key, $remove, true)) {
            continue;
        }

        if (is_array($value)) {
            continue;
        }

        $clean[$key] = (string) $value;
    }

    return $clean;
}

function query_with(array $overrides = [], array $remove = []): string
{
    $params = current_query_params($remove);

    foreach ($overrides as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }

        $params[$key] = (string) $value;
    }

    $query = http_build_query($params);
    return $query === '' ? '' : ('?' . $query);
}

/**
 * @return array<string, int>
 */
function pagination_meta(int $page, int $perPage, int $totalRows, int $currentCount): array
{
    $totalPages = pagination_total_pages($totalRows, $perPage);
    $page = min(max(1, $page), $totalPages);
    $offset = pagination_offset($page, $perPage);
    $from = $totalRows === 0 ? 0 : ($offset + 1);
    $to = min($offset + max(0, $currentCount), $totalRows);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total_rows' => max(0, $totalRows),
        'total_pages' => $totalPages,
        'from' => $from,
        'to' => $to,
    ];
}

function business_logo_public_path(string $relativePath): string
{
    return base_path('public/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
}

/**
 * Public URL for a business logo stored under public/, or null if missing.
 *
 * @param array<string, mixed>|null $business
 */
function business_logo_url(?array $business): ?string
{
    if (!is_array($business)) {
        return null;
    }
    $path = trim((string) ($business['logo_path'] ?? ''));
    if ($path === '') {
        return null;
    }
    $full = business_logo_public_path($path);
    if (!is_file($full)) {
        return null;
    }

    return url($path);
}

/**
 * Absolute URL for embedding in email or external links (requires app.url in config).
 *
 * @param array<string, mixed>|null $business
 */
function business_logo_absolute_url(?array $business): ?string
{
    if (!is_array($business)) {
        return null;
    }
    $rel = trim((string) ($business['logo_path'] ?? ''));
    if ($rel === '') {
        return null;
    }
    if (!is_file(business_logo_public_path($rel))) {
        return null;
    }

    return absolute_url($rel);
}

/** Max idle time in seconds before "Remember me" sessions are ended (72 hours). */
function remember_me_idle_seconds(): int
{
    return 72 * 3600;
}

/**
 * Enforce idle timeout for remembered sessions; refresh activity + cookie when active.
 */
function enforce_remember_me_idle_timeout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (empty($_SESSION['remember_me']) || auth_user() === null) {
        return;
    }

    $seconds = remember_me_idle_seconds();
    $last = (int) ($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > $seconds) {
        unset($_SESSION['user'], $_SESSION['remember_me'], $_SESSION['last_activity'], $_SESSION['active_business_id']);
        refresh_session_cookie_lifetime(0);
        flash('error', 'You were logged out after 72 hours of inactivity.');

        return;
    }

    $_SESSION['last_activity'] = time();
    refresh_session_cookie_lifetime($seconds);
}

/**
 * Refresh the PHP session cookie lifetime. Use 0 for a browser-session cookie.
 */
function refresh_session_cookie_lifetime(int $lifetimeSeconds): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $params = session_get_cookie_params();
    $expires = $lifetimeSeconds > 0 ? time() + $lifetimeSeconds : 0;

    $options = [
        'expires' => $expires,
        'path' => $params['path'] !== '' ? $params['path'] : '/',
        'domain' => $params['domain'] !== '' ? $params['domain'] : '',
        'secure' => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'],
    ];
    if (PHP_VERSION_ID >= 70300) {
        $options['samesite'] = $params['samesite'] ?? 'Lax';
    }

    setcookie(session_name(), session_id(), $options);
}
