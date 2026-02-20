<?php
    $filters = $filters ?? [];
    $tasks = $tasks ?? [];
    $summary = $summary ?? [];
    $users = $users ?? [];
    $statusOptions = $statusOptions ?? ['open', 'in_progress', 'closed'];
    $linkTypes = $linkTypes ?? ['general'];
    $linkTypeLabels = $linkTypeLabels ?? [];
    $ownerScopes = $ownerScopes ?? ['all', 'mine', 'team'];
    $savedPresets = is_array($savedPresets ?? null) ? $savedPresets : [];
    $selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;
    $filterPresetModule = (string) ($filterPresetModule ?? 'tasks');
    $returnTo = '/tasks';
    $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString !== '') {
        $returnTo .= '?' . $queryString;
    }
    $currentFilters = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? 'open'),
        'importance' => $filters['importance'] ?? '',
        'link_type' => (string) ($filters['link_type'] ?? 'all'),
        'owner_scope' => (string) ($filters['owner_scope'] ?? 'all'),
        'assigned_user_id' => $filters['assigned_user_id'] ?? '',
        'record_status' => (string) ($filters['record_status'] ?? 'active'),
        'due_start' => (string) ($filters['due_start'] ?? ''),
        'due_end' => (string) ($filters['due_end'] ?? ''),
    ];
    $exportParams = array_merge($currentFilters, ['preset_id' => $selectedPresetId > 0 ? (string) $selectedPresetId : '', 'export' => 'csv']);
    $exportParams = array_filter($exportParams, static fn (mixed $value): bool => (string) $value !== '');
    $summaryFilterBase = $currentFilters;
    $summaryFilterBase['status'] = '';
    if ($selectedPresetId > 0) {
        $summaryFilterBase['preset_id'] = (string) $selectedPresetId;
    }
    $summaryFilterUrl = static function (array $base, string $status): string {
        $params = $base;
        $params['status'] = $status;
        $params = array_filter($params, static fn (mixed $value): bool => (string) $value !== '');

        return url('/tasks?' . http_build_query($params));
    };
    $activeStatus = (string) ($filters['status'] ?? 'open');
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Tasks</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Tasks</li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <?php if (($filters['status'] ?? 'all') === 'closed'): ?>
                <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">
                    <i class="fas fa-list-check me-1"></i>
                    View Active
                </a>
            <?php else: ?>
                <a class="btn btn-outline-success" href="<?= url('/tasks?status=closed') ?>">
                    <i class="fas fa-check-circle me-1"></i>
                    View Completed
                </a>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= url('/tasks/new') ?>">
                <i class="fas fa-plus me-1"></i>
                Add Task
            </a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <form method="get" action="<?= url('/tasks') ?>">
                        <label class="form-label">Saved Filters</label>
                        <div class="input-group">
                            <select class="form-select" name="preset_id">
                                <option value="">Choose preset...</option>
                                <?php foreach ($savedPresets as $preset): ?>
                                    <?php $presetId = (int) ($preset['id'] ?? 0); ?>
                                    <option value="<?= e((string) $presetId) ?>" <?= $selectedPresetId === $presetId ? 'selected' : '' ?>>
                                        <?= e((string) ($preset['preset_name'] ?? ('Preset #' . $presetId))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="submit">Load</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">Reset</a>
                        </div>
                    </form>
                </div>
                <div class="col-12 col-lg-4">
                    <form method="post" action="<?= url('/filter-presets/save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                        <input type="hidden" name="filters_json" value='<?= e((string) json_encode($currentFilters)) ?>' />
                        <label class="form-label">Save Current Filters</label>
                        <div class="input-group">
                            <input class="form-control" type="text" name="preset_name" placeholder="Preset name..." />
                            <button class="btn btn-outline-success" type="submit">Save</button>
                        </div>
                    </form>
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                    <?php if ($selectedPresetId > 0): ?>
                        <form method="post" action="<?= url('/filter-presets/' . $selectedPresetId . '/delete') ?>" onsubmit="return confirm('Delete this preset?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                            <input type="hidden" name="return_to" value="<?= e('/tasks') ?>" />
                            <button class="btn btn-outline-danger" type="submit">Delete Preset</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary" href="<?= url('/tasks?' . http_build_query($exportParams)) ?>">
                        <i class="fas fa-file-csv me-1"></i>
                        Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block h-100" href="<?= e($summaryFilterUrl($summaryFilterBase, 'open')) ?>">
            <div class="card border-0 shadow-sm h-100 <?= $activeStatus === 'open' ? 'border border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Open</div>
                    <div class="h4 mb-0"><?= e((string) ((int) ($summary['open_count'] ?? 0))) ?></div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block h-100" href="<?= e($summaryFilterUrl($summaryFilterBase, 'in_progress')) ?>">
            <div class="card border-0 shadow-sm h-100 <?= $activeStatus === 'in_progress' ? 'border border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">In Progress</div>
                    <div class="h4 mb-0 text-primary"><?= e((string) ((int) ($summary['in_progress_count'] ?? 0))) ?></div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block h-100" href="<?= e($summaryFilterUrl($summaryFilterBase, 'overdue')) ?>">
            <div class="card border-0 shadow-sm h-100 <?= $activeStatus === 'overdue' ? 'border border-danger' : '' ?>">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Overdue</div>
                    <div class="h4 mb-0 text-danger"><?= e((string) ((int) ($summary['overdue_count'] ?? 0))) ?></div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a class="text-decoration-none text-reset d-block h-100" href="<?= e($summaryFilterUrl($summaryFilterBase, 'all')) ?>">
            <div class="card border-0 shadow-sm h-100 <?= $activeStatus === 'all' ? 'border border-primary' : '' ?>">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total</div>
                    <div class="h4 mb-0"><?= e((string) ((int) ($summary['total_count'] ?? 0))) ?></div>
                </div>
            </div>
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/tasks') ?>">
                <?php if ($selectedPresetId > 0): ?>
                    <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>" />
                <?php endif; ?>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                class="form-control"
                                type="text"
                                name="q"
                                placeholder="Search title, notes, outcome..."
                                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                            />
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?= ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="overdue" <?= ($filters['status'] ?? '') === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                                    <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-1">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="importance">
                            <option value="">All</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php $value = (string) $i; ?>
                                <option value="<?= e($value) ?>" <?= (string) ($filters['importance'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Link Type</label>
                        <select class="form-select" name="link_type">
                            <option value="all" <?= ($filters['link_type'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($linkTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($filters['link_type'] ?? '') === $type ? 'selected' : '' ?>>
                                    <?= e((string) ($linkTypeLabels[$type] ?? ucwords($type))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Assigned</label>
                        <select class="form-select" name="assigned_user_id">
                            <option value="">All</option>
                            <?php foreach ($users as $user): ?>
                                <?php $id = (string) ((int) ($user['id'] ?? 0)); ?>
                                <option value="<?= e($id) ?>" <?= (string) ($filters['assigned_user_id'] ?? '') === $id ? 'selected' : '' ?>>
                                    <?= e((string) ($user['name'] ?? ('User #' . $id))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Owner Scope</label>
                        <select class="form-select" name="owner_scope">
                            <?php foreach ($ownerScopes as $scope): ?>
                                <option value="<?= e($scope) ?>" <?= (string) ($filters['owner_scope'] ?? 'all') === $scope ? 'selected' : '' ?>>
                                    <?= e(match ($scope) {
                                        'mine' => 'Mine',
                                        'team' => 'Team',
                                        default => 'All',
                                    }) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-1">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Due Start</label>
                        <input class="form-control" type="date" name="due_start" value="<?= e((string) ($filters['due_start'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Due End</label>
                        <input class="form-control" type="date" name="due_end" value="<?= e((string) ($filters['due_end'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list-check me-1"></i>
            Task List
        </div>
        <div class="card-body">
            <?php if (empty($tasks)): ?>
                <div class="text-muted">No tasks found.</div>
            <?php else: ?>
                <table id="tasksTable">
                    <thead>
                        <tr>
                            <th>Done</th>
                            <th>ID</th>
                            <th>Task</th>
                            <th>Linked To</th>
                            <th>Assigned</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Due</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                                $rowHref = url('/tasks/' . (string) ($task['id'] ?? ''));
                                $status = (string) ($task['status'] ?? 'open');
                                $statusClass = match ($status) {
                                    'closed' => 'bg-success',
                                    'in_progress' => 'bg-primary',
                                    default => 'bg-warning text-dark',
                                };
                                $importance = (int) ($task['importance'] ?? 3);
                                $priorityClass = match (true) {
                                    $importance >= 5 => 'bg-danger',
                                    $importance === 4 => 'bg-warning text-dark',
                                    $importance === 3 => 'bg-info text-dark',
                                    default => 'bg-secondary',
                                };
                                $dueAt = (string) ($task['due_at'] ?? '');
                                $dueTs = $dueAt !== '' ? strtotime($dueAt) : false;
                                $isOverdue = $status !== 'closed' && $dueTs !== false && $dueTs < time();
                                $isDueToday = $status !== 'closed' && $dueTs !== false && date('Y-m-d', $dueTs) === date('Y-m-d');
                            ?>
                            <tr data-href="<?= $rowHref ?>" data-task-id="<?= e((string) ($task['id'] ?? '')) ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>">
                                    <form method="post" action="<?= url('/tasks/' . (string) ($task['id'] ?? '') . '/toggle-complete') ?>" class="js-task-toggle-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                                        <input type="hidden" name="is_completed" value="0" />
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="is_completed"
                                            value="1"
                                            <?= $status === 'closed' ? 'checked' : '' ?>
                                            onchange="this.form.submit()"
                                            aria-label="Toggle task completion"
                                        />
                                    </form>
                                </td>
                                <td data-href="<?= $rowHref ?>"><?= e((string) ($task['id'] ?? '')) ?></td>
                                <td>
                                    <a class="text-decoration-none fw-semibold js-task-title <?= $status === 'closed' ? 'text-muted text-decoration-line-through' : '' ?>" href="<?= $rowHref ?>">
                                        <?= e((string) ($task['title'] ?? '')) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($task['link_url'])): ?>
                                        <a class="text-decoration-none" href="<?= url((string) $task['link_url']) ?>">
                                            <?= e((string) ($task['link_label'] ?? '—')) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e((string) ($task['link_label'] ?? '—')) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) (($task['assigned_user_name'] ?? '') !== '' ? $task['assigned_user_name'] : 'Unassigned')) ?></td>
                                <td><span class="badge js-task-status-badge <?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span></td>
                                <td><span class="badge <?= e($priorityClass) ?>">P<?= e((string) $importance) ?></span></td>
                                <td>
                                    <?= e(format_datetime($task['due_at'] ?? null)) ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    <?php elseif ($isDueToday): ?>
                                        <span class="badge bg-warning text-dark ms-1">Due Today</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(format_datetime($task['updated_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
