<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class Mailer
{
    public static function send(string $toEmail, string $subject, string $textBody): bool
    {
        $to = trim($toEmail);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromAddress = trim((string) config('mail.from_address', 'no-reply@junktracker.local'));
        $fromName = trim((string) config('mail.from_name', 'JunkTracker'));
        $replyTo = trim((string) config('mail.reply_to', ''));
        $subjectPrefix = (string) config('mail.subject_prefix', '');
        if (AppSetting::isAvailable()) {
            $fromAddress = trim((string) AppSetting::get('mail.from_address', $fromAddress));
            $fromName = trim((string) AppSetting::get('mail.from_name', $fromName));
            $replyTo = trim((string) AppSetting::get('mail.reply_to', $replyTo));
            $subjectPrefix = (string) AppSetting::get('mail.subject_prefix', $subjectPrefix);
        }

        $encodedFromName = str_replace(["\r", "\n"], '', $fromName);
        $safeSubject = str_replace(["\r", "\n"], '', trim($subjectPrefix . $subject));

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . $encodedFromName . ' <' . $fromAddress . '>';
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $message = trim($textBody) . "\n";

        $sent = @mail($to, $safeSubject, $message, implode("\r\n", $headers));
        self::logMailAttempt($to, $safeSubject, $sent, $message);

        return $sent;
    }

    private static function logMailAttempt(string $to, string $subject, bool $sent, string $body): void
    {
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = sprintf(
            "[%s] %s to=%s subject=%s body=%s\n",
            date('Y-m-d H:i:s'),
            $sent ? 'SENT' : 'FAILED',
            $to,
            $subject,
            str_replace(["\r", "\n"], [' ', ' | '], trim($body))
        );

        @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
    }
}
