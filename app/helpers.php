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

function absolute_url(string $path = ''): string
{
    $configured = trim((string) config('app.url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . base_url($path);
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

    return hash_equals($_SESSION['csrf_token'], $token);
}

function expects_json_response(): bool
{
    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($requestedWith === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));
    if ($accept !== '' && str_contains($accept, 'application/json')) {
        return true;
    }

    $ajaxFlag = strtolower(trim((string) ($_POST['ajax'] ?? $_GET['ajax'] ?? '')));
    return in_array($ajaxFlag, ['1', 'true', 'yes'], true);
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}

function stream_csv_download(string $filename, array $headerRow, array $rows): never
{
    $safeName = trim($filename) !== '' ? trim($filename) : ('export-' . date('Ymd-His') . '.csv');
    if (!str_ends_with(strtolower($safeName), '.csv')) {
        $safeName .= '.csv';
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');

    $output = fopen('php://output', 'w');
    if ($output !== false) {
        fputcsv($output, $headerRow);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    exit;
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

function auth_user_role(): int
{
    $user = auth_user();
    if (!$user) {
        return 0;
    }

    return (int) ($user['role'] ?? 0);
}

function current_business_id(): int
{
    $fallback = (int) config('app.default_business_id', 1);
    if ($fallback <= 0) {
        $fallback = 1;
    }

    $user = auth_user();
    if (!$user) {
        return $fallback;
    }

    $role = (int) ($user['role'] ?? 0);
    if ($role >= 4 || $role === 99) {
        $activeBusinessId = (int) ($_SESSION['active_business_id'] ?? 0);
        if ($activeBusinessId > 0) {
            return $activeBusinessId;
        }

        // Site admins/dev are global users; do not bind scope to their user record.
        return $fallback;
    }

    $businessId = isset($user['business_id']) ? (int) $user['business_id'] : 0;
    return $businessId > 0 ? $businessId : $fallback;
}

function set_active_business_id(int $businessId): void
{
    if (!isset($_SESSION)) {
        return;
    }

    if ($businessId <= 0) {
        unset($_SESSION['active_business_id']);
        return;
    }

    $_SESSION['active_business_id'] = $businessId;
}

function request_ip_address(): ?string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        if (str_contains($candidate, ',')) {
            $candidate = trim((string) explode(',', $candidate)[0]);
        } else {
            $candidate = trim($candidate);
        }

        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return null;
}

function request_user_agent(): ?string
{
    $value = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($value === '') {
        return null;
    }

    return substr($value, 0, 512);
}

function request_geo_payload(?array $source = null): array
{
    $input = is_array($source) ? $source : $_POST;

    $latitude = normalize_geo_coordinate($input['geo_lat'] ?? null, -90.0, 90.0);
    $longitude = normalize_geo_coordinate($input['geo_lng'] ?? null, -180.0, 180.0);
    $accuracy = normalize_geo_accuracy($input['geo_accuracy'] ?? null);

    $sourceLabel = trim((string) ($input['geo_source'] ?? ''));
    if ($sourceLabel === '') {
        $sourceLabel = null;
    } else {
        $sourceLabel = substr($sourceLabel, 0, 32);
    }

    $capturedAt = normalize_geo_captured_at($input['geo_captured_at'] ?? null);

    return [
        'lat' => $latitude,
        'lng' => $longitude,
        'accuracy' => $accuracy,
        'source' => $sourceLabel,
        'captured_at' => $capturedAt,
    ];
}

function geo_capture_fields(string $source = 'browser'): string
{
    $safeSource = e(substr(trim($source) !== '' ? trim($source) : 'browser', 0, 32));

    return implode("\n", [
        '<input type="hidden" name="geo_lat" value="" />',
        '<input type="hidden" name="geo_lng" value="" />',
        '<input type="hidden" name="geo_accuracy" value="" />',
        '<input type="hidden" name="geo_captured_at" value="" />',
        '<input type="hidden" name="geo_source" value="' . $safeSource . '" />',
    ]);
}

function normalize_geo_coordinate(mixed $value, float $min, float $max): ?float
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }

    $coordinate = (float) $raw;
    if ($coordinate < $min || $coordinate > $max) {
        return null;
    }

    return round($coordinate, 7);
}

function normalize_geo_accuracy(mixed $value): ?float
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }

    $accuracy = (float) $raw;
    if ($accuracy < 0) {
        return null;
    }

    return round($accuracy, 2);
}

function normalize_geo_captured_at(mixed $value): ?string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function login_method_label(?string $method): string
{
    return match (trim((string) $method)) {
        'two_factor' => 'Password + 2FA',
        'remember_token' => 'Remember Token',
        default => 'Password',
    };
}

