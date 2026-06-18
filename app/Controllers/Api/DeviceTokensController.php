<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\DeviceToken;
use Core\ApiController;

final class DeviceTokensController extends ApiController
{
    public function register(): void
    {
        $this->authenticate();

        if (!DeviceToken::tableExists()) {
            $this->fail('Push notifications not configured. Run database migrations.', 503);
        }

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            $this->fail('Unauthorized', 401);
        }

        $input = $this->input();
        $token = trim((string) ($input['token'] ?? ''));
        $platform = trim((string) ($input['platform'] ?? 'android'));
        $deviceName = trim((string) ($input['device_name'] ?? ''));

        if ($token === '') {
            $this->fail('Device token is required.', 422);
        }

        $businessId = current_business_id();
        if (!DeviceToken::upsert($userId, $businessId > 0 ? $businessId : null, $platform, $token, $deviceName)) {
            $this->fail('Could not register device token.', 500);
        }

        $this->ok(['registered' => true]);
    }

    public function unregister(): void
    {
        $this->authenticate();

        $userId = auth_user_id() ?? 0;
        if ($userId <= 0) {
            $this->fail('Unauthorized', 401);
        }

        $input = $this->input();
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            $this->fail('Device token is required.', 422);
        }

        DeviceToken::revokeForUser($userId, $token);
        $this->ok(['registered' => false]);
    }
}
