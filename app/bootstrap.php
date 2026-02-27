<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

session_name((string) config('app.session_name', 'junktracker_session'));
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set((string) config('app.timezone', 'America/New_York'));

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => base_path('app/'),
        'Core\\' => base_path('core/'),
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
