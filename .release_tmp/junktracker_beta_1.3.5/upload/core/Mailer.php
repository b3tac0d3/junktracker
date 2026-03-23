<?php

declare(strict_types=1);

namespace Core;

final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $to = trim($to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            self::log('invalid-recipient', $to, $subject, false);
            return false;
        }

        $fromAddress = trim((string) \config('mail.from_address', ''));
        $fromName = trim((string) \config('mail.from_name', 'JunkTracker'));
        $transport = trim((string) \config('mail.transport', 'log'));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($fromAddress !== '') {
            $headers[] = sprintf('From: %s <%s>', $fromName !== '' ? $fromName : $fromAddress, $fromAddress);
            $headers[] = sprintf('Reply-To: %s', $fromAddress);
        }

        if ($transport === 'log') {
            self::log($to, $subject, $body, true, 'log');
            return true;
        }

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        self::log($to, $subject, $body, $sent, $transport);
        return $sent;
    }

    private static function log(string $to, string $subject, string $body, bool $ok, string $transport = 'mail'): void
    {
        \write_log_entry('mail', [
            'ok' => $ok,
            'transport' => $transport,
            'to' => $to,
            'subject' => $subject,
            'user_id' => \auth_user_id(),
            'business_id' => \current_business_id(),
            'body' => $body,
        ]);
    }
}
