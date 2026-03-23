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

        $this->render('site_admin/businesses', $this->businessesPageData());
    }

    public function createBusiness(): void
    {
        require_role(['site_admin']);

        $this->render('site_admin/business_form', [
            'pageTitle' => 'Add Company',
            'isCreate' => true,
            'business' => ['id' => 0, 'name' => ''],
            'actionUrl' => url('/site-admin/businesses'),
            'form' => $this->businessFormDefaults(),
            'errors' => [],
        ]);
    }

    public function showBusiness(array $params): void
    {
        require_role(['site_admin']);

        $businessId = (int) ($params['id'] ?? 0);
        if ($businessId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $business = Business::findById($businessId);
        if ($business === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('site_admin/business_show', [
            'pageTitle' => 'Company Profile',
            'business' => $business,
        ]);
    }

    public function editBusiness(array $params): void
    {
        require_role(['site_admin']);

        $businessId = (int) ($params['id'] ?? 0);
        if ($businessId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $business = Business::findById($businessId);
        if ($business === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('site_admin/business_form', [
            'pageTitle' => 'Edit Company',
            'isCreate' => false,
            'business' => $business,
            'actionUrl' => url('/site-admin/businesses/' . (string) $businessId . '/update'),
            'form' => $this->businessFormFromRecord($business),
            'errors' => [],
        ]);
    }

    public function updateBusiness(array $params): void
    {
        require_role(['site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Try again.');
            redirect('/site-admin/businesses');
        }

        $businessId = (int) ($params['id'] ?? 0);
        if ($businessId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $business = Business::findById($businessId);
        if ($business === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->businessFormFromPost($_POST);
        $errors = $this->validateBusinessForm($form);
        if ($errors !== []) {
            $this->render('site_admin/business_form', [
                'pageTitle' => 'Edit Company',
                'isCreate' => false,
                'business' => $business,
                'actionUrl' => url('/site-admin/businesses/' . (string) $businessId . '/update'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        Business::updateDetails($businessId, [
            'name' => $form['name'],
            'legal_name' => $form['legal_name'],
            'email' => $form['email'],
            'phone' => $form['phone'],
            'address_line1' => $form['address_line1'],
            'address_line2' => $form['address_line2'],
            'city' => $form['city'],
            'state' => $form['state'],
            'postal_code' => $form['postal_code'],
        ], (int) (auth_user_id() ?? 0));

        flash('success', 'Company updated.');
        redirect('/site-admin/businesses/' . (string) $businessId);
    }

    public function toggleBusinessActive(array $params): void
    {
        require_role(['site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Try again.');
            redirect('/site-admin/businesses');
        }

        $businessId = (int) ($params['id'] ?? 0);
        if ($businessId <= 0) {
            flash('error', 'Invalid company.');
            redirect('/site-admin/businesses');
        }

        $business = Business::findById($businessId);
        if ($business === null) {
            flash('error', 'Company not found.');
            redirect('/site-admin/businesses');
        }

        $setActive = ((string) ($_POST['set_active'] ?? '0')) === '1';
        Business::setActive($businessId, $setActive, (int) (auth_user_id() ?? 0));
        flash('success', $setActive ? 'Company reactivated.' : 'Company deactivated.');

        if (!$setActive && (int) (current_business_id() ?? 0) === $businessId) {
            unset($_SESSION['active_business_id']);
            $_SESSION['user']['business_id'] = 0;
            $_SESSION['user']['workspace_role'] = 'site_admin';
        }

        redirect('/site-admin/businesses/' . (string) $businessId);
    }

    public function storeBusiness(): void
    {
        require_role(['site_admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Try again.');
            redirect('/site-admin/businesses');
        }

        $form = $this->businessFormFromPost($_POST);
        $errors = $this->validateBusinessForm($form);
        if ($errors !== []) {
            $this->render('site_admin/business_form', [
                'pageTitle' => 'Add Company',
                'isCreate' => true,
                'business' => ['id' => 0, 'name' => ''],
                'actionUrl' => url('/site-admin/businesses'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        $actorUserId = (int) (auth_user_id() ?? 0);
        Business::create($form, $actorUserId);

        flash('success', 'Company added.');
        redirect('/site-admin/businesses');
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

        $business = Business::findById($businessId);
        if ($business === null || (int) ($business['is_active'] ?? 0) !== 1) {
            flash('error', 'That company is inactive or unavailable.');
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

    private function businessesPageData(): array
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'active')));
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'active';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Business::countForSiteAdmin($status, $query);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);
        $businesses = Business::allForSiteAdmin($status, $query, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($businesses));

        return [
            'pageTitle' => 'Select Business Workspace',
            'businesses' => $businesses,
            'pagination' => $pagination,
            'query' => $query,
            'status' => $status,
            'counts' => [
                'all' => Business::countForSiteAdmin('all'),
                'active' => Business::countForSiteAdmin('active'),
                'inactive' => Business::countForSiteAdmin('inactive'),
            ],
        ];
    }

    private function businessFormFromRecord(array $record): array
    {
        return [
            'name' => trim((string) ($record['name'] ?? '')),
            'legal_name' => trim((string) ($record['legal_name'] ?? '')),
            'email' => trim((string) ($record['email'] ?? '')),
            'phone' => trim((string) ($record['phone'] ?? '')),
            'address_line1' => trim((string) ($record['address_line1'] ?? '')),
            'address_line2' => trim((string) ($record['address_line2'] ?? '')),
            'city' => trim((string) ($record['city'] ?? '')),
            'state' => trim((string) ($record['state'] ?? '')),
            'postal_code' => trim((string) ($record['postal_code'] ?? '')),
            'country' => 'US',
        ];
    }

    private function businessFormDefaults(): array
    {
        return [
            'name' => '',
            'legal_name' => '',
            'email' => '',
            'phone' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'US',
        ];
    }

    private function businessFormFromPost(array $input): array
    {
        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'legal_name' => trim((string) ($input['legal_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'country' => 'US',
        ];
    }

    private function validateBusinessForm(array $form): array
    {
        $errors = [];

        if ($form['name'] === '') {
            $errors['name'] = 'Company name is required.';
        }

        if ($form['email'] !== '' && filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }
}
