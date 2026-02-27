<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\BusinessMembership;
use Core\Controller;

final class SiteAdminController extends Controller
{
    public function businesses(): void
    {
        require_role(['site_admin']);

        $this->render('site_admin/businesses', [
            'pageTitle' => 'Select Business Workspace',
            'businesses' => Business::allActive(),
        ]);
    }

    public function switchBusiness(): void
    {
        require_role(['site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Try again.');
            redirect('/site-admin/businesses');
        }

        $businessId = (int) ($_POST['business_id'] ?? 0);
        if ($businessId <= 0) {
            flash('error', 'Select a valid business.');
            redirect('/site-admin/businesses');
        }

        $_SESSION['active_business_id'] = $businessId;
        $_SESSION['user']['business_id'] = $businessId;

        $workspaceRole = BusinessMembership::roleForBusiness((int) (auth_user_id() ?? 0), $businessId);
        $_SESSION['user']['workspace_role'] = $workspaceRole ?? 'admin';

        flash('success', 'Workspace updated.');
        redirect('/');
    }

    public function exitWorkspace(): void
    {
        require_role(['site_admin']);

        unset($_SESSION['active_business_id']);
        $_SESSION['user']['business_id'] = 0;
        $_SESSION['user']['workspace_role'] = 'site_admin';

        flash('success', 'Returned to global site admin view.');
        redirect('/site-admin/businesses');
    }
}
