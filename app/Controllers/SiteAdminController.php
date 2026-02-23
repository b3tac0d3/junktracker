<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use Core\Controller;

final class SiteAdminController extends Controller
{
    public function index(): void
    {
        $this->authorize();

        $query = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $businesses = Business::search($query, $status);
        $currentBusinessId = current_business_id();
        $currentBusiness = Business::findById($currentBusinessId);

        $this->render('site_admin/index', [
            'pageTitle' => 'Site Admin',
            'businesses' => $businesses,
            'query' => $query,
            'status' => $status,
            'currentBusinessId' => $currentBusinessId,
            'currentBusiness' => $currentBusiness,
            'businessTableReady' => Business::isAvailable(),
        ]);

        clear_old();
    }

    public function storeBusiness(): void
    {
        $this->authorize();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin');
        }

        if (!Business::isAvailable()) {
            flash('error', 'Businesses table is not available yet. Run the latest migration bundle first.');
            redirect('/site-admin');
        }

        $data = $this->collectBusinessInput($_POST);

        $errors = $this->validateBusinessInput($data);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/site-admin');
        }

        $businessId = Business::create($data, auth_user_id());
        if ($businessId <= 0) {
            flash('error', 'Unable to create business right now.');
            redirect('/site-admin');
        }

        if ((int) ($_POST['switch_now'] ?? 0) === 1) {
            set_active_business_id($businessId);
        }

        $name = trim((string) ($data['name'] ?? ''));
        log_user_action(
            'business_created',
            'businesses',
            $businessId,
            'Created business #' . $businessId . ($name !== '' ? ' (' . $name . ')' : '') . '.'
        );

        flash('success', (int) ($_POST['switch_now'] ?? 0) === 1
            ? 'Business created and active context switched.'
            : 'Business created.');
        redirect('/site-admin');
    }

    public function editBusiness(array $params): void
    {
        $this->authorize();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/site-admin');
        }

        $business = Business::findById($id);
        if (!$business) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $this->render('site_admin/edit', [
            'pageTitle' => 'Edit Business',
            'business' => $business,
        ]);

        clear_old();
    }

    public function updateBusiness(array $params): void
    {
        $this->authorize();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/site-admin');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin/businesses/' . $id . '/edit');
        }

        $business = Business::findById($id);
        if (!$business) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $data = $this->collectBusinessInput($_POST);
        $errors = $this->validateBusinessInput($data, $id);
        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/site-admin/businesses/' . $id . '/edit');
        }

        Business::update($id, $data, auth_user_id());
        log_user_action('business_updated', 'businesses', $id, 'Updated business #' . $id . '.');

        // If current workspace is now inactive, force fallback workspace selection.
        if ((int) ($data['is_active'] ?? 1) !== 1 && current_business_id() === $id) {
            set_active_business_id(0);
        }

        flash('success', 'Business updated.');
        redirect('/site-admin/businesses/' . $id);
    }

    public function switchBusiness(): void
    {
        $this->authorize();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/site-admin');
        }

        $businessId = (int) ($_POST['business_id'] ?? 0);
        if ($businessId <= 0 || !Business::exists($businessId, false)) {
            flash('error', 'Business not found.');
            redirect('/site-admin');
        }

        $business = Business::findById($businessId);
        if (!$business) {
            flash('error', 'Business not found.');
            redirect('/site-admin');
        }

        if ((int) ($business['is_active'] ?? 0) !== 1) {
            flash('error', 'This business is inactive and cannot be selected.');
            redirect('/site-admin');
        }

        set_active_business_id($businessId);
        log_user_action(
            'business_context_switched',
            'businesses',
            $businessId,
            'Switched active business context to #' . $businessId . '.'
        );

        flash('success', 'Active business switched to ' . (string) ($business['name'] ?? ('#' . $businessId)) . '.');
        $next = trim((string) ($_POST['next'] ?? '/admin'));
        if ($next === '' || $next[0] !== '/') {
            $next = '/admin';
        }
        redirect($next);
    }

    public function showBusiness(array $params): void
    {
        $this->authorize();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/site-admin');
        }

        $business = Business::findWithStats($id);
        if (!$business) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $this->render('site_admin/show', [
            'pageTitle' => 'Business Profile',
            'business' => $business,
            'isCurrentWorkspace' => $id === current_business_id(),
        ]);
    }

    private function authorize(): void
    {
        require_role(4);
    }

    private function validateBusinessInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Business name is required.';
        } elseif (Business::nameInUse($name, $excludeId)) {
            $errors[] = 'That business name is already in use.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Business email must be a valid email address.';
        }

        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Business website must be a valid URL.';
        }

        return $errors;
    }

    private function collectBusinessInput(array $source): array
    {
        return [
            'name' => trim((string) ($source['name'] ?? '')),
            'legal_name' => trim((string) ($source['legal_name'] ?? '')),
            'email' => trim((string) ($source['email'] ?? '')),
            'phone' => trim((string) ($source['phone'] ?? '')),
            'website' => trim((string) ($source['website'] ?? '')),
            'is_active' => (int) ($source['is_active'] ?? 1) === 1 ? 1 : 0,
        ];
    }
}
