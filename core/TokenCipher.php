<?php

declare(strict_types=1);

namespace Core;

final class TokenCipher
{
    public static function encrypt(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }

        $key = self::keyMaterial();
        if ($key === '') {
            return $plain;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return $plain;
        }

        return 'enc1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        if (!str_starts_with($stored, 'enc1:')) {
            return $stored;
        }

        $key = self::keyMaterial();
        if ($key === '') {
            return '';
        }

        $raw = base64_decode(substr($stored, 5), true);
        if ($raw === false || strlen($raw) < 28) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return is_string($plain) ? $plain : '';
    }

    private static function keyMaterial(): string
    {
        $key = trim((string) config('google.token_encryption_key', ''));
        if ($key === '') {
            return '';
        }

        return hash('sha256', $key, true);
    }
}
