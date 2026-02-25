<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Contact;
use App\Models\User;
use Core\Controller;

final class SettingsController extends Controller
{
    public function edit(): void
    {
        $userId = auth_user_id();
        if ($userId === null) {
            redirect('/login');
        }

        $user = User::findById($userId);
        if (!$user) {
            redirect('/login');
        }

        $this->render('settings/edit', [
            'pageTitle' => 'Settings',
            'user' => $user,
            'globalTwoFactorEnabled' => is_two_factor_enabled(),
        ]);

        clear_old();
    }

    public function update(): void
    {
        $userId = auth_user_id();
        if ($userId === null) {
            redirect('/login');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/settings');
        }

        $user = User::findById($userId);
        if (!$user) {
            redirect('/login');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data, $userId);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/settings');
        }

        if (strcasecmp(trim((string) ($user['email'] ?? '')), trim((string) ($data['email'] ?? ''))) !== 0) {
            User::releaseEmailFromInactiveUsers($data['email'], $userId);
        }
        User::updateProfile($userId, $data, $userId);
        $updatedUser = User::findById($userId);
        if ($updatedUser) {
            Contact::upsertFromUser($updatedUser, $userId);
        }

        $_SESSION['user']['email'] = $data['email'];
        $_SESSION['user']['first_name'] = $data['first_name'];
        $_SESSION['user']['last_name'] = $data['last_name'];

        $message = 'Updated account settings.';
        if ($data['password'] !== '') {
            $message .= ' Password changed.';
        }
        if (array_key_exists('two_factor_enabled', $data)) {
            $message .= ' 2FA preference ' . ($data['two_factor_enabled'] === 1 ? 'enabled' : 'disabled') . '.';
        }
        log_user_action('settings_updated', 'users', $userId, $message);

        flash('success', 'Settings updated.');
        redirect('/settings');
    }

    private function collectFormData(): array
    {
        return [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
            'two_factor_enabled' => !empty($_POST['two_factor_enabled']) ? 1 : 0,
        ];
    }

    private function validate(array $data, int $userId): array
    {
        $errors = [];

        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }

        if (User::emailInUse($data['email'], $userId)) {
            $errors[] = 'That email is already in use.';
        }

        if ($data['password'] !== '' && $data['password'] !== $data['password_confirm']) {
            $errors[] = 'Password confirmation does not match.';
        }

        return $errors;
    }
}