function log_user_action(string $actionKey, ?string $entityTable = null, ?int $entityId = null, ?string $summary = null, ?string $details = null): void
{
    $userId = auth_user_id();
    if ($userId === null) {
        return;
    }

    $normalizedActionKey = trim($actionKey);
    if ($normalizedActionKey === '') {
        $normalizedActionKey = 'event';
    }

    $summaryText = trim((string) ($summary ?? ''));
    if ($summaryText === '') {
        $summaryText = ucwords(str_replace('_', ' ', $normalizedActionKey));
    }

    $ip = request_ip_address();

    try {
        \App\Models\UserAction::create([
            'user_id' => $userId,
            'action_key' => $normalizedActionKey,
            'entity_table' => $entityTable,
            'entity_id' => $entityId,
            'summary' => $summaryText,
            'details' => $details,
            'ip_address' => $ip,
        ]);
    } catch (\Throwable) {
        // Logging should never break the user flow.
    }
}

function record_user_login_event(array $user, string $loginMethod = 'password'): void
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    $context = [
        'login_method' => $loginMethod,
        'ip_address' => request_ip_address(),
        'user_agent' => request_user_agent(),
    ];

    $record = null;
    try {
        $record = \App\Models\UserLoginRecord::create($userId, $context);
    } catch (\Throwable) {
        $record = null;
    }

    if (auth_user_id() !== $userId) {
        return;
    }

    $record = is_array($record) ? $record : $context;
    $browser = trim((string) ($record['browser_name'] ?? ''));
    $browserVersion = trim((string) ($record['browser_version'] ?? ''));
    if ($browser !== '' && $browserVersion !== '') {
        $browser .= ' ' . $browserVersion;
    }
    $osName = trim((string) ($record['os_name'] ?? ''));
    $deviceType = trim((string) ($record['device_type'] ?? ''));
    $methodLabel = login_method_label((string) ($record['login_method'] ?? $loginMethod));

    $summary = 'Successful login via ' . $methodLabel;
    if ($browser !== '' && $osName !== '') {
        $summary .= ' (' . $browser . ' on ' . $osName . ')';
    } elseif ($browser !== '') {
        $summary .= ' (' . $browser . ')';
    } elseif ($osName !== '') {
        $summary .= ' (' . $osName . ')';
    }

    $details = [];
    if ($deviceType !== '') {
        $details[] = 'Device: ' . ucfirst($deviceType);
    }

    $ipAddress = trim((string) ($record['ip_address'] ?? $context['ip_address'] ?? ''));
    if ($ipAddress !== '') {
        $details[] = 'IP: ' . $ipAddress;
    }

    $userAgent = trim((string) ($record['user_agent'] ?? $context['user_agent'] ?? ''));
    if ($userAgent !== '') {
        $details[] = 'Agent: ' . substr($userAgent, 0, 255);
    }

    log_user_action(
        'user_login',
        'user_login_records',
        isset($record['id']) ? (int) $record['id'] : null,
        $summary,
        !empty($details) ? implode(' | ', $details) : null
    );
}

function is_authenticated(): bool
{
    return auth_user() !== null;
}

function setting(string $key, mixed $default = null): mixed
{
    $normalized = trim($key);
    if ($normalized === '') {
        return $default;
    }

    try {
        if (class_exists(\App\Models\AppSetting::class) && \App\Models\AppSetting::isAvailable()) {
            $value = \App\Models\AppSetting::get($normalized, null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
    } catch (\Throwable) {
        // Fallback to provided default.
    }

    return $default;
}

function setting_bool(string $key, bool $default = false): bool
{
    $raw = setting($key, $default ? '1' : '0');
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_numeric($raw)) {
        return (int) $raw === 1;
    }

    $value = strtolower(trim((string) $raw));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function has_role(int $minimumRole): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    return (int) ($user['role'] ?? 0) >= $minimumRole;
}

function is_punch_only_role(): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    return (int) ($user['role'] ?? 0) === 0;
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

function is_two_factor_enabled(): bool
{
    $configEnabled = (bool) config('app.two_factor_enabled', true);
    if (!$configEnabled) {
        return false;
    }

    return setting_bool('security.two_factor_enabled', $configEnabled);
}

function can_access(string $module, string $action = 'view'): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    $role = (int) ($user['role'] ?? 0);
    if ($role >= 4 || $role === 99) {
        return true;
    }

    try {
        if (class_exists(\App\Models\RolePermission::class) && \App\Models\RolePermission::isAvailable()) {
            return \App\Models\RolePermission::allows($role, $module, $action);
        }
    } catch (\Throwable) {
        // Fall through to permissive behavior for compatibility.
    }

    return true;
}

function require_permission(string $module, string $action = 'view'): void
{
    if (!is_authenticated()) {
        redirect('/login');
    }

    if (!can_access($module, $action)) {
        redirect('/401');
    }
}

