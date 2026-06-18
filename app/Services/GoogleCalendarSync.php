<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventFeed;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEventLink;
use App\Models\Client;
use App\Models\Job;
use App\Models\ClientDelivery;
use App\Models\Quote;
use App\Models\PurchaseQuote;
use App\Models\Purchase;
use App\Models\EstateSale;
use App\Models\Task;
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
     * Delete every JunkTracker-linked event from Google Calendar for this user.
     *
     * @return array{ok: bool, removed?: int, failed?: int, errors?: list<string>, error?: string}
     */
    public static function removeAllLinkedEvents(int $userId): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid user.'];
        }

        if (!GoogleCalendarConnection::isConnected($userId)) {
            return ['ok' => false, 'error' => 'Connect Google Calendar first.'];
        }

        $accessToken = self::accessTokenForUser($userId);
        if ($accessToken === '') {
            return ['ok' => false, 'error' => 'Unable to obtain Google access token. Try connecting again.'];
        }

        $links = GoogleCalendarEventLink::allForUser($userId);
        if ($links === []) {
            return ['ok' => true, 'removed' => 0, 'failed' => 0, 'errors' => []];
        }

        $removed = 0;
        $failed = 0;
        $errors = [];

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $deleteResult = self::deleteGoogleEventLink($userId, $link, $accessToken);
            if ($deleteResult['ok']) {
                $removed++;
                continue;
            }

            $failed++;
            $sourceType = trim((string) ($link['source_type'] ?? ''));
            $sourceId = (int) ($link['source_id'] ?? 0);
            $label = $sourceType !== '' && $sourceId > 0
                ? ucfirst($sourceType) . ' #' . (string) $sourceId
                : 'Linked event';
            $errors[] = $label . ': ' . (string) ($deleteResult['error'] ?? 'Delete failed.');
        }

        return ['ok' => true, 'removed' => $removed, 'failed' => $failed, 'errors' => $errors];
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

        $synced = 0;
        $skipped = 0;
        $errors = [];
        $seen = [];

        foreach (EventFeed::googleSyncItemRefs($businessId, $start, $end) as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $sourceType = strtolower(trim((string) ($ref['source_type'] ?? '')));
            $sourceId = (int) ($ref['source_id'] ?? 0);
            if ($sourceType === '' || $sourceId <= 0) {
                continue;
            }

            $dedupeKey = $sourceType . ':' . (string) $sourceId;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $result = self::syncSourceRecord($userId, $businessId, $sourceType, $sourceId);
            if ($result['ok']) {
                if (($result['action'] ?? '') === 'skip' || ($result['action'] ?? '') === 'removed') {
                    $skipped++;
                } else {
                    $synced++;
                }
                continue;
            }

            $errors[] = ucfirst($sourceType) . ' #' . (string) $sourceId . ': ' . (string) ($result['error'] ?? 'Sync failed.');
        }

        return [
            'ok' => $errors === [],
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public static function syncSource(int $userId, int $businessId, string $sourceType, int $sourceId): void
    {
        if ($userId <= 0 || $businessId <= 0 || $sourceId <= 0) {
            return;
        }

        if (!GoogleCalendarConnection::isConnected($userId)) {
            return;
        }

        self::syncSourceRecord($userId, $businessId, strtolower(trim($sourceType)), $sourceId);
    }

    public static function removeSource(int $userId, string $sourceType, int $sourceId): void
    {
        self::removeLinkedRecord($userId, strtolower(trim($sourceType)), $sourceId);
    }

    /**
     * Build the Google Calendar payload for a record without calling the API (offline verification).
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public static function previewPayload(string $sourceType, array $record, int $businessId): array
    {
        return match (strtolower(trim($sourceType))) {
            'event' => self::googlePayloadFromEvent($record, $businessId),
            'job' => self::googlePayloadFromJob($record, $businessId),
            'task' => self::googlePayloadFromTask($record, $businessId),
            'delivery' => self::googlePayloadFromDelivery($record, $businessId),
            'quote' => self::googlePayloadFromQuote($record, $businessId),
            'purchase_quote' => self::googlePayloadFromPurchaseQuote($record, $businessId),
            'estate_sale' => self::googlePayloadFromEstateSale($record, $businessId),
            default => throw new \InvalidArgumentException('Unsupported Google Calendar source type: ' . $sourceType),
        };
    }

    public static function syncTask(int $userId, int $businessId, int $taskId): void
    {
        self::syncSource($userId, $businessId, 'task', $taskId);
    }

    public static function removeTask(int $userId, int $taskId): void
    {
        self::removeSource($userId, 'task', $taskId);
    }

    public static function syncDelivery(int $userId, int $businessId, int $deliveryId): void
    {
        self::syncSource($userId, $businessId, 'delivery', $deliveryId);
    }

    public static function removeDelivery(int $userId, int $deliveryId): void
    {
        self::removeSource($userId, 'delivery', $deliveryId);
    }

    public static function syncQuote(int $userId, int $businessId, int $quoteId): void
    {
        self::syncSource($userId, $businessId, 'quote', $quoteId);
    }

    public static function removeQuote(int $userId, int $quoteId): void
    {
        self::removeSource($userId, 'quote', $quoteId);
    }

    public static function syncPurchaseQuote(int $userId, int $businessId, int $purchaseQuoteId): void
    {
        self::syncSource($userId, $businessId, 'purchase_quote', $purchaseQuoteId);
    }

    public static function removePurchaseQuote(int $userId, int $purchaseQuoteId): void
    {
        self::removeSource($userId, 'purchase_quote', $purchaseQuoteId);
    }

    public static function syncEstateSale(int $userId, int $businessId, int $estateSaleId): void
    {
        self::syncSource($userId, $businessId, 'estate_sale', $estateSaleId);
    }

    public static function removeEstateSale(int $userId, int $estateSaleId): void
    {
        self::removeSource($userId, 'estate_sale', $estateSaleId);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncSourceRecord(int $userId, int $businessId, string $sourceType, int $sourceId): array
    {
        return match ($sourceType) {
            'event' => self::syncEventOrRemove($userId, $businessId, $sourceId),
            'job' => self::syncJobOrRemove($userId, $businessId, $sourceId),
            'task' => self::syncTaskOrRemove($userId, $businessId, $sourceId),
            'delivery' => self::syncDeliveryOrRemove($userId, $businessId, $sourceId),
            'quote' => self::syncQuoteOrRemove($userId, $businessId, $sourceId),
            'purchase_quote' => self::syncPurchaseQuoteOrRemove($userId, $businessId, $sourceId),
            'estate_sale' => self::syncEstateSaleOrRemove($userId, $businessId, $sourceId),
            default => ['ok' => false, 'error' => 'Unsupported calendar source.'],
        };
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncEventOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $event = Event::findForBusiness($businessId, $sourceId);
        if ($event === null) {
            self::removeEvent($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncEventRecord($userId, $businessId, $event);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncJobOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $job = Job::findForBusiness($businessId, $sourceId);
        if ($job === null) {
            self::removeJob($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncJobRecord($userId, $businessId, $job);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncTaskOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $task = Task::findForBusiness($businessId, $sourceId);
        if ($task === null) {
            self::removeTask($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncTaskRecord($userId, $businessId, $task);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncDeliveryOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $delivery = ClientDelivery::findForBusiness($businessId, $sourceId);
        if ($delivery === null) {
            self::removeDelivery($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncDeliveryRecord($userId, $businessId, $delivery);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncQuoteOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $quote = Quote::findForBusiness($businessId, $sourceId);
        if ($quote === null) {
            self::removeQuote($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncQuoteRecord($userId, $businessId, $quote);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncPurchaseQuoteOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $quote = PurchaseQuote::findForBusiness($businessId, $sourceId);
        if ($quote === null) {
            self::removePurchaseQuote($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncPurchaseQuoteRecord($userId, $businessId, $quote);
    }

    /**
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncEstateSaleOrRemove(int $userId, int $businessId, int $sourceId): array
    {
        $estateSale = EstateSale::findForBusiness($businessId, $sourceId);
        if ($estateSale === null) {
            self::removeEstateSale($userId, $sourceId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::syncEstateSaleRecord($userId, $businessId, $estateSale);
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

        self::deleteGoogleEventLink($userId, $link, $accessToken);
    }

    /**
     * @param array<string, mixed> $link
     * @return array{ok: bool, error?: string}
     */
    private static function deleteGoogleEventLink(int $userId, array $link, string $accessToken): array
    {
        $sourceType = strtolower(trim((string) ($link['source_type'] ?? '')));
        $sourceId = (int) ($link['source_id'] ?? 0);
        if ($userId <= 0 || $sourceType === '' || $sourceId <= 0) {
            return ['ok' => false, 'error' => 'Invalid link record.'];
        }

        $calendarId = rawurlencode(trim((string) ($link['google_calendar_id'] ?? '')));
        $googleEventId = rawurlencode(trim((string) ($link['google_event_id'] ?? '')));
        if ($calendarId === '' || $googleEventId === '') {
            GoogleCalendarEventLink::delete($userId, $sourceType, $sourceId);

            return ['ok' => true];
        }

        $response = GoogleCalendarApi::request(
            'DELETE',
            'https://www.googleapis.com/calendar/v3/calendars/' . $calendarId . '/events/' . $googleEventId,
            $accessToken
        );

        if ($response['ok'] || (int) ($response['status'] ?? 0) === 404) {
            GoogleCalendarEventLink::delete($userId, $sourceType, $sourceId);

            return ['ok' => true];
        }

        return ['ok' => false, 'error' => (string) ($response['error'] ?? 'Google API delete failed.')];
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
     * @param array<string, mixed> $task
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncTaskRecord(int $userId, int $businessId, array $task): array
    {
        $taskId = (int) ($task['id'] ?? 0);
        if ($taskId <= 0) {
            return ['ok' => false, 'error' => 'Invalid task id.'];
        }

        if (!self::shouldSyncTask($task)) {
            self::removeTask($userId, $taskId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::pushLinkedRecord($userId, 'task', $taskId, self::googlePayloadFromTask($task, $businessId));
    }

    /**
     * @param array<string, mixed> $delivery
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncDeliveryRecord(int $userId, int $businessId, array $delivery): array
    {
        $deliveryId = (int) ($delivery['id'] ?? 0);
        if ($deliveryId <= 0) {
            return ['ok' => false, 'error' => 'Invalid delivery id.'];
        }

        if (!self::shouldSyncDelivery($delivery)) {
            self::removeDelivery($userId, $deliveryId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::pushLinkedRecord($userId, 'delivery', $deliveryId, self::googlePayloadFromDelivery($delivery, $businessId));
    }

    /**
     * @param array<string, mixed> $quote
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncQuoteRecord(int $userId, int $businessId, array $quote): array
    {
        $quoteId = (int) ($quote['id'] ?? 0);
        if ($quoteId <= 0) {
            return ['ok' => false, 'error' => 'Invalid quote id.'];
        }

        if (!self::shouldSyncQuote($quote)) {
            self::removeQuote($userId, $quoteId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::pushLinkedRecord($userId, 'quote', $quoteId, self::googlePayloadFromQuote($quote, $businessId));
    }

    /**
     * @param array<string, mixed> $quote
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncPurchaseQuoteRecord(int $userId, int $businessId, array $quote): array
    {
        $quoteId = (int) ($quote['id'] ?? 0);
        if ($quoteId <= 0) {
            return ['ok' => false, 'error' => 'Invalid purchase quote id.'];
        }

        if (!self::shouldSyncPurchaseQuote($quote)) {
            self::removePurchaseQuote($userId, $quoteId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::pushLinkedRecord(
            $userId,
            'purchase_quote',
            $quoteId,
            self::googlePayloadFromPurchaseQuote($quote, $businessId)
        );
    }

    /**
     * @param array<string, mixed> $estateSale
     * @return array{ok: bool, action?: string, error?: string}
     */
    private static function syncEstateSaleRecord(int $userId, int $businessId, array $estateSale): array
    {
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        if ($estateSaleId <= 0) {
            return ['ok' => false, 'error' => 'Invalid estate sale id.'];
        }

        if (!self::shouldSyncEstateSale($estateSale)) {
            self::removeEstateSale($userId, $estateSaleId);

            return ['ok' => true, 'action' => 'removed'];
        }

        return self::pushLinkedRecord(
            $userId,
            'estate_sale',
            $estateSaleId,
            self::googlePayloadFromEstateSale($estateSale, $businessId)
        );
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
        $location = self::addressFromRecord($job, $businessId);

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

    /**
     * @param array<string, mixed> $task
     */
    private static function shouldSyncTask(array $task): bool
    {
        return trim((string) ($task['due_at'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private static function shouldSyncDelivery(array $delivery): bool
    {
        if (trim((string) ($delivery['scheduled_at'] ?? '')) === '') {
            return false;
        }

        return strtolower(trim((string) ($delivery['status'] ?? ''))) !== 'cancelled';
    }

    /**
     * @param array<string, mixed> $quote
     */
    private static function shouldSyncQuote(array $quote): bool
    {
        return trim((string) ($quote['next_follow_up_at'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $quote
     */
    private static function shouldSyncPurchaseQuote(array $quote): bool
    {
        $followUpAt = trim((string) ($quote['next_follow_up_at'] ?? ''));
        if ($followUpAt !== '') {
            return true;
        }

        return trim((string) ($quote['contact_date'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $estateSale
     */
    private static function shouldSyncEstateSale(array $estateSale): bool
    {
        if (trim((string) ($estateSale['start_at'] ?? '')) === '') {
            return false;
        }

        return strtolower(trim((string) ($estateSale['status'] ?? ''))) !== 'cancelled';
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private static function googlePayloadFromTask(array $task, int $businessId): array
    {
        $taskId = (int) ($task['id'] ?? 0);
        $title = Task::displayTitle($task);
        $timezone = (string) config('app.timezone', 'America/New_York');
        $startAt = trim((string) ($task['due_at'] ?? ''));
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($task['status'] ?? 'open')))));
        $ownerName = trim((string) ($task['owner_name'] ?? ''));
        $clientName = trim((string) ($task['client_name'] ?? ''));
        $location = self::addressFromLink(
            $businessId,
            (string) ($task['link_type'] ?? ''),
            (int) ($task['link_id'] ?? 0)
        );
        if ($location === '' && (int) ($task['client_id'] ?? 0) > 0) {
            $location = self::addressFromClient($businessId, (int) $task['client_id']);
        }

        $descriptionParts = ['Type: Task', 'Status: ' . $status];
        if ($ownerName !== '') {
            $descriptionParts[] = 'Owner: ' . $ownerName;
        }
        if ($clientName !== '') {
            $descriptionParts[] = 'Client: ' . $clientName;
        }
        if ($location !== '') {
            $descriptionParts[] = 'Address: ' . $location;
        }
        $descriptionParts[] = 'JunkTracker: ' . url('/tasks/' . (string) $taskId);

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'task:' . (string) $taskId,
                    'junktrackerBusinessId' => (string) $businessId,
                ],
            ],
            'start' => [
                'dateTime' => self::isoDateTime($startAt),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::isoDateTime(date('Y-m-d H:i:s', strtotime($startAt . ' +1 hour'))),
                'timeZone' => $timezone,
            ],
        ];
        if ($location !== '') {
            $payload['location'] = $location;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $delivery
     * @return array<string, mixed>
     */
    private static function googlePayloadFromDelivery(array $delivery, int $businessId): array
    {
        $deliveryId = (int) ($delivery['id'] ?? 0);
        $clientName = trim((string) ($delivery['client_name'] ?? ''));
        $title = $clientName !== '' ? $clientName : 'Delivery #' . (string) $deliveryId;
        $timezone = (string) config('app.timezone', 'America/New_York');
        $startAt = trim((string) ($delivery['scheduled_at'] ?? ''));
        $endAt = trim((string) ($delivery['end_at'] ?? ''));
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($delivery['status'] ?? 'scheduled')))));
        $location = self::addressFromRecord($delivery, $businessId);
        $notes = trim((string) ($delivery['notes'] ?? ''));

        $descriptionParts = ['Type: Delivery', 'Status: ' . $status];
        if ($clientName !== '') {
            $descriptionParts[] = 'Client: ' . $clientName;
        }
        if ($location !== '') {
            $descriptionParts[] = 'Address: ' . $location;
        }
        if ($notes !== '') {
            $descriptionParts[] = $notes;
        }
        $descriptionParts[] = 'JunkTracker: ' . url('/deliveries/' . (string) $deliveryId);

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'delivery:' . (string) $deliveryId,
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

    /**
     * @param array<string, mixed> $quote
     * @return array<string, mixed>
     */
    private static function googlePayloadFromQuote(array $quote, int $businessId): array
    {
        $quoteId = (int) ($quote['id'] ?? 0);
        $title = trim((string) ($quote['title'] ?? ''));
        if ($title === '') {
            $title = 'Quote #' . (string) $quoteId;
        }
        $timezone = (string) config('app.timezone', 'America/New_York');
        $startAt = trim((string) ($quote['next_follow_up_at'] ?? ''));
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($quote['status'] ?? 'new')))));
        $clientName = trim((string) ($quote['client_name'] ?? ''));
        $clientPhone = trim((string) ($quote['client_phone'] ?? ''));
        $location = self::addressFromRecord($quote, $businessId);

        $descriptionParts = ['Type: Quote follow-up', 'Status: ' . $status];
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
        $descriptionParts[] = 'JunkTracker: ' . url('/quotes/' . (string) $quoteId);

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'quote:' . (string) $quoteId,
                    'junktrackerBusinessId' => (string) $businessId,
                ],
            ],
            'start' => [
                'dateTime' => self::isoDateTime($startAt),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::isoDateTime(date('Y-m-d H:i:s', strtotime($startAt . ' +1 hour'))),
                'timeZone' => $timezone,
            ],
        ];
        if ($location !== '') {
            $payload['location'] = $location;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $quote
     * @return array<string, mixed>
     */
    private static function googlePayloadFromPurchaseQuote(array $quote, int $businessId): array
    {
        $quoteId = (int) ($quote['id'] ?? 0);
        $title = trim((string) ($quote['title'] ?? ''));
        if ($title === '') {
            $title = 'Purchase Quote #' . (string) $quoteId;
        }
        $timezone = (string) config('app.timezone', 'America/New_York');
        $startAt = trim((string) ($quote['next_follow_up_at'] ?? ''));
        if ($startAt === '') {
            $contactDate = trim((string) ($quote['contact_date'] ?? ''));
            if ($contactDate !== '') {
                $startAt = $contactDate . ' 09:00:00';
            }
        }
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($quote['status'] ?? 'new')))));
        $clientName = trim((string) ($quote['client_name'] ?? ''));
        $clientPhone = trim((string) ($quote['client_phone'] ?? ''));
        $location = self::addressFromRecord($quote, $businessId);

        $descriptionParts = ['Type: Purchase quote follow-up', 'Status: ' . $status];
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
        $descriptionParts[] = 'JunkTracker: ' . url('/purchase-quotes/' . (string) $quoteId);

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'purchase_quote:' . (string) $quoteId,
                    'junktrackerBusinessId' => (string) $businessId,
                ],
            ],
            'start' => [
                'dateTime' => self::isoDateTime($startAt),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::isoDateTime(date('Y-m-d H:i:s', strtotime($startAt . ' +1 hour'))),
                'timeZone' => $timezone,
            ],
        ];
        if ($location !== '') {
            $payload['location'] = $location;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $estateSale
     * @return array<string, mixed>
     */
    private static function googlePayloadFromEstateSale(array $estateSale, int $businessId): array
    {
        $estateSaleId = (int) ($estateSale['id'] ?? 0);
        $title = trim((string) ($estateSale['title'] ?? ''));
        if ($title === '') {
            $title = 'Estate Sale #' . (string) $estateSaleId;
        }
        $timezone = (string) config('app.timezone', 'America/New_York');
        $startAt = trim((string) ($estateSale['start_at'] ?? ''));
        $endAt = trim((string) ($estateSale['end_at'] ?? ''));
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($estateSale['status'] ?? 'scheduled')))));
        $location = self::addressFromRecord($estateSale, $businessId);
        $notes = trim((string) ($estateSale['notes'] ?? ''));

        $descriptionParts = ['Type: Estate sale', 'Status: ' . $status];
        if ($location !== '') {
            $descriptionParts[] = 'Address: ' . $location;
        }
        if ($notes !== '') {
            $descriptionParts[] = $notes;
        }
        $descriptionParts[] = 'JunkTracker: ' . url('/estate-sales/' . (string) $estateSaleId);

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descriptionParts),
            'extendedProperties' => [
                'private' => [
                    'junktrackerKey' => 'estate_sale:' . (string) $estateSaleId,
                    'junktrackerBusinessId' => (string) $businessId,
                ],
            ],
            'start' => [
                'dateTime' => self::isoDateTime($startAt),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::isoDateTime($endAt !== '' ? $endAt : date('Y-m-d H:i:s', strtotime($startAt . ' +4 hours'))),
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
     * @param array<string, mixed> $record
     */
    private static function addressFromRecord(array $record, int $businessId): string
    {
        $location = self::formatAddress(
            trim((string) ($record['address_line1'] ?? '')),
            trim((string) ($record['address_line2'] ?? '')),
            trim((string) ($record['city'] ?? '')),
            trim((string) ($record['state'] ?? '')),
            trim((string) ($record['postal_code'] ?? ''))
        );
        if ($location !== '') {
            return $location;
        }

        $clientId = (int) ($record['client_id'] ?? 0);
        if ($clientId > 0 && $businessId > 0) {
            return self::addressFromClient($businessId, $clientId);
        }

        return '';
    }

    private static function addressFromClient(int $businessId, int $clientId): string
    {
        if ($businessId <= 0 || $clientId <= 0) {
            return '';
        }

        $client = Client::findForBusiness($businessId, $clientId);
        if ($client === null) {
            return '';
        }

        return self::formatAddress(
            trim((string) ($client['address_line1'] ?? '')),
            trim((string) ($client['address_line2'] ?? '')),
            trim((string) ($client['city'] ?? '')),
            trim((string) ($client['state'] ?? '')),
            trim((string) ($client['postal_code'] ?? ''))
        );
    }

    private static function addressFromLink(int $businessId, string $linkType, int $linkId): string
    {
        $linkType = strtolower(trim($linkType));
        if ($businessId <= 0 || $linkId <= 0) {
            return '';
        }

        if ($linkType === 'client') {
            return self::addressFromClient($businessId, $linkId);
        }

        if ($linkType === 'job') {
            $job = Job::findForBusiness($businessId, $linkId);

            return $job !== null ? self::addressFromRecord($job, $businessId) : '';
        }

        if ($linkType === 'purchase') {
            $purchase = Purchase::findForBusiness($businessId, $linkId);
            if ($purchase === null) {
                return '';
            }

            $clientId = (int) ($purchase['client_id'] ?? 0);

            return $clientId > 0 ? self::addressFromClient($businessId, $clientId) : '';
        }

        return '';
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
        $type = strtolower(trim((string) ($event['type'] ?? 'appointment')));
        $notes = trim((string) ($event['notes'] ?? ''));
        $allDay = (int) ($event['all_day'] ?? 0) === 1;
        $timezone = (string) config('app.timezone', 'America/New_York');
        $summary = self::eventSummaryTitle($title, $type);

        $descriptionParts = [];
        if ($type !== '' && $type !== 'appointment') {
            $descriptionParts[] = 'Type: ' . ucfirst(str_replace('_', ' ', $type));
        }

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
        $location = self::addressFromLink(
            $businessId,
            (string) ($event['link_type'] ?? ''),
            (int) ($event['link_id'] ?? 0)
        );
        if ($location !== '') {
            $descriptionParts[] = 'Address: ' . $location;
        }

        if ($notes !== '') {
            $descriptionParts[] = $notes;
        }
        $descriptionParts[] = 'JunkTracker: ' . url('/events/' . (string) $eventId);

        $payload = [
            'summary' => $summary,
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

        if ($location !== '') {
            $payload['location'] = $location;
        }

        return $payload;
    }

    private static function eventSummaryTitle(string $title, string $type): string
    {
        $title = trim($title);
        $type = strtolower(trim($type));
        if ($type === '' || $type === 'appointment') {
            return $title !== '' ? $title : 'Appointment';
        }

        $typeLabel = match ($type) {
            'personal' => 'Personal time',
            'cancellation' => 'Cancellation',
            'reminder' => 'Reminder',
            'note' => 'Note',
            'task' => 'Task',
            default => ucfirst(str_replace('_', ' ', $type)),
        };

        if ($title === '' || strcasecmp($title, $typeLabel) === 0) {
            return $typeLabel;
        }

        if (stripos($title, $typeLabel) === 0) {
            return $title;
        }

        return $typeLabel . ': ' . $title;
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
