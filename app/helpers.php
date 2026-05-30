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
        $google = require base_path('config/google.php');
        $__appConfig = [
            'app' => $app,
            'database' => $database,
            'mail' => $mail,
            'google' => $google,
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

function maps_directions_url(string $destination): string
{
    $destination = trim($destination);
    if ($destination === '') {
        return '';
    }

    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination);
}

/**
 * @param array<int, string|null> $parts
 */
function maps_directions_url_from_parts(array $parts): string
{
    $normalized = [];
    foreach ($parts as $part) {
        $value = trim((string) $part);
        if ($value === '') {
            continue;
        }
        $normalized[] = $value;
    }

    return maps_directions_url(implode(', ', $normalized));
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
        url('/settings/google-calendar/connect'),
        url('/settings/google-calendar/callback'),
        url('/settings/google-calendar/disconnect'),
        url('/settings/google-calendar/backfill'),
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

function can_view_financials(): bool
{
    if (is_site_admin()) {
        return true;
    }

    return workspace_role() === 'admin';
}

function require_financial_access(): void
{
    business_context_required();

    if (is_site_admin()) {
        return;
    }

    if (!can_view_financials()) {
        \Core\ErrorHandler::renderHttpError(403, 'Access denied', 'Financial data and reports are limited to workspace admins.');
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

/**
 * Parse ?at= from calendar slot clicks into datetime-local value (Y-m-d\TH:i).
 */
function calendar_slot_prefill_at(): string
{
    $raw = trim((string) ($_GET['at'] ?? ''));
    if ($raw === '') {
        return '';
    }

    $normalized = str_replace('T', ' ', urldecode($raw));
    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

/**
 * Default end time for calendar-created records (+N minutes from start local value).
 */
function calendar_slot_prefill_end_at(string $startLocal = '', int $minutes = 60): string
{
    if ($startLocal === '') {
        $startLocal = calendar_slot_prefill_at();
    }
    if ($startLocal === '') {
        return '';
    }

    $timestamp = strtotime(str_replace('T', ' ', $startLocal));
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp + ($minutes * 60));
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

function phone_tel_href(?string $value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '';
    }

    $hasLeadingPlus = str_starts_with($raw, '+');
    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits) || $digits === '') {
        return '';
    }

    if ($hasLeadingPlus) {
        return 'tel:+' . $digits;
    }

    if (strlen($digits) === 10) {
        return 'tel:+1' . $digits;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        return 'tel:+' . $digits;
    }

    return 'tel:' . $digits;
}

/**
 * Allowed "rows per page" values (shown in index pagination dropdowns).
 *
 * @return array<int, int>
 */
function format_client_percentage(?float $pct): string
{
    if ($pct === null) {
        return '—';
    }

    $formatted = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');

    return $formatted . '%';
}

function pagination_per_page_options(): array
{
    return [10, 25, 50, 100, 200];
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

/** Default page size when `per_page` is missing or invalid (must be in {@see pagination_per_page_options()}). */
function pagination_per_page(mixed $value, int $default = 25): int
{
    $perPage = (int) $value;
    $options = pagination_per_page_options();
    if (in_array($perPage, $options, true)) {
        return $perPage;
    }

    return in_array($default, $options, true) ? $default : $options[0];
}

function pagination_remembered_per_page(string $scope, string $param = 'per_page', int $default = 25): int
{
    if (array_key_exists($param, $_GET)) {
        $perPage = pagination_per_page($_GET[$param], $default);
        if (!isset($_SESSION['pagination']) || !is_array($_SESSION['pagination'])) {
            $_SESSION['pagination'] = [];
        }
        $businessId = max(0, current_business_id());
        $_SESSION['pagination'][$businessId][$scope] = $perPage;

        return $perPage;
    }

    $stored = null;
    if (isset($_SESSION['pagination']) && is_array($_SESSION['pagination'])) {
        $businessBlock = $_SESSION['pagination'][max(0, current_business_id())] ?? null;
        if (is_array($businessBlock)) {
            $stored = $businessBlock[$scope] ?? null;
        }
    }

    return pagination_per_page($stored, $default);
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

function safe_return_path(string $raw): string
{
    $path = trim($raw);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return '';
    }

    return $path;
}

function sanitize_detail_tab(string $tab, array $allowed, string $default = 'details'): string
{
    $tab = strtolower(trim($tab));
    return in_array($tab, $allowed, true) ? $tab : $default;
}

function request_detail_tab(array $allowed, string $default = 'details'): string
{
    foreach (['return_tab', 'tab'] as $key) {
        $raw = $_POST[$key] ?? $_GET[$key] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return sanitize_detail_tab($raw, $allowed, $default);
        }
    }

    return $default;
}

function detail_path_with_tab(string $basePath, ?string $tab = null, string $defaultTab = 'details'): string
{
    $basePath = '/' . ltrim(trim($basePath), '/');
    $tab = strtolower(trim((string) $tab));
    if ($tab === '' || $tab === strtolower($defaultTab)) {
        return $basePath;
    }

    return $basePath . '?tab=' . rawurlencode($tab);
}

function detail_return_tab_query(?string $tab = null, string $defaultTab = 'details'): string
{
    $tab = strtolower(trim((string) $tab));
    if ($tab === '' || $tab === strtolower($defaultTab)) {
        return '';
    }

    return '?return_tab=' . rawurlencode($tab);
}

function redirect_to_detail(string $basePath, ?string $tab = null, string $defaultTab = 'details'): never
{
    redirect(detail_path_with_tab($basePath, $tab, $defaultTab));
}

function detail_tab_hidden_field(?string $tab = null, string $defaultTab = 'details'): string
{
    $tab = strtolower(trim((string) $tab));
    if ($tab === '' || $tab === strtolower($defaultTab)) {
        return '';
    }

    return '<input type="hidden" name="return_tab" value="' . e($tab) . '" />';
}

/**
 * @param array<string, mixed> $sale
 * @return array{url: string, label: string, path: string}
 */
function sale_detail_back_meta(array $sale, string $returnTo = ''): array
{
    $path = safe_return_path($returnTo);
    if ($path === '') {
        $estateSaleId = (int) ($sale['estate_sale_id'] ?? 0);
        if ($estateSaleId > 0) {
            $path = '/estate-sales/' . $estateSaleId . '?tab=sales';

            return [
                'url' => url($path),
                'label' => 'Back to Estate Sale',
                'path' => $path,
            ];
        }

        return [
            'url' => url('/sales'),
            'label' => 'Back to Sales',
            'path' => '/sales',
        ];
    }

    if (preg_match('#^/estate-sales/\d+/customers/\d+#', $path)) {
        return ['url' => url($path), 'label' => 'Back to Customer', 'path' => $path];
    }
    if (preg_match('#^/estate-sales/\d+#', $path)) {
        return ['url' => url($path), 'label' => 'Back to Estate Sale', 'path' => $path];
    }
    if ($path === '/estate-sale-records' || str_starts_with($path, '/estate-sale-records?')) {
        return ['url' => url($path), 'label' => 'Back to Estate Sale Records', 'path' => $path];
    }

    return ['url' => url($path), 'label' => 'Back', 'path' => $path];
}

function sale_detail_url(int $saleId, string $returnTo = ''): string
{
    $url = url('/sales/' . (string) $saleId);
    $path = safe_return_path($returnTo);
    if ($path === '') {
        return $url;
    }

    return $url . '?return_to=' . rawurlencode($path);
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

/**
 * Session cookie expiry + server GC floor for "Remember me" (effectively until logout).
 * Sliding window: cookie is refreshed on each request so it does not expire while in use.
 */
function remember_me_persistent_seconds(): int
{
    return 10 * 365 * 24 * 3600;
}

/**
 * Cookie path for the front controller (e.g. /app/public/) so the session cookie matches the install URL.
 */
function session_cookie_base_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '/';
    }

    return rtrim($dir, '/') . '/';
}

