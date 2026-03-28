<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ((string) config('app.env', 'production') === 'local' && function_exists('opcache_reset')) {
    @opcache_reset();
}

session_name((string) config('app.session_name', 'junktracker_session'));
if (session_status() !== PHP_SESSION_ACTIVE) {
    $gcMax = (int) config('app.session_gc_maxlifetime', remember_me_idle_seconds());
    if ($gcMax < 60) {
        $gcMax = remember_me_idle_seconds();
    }
    @ini_set('session.gc_maxlifetime', (string) $gcMax);
    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    enforce_remember_me_idle_timeout();
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

\Core\ErrorHandler::register();
