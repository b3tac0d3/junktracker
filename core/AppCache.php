<?php

declare(strict_types=1);

namespace Core;

/**
 * Short-lived app cache: APCu when available, otherwise files under storage/cache/.
 */
final class AppCache
{
    private const PREFIX = 'jt_';

    public static function enabled(): bool
    {
        $ttl = (int) config('app.cache_ttl_seconds', 60);

        return $ttl > 0;
    }

    public static function ttlSeconds(): int
    {
        $ttl = (int) config('app.cache_ttl_seconds', 60);

        return max(0, min($ttl, 3600));
    }

    /**
     * Append to cache keys so deploys that bump app.version invalidate APCu/file cache (avoids stale dashboard/nav after upload).
     */
    public static function versionSuffix(): string
    {
        $v = trim((string) config('app.version', ''));

        return $v !== '' ? substr(md5($v), 0, 8) : '0';
    }

    public static function get(string $key): mixed
    {
        if (!self::enabled()) {
            return null;
        }

        $k = self::namespacedKey($key);
        if (self::apcuAvailable()) {
            $ok = false;
            $out = apcu_fetch($k, $ok);

            return $ok ? $out : null;
        }

        return self::fileGet($k);
    }

    public static function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        if (!self::enabled()) {
            return;
        }

        $ttl = $ttlSeconds ?? self::ttlSeconds();
        if ($ttl <= 0) {
            return;
        }

        $k = self::namespacedKey($key);
        if (self::apcuAvailable()) {
            apcu_store($k, $value, $ttl);

            return;
        }

        self::fileSet($k, $value, $ttl);
    }

    public static function forget(string $key): void
    {
        $k = self::namespacedKey($key);
        if (self::apcuAvailable()) {
            apcu_delete($k);
        }
        $path = self::filePathForKey($k);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function namespacedKey(string $key): string
    {
        return self::PREFIX . self::sanitizeKey($key);
    }

    private static function sanitizeKey(string $key): string
    {
        $key = preg_replace('/[^a-zA-Z0-9._:-]/', '', $key) ?? '';

        return $key !== '' ? $key : 'k';
    }

    private static function apcuAvailable(): bool
    {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
            return false;
        }

        return function_exists('apcu_enabled') && apcu_enabled();
    }

    private static function cacheDir(): string
    {
        $dir = base_path('storage/cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function filePathForKey(string $namespacedKey): string
    {
        return self::cacheDir() . '/' . hash('sha256', $namespacedKey) . '.cache';
    }

    private static function fileGet(string $namespacedKey): mixed
    {
        $path = self::filePathForKey($namespacedKey);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = @unserialize($raw);
        if (!is_array($data) || !array_key_exists('exp', $data) || !array_key_exists('payload', $data)) {
            @unlink($path);

            return null;
        }
        if ((int) $data['exp'] < time()) {
            @unlink($path);

            return null;
        }

        return $data['payload'];
    }

    private static function fileSet(string $namespacedKey, mixed $value, int $ttl): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $path = self::filePathForKey($namespacedKey);
        $blob = serialize([
            'exp' => time() + $ttl,
            'payload' => $value,
        ]);
        @file_put_contents($path, $blob, LOCK_EX);
    }
}
