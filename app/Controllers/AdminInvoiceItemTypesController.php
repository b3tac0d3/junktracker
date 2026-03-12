<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\InvoiceItemType;
use Core\Controller;

final class AdminInvoiceItemTypesController extends Controller
{
    public function index(): void
    {
        require_business_role(['admin']);

        $businessId = current_business_id();
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);

        $totalRows = InvoiceItemType::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $types = InvoiceItemType::indexList($businessId, $search, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($types));

        $this->render('admin/invoice_item_types/index', [
            'pageTitle' => 'Invoice Item Types',
            'search' => $search,
            'status' => $status,
            'types' => $types,
            'pagination' => $pagination,
            'tableAvailable' => InvoiceItemType::isAvailable(),
        ]);
    }

    public function create(): void
    {
        require_business_role(['admin']);

        $this->render('admin/invoice_item_types/form', [
            'pageTitle' => 'Add Invoice Item Type',
            'mode' => 'create',
            'actionUrl' => url('/admin/invoice-item-types'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'tableAvailable' => InvoiceItemType::isAvailable(),
        ]);
    }

    public function store(): void
    {
        require_business_role(['admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/invoice-item-types/create');
        }

        if (!InvoiceItemType::isAvailable()) {
            flash('error', 'Invoice item types table is missing. Run migrations first.');
            redirect('/admin/invoice-item-types');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($businessId, $form, null);

        if ($errors !== []) {
            $this->render('admin/invoice_item_types/form', [
                'pageTitle' => 'Add Invoice Item Type',
                'mode' => 'create',
                'actionUrl' => url('/admin/invoice-item-types'),
                'form' => $form,
                'errors' => $errors,
                'tableAvailable' => true,
            ]);
            return;
        }

        InvoiceItemType::create($businessId, $this->payloadForSave($form), (int) (auth_user_id() ?? 0));
        flash('success', 'Invoice item type added.');
        redirect('/admin/invoice-item-types');
    }

    public function edit(array $params): void
    {
        require_business_role(['admin']);

        $typeId = (int) ($params['id'] ?? 0);
        if ($typeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $type = InvoiceItemType::findForBusiness($businessId, $typeId);
        if ($type === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('admin/invoice_item_types/form', [
            'pageTitle' => 'Edit Invoice Item Type',
            'mode' => 'edit',
            'actionUrl' => url('/admin/invoice-item-types/' . (string) $typeId . '/update'),
            'form' => $this->formFromModel($type),
            'errors' => [],
            'typeId' => $typeId,
            'tableAvailable' => true,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['admin']);

        $typeId = (int) ($params['id'] ?? 0);
        if ($typeId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/invoice-item-types/' . (string) $typeId . '/edit');
        }

        $businessId = current_business_id();
        $existing = InvoiceItemType::findForBusiness($businessId, $typeId);
        if ($existing === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($businessId, $form, $typeId);

        if ($errors !== []) {
            $this->render('admin/invoice_item_types/form', [
                'pageTitle' => 'Edit Invoice Item Type',
                'mode' => 'edit',
                'actionUrl' => url('/admin/invoice-item-types/' . (string) $typeId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'typeId' => $typeId,
                'tableAvailable' => true,
            ]);
            return;
        }

        InvoiceItemType::update($businessId, $typeId, $this->payloadForSave($form), (int) (auth_user_id() ?? 0));
        flash('success', 'Invoice item type updated.');
        redirect('/admin/invoice-item-types');
    }

    public function delete(array $params): void
    {
        require_business_role(['admin']);

        $typeId = (int) ($params['id'] ?? 0);
        if ($typeId <= 0) {
            flash('error', 'Invalid invoice item type.');
            redirect('/admin/invoice-item-types');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/admin/invoice-item-types');
        }

        $businessId = current_business_id();
        $type = InvoiceItemType::findForBusiness($businessId, $typeId);
        if ($type === null) {
            flash('error', 'Invoice item type was not found.');
            redirect('/admin/invoice-item-types');
        }

        InvoiceItemType::softDelete($businessId, $typeId, (int) (auth_user_id() ?? 0));
        flash('success', 'Invoice item type removed.');
        redirect('/admin/invoice-item-types');
    }

    private function defaultForm(): array
    {
        return [
            'name' => '',
            'default_unit_price' => '0.00',
            'default_taxable' => '1',
            'default_note' => '',
            'sort_order' => '100',
            'is_active' => '1',
        ];
    }

    private function formFromModel(array $type): array
    {
        return [
            'name' => trim((string) ($type['name'] ?? '')),
            'default_unit_price' => number_format((float) ($type['default_unit_price'] ?? 0), 2, '.', ''),
            'default_taxable' => ((int) ($type['default_taxable'] ?? 0)) === 1 ? '1' : '0',
            'default_note' => trim((string) ($type['default_note'] ?? '')),
            'sort_order' => (string) ((int) ($type['sort_order'] ?? 100)),
            'is_active' => ((int) ($type['is_active'] ?? 1)) === 1 ? '1' : '0',
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'default_unit_price' => trim((string) ($input['default_unit_price'] ?? '0')),
            'default_taxable' => ((string) ($input['default_taxable'] ?? '0')) === '1' ? '1' : '0',
            'default_note' => trim((string) ($input['default_note'] ?? '')),
            'sort_order' => trim((string) ($input['sort_order'] ?? '100')),
            'is_active' => ((string) ($input['is_active'] ?? '1')) === '1' ? '1' : '0',
        ];
    }

    private function validateForm(int $businessId, array $form, ?int $excludeId): array
    {
        $errors = [];

        if ($form['name'] === '') {
            $errors['name'] = 'Name is required.';
        } elseif (InvoiceItemType::nameExists($businessId, $form['name'], $excludeId)) {
            $errors['name'] = 'An item with this name already exists.';
        }

        if (!is_numeric($form['default_unit_price']) || (float) $form['default_unit_price'] < 0) {
            $errors['default_unit_price'] = 'Default price must be zero or greater.';
        }

        if (!is_numeric($form['sort_order']) || (int) $form['sort_order'] < 0) {
            $errors['sort_order'] = 'Sort order must be zero or greater.';
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'name' => $form['name'],
            'default_unit_price' => round((float) $form['default_unit_price'], 2),
            'default_taxable' => $form['default_taxable'] === '1' ? 1 : 0,
            'default_note' => $form['default_note'],
            'sort_order' => max(0, (int) $form['sort_order']),
            'is_active' => $form['is_active'] === '1' ? 1 : 0,
        ];
    }
}
