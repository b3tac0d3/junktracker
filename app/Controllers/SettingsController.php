<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Controller;

final class SettingsController extends Controller
{
    public function edit(): void
    {
        require_auth();

        $userId = (int) (auth_user_id() ?? 0);
        $user = User::findById($userId);
        if ($user === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('settings/edit', [
            'pageTitle' => 'Settings',
            'actionUrl' => url('/settings/update'),
            'form' => $this->formFromUser($user),
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        require_auth();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/settings');
        }

        $userId = (int) (auth_user_id() ?? 0);
        $user = User::findById($userId);
        if ($user === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $userId);
        if ($errors !== []) {
            $this->render('settings/edit', [
                'pageTitle' => 'Settings',
                'actionUrl' => url('/settings/update'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        $payload = [
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'],
            'email' => $form['email'],
        ];
        if ($form['password'] !== '') {
            $payload['password_hash'] = password_hash($form['password'], PASSWORD_DEFAULT);
        }

        User::updateProfile($userId, $payload, $userId);

        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['first_name'] = $form['first_name'];
            $_SESSION['user']['last_name'] = $form['last_name'];
            $_SESSION['user']['email'] = strtolower($form['email']);
        }

        flash('success', 'Settings updated.');
        redirect('/settings');
    }

    private function formFromUser(array $user): array
    {
        return [
            'first_name' => trim((string) ($user['first_name'] ?? '')),
            'last_name' => trim((string) ($user['last_name'] ?? '')),
            'email' => trim((string) ($user['email'] ?? '')),
            'password' => '',
            'password_confirm' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim(strtolower((string) ($input['email'] ?? ''))),
            'password' => (string) ($input['password'] ?? ''),
            'password_confirm' => (string) ($input['password_confirm'] ?? ''),
        ];
    }

    private function validateForm(array $form, int $userId): array
    {
        $errors = [];

        if ($form['first_name'] === '' && $form['last_name'] === '') {
            $errors['first_name'] = 'First or last name is required.';
        }

        if ($form['email'] === '' || filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email.';
        } elseif (User::emailExists($form['email'], $userId)) {
            $errors['email'] = 'Email is already in use.';
        }

        if ($form['password'] !== '') {
            if (strlen($form['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            }
            if (!hash_equals($form['password'], $form['password_confirm'])) {
                $errors['password_confirm'] = 'Passwords do not match.';
            }
        }

        return $errors;
    }
}

