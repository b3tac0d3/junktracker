<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Business;
use App\Models\DevTrackerItem;
use App\Models\DevTrackerLog;
use Core\Controller;

final class DevTrackerController extends Controller
{
    public function index(): void
    {
        $this->requireDevAccess();

        $search = trim((string) ($_GET['q'] ?? ''));
        $statusFilters = $this->indexStatusFiltersFromRequest();
        $type = strtolower(trim((string) ($_GET['type'] ?? '')));
        $priority = strtolower(trim((string) ($_GET['priority'] ?? '')));

        $perPage = pagination_per_page($_GET['per_page'] ?? null, 50);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = DevTrackerItem::indexCount($search, $statusFilters, $type, $priority);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $items = DevTrackerItem::indexList($search, $statusFilters, $type, $priority, $perPage, $offset);

        $this->render('dev_tracker/index', [
            'pageTitle' => 'Dev Tracker',
            'search' => $search,
            'statusFilters' => $statusFilters,
            'type' => $type,
            'priority' => $priority,
            'items' => $items,
            'summary' => DevTrackerItem::statusSummary(),
            'pagination' => pagination_meta($page, $perPage, $totalRows, count($items)),
            'typeOptions' => DevTrackerItem::typeOptions(),
            'statusOptions' => DevTrackerItem::devStatusOptions(),
            'priorityOptions' => DevTrackerItem::priorityOptions(),
            'pendingReviewCount' => DevTrackerItem::pendingReviewCount(),
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

        $actorId = auth_user_id() ?? 0;
        $screenshot = dev_tracker_store_screenshot($itemId, $_FILES['screenshot'] ?? null);
        if ($screenshot['error'] !== null) {
            flash('error', $screenshot['error']);
            redirect('/dev/' . (string) $itemId);
        }

        DevTrackerLog::append($itemId, 'created', [
            'body' => $form['notes'] !== '' ? $form['notes'] : $form['title'],
            'screenshot_path' => $screenshot['path'],
        ], $actorId);

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
            'logEntries' => DevTrackerLog::forItem((int) ($item['id'] ?? 0)),
            'business' => (int) ($item['business_id'] ?? 0) > 0 ? Business::findById((int) ($item['business_id'] ?? 0)) : null,
            'statusOptions' => DevTrackerItem::devStatusOptions(),
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
            $this->logFieldChanges($item, $this->payloadForSave($form), auth_user_id() ?? 0);
            flash('success', 'Dev item updated.');
        } else {
            flash('error', 'Unable to update dev item.');
        }

        redirect('/dev/' . (string) $itemId);
    }

    public function addLog(array $params): void
    {
        $this->requireDevAccess();

        $itemId = (int) ($params['id'] ?? 0);
        $item = DevTrackerItem::find($itemId);
        if ($item === null) {
            redirect('/dev');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/dev/' . (string) $itemId);
        }

        $body = trim((string) ($_POST['body'] ?? ''));
        $screenshot = dev_tracker_store_screenshot($itemId, $_FILES['screenshot'] ?? null);
        if ($screenshot['error'] !== null) {
            flash('error', $screenshot['error']);
            redirect('/dev/' . (string) $itemId);
        }

        if ($body === '' && $screenshot['path'] === null) {
            flash('error', 'Add an update or attach a screenshot.');
            redirect('/dev/' . (string) $itemId);
        }

        DevTrackerLog::append($itemId, 'comment', [
            'body' => $body,
            'screenshot_path' => $screenshot['path'],
        ], auth_user_id() ?? 0);

        flash('success', 'Update added to the bug log.');
        redirect('/dev/' . (string) $itemId);
    }

    public function acceptSubmission(array $params): void
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

        $item = DevTrackerItem::find($itemId);
        if ($item === null || !DevTrackerItem::isPendingSubmission($item)) {
            flash('error', 'This submission is no longer pending review.');
            redirect('/dev/' . (string) $itemId);
        }

        $status = strtolower(trim((string) ($_POST['status'] ?? '')));
        if (!in_array($status, DevTrackerItem::devStatusOptions(), true)) {
            $status = DevTrackerItem::defaultAcceptStatusForSubmission($item);
        }
        $note = trim((string) ($_POST['body'] ?? ''));
        $actorId = auth_user_id() ?? 0;
        $isUpdate = strtolower(trim((string) ($item['item_type'] ?? ''))) === 'update';

        if (!DevTrackerItem::acceptSubmission($itemId, $actorId, $status)) {
            flash('error', 'Unable to accept submission.');
            redirect('/dev/' . (string) $itemId);
        }

        $screenshot = dev_tracker_store_screenshot($itemId, $_FILES['screenshot'] ?? null);
        if ($screenshot['error'] !== null) {
            flash('error', $screenshot['error']);
            redirect('/dev/' . (string) $itemId);
        }

        DevTrackerLog::append($itemId, 'accepted', [
            'body' => $note !== '' ? $note : ($isUpdate ? 'Accepted for a future release.' : 'Accepted as a tracked bug.'),
            'status_from' => 'pending_review',
            'status_to' => $status,
            'screenshot_path' => $screenshot['path'],
        ], $actorId);

        flash('success', $isUpdate ? 'Update request accepted.' : 'Bug report accepted.');
        redirect('/dev/' . (string) $itemId);
    }

