<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

final class AdminController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $this->render('admin/index', [
            'pageTitle' => 'Business Admin',
        ]);
    }
}

