<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Event;
use App\Models\GoogleCalendarConnection;
use Core\GmailApi;

final class AppointmentGmailNotify
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_DELETED = 'deleted';

    /**
     * Send Gmail notification for a calendar appointment event (non-blocking for callers).
     *
     * @param array<string, mixed>|null $event Pass a snapshot for delete so the row can already be gone.
     */
    public static function notifyEventChange(int $userId, int $businessId, string $action, ?array $event): void
    {
        if ($userId <= 0 || $businessId <= 0 || !is_array($event)) {
            return;
        }

        $type = strtolower(trim((string) ($event['type'] ?? '')));
        if ($type !== 'appointment') {
            return;
        }

        if (!GoogleCalendarConnection::isConnected($userId)) {
            return;
        }

        if (!GoogleCalendarConnection::appointmentGmailNotifyEnabled($userId)) {
            return;
        }

        $accessToken = GoogleCalendarSync::accessTokenForUser($userId);
        if ($accessToken === '') {
            self::logFailure($userId, $action, 'Missing or expired Google access token.');
            return;
        }

        $connection = GoogleCalendarConnection::findByUserId($userId);
        if ($connection === null) {
            return;
        }

        $fromEmail = trim((string) ($connection['google_account_email'] ?? ''));
        if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            self::logFailure($userId, $action, 'Connected Google account has no valid email.');
            return;
        }

        $recipients = GoogleCalendarConnection::appointmentGmailNotifyRecipients($userId);
        if ($recipients === []) {
            self::logFailure($userId, $action, 'No notification recipients configured.');
            return;
        }

        $business = Business::findById($businessId);
        $businessName = trim((string) ($business['name'] ?? ''));
        if ($businessName === '') {
            $businessName = 'JunkTracker';
        }

        $subject = self::subjectForAction($action, $event, $businessName);
        $body = self::bodyForAction($action, $event, $businessName, $businessId);

        $result = GmailApi::sendPlainText($accessToken, $recipients, $subject, $body, $fromEmail);
        if (!$result['ok']) {
            self::logFailure($userId, $action, (string) ($result['error'] ?? 'Gmail send failed.'));
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function subjectForAction(string $action, array $event, string $businessName): string
    {
        $title = trim((string) ($event['title'] ?? 'Appointment'));
        if ($title === '') {
            $title = 'Appointment';
        }

        return match ($action) {
            self::ACTION_CREATED => '[' . $businessName . '] New appointment: ' . $title,
            self::ACTION_UPDATED => '[' . $businessName . '] Appointment updated: ' . $title,
            self::ACTION_CANCELLED => '[' . $businessName . '] Appointment cancelled: ' . $title,
            self::ACTION_DELETED => '[' . $businessName . '] Appointment removed: ' . $title,
            default => '[' . $businessName . '] Appointment: ' . $title,
        };
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function bodyForAction(string $action, array $event, string $businessName, int $businessId): string
    {
        $eventId = (int) ($event['id'] ?? 0);
        $title = trim((string) ($event['title'] ?? ''));
        $status = ucfirst(str_replace('_', ' ', strtolower(trim((string) ($event['status'] ?? 'scheduled')))));
        $when = self::formatWhen($event);
        $notes = trim((string) ($event['notes'] ?? ''));
        $url = $eventId > 0 ? absolute_url('/events/' . (string) $eventId) : absolute_url('/events');

        $intro = match ($action) {
            self::ACTION_CREATED => 'A new appointment was added in JunkTracker.',
            self::ACTION_UPDATED => 'An appointment was changed in JunkTracker.',
            self::ACTION_CANCELLED => 'An appointment was cancelled in JunkTracker.',
            self::ACTION_DELETED => 'An appointment was deleted in JunkTracker.',
            default => 'Appointment update from JunkTracker.',
        };

        $contact = Event::linkedClientContact($businessId, $event);

        $lines = [
            $intro,
            '',
            'Business: ' . $businessName,
            'Title: ' . ($title !== '' ? $title : '—'),
            'When: ' . $when,
            'Status: ' . $status,
        ];

        $clientName = trim((string) ($contact['name'] ?? ''));
        $clientPhone = trim((string) ($contact['phone'] ?? ''));
        if ($clientName !== '') {
            $lines[] = 'Client: ' . $clientName;
        }
        if ($clientPhone !== '') {
            $formatted = format_phone($clientPhone);
            $lines[] = 'Phone: ' . ($formatted !== '—' ? $formatted : $clientPhone);
        }

        if ($notes !== '') {
            $lines[] = '';
            $lines[] = 'Notes:';
            $lines[] = $notes;
        }

        $lines[] = '';
        $lines[] = 'View in JunkTracker: ' . $url;
        $lines[] = '';
        $lines[] = 'Sent via Gmail from your connected Google account.';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function formatWhen(array $event): string
    {
        $allDay = (int) ($event['all_day'] ?? 0) === 1;
        $startRaw = trim((string) ($event['start_at'] ?? ''));
        $endRaw = trim((string) ($event['end_at'] ?? ''));

        $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
        if ($startTs === false) {
            return '—';
        }

        if ($allDay) {
            $startDate = date('l, F j, Y', $startTs);
            if ($endRaw !== '') {
                $endTs = strtotime($endRaw);
                if ($endTs !== false && date('Y-m-d', $endTs) !== date('Y-m-d', $startTs)) {
                    return $startDate . ' – ' . date('l, F j, Y', $endTs);
                }
            }

            return $startDate . ' (all day)';
        }

        $startText = date('l, F j, Y g:i A', $startTs);
        if ($endRaw === '') {
            return $startText;
        }

        $endTs = strtotime($endRaw);
        if ($endTs === false) {
            return $startText;
        }

        if (date('Y-m-d', $startTs) === date('Y-m-d', $endTs)) {
            return date('l, F j, Y', $startTs) . ', ' . date('g:i A', $startTs) . ' – ' . date('g:i A', $endTs);
        }

        return date('M j, Y g:i A', $startTs) . ' – ' . date('M j, Y g:i A', $endTs);
    }

    private static function logFailure(int $userId, string $action, string $message): void
    {
        $line = '[AppointmentGmailNotify] user=' . (string) $userId . ' action=' . $action . ' ' . $message;
        error_log($line);
    }
}