    public function rejectSubmission(array $params): void
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

        $item = DevTrackerItem::find($itemId);
        if ($item === null || !DevTrackerItem::isPendingSubmission($item)) {
            flash('error', 'This submission is no longer pending review.');
            redirect('/dev/' . (string) $itemId);
        }

        $note = trim((string) ($_POST['body'] ?? ''));
        if ($note === '') {
            flash('error', 'Add a reason when rejecting a report.');
            redirect('/dev/' . (string) $itemId);
        }

        $actorId = auth_user_id() ?? 0;
        $isUpdate = strtolower(trim((string) ($item['item_type'] ?? ''))) === 'update';
        if (!DevTrackerItem::rejectSubmission($itemId, $actorId)) {
            flash('error', 'Unable to reject submission.');
            redirect('/dev/' . (string) $itemId);
        }

        $screenshot = dev_tracker_store_screenshot($itemId, $_FILES['screenshot'] ?? null);
        if ($screenshot['error'] !== null) {
            flash('error', $screenshot['error']);
            redirect('/dev/' . (string) $itemId);
        }

        DevTrackerLog::append($itemId, 'rejected', [
            'body' => $note,
            'status_from' => 'pending_review',
            'status_to' => 'wont_fix',
            'screenshot_path' => $screenshot['path'],
        ], $actorId);

        flash('success', $isUpdate ? 'Update request declined.' : 'Bug report rejected.');
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
        if (!DevTrackerItem::isValidStatus($status) || $status === 'pending_review') {
            flash('error', 'Invalid status.');
            redirect('/dev');
        }

        $item = DevTrackerItem::find($itemId);
        if ($item === null) {
            flash('error', 'Item not found.');
            redirect('/dev');
        }

        $previousStatus = trim((string) ($item['status'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $actorId = auth_user_id() ?? 0;

        if (!DevTrackerItem::updateStatus($itemId, $status, $actorId)) {
            flash('error', 'Unable to update status.');
            redirect('/dev');
        }

        $screenshot = dev_tracker_store_screenshot($itemId, $_FILES['screenshot'] ?? null);
        if ($screenshot['error'] !== null) {
            flash('error', $screenshot['error']);
            redirect('/dev/' . (string) $itemId);
        }

        if ($previousStatus !== $status || $body !== '' || $screenshot['path'] !== null) {
            DevTrackerLog::append($itemId, 'status_change', [
                'body' => $body,
                'status_from' => $previousStatus,
                'status_to' => $status,
                'screenshot_path' => $screenshot['path'],
            ], $actorId);
        }

        flash('success', 'Status updated.');

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

    /**
     * @return array<int, string>
     */
    private function indexStatusFiltersFromRequest(): array
    {
        if (!array_key_exists('status', $_GET) && !isset($_GET['status_applied'])) {
            return DevTrackerItem::defaultIndexStatusFilters();
        }

        if (isset($_GET['status_applied']) && !isset($_GET['status'])) {
            return [];
        }

        $raw = $_GET['status'] ?? [];
        if (is_string($raw)) {
            return DevTrackerItem::normalizeIndexStatusFilters($raw);
        }

        if (is_array($raw)) {
            return DevTrackerItem::normalizeIndexStatusFilters($raw);
        }

        return [];
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

        if (!in_array($form['status'], DevTrackerItem::devStatusOptions(), true)) {
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

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function logFieldChanges(array $before, array $after, int $actorUserId): void
    {
        $itemId = (int) ($before['id'] ?? 0);
        if ($itemId <= 0) {
            return;
        }

        $changes = [];
        foreach ([
            'title' => 'Title',
            'notes' => 'Notes',
            'area' => 'Area',
            'priority' => 'Priority',
            'item_type' => 'Type',
        ] as $field => $label) {
            $oldValue = trim((string) ($before[$field] ?? ''));
            $newValue = trim((string) ($after[$field] ?? ''));
            if ($oldValue === $newValue) {
                continue;
            }
            $changes[] = $label . ': ' . ($oldValue !== '' ? $oldValue : '—') . ' → ' . ($newValue !== '' ? $newValue : '—');
        }

        $oldStatus = trim((string) ($before['status'] ?? ''));
        $newStatus = trim((string) ($after['status'] ?? ''));
        if ($oldStatus !== $newStatus) {
            DevTrackerLog::append($itemId, 'status_change', [
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
            ], $actorUserId);
        }

        if ($changes !== []) {
            DevTrackerLog::append($itemId, 'updated', [
                'body' => implode("\n", $changes),
            ], $actorUserId);
        }
    }
}
