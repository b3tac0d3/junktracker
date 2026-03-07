<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\Purchase;
use App\Models\Task;
use Core\Controller;

final class PurchasesController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        if ($status !== '' && !in_array($status, Purchase::statusOptions(), true)) {
            $status = '';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Purchase::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $purchases = Purchase::indexList($businessId, $search, $status, $perPage, $offset);
        $summary = Purchase::statusSummary($businessId);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($purchases));

        $this->render('purchases/index', [
            'pageTitle' => 'Purchasing',
            'search' => $search,
            'status' => $status,
            'statusOptions' => Purchase::statusOptions(),
            'purchases' => $purchases,
            'summary' => $summary,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $this->render('purchases/form', [
            'pageTitle' => 'Add Purchase Order',
            'mode' => 'create',
            'actionUrl' => url('/purchases'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'statusOptions' => Purchase::statusOptions(),
            'searchUrl' => url('/purchases/client-search'),
        ]);
    }

    public function clientSearch(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = Client::searchOptions($businessId, $query, 8);

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $results[] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'company_name' => (string) ($item['company_name'] ?? ''),
                'phone' => (string) ($item['phone'] ?? ''),
                'city' => (string) ($item['city'] ?? ''),
            ];
        }

        $this->json([
            'ok' => true,
            'results' => $results,
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchases/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('purchases/form', [
                'pageTitle' => 'Add Purchase Order',
                'mode' => 'create',
                'actionUrl' => url('/purchases'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => Purchase::statusOptions(),
                'searchUrl' => url('/purchases/client-search'),
            ]);
            return;
        }

        $actorUserId = auth_user_id() ?? 0;
        $purchaseId = Purchase::create($businessId, $this->payloadForSave($form), $actorUserId);
        $this->maybeCreateFollowUpTask($businessId, $purchaseId, $form, $actorUserId);

        flash('success', 'Purchase order created.');
        redirect('/purchases/' . (string) $purchaseId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $purchaseId = (int) ($params['id'] ?? 0);
        if ($purchaseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $purchase = Purchase::findForBusiness(current_business_id(), $purchaseId);
        if ($purchase === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $tasks = Purchase::tasksByPurchase(current_business_id(), $purchaseId);

        $this->render('purchases/show', [
            'pageTitle' => 'Purchase Details',
            'purchase' => $purchase,
            'tasks' => $tasks,
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $purchaseId = (int) ($params['id'] ?? 0);
        if ($purchaseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $purchase = Purchase::findForBusiness(current_business_id(), $purchaseId);
        if ($purchase === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('purchases/form', [
            'pageTitle' => 'Edit Purchase Order',
            'mode' => 'edit',
            'actionUrl' => url('/purchases/' . (string) $purchaseId . '/update'),
            'form' => $this->formFromModel($purchase),
            'errors' => [],
            'statusOptions' => Purchase::statusOptions(),
            'searchUrl' => url('/purchases/client-search'),
            'purchaseId' => $purchaseId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $purchaseId = (int) ($params['id'] ?? 0);
        if ($purchaseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchases/' . (string) $purchaseId . '/edit');
        }

        $businessId = current_business_id();
        $purchase = Purchase::findForBusiness($businessId, $purchaseId);
        if ($purchase === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $businessId);
        if ($errors !== []) {
            $this->render('purchases/form', [
                'pageTitle' => 'Edit Purchase Order',
                'mode' => 'edit',
                'actionUrl' => url('/purchases/' . (string) $purchaseId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'statusOptions' => Purchase::statusOptions(),
                'searchUrl' => url('/purchases/client-search'),
                'purchaseId' => $purchaseId,
            ]);
            return;
        }

        Purchase::update($businessId, $purchaseId, $this->payloadForSave($form), auth_user_id() ?? 0);
        $this->maybeCreateFollowUpTask($businessId, $purchaseId, $form, auth_user_id() ?? 0);

        flash('success', 'Purchase order updated.');
        redirect('/purchases/' . (string) $purchaseId);
    }

    public function delete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $purchaseId = (int) ($params['id'] ?? 0);
        if ($purchaseId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/purchases/' . (string) $purchaseId);
        }

        $purchase = Purchase::findForBusiness(current_business_id(), $purchaseId);
        if ($purchase === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $deleted = Purchase::softDelete(current_business_id(), $purchaseId, auth_user_id() ?? 0);
        if (!$deleted) {
            flash('error', 'Unable to delete purchase order.');
            redirect('/purchases/' . (string) $purchaseId);
        }

        flash('success', 'Purchase order deleted.');
        redirect('/purchases');
    }

    private function defaultForm(): array
    {
        return [
            'title' => '',
            'status' => 'prospect',
            'client_id' => '',
            'client_name' => '',
            'contact_date' => date('Y-m-d'),
            'purchase_date' => '',
            'notes' => '',
            'create_follow_up_task' => '',
            'follow_up_title' => '',
            'follow_up_due_date' => '',
        ];
    }

    private function formFromPost(array $input): array
    {
        $clientIdRaw = trim((string) ($input['client_id'] ?? ''));
        $clientId = ((int) $clientIdRaw) > 0 ? (string) ((int) $clientIdRaw) : '';

        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'status' => strtolower(trim((string) ($input['status'] ?? 'prospect'))),
            'client_id' => $clientId,
            'client_name' => trim((string) ($input['client_name'] ?? '')),
            'contact_date' => trim((string) ($input['contact_date'] ?? '')),
            'purchase_date' => trim((string) ($input['purchase_date'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'create_follow_up_task' => isset($input['create_follow_up_task']) ? '1' : '',
            'follow_up_title' => trim((string) ($input['follow_up_title'] ?? '')),
            'follow_up_due_date' => trim((string) ($input['follow_up_due_date'] ?? '')),
        ];
    }

    private function formFromModel(array $purchase): array
    {
        $contactDate = trim((string) ($purchase['contact_date'] ?? ''));
        if ($contactDate !== '') {
            $stamp = strtotime($contactDate);
            $contactDate = $stamp === false ? '' : date('Y-m-d', $stamp);
        }

        $purchaseDate = trim((string) ($purchase['purchase_date'] ?? ''));
        if ($purchaseDate !== '') {
            $stamp = strtotime($purchaseDate);
            $purchaseDate = $stamp === false ? '' : date('Y-m-d', $stamp);
        }

        return [
            'title' => trim((string) ($purchase['title'] ?? '')),
            'status' => strtolower(trim((string) ($purchase['status'] ?? 'prospect'))),
            'client_id' => ((int) ($purchase['client_id'] ?? 0)) > 0 ? (string) ((int) ($purchase['client_id'] ?? 0)) : '',
            'client_name' => trim((string) ($purchase['client_name'] ?? '')),
            'contact_date' => $contactDate,
            'purchase_date' => $purchaseDate,
            'notes' => trim((string) ($purchase['notes'] ?? '')),
            'create_follow_up_task' => '',
            'follow_up_title' => '',
            'follow_up_due_date' => '',
        ];
    }

    private function validateForm(array $form, int $businessId): array
    {
        $errors = [];

        if ($form['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        if (!in_array($form['status'], Purchase::statusOptions(), true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        $clientId = (int) $form['client_id'];
        if ($clientId <= 0) {
            $errors['client_id'] = 'Select a client from suggestions.';
        } elseif (Client::findForBusiness($businessId, $clientId) === null) {
            $errors['client_id'] = 'Selected client was not found.';
        }

        if ($form['contact_date'] !== '' && $this->asDate($form['contact_date']) === null) {
            $errors['contact_date'] = 'Contact date is invalid.';
        }

        if ($form['purchase_date'] !== '' && $this->asDate($form['purchase_date']) === null) {
            $errors['purchase_date'] = 'Purchase date is invalid.';
        }

        if ($form['create_follow_up_task'] === '1' && $form['follow_up_due_date'] !== '' && $this->asDate($form['follow_up_due_date']) === null) {
            $errors['follow_up_due_date'] = 'Follow-up due date is invalid.';
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'title' => $form['title'],
            'status' => $form['status'],
            'client_id' => (int) $form['client_id'],
            'contact_date' => $this->toDatabaseDate($form['contact_date']),
            'purchase_date' => $this->toDatabaseDate($form['purchase_date']),
            'notes' => $form['notes'],
        ];
    }

    private function maybeCreateFollowUpTask(int $businessId, int $purchaseId, array $form, int $actorUserId): void
    {
        if ($purchaseId <= 0 || $actorUserId <= 0 || $form['create_follow_up_task'] !== '1') {
            return;
        }

        $taskTitle = trim($form['follow_up_title']);
        if ($taskTitle === '') {
            $taskTitle = 'Purchase Follow-Up: ' . $form['title'];
        }

        $dueAt = null;
        if (trim((string) $form['follow_up_due_date']) !== '') {
            $dueAt = $this->toDatabaseDateTime($form['follow_up_due_date']);
        } elseif (trim((string) $form['contact_date']) !== '') {
            $dueAt = $this->toDatabaseDateTime($form['contact_date']);
        }

        $body = '';
        if (trim((string) $form['notes']) !== '') {
            $body = trim((string) $form['notes']);
        }

        Task::create($businessId, [
            'title' => $taskTitle,
            'body' => $body,
            'status' => 'open',
            'owner_user_id' => $actorUserId,
            'assigned_user_id' => null,
            'due_at' => $dueAt,
            'priority' => 3,
            'link_type' => 'purchase',
            'link_id' => $purchaseId,
        ], $actorUserId);
    }

    private function asDate(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function toDatabaseDate(string $value): ?string
    {
        $timestamp = $this->asDate($value);
        return $timestamp === null ? null : date('Y-m-d', $timestamp);
    }

    private function toDatabaseDateTime(string $value): ?string
    {
        $timestamp = $this->asDate($value);
        if ($timestamp === null) {
            return null;
        }

        return date('Y-m-d 09:00:00', $timestamp);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
