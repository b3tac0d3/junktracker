<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GoogleCalendarSync;
use Core\Controller;

final class GoogleCalendarController extends Controller
{
    public function connect(): void
    {
        require_auth();

        if (!GoogleCalendarSync::isConfigured()) {
            flash('error', 'Google Calendar is not configured. Add your OAuth client secret in config/google.local.php.');
            redirect('/settings');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;

        $auth = GoogleCalendarSync::authorizationUrl($state);
        if (!$auth['ok'] || trim((string) ($auth['url'] ?? '')) === '') {
            flash('error', (string) ($auth['error'] ?? 'Unable to start Google authorization.'));
            redirect('/settings');
        }

        header('Location: ' . (string) $auth['url']);
        exit;
    }

    public function callback(): void
    {
        require_auth();

        $expectedState = trim((string) ($_SESSION['google_oauth_state'] ?? ''));
        unset($_SESSION['google_oauth_state']);

        $state = trim((string) ($_GET['state'] ?? ''));
        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            flash('error', 'Google authorization failed (invalid state). Please try again.');
            redirect('/settings');
        }

        $error = trim((string) ($_GET['error'] ?? ''));
        if ($error !== '') {
            flash('error', 'Google authorization was cancelled or denied.');
            redirect('/settings');
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            flash('error', 'Google did not return an authorization code.');
            redirect('/settings');
        }

        $userId = (int) (auth_user_id() ?? 0);
        $result = GoogleCalendarSync::completeOAuth($userId, $code);
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'Unable to connect Google Calendar.'));
            redirect('/settings');
        }

        $email = trim((string) ($result['email'] ?? ''));
        $gmailWarning = trim((string) ($result['gmail_warning'] ?? ''));
        if ($gmailWarning !== '') {
            flash('info', $gmailWarning);
        }
        flash('success', $email !== '' ? 'Google connected as ' . $email . ' (Calendar + Gmail).' : 'Google connected.');
        redirect('/settings');
    }

    public function disconnect(): void
    {
        require_auth();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/settings');
        }

        $userId = (int) (auth_user_id() ?? 0);
        GoogleCalendarSync::disconnect($userId);
        flash('success', 'Google Calendar disconnected.');
        redirect('/settings');
    }

    public function backfill(): void
    {
        require_auth();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/settings');
        }

        $userId = (int) (auth_user_id() ?? 0);
        $businessId = current_business_id();
        $result = GoogleCalendarSync::backfillUpcoming($userId, $businessId, 90);

        $this->finishBackfillResponse($result, 'upcoming');
    }

    public function backfillPast(): void
    {
        require_auth();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/settings');
        }

        $userId = (int) (auth_user_id() ?? 0);
        $businessId = current_business_id();
        $pastDays = (int) ($_POST['past_days'] ?? 365);
        $result = GoogleCalendarSync::backfillPast($userId, $businessId, $pastDays);

        $this->finishBackfillResponse($result, 'past', $pastDays);
    }

    /**
     * @param array{ok: bool, synced?: int, skipped?: int, errors?: list<string>, error?: string} $result
     */
    private function finishBackfillResponse(array $result, string $range, int $pastDays = 365): void
    {
        if (!$result['ok']) {
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            $combined = strtolower(implode(' ', $errors));
            if (str_contains($combined, 'insufficient authentication scopes')) {
                flash(
                    'error',
                    'Google Calendar scope was not granted. In Google Cloud Console → OAuth consent screen → Scopes, add Google Calendar API access. Then Disconnect here and connect again.'
                );
            } elseif ($errors !== []) {
                flash('error', 'Sync finished with errors: ' . implode(' ', array_slice($errors, 0, 3)));
            } else {
                $fallback = $range === 'past' ? 'Unable to sync past events.' : 'Unable to sync upcoming events.';
                flash('error', (string) ($result['error'] ?? $fallback));
            }
            redirect('/settings');
        }

        $synced = (int) ($result['synced'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        if ($range === 'past') {
            $pastDays = max(1, min($pastDays, 3650));
            flash(
                'success',
                'Synced ' . (string) $synced . ' past event(s) from the last ' . (string) $pastDays . ' day(s)'
                    . ($skipped > 0 ? ' (' . (string) $skipped . ' skipped).' : '.')
            );
        } else {
            flash('success', 'Synced ' . (string) $synced . ' upcoming event(s)' . ($skipped > 0 ? ' (' . (string) $skipped . ' skipped).' : '.'));
        }

        redirect('/settings');
    }
}
