<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use Core\ApiController;

final class AuthController extends ApiController
{
    public function login(): void
    {
        if (!ApiToken::tableExists()) {
            $this->fail('API not configured. Run database migrations.', 503);
        }

        $input = $this->input();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $deviceName = trim((string) ($input['device_name'] ?? ''));

        if ($email === '' || $password === '') {
            $this->fail('Email and password are required.', 422, [
                'email' => $email === '' ? 'Required.' : '',
                'password' => $password === '' ? 'Required.' : '',
            ]);
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $this->fail('Invalid credentials.', 401);
        }

        $context = $this->buildAuthContext($user);
        if ($context === null) {
            $this->fail('Account is not ready for mobile login.', 403);
        }

        $access = ApiToken::issue((int) $context['user']['id'], (int) $context['business']['id'], 'access', $deviceName);
        $refresh = ApiToken::issue((int) $context['user']['id'], (int) $context['business']['id'], 'refresh', $deviceName);
        if ($access === null || $refresh === null) {
            $this->fail('Could not issue tokens.', 500);
        }

        AuditLog::write(
            action: 'api_login',
            entity: 'users',
            entityId: (int) $context['user']['id'],
            businessId: (int) $context['business']['id'] > 0 ? (int) $context['business']['id'] : null,
            userId: (int) $context['user']['id'],
            meta: ['device_name' => $deviceName !== '' ? $deviceName : null]
        );

        $this->ok([
            'access_token' => $access['plain'],
            'refresh_token' => $refresh['plain'],
            'token_type' => 'Bearer',
            'expires_at' => (string) ($access['row']['expires_at'] ?? ''),
            'user' => $context['user'],
            'business' => $context['business'],
            'workspace_role' => $context['workspace_role'],
            'module_flags' => $context['module_flags'],
            'label_job' => $context['label_job'],
        ]);
    }

    public function refresh(): void
    {
        if (!ApiToken::tableExists()) {
            $this->fail('API not configured. Run database migrations.', 503);
        }

        $input = $this->input();
        $refreshToken = trim((string) ($input['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            $this->fail('Refresh token is required.', 422);
        }

        $row = ApiToken::findValidRefreshToken($refreshToken);
        if ($row === null) {
            $this->fail('Invalid refresh token.', 401);
        }

        $userId = (int) ($row['user_id'] ?? 0);
        $user = User::findById($userId);
        if ($user === null) {
            $this->fail('Invalid refresh token.', 401);
        }

        ApiToken::revokePlainToken($refreshToken);

        $context = $this->buildAuthContext($user, (int) ($row['business_id'] ?? 0));
        if ($context === null) {
            $this->fail('Account is not ready for mobile login.', 403);
        }

        $access = ApiToken::issue($userId, (int) $context['business']['id'], 'access');
        $refresh = ApiToken::issue($userId, (int) $context['business']['id'], 'refresh');
        if ($access === null || $refresh === null) {
            $this->fail('Could not issue tokens.', 500);
        }

        $this->ok([
            'access_token' => $access['plain'],
            'refresh_token' => $refresh['plain'],
            'token_type' => 'Bearer',
            'expires_at' => (string) ($access['row']['expires_at'] ?? ''),
            'user' => $context['user'],
            'business' => $context['business'],
            'workspace_role' => $context['workspace_role'],
            'module_flags' => $context['module_flags'],
            'label_job' => $context['label_job'],
        ]);
    }

    public function logout(): void
    {
        $plain = api_bearer_token();
        if ($plain !== null) {
            ApiToken::revokePlainToken($plain);
        }

        $input = $this->input();
        $refreshToken = trim((string) ($input['refresh_token'] ?? ''));
        if ($refreshToken !== '') {
            ApiToken::revokePlainToken($refreshToken);
        }

        $this->ok(['logged_out' => true]);
    }

    public function me(): void
    {
        $this->authenticate();

        $user = auth_user();
        if ($user === null) {
            $this->fail('Unauthorized', 401);
        }

        $businessId = current_business_id();
        $business = $businessId > 0 ? Business::findById($businessId) : null;

        $this->ok([
            'user' => $this->serializeUser($user),
            'business' => $this->serializeBusiness($business),
            'workspace_role' => workspace_role(),
            'module_flags' => business_module_flags($business),
            'label_job' => business_job_label($business),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function buildAuthContext(array $user, int $preferredBusinessId = 0): ?array
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $invitedAt = trim((string) ($user['invited_at'] ?? ''));
        $invitationAcceptedAt = trim((string) ($user['invitation_accepted_at'] ?? ''));
        $invitationExpiresAt = trim((string) ($user['invitation_expires_at'] ?? ''));
        $invitationExpired = $invitedAt !== ''
            && $invitationAcceptedAt === ''
            && $invitationExpiresAt !== ''
            && strtotime($invitationExpiresAt) !== false
            && strtotime($invitationExpiresAt) < time();
        if ($invitationExpired) {
            return null;
        }

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            return null;
        }
        if ($invitedAt !== '' && $invitationAcceptedAt === '') {
            return null;
        }

        $globalRole = trim((string) ($user['role'] ?? 'general_user'));
        if ($globalRole === 'site_admin') {
            return null;
        }

        $membership = null;
        if ($preferredBusinessId > 0) {
            $membership = BusinessMembership::findForBusiness($preferredBusinessId, $userId);
            if ($membership === null || trim((string) ($membership['deleted_at'] ?? '')) !== '' || (int) ($membership['is_active'] ?? 1) !== 1) {
                $membership = null;
            }
        }
        if ($membership === null) {
            $membership = BusinessMembership::firstActiveMembership($userId);
        }
        if ($membership === null) {
            return null;
        }

        $businessId = (int) ($membership['business_id'] ?? 0);
        $business = $businessId > 0 ? Business::findById($businessId) : null;
        if ($business === null) {
            return null;
        }

        $sessionUser = [
            'id' => $userId,
            'email' => strtolower(trim((string) ($user['email'] ?? ''))),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'role' => $globalRole,
            'workspace_role' => (string) ($membership['role'] ?? 'general_user'),
            'business_id' => $businessId,
        ];

        return [
            'user' => $this->serializeUser($sessionUser),
            'business' => $this->serializeBusiness($business),
            'workspace_role' => (string) ($membership['role'] ?? 'general_user'),
            'module_flags' => business_module_flags($business),
            'label_job' => business_job_label($business),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function serializeUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'display_name' => trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? ''))) ?: (string) ($user['email'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed>|null $business
     * @return array<string, mixed>
     */
    private function serializeBusiness(?array $business): array
    {
        if ($business === null) {
            return ['id' => 0, 'name' => ''];
        }

        return [
            'id' => (int) ($business['id'] ?? 0),
            'name' => (string) ($business['name'] ?? ''),
            'timezone' => (string) config('app.timezone', 'America/New_York'),
        ];
    }
}
