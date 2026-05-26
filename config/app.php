<?php

declare(strict_types=1);

$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocalHost = $host !== '' && (
    str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')
);

$appUrl = 'http://localhost/junktracker';
if (!$isLocalHost && $host !== '') {
    $https = (!empty($_SERVER['HTTPS']) && (string) $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $https ? 'https' : 'http';
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($base === '/') {
        $base = '';
    }
    $appUrl = $scheme . '://' . $host . $base;
} elseif (!$isLocalHost) {
    // CLI/cron without HTTP_HOST — beta-live default (override in app.local.php on server if needed).
    $appUrl = 'https://junktracker.jimmysjunk.com';
}

$config = [
    'name' => 'JunkTracker',
    'version' => '1.10.1.1',
    // Beta-live deploys run on real hostnames: treat as production (mail, etc.). Localhost stays dev.
    'env' => $isLocalHost ? 'local' : 'production',
    'url' => $appUrl,
    'timezone' => 'America/New_York',
    'session_name' => 'junktracker_session',
    /**
     * PHP session file lifetime (seconds). The app raises this to at least remember-me persistence (~10y).
     * If the host ignores ini_set, set session.gc_maxlifetime in php.ini accordingly.
     */
    'session_gc_maxlifetime' => 259200,
    'default_business_id' => 1,
    'debug' => $isLocalHost,
    /**
     * Short-lived server cache (APCu, else storage/cache) for nav notifications + dashboard summary.
     * Set to 0 to disable. Max 3600.
     */
    'cache_ttl_seconds' => 60,
    /** Shared secret for /cron/daily-digest?key= — leave empty to disable remote calls */
    'cron_key' => (string) (getenv('JUNKTRACKER_CRON_KEY') ?: ''),
];

$localOverride = __DIR__ . '/app.local.php';
if (is_file($localOverride)) {
    /** @var array<string, mixed> $override */
    $override = require $localOverride;
    $config = array_merge($config, $override);
}

return $config;
