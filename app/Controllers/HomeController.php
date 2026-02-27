<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\DashboardSummary;
use Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        require_auth();

        if (is_site_admin() && current_business_id() <= 0) {
            redirect('/site-admin/businesses');
        }

        business_context_required();

        $businessId = current_business_id();
        $business = Business::findById($businessId);

        $summary = DashboardSummary::byBusiness($businessId, auth_user_id() ?? 0);

        $this->render('home/index', [
            'pageTitle' => 'Dashboard',
            'business' => $business,
            'summary' => $summary,
        ]);
    }
}
