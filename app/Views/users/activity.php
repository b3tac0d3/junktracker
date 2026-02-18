<?php
    $userName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    if ($userName === '') {
        $userName = (string) ($user['email'] ?? ('User #' . ($user['id'] ?? '')));
    }

    $entityUrlFor = static function (?string $table, ?int $id): ?string {
        if ($id === null || $id <= 0 || $table === null || $table === '') {
            return null;
        }

        return match ($table) {
            'users' => url('/users/' . $id),
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
                    <li class="breadcrumb-item"><a href="<?= url('/users') ?>">Users</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('/users/' . ($user['id'] ?? '')) ?>"><?= e($userName) ?></a></li>
                    <li class="breadcrumb-item active">Activity Log</li>
                <?php endif; ?>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($isOwnActivity)): ?>
                <a class="btn btn-outline-secondary" href="<?= url('/settings') ?>">Back to Settings</a>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="<?= url('/users/' . ($user['id'] ?? '')) ?>">Back to User</a>
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
            Search Activity
        </div>
        <div class="card-body">
            <form method="get" action="<?= !empty($isOwnActivity) ? url('/activity-log') : url('/users/' . ($user['id'] ?? '') . '/activity') ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-10">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search action type, summary, details, table..." value="<?= e($query ?? '') ?>" />
                            <?php if (!empty($query)): ?>
                                <a class="btn btn-outline-secondary" href="<?= !empty($isOwnActivity) ? url('/activity-log') : url('/users/' . ($user['id'] ?? '') . '/activity') ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button class="btn btn-primary" type="submit">Search</button>
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
