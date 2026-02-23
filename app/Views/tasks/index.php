<?php
    $filters = $filters ?? [];
    $tasks = $tasks ?? [];
    $users = $users ?? [];
    $statusOptions = $statusOptions ?? ['open', 'in_progress', 'closed'];
    $assignmentOptions = $assignmentOptions ?? ['all', 'pending', 'accepted', 'declined', 'unassigned'];
    $linkTypes = $linkTypes ?? ['general'];
    $linkTypeLabels = $linkTypeLabels ?? [];
    $ownerScopes = $ownerScopes ?? ['all', 'mine', 'team'];
    $savedPresets = is_array($savedPresets ?? null) ? $savedPresets : [];
    $selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;
    $filterPresetModule = (string) ($filterPresetModule ?? 'tasks');
    $currentPath = '/tasks';
    $currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
    $currentFilters = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? 'open'),
        'assignment_status' => (string) ($filters['assignment_status'] ?? 'all'),
        'importance' => $filters['importance'] ?? '',
        'link_type' => (string) ($filters['link_type'] ?? 'all'),
        'owner_scope' => (string) ($filters['owner_scope'] ?? 'all'),
        'assigned_user_id' => $filters['assigned_user_id'] ?? '',
        'record_status' => (string) ($filters['record_status'] ?? 'active'),
        'due_start' => (string) ($filters['due_start'] ?? ''),
        'due_end' => (string) ($filters['due_end'] ?? ''),
    ];
    $activeFilterCount = count(array_filter([
        $currentFilters['q'] !== '',
        $currentFilters['status'] !== 'open',
        $currentFilters['assignment_status'] !== 'all',
        $currentFilters['importance'] !== '',
        $currentFilters['link_type'] !== 'all',
        $currentFilters['owner_scope'] !== 'all',
        $currentFilters['assigned_user_id'] !== '',
        $currentFilters['record_status'] !== 'active',
        $currentFilters['due_start'] !== '',
        $currentFilters['due_end'] !== '',
    ]));
?><div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Tasks</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Tasks</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
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
    <!-- Filter card -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2" data-bs-toggle="collapse" data-bs-target="#tasksFilterCollapse" aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="tasksFilterCollapse" style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="tasksFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/tasks') ?>">
                    <?php if ($selectedPresetId > 0): ?>
                    <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>" />
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label small fw-bold text-muted">Search</label>
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
                            <label class="form-label small fw-bold text-muted">Status</label>
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
                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Assignment</label>
                            <select class="form-select" name="assignment_status">
                                <?php foreach ($assignmentOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= ($filters['assignment_status'] ?? 'all') === $option ? 'selected' : '' ?>>
                                    <?= e(match ($option) {
                                        'all' => 'All',
                                        'pending' => 'Pending Response',
                                        'accepted' => 'Accepted',
                                        'declined' => 'Declined',
                                        'unassigned' => 'Unassigned',
                                        default => ucwords(str_replace('_', ' ', $option)),
                                    }) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-1">
                            <label class="form-label small fw-bold text-muted">Priority</label>
                            <select class="form-select" name="importance">
                                <option value="">All</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php $value = (string) $i; ?>
                                <option value="<?= e($value) ?>" <?= (string) ($filters['importance'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Link Type</label>
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
                            <label class="form-label small fw-bold text-muted">Assigned</label>
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
                            <label class="form-label small fw-bold text-muted">Owner Scope</label>
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
                            <label class="form-label small fw-bold text-muted">Record</label>
                            <select class="form-select" name="record_status">
                                <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                                <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Due Start</label>
                            <input class="form-control" type="date" name="due_start" value="<?= e((string) ($filters['due_start'] ?? '')) ?>" />
                        </div>
                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Due End</label>
                            <input class="form-control" type="date" name="due_end" value="<?= e((string) ($filters['due_end'] ?? '')) ?>" />
                        </div>
                        <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">Clear</a>
                        </div>
                    </div>
                </form>
                <div class="filter-presets-section border-top mt-4 pt-3">
                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <form method="get" action="<?= url('/tasks') ?>">
                                <label class="form-label small fw-bold text-muted">Saved Filters</label>
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
                                <input type="hidden" name="return_to" value="<?= e($currentReturnTo) ?>" />
                                <input type="hidden" name="filters_json" value='<?= e((string) json_encode($currentFilters)) ?>' />
                                <label class="form-label small fw-bold text-muted">Save Current Filters</label>
                                <div class="input-group">
                                    <input class="form-control" type="text" name="preset_name" placeholder="Preset name..." />
                                    <button class="btn btn-outline-success" type="submit">Save</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end mobile-two-col-buttons">
                            <?php if ($selectedPresetId > 0): ?>
                            <form method="post" action="<?= url('/filter-presets/' . $selectedPresetId . '/delete') ?>" onsubmit="return confirm('Delete this preset?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                                <input type="hidden" name="return_to" value="<?= e('/tasks') ?>" />
                                <button class="btn btn-outline-danger" type="submit">Delete Preset</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Task list card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="fas fa-list-check me-1"></i>
                <span class="fw-semibold">Task List</span>
            </div>
            <?php if (!empty($tasks)): ?>
            <span class="badge bg-secondary rounded-pill"><?= count($tasks) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tasks)): ?>
            <div class="jt-empty-state py-5 text-center">
                <div class="empty-icon-wrapper bg-3 mb-3">
                    <i class="fas fa-list-check fa-3x text-light-subtle"></i>
                </div>
                <h5 class="text-muted fw-normal">No tasks found</h5>
                <p class="text-muted small mb-4">Try adjusting your filters or add a new task.</p>
                <a href="<?= url('/tasks/new') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i> Add Task
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
            <table id="tasksTable" class="table table-hover align-middle mb-0 js-card-list-source">
                <thead class="table-light">
                    <tr>
                        <th>Done</th>
                        <th>ID</th>
                        <th>Task</th>
                        <th>Linked To</th>
                        <th>Assigned</th>
                        <th>Assignment</th>
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
                        $assignmentStatus = strtolower((string) ($task['assignment_status'] ?? 'unassigned'));
                        $assignmentBadgeClass = match ($assignmentStatus) {
                            'pending' => 'bg-warning text-dark',
                            'accepted' => 'bg-success',
                            'declined' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        $assignmentLabel = match ($assignmentStatus) {
                            'pending' => 'Pending',
                            'accepted' => 'Accepted',
                            'declined' => 'Declined',
                            default => 'Unassigned',
                        };
                    ?>
                    <tr data-href="<?= $rowHref ?>" data-task-id="<?= e((string) ($task['id'] ?? '')) ?>" style="cursor: pointer;">
                        <td data-href="<?= $rowHref ?>">
                            <form method="post" action="<?= url('/tasks/' . (string) ($task['id'] ?? '') . '/toggle-complete') ?>" class="js-task-toggle-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($currentReturnTo) ?>" />
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
                        <td><span class="badge <?= e($assignmentBadgeClass) ?>"><?= e($assignmentLabel) ?></span></td>
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
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
