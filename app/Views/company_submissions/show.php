<?php

use App\Models\DevTrackerItem;

$item = is_array($item ?? null) ? $item : [];
$logEntries = is_array($logEntries ?? null) ? $logEntries : [];
$labels = is_array($labels ?? null) ? $labels : [];
$routePrefix = trim((string) ($routePrefix ?? '/admin/bug-reports'));
$business = is_array($business ?? null) ? $business : [];

$itemId = (int) ($item['id'] ?? 0);
$itemTitle = trim((string) ($item['title'] ?? ''));
$itemType = strtolower(trim((string) ($item['item_type'] ?? '')));
$itemArea = trim((string) ($item['area'] ?? ''));
$itemStatus = trim((string) ($item['status'] ?? ''));
$reviewStatus = trim((string) ($item['review_status'] ?? ''));
$isPending = DevTrackerItem::isPendingSubmission($item);
$reviewBadgeClass = match ($reviewStatus) {
    'accepted' => 'text-bg-success',
    'rejected' => 'text-bg-secondary',
    default => 'text-bg-warning text-dark',
};
$reviewLabel = $reviewStatus !== ''
    ? DevTrackerItem::reviewStatusLabel($reviewStatus)
    : DevTrackerItem::statusLabel($itemStatus);
$businessName = trim((string) ($business['name'] ?? ''));
$canAddUpdates = false;
$createdLogLabel = DevTrackerItem::submissionCreatedLogLabel((string) ($item['item_type'] ?? ''));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1 class="d-flex flex-wrap align-items-center gap-2">
            <?= e($itemTitle) ?>
            <?php if (DevTrackerItem::isCompanySubmissionType($itemType)): ?>
                <span class="badge <?= e($itemType === 'update' ? 'text-bg-info' : 'text-bg-danger') ?> fs-6"><?= e(DevTrackerItem::typeLabel($itemType)) ?></span>
            <?php endif; ?>
            <span class="badge <?= e($reviewBadgeClass) ?> fs-6"><?= e($reviewLabel) ?></span>
        </h1>
        <p class="muted mb-0">
            #<?= e((string) $itemId) ?>
            <?php if ($businessName !== ''): ?> · <?= e($businessName) ?><?php endif; ?>
            <?php if ($itemArea !== ''): ?> · <?= e($itemArea) ?><?php endif; ?>
        </p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url($routePrefix)) ?>">Back</a>
    </div>
</div>

<?php if ($isPending): ?>
<div class="alert alert-info"><?= e((string) ($labels['pending_alert'] ?? '')) ?></div>
<?php elseif ($reviewStatus === 'accepted'): ?>
<div class="alert alert-success">
    <?= e((string) ($labels['accepted_alert'] ?? 'Accepted by devs')) ?> #<?= e((string) $itemId) ?> · current status: <?= e(DevTrackerItem::statusLabel($itemStatus)) ?>.
</div>
<?php elseif ($reviewStatus === 'rejected'): ?>
<div class="alert alert-secondary"><?= e((string) ($labels['rejected_alert'] ?? '')) ?></div>
<?php endif; ?>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-circle-info me-2"></i>Summary</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Review Status</span>
                <span class="record-value"><?= e($reviewLabel) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Dev Status</span>
                <span class="record-value"><?= e(DevTrackerItem::statusLabel($itemStatus)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Last Updated</span>
                <span class="record-value"><?= e(format_datetime((string) ($item['updated_at'] ?? null))) ?></span>
            </div>
        </div>
    </div>
</section>

<?php
$showAddForm = $canAddUpdates;
$addLogUrl = url($routePrefix . '/' . (string) $itemId . '/log');
$createdEntryLabel = $createdLogLabel;
require base_path('app/Views/dev_tracker/_log_timeline.php');
?>
