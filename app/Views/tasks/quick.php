<?php
    $tasks = is_array($tasks ?? null) ? $tasks : [];
    $quickFilters = is_array($quickFilters ?? null) ? $quickFilters : [];
    $statusOptions = is_array($statusOptions ?? null) ? $statusOptions : ['open', 'in_progress', 'closed'];
    $searchValue = trim((string) ($quickFilters['q'] ?? ''));
    $statusValue = (string) ($quickFilters['status'] ?? 'all');
    if (!in_array($statusValue, array_merge(['all'], $statusOptions), true)) {
        $statusValue = 'all';
    }
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Quick Add Tasks</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/tasks') ?>">Tasks</a></li>
                <li class="breadcrumb-item active">Quick Add</li>
            </ol>
        </div>
        <div class="d-flex flex-wrap gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-primary" href="<?= url('/tasks/new/full') ?>">
                <i class="fas fa-sliders-h me-1"></i>Full Task Form
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">Back to Tasks</a>
        </div>
    </div>

    <div id="taskQuickAlert" class="alert d-none" role="alert"></div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

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

            <div class="row g-2 mb-3">
                <div class="col-12 col-md-8">
                    <label class="form-label visually-hidden" for="taskQuickSearch">Search</label>
                    <input
                        class="form-control"
                        id="taskQuickSearch"
                        type="text"
                        value="<?= e($searchValue) ?>"
                        placeholder="Search tasks..."
                    />
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label visually-hidden" for="taskQuickStatus">Status</label>
                    <select class="form-select" id="taskQuickStatus">
                        <option value="all" <?= $statusValue === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <?php foreach ($statusOptions as $statusOption): ?>
                            <option value="<?= e($statusOption) ?>" <?= $statusValue === $statusOption ? 'selected' : '' ?>>
                                <?= e(ucwords(str_replace('_', ' ', $statusOption))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

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
