<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use Core\Controller;

final class TasksController extends Controller
{
    public function index(): void
    {
        require_business_role(['general_user', 'admin']);

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'open')));
        $allowedStatuses = ['open', 'in_progress', 'closed'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = 'open';
        }

        $businessId = current_business_id();
        $perPage = pagination_per_page($_GET['per_page'] ?? null);
        $page = pagination_current_page($_GET['page'] ?? null);
        $totalRows = Task::indexCount($businessId, $search, $status);
        $totalPages = pagination_total_pages($totalRows, $perPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = pagination_offset($page, $perPage);

        $tasks = Task::indexList($businessId, $search, $status, $perPage, $offset);
        $pagination = pagination_meta($page, $perPage, $totalRows, count($tasks));
        $summary = Task::statusSummary($businessId);

        $this->render('tasks/index', [
            'pageTitle' => 'Tasks',
            'search' => $search,
            'status' => $status,
            'tasks' => $tasks,
            'summary' => $summary,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        require_business_role(['general_user', 'admin']);

        $businessId = current_business_id();
        $this->render('tasks/form', [
            'pageTitle' => 'Add Task',
            'mode' => 'create',
            'actionUrl' => url('/tasks'),
            'form' => $this->defaultForm(),
            'errors' => [],
            'userOptions' => Task::userOptions($businessId),
        ]);
    }

    public function store(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/tasks/create');
        }

        $businessId = current_business_id();
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, Task::userOptions($businessId));
        if ($errors !== []) {
            $this->render('tasks/form', [
                'pageTitle' => 'Add Task',
                'mode' => 'create',
                'actionUrl' => url('/tasks'),
                'form' => $form,
                'errors' => $errors,
                'userOptions' => Task::userOptions($businessId),
            ]);
            return;
        }

        $taskId = Task::create($businessId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Task created.');
        redirect('/tasks/' . (string) $taskId);
    }

    public function quickCreate(): void
    {
        require_business_role(['general_user', 'admin']);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'Task title is required.'], 422);
        }

        $ownerId = auth_user_id() ?? 0;
        if ($ownerId <= 0) {
            $this->json(['ok' => false, 'error' => 'Unable to determine task owner.'], 422);
        }

        $taskId = Task::create($businessId, [
            'title' => $title,
            'body' => '',
            'status' => 'open',
            'priority' => 3,
            'owner_user_id' => $ownerId,
            'assigned_user_id' => null,
            'due_at' => null,
            'link_type' => '',
            'link_id' => null,
        ], $ownerId);

        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            $this->json(['ok' => false, 'error' => 'Task was created but could not be loaded.'], 500);
        }

        $summary = Task::statusSummary($businessId);
        $this->json([
            'ok' => true,
            'task' => [
                'id' => (int) ($task['id'] ?? 0),
                'title' => (string) ($task['title'] ?? ''),
                'status' => (string) ($task['status'] ?? 'open'),
                'owner_user_id' => (int) ($task['owner_user_id'] ?? 0),
                'owner_name' => (string) ($task['owner_name'] ?? ''),
                'due_at' => (string) ($task['due_at'] ?? ''),
                'due_at_display' => format_datetime((string) ($task['due_at'] ?? null)),
                'url' => url('/tasks/' . (string) ((int) ($task['id'] ?? 0))),
            ],
            'summary' => [
                'open' => (int) ($summary['open'] ?? 0),
                'in_progress' => (int) ($summary['in_progress'] ?? 0),
                'closed' => (int) ($summary['closed'] ?? 0),
            ],
        ], 201);
    }

    public function quickComplete(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            $this->json(['ok' => false, 'error' => 'Invalid task id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if ($actorId <= 0) {
            $this->json(['ok' => false, 'error' => 'Unable to identify user.'], 422);
        }

        $isDone = ((string) ($_POST['done'] ?? '0')) === '1';
        Task::setCompletionStatus($businessId, $taskId, $isDone, $actorId);

        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            $this->json(['ok' => false, 'error' => 'Task not found in this workspace.'], 404);
        }

        $summary = Task::statusSummary($businessId);
        $this->json([
            'ok' => true,
            'task' => [
                'id' => (int) ($task['id'] ?? 0),
                'status' => (string) ($task['status'] ?? 'open'),
                'owner_user_id' => (int) ($task['owner_user_id'] ?? 0),
                'owner_name' => (string) ($task['owner_name'] ?? ''),
                'completed_at' => (string) ($task['completed_at'] ?? ''),
                'completed_at_display' => format_datetime((string) ($task['completed_at'] ?? null)),
                'completed_by_name' => (string) ($task['completed_by_name'] ?? ''),
            ],
            'summary' => [
                'open' => (int) ($summary['open'] ?? 0),
                'in_progress' => (int) ($summary['in_progress'] ?? 0),
                'closed' => (int) ($summary['closed'] ?? 0),
            ],
        ]);
    }

    public function quickStatus(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            $this->json(['ok' => false, 'error' => 'Invalid task id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $targetStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
        $allowed = ['open', 'in_progress', 'closed'];
        if (!in_array($targetStatus, $allowed, true)) {
            $this->json(['ok' => false, 'error' => 'Invalid status.'], 422);
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if ($actorId <= 0) {
            $this->json(['ok' => false, 'error' => 'Unable to identify user.'], 422);
        }

        Task::setStatus($businessId, $taskId, $targetStatus, $actorId);
        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            $this->json(['ok' => false, 'error' => 'Task not found in this workspace.'], 404);
        }

        $summary = Task::statusSummary($businessId);
        $this->json([
            'ok' => true,
            'task' => [
                'id' => (int) ($task['id'] ?? 0),
                'status' => (string) ($task['status'] ?? 'open'),
                'owner_user_id' => (int) ($task['owner_user_id'] ?? 0),
                'owner_name' => (string) ($task['owner_name'] ?? ''),
                'completed_at' => (string) ($task['completed_at'] ?? ''),
                'completed_at_display' => format_datetime((string) ($task['completed_at'] ?? null)),
                'completed_by_name' => (string) ($task['completed_by_name'] ?? ''),
            ],
            'summary' => [
                'open' => (int) ($summary['open'] ?? 0),
                'in_progress' => (int) ($summary['in_progress'] ?? 0),
                'closed' => (int) ($summary['closed'] ?? 0),
            ],
        ]);
    }

    public function quickTakeOwnership(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            $this->json(['ok' => false, 'error' => 'Invalid task id.'], 422);
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'error' => 'Session expired. Please reload and try again.'], 422);
        }

        $businessId = current_business_id();
        $actorId = (int) (auth_user_id() ?? 0);
        if ($actorId <= 0) {
            $this->json(['ok' => false, 'error' => 'Unable to identify user.'], 422);
        }

        $allowedUserIds = [];
        foreach (Task::userOptions($businessId) as $row) {
            $allowedUserIds[] = (int) ($row['id'] ?? 0);
        }
        if (!in_array($actorId, $allowedUserIds, true)) {
            $this->json(['ok' => false, 'error' => 'You are not an active user in this business.'], 403);
        }

        Task::setOwner($businessId, $taskId, $actorId, $actorId);
        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            $this->json(['ok' => false, 'error' => 'Task not found in this workspace.'], 404);
        }

        $this->json([
            'ok' => true,
            'task' => [
                'id' => (int) ($task['id'] ?? 0),
                'owner_user_id' => (int) ($task['owner_user_id'] ?? 0),
                'owner_name' => (string) ($task['owner_name'] ?? ''),
            ],
        ]);
    }

    public function edit(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $businessId = current_business_id();
        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('tasks/form', [
            'pageTitle' => 'Edit Task',
            'mode' => 'edit',
            'actionUrl' => url('/tasks/' . (string) $taskId . '/update'),
            'form' => $this->formFromModel($task),
            'errors' => [],
            'userOptions' => Task::userOptions($businessId),
            'taskId' => $taskId,
        ]);
    }

    public function update(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/tasks/' . (string) $taskId . '/edit');
        }

        $businessId = current_business_id();
        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $userOptions = Task::userOptions($businessId);
        $form = $this->formFromPost($_POST);
        $errors = $this->validateForm($form, $userOptions);
        if ($errors !== []) {
            $this->render('tasks/form', [
                'pageTitle' => 'Edit Task',
                'mode' => 'edit',
                'actionUrl' => url('/tasks/' . (string) $taskId . '/update'),
                'form' => $form,
                'errors' => $errors,
                'userOptions' => $userOptions,
                'taskId' => $taskId,
            ]);
            return;
        }

        Task::update($businessId, $taskId, $this->payloadForSave($form), auth_user_id() ?? 0);
        flash('success', 'Task updated.');
        redirect('/tasks/' . (string) $taskId);
    }

    public function show(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $task = Task::findForBusiness(current_business_id(), $taskId);
        if ($task === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $this->render('tasks/show', [
            'pageTitle' => 'Task',
            'task' => $task,
        ]);
    }

    public function takeOwnership(array $params): void
    {
        require_business_role(['general_user', 'admin']);

        $taskId = (int) ($params['id'] ?? 0);
        if ($taskId <= 0) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Session expired. Please try again.');
            redirect('/tasks/' . (string) $taskId);
        }

        $businessId = current_business_id();
        $task = Task::findForBusiness($businessId, $taskId);
        if ($task === null) {
            http_response_code(404);
            $this->render('errors/404', ['pageTitle' => 'Not Found']);
            return;
        }

        $actorId = (int) (auth_user_id() ?? 0);
        if ($actorId <= 0) {
            flash('error', 'Unable to identify current user.');
            redirect('/tasks/' . (string) $taskId);
        }

        $allowedUserIds = [];
        foreach (Task::userOptions($businessId) as $row) {
            $allowedUserIds[] = (int) ($row['id'] ?? 0);
        }
        if (!in_array($actorId, $allowedUserIds, true)) {
            flash('error', 'You are not an active user in this business.');
            redirect('/tasks/' . (string) $taskId);
        }

        Task::setOwner($businessId, $taskId, $actorId, $actorId);
        flash('success', 'Task ownership updated.');
        redirect('/tasks/' . (string) $taskId);
    }

    private function defaultForm(): array
    {
        return [
            'title' => '',
            'body' => '',
            'status' => 'open',
            'priority' => '3',
            'owner_user_id' => (string) (auth_user_id() ?? 0),
            'owner_user_name' => '',
            'due_at' => '',
        ];
    }

    private function formFromModel(array $task): array
    {
        return [
            'title' => trim((string) ($task['title'] ?? '')),
            'body' => trim((string) ($task['body'] ?? '')),
            'status' => strtolower(trim((string) ($task['status'] ?? 'open'))),
            'priority' => (string) max(1, min(5, (int) ($task['priority'] ?? 3))),
            'owner_user_id' => (string) ((int) ($task['owner_user_id'] ?? 0)),
            'owner_user_name' => trim((string) ($task['owner_name'] ?? '')),
            'due_at' => $this->toInputDatetime((string) ($task['due_at'] ?? '')),
        ];
    }

    private function formFromPost(array $input): array
    {
        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'body' => trim((string) ($input['body'] ?? '')),
            'status' => strtolower(trim((string) ($input['status'] ?? 'open'))),
            'priority' => trim((string) ($input['priority'] ?? '3')),
            'owner_user_id' => trim((string) ($input['owner_user_id'] ?? '')),
            'owner_user_name' => trim((string) ($input['owner_user_name'] ?? '')),
            'due_at' => trim((string) ($input['due_at'] ?? '')),
        ];
    }

    private function validateForm(array $form, array $userOptions): array
    {
        $errors = [];
        $allowedStatuses = ['open', 'in_progress', 'closed'];
        $allowedUserIds = [];
        foreach ($userOptions as $row) {
            $allowedUserIds[] = (int) ($row['id'] ?? 0);
        }

        if ($form['title'] === '') {
            $errors['title'] = 'Task title is required.';
        }

        if (!in_array($form['status'], $allowedStatuses, true)) {
            $errors['status'] = 'Choose a valid status.';
        }

        $priority = (int) $form['priority'];
        if ($priority < 1 || $priority > 5) {
            $errors['priority'] = 'Priority must be between 1 and 5.';
        }

        $ownerId = (int) $form['owner_user_id'];
        if ($ownerId <= 0 || !in_array($ownerId, $allowedUserIds, true)) {
            $errors['owner_user_id'] = 'Choose a valid owner.';
        }

        if ($form['due_at'] !== '' && $this->asTimestamp($form['due_at']) === null) {
            $errors['due_at'] = 'Enter a valid due date/time.';
        }

        return $errors;
    }

    private function payloadForSave(array $form): array
    {
        return [
            'title' => $form['title'],
            'body' => $form['body'],
            'status' => $form['status'],
            'priority' => (int) $form['priority'],
            'owner_user_id' => (int) $form['owner_user_id'],
            'assigned_user_id' => null,
            'due_at' => $this->toDatabaseDatetime($form['due_at']),
            'link_type' => '',
            'link_id' => null,
        ];
    }

    private function asTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private function toDatabaseDatetime(string $value): ?string
    {
        $timestamp = $this->asTimestamp($value);
        return $timestamp === null ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function toInputDatetime(string $value): string
    {
        $timestamp = $this->asTimestamp($value);
        return $timestamp === null ? '' : date('Y-m-d\TH:i', $timestamp);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
