<?php

declare(strict_types=1);

return [
    'name' => 'JunkTracker',
    'version' => '2.1.5 (beta)',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => getenv('APP_DEBUG') !== false
        ? !in_array(strtolower(trim((string) getenv('APP_DEBUG'))), ['0', 'false', 'off', 'no'], true)
        : in_array(strtolower((string) (getenv('APP_ENV') ?: 'local')), ['local', 'development', 'dev'], true),
    'key' => getenv('APP_KEY') ?: 'local-dev-key-change-me',
    // Keep blank by default so absolute links use the current request host.
    'url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    // Default to noindex for this app so it never gets indexed accidentally.
    'noindex' => getenv('APP_NOINDEX') !== '0',
    'two_factor_enabled' => getenv('APP_2FA_ENABLED') !== '0',
    'timezone' => 'America/New_York',
    'default_business_id' => (int) (getenv('APP_DEFAULT_BUSINESS_ID') ?: 1),
    'dashboard_cache_ttl' => (int) (getenv('APP_DASHBOARD_CACHE_TTL') ?: 60),
    'schema_cache_ttl' => (int) (getenv('APP_SCHEMA_CACHE_TTL') ?: 600),
];
