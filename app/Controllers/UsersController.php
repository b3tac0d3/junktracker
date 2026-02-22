<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserAction;
use App\Models\UserLoginRecord;
use App\Support\Mailer;

final class UsersController extends Controller
{
    public function index(): void
    {
        require_permission('users', 'view');

        $query = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $inviteFilter = trim((string) ($_GET['invite'] ?? 'all'));
        $allowedInviteFilters = ['all', 'pending', 'invited', 'expired', 'accepted', 'none'];
        if (!in_array($inviteFilter, $allowedInviteFilters, true)) {
            $inviteFilter = 'all';
        }

        $users = User::search($query, $status);
        $users = array_values(array_filter(array_map(static function (array $user): array {
            $user['invite'] = User::inviteStatus($user);
            return $user;
        }, $users), static function (array $user) use ($inviteFilter): bool {
            if ($inviteFilter === 'all') {
                return true;
            }

            $invite = is_array($user['invite'] ?? null) ? $user['invite'] : [];
            $inviteStatus = (string) ($invite['status'] ?? 'none');
            return match ($inviteFilter) {
                'pending' => in_array($inviteStatus, ['invited', 'expired'], true),
                'invited' => $inviteStatus === 'invited',
                'expired' => $inviteStatus === 'expired',
                'accepted' => $inviteStatus === 'accepted',
                'none' => $inviteStatus === 'none',
                default => true,
            };
        }));

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/users-table.js') . '"></script>',
        ]);

        $this->render('users/index', [
            'pageTitle' => 'Users',
            'users' => $users,
            'query' => $query,
            'status' => $status,
            'inviteFilter' => $inviteFilter,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function show(array $params): void
    {
        require_permission('users', 'view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $user = $id > 0 ? User::findById($id) : null;
        $lastLogin = $id > 0 ? UserLoginRecord::latestForUser($id) : null;
        $canManageEmployeeLink = has_role(2);
        $employeeLinkSupported = Employee::supportsUserLinking();
        $linkedEmployee = ($canManageEmployeeLink && $employeeLinkSupported && $id > 0)
            ? Employee::linkedToUser($id)
            : null;
        $viewer = auth_user();
        $viewerRole = (int) ($viewer['role'] ?? 0);
        $viewerId = auth_user_id();
        $canManageRole = $user ? $this->canManageRoleValue((int) ($user['role'] ?? 0)) : false;
        $canDeactivate = $canManageRole && !($viewerId !== null && $viewerId === $id && $viewerRole !== 99);

        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
        }

        $inviteStatus = User::inviteStatus($user);

        $pageScripts = '';
        if ($canManageEmployeeLink && $employeeLinkSupported) {
            $pageScripts = '<script src="' . asset('js/user-employee-link.js') . '"></script>';
        }

        $this->render('users/show', [
            'pageTitle' => 'User Details',
            'user' => $user,
            'lastLogin' => $lastLogin,
            'canManageEmployeeLink' => $canManageEmployeeLink,
            'employeeLinkSupported' => $employeeLinkSupported,
            'linkedEmployee' => $linkedEmployee,
            'canManageRole' => $canManageRole,
            'canDeactivate' => $canDeactivate,
            'inviteStatus' => $inviteStatus,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function employeeLookup(): void
    {
        $this->authorizeEmployeeLinking();

        if (!Employee::supportsUserLinking()) {
            json_response([]);
            return;
        }

        $term = trim((string) ($_GET['q'] ?? ''));
        $rows = Employee::lookupForUserLink($term);
        $payload = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'linked_user_id' => isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
                'linked_user_name' => trim((string) ($row['linked_user_name'] ?? '')),
            ];
        }, $rows);

        json_response($payload);
    }

    public function linkEmployee(array $params): void
    {
        $this->authorizeEmployeeLinking();

        $userId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($userId <= 0) {
            redirect('/users');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . $userId);
        }

        $user = User::findById($userId);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        if (!Employee::supportsUserLinking()) {
            flash('error', 'Employee-to-user linking is not enabled yet. Run the user/employee link migration first.');
            redirect('/users/' . $userId);
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            flash('error', 'Select an employee to link.');
            redirect('/users/' . $userId);
        }

        $employee = Employee::findActiveById($employeeId);
        if (!$employee) {
            flash('error', 'Selected employee is invalid or inactive.');
            redirect('/users/' . $userId);
        }

        try {
            Employee::assignToUser($employeeId, $userId, auth_user_id());
        } catch (\Throwable) {
            flash('error', 'Unable to link employee right now. Please try again.');
            redirect('/users/' . $userId);
        }

        $employeeName = trim((string) ($employee['name'] ?? ''));
        $label = $employeeName !== '' ? $employeeName : ('Employee #' . $employeeId);
        log_user_action(
            'user_employee_linked',
            'employees',
            $employeeId,
            'Linked ' . $label . ' to user #' . $userId . '.'
        );

        flash('success', 'Employee linked for punch actions.');
        redirect('/users/' . $userId);
    }

    public function unlinkEmployee(array $params): void
    {
        $this->authorizeEmployeeLinking();

        $userId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($userId <= 0) {
            redirect('/users');
        }

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . $userId);
        }

        if (!Employee::supportsUserLinking()) {
            flash('error', 'Employee-to-user linking is not enabled yet. Run the user/employee link migration first.');
            redirect('/users/' . $userId);
        }

        $linked = Employee::linkedToUser($userId);
        $affected = Employee::clearUserLink($userId, auth_user_id());

        if ($affected < 1) {
            flash('error', 'No linked employee found for this user.');
            redirect('/users/' . $userId);
        }

        $employeeId = isset($linked['id']) ? (int) $linked['id'] : null;
        $employeeName = trim((string) ($linked['name'] ?? ''));
        $label = $employeeName !== '' ? $employeeName : ($employeeId !== null ? ('Employee #' . $employeeId) : 'employee');
        log_user_action(
            'user_employee_unlinked',
            'employees',
            $employeeId,
            'Unlinked ' . $label . ' from user #' . $userId . '.'
        );

        flash('success', 'Employee link removed.');
        redirect('/users/' . $userId);
    }

    public function create(): void
    {
        require_permission('users', 'create');

        $this->render('users/create', [
            'pageTitle' => 'Add User',
            'roleOptions' => $this->assignableRoleOptions(),
            'employeeLinkReview' => null,
            'employeeLinkSupported' => Employee::supportsUserLinking() && has_role(2),
            'canCreateEmployee' => can_access('employees', 'create'),
        ]);

        clear_old();
    }

    public function store(): void
    {
        require_permission('users', 'create');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/new');
        }

        $data = $this->collectFormData();
        $data['business_id'] = is_global_role_value((int) ($data['role'] ?? 0)) ? 0 : current_business_id();
        $errors = $this->validate($data, true);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/users/new');
        }

        $employeeLinkSupported = Employee::supportsUserLinking() && has_role(2);
        $canCreateEmployee = can_access('employees', 'create');
        $employeeCandidates = $employeeLinkSupported ? Employee::findUserLinkCandidates($data) : [];
        $employeeLinkReviewed = isset($_POST['employee_link_reviewed']) && (string) ($_POST['employee_link_reviewed'] ?? '') === '1';
        $employeeLinkDecision = trim((string) ($_POST['employee_link_decision'] ?? ''));

        if ($employeeLinkSupported) {
            if (!$employeeLinkReviewed) {
                $this->render('users/create', [
                    'pageTitle' => 'Add User',
                    'roleOptions' => $this->assignableRoleOptions(),
                    'formValues' => $data,
                    'employeeLinkReview' => $this->buildEmployeeLinkReview($data, $employeeCandidates, '', $canCreateEmployee),
                    'employeeLinkSupported' => $employeeLinkSupported,
                    'canCreateEmployee' => $canCreateEmployee,
                ]);
                return;
            }

            $decisionError = $this->validateEmployeeLinkDecision($employeeLinkDecision, $employeeCandidates, $canCreateEmployee);
            if ($decisionError !== null) {
                flash('error', $decisionError);
                $this->render('users/create', [
                    'pageTitle' => 'Add User',
                    'roleOptions' => $this->assignableRoleOptions(),
                    'formValues' => $data,
                    'employeeLinkReview' => $this->buildEmployeeLinkReview($data, $employeeCandidates, $employeeLinkDecision, $canCreateEmployee),
                    'employeeLinkSupported' => $employeeLinkSupported,
                    'canCreateEmployee' => $canCreateEmployee,
                ]);
                return;
            }
        }

        $data['password'] = '';
        $data['password_confirm'] = '';
        $userId = User::create($data, auth_user_id());
        $savedUser = User::findById($userId);
        if ($savedUser) {
            Contact::upsertFromUser($savedUser, auth_user_id());
        }

        $linkMessage = '';
        if ($employeeLinkSupported) {
            $linkMessage = $this->processEmployeeLinkDecision($employeeLinkDecision, $employeeCandidates, $data, $userId);
        }

        $inviteSent = $this->sendSetupInvite($userId, $data['email'], trim($data['first_name'] . ' ' . $data['last_name']));
        if (!$savedUser) {
            $savedUser = User::findById($userId);
        }
        $inviteState = $savedUser ? User::inviteStatus($savedUser) : null;
        $inviteExpiryLabel = '';
        if ($inviteState && !empty($inviteState['expires_at'])) {
            $inviteExpiryLabel = ' Invite expires: ' . format_datetime((string) $inviteState['expires_at']) . '.';
        }
        $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
        log_user_action(
            'user_created',
            'users',
            $userId,
            'Created user #' . $userId . ' (' . ($fullName !== '' ? $fullName : $data['email']) . ').'
        );
        if ($inviteSent) {
            flash('success', trim('User created and setup email sent.' . $inviteExpiryLabel . ' ' . $linkMessage));
        } else {
            flash('error', trim('User created, but setup email could not be sent. Check mail settings/logs.' . $inviteExpiryLabel . ' ' . $linkMessage));
        }
        redirect('/users/' . $userId);
    }

    public function update(array $params): void
    {
        require_permission('users', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . ($params['id'] ?? '') . '/edit');
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $existing = User::findById($id);
        if (!$existing) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
        }
        if (!$this->canManageRoleValue((int) ($existing['role'] ?? 0))) {
            flash('error', 'You cannot edit this user role.');
            redirect('/users/' . $id);
        }

        $data = $this->collectFormData();
        $data['business_id'] = is_global_role_value((int) ($data['role'] ?? 0)) ? 0 : current_business_id();
        $errors = $this->validate($data, false, $id);

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            flash_old($data);
            redirect('/users/' . $id . '/edit');
        }

        User::update($id, $data, auth_user_id());
        $updatedUser = User::findById($id);
        if ($updatedUser) {
            Contact::upsertFromUser($updatedUser, auth_user_id());
        }
        log_user_action('user_updated', 'users', $id, 'Updated user #' . $id . '.');
        redirect('/users/' . $id);
    }

    public function deactivate(array $params): void
    {
        require_permission('users', 'delete');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect('/users/' . ($params['id'] ?? ''));
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $viewer = auth_user();
        $viewerRole = (int) ($viewer['role'] ?? 0);
        $viewerId = auth_user_id();
        if ($viewerId !== null && $viewerId === $id && $viewerRole !== 99) {
            flash('error', 'You cannot deactivate your own account.');
            redirect('/users/' . $id);
        }

        $user = User::findById($id);
        if (!$user) {
            flash('error', 'User not found.');
            redirect('/users');
        }
        if (!$this->canManageRoleValue((int) ($user['role'] ?? 0))) {
            flash('error', 'You cannot deactivate this user.');
            redirect('/users/' . $id);
        }

        User::deactivate($id, auth_user_id());
        $deactivatedUser = User::findById($id);
        if ($deactivatedUser) {
            Contact::upsertFromUser($deactivatedUser, auth_user_id());
        }
        log_user_action('user_deactivated', 'users', $id, 'Deactivated user #' . $id . '.');
        redirect('/users/' . $id);
    }

    public function autoAcceptInvite(array $params): void
    {
        require_permission('users', 'edit');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            flash('error', 'Your session expired. Please try again.');
            redirect('/users/' . ($params['id'] ?? ''));
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $user = User::findById($id);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }
        if (!$this->canManageRoleValue((int) ($user['role'] ?? 0))) {
            flash('error', 'You cannot manage invites for this user.');
            redirect('/users');
        }

        $invite = User::inviteStatus($user);
        if (!in_array((string) ($invite['status'] ?? ''), ['invited', 'expired'], true)) {
            flash('error', 'This user does not have an outstanding invite.');
            redirect('/users/' . $id);
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $accepted = User::autoAcceptInvite($id, $temporaryPassword, auth_user_id());
        if (!$accepted) {
            flash('error', 'Unable to auto-accept this invite. Refresh and try again.');
            redirect('/users/' . $id);
        }

        log_user_action('user_invite_auto_accepted', 'users', $id, 'Auto-accepted invite for user #' . $id . '.');
        flash(
            'success',
            'Invite auto-accepted. Temporary password: ' . $temporaryPassword . ' (share securely and have them change it immediately).'
        );
        redirect('/users/' . $id);
    }

    public function edit(array $params): void
    {
        require_permission('users', 'edit');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $user = $id > 0 ? User::findById($id) : null;

        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
        }
        if (!$this->canManageRoleValue((int) ($user['role'] ?? 0))) {
            flash('error', 'You cannot edit this user role.');
            redirect('/users');
        }

        $this->render('users/edit', [
            'pageTitle' => 'Edit User',
            'user' => $user,
            'roleOptions' => $this->assignableRoleOptions(),
        ]);

        clear_old();
    }

    public function myActivity(): void
    {
        $userId = auth_user_id();
        if ($userId === null) {
            redirect('/login');
        }

        $this->renderActivity($userId, true);
    }

    public function activity(array $params): void
    {
        require_permission('users', 'view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $this->renderActivity($id, false);
    }

    public function logins(array $params): void
    {
        require_permission('users', 'view');

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            redirect('/users');
        }

        $user = User::findById($id);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $records = UserLoginRecord::forUser($id, $query);
        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/user-activity-table.js') . '"></script>',
        ]);

        $this->render('users/logins', [
            'pageTitle' => 'User Login Records',
            'user' => $user,
            'records' => $records,
            'query' => $query,
            'isReady' => UserLoginRecord::isAvailable(),
            'pageScripts' => $pageScripts,
        ]);
    }

    private function collectFormData(): array
    {
        return [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => (int) ($_POST['role'] ?? 1),
            'is_active' => (int) ($_POST['is_active'] ?? 1),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ];
    }

    private function validate(array $data, bool $isCreate, ?int $userId = null): array
    {
        $errors = [];

        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        }
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        } elseif (User::emailInUse($data['email'], $userId)) {
            $errors[] = 'That email is already in use.';
        }
        if ($data['password'] !== '' && $data['password'] !== $data['password_confirm']) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($data['password'] !== '' && strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        $allowedRoles = array_keys($this->assignableRoleOptions());
        if (!in_array((int) $data['role'], $allowedRoles, true)) {
            $errors[] = 'Selected role is not allowed.';
        }

        return $errors;
    }

    private function assignableRoleOptions(): array
    {
        return assignable_role_options_for_user(auth_user_role());
    }

    private function canManageRoleValue(int $targetRole): bool
    {
        return can_manage_role(auth_user_role(), $targetRole);
    }

    private function sendSetupInvite(int $userId, string $email, string $displayName): bool
    {
        if ($userId <= 0 || trim($email) === '') {
            return false;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Throwable) {
            return false;
        }
        User::issuePasswordSetupToken($userId, $token, 72, auth_user_id());

        $name = trim($displayName);
        if ($name === '') {
            $name = 'there';
        }

        $link = absolute_url('/set-password?token=' . urlencode($token));
        $subject = 'Set your JunkTracker password';
        $body = "Hi {$name},\n\n"
            . "Your JunkTracker account has been created.\n"
            . "Use this link to set your password (expires in 72 hours):\n{$link}\n\n"
            . "If you did not expect this, please contact your administrator.\n";

        return Mailer::send($email, $subject, $body);
    }

    private function renderActivity(int $userId, bool $isOwn): void
    {
        $user = User::findById($userId);
        if (!$user) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }

            echo '404 Not Found';
            return;
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $actions = UserAction::forUser($userId, $query);

        $pageScripts = implode("\n", [
            '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>',
            '<script src="' . asset('js/user-activity-table.js') . '"></script>',
        ]);

        $this->render('users/activity', [
            'pageTitle' => $isOwn ? 'My Activity Log' : 'User Activity Log',
            'user' => $user,
            'actions' => $actions,
            'query' => $query,
            'isOwnActivity' => $isOwn,
            'isLogReady' => UserAction::isAvailable(),
            'pageScripts' => $pageScripts,
        ]);
    }

    private function authorizeEmployeeLinking(): void
    {
        require_permission('users', 'view');

        if (!has_role(2)) {
            redirect('/401');
        }
    }

    private function buildEmployeeLinkReview(
        array $userData,
        array $candidates,
        string $selectedDecision,
        bool $canCreateEmployee
    ): array {
        $email = strtolower(trim((string) ($userData['email'] ?? '')));
        $firstName = strtolower(trim((string) ($userData['first_name'] ?? '')));
        $lastName = strtolower(trim((string) ($userData['last_name'] ?? '')));

        $normalizedCandidates = [];
        foreach ($candidates as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }

            $reasons = [];
            $candidateEmail = strtolower(trim((string) ($candidate['email'] ?? '')));
            $candidateFirst = strtolower(trim((string) ($candidate['first_name'] ?? '')));
            $candidateLast = strtolower(trim((string) ($candidate['last_name'] ?? '')));

            if ($email !== '' && $candidateEmail !== '' && $candidateEmail === $email) {
                $reasons[] = 'email';
            }
            if ($firstName !== '' && $lastName !== '' && $candidateFirst === $firstName && $candidateLast === $lastName) {
                $reasons[] = 'name';
            }
            if (empty($reasons)) {
                $reasons[] = 'possible';
            }

            $candidate['match_reason'] = implode(' + ', $reasons);
            $normalizedCandidates[] = $candidate;
        }

        return [
            'has_candidates' => !empty($normalizedCandidates),
            'candidates' => $normalizedCandidates,
            'selected_decision' => $selectedDecision,
            'can_create_employee' => $canCreateEmployee,
        ];
    }

    private function validateEmployeeLinkDecision(string $decision, array $candidates, bool $canCreateEmployee): ?string
    {
        if ($decision === '') {
            return 'Choose how this user should be linked for punch in/out before saving.';
        }

        if ($decision === 'skip') {
            return null;
        }

        if ($decision === 'create_new') {
            return $canCreateEmployee ? null : 'You do not have permission to create employees.';
        }

        if (str_starts_with($decision, 'employee:')) {
            $employeeId = $this->parseEmployeeDecisionEmployeeId($decision);
            if ($employeeId === null) {
                return 'Select a valid employee match.';
            }

            foreach ($candidates as $candidate) {
                if ((int) ($candidate['id'] ?? 0) === $employeeId) {
                    return null;
                }
            }

            return 'Selected employee match is no longer valid. Please select again.';
        }

        return 'Invalid employee link decision.';
    }

    private function processEmployeeLinkDecision(string $decision, array $candidates, array $userData, int $userId): string
    {
        if ($decision === '' || $decision === 'skip') {
            return 'Employee link skipped.';
        }

        try {
            if (str_starts_with($decision, 'employee:')) {
                $employeeId = $this->parseEmployeeDecisionEmployeeId($decision);
                if ($employeeId === null) {
                    return 'Employee link skipped.';
                }

                $selected = null;
                foreach ($candidates as $candidate) {
                    if ((int) ($candidate['id'] ?? 0) === $employeeId) {
                        $selected = $candidate;
                        break;
                    }
                }

                if ($selected === null) {
                    return 'Employee link skipped.';
                }

                Employee::assignToUser($employeeId, $userId, auth_user_id());
                $employeeName = trim((string) ($selected['name'] ?? ''));
                $label = $employeeName !== '' ? $employeeName : ('Employee #' . $employeeId);
                log_user_action('user_employee_linked', 'employees', $employeeId, 'Linked ' . $label . ' to user #' . $userId . ' during user creation.');

                return 'Linked to existing employee: ' . $label . '.';
            }

            if ($decision === 'create_new' && can_access('employees', 'create')) {
                $employeeData = [
                    'first_name' => trim((string) ($userData['first_name'] ?? '')),
                    'last_name' => trim((string) ($userData['last_name'] ?? '')),
                    'phone' => '',
                    'email' => trim((string) ($userData['email'] ?? '')),
                    'hire_date' => date('Y-m-d'),
                    'fire_date' => null,
                    'wage_type' => 'hourly',
                    'pay_rate' => null,
                    'note' => 'Auto-created when user #' . $userId . ' was added.',
                    'active' => 1,
                ];

                $employeeId = Employee::create($employeeData, auth_user_id());
                Employee::assignToUser($employeeId, $userId, auth_user_id());
                $employee = Employee::findById($employeeId);
                if ($employee) {
                    Contact::upsertFromEmployee($employee, auth_user_id());
                }

                $employeeName = trim($employeeData['first_name'] . ' ' . $employeeData['last_name']);
                $label = $employeeName !== '' ? $employeeName : ('Employee #' . $employeeId);
                log_user_action('employee_created', 'employees', $employeeId, 'Auto-created employee for user #' . $userId . '.');
                log_user_action('user_employee_linked', 'employees', $employeeId, 'Linked auto-created employee to user #' . $userId . '.');

                return 'Created and linked new employee: ' . $label . '.';
            }
        } catch (\Throwable) {
            return 'Could not complete employee linking automatically.';
        }

        return 'Employee link skipped.';
    }

    private function parseEmployeeDecisionEmployeeId(string $decision): ?int
    {
        if (!str_starts_with($decision, 'employee:')) {
            return null;
        }

        $raw = substr($decision, strlen('employee:'));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    private function generateTemporaryPassword(): string
    {
        try {
            $random = bin2hex(random_bytes(6));
        } catch (\Throwable) {
            $random = substr(hash('sha256', uniqid('junktracker', true)), 0, 12);
        }

        return 'JT!' . strtoupper(substr($random, 0, 4)) . '-' . substr($random, 4, 8);
    }
}
