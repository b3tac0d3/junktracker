<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

final class DeviceToken
{
    public static function tableExists(): bool
    {
        return SchemaInspector::hasTable('device_tokens');
    }

    public static function upsert(int $userId, ?int $businessId, string $platform, string $token, ?string $deviceName = null): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $platform = strtolower(trim($platform));
        if (!in_array($platform, ['ios', 'android', 'web'], true)) {
            $platform = 'android';
        }

        $deviceName = trim((string) $deviceName);
        if ($deviceName === '') {
            $deviceName = null;
        } elseif (mb_strlen($deviceName) > 255) {
            $deviceName = mb_substr($deviceName, 0, 255);
        }

        $stmt = Database::connection()->prepare(
            'SELECT id FROM device_tokens WHERE token = :token LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $existingId = (int) $stmt->fetchColumn();

        if ($existingId > 0) {
            $update = Database::connection()->prepare(
                'UPDATE device_tokens
                 SET user_id = :user_id,
                     business_id = :business_id,
                     platform = :platform,
                     device_name = :device_name,
                     last_seen_at = NOW(),
                     revoked_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $update->execute([
                'user_id' => $userId,
                'business_id' => $businessId !== null && $businessId > 0 ? $businessId : null,
                'platform' => $platform,
                'device_name' => $deviceName,
                'id' => $existingId,
            ]);
            return true;
        }

        $insert = Database::connection()->prepare(
            'INSERT INTO device_tokens (
                user_id, business_id, platform, token, device_name, last_seen_at, created_at, updated_at
             ) VALUES (
                :user_id, :business_id, :platform, :token, :device_name, NOW(), NOW(), NOW()
             )'
        );
        $insert->execute([
            'user_id' => $userId,
            'business_id' => $businessId !== null && $businessId > 0 ? $businessId : null,
            'platform' => $platform,
            'token' => $token,
            'device_name' => $deviceName,
        ]);

        return true;
    }

    public static function revokeForUser(int $userId, string $token): bool
    {
        if ($userId <= 0 || !self::tableExists()) {
            return false;
        }

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE device_tokens
             SET revoked_at = NOW(), updated_at = NOW()
             WHERE user_id = :user_id AND token = :token AND revoked_at IS NULL'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token' => $token,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return list<string>
     */
    public static function activeTokensForUser(int $userId): array
    {
        if ($userId <= 0 || !self::tableExists()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT token
             FROM device_tokens
             WHERE user_id = :user_id
               AND revoked_at IS NULL
             ORDER BY updated_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $tokens = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $token = trim((string) ($row['token'] ?? ''));
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }
}
