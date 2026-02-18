<?php

declare(strict_types=1);

return [
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@junktracker.local',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'JunkTracker',
    'reply_to' => getenv('MAIL_REPLY_TO') ?: '',
    'subject_prefix' => getenv('MAIL_SUBJECT_PREFIX') ?: '[JunkTracker] ',
];
