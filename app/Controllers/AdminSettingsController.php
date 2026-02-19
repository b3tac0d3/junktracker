<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AppSetting;
use App\Support\Mailer;
use Core\Controller;

final class AdminSettingsController extends Controller
{
    public function index(): void
    {
        require_permission('admin_settings', 'view');

        $user = auth_user();
        $defaults = [
            'security.two_factor_enabled' => (bool) config('app.two_factor_enabled', true) ? '1' : '0',
            'display.date_format' => 'm/d/Y',
            'display.datetime_format' => 'm/d/Y g:i A',
            'display.timezone' => (string) config('app.timezone', 'America/New_York'),
            'mail.from_address' => (string) config('mail.from_address', ''),
            'mail.from_name' => (string) config('mail.from_name', ''),
            'mail.reply_to' => (string) config('mail.reply_to', ''),
            'mail.subject_prefix' => (string) config('mail.subject_prefix', ''),
            'mail.test_recipient' => (string) ($user['email'] ?? ''),
        ];

        $stored = AppSetting::all();
        $settings = [];
        foreach ($defaults as $key => $defaultValue) {
            $settings[$key] = (string) ($stored[$key] ?? $defaultValue);
        }

        $this->render('admin/settings/index', [
            'pageTitle' => 'Admin Settings',
            'settings' => $settings,
            'isReady' => AppSetting::isAvailable(),
        ]);
    }

    public function update(): void
    {
        require_permission('admin_settings', 'edit');

        if (!AppSetting::isAvailable()) {
            flash('error', 'System settings table is not available yet.');
            redirect('/admin/settings');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/settings');
        }

        $values = [
            'security.two_factor_enabled' => !empty($_POST['security_two_factor_enabled']) ? '1' : '0',
            'display.date_format' => trim((string) ($_POST['display_date_format'] ?? 'm/d/Y')),
            'display.datetime_format' => trim((string) ($_POST['display_datetime_format'] ?? 'm/d/Y g:i A')),
            'display.timezone' => trim((string) ($_POST['display_timezone'] ?? (string) config('app.timezone', 'America/New_York'))),
            'mail.from_address' => trim((string) ($_POST['mail_from_address'] ?? '')),
            'mail.from_name' => trim((string) ($_POST['mail_from_name'] ?? '')),
            'mail.reply_to' => trim((string) ($_POST['mail_reply_to'] ?? '')),
            'mail.subject_prefix' => (string) ($_POST['mail_subject_prefix'] ?? ''),
            'mail.test_recipient' => trim((string) ($_POST['mail_test_recipient'] ?? '')),
        ];

        $errors = $this->validate($values);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($values);
            redirect('/admin/settings');
        }

        AppSetting::setMany($values, auth_user_id());
        log_user_action('admin_settings_updated', 'app_settings', null, 'Updated admin system settings.');
        flash('success', 'Admin settings updated.');
        redirect('/admin/settings');
    }

    public function sendTestEmail(): void
    {
        require_permission('admin_settings', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/settings');
        }

        $recipient = trim((string) ($_POST['mail_test_recipient'] ?? ''));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid test recipient email.');
            redirect('/admin/settings');
        }

        $sent = Mailer::send(
            $recipient,
            'JunkTracker Test Email',
            "This is a test email from JunkTracker.\n\nIf you received this, mail settings are working."
        );

        if ($sent) {
            flash('success', 'Test email sent to ' . $recipient . '.');
        } else {
            flash('error', 'Test email failed. Check storage/logs/mail.log for details.');
        }

        redirect('/admin/settings');
    }

    private function validate(array $values): array
    {
        $errors = [];

        if ((string) $values['display.date_format'] === '') {
            $errors[] = 'Date format is required.';
        }
        if ((string) $values['display.datetime_format'] === '') {
            $errors[] = 'Date/time format is required.';
        }

        $from = trim((string) ($values['mail.from_address'] ?? ''));
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Mail from address is invalid.';
        }

        $replyTo = trim((string) ($values['mail.reply_to'] ?? ''));
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Reply-to email is invalid.';
        }

        $testRecipient = trim((string) ($values['mail.test_recipient'] ?? ''));
        if ($testRecipient !== '' && !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Test recipient email is invalid.';
        }

        return $errors;
    }
}
