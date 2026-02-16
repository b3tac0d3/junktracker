<?php
    $filters = $filters ?? [];
    $tasks = $tasks ?? [];
    $summary = $summary ?? [];
    $users = $users ?? [];
    $statusOptions = $statusOptions ?? ['open', 'in_progress', 'closed'];
    $linkTypes = $linkTypes ?? ['general'];
    $linkTypeLabels = $linkTypeLabels ?? [];
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
        <a class="btn btn-primary" href="<?= url('/tasks/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Task
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Open</div>
                    <div class="h4 mb-0"><?= e((string) ((int) ($summary['open_count'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">In Progress</div>
                    <div class="h4 mb-0 text-primary"><?= e((string) ((int) ($summary['in_progress_count'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Overdue</div>
                    <div class="h4 mb-0 text-danger"><?= e((string) ((int) ($summary['overdue_count'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total</div>
                    <div class="h4 mb-0"><?= e((string) ((int) ($summary['total_count'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/tasks') ?>">
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
                            <th>ID</th>
                            <th>Task</th>
                            <th>Linked To</th>
                            <th>Assigned</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Due</th>
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
                            ?>
                            <tr data-href="<?= $rowHref ?>" style="cursor: pointer;">
                                <td data-href="<?= $rowHref ?>"><?= e((string) ($task['id'] ?? '')) ?></td>
                                <td>
                                    <a class="text-decoration-none fw-semibold" href="<?= $rowHref ?>">
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
                                <td><span class="badge <?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span></td>
                                <td><?= e((string) ($task['importance'] ?? 3)) ?></td>
                                <td><?= e(format_datetime($task['due_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
