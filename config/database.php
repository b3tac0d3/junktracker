<?php

declare(strict_types=1);

$localOverride = __DIR__ . '/database.local.php';
if (is_file($localOverride)) {
    /** @var array<string, mixed> $override */
    $override = require $localOverride;
    return $override;
}

return [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'junk_tracker',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
];
