<?php

declare(strict_types=1);

$config = [
    /** Access token lifetime (seconds). Default 30 days. */
    'access_token_ttl' => 60 * 60 * 24 * 30,
    /** Refresh token lifetime (seconds). Default 90 days. */
    'refresh_token_ttl' => 60 * 60 * 24 * 90,
    /**
     * CORS allowed origins for /api/* (exact match). Use ['*'] only in local dev.
     * Mobile native apps do not send Origin; browser/PWA clients do.
     */
    'cors_origins' => [],
    /** Firebase Cloud Messaging server key or service account path (optional). */
    'fcm_server_key' => (string) (getenv('JUNKMETRIX_FCM_SERVER_KEY') ?: ''),
];

$localOverride = __DIR__ . '/api.local.php';
if (is_file($localOverride)) {
    /** @var array<string, mixed> $override */
    $override = require $localOverride;
    $config = array_merge($config, $override);
}

return $config;
