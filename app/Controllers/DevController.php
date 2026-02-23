<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AdminPanel;
use App\Models\DevBug;
use Core\Controller;

final class DevController extends Controller
{
    public function index(): void
    {
        $this->authorizeDev();

        $this->render('dev/index', [
            'pageTitle' => 'Dev Center',
            'bugSummary' => DevBug::summary(),
            'recentBugs' => DevBug::recentOpen(8),
            'health' => AdminPanel::healthSummary(),
            'systemStatus' => AdminPanel::systemStatus(),
        ]);
    }

    public function bugs(): void
    {
        $this->authorizeDev();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'open')),
            'severity' => $this->toInt($_GET['severity'] ?? null),
            'environment' => trim((string) ($_GET['environment'] ?? 'all')),
            'assigned_user_id' => $this->toInt($_GET['assigned_user_id'] ?? null),
        ];

        $statusFilters = ['open', 'unresearched', 'confirmed', 'working', 'fixed_closed', 'all'];
        if (!in_array($filters['status'], $statusFilters, true)) {
            $filters['status'] = 'open';
        }
        if (($filters['severity'] ?? 0) < 1 || ($filters['severity'] ?? 0) > 5) {
            $filters['severity'] = 0;
        }
        if (!in_array($filters['environment'], ['all', 'local', 'live', 'both'], true)) {
            $filters['environment'] = 'all';
        }
        if (($filters['assigned_user_id'] ?? 0) < 1) {
            $filters['assigned_user_id'] = 0;
        }

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/dev-bugs-table.js') . '"></script>',
        ]);

        $this->render('dev/bugs/index', [
            'pageTitle' => 'Bug Board',
            'filters' => $filters,
            'bugs' => DevBug::filter($filters),
            'summary' => DevBug::summary(),
            'users' => DevBug::users(),
            'statusFilters' => $statusFilters,
            'statusOptions' => DevBug::STATUSES,
            'environmentOptions' => array_merge(['all'], DevBug::ENVIRONMENTS),
            'pageScripts' => $pageScripts,
        ]);

        clear_old();
    }

    public function storeBug(): void
    {
        $this->authorizeDev();

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/dev/bugs');
        }

        $data = $this->collectBugData($_POST);
        $errors = $this->validateBugData($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/dev/bugs');
        }

        $actorId = auth_user_id();
        $data['reported_by'] = $actorId;
        $bugId = DevBug::create($data, $actorId);

        log_user_action('dev_bug_created', 'dev_bugs', $bugId, 'Created dev bug #' . $bugId . '.');
        flash('success', 'Bug logged.');
        redirect('/dev/bugs/' . $bugId);
    }

    public function showBug(array $params): void
    {
        $this->authorizeDev();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/dev/bugs');
        }

        $bug = DevBug::findById($id);
        if (!$bug) {
            $this->renderNotFound();
            return;
        }

        $this->render('dev/bugs/show', [
            'pageTitle' => 'Bug #' . $id,
            'bug' => $bug,
            'notes' => DevBug::notes($id),
            'users' => DevBug::users(),
            'statusOptions' => DevBug::STATUSES,
            'environmentOptions' => DevBug::ENVIRONMENTS,
        ]);

        clear_old();
    }

    public function updateBug(array $params): void
    {
        $this->authorizeDev();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/dev/bugs');
        }

        $bug = DevBug::findById($id);
        if (!$bug) {
            $this->renderNotFound();
            return;
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/dev/bugs/' . $id);
        }

        $data = $this->collectBugData($_POST, true);
        $errors = $this->validateBugData($data);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($_POST);
            redirect('/dev/bugs/' . $id);
        }

        DevBug::update($id, $data, auth_user_id());
        log_user_action('dev_bug_updated', 'dev_bugs', $id, 'Updated dev bug #' . $id . '.');
        flash('success', 'Bug updated.');
        redirect('/dev/bugs/' . $id);
    }

    public function updateBugStatus(array $params): void
    {
        $this->authorizeDev();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/dev/bugs');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/dev/bugs');
        }

        $bug = DevBug::findById($id);
        if (!$bug) {
            $this->renderNotFound();
            return;
        }

        $status = trim((string) ($_POST['status'] ?? ''));
        if (!in_array($status, DevBug::STATUSES, true)) {
            flash('error', 'Invalid bug status.');
            redirect($this->safeReturn('/dev/bugs/' . $id));
        }

        DevBug::setStatus($id, $status, auth_user_id());
        log_user_action('dev_bug_status_updated', 'dev_bugs', $id, 'Set dev bug #' . $id . ' status to ' . $status . '.');

        flash('success', 'Bug status updated.');
        redirect($this->safeReturn('/dev/bugs/' . $id));
    }

    public function deleteBug(array $params): void
    {
        $this->authorizeDev();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/dev/bugs');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/dev/bugs/' . $id);
        }

        $bug = DevBug::findById($id);
        if (!$bug) {
            $this->renderNotFound();
            return;
        }

        DevBug::softDelete($id, auth_user_id());
        log_user_action('dev_bug_deleted', 'dev_bugs', $id, 'Deleted dev bug #' . $id . '.');
        flash('success', 'Bug deleted.');
        redirect('/dev/bugs');
    }

    public function addBugNote(array $params): void
    {
        $this->authorizeDev();

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/dev/bugs');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/dev/bugs/' . $id);
        }

        $bug = DevBug::findById($id);
        if (!$bug) {
            $this->renderNotFound();
            return;
        }

        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            flash('error', 'Note is required.');
            flash_old(['note' => (string) ($_POST['note'] ?? '')]);
            redirect('/dev/bugs/' . $id);
        }
        if (mb_strlen($note) > 5000) {
            flash('error', 'Note is too long (max 5000 characters).');
            flash_old(['note' => (string) ($_POST['note'] ?? '')]);
            redirect('/dev/bugs/' . $id);
        }

        $noteId = DevBug::addNote($id, $note, auth_user_id());
        if ($noteId <= 0) {
            flash('error', 'Unable to save bug note.');
            redirect('/dev/bugs/' . $id);
        }

        log_user_action('dev_bug_note_added', 'dev_bug_notes', $noteId, 'Added note to dev bug #' . $id . '.');
        flash('success', 'Note added.');
        redirect('/dev/bugs/' . $id);
    }

    private function authorizeDev(): void
    {
        require_role(4);
    }

    private function collectBugData(array $input, bool $allowStatus = true): array
    {
        $status = trim((string) ($input['status'] ?? 'unresearched'));
        if (!$allowStatus) {
            $status = 'unresearched';
        }
        if (!in_array($status, DevBug::STATUSES, true)) {
            $status = 'unresearched';
        }

        $environment = trim((string) ($input['environment'] ?? 'local'));
        if (!in_array($environment, DevBug::ENVIRONMENTS, true)) {
            $environment = 'local';
        }

        $severity = $this->toInt($input['severity'] ?? null);
        if ($severity < 1 || $severity > 5) {
            $severity = 3;
        }

        $assignedUserId = $this->toInt($input['assigned_user_id'] ?? null);
        if ($assignedUserId <= 0) {
            $assignedUserId = null;
        }

        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'details' => trim((string) ($input['details'] ?? '')),
            'status' => $status,
            'severity' => $severity,
            'environment' => $environment,
            'module_key' => trim((string) ($input['module_key'] ?? '')),
            'route_path' => trim((string) ($input['route_path'] ?? '')),
            'assigned_user_id' => $assignedUserId,
        ];
    }

    private function validateBugData(array $data): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors[] = 'Bug title is required.';
        }
        if (!in_array($data['status'], DevBug::STATUSES, true)) {
            $errors[] = 'Bug status is invalid.';
        }
        if ($data['severity'] < 1 || $data['severity'] > 5) {
            $errors[] = 'Severity must be between 1 and 5.';
        }
        if (!in_array($data['environment'], DevBug::ENVIRONMENTS, true)) {
            $errors[] = 'Environment is invalid.';
        }
        if (strlen($data['module_key']) > 80) {
            $errors[] = 'Module key is too long.';
        }
        if (strlen($data['route_path']) > 255) {
            $errors[] = 'Route path is too long.';
        }

        if ($data['assigned_user_id'] !== null) {
            $userIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), DevBug::users());
            if (!in_array((int) $data['assigned_user_id'], $userIds, true)) {
                $errors[] = 'Assigned user is invalid.';
            }
        }

        return $errors;
    }

    private function toInt(mixed $value): int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
            return 0;
        }

        return (int) $raw;
    }

    private function safeReturn(string $fallback): string
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
}