function lookup_options(string $groupKey, array $fallback = []): array
{
    $normalized = trim($groupKey);
    if ($normalized === '') {
        return $fallback;
    }

    try {
        if (class_exists(\App\Models\LookupOption::class) && \App\Models\LookupOption::isAvailable()) {
            \App\Models\LookupOption::seedDefaults();
            $rows = \App\Models\LookupOption::options($normalized);
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (\Throwable) {
        // Ignore and use fallback options.
    }

    return $fallback;
}

function role_label(?int $role): string
{
    return match ($role) {
        0 => 'Punch Only',
        1 => 'User',
        2 => 'Manager',
        3 => 'Admin',
        4 => 'Site Admin',
        99 => 'Dev',
        default => 'Unknown',
    };
}

function is_global_role_value(?int $role): bool
{
    $value = (int) ($role ?? 0);
    return $value >= 4 || $value === 99;
}

function assignable_role_options_for_user(?int $actorRole = null): array
{
    $role = $actorRole !== null ? (int) $actorRole : auth_user_role();
    $options = class_exists(\App\Models\RolePermission::class)
        ? \App\Models\RolePermission::roleOptions()
        : [
            0 => 'Punch Only',
            1 => 'User',
            2 => 'Manager',
            3 => 'Admin',
            4 => 'Site Admin',
            99 => 'Dev',
        ];

    if ($role === 99) {
        return $options;
    }

    if ($role >= 4) {
        unset($options[99]);
        return $options;
    }

    if ($role >= 3) {
        unset($options[4], $options[99]);
        return $options;
    }

    if ($role >= 2) {
        return array_intersect_key($options, array_flip([0, 1, 2]));
    }

    return array_intersect_key($options, array_flip([0, 1]));
}

function can_manage_role(?int $actorRole, ?int $targetRole): bool
{
    $actor = (int) ($actorRole ?? 0);
    $target = (int) ($targetRole ?? 0);

    if ($actor === 99) {
        return true;
    }

    return $actor >= $target;
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

    $format = (string) setting('display.datetime_format', 'm/d/Y g:i A');
    $timezone = (string) setting('display.timezone', (string) config('app.timezone', 'America/New_York'));
    try {
        $dt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone($timezone));
        return $dt->format($format !== '' ? $format : 'm/d/Y g:i A');
    } catch (\Throwable) {
        return date($format !== '' ? $format : 'm/d/Y g:i A', $timestamp);
    }
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

    $timezone = (string) setting('display.timezone', (string) config('app.timezone', 'America/New_York'));
    try {
        $dt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone($timezone));
        return $dt->format('Y-m-d\\TH:i');
    } catch (\Throwable) {
        return date('Y-m-d\\TH:i', $timestamp);
    }
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

    $format = (string) setting('display.date_format', 'm/d/Y');
    $timezone = (string) setting('display.timezone', (string) config('app.timezone', 'America/New_York'));
    try {
        $dt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone($timezone));
        return $dt->format($format !== '' ? $format : 'm/d/Y');
    } catch (\Throwable) {
        return date($format !== '' ? $format : 'm/d/Y', $timestamp);
    }
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

function set_two_factor_trust_cookie(array $user, int $days = 30): void
{
    $userId = (int) ($user['id'] ?? 0);
    $passwordHash = (string) ($user['password_hash'] ?? '');
    if ($userId <= 0 || $passwordHash === '') {
        return;
    }

    $expiresAt = time() + max(1, $days) * 86400;
    $signature = hash_hmac('sha256', $userId . '|' . $passwordHash . '|' . $expiresAt, app_key());
    $payload = base64_encode($userId . ':' . $expiresAt . ':' . $signature);

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('trusted_2fa', $payload, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_two_factor_trust_cookie(): void
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('trusted_2fa', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function has_valid_two_factor_trust_cookie(array $user): bool
{
    $payload = (string) ($_COOKIE['trusted_2fa'] ?? '');
    if ($payload === '') {
        return false;
    }

    $decoded = base64_decode($payload, true);
    if ($decoded === false || substr_count($decoded, ':') !== 2) {
        return false;
    }

    [$id, $expiresAt, $signature] = explode(':', $decoded, 3);
    if (!ctype_digit($id) || !ctype_digit($expiresAt)) {
        return false;
    }

    $userId = (int) ($user['id'] ?? 0);
    $passwordHash = (string) ($user['password_hash'] ?? '');
    if ($userId <= 0 || $passwordHash === '' || $userId !== (int) $id) {
        return false;
    }

    if ((int) $expiresAt < time()) {
        return false;
    }

    $expected = hash_hmac('sha256', $id . '|' . $passwordHash . '|' . $expiresAt, app_key());
    return hash_equals($expected, $signature);
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

    if (is_two_factor_enabled() && !has_valid_two_factor_trust_cookie($user)) {
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
        'business_id' => isset($user['business_id']) ? (int) $user['business_id'] : 1,
    ];

    $role = (int) ($user['role'] ?? 0);
    if ($role >= 4 || $role === 99) {
        set_active_business_id(0);
    } else {
        set_active_business_id(0);
    }

    try {
        \App\Models\User::clearFailedLogin((int) ($user['id'] ?? 0));
        \App\Models\AuthLoginAttempt::record(
            (string) ($user['email'] ?? ''),
            (int) ($user['id'] ?? 0),
            request_ip_address(),
            'success',
            'remember_token',
            request_user_agent()
        );
    } catch (\Throwable) {
        // Never block login recovery.
    }

    record_user_login_event($user, 'remember_token');
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
