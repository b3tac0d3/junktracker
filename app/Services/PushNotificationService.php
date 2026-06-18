<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceToken;

final class PushNotificationService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $tokens = DeviceToken::activeTokensForUser($userId);
        if ($tokens === []) {
            return false;
        }

        $serverKey = trim((string) config('api.fcm_server_key', ''));
        if ($serverKey === '') {
            error_log('[push] FCM server key not configured; skipped push for user ' . (string) $userId);
            return false;
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $data,
            'priority' => 'high',
        ];

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: key=' . $serverKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[push] FCM send failed HTTP ' . (string) $httpCode . ': ' . (string) $response);
            return false;
        }

        return true;
    }
}
