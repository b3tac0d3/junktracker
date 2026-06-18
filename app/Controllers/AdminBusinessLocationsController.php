<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BusinessLocation;
use Core\Controller;

final class AdminBusinessLocationsController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        $search = trim((string) ($_GET['q'] ?? ''));
        $type = strtolower(trim((string) ($_GET['type'] ?? '')));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = BusinessLocation::indexCount($businessId, $search, $type, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $locations = BusinessLocation::indexList($businessId, $search, $type, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($locations));

        $this->render('admin/business_locations/index', [
            'pageTitle' => 'Business Locations',
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'locations' => $locations,
            'pagination' => $pagination,
            'typeOptions' => BusinessLocation::typeOptions(),
            'tableAvailable' => BusinessLocation::isAvailable(),
        ]);
    }

    public function create(): void
    {
        require_business_role(['admin']);

        $this->render('admin/business_locations/form', [
            'pageTitle' => 'Add Location',
            'mode' => 'create',
            'actionUrl' => url('/admin/business-locations'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'typeOptions' => BusinessLocation::typeOptions(),
            'tableAvailable' => BusinessLocation::isAvailable(),
        ]);
    }

    public function store(): void
    {
        require_business_role(['admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/business-locations/create');
        }

        if (!BusinessLocation::isAvailable()) {
            flash('error', 'Business locations table is missing. Run migrations first.');
            redirect('/admin/business-locations');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);

        if ($errors !== []) {
            $this->render('admin/business_locations/form', [
                'pageTitle' => 'Add Location',
                'mode' => 'create',
                'actionUrl' => url('/admin/business-locations'),
                'form' => $form,
                'errors' => $errors,
                'typeOptions' => BusinessLocation::typeOptions(),
                'tableAvailable' => true,
            ]);
            return;
        }

        $locationId = BusinessLocation::create($businessId, $this->payloadForSave($form), (int) (auth_user_id() ?? 0));
        audit('business_location_created', 'business_locations', $locationId > 0 ? $locationId : null, [
            'name' => $form['name'] ?? '',
            'location_type' => $form['location_type'] ?? '',
        ]);
        flash('success', 'Location added.');
        redirect('/admin/business-locations');
    }

    public function edit(array $params): void
    {
        require_business_role(['admin']);

        $locationId = (int) ($params['id'] ?? 0);
        if ($locationId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $location = BusinessLocation::findForBusiness($businessId, $locationId);
        if ($location === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/business_locations/form', [
            'pageTitle' => 'Edit Location',
            'mode' => 'edit',
            'actionUrl' => url('/admin/business-locations/' . (string) $locationId . '/update'),
            'form' => $this->formFromModel($location),
            'errors' => [],
            'locationId' => $locationId,
            'typeOptions' => BusinessLocation::typeOptions(),
            'tableAvailable' => true,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['admin']);

        $locationId = (int) ($params['id'] ?? 0);
        if ($locationId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/business-locations/' . (string) $locationId . '/edit');
        }

        $businessId = current_business_id();
        $existing = BusinessLocation::findForBusiness($businessId, $locationId);
        if ($existing === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);

        if ($errors !== []) {
            $this->render('admin/business_locations/form', [
                'pageTitle' => 'Edit Location',
                'mode' => 'edit',
                'actionUrl' => url('/admin/business-locations/' . (string) $locationId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'locationId' => $locationId,
                'typeOptions' => BusinessLocation::typeOptions(),
                'tableAvailable' => true,
            ]);
            return;
        }

        BusinessLocation::update($businessId, $locationId, $this->payloadForSave($form), (int) (auth_user_id() ?? 0));
        audit('business_location_updated', 'business_locations', $locationId);
        flash('success', 'Location updated.');
        redirect('/admin/business-locations');
    }

    public function delete(array $params): void
    {
        require_business_role(['admin']);

        $locationId = (int) ($params['id'] ?? 0);
        if ($locationId <= 0) {
            flash('error', 'Invalid location.');
            redirect('/admin/business-locations');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/business-locations');
        }

        $businessId = current_business_id();
        $location = BusinessLocation::findForBusiness($businessId, $locationId);
        if ($location === null) {
            flash('error', 'Location was not found.');
            redirect('/admin/business-locations');
        }

        BusinessLocation::softDelete($businessId, $locationId, (int) (auth_user_id() ?? 0));
        audit('business_location_deleted', 'business_locations', $locationId);
        flash('success', 'Location removed.');
        redirect('/admin/business-locations');
    }

    private function defaultForm(): array
    {
        return [
            'location_type' => BusinessLocation::TYPE_STORE,
            'name' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'US',
            'phone' => '',
            'notes' => '',
            'is_active' => '1',
            'sort_order' => '0',
        ];
    }

    private function formFromModel(array $location): array
    {
        return [
            'location_type' => strtolower(trim((string) ($location['location_type'] ?? BusinessLocation::TYPE_OTHER))),
            'name' => trim((string) ($location['name'] ?? '')),
            'address_line1' => trim((string) ($location['address_line1'] ?? '')),
            'address_line2' => trim((string) ($location['address_line2'] ?? '')),
            'city' => trim((string) ($location['city'] ?? '')),
            'state' => trim((string) ($location['state'] ?? '')),
            'postal_code' => trim((string) ($location['postal_code'] ?? '')),
            'country' => trim((string) ($location['country'] ?? 'US')),
            'phone' => trim((string) ($location['phone'] ?? '')),
            'notes' => trim((string) ($location['notes'] ?? '')),
            'is_active' => ((int) ($location['is_active'] ?? 1)) === 1 ? '1' : '0',
            'sort_order' => (string) max(0, (int) ($location['sort_order'] ?? 0)),
        ];
    }

    private function formFromPost(array $input): array
    {
        $type = strtolower(trim((string) ($input['location_type'] ?? BusinessLocation::TYPE_OTHER)));
        if (!array_key_exists($type, BusinessLocation::typeOptions())) {
            $type = BusinessLocation::TYPE_OTHER;
        }

        return [
            'location_type' => $type,
            'name' => trim((string) ($input['name'] ?? '')),
            'address_line1' => trim((string) ($input['address_line1'] ?? '')),
            'address_line2' => trim((string) ($input['address_line2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'country' => trim((string) ($input['country'] ?? 'US')) ?: 'US',
            'phone' => trim((string) ($input['phone'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'is_active' => ((string) ($input['is_active'] ?? '1')) === '1' ? '1' : '0',
            'sort_order' => trim((string) ($input['sort_order'] ?? '0')),
        ];
    }

    private function validateForm(array $form): array
    {
        $errors = [];

        if ($form['name'] === '') {
            $errors['name'] = 'Location name is required.';
        }

        if (!array_key_exists($form['location_type'], BusinessLocation::typeOptions())) {
            $errors['location_type'] = 'Choose a valid location type.';
        }

        if ($form['sort_order'] !== '' && (!ctype_digit($form['sort_order']) || (int) $form['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be zero or greater.';
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'location_type' => $form['location_type'],
            'name' => $form['name'],
            'address_line1' => $form['address_line1'],
            'address_line2' => $form['address_line2'],
            'city' => $form['city'],
            'state' => $form['state'],
            'postal_code' => $form['postal_code'],
            'country' => $form['country'],
            'phone' => $form['phone'],
            'notes' => $form['notes'],
            'is_active' => $form['is_active'] === '1' ? 1 : 0,
            'sort_order' => $form['sort_order'] !== '' ? (int) $form['sort_order'] : 0,
        ];
    }
}
