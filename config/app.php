<?php

declare(strict_types=1);

return [
    'name' => 'JunkTracker',
    'version' => '1.3.7.3 (beta)',
    'env' => 'local',
    'url' => 'http://localhost/junktracker',
    'timezone' => 'America/New_York',
    'session_name' => 'junktracker_session',
    'default_business_id' => 1,
    'debug' => true,
    /** Shared secret for /cron/daily-digest?key= — leave empty to disable remote calls */
    'cron_key' => (string) (getenv('JUNKTRACKER_CRON_KEY') ?: ''),
];
