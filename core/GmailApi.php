<?php

declare(strict_types=1);

namespace Core;

final class GmailApi
{
    /**
     * @param list<string> $toAddresses
     * @return array{ok: bool, error?: string}
     */
    public static function sendPlainText(string $accessToken, array $toAddresses, string $subject, string $body, ?string $fromEmail = null): array
    {
        $recipients = self::normalizeRecipients($toAddresses);
        if ($recipients === []) {
            return ['ok' => false, 'error' => 'No valid recipients.'];
        }

        $from = trim((string) $fromEmail);
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Invalid sender address.'];
        }

        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        $headers = [
            'From: ' . $from,
            'To: ' . implode(', ', $recipients),
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $raw = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $response = GoogleCalendarApi::request(
            'POST',
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
            $accessToken,
            ['raw' => $encoded]
        );

        if ($response['ok']) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => (string) ($response['error'] ?? 'Gmail send failed.')];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function verifySendAccess(string $accessToken): array
    {
        $response = GoogleCalendarApi::request(
            'GET',
            'https://gmail.googleapis.com/gmail/v1/users/me/profile',
            $accessToken
        );

        if ($response['ok']) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => (string) ($response['error'] ?? 'Gmail access was not granted.')];
    }

    /**
     * @param list<string> $toAddresses
     * @return list<string>
     */
    private static function normalizeRecipients(array $toAddresses): array
    {
        $out = [];
        foreach ($toAddresses as $raw) {
            $email = strtolower(trim((string) $raw));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            if (!in_array($email, $out, true)) {
                $out[] = $email;
            }
        }

        return $out;
    }
}
