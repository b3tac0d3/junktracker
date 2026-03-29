<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ((string) config('app.env', 'production') === 'local' && function_exists('opcache_reset')) {
    @opcache_reset();
}

session_name((string) config('app.session_name', 'junktracker_session'));
if (session_status() !== PHP_SESSION_ACTIVE) {
    $persistent = remember_me_persistent_seconds();
    $gcMax = (int) config('app.session_gc_maxlifetime', $persistent);
    if ($gcMax < 60) {
        $gcMax = $persistent;
    }
    $gcMax = max($gcMax, $persistent);
    // Many hosts default session.gc_maxlifetime to 900 (15m); if ini_set is ignored, set php.ini / .user.ini (see deploy-checklist).
    @ini_set('session.gc_maxlifetime', (string) $gcMax);

    // Isolate session files from other apps on the same host (often share /tmp with short GC).
    $sessionDir = base_path('storage/sessions');
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0750, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        @session_save_path($sessionDir);
    }

    $cookiePath = session_cookie_base_path();
    $secure = session_cookie_secure_preferred();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath, '', $secure, true);
    }

    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    maintain_remember_me_session();
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
