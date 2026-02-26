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
    $activeAdvancedFilterCount = count(array_filter([
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
    <div class="card mb-4 jt-filter-card" data-mobile-filter="false">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeAdvancedFilterCount > 0): ?>
                <span class="badge bg-primary ms-2 rounded-pill"><?= $activeAdvancedFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#tasksAdvancedFilters" aria-expanded="false" aria-controls="tasksAdvancedFilters">
                <i class="fas fa-sliders-h me-1"></i>Advanced Filters
            </button>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label small fw-bold text-muted" for="taskQuickSearch">Search</label>
                    <input
                        class="form-control"
                        id="taskQuickSearch"
                        type="text"
                        value="<?= e((string) ($filters['q'] ?? '')) ?>"
                        placeholder="Search tasks..."
                    />
                </div>
            </div>
            <div class="collapse mt-3" id="tasksAdvancedFilters">
                <form method="get" action="<?= url('/tasks') ?>">
                    <?php if ($selectedPresetId > 0): ?>
                    <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>" />
                    <?php endif; ?>
                    <input type="hidden" id="tasksFilterQ" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" />
                    <div class="row g-3">
                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" id="taskQuickStatus" name="status">
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
                        <div class="col-12 col-lg-3 jt-filter-actions">
                            <label class="form-label small fw-bold text-muted d-block">&nbsp;</label>
                            <div class="d-flex gap-2 mobile-two-col-buttons">
                                <button class="btn btn-primary px-4" type="submit">Apply Filters</button>
                                <a class="btn btn-outline-secondary px-4" href="<?= url('/tasks') ?>">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="filter-presets-section border-top mt-4 pt-3">
                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <form method="get" action="<?= url('/tasks') ?>">
                                <input type="hidden" name="q" id="tasksPresetLoadQ" value="<?= e((string) ($filters['q'] ?? '')) ?>" />
                                <label class="form-label small fw-bold text-muted">Saved Filters</label>
                                <div class="input-group jt-preset-input-group">
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
                                <div class="input-group jt-preset-input-group">
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
    <div id="taskQuickAlert" class="alert d-none" role="alert"></div>

    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div><i class="fas fa-list-check me-1"></i>Task Quick Add</div>
            <span class="small text-muted">Save fast, open later for details</span>
        </div>
        <div class="card-body">
            <form id="taskQuickAddForm" class="row g-2 mb-4" method="post" action="<?= url('/tasks/new') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ajax" value="1" />
                <div class="col-12 col-md-9">
                    <label class="form-label visually-hidden" for="taskQuickTitle">Task</label>
                    <input
                        class="form-control"
                        id="taskQuickTitle"
                        name="title"
                        type="text"
                        maxlength="255"
                        placeholder="Add a task..."
                        required
                    />
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-primary" type="submit" id="taskQuickAddBtn">
                        <i class="fas fa-plus me-1"></i>Add Task
                    </button>
                </div>
            </form>

            <ul
                class="list-group task-quick-list"
                id="taskQuickList"
                data-empty-text="No tasks match your filters."
                data-task-url-template="<?= e(url('/tasks/__ID__')) ?>"
                data-toggle-url-template="<?= e(url('/tasks/__ID__/toggle-complete')) ?>"
            >
                <?php foreach ($tasks as $task): ?>
                    <?php
                        $taskId = (int) ($task['id'] ?? 0);
                        $taskTitle = trim((string) ($task['title'] ?? ''));
                        if ($taskTitle === '') {
                            $taskTitle = 'Task #' . $taskId;
                        }
                        $taskStatus = (string) ($task['status'] ?? 'open');
                        $isClosed = $taskStatus === 'closed';
                    ?>
                    <li class="list-group-item task-quick-item<?= $isClosed ? ' is-complete' : '' ?>" data-task-id="<?= e((string) $taskId) ?>" data-title="<?= e(strtolower($taskTitle)) ?>" data-status="<?= e($taskStatus) ?>">
                        <div class="d-flex align-items-start gap-3">
                            <input
                                class="form-check-input mt-1 task-quick-toggle"
                                type="checkbox"
                                <?= $isClosed ? 'checked' : '' ?>
                                data-id="<?= e((string) $taskId) ?>"
                                data-toggle-url="<?= e(url('/tasks/' . $taskId . '/toggle-complete')) ?>"
                            />
                            <div class="min-w-0">
                                <a class="task-quick-title text-decoration-none<?= $isClosed ? ' text-muted text-decoration-line-through' : '' ?>" href="<?= url('/tasks/' . $taskId) ?>">
                                    <?= e($taskTitle) ?>
                                </a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
