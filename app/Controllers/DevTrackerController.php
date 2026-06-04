<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DevTrackerItem;
use Core\Controller;

final class DevTrackerController extends Controller
{
    public function index(): void
    {
        $this->requireDevAccess();

        $search = trim((string) ($_GET['q'] ?? ''));
        $statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'active')));
        $type = strtolower(trim((string) ($_GET['type'] ?? '')));
        $priority = strtolower(trim((string) ($_GET['priority'] ?? '')));
        $status = $statusFilter === 'active' ? '__active__' : $statusFilter;

        $perPage = pagination_per_page($_GET['per_page'] ?? null, 50);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = DevTrackerItem::indexCount($search, $status, $type, $priority);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $items = DevTrackerItem::indexList($search, $status, $type, $priority, $perPage, $offset);

        $this->render('dev_tracker/index', [
            'pageTitle' => 'Dev Tracker',
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'priority' => $priority,
            'statusFilter' => $statusFilter,
            'items' => $items,
            'summary' => DevTrackerItem::statusSummary(),
            'pagination' => pagination_meta($page, $perPage, $totalRows, count($items)),
            'typeOptions' => DevTrackerItem::typeOptions(),
            'statusOptions' => DevTrackerItem::statusOptions(),
            'priorityOptions' => DevTrackerItem::priorityOptions(),
        ]);
    }

    public function create(): void
    {
        $this->requireDevAccess();

        $form = $this->defaultForm();
        $presetType = strtolower(trim((string) ($_GET['type'] ?? '')));
        if (DevTrackerItem::isValidType($presetType)) {
            $form['item_type'] = $presetType;
        }

        $this->render('dev_tracker/form', [
            'pageTitle' => 'Add Dev Item',
            'mode' => 'create',
            'actionUrl' => url('/dev'),
            'form' => $form,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireDevAccess();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/dev/create');
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);
        if ($errors !== []) {
            $this->render('dev_tracker/form', [
                'pageTitle' => 'Add Dev Item',
                'mode' => 'create',
                'actionUrl' => url('/dev'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        $itemId = DevTrackerItem::create($this->payloadForSave($form), auth_user_id() ?? 0);
        if ($itemId <= 0) {
            flash('error', 'Dev tracker table is missing. Run the latest migration.');
            redirect('/dev/create');
        }

        flash('success', 'Dev item added.');
        redirect('/dev/' . (string) $itemId);
    }

    public function show(array $params): void
    {
        $this->requireDevAccess();

        $item = $this->itemOr404((int) ($params['id'] ?? 0));
        if ($item === null) {
            return;
        }

        $this->render('dev_tracker/show', [
            'pageTitle' => 'Dev Item',
            'item' => $item,
            'statusOptions' => DevTrackerItem::statusOptions(),
        ]);
    }

    public function edit(array $params): void
    {
        $this->requireDevAccess();

        $item = $this->itemOr404((int) ($params['id'] ?? 0));
        if ($item === null) {
            return;
        }

        $this->render('dev_tracker/form', [
            'pageTitle' => 'Edit Dev Item',
            'mode' => 'edit',
            'itemId' => (int) ($item['id'] ?? 0),
            'actionUrl' => url('/dev/' . (string) ((int) ($item['id'] ?? 0)) . '/update'),
            'form' => $this->formFromModel($item),
            'errors' => [],
        ]);
    }

    public function update(array $params): void
    {
        $this->requireDevAccess();

        $itemId = (int) ($params['id'] ?? 0);
        if ($itemId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/dev/' . (string) $itemId . '/edit');
        }

        $item = DevTrackerItem::find($itemId);
        if ($item === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form);
        if ($errors !== []) {
            $this->render('dev_tracker/form', [
                'pageTitle' => 'Edit Dev Item',
                'mode' => 'edit',
                'itemId' => $itemId,
                'actionUrl' => url('/dev/' . (string) $itemId . '/update'),
                'form' => $form,
                'errors' => $errors,
            ]);
            return;
        }

        $updated = DevTrackerItem::update($itemId, $this->payloadForSave($form), auth_user_id() ?? 0);
        if ($updated) {
            flash('success', 'Dev item updated.');
        } else {
            flash('error', 'Unable to update dev item.');
        }

        redirect('/dev/' . (string) $itemId);
    }

    public function quickStatus(array $params): void
    {
        $this->requireDevAccess();

        $itemId = (int) ($params['id'] ?? 0);
        if ($itemId <= 0) {
            redirect('/dev');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/dev');
        }

        $status = strtolower(trim((string) ($_POST['status'] ?? '')));
        if (!DevTrackerItem::isValidStatus($status)) {
            flash('error', 'Invalid status.');
            redirect('/dev');
        }

        if (DevTrackerItem::updateStatus($itemId, $status, auth_user_id() ?? 0)) {
            flash('success', 'Status updated.');
        } else {
            flash('error', 'Unable to update status.');
        }

        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '' && str_starts_with($returnTo, '/dev')) {
            redirect($returnTo);
        }

        redirect('/dev');
    }

    public function delete(array $params): void
    {
        $this->requireDevAccess();

        $itemId = (int) ($params['id'] ?? 0);
        if ($itemId <= 0) {
            redirect('/dev');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/dev/' . (string) $itemId);
        }

        if (DevTrackerItem::softDelete($itemId, auth_user_id() ?? 0)) {
            flash('success', 'Dev item removed.');
        } else {
            flash('error', 'Unable to remove dev item.');
        }

        redirect('/dev');
    }

    private function requireDevAccess(): void
    {
        require_role(['site_admin']);
    }

    /**
     * @return array<string, string>
     */
    private function defaultForm(): array
    {
        return [
            'item_type' => 'bug',
            'title' => '',
            'notes' => '',
            'status' => 'backlog',
            'priority' => 'normal',
            'area' => '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function formFromPost(array $input): array
    {
        return [
            'item_type' => trim((string) ($input['item_type'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
            'priority' => trim((string) ($input['priority'] ?? '')),
            'area' => trim((string) ($input['area'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function formFromModel(array $item): array
    {
        return [
            'item_type' => trim((string) ($item['item_type'] ?? 'bug')),
            'title' => trim((string) ($item['title'] ?? '')),
            'notes' => trim((string) ($item['notes'] ?? '')),
            'status' => trim((string) ($item['status'] ?? 'backlog')),
            'priority' => trim((string) ($item['priority'] ?? 'normal')),
            'area' => trim((string) ($item['area'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    private function payloadForSave(array $form): array
    {
        return [
            'item_type' => $form['item_type'],
            'title' => $form['title'],
            'notes' => $form['notes'],
            'status' => $form['status'],
            'priority' => $form['priority'],
            'area' => $form['area'],
        ];
    }

    /**
     * @param array<string, string> $form
     * @return array<string, string>
     */
    private function validateForm(array $form): array
    {
        $errors = [];

        if ($form['title'] === '') {
            $errors['title'] = 'Title is required.';
        }

        if (!DevTrackerItem::isValidType($form['item_type'])) {
            $errors['item_type'] = 'Choose a valid type.';
        }

        if (!DevTrackerItem::isValidStatus($form['status'])) {
            $errors['status'] = 'Choose a valid status.';
        }

        if (!DevTrackerItem::isValidPriority($form['priority'])) {
            $errors['priority'] = 'Choose a valid priority.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemOr404(int $itemId): ?array
    {
        if ($itemId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        $item = DevTrackerItem::find($itemId);
        if ($item === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return null;
        }

        return $item;
    }
}
