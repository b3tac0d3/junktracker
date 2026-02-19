<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AdminPanel;
use App\Models\Recovery;
use Core\Controller;

final class AdminController extends Controller
{
    public function index(): void
    {
        require_permission('admin', 'view');

        $this->render('admin/index', [
            'pageTitle' => 'Admin',
            'health' => AdminPanel::healthSummary(),
            'systemStatus' => AdminPanel::systemStatus(),
            'deletedCounts' => Recovery::softDeleteCounts(),
        ]);
    }
}
