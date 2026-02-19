<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dashboard;
use Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        require_permission('dashboard', 'view');

        $overview = Dashboard::overview();

        $this->render('home/index', [
            'pageTitle' => 'Dashboard',
            'overview' => $overview,
        ]);
    }
}
