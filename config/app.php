<?php

declare(strict_types=1);

return [
    'name' => 'JunkTracker',
    'env' => 'local',
    'key' => getenv('APP_KEY') ?: 'local-dev-key-change-me',
    'url' => getenv('APP_URL') ?: 'http://localhost/junktracker',
    'timezone' => 'America/New_York',
];
