<?php
    $task = $task ?? [];
    $linkTypeLabels = $linkTypeLabels ?? [];
    $status = (string) ($task['status'] ?? 'open');
    $statusClass = match ($status) {
        'closed' => 'bg-success',
        'in_progress' => 'bg-primary',
        default => 'bg-warning text-dark',
    };
    $isActive = empty($task['deleted_at']);
    $isCompleted = $status === 'closed';
    $returnTo = '/tasks/' . (string) ($task['id'] ?? '');
    $importance = (int) ($task['importance'] ?? 3);
    $priorityClass = match (true) {
        $importance >= 5 => 'bg-danger',
        $importance === 4 => 'bg-warning text-dark',
        $importance === 3 => 'bg-info text-dark',
        default => 'bg-secondary',
    };
    $assignmentStatus = strtolower((string) ($task['assignment_status'] ?? 'unassigned'));
    $assignmentBadgeClass = match ($assignmentStatus) {
        'pending' => 'bg-warning text-dark',
        'accepted' => 'bg-success',
        'declined' => 'bg-danger',
        default => 'bg-secondary',
    };
    $assignmentLabel = match ($assignmentStatus) {
        'pending' => 'Pending Response',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        default => 'Unassigned',
    };
    $canRespondAssignment = !empty($canRespondAssignment);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Task Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/tasks') ?>">Tasks</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($task['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <?php if ($isActive && $canRespondAssignment): ?>
                <form method="post" action="<?= url('/tasks/' . ($task['id'] ?? '') . '/assignment/respond') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                    <input type="hidden" name="decision" value="accept" />
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check me-1"></i>
                        Accept
                    </button>
                </form>
                <form method="post" action="<?= url('/tasks/' . ($task['id'] ?? '') . '/assignment/respond') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                    <input type="hidden" name="decision" value="decline" />
                    <button class="btn btn-outline-danger" type="submit">
                        <i class="fas fa-xmark me-1"></i>
                        Decline
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($isActive): ?>
                <form method="post" action="<?= url('/tasks/' . ($task['id'] ?? '') . '/toggle-complete') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                    <input type="hidden" name="is_completed" value="<?= $isCompleted ? '0' : '1' ?>" />
                    <button class="btn <?= $isCompleted ? 'btn-outline-success' : 'btn-success' ?>" type="submit">
                        <i class="fas fa-check-circle me-1"></i>
                        <?= $isCompleted ? 'Mark Active' : 'Mark Complete' ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($isActive): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteTaskModal">
                    <i class="fas fa-trash me-1"></i>
                    Delete
                </button>
            <?php endif; ?>
            <a class="btn btn-warning" href="<?= url('/tasks/' . ($task['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Task
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>">Back to Tasks</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list-check me-1"></i>
            Task
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Title</div>
                    <div class="fw-semibold <?= $isCompleted ? 'text-muted text-decoration-line-through' : '' ?>"><?= e((string) ($task['title'] ?? '')) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold"><span class="badge <?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Priority</div>
                    <div class="fw-semibold"><span class="badge <?= e($priorityClass) ?>">P<?= e((string) $importance) ?></span></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Completed</div>
                    <div class="fw-semibold">
                        <?php if ($isActive): ?>
                            <form method="post" action="<?= url('/tasks/' . ($task['id'] ?? '') . '/toggle-complete') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                                <input type="hidden" name="is_completed" value="0" />
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_completed"
                                    value="1"
                                    <?= $isCompleted ? 'checked' : '' ?>
                                    onchange="this.form.submit()"
                                />
                            </form>
                        <?php else: ?>
                            <?= $isCompleted ? 'Yes' : 'No' ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Assigned To</div>
                    <div class="fw-semibold"><?= e((string) (($task['assigned_user_name'] ?? '') !== '' ? $task['assigned_user_name'] : 'Unassigned')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Assignment Status</div>
                    <div class="fw-semibold"><span class="badge <?= e($assignmentBadgeClass) ?>"><?= e($assignmentLabel) ?></span></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Due At</div>
                    <div class="fw-semibold"><?= e(format_datetime($task['due_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Completed At</div>
                    <div class="fw-semibold"><?= e(format_datetime($task['completed_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Assigned By</div>
                    <div class="fw-semibold"><?= e((string) (($task['assignment_requested_by_name'] ?? '') !== '' ? $task['assignment_requested_by_name'] : '—')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Requested At</div>
                    <div class="fw-semibold"><?= e(format_datetime($task['assignment_requested_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Responded At</div>
                    <div class="fw-semibold"><?= e(format_datetime($task['assignment_responded_at'] ?? null)) ?></div>
                </div>
                <?php if (trim((string) ($task['assignment_note'] ?? '')) !== ''): ?>
                    <div class="col-12">
                        <div class="text-muted small">Assignment Note</div>
                        <div class="fw-semibold"><?= e((string) ($task['assignment_note'] ?? '')) ?></div>
                    </div>
                <?php endif; ?>

                <div class="col-md-3">
                    <div class="text-muted small">Link Type</div>
                    <div class="fw-semibold"><?= e((string) ($task['link_type_label'] ?? ($linkTypeLabels[$task['link_type'] ?? 'general'] ?? 'General'))) ?></div>
                </div>
                <div class="col-md-9">
                    <div class="text-muted small">Linked Record</div>
                    <div class="fw-semibold">
                        <?php if (!empty($task['link_url'])): ?>
                            <a class="text-decoration-none" href="<?= url((string) $task['link_url']) ?>">
                                <?= e((string) ($task['link_label'] ?? '—')) ?>
                            </a>
                        <?php else: ?>
                            <?= e((string) ($task['link_label'] ?? '—')) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12">
                    <div class="text-muted small">Task Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($task['body'] ?? '') !== '' ? $task['body'] : '—')) ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Outcome</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($task['outcome'] ?? '') !== '' ? $task['outcome'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list me-1"></i>
            Activity Log
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($task['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($task['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($task['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($task['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($task['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($task['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($task['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isActive): ?>
        <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteTaskModalLabel">Delete Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will deactivate the task and hide it from active lists. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/tasks/' . ($task['id'] ?? '') . '/delete') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Delete Task</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
