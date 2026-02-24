<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use Core\Controller;

final class AdminBusinessInfoController extends Controller
{
    public function index(): void
    {
        require_permission('business_info', 'view');

        $businessId = current_business_id();
        $business = Business::findById($businessId);

        $this->render('admin/business_info/index', [
            'pageTitle' => 'Business Info',
            'business' => $business,
            'businessId' => $businessId,
            'isSiteAdmin' => auth_user_role() >= 4 || auth_user_role() === 99,
        ]);
    }

    public function update(): void
    {
        require_permission('business_info', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/admin/business-info');
        }

        if (auth_user_role() < 4 && auth_user_role() !== 99) {
            flash('error', 'Business profile is managed by a site admin.');
            redirect('/admin/business-info');
        }

        $businessId = current_business_id();
        if ($businessId <= 0) {
            flash('error', 'No business selected.');
            redirect('/site-admin');
        }

        redirect('/site-admin/businesses/' . $businessId . '/edit');
    }
}
