<?php

declare(strict_types=1);

$env = (string) (require __DIR__ . '/app.php')['env'];

return [
    'transport' => $env === 'production' ? 'mail' : 'log',
    'from_address' => 'no-reply@junktracker.jimmysjunk.com',
    'from_name' => 'JunkTracker',
    'invite_subject' => 'Your JunkTracker invite',
    'reset_subject' => 'Your JunkTracker password reset link',
];
