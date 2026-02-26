<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use App\Models\UserFilterPreset;
use Core\Controller;

final class TasksController extends Controller
{
    public function index(): void
    {
        $this->authorize('view');

        $currentUserId = auth_user_id() ?? 0;
        $canViewAllOwners = $this->canViewAllTaskOwners();
        $ownerScopeOptions = $canViewAllOwners ? ['mine', 'all'] : ['mine'];

        $moduleKey = 'tasks';
        $userId = $currentUserId;
        $savedPresets = $userId > 0 ? UserFilterPreset::forUser($userId, $moduleKey) : [];
        $selectedPresetId = $this->toIntOrNull($_GET['preset_id'] ?? null);
        $presetFilters = [];
        if ($selectedPresetId !== null && $selectedPresetId > 0 && $userId > 0) {
            $preset = UserFilterPreset::findForUser($selectedPresetId, $userId, $moduleKey);
            if ($preset) {
                $presetFilters = is_array($preset['filters'] ?? null) ? $preset['filters'] : [];
            } else {
                $selectedPresetId = null;
            }
        }

        $filters = [
            'q' => trim((string) ($presetFilters['q'] ?? '')),
            'status' => (string) ($presetFilters['status'] ?? 'open'),
            'assignment_status' => (string) ($presetFilters['assignment_status'] ?? 'all'),
            'importance' => $this->toIntOrNull($presetFilters['importance'] ?? null),
            'link_type' => (string) ($presetFilters['link_type'] ?? 'all'),
            'owner_scope' => (string) ($presetFilters['owner_scope'] ?? 'mine'),
            'assigned_user_id' => $this->toIntOrNull($presetFilters['assigned_user_id'] ?? null),
            'record_status' => (string) ($presetFilters['record_status'] ?? 'active'),
            'due_start' => trim((string) ($presetFilters['due_start'] ?? '')),
            'due_end' => trim((string) ($presetFilters['due_end'] ?? '')),
            'current_user_id' => $currentUserId,
        ];

        if (array_key_exists('q', $_GET)) {
            $filters['q'] = trim((string) ($_GET['q'] ?? ''));
        }
        if (array_key_exists('status', $_GET)) {
            $filters['status'] = (string) ($_GET['status'] ?? 'open');
        }
        if (array_key_exists('assignment_status', $_GET)) {
            $filters['assignment_status'] = (string) ($_GET['assignment_status'] ?? 'all');
        }
        if (array_key_exists('importance', $_GET)) {
            $filters['importance'] = $this->toIntOrNull($_GET['importance'] ?? null);
        }
        if (array_key_exists('link_type', $_GET)) {
            $filters['link_type'] = (string) ($_GET['link_type'] ?? 'all');
        }
        if (array_key_exists('owner_scope', $_GET)) {
            $filters['owner_scope'] = (string) ($_GET['owner_scope'] ?? 'all');
        }
        if (array_key_exists('assigned_user_id', $_GET)) {
            $filters['assigned_user_id'] = $this->toIntOrNull($_GET['assigned_user_id'] ?? null);
        }
        if (array_key_exists('record_status', $_GET)) {
            $filters['record_status'] = (string) ($_GET['record_status'] ?? 'active');
        }
        if (array_key_exists('due_start', $_GET)) {
            $filters['due_start'] = trim((string) ($_GET['due_start'] ?? ''));
        }
        if (array_key_exists('due_end', $_GET)) {
            $filters['due_end'] = trim((string) ($_GET['due_end'] ?? ''));
        }

        if (!in_array($filters['status'], array_merge(['all', 'overdue'], Task::STATUSES), true)) {
            $filters['status'] = 'open';
        }
        if (!in_array($filters['assignment_status'], Task::ASSIGNMENT_STATUSES, true)) {
            $filters['assignment_status'] = 'all';
        }
        if (($filters['importance'] ?? 0) < 1 || ($filters['importance'] ?? 0) > 5) {
            $filters['importance'] = null;
        }
        if (!in_array($filters['link_type'], array_merge(['all'], Task::LINK_TYPES), true)) {
            $filters['link_type'] = 'all';
        }
        if (!in_array($filters['owner_scope'], $ownerScopeOptions, true)) {
            $filters['owner_scope'] = 'mine';
        }
        if (!in_array($filters['record_status'], ['active', 'deleted', 'all'], true)) {
            $filters['record_status'] = 'active';
        }
        if (!$canViewAllOwners) {
            $filters['owner_scope'] = 'mine';
            if ((int) ($filters['assigned_user_id'] ?? 0) > 0 && (int) ($filters['assigned_user_id'] ?? 0) !== $currentUserId) {
                $filters['assigned_user_id'] = null;
            }
        }

        $tasks = Task::filter($filters);
        if ((string) ($_GET['export'] ?? '') === 'csv') {
            $this->downloadIndexCsv($tasks);
            return;
        }

        $pageScripts = '<script src="' . asset('js/tasks-quick-add.js') . '"></script>';

        $this->render('tasks/index', [
            'pageTitle' => 'Tasks',
            'filters' => $filters,
            'tasks' => $tasks,
            'summary' => Task::summary($filters),
            'users' => $canViewAllOwners ? Task::users() : [],
            'statusOptions' => Task::STATUSES,
            'assignmentOptions' => Task::ASSIGNMENT_STATUSES,
            'linkTypes' => Task::LINK_TYPES,
            'linkTypeLabels' => $this->linkTypeLabels(),
            'ownerScopes' => $ownerScopeOptions,
            'defaultOwnerScope' => 'mine',
            'canViewAllTaskOwners' => $canViewAllOwners,
            'savedPresets' => $savedPresets,
            'selectedPresetId' => $selectedPresetId,
            'filterPresetModule' => $moduleKey,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function create(): void
    {
        $this->authorize('create');

        $quickFilters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? 'all'),
            'assignment_status' => 'all',
            'importance' => null,
            'link_type' => 'all',
            'owner_scope' => 'mine',
            'assigned_user_id' => auth_user_id() ?? null,
            'record_status' => 'active',
            'due_start' => '',
            'due_end' => '',
            'current_user_id' => auth_user_id() ?? 0,
        ];
        if (!in_array($quickFilters['status'], array_merge(['all'], Task::STATUSES), true)) {
            $quickFilters['status'] = 'all';
        }

        $tasks = Task::filter($quickFilters);
        if (count($tasks) > 150) {
            $tasks = array_slice($tasks, 0, 150);
        }

        $this->render('tasks/quick', [
            'pageTitle' => 'Quick Add Tasks',
            'tasks' => $tasks,
            'quickFilters' => $quickFilters,
            'statusOptions' => Task::STATUSES,
            'pageScripts' => '<script src="' . asset('js/tasks-quick-add.js') . '"></script>',
        ]);

        clear_old();
    }

    public function createFull(): void
    {
        $this->authorize('create');

        $task = $this->prefillTask();

        $this->render('tasks/create', [
            'pageTitle' => 'Add Task',
            'task' => $task,
            'entryMode' => 'full',
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
        $newTaskRedirect = trim((string) ($_POST['entry_mode'] ?? '')) === 'full' ? '/tasks/new/full' : '/tasks/new';

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $message = 'Your session expired. Please try again.';
            if (expects_json_response()) {
                json_response([
                    'ok' => false,
                    'message' => $message,
                ], 419);
                return;
            }

            flash('error', $message);
            redirect($newTaskRedirect);
        }

        $data = $this->collectFormData();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $message = implode(' ', $errors);
            if (expects_json_response()) {
                json_response([
                    'ok' => false,
                    'message' => $message,
                ], 422);
                return;
            }

            flash('error', $message);
            flash_old($_POST);
            redirect($newTaskRedirect);
        }

        $taskId = Task::create($data, auth_user_id());
        log_user_action('task_created', 'todos', $taskId, 'Created task: ' . ($data['title'] ?: ('Task #' . $taskId)));
        if (expects_json_response()) {
            $task = Task::findById($taskId);
            json_response([
                'ok' => true,
                'message' => 'Task added.',
                'task' => $this->taskJsonPayload($task ?: [
                    'id' => $taskId,
                    'title' => $data['title'],
                    'status' => $data['status'],
                    'due_at' => $data['due_at'],
                ]),
            ]);
            return;
        }

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
            'canRespondAssignment' => $this->canRespondToAssignment($task),
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
        log_user_action('task_updated', 'todos', $id, 'Updated task: ' . ($data['title'] ?: ('Task #' . $id)));
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
            if (expects_json_response()) {
                json_response([
                    'ok' => false,
                    'message' => 'Invalid task.',
                ], 400);
                return;
            }
            redirect('/tasks');
        }

        $returnPath = $this->resolveReturnPath('/tasks/' . $id);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            $message = 'Your session expired. Please try again.';
            if (expects_json_response()) {
                json_response([
                    'ok' => false,
                    'message' => $message,
                ], 419);
                return;
            }

            flash('error', $message);
            redirect($returnPath);
        }

