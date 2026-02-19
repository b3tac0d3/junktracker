<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class AuthLoginAttempt
{
    private static ?bool $tableAvailable = null;

    public static function isAvailable(): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        $schema = (string) config('database.database', '');
        if ($schema === '') {
            self::$tableAvailable = false;
            return false;
        }

        $sql = 'SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME = :table
                LIMIT 1';

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'schema' => $schema,
                'table' => 'auth_login_attempts',
            ]);
            self::$tableAvailable = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }

    public static function record(
        string $email,
        ?int $userId,
        ?string $ipAddress,
        string $status,
        string $reason,
        ?string $userAgent = null
    ): void {
        if (!self::isAvailable()) {
            return;
        }

        $normalizedStatus = $status === 'success' ? 'success' : 'failed';
        $cleanEmail = strtolower(trim($email));
        $cleanEmail = $cleanEmail !== '' ? substr($cleanEmail, 0, 255) : null;
        $cleanIp = trim((string) $ipAddress);
        $cleanIp = $cleanIp !== '' ? substr($cleanIp, 0, 45) : null;
        $cleanUserAgent = trim((string) $userAgent);
        $cleanUserAgent = $cleanUserAgent !== '' ? substr($cleanUserAgent, 0, 512) : null;

        $sql = 'INSERT INTO auth_login_attempts
                    (email, user_id, ip_address, status, reason, user_agent, attempted_at)
                VALUES
                    (:email, :user_id, :ip_address, :status, :reason, :user_agent, NOW())';

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'email' => $cleanEmail,
                'user_id' => $userId !== null && $userId > 0 ? $userId : null,
                'ip_address' => $cleanIp,
                'status' => $normalizedStatus,
                'reason' => substr(trim($reason), 0, 80),
                'user_agent' => $cleanUserAgent,
            ]);
        } catch (Throwable) {
            // Never break auth flow.
        }
    }

    public static function isRateLimited(string $email, ?string $ipAddress): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $cleanEmail = strtolower(trim($email));
        $cleanIp = trim((string) $ipAddress);
        if ($cleanEmail === '' && $cleanIp === '') {
            return false;
        }

        try {
            if ($cleanEmail !== '' && $cleanIp !== '') {
                $sql = 'SELECT COUNT(*)
                        FROM auth_login_attempts
                        WHERE status = \'failed\'
                          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                          AND email = :email
                          AND ip_address = :ip_address';
                $stmt = Database::connection()->prepare($sql);
                $stmt->execute([
                    'email' => $cleanEmail,
                    'ip_address' => $cleanIp,
                ]);
                if ((int) $stmt->fetchColumn() >= 8) {
                    return true;
                }
            }

            if ($cleanIp !== '') {
                $sql = 'SELECT COUNT(*)
                        FROM auth_login_attempts
                        WHERE status = \'failed\'
                          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                          AND ip_address = :ip_address';
                $stmt = Database::connection()->prepare($sql);
                $stmt->execute(['ip_address' => $cleanIp]);
                if ((int) $stmt->fetchColumn() >= 20) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    public static function failedCountLastDay(): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        try {
            $sql = 'SELECT COUNT(*)
                    FROM auth_login_attempts
                    WHERE status = \'failed\'
                      AND attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';

            return max(0, (int) Database::connection()->query($sql)->fetchColumn());
        } catch (Throwable) {
            return 0;
        }
    }
}
