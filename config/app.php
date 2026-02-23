<?php

declare(strict_types=1);

return [
    'name' => 'JunkTracker',
    'version' => '2.0.1',
    'env' => getenv('APP_ENV') ?: 'local',
    'key' => getenv('APP_KEY') ?: 'local-dev-key-change-me',
    // Keep blank by default so absolute links use the current request host.
    'url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    // Default to noindex for this app so it never gets indexed accidentally.
    'noindex' => getenv('APP_NOINDEX') !== '0',
    'two_factor_enabled' => getenv('APP_2FA_ENABLED') !== '0',
    'timezone' => 'America/New_York',
    'default_business_id' => (int) (getenv('APP_DEFAULT_BUSINESS_ID') ?: 1),
];