        $task = Task::findById($id);
        if (!$task) {
            if (expects_json_response()) {
                json_response([
                    'ok' => false,
                    'message' => 'Task not found.',
                ], 404);
                return;
            }
            $this->renderNotFound();
            return;
        }

        if (!empty($task['deleted_at'])) {
            $message = 'Deleted tasks cannot be updated.';
            if (expects_json_response()) {
                json_response([
                    'ok' => false,
                    'message' => $message,
                ], 422);
                return;
            }

            flash('error', $message);
            redirect($returnPath);
        }

        $isCompleted = $this->toIntOrNull($_POST['is_completed'] ?? null) === 1;
        Task::setCompletion($id, $isCompleted, auth_user_id());

        $message = $isCompleted ? 'Task marked complete.' : 'Task marked active.';
        if (expects_json_response()) {
            $updatedTask = Task::findById($id);
            $status = (string) ($updatedTask['status'] ?? ($isCompleted ? 'closed' : 'open'));
            json_response([
                'ok' => true,
                'message' => $message,
                'task' => [
                    'id' => $id,
                    'status' => $status,
                    'is_completed' => $status === 'closed',
                ],
            ]);
            return;
        }

        flash('success', $message);
        redirect($returnPath);
    }

    public function respondAssignment(array $params): void
    {
        $this->authorize('edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/tasks');
        }

        $returnPath = $this->resolveReturnPath('/tasks/' . $id);

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect($returnPath);
        }

        $task = Task::findById($id);
        if (!$task) {
            $this->renderNotFound();
            return;
        }

        if (!$this->canRespondToAssignment($task)) {
            flash('error', 'You are not allowed to respond to this assignment.');
            redirect($returnPath);
        }

        $decision = strtolower(trim((string) ($_POST['decision'] ?? '')));
        if (!in_array($decision, ['accept', 'decline'], true)) {
            flash('error', 'Invalid assignment response.');
            redirect($returnPath);
        }

        $note = trim((string) ($_POST['assignment_note'] ?? ''));
        $saved = Task::respondAssignment($id, $decision, (int) (auth_user_id() ?? 0), $note !== '' ? $note : null);
        if (!$saved) {
            flash('error', 'Unable to update assignment status.');
            redirect($returnPath);
        }

        log_user_action(
            $decision === 'accept' ? 'task_assignment_accepted' : 'task_assignment_declined',
            'todos',
            $id,
            ($decision === 'accept' ? 'Accepted' : 'Declined') . ' task assignment.'
        );
        flash('success', $decision === 'accept' ? 'Task assignment accepted.' : 'Task assignment declined.');
        redirect($returnPath);
    }

    public function linkLookup(): void
    {
        $this->authorize('view');

        $type = (string) ($_GET['type'] ?? 'general');
        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Task::lookupLinks($type, $term));
    }

    public function userLookup(): void
    {
        $this->authorize('view');

        $term = trim((string) ($_GET['q'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(Task::lookupUsers($term));
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
        $assignedUserSearch = trim((string) ($_POST['assigned_user_search'] ?? ''));
        if ($assignedUserSearch !== '' && $data['assigned_user_id'] === null) {
            $errors[] = 'Select an owner from suggestions or leave owner blank.';
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
        return '<script src="' . asset('js/task-link-lookup.js') . '"></script>'
            . '<script src="' . asset('js/task-owner-lookup.js') . '"></script>';
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

    private function downloadIndexCsv(array $tasks): void
    {
        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [
                (string) ($task['id'] ?? ''),
                (string) ($task['title'] ?? ''),
                (string) ($task['link_label'] ?? ''),
                (string) (($task['assigned_user_name'] ?? '') !== '' ? $task['assigned_user_name'] : 'Unassigned'),
                (string) ($task['assignment_status'] ?? 'unassigned'),
                (string) ($task['status'] ?? ''),
                (string) ($task['importance'] ?? ''),
                format_datetime($task['due_at'] ?? null),
                format_datetime($task['updated_at'] ?? null),
            ];
        }

        stream_csv_download(
            'tasks-' . date('Ymd-His') . '.csv',
            ['ID', 'Task', 'Linked To', 'Assigned', 'Assignment', 'Status', 'Priority', 'Due', 'Last Activity'],
            $rows
        );
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

    private function canRespondToAssignment(array $task): bool
    {
        if (!empty($task['deleted_at'])) {
            return false;
        }

        $assignedUserId = (int) ($task['assigned_user_id'] ?? 0);
        if ($assignedUserId <= 0) {
            return false;
        }

        $assignmentStatus = strtolower(trim((string) ($task['assignment_status'] ?? '')));
        if ($assignmentStatus !== 'pending') {
            return false;
        }

        $currentUserId = (int) (auth_user_id() ?? 0);
        if ($currentUserId <= 0) {
            return false;
        }

        if ($currentUserId === $assignedUserId) {
            return true;
        }

        $currentRole = (int) ((auth_user()['role'] ?? 0));
        return $currentRole === 99 || $currentRole >= 2;
    }

    private function taskJsonPayload(array $task): array
    {
        $status = (string) ($task['status'] ?? 'open');
        if (!in_array($status, Task::STATUSES, true)) {
            $status = 'open';
        }

        $id = (int) ($task['id'] ?? 0);
        $title = trim((string) ($task['title'] ?? ''));

        return [
            'id' => $id,
            'title' => $title !== '' ? $title : ('Task #' . $id),
            'status' => $status,
            'is_completed' => $status === 'closed',
            'url' => url('/tasks/' . $id),
            'assigned_user_name' => (string) (($task['assigned_user_name'] ?? '') !== '' ? $task['assigned_user_name'] : 'Unassigned'),
            'due_at' => $task['due_at'] ?? null,
            'due_at_label' => format_datetime($task['due_at'] ?? null),
        ];
    }

    private function canViewAllTaskOwners(): bool
    {
        return has_role(2);
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
