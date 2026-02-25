<?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $actionOptions = is_array($actionOptions ?? null) ? $actionOptions : [];
    $query = (string) ($filters['q'] ?? '');
    $selectedActionKey = (string) ($filters['action_key'] ?? '');
    $dateFrom = (string) ($filters['date_from'] ?? '');
    $dateTo = (string) ($filters['date_to'] ?? '');

    $userName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    if ($userName === '') {
        $userName = (string) ($user['email'] ?? ('User #' . ($user['id'] ?? '')));
    }
    $usersBasePath = trim((string) ($usersBasePath ?? '/users'));
    if ($usersBasePath === '') {
        $usersBasePath = '/users';
    }
    $isGlobalDirectory = !empty($isGlobalDirectory);

    $entityUrlFor = static function (?string $table, ?int $id) use ($usersBasePath, $user): ?string {
        if ($id === null || $id <= 0 || $table === null || $table === '') {
            return null;
        }

        return match ($table) {
            'users' => url($usersBasePath . '/' . $id),
            'jobs' => url('/jobs/' . $id),
            'clients' => url('/clients/' . $id),
            'companies' => url('/companies/' . $id),
            'estates' => url('/estates/' . $id),
            'prospects' => url('/prospects/' . $id),
            'expenses' => url('/expenses'),
            'sales' => url('/sales/' . $id),
            'employees' => url('/employees/' . $id),
            'tasks' => url('/tasks/' . $id),
            'employee_time_entries' => url('/time-tracking/' . $id),
            'user_login_records' => url($usersBasePath . '/' . ($user['id'] ?? '') . '/logins'),
            default => null,
        };
    };
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1"><?= e(!empty($isOwnActivity) ? 'My Activity Log' : 'User Activity Log') ?></h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <?php if (!empty($isOwnActivity)): ?>
                    <li class="breadcrumb-item active">Activity Log</li>
                <?php else: ?>
                    <?php if ($isGlobalDirectory): ?>
                        <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                        <li class="breadcrumb-item"><a href="<?= url($usersBasePath) ?>">Global Users</a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="<?= url($usersBasePath) ?>">Users</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item"><a href="<?= url($usersBasePath . '/' . ($user['id'] ?? '')) ?>"><?= e($userName) ?></a></li>
                    <li class="breadcrumb-item active">Activity Log</li>
                <?php endif; ?>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <?php if (!empty($isOwnActivity)): ?>
                <a class="btn btn-outline-secondary" href="<?= url('/settings') ?>">Back to Settings</a>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="<?= url($usersBasePath . '/' . ($user['id'] ?? '')) ?>">Back to User</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($isLogReady)): ?>
        <div class="alert alert-warning">
            Activity logging is not enabled yet. Run the latest SQL migration to create the <code>user_actions</code> table.
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-search me-1"></i>
            Filter Activity
        </div>
        <div class="card-body">
            <?php $activityBaseUrl = !empty($isOwnActivity) ? url('/activity-log') : url($usersBasePath . '/' . ($user['id'] ?? '') . '/activity'); ?>
            <form method="get" action="<?= $activityBaseUrl ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search action type, summary, details, table..." value="<?= e($query) ?>" />
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label">Action Type</label>
                        <select class="form-select" name="action_key">
                            <option value="">All actions</option>
                            <?php foreach ($actionOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= $selectedActionKey === (string) $option ? 'selected' : '' ?>>
                                    <?= e(ucwords(str_replace('_', ' ', (string) $option))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label">From</label>
                        <input class="form-control" type="date" name="date_from" value="<?= e($dateFrom) ?>" />
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label">To</label>
                        <input class="form-control" type="date" name="date_to" value="<?= e($dateTo) ?>" />
                    </div>
                    <div class="col-12 col-lg-1 d-grid">
                        <button class="btn btn-primary" type="submit">Apply</button>
                    </div>
                    <div class="col-12 col-lg-12">
                        <?php if ($query !== '' || $selectedActionKey !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $activityBaseUrl ?>">Reset Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clock-rotate-left me-1"></i>
            <?= e($userName) ?> Activity
        </div>
        <div class="card-body">
            <table id="userActivityTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>Summary</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actions as $action): ?>
                        <?php
                            $entityTable = (string) ($action['entity_table'] ?? '');
                            $entityId = isset($action['entity_id']) ? (int) $action['entity_id'] : null;
                            $recordLabel = $entityTable !== '' ? ($entityTable . ($entityId ? ' #' . $entityId : '')) : '—';
                            $entityUrl = $entityUrlFor($entityTable !== '' ? $entityTable : null, $entityId);
                            $actionLabel = trim((string) ($action['action_key'] ?? ''));
                            $actionLabel = $actionLabel !== '' ? ucwords(str_replace('_', ' ', $actionLabel)) : 'Event';
                            $summary = trim((string) ($action['summary'] ?? ''));
                            $details = trim((string) ($action['details'] ?? ''));
                        ?>
                        <tr>
                            <td><?= e(format_datetime($action['created_at'] ?? null)) ?></td>
                            <td><?= e($actionLabel) ?></td>
                            <td>
                                <?php if ($entityUrl !== null): ?>
                                    <a href="<?= e($entityUrl) ?>"><?= e($recordLabel) ?></a>
                                <?php else: ?>
                                    <?= e($recordLabel) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= e($summary !== '' ? $summary : '—') ?></div>
                                <?php if ($details !== ''): ?>
                                    <div class="text-muted small mt-1"><?= e($details) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($action['ip_address'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
