<?php

declare(strict_types=1);

$env = strtolower(trim((string) (getenv('APP_ENV') ?: 'local')));
$debugRaw = getenv('APP_DEBUG');
$isDebug = $debugRaw !== false
    ? !in_array(strtolower(trim((string) $debugRaw)), ['0', 'false', 'off', 'no'], true)
    : in_array($env, ['local', 'development', 'dev'], true);

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$httpsFlag = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
$isHttps = ($httpsFlag !== '' && $httpsFlag !== 'off')
    || $forwardedProto === 'https'
    || $forwardedSsl === 'on';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocalHost = $host === ''
    || preg_match('/^(localhost|127\\.0\\.0\\.1)(:\\d+)?$/i', $host) === 1;
$forceHttpsRaw = strtolower(trim((string) (getenv('APP_FORCE_HTTPS') ?: '1')));
$shouldForceHttps = !in_array($forceHttpsRaw, ['0', 'false', 'off', 'no'], true);
if ($shouldForceHttps && !$isHttps && !$isLocalHost) {
    $target = 'https://' . (string) ($_SERVER['HTTP_HOST'] ?? '') . (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $target, true, 301);
    exit;
}

// Use an app-local session storage path so auth does not depend on host-level
// PHP session directory permissions.
$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

$secureCookie = $isHttps;
session_name('junktracker_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

require dirname(__DIR__) . '/app/bootstrap.php';

// Prevent stale HTML from being served for authenticated app routes.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), payment=()');

if (config('app.noindex', true)) {
    header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');
}

attempt_remember_login();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = $path === '' ? '/' : $path;
$publicPaths = [
    '/login',
    '/login/2fa',
    '/login/2fa/resend',
    '/set-password',
    '/register',
    '/forgot-password',
    '/privacy-policy',
    '/terms-and-conditions',
];

if (!is_authenticated()) {
    $isAsset = $path === '/assets' || str_starts_with($path, '/assets/');
    if (!$isAsset && !in_array($path, $publicPaths, true)) {
        redirect('/login');
    }
}

if (is_authenticated()) {
    $isGlobalContext = is_site_admin_global_context();
    $isAsset = $path === '/assets' || str_starts_with($path, '/assets/');
    if ($isGlobalContext && !$isAsset) {
        $allowedGlobalPrefixes = [
            '/site-admin',
            '/logout',
            '/settings',
            '/activity-log',
        ];

        $isAllowedGlobalPath = false;
        foreach ($allowedGlobalPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $isAllowedGlobalPath = true;
                break;
            }
        }

        if (!$isAllowedGlobalPath) {
            flash('error', 'Select a business workspace before accessing business data.');
            redirect('/site-admin');
        }
    }
}

$router = require BASE_PATH . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
