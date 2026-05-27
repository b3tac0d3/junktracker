<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\TokenCipher;

final class GoogleCalendarConnection
{
    public static function ensureTable(): void
    {
        if (SchemaInspector::hasTable('user_google_calendar_connections')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS user_google_calendar_connections (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    google_account_email VARCHAR(190) NULL,
                    calendar_id VARCHAR(190) NOT NULL DEFAULT 'primary',
                    access_token TEXT NULL,
                    refresh_token TEXT NULL,
                    token_expires_at DATETIME NULL,
                    connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_user_google_calendar_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::connection()->exec($sql);
    }

    public static function findByUserId(int $userId): ?array
    {
        self::ensureTable();
        if ($userId <= 0 || !SchemaInspector::hasTable('user_google_calendar_connections')) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM user_google_calendar_connections
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return self::hydrateRow($row);
    }

    public static function isConnected(int $userId): bool
    {
        $row = self::findByUserId($userId);
        if ($row === null) {
            return false;
        }

        return trim((string) ($row['refresh_token'] ?? '')) !== '';
    }

    public static function upsert(int $userId, array $data): void
    {
        self::ensureTable();
        if ($userId <= 0 || !SchemaInspector::hasTable('user_google_calendar_connections')) {
            return;
        }

        $existing = self::findByUserId($userId);
        $calendarId = trim((string) ($data['calendar_id'] ?? 'primary'));
        if ($calendarId === '') {
            $calendarId = 'primary';
        }

        $accessToken = TokenCipher::encrypt(trim((string) ($data['access_token'] ?? '')));
        $refreshToken = TokenCipher::encrypt(trim((string) ($data['refresh_token'] ?? '')));
        $expiresAt = trim((string) ($data['token_expires_at'] ?? ''));
        $googleEmail = trim((string) ($data['google_account_email'] ?? ''));

        if ($existing === null) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO user_google_calendar_connections (
                    user_id, google_account_email, calendar_id, access_token, refresh_token, token_expires_at, connected_at, updated_at
                 ) VALUES (
                    :user_id, :google_account_email, :calendar_id, :access_token, :refresh_token, :token_expires_at, NOW(), NOW()
                 )'
            );
            $stmt->execute([
                'user_id' => $userId,
                'google_account_email' => $googleEmail !== '' ? $googleEmail : null,
                'calendar_id' => $calendarId,
                'access_token' => $accessToken !== '' ? $accessToken : null,
                'refresh_token' => $refreshToken !== '' ? $refreshToken : null,
                'token_expires_at' => $expiresAt !== '' ? $expiresAt : null,
            ]);
            return;
        }

        $sql = 'UPDATE user_google_calendar_connections
             SET google_account_email = :google_account_email,
                 calendar_id = :calendar_id,
                 access_token = :access_token,
                 token_expires_at = :token_expires_at,
                 updated_at = NOW()';
        $params = [
            'user_id' => $userId,
            'google_account_email' => $googleEmail !== '' ? $googleEmail : ($existing['google_account_email'] ?? null),
            'calendar_id' => $calendarId,
            'access_token' => $accessToken !== '' ? $accessToken : null,
            'token_expires_at' => $expiresAt !== '' ? $expiresAt : null,
        ];
        if ($refreshToken !== '') {
            $sql .= ', refresh_token = :refresh_token';
            $params['refresh_token'] = $refreshToken;
        }
        $sql .= ' WHERE user_id = :user_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function deleteForUser(int $userId): void
    {
        self::ensureTable();
        if ($userId <= 0 || !SchemaInspector::hasTable('user_google_calendar_connections')) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM user_google_calendar_connections WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function hydrateRow(array $row): array
    {
        $row['access_token'] = TokenCipher::decrypt(trim((string) ($row['access_token'] ?? '')));
        $row['refresh_token'] = TokenCipher::decrypt(trim((string) ($row['refresh_token'] ?? '')));

        return $row;
    }
}
