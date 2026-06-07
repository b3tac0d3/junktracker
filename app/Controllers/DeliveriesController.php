<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\ClientDelivery;
use App\Models\FormSelectValue;
use Core\Controller;

final class DeliveriesController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'dispatch')));
        $sortBy = strtolower(trim((string) ($_GET['sort_by'] ?? 'scheduled_at')));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? 'asc')));
        if (!in_array($sortBy, ['scheduled_at', 'id', 'client_name'], true)) {
            $sortBy = 'scheduled_at';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $businessId = current_business_id();
        $statusOptions = ClientDelivery::statusOptions();
        $allowedFilterStatuses = array_merge(['dispatch', ''], $statusOptions);
        if (!in_array($status, $allowedFilterStatuses, true)) {
            $status = 'dispatch';
        }

        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = ClientDelivery::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $deliveries = ClientDelivery::indexList($businessId, $search, $status, $perPage, $offset, $sortBy, $sortDir);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($deliveries));

        $this->render('deliveries/index', [
            'pageTitle' => 'Deliveries',
            'search' => $search,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'statusOptions' => $statusOptions,
            'deliveries' => $deliveries,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $form = $this->defaultForm();
        $prefillAt = calendar_slot_prefill_at();
        if ($prefillAt !== '') {
            $form['scheduled_at'] = $prefillAt;
            $form['status'] = 'scheduled';
        }
        $requestedClientId = (int) ($_GET['client_id'] ?? 0);
        if ($requestedClientId > 0) {
            $client = Client::findForBusiness($businessId, $requestedClientId);
            if ($client !== null) {
                $form['client_id'] = (string) $requestedClientId;
                $form['client_name'] = Client::displayName($client);
                $form['address_line1'] = trim((string) ($client['address_line1'] ?? ''));
                $form['address_line2'] = trim((string) ($client['address_line2'] ?? ''));
                $form['city'] = trim((string) ($client['city'] ?? ''));
                $form['state'] = trim((string) ($client['state'] ?? ''));
                $form['postal_code'] = trim((string) ($client['postal_code'] ?? ''));
            }
        }

        $this->render('deliveries/form', [
            'pageTitle' => 'Add Delivery',
            'mode' => 'create',
            'actionUrl' => url('/deliveries'),
            'form' => $form,
            'errors' => [],
            'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/deliveries/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = ClientDelivery::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('deliveries/form', [
                'pageTitle' => 'Add Delivery',
                'mode' => 'create',
                'actionUrl' => url('/deliveries'),
                'form' => $form,
                'errors' => $errors,
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            ]);

            return;
        }

        $actorId = (int) (auth_user_id() ?? 0);
        $id = ClientDelivery::create($businessId, $form, $actorId);
        if ($id <= 0) {
            flash('error', 'Could not save delivery.');
            redirect('/deliveries/create');
        }

        $st = strtolower(trim((string) ($form['status'] ?? '')));
        audit('delivery_created', 'deliveries', $id, ['status' => $st]);
        google_calendar_sync_item($businessId, 'delivery', $id);
        if ($st === 'need_to_schedule') {
            flash('success', 'Delivery saved.');
        } elseif ($st === 'scheduled' || $st === 'completed') {
            flash('success', 'Delivery scheduled.');
        } else {
            flash('success', 'Delivery saved.');
        }
        redirect('/deliveries/' . (string) $id);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $delivery = $this->deliveryOr404((int) ($params['id'] ?? 0));
        if ($delivery === null) {
            return;
        }

        $this->render('deliveries/show', [
            'pageTitle' => 'Delivery #' . (string) ((int) ($delivery['id'] ?? 0)),
            'delivery' => $delivery,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $delivery = $this->deliveryOr404((int) ($params['id'] ?? 0));
        if ($delivery === null) {
            return;
        }

        $this->render('deliveries/form', [
            'pageTitle' => 'Edit Delivery',
            'mode' => 'edit',
            'actionUrl' => url('/deliveries/' . (string) ((int) ($delivery['id'] ?? 0)) . '/update'),
            'form' => $this->formFromRow($delivery),
            'errors' => [],
            'deliveryId' => (int) ($delivery['id'] ?? 0),
            'clientTypeOptions' => FormSelectValue::optionsForSection(current_business_id(), 'client_type'),
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/deliveries');
        }

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $delivery = ClientDelivery::findForBusiness($businessId, $id);
        if ($delivery === null) {
            flash('error', 'Delivery not found.');
            redirect('/deliveries');
        }

        $form = $this->formFromPost($_POST);
        $errors = ClientDelivery::validate($form, $businessId);
        if ($errors !== []) {
            $this->render('deliveries/form', [
                'pageTitle' => 'Edit Delivery',
                'mode' => 'edit',
                'actionUrl' => url('/deliveries/' . (string) $id . '/update'),
                'form' => $form,
                'errors' => $errors,
                'deliveryId' => $id,
                'clientTypeOptions' => FormSelectValue::optionsForSection($businessId, 'client_type'),
            ]);

            return;
        }

        $actorId = (int) (auth_user_id() ?? 0);
        ClientDelivery::update($businessId, $id, $form, $actorId);
        audit('delivery_updated', 'deliveries', $id);
        google_calendar_sync_item($businessId, 'delivery', $id);
        flash('success', 'Delivery updated.');
        redirect('/deliveries/' . (string) $id);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/deliveries');
        }

        $id = (int) ($params['id'] ?? 0);
        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if (ClientDelivery::softDelete($businessId, $id, $actorId)) {
            audit('delivery_deleted', 'deliveries', $id);
            google_calendar_remove_item('delivery', $id);
            flash('success', 'Delivery removed.');
        } else {
            flash('error', 'Could not remove delivery.');
        }

        redirect('/deliveries');
    }

    /**
     * @return array<string, string>
     */
    private function defaultForm(): array
    {
        return [
            'client_id' => '',
            'client_name' => '',
            'scheduled_at' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'notes' => '',
            'status' => 'need_to_schedule',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function formFromPost(array $post): array
    {
        return [
            'client_id' => trim((string) ($post['client_id'] ?? '')),
            'client_name' => trim((string) ($post['client_name'] ?? '')),
            'scheduled_at' => trim((string) ($post['scheduled_at'] ?? '')),
            'address_line1' => trim((string) ($post['address_line1'] ?? '')),
            'address_line2' => trim((string) ($post['address_line2'] ?? '')),
            'city' => trim((string) ($post['city'] ?? '')),
            'state' => trim((string) ($post['state'] ?? '')),
            'postal_code' => trim((string) ($post['postal_code'] ?? '')),
            'notes' => trim((string) ($post['notes'] ?? '')),
            'status' => strtolower(trim((string) ($post['status'] ?? 'need_to_schedule'))),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function formFromRow(array $row): array
    {
        $scheduled = trim((string) ($row['scheduled_at'] ?? ''));
        $scheduledLocal = '';
        if ($scheduled !== '') {
            $ts = strtotime($scheduled);
            if ($ts !== false) {
                $scheduledLocal = date('Y-m-d\TH:i', $ts);
            }
        }

        return [
            'client_id' => (string) ((int) ($row['client_id'] ?? 0)),
            'client_name' => trim((string) ($row['client_name'] ?? '')),
            'scheduled_at' => $scheduledLocal,
            'address_line1' => trim((string) ($row['address_line1'] ?? '')),
            'address_line2' => trim((string) ($row['address_line2'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'state' => trim((string) ($row['state'] ?? '')),
            'postal_code' => trim((string) ($row['postal_code'] ?? '')),
            'notes' => trim((string) ($row['notes'] ?? '')),
            'status' => strtolower(trim((string) ($row['status'] ?? 'need_to_schedule'))),
        ];
    }

    private function deliveryOr404(int $id): ?array
    {
        $businessId = current_business_id();
        if ($id <= 0) {
            flash('error', 'Delivery not found.');
            redirect('/deliveries');
        }

        $delivery = ClientDelivery::findForBusiness($businessId, $id);
        if ($delivery === null) {
            flash('error', 'Delivery not found.');
            redirect('/deliveries');
        }

        return $delivery;
    }
}
