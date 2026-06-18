<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\NavNotifications;
use Core\ApiController;

final class NotificationsController extends ApiController
{
    public function index(): void
    {
        $this->requireBusinessRole(['general_user', 'admin']);

        $summary = NavNotifications::summary();

        $this->ok([
            'items' => $summary['items'] ?? [],
            'total' => (int) ($summary['total'] ?? 0),
        ]);
    }
}
