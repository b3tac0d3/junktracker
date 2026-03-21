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
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'jimmys11_junktracker',
    'username' => 'jimmys11_junktracker_admin',
    'password' => 'X5.x~FT([IuZD;lknasdf02asA',
    'charset' => 'utf8mb4',
];
