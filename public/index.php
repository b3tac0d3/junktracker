<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

require dirname(__DIR__) . '/app/bootstrap.php';

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
];

if (!is_authenticated()) {
    $isAsset = $path === '/assets' || str_starts_with($path, '/assets/');
    if (!$isAsset && !in_array($path, $publicPaths, true)) {
        redirect('/login');
    }
}

$router = require BASE_PATH . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
