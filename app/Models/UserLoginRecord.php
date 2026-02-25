<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Throwable;

final class UserLoginRecord
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
                'table' => 'user_login_records',
            ]);
            self::$tableAvailable = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }

    public static function create(int $userId, array $context = []): array
    {
        $normalized = self::normalizeContext($context);
        $normalized['id'] = null;

        if ($userId <= 0 || !self::isAvailable()) {
            return $normalized;
        }

        $columns = ['user_id', 'login_method', 'ip_address', 'user_agent', 'browser_name', 'browser_version', 'os_name', 'device_type', 'logged_in_at'];
        $values = [':user_id', ':login_method', ':ip_address', ':user_agent', ':browser_name', ':browser_version', ':os_name', ':device_type', 'NOW()'];
        $params = [
            'user_id' => $userId,
            'login_method' => $normalized['login_method'],
            'ip_address' => $normalized['ip_address'],
            'user_agent' => $normalized['user_agent'],
            'browser_name' => $normalized['browser_name'],
            'browser_version' => $normalized['browser_version'],
            'os_name' => $normalized['os_name'],
            'device_type' => $normalized['device_type'],
        ];
        if (self::hasBusinessColumn()) {
            $columns[] = 'business_id';
            $values[] = ':business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $sql = 'INSERT INTO user_login_records (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $normalized['id'] = (int) Database::connection()->lastInsertId();
        } catch (Throwable) {
            $normalized['id'] = null;
        }

        return $normalized;
    }

    public static function latestForUser(int $userId): ?array
    {
        if ($userId <= 0 || !self::isAvailable()) {
            return null;
        }

        $sql = 'SELECT id,
                       user_id,
                       login_method,
                       ip_address,
                       user_agent,
                       browser_name,
                       browser_version,
                       os_name,
                       device_type,
                       logged_in_at
                FROM user_login_records
                WHERE user_id = :user_id
                  ' . (self::hasBusinessColumn() && self::shouldApplyBusinessScope() ? 'AND business_id = :business_id' : '') . '
                ORDER BY logged_in_at DESC, id DESC
                LIMIT 1';

        try {
            $stmt = Database::connection()->prepare($sql);
            $params = ['user_id' => $userId];
            if (self::hasBusinessColumn() && self::shouldApplyBusinessScope()) {
                $params['business_id'] = self::currentBusinessId();
            }
            $stmt->execute($params);
            $record = $stmt->fetch();
            return $record ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function forUser(int $userId, string $query = ''): array
    {
        if ($userId <= 0 || !self::isAvailable()) {
            return [];
        }

        $sql = 'SELECT id,
                       user_id,
                       login_method,
                       ip_address,
                       user_agent,
                       browser_name,
                       browser_version,
                       os_name,
                       device_type,
                       logged_in_at
                FROM user_login_records
                WHERE user_id = :user_id';
        $params = ['user_id' => $userId];
        if (self::hasBusinessColumn() && self::shouldApplyBusinessScope()) {
            $sql .= ' AND business_id = :business_id';
            $params['business_id'] = self::currentBusinessId();
        }

        $search = trim($query);
        if ($search !== '') {
            $sql .= ' AND (
                        login_method LIKE :q
                        OR ip_address LIKE :q
                        OR browser_name LIKE :q
                        OR browser_version LIKE :q
                        OR os_name LIKE :q
                        OR device_type LIKE :q
                        OR user_agent LIKE :q
                      )';
            $params['q'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY logged_in_at DESC, id DESC
                  LIMIT 1000';

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private static function hasBusinessColumn(): bool
    {
        return Schema::hasColumn('user_login_records', 'business_id');
    }

    private static function currentBusinessId(): int
    {
        if (function_exists('current_business_id')) {
            return max(1, (int) current_business_id());
        }

        return max(1, (int) config('app.default_business_id', 1));
    }

    private static function shouldApplyBusinessScope(): bool
    {
        if (!function_exists('auth_user_role')) {
            return true;
        }

        $role = (int) auth_user_role();
        if (function_exists('is_global_role_value')) {
            return !is_global_role_value($role);
        }

        return $role < 4;
    }

    private static function normalizeContext(array $context): array
    {
        $loginMethod = trim((string) ($context['login_method'] ?? 'password'));
        if ($loginMethod === '') {
            $loginMethod = 'password';
        }

        $ip = trim((string) ($context['ip_address'] ?? ''));
        $ip = $ip !== '' ? substr($ip, 0, 45) : null;

        $userAgent = trim((string) ($context['user_agent'] ?? ''));
        $userAgent = $userAgent !== '' ? substr($userAgent, 0, 512) : null;

        $agentDetails = self::parseUserAgent($userAgent ?? '');

        return [
            'login_method' => substr($loginMethod, 0, 30),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'browser_name' => $agentDetails['browser_name'],
            'browser_version' => $agentDetails['browser_version'],
            'os_name' => $agentDetails['os_name'],
            'device_type' => $agentDetails['device_type'],
        ];
    }

    private static function parseUserAgent(string $userAgent): array
    {
        $ua = strtolower(trim($userAgent));
        if ($ua === '') {
            return [
                'browser_name' => null,
                'browser_version' => null,
                'os_name' => null,
                'device_type' => null,
            ];
        }

        $os = 'Other';
        $osMap = [
            '/windows nt 10\\.0/i' => 'Windows 10/11',
            '/windows nt 6\\.3/i' => 'Windows 8.1',
            '/windows nt 6\\.2/i' => 'Windows 8',
            '/windows nt 6\\.1/i' => 'Windows 7',
            '/iphone/i' => 'iOS',
            '/ipad/i' => 'iPadOS',
            '/android/i' => 'Android',
            '/mac os x/i' => 'macOS',
            '/linux/i' => 'Linux',
        ];
        foreach ($osMap as $pattern => $label) {
            if (preg_match($pattern, $userAgent)) {
                $os = $label;
                break;
            }
        }

        $browserName = 'Other';
        $browserVersion = null;
        $browserPatterns = [
            'Edge' => '/Edg\/([0-9\.]+)/i',
            'Opera' => '/OPR\/([0-9\.]+)/i',
            'Chrome' => '/Chrome\/([0-9\.]+)/i',
            'Firefox' => '/Firefox\/([0-9\.]+)/i',
            'Safari' => '/Version\/([0-9\.]+).*Safari/i',
            'Internet Explorer' => '/MSIE\s([0-9\.]+)/i',
            'Internet Explorer' => '/Trident\/.*rv:([0-9\.]+)/i',
        ];
        foreach ($browserPatterns as $name => $pattern) {
            if (preg_match($pattern, $userAgent, $matches) === 1) {
                $browserName = $name;
                $browserVersion = $matches[1] ?? null;
                break;
            }
        }

        $deviceType = 'desktop';
        if (str_contains($ua, 'bot') || str_contains($ua, 'spider') || str_contains($ua, 'crawl')) {
            $deviceType = 'bot';
        } elseif (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            $deviceType = 'tablet';
        } elseif (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            $deviceType = 'mobile';
        }

        return [
            'browser_name' => $browserName !== '' ? $browserName : null,
            'browser_version' => $browserVersion !== '' ? $browserVersion : null,
            'os_name' => $os !== '' ? $os : null,
            'device_type' => $deviceType !== '' ? $deviceType : null,
        ];
    }
}
