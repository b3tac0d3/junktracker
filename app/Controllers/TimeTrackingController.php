<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

final class TimeTrackingController extends Controller
{
    public function index(): void
    {
        require_business_role(['punch_only', 'general_user', 'admin']);

        $this->render('placeholders/module', [
            'pageTitle' => 'Time Tracking',
            'title' => 'Time Tracking',
            'message' => 'Phase A scaffold: time tracking module will be implemented in Phase C.',
        ]);
    }
}
