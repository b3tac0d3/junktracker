<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

final class JobsController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $this->render('placeholders/module', [
            'pageTitle' => 'Jobs',
            'title' => 'Jobs',
            'message' => 'Phase A scaffold: jobs module will be implemented in Phase B.',
        ]);
    }
}
