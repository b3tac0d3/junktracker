<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\DashboardSummary;
use App\Models\Employee;
use App\Models\TimeEntry;
use Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        require_auth();

        if (is_site_admin() && current_business_id() <= 0) {
            redirect('/site-admin/businesses');
        }

        if (workspace_role() === 'punch_only') {
            redirect('/time-tracking/punch-board');
        }

        business_context_required();

        $businessId = current_business_id();
        $business = Business::findById($businessId);

        $summary = DashboardSummary::byBusiness($businessId, auth_user_id() ?? 0);
        $selfEmployee = Employee::findByUserForBusiness($businessId, auth_user_id() ?? 0);
        $selfOpenEntry = null;
        if (is_array($selfEmployee) && ((int) ($selfEmployee['id'] ?? 0)) > 0) {
            $selfOpenEntry = TimeEntry::openEntryForEmployee($businessId, (int) $selfEmployee['id']);
        }

        $canViewPunchBoard = is_site_admin() || workspace_role() === 'admin';
        $openPunches = [];
        if ($canViewPunchBoard) {
            $rows = TimeEntry::punchBoardEmployees($businessId);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (((int) ($row['open_entry_id'] ?? 0)) <= 0) {
                    continue;
                }
                $openPunches[] = $row;
            }
        }

        $this->render('home/index', [
            'pageTitle' => 'Dashboard',
            'business' => $business,
            'summary' => $summary,
            'selfEmployee' => $selfEmployee,
            'selfOpenEntry' => $selfOpenEntry,
            'canViewPunchBoard' => $canViewPunchBoard,
            'openPunches' => $openPunches,
        ]);
    }
}
