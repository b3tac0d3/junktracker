<?php
$task = is_array($task ?? null) ? $task : [];
$taskLink = is_array($taskLink ?? null) ? $taskLink : ['state' => 'empty'];
$title = trim((string) ($task['title'] ?? '')) !== '' ? (string) $task['title'] : ('Task #' . (string) ((int) ($task['id'] ?? 0)));
$status = str_replace('_', ' ', (string) ($task['status'] ?? 'open'));
$statusRaw = strtolower(trim((string) ($task['status'] ?? 'open')));
$isClosed = $statusRaw === 'closed';
$completedByName = trim((string) ($task['completed_by_name'] ?? ''));
$completedAtText = format_datetime((string) ($task['completed_at'] ?? null));
$taskOwnerId = (int) ($task['owner_user_id'] ?? 0);
$currentUserId = (int) (auth_user_id() ?? 0);
$canTakeOwnership = $currentUserId > 0 && $currentUserId !== $taskOwnerId;
?>

<?php $taskId = (int) ($task['id'] ?? 0); ?>
<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1 class="d-flex flex-wrap align-items-center gap-2">
            Task Details
            <?php if ($isClosed): ?>
                <span class="badge text-bg-success fs-6 fw-semibold">Completed</span>
            <?php endif; ?>
        </h1>
        <p class="muted mb-0<?= $isClosed ? ' text-decoration-line-through' : '' ?>"><?= e($title) ?></p>
        <?php if ($isClosed): ?>
            <?php
            $completedMetaParts = [];
            if ($completedByName !== '') {
                $completedMetaParts[] = 'by ' . $completedByName;
            }
            if ($completedAtText !== '—') {
                $completedMetaParts[] = 'on ' . $completedAtText;
            }
            ?>
            <?php if ($completedMetaParts !== []): ?>
                <p class="small text-success mb-0 mt-1">
                    <i class="fas fa-circle-check me-1" aria-hidden="true"></i>Completed <?= e(implode(' ', $completedMetaParts)) ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if (!$isClosed): ?>
                    <li>
                        <form method="post" action="<?= e(url('/tasks/' . (string) $taskId . '/quick-complete')) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="done" value="1" />
                            <button class="dropdown-item text-success" type="submit"><i class="fas fa-check me-2"></i>Quick Complete</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li>
                        <form method="post" action="<?= e(url('/tasks/' . (string) $taskId . '/quick-complete')) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="done" value="0" />
                            <button class="dropdown-item text-warning" type="submit"><i class="fas fa-redo me-2"></i>Reopen</button>
                        </form>
                    </li>
                <?php endif; ?>
                <?php if ($canTakeOwnership): ?>
                    <li>
                        <form method="post" action="<?= e(url('/tasks/' . (string) $taskId . '/take-ownership')) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="fas fa-hand me-2"></i>Take Ownership</button>
                        </form>
                    </li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= e(url('/tasks/' . (string) $taskId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Task</a></li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/tasks')) ?>">Back to Tasks</a>
    </div>
</div>

<section class="card index-card mb-3<?= $isClosed ? ' task-show-card--completed' : '' ?>">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong><i class="fas fa-list-check me-2"></i>Task Details</strong>
        <?php if ($isClosed): ?>
            <span class="badge text-bg-success"><i class="fas fa-check me-1" aria-hidden="true"></i>Done</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($isClosed): ?>
            <div class="task-show-complete-banner" role="status">
                <i class="fas fa-circle-check fa-lg" aria-hidden="true"></i>
                <div>
                    <div class="task-show-complete-banner-title">This task is complete</div>
                    <div class="task-show-complete-banner-meta">
                        <?php
                        $bannerParts = ['Status: Closed'];
                        if ($completedByName !== '') {
                            $bannerParts[] = 'Completed by ' . $completedByName;
                        }
                        if ($completedAtText !== '—') {
                            $bannerParts[] = $completedAtText;
                        }
                        echo e(implode(' · ', $bannerParts));
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="record-row-fields record-row-fields-4">
            <div class="record-field">
                <span class="record-label">Task ID</span>
                <span class="record-value"><?= e((string) ((int) ($task['id'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value">
                    <?php if ($isClosed): ?>
                        <span class="badge text-bg-success text-capitalize"><?= e($status) ?></span>
                    <?php else: ?>
                        <span class="text-capitalize"><?= e($status) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="record-field">
                <span class="record-label">Priority</span>
                <span class="record-value"><?= e((string) ($task['priority'] ?? '—')) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Due</span>
                <span class="record-value"><?= e(format_datetime((string) ($task['due_at'] ?? null))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Completed At</span>
                <span class="record-value"><?= e(format_datetime((string) ($task['completed_at'] ?? null))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Completed By</span>
                <span class="record-value"><?= e(trim((string) ($task['completed_by_name'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Owner</span>
                <span class="record-value"><?= e(trim((string) ($task['owner_name'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Assigned</span>
                <span class="record-value"><?= e(trim((string) ($task['assigned_name'] ?? '')) ?: '—') ?></span>
            </div>
            <?php
            $linkState = (string) ($taskLink['state'] ?? 'empty');
            $jobLink = is_array($taskLink['job'] ?? null) ? $taskLink['job'] : null;
            $purchaseLink = is_array($taskLink['purchase'] ?? null) ? $taskLink['purchase'] : null;
            $clientLink = is_array($taskLink['client'] ?? null) ? $taskLink['client'] : null;
            $linkPhone = trim((string) ($taskLink['phone'] ?? ''));
            $telDigits = $linkPhone !== '' ? preg_replace('/\D+/', '', $linkPhone) : '';
            ?>
            <?php if ($linkState === 'resolved' && $jobLink !== null): ?>
                <div class="record-field">
                    <span class="record-label">Job</span>
                    <span class="record-value">
                        <a href="<?= e((string) ($jobLink['url'] ?? '#')) ?>"><?= e((string) ($jobLink['title'] ?? 'Job')) ?></a>
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($linkState === 'resolved' && $purchaseLink !== null): ?>
                <div class="record-field">
                    <span class="record-label">Purchase</span>
                    <span class="record-value">
                        <a href="<?= e((string) ($purchaseLink['url'] ?? '#')) ?>"><?= e((string) ($purchaseLink['title'] ?? 'Purchase')) ?></a>
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($linkState === 'resolved' && $clientLink !== null): ?>
                <div class="record-field">
                    <span class="record-label">Client</span>
                    <span class="record-value">
                        <a href="<?= e((string) ($clientLink['url'] ?? '#')) ?>"><?= e((string) ($clientLink['name'] ?? 'Client')) ?></a>
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($linkState === 'resolved' && $linkPhone !== ''): ?>
                <div class="record-field">
                    <span class="record-label">Client phone</span>
                    <span class="record-value">
                        <?php $phoneHref = phone_tel_href($linkPhone); ?>
                        <?php if ($phoneHref !== ''): ?>
                            <a href="<?= e($phoneHref) ?>"><?= e(format_phone($linkPhone)) ?></a>
                        <?php else: ?>
                            <?= e(format_phone($linkPhone)) ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php elseif ($linkState === 'resolved' && $clientLink !== null): ?>
                <div class="record-field">
                    <span class="record-label">Client phone</span>
                    <span class="record-value">—</span>
                </div>
            <?php endif; ?>
            <?php if ($linkState === 'missing'): ?>
                <div class="record-field record-field-full">
                    <span class="record-label">Linked record</span>
                    <span class="record-value text-warning">
                        Linked <?= e((string) ($taskLink['link_type'] ?? '')) ?> #<?= e((string) (int) ($taskLink['link_id'] ?? 0)) ?> was not found (it may have been deleted).
                    </span>
                </div>
            <?php elseif ($linkState === 'unknown'): ?>
                <div class="record-field">
                    <span class="record-label">Linked type</span>
                    <span class="record-value"><?= e((string) ($taskLink['link_type'] ?? '—')) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Linked ID</span>
                    <span class="record-value"><?= e((string) (int) ($taskLink['link_id'] ?? 0)) ?></span>
                </div>
            <?php elseif ($linkState === 'empty'): ?>
                <div class="record-field">
                    <span class="record-label">Linked record</span>
                    <span class="record-value">—</span>
                </div>
            <?php endif; ?>
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($task['body'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
