<?php

declare(strict_types=1);

$app = require __DIR__ . '/app.php';
$env = (string) ($app['env'] ?? 'local');

$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocalHost = $host !== '' && (
    str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')
);

$envTransport = getenv('JUNKTRACKER_MAIL_TRANSPORT');
$transport = 'log';
if (is_string($envTransport) && trim($envTransport) !== '') {
    $transport = strtolower(trim($envTransport));
} elseif ($env === 'production' || !$isLocalHost) {
    $transport = 'mail';
}

$config = [
    'transport' => $transport,
    'from_address' => 'no-reply@junktracker.jimmysjunk.com',
    'from_name' => 'JunkTracker',
    'invite_subject' => 'Your JunkTracker invite',
    'reset_subject' => 'Your JunkTracker password reset link',
    'estimate_sent_subject' => 'Your estimate from JunkTracker',
    'invoice_sent_subject' => 'Your invoice from JunkTracker',
    'payment_receipt_subject' => 'Payment receipt',
    'daily_digest_subject' => 'JunkTracker daily digest',
];

$localOverride = __DIR__ . '/mail.local.php';
if (is_file($localOverride)) {
    /** @var array<string, mixed> $override */
    $override = require $localOverride;
    $config = array_merge($config, $override);
}

return $config;
