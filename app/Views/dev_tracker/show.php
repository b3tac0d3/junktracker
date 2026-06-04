<?php

use App\Models\DevTrackerItem;

$item = is_array($item ?? null) ? $item : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : DevTrackerItem::statusOptions();

$itemId = (int) ($item['id'] ?? 0);
$itemType = trim((string) ($item['item_type'] ?? ''));
$itemStatus = trim((string) ($item['status'] ?? ''));
$itemPriority = trim((string) ($item['priority'] ?? ''));
$itemTitle = trim((string) ($item['title'] ?? ''));
$itemArea = trim((string) ($item['area'] ?? ''));
$itemNotes = trim((string) ($item['notes'] ?? ''));
$updatedDisplay = format_datetime((string) ($item['updated_at'] ?? null));
$createdDisplay = format_datetime((string) ($item['created_at'] ?? null));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1 class="d-flex flex-wrap align-items-center gap-2">
            <?= e($itemTitle) ?>
            <span class="badge text-bg-light text-dark border fs-6"><?= e(DevTrackerItem::typeLabel($itemType)) ?></span>
        </h1>
        <p class="muted mb-0">#<?= e((string) $itemId) ?><?= $itemArea !== '' ? ' · ' . e($itemArea) : '' ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('/dev/' . (string) $itemId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a></li>
                <li>
                    <form method="post" action="<?= e(url('/dev/' . (string) $itemId . '/delete')) ?>" class="m-0" onsubmit="return confirm('Remove this dev item?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/dev')) ?>">Back to Dev Tracker</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-traffic-light me-2"></i>Progress</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/dev/' . (string) $itemId . '/quick-status')) ?>" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e(url('/dev/' . (string) $itemId)) ?>">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="dev-quick-status">Status</label>
                <select id="dev-quick-status" name="status" class="form-select">
                    <?php foreach ($statusOptions as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $itemStatus === $statusOption ? 'selected' : '' ?>><?= e(DevTrackerItem::statusLabel($statusOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100" type="submit">Update Status</button>
            </div>
        </form>

        <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2 mt-4">
            <div class="record-field">
                <span class="record-label">Priority</span>
                <span class="record-value"><?= e(DevTrackerItem::priorityLabel($itemPriority)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value"><?= e(DevTrackerItem::statusLabel($itemStatus)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Updated</span>
                <span class="record-value"><?= e($updatedDisplay) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Created</span>
                <span class="record-value"><?= e($createdDisplay) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-sticky-note me-2"></i>Notes</strong>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/dev/' . (string) $itemId . '/edit')) ?>">Edit Notes</a>
    </div>
    <div class="card-body">
        <?php if ($itemNotes === ''): ?>
            <div class="record-empty">No notes yet. Edit this item to start your running log.</div>
        <?php else: ?>
            <div class="dev-tracker-notes"><?= nl2br(e($itemNotes)) ?></div>
        <?php endif; ?>
    </div>
</section>

<style>
.dev-tracker-notes {
    white-space: pre-wrap;
    word-break: break-word;
    font-family: inherit;
    line-height: 1.5;
}
</style>
