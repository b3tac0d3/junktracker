<?php

declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    $value = trim((string) $value);
    return $value === '' ? $default : $value;
};

$config = [
    'driver' => 'mysql',
    'host' => $env('JT_DB_HOST', $env('DB_HOST', '127.0.0.1')),
    'port' => (int) ($env('JT_DB_PORT', $env('DB_PORT', '3306')) ?? '3306'),
    'database' => $env('JT_DB_NAME', $env('DB_DATABASE', 'junk_tracker')),
    'username' => $env('JT_DB_USER', $env('DB_USERNAME', 'root')),
    'password' => $env('JT_DB_PASS', $env('DB_PASSWORD', 'root')),
    'charset' => $env('JT_DB_CHARSET', 'utf8mb4'),
];

$localOverridePath = __DIR__ . '/database.local.php';
if (is_file($localOverridePath)) {
    $overrides = require $localOverridePath;
    if (is_array($overrides)) {
        $config = array_replace($config, $overrides);
    }
}

return $config;
