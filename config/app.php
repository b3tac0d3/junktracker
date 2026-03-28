<?php

declare(strict_types=1);

return [
    'name' => 'JunkTracker',
    'version' => '1.3.9.1 (beta)',
    'env' => 'local',
    'url' => 'http://localhost/junktracker',
    'timezone' => 'America/New_York',
    'session_name' => 'junktracker_session',
    /**
     * PHP session file lifetime (seconds). The app raises this to at least remember-me persistence (~10y).
     * If the host ignores ini_set, set session.gc_maxlifetime in php.ini accordingly.
     */
    'session_gc_maxlifetime' => 259200,
    'default_business_id' => 1,
    'debug' => true,
    /** Shared secret for /cron/daily-digest?key= — leave empty to disable remote calls */
    'cron_key' => (string) (getenv('JUNKTRACKER_CRON_KEY') ?: ''),
];