/**
 * Path used when emitting Set-Cookie (falls back to SCRIPT_NAME-based path if PHP left path empty).
 */
function session_cookie_effective_path(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return session_cookie_base_path();
    }
    $p = (string) (session_get_cookie_params()['path'] ?? '');
    if ($p !== '') {
        return $p;
    }

    return session_cookie_base_path();
}

/**
 * Prefer Secure cookies when the app URL is https or the request is HTTPS.
 */
function session_cookie_secure_preferred(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    $appUrl = (string) config('app.url', '');
    if ($appUrl !== '' && str_starts_with(strtolower($appUrl), 'https://')) {
        return true;
    }

    return (bool) (session_get_cookie_params()['secure'] ?? false);
}

/**
 * Keep session cookies aligned with login: long-lived for "Remember me", browser session otherwise.
 * Re-sends Set-Cookie on each request so path/secure/samesite stay correct on shared hosts.
 */
function maintain_remember_me_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || auth_user() === null) {
        return;
    }
    if (!empty($_SESSION['remember_me'])) {
        refresh_session_cookie_lifetime(remember_me_persistent_seconds());
    } else {
        refresh_session_cookie_lifetime(0);
    }
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
        'path' => session_cookie_effective_path(),
        'domain' => $params['domain'] !== '' ? $params['domain'] : '',
        'secure' => session_cookie_secure_preferred(),
        'httponly' => (bool) ($params['httponly'] ?? true),
    ];
    if (PHP_VERSION_ID >= 70300) {
        $options['samesite'] = $params['samesite'] ?? 'Lax';
    }

    setcookie(session_name(), session_id(), $options);
}

