<?php

declare(strict_types=1);

return [
    'name' => 'JunkTracker',
    'version' => '1.1.4 (beta)',
    'env' => 'local',
    'key' => getenv('APP_KEY') ?: 'local-dev-key-change-me',
    'url' => getenv('APP_URL') ?: 'http://localhost/junktracker',
    // Default to noindex for this app so it never gets indexed accidentally.
    'noindex' => getenv('APP_NOINDEX') !== '0',
    'two_factor_enabled' => getenv('APP_2FA_ENABLED') !== '0',
    'timezone' => 'America/New_York',
];
