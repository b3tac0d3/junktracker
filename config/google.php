<?php

declare(strict_types=1);

$config = [
    'client_id' => '750755942442-8sscaucogu06q8jpeki10h81eugn4hso.apps.googleusercontent.com',
    'client_secret' => '',
    'calendar_id' => 'primary',
    /** Optional: encrypt OAuth tokens at rest (32+ char random string). */
    'token_encryption_key' => '',
    'enabled' => true,
    'oauth_scopes' => [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/userinfo.email',
    ],
];

$localOverride = __DIR__ . '/google.local.php';
if (is_file($localOverride)) {
    /** @var array<string, mixed> $override */
    $override = require $localOverride;
    $config = array_merge($config, $override);
}

return $config;
