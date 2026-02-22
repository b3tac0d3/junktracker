<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AppSetting;
use Core\Controller;

final class AdminBusinessInfoController extends Controller
{
    private const SETTING_DEFAULTS = [
        'business.name' => '',
        'business.legal_name' => '',
        'business.email' => '',
        'business.phone' => '',
        'business.website' => '',
        'business.address_line1' => '',
        'business.address_line2' => '',
        'business.city' => '',
        'business.state' => '',
        'business.postal_code' => '',
        'business.country' => 'US',
        'business.tax_id' => '',
        'business.timezone' => 'America/New_York',
    ];

    public function index(): void
    {
        require_permission('business_info', 'view');

        $this->render('admin/business_info/index', [
            'pageTitle' => 'Business Info',
            'settings' => $this->resolvedSettings(),
            'isReady' => AppSetting::isAvailable(),
        ]);
    }

    public function update(): void
    {
        require_permission('business_info', 'edit');

        if (!AppSetting::isAvailable()) {
            flash('error', 'System settings table is not available yet.');
            redirect('/admin/business-info');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/business-info');
        }

        $values = [
            'business.name' => trim((string) ($_POST['business_name'] ?? '')),
            'business.legal_name' => trim((string) ($_POST['business_legal_name'] ?? '')),
            'business.email' => trim((string) ($_POST['business_email'] ?? '')),
            'business.phone' => trim((string) ($_POST['business_phone'] ?? '')),
            'business.website' => trim((string) ($_POST['business_website'] ?? '')),
            'business.address_line1' => trim((string) ($_POST['business_address_line1'] ?? '')),
            'business.address_line2' => trim((string) ($_POST['business_address_line2'] ?? '')),
            'business.city' => trim((string) ($_POST['business_city'] ?? '')),
            'business.state' => trim((string) ($_POST['business_state'] ?? '')),
            'business.postal_code' => trim((string) ($_POST['business_postal_code'] ?? '')),
            'business.country' => strtoupper(trim((string) ($_POST['business_country'] ?? 'US'))),
            'business.tax_id' => trim((string) ($_POST['business_tax_id'] ?? '')),
            'business.timezone' => trim((string) ($_POST['business_timezone'] ?? 'America/New_York')),
        ];

        $errors = $this->validate($values);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($values);
            redirect('/admin/business-info');
        }

        AppSetting::setMany($values, auth_user_id());
        log_user_action('business_info_updated', 'app_settings', null, 'Updated business profile settings.');
        flash('success', 'Business info updated.');
        redirect('/admin/business-info');
    }

    private function resolvedSettings(): array
    {
        $stored = AppSetting::all();
        $settings = [];
        foreach (self::SETTING_DEFAULTS as $key => $defaultValue) {
            $settings[$key] = (string) ($stored[$key] ?? $defaultValue);
        }

        return $settings;
    }

    private function validate(array $values): array
    {
        $errors = [];

        if ((string) $values['business.name'] === '') {
            $errors[] = 'Business name is required.';
        }

        $email = trim((string) ($values['business.email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Business email is invalid.';
        }

        $website = trim((string) ($values['business.website'] ?? ''));
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Business website URL is invalid.';
        }

        $timezone = trim((string) ($values['business.timezone'] ?? ''));
        if ($timezone === '') {
            $errors[] = 'Business timezone is required.';
        } else {
            try {
                new \DateTimeZone($timezone);
            } catch (\Throwable) {
                $errors[] = 'Business timezone is invalid.';
            }
        }

        return $errors;
    }
}

