<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$basePath = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}

if (str_starts_with($path, '/api/')) {
    api_handle_cors();
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $router = require dirname(__DIR__) . '/routes/api.php';
    $router->dispatch($method, $uri);
    exit;
}

$router = require dirname(__DIR__) . '/routes/web.php';
$router->dispatch($method, $uri);
