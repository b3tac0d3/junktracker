<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use Core\Controller;

final class TasksController extends Controller
{
    public function index(): void
    {
        $this->authorize('view');

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? 'open'),
            'importance' => $this->toIntOrNull($_GET['importance'] ?? null),
            'link_type' => (string) ($_GET['link_type'] ?? 'all'),
            'owner_scope' => (string) ($_GET['owner_scope'] ?? 'all'),
            'assigned_user_id' => $this->toIntOrNull($_GET['assigned_user_id'] ?? null),
            'record_status' => (string) ($_GET['record_status'] ?? 'active'),
            'due_start' => trim((string) ($_GET['due_start'] ?? '')),
            'due_end' => trim((string) ($_GET['due_end'] ?? '')),
            'current_user_id' => auth_user_id() ?? 0,
        ];

        if (!in_array($filters['status'], array_merge(['all', 'overdue'], Task::STATUSES), true)) {
            $filters['status'] = 'open';
        }
        if (($filters['importance'] ?? 0) < 1 || ($filters['importance'] ?? 0) > 5) {
            $filters['importance'] = null;
        }
        if (!in_array($filters['link_type'], array_merge(['all'], Task::LINK_TYPES), true)) {
            $filters['link_type'] = 'all';
        }
        if (!in_array($filters['owner_scope'], ['all', 'mine', 'team'], true)) {
            $filters['owner_scope'] = 'all';
        }
        if (!in_array($filters['record_status'], ['active', 'deleted', 'all'], true)) {
            $filters['record_status'] = 'active';
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/tasks-table.js') . '"></script>',
        ]);

        $this->render('tasks/index', [
            'pageTitle' => 'Tasks',
            'filters' => $filters,
            'tasks' => Task::filter($filters),
            'summary' => Task::summary($filters),
            'users' => Task::users(),
            'statusOptions' => Task::STATUSES,
            'linkTypes' => Task::LINK_TYPES,
            'linkTypeLabels' => $this->linkTypeLabels(),
            'ownerScopes' => ['all', 'mine', 'team'],
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $task = $this->prefillTask();

        $this->render('tasks/create', [
            'pageTitle' => 'Add Task',
            'task' => $task,
            'users' => Task::users(),
            'statusOptions' => Task::STATUSES,
            'linkTypes' => Task::LINK_TYPES,
            'linkTypeLabels' => $this->linkTypeLabels(),
            'importanceOptions' => [1, 2, 3, 4, 5],
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function store(): void
    {
        $this->authorize('create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/tasks/new');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/tasks/new');
        }

        $taskId = Task::create($data, auth_user_id());
        flash('success', 'Task added.');
        redirect('/tasks/' . $taskId);
    }

    public function show(array $params): void
    {
        $this->authorize('view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/tasks');
        }

        $task = Task::findById($id);
        if (!$task) {
            $this->renderNotFound();
            return;
        }

        $this->render('tasks/show', [
            'pageTitle' => 'Task Details',
            'task' => $task,
            'linkTypeLabels' => $this->linkTypeLabels(),
        ]);
    }

    public function edit(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/tasks');
        }

        $task = Task::findById($id);
        if (!$task) {
            $this->renderNotFound();
            return;
        }

        $this->render('tasks/edit', [
            'pageTitle' => 'Edit Task',
            'task' => $task,
            'users' => Task::users(),
            'statusOptions' => Task::STATUSES,
            'linkTypes' => Task::LINK_TYPES,
            'linkTypeLabels' => $this->linkTypeLabels(),
            'importanceOptions' => [1, 2, 3, 4, 5],
            'pageScripts' => $this->formScripts(),
        ]);

        clear_old();
    }

    public function update(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/tasks');
        }

        $task = Task::findById($id);
        if (!$task) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/tasks/' . $id . '/edit');
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/tasks/' . $id . '/edit');
        }

        Task::update($id, $data, auth_user_id());
        flash('success', 'Task updated.');
        redirect('/tasks/' . $id);
    }

    public function delete(array $params): void
    {
        $this->authorize('delete');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/tasks');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/tasks/' . $id);
        }

        $task = Task::findById($id);
        if (!$task) {
            $this->renderNotFound();
            return;
        }

        if (empty($task['deleted_at'])) {
            Task::softDelete($id, auth_user_id());
            flash('success', 'Task deleted.');
        } else {
            flash('success', 'Task is already deleted.');
        }

        redirect('/tasks');
    }

    public function toggleComplete(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/tasks');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($this->resolveReturnPath('/tasks/' . $id));
        }

        $task = Task::findById($id);
        if (!$task) {
            $this->renderNotFound();
            return;
        }

        if (!empty($task['deleted_at'])) {
            flash('error', 'Deleted tasks cannot be updated.');
            redirect($this->resolveReturnPath('/tasks/' . $id));
        }

        $isCompleted = $this->toIntOrNull($_POST['is_completed'] ?? null) === 1;
        Task::setCompletion($id, $isCompleted, auth_user_id());

        flash('success', $isCompleted ? 'Task marked complete.' : 'Task marked active.');
        redirect($this->resolveReturnPath('/tasks/' . $id));
    }

    public function linkLookup(): void
    {
        $this->authorize('view');

        $type = (string) ($_GET['type'] ?? 'general');
        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Task::lookupLinks($type, $term));
    }

    private function collectFormData(): array
    {
        $status = trim((string) ($_POST['status'] ?? 'open'));
        if (!in_array($status, Task::STATUSES, true)) {
            $status = 'open';
        }

        $linkType = trim((string) ($_POST['link_type'] ?? 'general'));
        if (!in_array($linkType, Task::LINK_TYPES, true)) {
            $linkType = 'general';
        }

        $importance = $this->toIntOrNull($_POST['importance'] ?? null);
        if ($importance === null || $importance < 1 || $importance > 5) {
            $importance = 3;
        }

        $completedAt = $this->toDateTimeOrNull($_POST['completed_at'] ?? null);
        if ($status === 'closed' && $completedAt === null) {
            $completedAt = date('Y-m-d H:i:s');
        }
        if ($status !== 'closed') {
            $completedAt = null;
        }

        $linkId = $this->toIntOrNull($_POST['link_id'] ?? null);
        if ($linkType === 'general') {
            $linkId = null;
        }

        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'body' => trim((string) ($_POST['body'] ?? '')),
            'link_type' => $linkType,
            'link_id' => $linkId,
            'assigned_user_id' => $this->toIntOrNull($_POST['assigned_user_id'] ?? null),
            'importance' => $importance,
            'status' => $status,
            'outcome' => trim((string) ($_POST['outcome'] ?? '')),
            'due_at' => $this->toDateTimeOrNull($_POST['due_at'] ?? null),
            'completed_at' => $completedAt,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors[] = 'Task title is required.';
        }

        if (!in_array($data['status'], Task::STATUSES, true)) {
            $errors[] = 'Task status is invalid.';
        }

        if ($data['importance'] < 1 || $data['importance'] > 5) {
            $errors[] = 'Importance must be between 1 and 5.';
        }

        if ($data['assigned_user_id'] !== null) {
            $users = Task::users();
            $validUserIds = array_map(static fn (array $u): int => (int) ($u['id'] ?? 0), $users);
            if (!in_array((int) $data['assigned_user_id'], $validUserIds, true)) {
                $errors[] = 'Assigned user is invalid.';
            }
        }

        $linkSearch = trim((string) ($_POST['link_search'] ?? ''));
        if ($data['link_type'] !== 'general' && ($data['link_id'] === null || $data['link_id'] <= 0)) {
            $errors[] = 'Select a valid linked record.';
        }
        if ($data['link_type'] === 'general' && $linkSearch !== '' && $data['link_id'] === null) {
            $errors[] = 'Clear linked record search or choose a link type.';
        }
        if ($data['link_type'] !== 'general' && $data['link_id'] !== null && !Task::linkExists($data['link_type'], $data['link_id'])) {
            $errors[] = 'Selected linked record is invalid.';
        }

        return $errors;
    }

    private function formScripts(): string
    {
        return '<script src="' . asset('js/task-link-lookup.js') . '"></script>';
    }

    private function authorize(string $action): void
    {
        require_permission('tasks', $action);
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function toDateTimeOrNull(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $time = strtotime($raw);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }

    private function prefillTask(): ?array
    {
        $linkType = trim((string) ($_GET['link_type'] ?? ''));
        $linkType = in_array($linkType, Task::LINK_TYPES, true) ? $linkType : 'general';

        $linkId = $this->toIntOrNull($_GET['link_id'] ?? null);
        if ($linkType === 'general' || $linkId === null || $linkId <= 0) {
            return null;
        }

        if (!Task::linkExists($linkType, $linkId)) {
            return null;
        }

        $link = Task::resolveLink($linkType, $linkId);
        if ($link === null) {
            return null;
        }

        return [
            'link_type' => $linkType,
            'link_id' => $linkId,
            'link_label' => $link['label'] ?? '',
            'assigned_user_id' => auth_user_id(),
            'status' => 'open',
            'importance' => 3,
        ];
    }

    private function resolveReturnPath(string $fallback): string
    {
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === '') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $returnTo)) {
            return $fallback;
        }

        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return $fallback;
        }

        return $returnTo;
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        if (class_exists('App\\Controllers\\ErrorController')) {
            (new \App\Controllers\ErrorController())->notFound();
            return;
        }

        echo '404 Not Found';
    }

    private function linkTypeLabels(): array
    {
        $labels = [];
        foreach (Task::LINK_TYPES as $type) {
            $labels[$type] = Task::linkTypeLabel($type);
        }
        return $labels;
    }
}
