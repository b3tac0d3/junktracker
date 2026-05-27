<?php

declare(strict_types=1);

namespace Core;

final class GoogleCalendarApi
{
    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>|null, error: string}
     */
    public static function request(string $method, string $url, string $accessToken, ?array $jsonBody = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'cURL is not available on this server.'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'Unable to start HTTP request.'];
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                curl_close($ch);
                return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'Unable to encode request body.'];
            }
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'error' => $curlError !== '' ? $curlError : 'HTTP request failed.'];
        }

        $body = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => ''];
        }

        $message = '';
        if (is_array($body)) {
            $message = trim((string) ($body['error']['message'] ?? $body['error_description'] ?? ''));
        }

        return [
            'ok' => false,
            'status' => $status,
            'body' => $body,
            'error' => $message !== '' ? $message : ('Google API returned HTTP ' . (string) $status),
        ];
    }

    /**
     * @return array{ok: bool, access_token?: string, refresh_token?: string, expires_in?: int, error?: string}
     */
    public static function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        return self::tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => trim((string) config('google.client_id', '')),
            'client_secret' => trim((string) config('google.client_secret', '')),
        ]);
    }

    /**
     * @return array{ok: bool, access_token?: string, expires_in?: int, error?: string}
     */
    public static function refreshAccessToken(string $refreshToken): array
    {
        return self::tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => trim((string) config('google.client_id', '')),
            'client_secret' => trim((string) config('google.client_secret', '')),
        ]);
    }

    /**
     * @param array<string, string> $fields
     * @return array{ok: bool, access_token?: string, refresh_token?: string, expires_in?: int, error?: string}
     */
    private static function tokenRequest(array $fields): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL is not available on this server.'];
        }

        $ch = curl_init('https://oauth2.googleapis.com/token');
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Unable to start token request.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $curlError !== '' ? $curlError : 'Token request failed.'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Invalid token response from Google.'];
        }

        if ($status < 200 || $status >= 300) {
            $message = trim((string) ($decoded['error_description'] ?? $decoded['error'] ?? ''));
            return ['ok' => false, 'error' => $message !== '' ? $message : ('Token request failed with HTTP ' . (string) $status)];
        }

        return [
            'ok' => true,
            'access_token' => trim((string) ($decoded['access_token'] ?? '')),
            'refresh_token' => trim((string) ($decoded['refresh_token'] ?? '')),
            'expires_in' => (int) ($decoded['expires_in'] ?? 0),
        ];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function verifyCalendarAccess(string $accessToken): array
    {
        $response = self::request(
            'GET',
            'https://www.googleapis.com/calendar/v3/users/me/calendarList?maxResults=1',
            $accessToken
        );
        if ($response['ok']) {
            return ['ok' => true];
        }

        $error = $response['error'] !== '' ? $response['error'] : 'Unable to access Google Calendar.';
        if (stripos($error, 'insufficient') !== false && stripos($error, 'scope') !== false) {
            $error .= ' Disconnect and reconnect after adding the Calendar scope in Google Cloud Console.';
        }

        return ['ok' => false, 'error' => $error];
    }

    /**
     * @return array{ok: bool, email?: string, error?: string}
     */
    public static function fetchUserEmail(string $accessToken): array
    {
        $response = self::request('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', $accessToken);
        if (!$response['ok'] || !is_array($response['body'])) {
            return ['ok' => false, 'error' => $response['error'] !== '' ? $response['error'] : 'Unable to load Google account profile.'];
        }

        $email = trim((string) ($response['body']['email'] ?? ''));
        if ($email === '') {
            return ['ok' => false, 'error' => 'Google account email was not returned.'];
        }

        return ['ok' => true, 'email' => $email];
    }
}