function audit(string $action, string $entity, ?int $entityId, array $meta = []): void
{
    $businessId = current_business_id();
    \App\Models\AuditLog::write(
        $action,
        $entity,
        $entityId,
        $businessId > 0 ? $businessId : null,
        auth_user_id(),
        $meta
    );
}

function audit_action_label(string $action): string
{
    static $labels = [
        'user_login' => 'Logged in',
        'user_logout' => 'Logged out',
        'client_created' => 'Client created',
        'client_updated' => 'Client updated',
        'client_deactivated' => 'Client deactivated',
        'client_contact_created' => 'Client contact added',
        'client_bolo_saved' => 'BOLO profile saved',
        'client_bolo_deactivated' => 'BOLO profile deactivated',
        'client_bolo_reactivated' => 'BOLO profile reactivated',
        'job_created' => 'Job created',
        'job_updated' => 'Job updated',
        'job_deactivated' => 'Job deactivated',
        'job_status_updated' => 'Job status updated',
        'job_closeout_saved' => 'Job close-out saved',
        'job_expense_created' => 'Job expense added',
        'job_expense_updated' => 'Job expense updated',
        'job_expense_deleted' => 'Job expense deleted',
        'job_adjustment_created' => 'Job adjustment added',
        'job_adjustment_updated' => 'Job adjustment updated',
        'job_adjustment_deleted' => 'Job adjustment deleted',
        'job_employee_unassigned' => 'Employee removed from job',
        'estate_sale_created' => 'Estate sale created',
        'estate_sale_updated' => 'Estate sale updated',
        'estate_sale_deleted' => 'Estate sale deleted',
        'estate_sale_customer_created' => 'Estate sale customer added',
        'estate_sale_customer_attached' => 'Estate sale customer added to sale',
        'estate_sale_customer_updated' => 'Estate sale customer updated',
        'estate_sale_customer_removed' => 'Estate sale customer removed',
        'estate_sale_sale_created' => 'Estate sale transaction added',
        'estate_sale_sale_updated' => 'Estate sale transaction updated',
        'estate_sale_expense_created' => 'Estate sale expense added',
        'estate_sale_expense_removed' => 'Estate sale expense removed',
        'estate_sale_employee_assigned' => 'Employee assigned to estate sale',
        'estate_sale_employee_unassigned' => 'Employee removed from estate sale',
        'sale_created' => 'Sale created',
        'sale_updated' => 'Sale updated',
        'sale_deleted' => 'Sale deleted',
        'expense_created' => 'Expense created',
        'expense_updated' => 'Expense updated',
        'expense_deleted' => 'Expense deleted',
        'purchase_created' => 'Purchase created',
        'purchase_updated' => 'Purchase updated',
        'purchase_deleted' => 'Purchase deleted',
        'task_created' => 'Task created',
        'task_updated' => 'Task updated',
        'task_ownership_updated' => 'Task ownership updated',
        'event_created' => 'Event created',
        'event_updated' => 'Event updated',
        'event_cancelled' => 'Event cancelled',
        'event_restored' => 'Event restored',
        'event_moved' => 'Event rescheduled',
        'delivery_created' => 'Delivery created',
        'delivery_updated' => 'Delivery updated',
        'delivery_deleted' => 'Delivery deleted',
        'networking_contact_created' => 'Networking contact created',
        'networking_contact_updated' => 'Networking contact updated',
        'networking_contact_deleted' => 'Networking contact deleted',
        'user_created' => 'User invited',
        'user_updated' => 'User updated',
        'user_deactivated' => 'User deactivated',
        'user_reactivated' => 'User reactivated',
        'user_invite_resent' => 'User invite resent',
        'user_password_reset_sent' => 'Password reset sent',
        'user_auto_accepted' => 'User invite auto-accepted',
        'employee_created' => 'Employee created',
        'employee_updated' => 'Employee updated',
        'business_details_updated' => 'Business details updated',
        'invoice_item_type_created' => 'Invoice item type added',
        'invoice_item_type_updated' => 'Invoice item type updated',
        'invoice_item_type_deleted' => 'Invoice item type removed',
        'form_select_value_created' => 'Form option added',
        'form_select_value_updated' => 'Form option updated',
        'form_select_value_deleted' => 'Form option removed',
        'time_entry_created' => 'Time entry created',
        'time_entry_updated' => 'Time entry updated',
        'time_entry_deleted' => 'Time entry deleted',
        'invoice_created' => 'Invoice/estimate created',
        'invoice_updated' => 'Invoice/estimate updated',
        'invoice_deleted' => 'Invoice/estimate deleted',
        'invoice_status_changed' => 'Invoice/estimate status changed',
        'payment_created' => 'Payment added',
        'payment_updated' => 'Payment updated',
        'payment_deleted' => 'Payment deleted',
        'quote_created' => 'Quote created',
        'quote_updated' => 'Quote updated',
        'quote_status_updated' => 'Quote status updated',
        'quote_converted_to_job' => 'Quote converted to job',
        'quote_converted_to_estate_sale' => 'Quote converted to estate sale',
        'quote_converted_to_purchase' => 'Quote converted to purchase',
        'purchase_quote_created' => 'Purchase quote created',
        'purchase_quote_updated' => 'Purchase quote updated',
        'purchase_quote_status_updated' => 'Purchase quote status updated',
        'purchase_quote_converted' => 'Purchase quote converted to purchase',
        'purchase_quote_marked_lost' => 'Purchase quote marked lost',
        'purchase_quote_offer_added' => 'Purchase quote offer added',
        'purchase_quote_contact_logged' => 'Purchase quote contact logged',
        'bank_deposit_created' => 'Bank deposit recorded',
        'bank_deposit_payment_linked' => 'Payment linked to deposit',
        'bank_deposit_payment_unlinked' => 'Payment unlinked from deposit',
        'estimate_portal_approved' => 'Estimate approved (client portal)',
    ];

    if (isset($labels[$action])) {
        return $labels[$action];
    }

    return ucwords(str_replace('_', ' ', $action));
}

