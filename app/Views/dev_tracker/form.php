<?php

use App\Models\DevTrackerItem;

$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$itemId = (int) ($itemId ?? 0);
$actionUrl = (string) ($actionUrl ?? url('/dev'));
$cancelUrl = $mode === 'edit' && $itemId > 0 ? url('/dev/' . (string) $itemId) : url('/dev');

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Dev Item' : 'Add Dev Item') ?></h1>
        <p class="muted">Capture bugs, shipped updates, features, or scratch notes.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>"><?= e($mode === 'edit' ? 'Back to Item' : 'Back to Dev Tracker') ?></a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-clipboard-list me-2"></i>Details</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="dev-title">Title</label>
                <input
                    id="dev-title"
                    type="text"
                    name="title"
                    class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['title'] ?? '')) ?>"
                    maxlength="200"
                    required
                />
                <?php if ($hasError('title')): ?><div class="invalid-feedback d-block"><?= e($fieldError('title')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="dev-area">Area</label>
                <input
                    id="dev-area"
                    type="text"
                    name="area"
                    class="form-control"
                    value="<?= e((string) ($form['area'] ?? '')) ?>"
                    maxlength="80"
                    placeholder="jobs, billing, mobile..."
                />
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="dev-type">Type</label>
                <select id="dev-type" name="item_type" class="form-select <?= $hasError('item_type') ? 'is-invalid' : '' ?>">
                    <?php foreach (DevTrackerItem::typeOptions() as $typeOption): ?>
                        <option value="<?= e($typeOption) ?>" <?= strcasecmp((string) ($form['item_type'] ?? ''), $typeOption) === 0 ? 'selected' : '' ?>><?= e(DevTrackerItem::typeLabel($typeOption)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('item_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('item_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="dev-status">Status</label>
                <select id="dev-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <?php foreach (DevTrackerItem::statusOptions() as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= strcasecmp((string) ($form['status'] ?? ''), $statusOption) === 0 ? 'selected' : '' ?>><?= e(DevTrackerItem::statusLabel($statusOption)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="dev-priority">Priority</label>
                <select id="dev-priority" name="priority" class="form-select <?= $hasError('priority') ? 'is-invalid' : '' ?>">
                    <?php foreach (DevTrackerItem::priorityOptions() as $priorityOption): ?>
                        <option value="<?= e($priorityOption) ?>" <?= strcasecmp((string) ($form['priority'] ?? ''), $priorityOption) === 0 ? 'selected' : '' ?>><?= e(DevTrackerItem::priorityLabel($priorityOption)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('priority')): ?><div class="invalid-feedback d-block"><?= e($fieldError('priority')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="dev-notes">Notes</label>
                <textarea id="dev-notes" name="notes" class="form-control" rows="12" placeholder="Steps to reproduce, progress updates, release notes, links..."><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                <div class="form-text">Use this as your running log — add timestamps or bullets as you go.</div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Save Item') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
