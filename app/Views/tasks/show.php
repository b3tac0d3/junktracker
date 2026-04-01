<?php
$task = is_array($task ?? null) ? $task : [];
$taskLink = is_array($taskLink ?? null) ? $taskLink : ['state' => 'empty'];
$title = trim((string) ($task['title'] ?? '')) !== '' ? (string) $task['title'] : ('Task #' . (string) ((int) ($task['id'] ?? 0)));
$status = str_replace('_', ' ', (string) ($task['status'] ?? 'open'));
$statusRaw = strtolower(trim((string) ($task['status'] ?? 'open')));
$isClosed = $statusRaw === 'closed';
$taskOwnerId = (int) ($task['owner_user_id'] ?? 0);
$currentUserId = (int) (auth_user_id() ?? 0);
$canTakeOwnership = $currentUserId > 0 && $currentUserId !== $taskOwnerId;
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Task Details</h1>
        <p class="muted"><?= e($title) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$isClosed): ?>
            <form method="post" action="<?= e(url('/tasks/' . (string) ((int) ($task['id'] ?? 0)) . '/quick-complete')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="done" value="1" />
                <button class="btn btn-outline-success" type="submit"><i class="fas fa-check me-2"></i>Quick Complete</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/tasks/' . (string) ((int) ($task['id'] ?? 0)) . '/quick-complete')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="done" value="0" />
                <button class="btn btn-outline-warning" type="submit"><i class="fas fa-redo me-2"></i>Reopen</button>
            </form>
        <?php endif; ?>
        <?php if ($canTakeOwnership): ?>
            <form method="post" action="<?= e(url('/tasks/' . (string) ((int) ($task['id'] ?? 0)) . '/take-ownership')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-hand me-2"></i>Take Ownership</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/tasks/' . (string) ((int) ($task['id'] ?? 0)) . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Task</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/tasks')) ?>">Back to Tasks</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-list-check me-2"></i>Task Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4">
            <div class="record-field">
                <span class="record-label">Task ID</span>
                <span class="record-value"><?= e((string) ((int) ($task['id'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value text-capitalize"><?= e($status) ?></span>
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
                        <?php if ($telDigits !== ''): ?>
                            <a href="<?= e('tel:' . $telDigits) ?>"><?= e(format_phone($linkPhone)) ?></a>
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