function audit_entity_label(string $entity): string
{
    static $labels = [
        'clients' => 'Client',
        'jobs' => 'Job',
        'estate_sales' => 'Estate sale',
        'estate_sale_customers' => 'Estate sale customer',
        'sales' => 'Sale',
        'expenses' => 'Expense',
        'purchases' => 'Purchase',
        'tasks' => 'Task',
        'events' => 'Event',
        'deliveries' => 'Delivery',
        'networking_contacts' => 'Networking contact',
        'users' => 'User',
        'employees' => 'Employee',
        'businesses' => 'Business',
        'invoice_item_types' => 'Invoice item type',
        'form_select_values' => 'Form option',
        'time_entries' => 'Time entry',
        'invoices' => 'Invoice/estimate',
        'payments' => 'Payment',
        'quotes' => 'Quote',
        'purchase_quotes' => 'Purchase quote',
        'bank_deposits' => 'Bank deposit',
    ];

    if (isset($labels[$entity])) {
        return $labels[$entity];
    }

    return ucwords(str_replace('_', ' ', $entity));
}

function audit_entity_url(string $entity, ?int $entityId, array $meta = []): ?string
{
    if ($entityId === null || $entityId <= 0) {
        return null;
    }

    return match ($entity) {
        'clients' => url('/clients/' . (string) $entityId),
        'jobs' => url('/jobs/' . (string) $entityId),
        'estate_sales' => url('/estate-sales/' . (string) $entityId),
        'estate_sale_customers' => (static function () use ($entityId, $meta): ?string {
            $estateSaleId = (int) ($meta['estate_sale_id'] ?? 0);
            return $estateSaleId > 0
                ? url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $entityId)
                : null;
        })(),
        'sales' => url('/sales/' . (string) $entityId),
        'expenses' => url('/expenses/' . (string) $entityId),
        'purchases' => url('/purchases/' . (string) $entityId),
        'tasks' => url('/tasks/' . (string) $entityId),
        'events' => url('/events/' . (string) $entityId),
        'deliveries' => url('/deliveries/' . (string) $entityId),
        'networking_contacts' => url('/networking/' . (string) $entityId),
        'users' => url('/admin/users/' . (string) $entityId . '/edit'),
        'employees' => url('/admin/employees/' . (string) $entityId),
        'quotes' => url('/quotes/' . (string) $entityId),
        'purchase_quotes' => url('/purchase-quotes/' . (string) $entityId),
        'invoices' => url('/billing/' . (string) $entityId),
        'payments' => url('/billing/payments/' . (string) $entityId),
        'bank_deposits' => url('/billing/deposits/' . (string) $entityId),
        'time_entries' => (static function () use ($meta): ?string {
            $jobId = (int) ($meta['job_id'] ?? 0);
            return $jobId > 0 ? url('/jobs/' . (string) $jobId) : url('/time-tracking');
        })(),
        default => null,
    };
}

function audit_user_display_name(?array $row): string
{
    if ($row === null) {
        return 'System';
    }

    $first = trim((string) ($row['user_first_name'] ?? $row['first_name'] ?? ''));
    $last = trim((string) ($row['user_last_name'] ?? $row['last_name'] ?? ''));
    $full = trim($first . ' ' . $last);
    if ($full !== '') {
        return $full;
    }

    $email = trim((string) ($row['user_email'] ?? $row['email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    $userId = (int) ($row['user_id'] ?? $row['id'] ?? 0);
    return $userId > 0 ? ('User #' . (string) $userId) : 'System';
}

function audit_metadata_summary(array $meta): string
{
    if ($meta === []) {
        return '';
    }

    $parts = [];
    foreach ($meta as $key => $value) {
        if ($value === null || $value === '' || is_array($value)) {
            continue;
        }
        $label = ucwords(str_replace('_', ' ', (string) $key));
        $parts[] = $label . ': ' . (string) $value;
    }

    return implode(' · ', $parts);
}
