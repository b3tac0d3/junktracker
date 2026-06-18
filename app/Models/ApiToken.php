<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class ApiToken
{
    public static function tableExists(): bool
    {
        return SchemaInspector::hasTable('api_tokens');
    }

    /**
     * @return array{plain: string, row: array<string, mixed>}|null
     */
    public static function issue(int $userId, ?int $businessId, string $tokenType, ?string $deviceName = null): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $ttl = $tokenType === 'refresh'
            ? (int) config('api.refresh_token_ttl', 60 * 60 * 24 * 90)
            : (int) config('api.access_token_ttl', 60 * 60 * 24 * 30);
        if ($ttl < 60) {
            $ttl = 60;
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $stmt = Database::connection()->prepare(
            'INSERT INTO api_tokens (
                user_id, business_id, token_hash, token_type, device_name, expires_at, created_at
             ) VALUES (
                :user_id, :business_id, :token_hash, :token_type, :device_name, :expires_at, NOW()
             )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'business_id' => $businessId !== null && $businessId > 0 ? $businessId : null,
            'token_hash' => $hash,
            'token_type' => $tokenType === 'refresh' ? 'refresh' : 'access',
            'device_name' => self::normalizeDeviceName($deviceName),
            'expires_at' => $expiresAt,
        ]);

        $id = (int) Database::connection()->lastInsertId();
        if ($id <= 0) {
            return null;
        }

        return [
            'plain' => $plain,
            'row' => [
                'id' => $id,
                'expires_at' => $expiresAt,
                'token_type' => $tokenType === 'refresh' ? 'refresh' : 'access',
            ],
        ];
    }

    public static function findValidAccessToken(string $plainToken): ?array
    {
        return self::findValidToken($plainToken, 'access');
    }

    public static function findValidRefreshToken(string $plainToken): ?array
    {
        return self::findValidToken($plainToken, 'refresh');
    }

    public static function touchLastUsed(int $tokenId): void
    {
        if ($tokenId <= 0 || !self::tableExists()) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $tokenId]);
    }

    public static function revokePlainToken(string $plainToken): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $hash = hash('sha256', $plainToken);
        $stmt = Database::connection()->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = :token_hash AND revoked_at IS NULL'
        );
        $stmt->execute(['token_hash' => $hash]);

        return $stmt->rowCount() > 0;
    }

    public static function revokeAllForUser(int $userId): void
    {
        if ($userId <= 0 || !self::tableExists()) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    private static function findValidToken(string $plainToken, string $tokenType): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $stmt = Database::connection()->prepare(
            'SELECT id, user_id, business_id, token_type, expires_at, revoked_at
             FROM api_tokens
             WHERE token_hash = :token_hash
               AND token_type = :token_type
             LIMIT 1'
        );
        $stmt->execute([
            'token_hash' => $hash,
            'token_type' => $tokenType,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        if (trim((string) ($row['revoked_at'] ?? '')) !== '') {
            return null;
        }

        $expiresAt = strtotime((string) ($row['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            return null;
        }

        return $row;
    }

    private static function normalizeDeviceName(?string $deviceName): ?string
    {
        $deviceName = trim((string) $deviceName);
        if ($deviceName === '') {
            return null;
        }

        return mb_substr($deviceName, 0, 255);
    }
}
