<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEventLink;
use App\Models\Job;
use Core\GoogleCalendarApi;

final class GoogleCalendarSync
{
    public static function isConfigured(): bool
    {
        if (!(bool) config('google.enabled', true)) {
            return false;
        }

        $clientId = trim((string) config('google.client_id', ''));
        $clientSecret = trim((string) config('google.client_secret', ''));

        return $clientId !== '' && $clientSecret !== '';
    }

    public static function oauthRedirectUri(): string
    {
        return absolute_url('/settings/google-calendar/callback');
    }

    /**
     * @return array{ok: bool, url?: string, error?: string}
     */
    public static function authorizationUrl(string $state): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'Google Calendar is not configured on this server.'];
        }

        $params = [
            'client_id' => trim((string) config('google.client_id', '')),
            'redirect_uri' => self::oauthRedirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', (array) config('google.oauth_scopes', [])),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return [
            'ok' => true,
            'url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params),
        ];
    }

    /**
     * @return array{ok: bool, error?: string, email?: string}
     */
    public static function completeOAuth(int $userId, string $code): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid user.'];
        }

        $token = GoogleCalendarApi::exchangeAuthorizationCode($code, self::oauthRedirectUri());
        if (!$token['ok']) {
            return ['ok' => false, 'error' => (string) ($token['error'] ?? 'OAuth token exchange failed.')];
        }

        $accessToken = trim((string) ($token['access_token'] ?? ''));
        $refreshToken = trim((string) ($token['refresh_token'] ?? ''));
        if ($accessToken === '' || $refreshToken === '') {
            return ['ok' => false, 'error' => 'Google did not return the required tokens. Try connecting again.'];
        }

        $profile = GoogleCalendarApi::fetchUserEmail($accessToken);
        $email = $profile['ok'] ? trim((string) ($profile['email'] ?? '')) : '';

        $calendarCheck = GoogleCalendarApi::verifyCalendarAccess($accessToken);
        if (!$calendarCheck['ok']) {
            return ['ok' => false, 'error' => (string) ($calendarCheck['error'] ?? 'Google Calendar access was not granted.')];
        }

        GoogleCalendarConnection::upsert($userId, [
            'google_account_email' => $email,
            'calendar_id' => trim((string) config('google.calendar_id', 'primary')) ?: 'primary',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => self::expiresAtFromSeconds((int) ($token['expires_in'] ?? 0)),
        ]);

        $gmailCheck = \Core\GmailApi::verifySendAccess($accessToken);
        if (!$gmailCheck['ok']) {
            return [
                'ok' => true,
                'email' => $email,
                'gmail_warning' => 'Calendar connected, but Gmail send was not granted. Re-connect after adding the Gmail scope in Google Cloud, or appointment emails will not send.',
            ];
        }

        return ['ok' => true, 'email' => $email];
    }

    public static function accessTokenForUser(int $userId): string
    {
        return self::resolveAccessTokenForUser($userId);
    }

    public static function disconnect(int $userId): void
    {
        GoogleCalendarEventLink::deleteAllForUser($userId);
        GoogleCalendarConnection::deleteForUser($userId);
    }

    /**
     * @return array{ok: bool, synced?: int, skipped?: int, errors?: list<string>, error?: string}
     */
    public static function backfillUpcoming(int $userId, int $businessId, int $days = 90): array
    {
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59', strtotime('+' . max(1, $days) . ' days'));

        return self::backfillInRange($userId, $businessId, $start, $end);
    }

    /**
     * Push events and scheduled jobs with start times before today (default: last 365 days).
     *
     * @return array{ok: bool, synced?: int, skipped?: int, errors?: list<string>, error?: string}
     */
    public static function backfillPast(int $userId, int $businessId, int $pastDays = 365): array
    {
        $pastDays = max(1, min($pastDays, 3650));
        $start = date('Y-m-d 00:00:00', strtotime('-' . (string) $pastDays . ' days'));
        $end = date('Y-m-d 00:00:00');

        return self::backfillInRange($userId, $businessId, $start, $end);
    }

    /**
     * @return array{ok: bool, synced?: int, skipped?: int, errors?: list<string>, error?: string}
     */
    public static function backfillInRange(int $userId, int $businessId, string $start, string $end): array
    {
        if ($userId <= 0 || $businessId <= 0) {
            return ['ok' => false, 'error' => 'Invalid user or business.'];
        }

        if (!GoogleCalendarConnection::isConnected($userId)) {
            return ['ok' => false, 'error' => 'Connect Google Calendar first.'];
        }

        $rows = Event::range($businessId, $start, $end, []);

        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $eventId = (int) ($row['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $result = self::syncEventRecord($userId, $businessId, $row);
            if ($result['ok']) {
                if (($result['action'] ?? '') === 'skip' || ($result['action'] ?? '') === 'removed') {
                    $skipped++;
                } else {
                    $synced++;
                }
                continue;
            }

            $errors[] = 'Event #' . (string) $eventId . ': ' . (string) ($result['error'] ?? 'Sync failed.');
        }

        $jobs = Job::scheduledForCalendarSync($businessId, $start, $end);
        foreach ($jobs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $jobId = (int) ($row['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $result = self::syncJobRecord($userId, $businessId, $row);
            if ($result['ok']) {
                if (($result['action'] ?? '') === 'skip' || ($result['action'] ?? '') === 'removed') {
                    $skipped++;
                } else {
                    $synced++;
                }
                continue;
            }

            $errors[] = 'Job #' . (string) $jobId . ': ' . (string) ($result['error'] ?? 'Sync failed.');
        }

        return [
            'ok' => $errors === [],
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public static function syncEvent(int $userId, int $businessId, int $eventId): void
    {
        if ($userId <= 0 || $businessId <= 0 || $eventId <= 0) {
            return;
        }

        if (!GoogleCalendarConnection::isConnected($userId)) {
            return;
        }

        $event = Event::findForBusiness($businessId, $eventId);
        if ($event === null) {
            self::removeEvent($userId, $eventId);
            return;
        }

        self::syncEventRecord($userId, $businessId, $event);
    }

    public static function removeEvent(int $userId, int $eventId): void
    {
        self::removeLinkedRecord($userId, 'event', $eventId);
    }

    public static function syncJob(int $userId, int $businessId, int $jobId): void
    {
        if ($userId <= 0 || $businessId <= 0 || $jobId <= 0) {
            return;
        }

        if (!GoogleCalendarConnection::isConnected($userId)) {
            return;
        }

        $job = Job::findForBusiness($businessId, $jobId);
        if ($job === null) {
            self::removeJob($userId, $jobId);
            return;
        }

        self::syncJobRecord($userId, $businessId, $job);
    }

    public static function removeJob(int $userId, int $jobId): void
    {
        self::removeLinkedRecord($userId, 'job', $jobId);
    }

    private static function removeLinkedRecord(int $userId, string $sourceType, int $sourceId): void
    {
        if ($userId <= 0 || $sourceId <= 0 || !GoogleCalendarConnection::isConnected($userId)) {
            return;
        }

        $link = GoogleCalendarEventLink::find($userId, $sourceType, $sourceId);
        if ($link === null) {
            return;
        }

        $accessToken = self::accessTokenForUser($userId);
        if ($accessToken === '') {
            return;
        }

        $calendarId = rawurlencode(trim((string) ($link['google_calendar_id'] ?? '')));
        $googleEventId = rawurlencode(trim((string) ($link['google_event_id'] ?? '')));
        if ($calendarId === '' || $googleEventId === '') {
            GoogleCalendarEventLink::delete($userId, $sourceType, $sourceId);
            return;
        }

        GoogleCalendarApi::request(
            'DELETE',
            'https://www.googleapis.com/calendar/v3/calendars/' . $calendarId . '/events/' . $googleEventId,
            $accessToken
        );

        GoogleCalendarEventLink::delete($userId, $sourceType, $sourceId);
    }

    /**
     * @param array<string, mixed> $job
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncJobRecord(int $userId, int $businessId, array $job): array
    {
        $jobId = (int) ($job['id'] ?? 0);
        if ($jobId <= 0) {
            return ['ok' => false, 'error' => 'Invalid job id.'];
        }

        if (!self::shouldSyncJob($job)) {
            self::removeJob($userId, $jobId);
            return ['ok' => true, 'action' => 'removed'];
        }

        $payload = self::googlePayloadFromJob($job, $businessId);

        return self::pushLinkedRecord($userId, 'job', $jobId, $payload);
    }

    /**
     * @param array<string, mixed> $job
     */
    private static function shouldSyncJob(array $job): bool
    {
        $startAt = trim((string) ($job['scheduled_start_at'] ?? ''));
        if ($startAt === '') {
            return false;
        }

        $status = strtolower(trim((string) ($job['status'] ?? '')));
        if (in_array($status, ['cancelled', 'inactive'], true)) {
            return false;
        }

        if ((int) ($job['is_active'] ?? 1) === 0) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private static function googlePayloadFromJob(array $job, int $businessId): array
    {
        $jobId = (int) ($job['id'] ?? 0);
        $title = trim((string) ($job['title'] ?? 'Job'));
        if ($title === '') {
            $title = 'Job #' . (string) $jobId;
        }

        $timezone = (string) config('app.timezone', 'America/New_York');
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($job['status'] ?? 'pending')))));
        $jobType = trim((string) ($job['job_type'] ?? ''));
        $clientName = trim((string) ($job['client_name'] ?? ''));
        $clientPhone = trim((string) ($job['client_phone'] ?? ''));
        $notes = trim((string) ($job['notes'] ?? ''));
        $location = self::formatAddress(
            trim((string) ($job['address_line1'] ?? '')),
            trim((string) ($job['address_line2'] ?? '')),
            trim((string) ($job['city'] ?? '')),
            trim((string) ($job['state'] ?? '')),
            trim((string) ($job['postal_code'] ?? ''))
        );

        $descriptionParts = ['Status: ' . $status];
        if ($jobType !== '') {
            $descriptionParts[] = 'Type: ' . ucfirst(str_replace('_', ' ', $jobType));
        }
        if ($clientName !== '') {
            $descriptionParts[] = 'Client: ' . $clientName;
        }
        if ($clientPhone !== '') {
            $formatted = format_phone($clientPhone);
            $descriptionParts[] = 'Phone: ' . ($formatted !== '—' ? $formatted : $clientPhone);
        }
        if ($location !== '') {
            $descriptionParts[] = 'Address: ' . $location;
        }
        if ($notes !== '') {
            $descriptionParts[] = $notes;
        }
        $descriptionParts[] = 'JunkTracker: ' . url('/jobs/' . (string) $jobId);

        $startAt = trim((string) ($job['scheduled_start_at'] ?? ''));
        $endAt = trim((string) ($job['scheduled_end_at'] ?? ''));

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'job:' . (string) $jobId,
                    'junktrackerBusinessId' => (string) $businessId,
                ],
            ],
            'start' => [
                'dateTime' => self::isoDateTime($startAt),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::isoDateTime($endAt !== '' ? $endAt : date('Y-m-d H:i:s', strtotime($startAt . ' +2 hours'))),
                'timeZone' => $timezone,
            ],
        ];

        if ($location !== '') {
            $payload['location'] = $location;
        }

        return $payload;
    }

    private static function formatAddress(string $line1, string $line2, string $city, string $state, string $postal): string
    {
        $parts = [];
        if ($line1 !== '') {
            $parts[] = $line1;
        }
        if ($line2 !== '') {
            $parts[] = $line2;
        }

        $cityLine = trim($city . ($state !== '' ? ', ' . $state : '') . ($postal !== '' ? ' ' . $postal : ''));
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $event
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncEventRecord(int $userId, int $businessId, array $event): array
    {
        $eventId = (int) ($event['id'] ?? 0);
        if ($eventId <= 0) {
            return ['ok' => false, 'error' => 'Invalid event id.'];
        }

        $status = strtolower(trim((string) ($event['status'] ?? 'scheduled')));
        $cancelledAt = trim((string) ($event['cancelled_at'] ?? ''));
        if ($status === 'cancelled' || $cancelledAt !== '') {
            self::removeEvent($userId, $eventId);
            return ['ok' => true, 'action' => 'removed'];
        }

        $payload = self::googlePayloadFromEvent($event, $businessId);

        return self::pushLinkedRecord($userId, 'event', $eventId, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function pushLinkedRecord(int $userId, string $sourceType, int $sourceId, array $payload): array
    {
        $accessToken = self::accessTokenForUser($userId);
        if ($accessToken === '') {
            return ['ok' => false, 'error' => 'Unable to refresh Google access token.'];
        }

        $connection = GoogleCalendarConnection::findByUserId($userId);
        if ($connection === null) {
            return ['ok' => false, 'error' => 'Google Calendar is not connected.'];
        }

        $calendarId = trim((string) ($connection['calendar_id'] ?? 'primary')) ?: 'primary';
        $link = GoogleCalendarEventLink::find($userId, $sourceType, $sourceId);

        if ($link === null) {
            $response = GoogleCalendarApi::request(
                'POST',
                'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events',
                $accessToken,
                $payload
            );
            if (!$response['ok'] || !is_array($response['body'])) {
                return ['ok' => false, 'error' => $response['error'] !== '' ? $response['error'] : 'Unable to create Google event.'];
            }

            $googleEventId = trim((string) ($response['body']['id'] ?? ''));
            if ($googleEventId === '') {
                return ['ok' => false, 'error' => 'Google did not return an event id.'];
            }

            GoogleCalendarEventLink::upsert($userId, $sourceType, $sourceId, $calendarId, $googleEventId);
            return ['ok' => true, 'action' => 'created'];
        }

        $googleEventId = trim((string) ($link['google_event_id'] ?? ''));
        $response = GoogleCalendarApi::request(
            'PATCH',
            'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($googleEventId),
            $accessToken,
            $payload
        );
        if (!$response['ok']) {
            return ['ok' => false, 'error' => $response['error'] !== '' ? $response['error'] : 'Unable to update Google event.'];
        }

        GoogleCalendarEventLink::upsert($userId, $sourceType, $sourceId, $calendarId, $googleEventId);
        return ['ok' => true, 'action' => 'updated'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private static function googlePayloadFromEvent(array $event, int $businessId): array
    {
        $eventId = (int) ($event['id'] ?? 0);
        $title = trim((string) ($event['title'] ?? 'Event'));
        $type = trim((string) ($event['type'] ?? 'appointment'));
        $notes = trim((string) ($event['notes'] ?? ''));
        $allDay = (int) ($event['all_day'] ?? 0) === 1;
        $timezone = (string) config('app.timezone', 'America/New_York');

        $descriptionParts = [];
        if ($type !== '' && $type !== 'appointment') {
            $descriptionParts[] = 'Type: ' . ucfirst(str_replace('_', ' ', $type));
        }

        if (strtolower($type) === 'appointment') {
            $contact = Event::linkedClientContact($businessId, $event);
            $clientName = trim((string) ($contact['name'] ?? ''));
            $clientPhone = trim((string) ($contact['phone'] ?? ''));
            if ($clientName !== '') {
                $descriptionParts[] = 'Client: ' . $clientName;
            }
            if ($clientPhone !== '') {
                $formatted = format_phone($clientPhone);
                $descriptionParts[] = 'Phone: ' . ($formatted !== '—' ? $formatted : $clientPhone);
            }
        }

        if ($notes !== '') {
            $descriptionParts[] = $notes;
        }
        $descriptionParts[] = 'JunkTracker: ' . url('/events/' . (string) $eventId);

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'event:' . (string) $eventId,
                    'junktrackerBusinessId' => (string) $businessId,
                ],
            ],
        ];

        $startAt = trim((string) ($event['start_at'] ?? ''));
        $endAt = trim((string) ($event['end_at'] ?? ''));

        if ($allDay) {
            $startDate = self::datePart($startAt);
            $endDate = self::datePart($endAt !== '' ? $endAt : $startAt);
            if ($endDate === $startDate) {
                $endDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
            }
            $payload['start'] = ['date' => $startDate];
            $payload['end'] = ['date' => $endDate];
        } else {
            $payload['start'] = [
                'dateTime' => self::isoDateTime($startAt),
                'timeZone' => $timezone,
            ];
            $payload['end'] = [
                'dateTime' => self::isoDateTime($endAt !== '' ? $endAt : date('Y-m-d H:i:s', strtotime($startAt . ' +1 hour'))),
                'timeZone' => $timezone,
            ];
        }

        return $payload;
    }

    private static function resolveAccessTokenForUser(int $userId): string
    {
        $connection = GoogleCalendarConnection::findByUserId($userId);
        if ($connection === null) {
            return '';
        }

        $accessToken = trim((string) ($connection['access_token'] ?? ''));
        $refreshToken = trim((string) ($connection['refresh_token'] ?? ''));
        $expiresAt = trim((string) ($connection['token_expires_at'] ?? ''));

        if ($accessToken !== '' && ($expiresAt === '' || strtotime($expiresAt) > time() + 60)) {
            return $accessToken;
        }

        if ($refreshToken === '') {
            return '';
        }

        $token = GoogleCalendarApi::refreshAccessToken($refreshToken);
        if (!$token['ok']) {
            return '';
        }

        $newAccessToken = trim((string) ($token['access_token'] ?? ''));
        if ($newAccessToken === '') {
            return '';
        }

        GoogleCalendarConnection::upsert($userId, [
            'access_token' => $newAccessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => self::expiresAtFromSeconds((int) ($token['expires_in'] ?? 0)),
            'calendar_id' => trim((string) ($connection['calendar_id'] ?? 'primary')) ?: 'primary',
            'google_account_email' => trim((string) ($connection['google_account_email'] ?? '')),
        ]);

        return $newAccessToken;
    }

    private static function expiresAtFromSeconds(int $expiresIn): string
    {
        if ($expiresIn <= 0) {
            return date('Y-m-d H:i:s', time() + 3300);
        }

        return date('Y-m-d H:i:s', time() + max(60, $expiresIn - 60));
    }

    private static function datePart(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return date('Y-m-d');
        }

        return date('Y-m-d', $ts);
    }

    private static function isoDateTime(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return date('c');
        }

        return date('c', $ts);
    }
}
