<?php

use App\Models\DevTrackerItem;

$item = is_array($item ?? null) ? $item : [];
$logEntries = is_array($logEntries ?? null) ? $logEntries : [];
$business = is_array($business ?? null) ? $business : null;
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : DevTrackerItem::devStatusOptions();

$itemId = (int) ($item['id'] ?? 0);
$itemType = trim((string) ($item['item_type'] ?? ''));
$itemStatus = trim((string) ($item['status'] ?? ''));
$itemPriority = trim((string) ($item['priority'] ?? ''));
$itemTitle = trim((string) ($item['title'] ?? ''));
$itemArea = trim((string) ($item['area'] ?? ''));
$itemNotes = trim((string) ($item['notes'] ?? ''));
$reviewStatus = trim((string) ($item['review_status'] ?? ''));
$isPendingSubmission = DevTrackerItem::isPendingSubmission($item);
$updatedDisplay = format_datetime((string) ($item['updated_at'] ?? null));
$createdDisplay = format_datetime((string) ($item['created_at'] ?? null));
$businessName = is_array($business) ? trim((string) ($business['name'] ?? '')) : '';
$createdEntryLabel = DevTrackerItem::submissionCreatedLogLabel($itemType);
$isUpdateSubmission = $itemType === 'update';
$reviewHeading = DevTrackerItem::submissionReviewHeading($item);
$defaultAcceptStatus = DevTrackerItem::defaultAcceptStatusForSubmission($item);
$acceptButtonLabel = $isUpdateSubmission ? 'Accept for Release' : 'Accept Bug';
$acceptCardTitle = $isUpdateSubmission ? 'Accept update request' : 'Accept as bug';
$acceptCardDesc = $isUpdateSubmission
    ? 'This update was requested by a company admin and must be accepted before it is scheduled for a future release.'
    : 'This bug was reported by a company admin and must be accepted before it enters the dev workflow.';
$rejectCardTitle = $isUpdateSubmission ? 'Decline update request' : 'Decline bug';
$declineButtonLabel = $isUpdateSubmission ? 'Decline Request' : 'Decline Bug';

$submissionScreenshots = [];
foreach ($logEntries as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $screenshotUrl = dev_tracker_screenshot_url((string) ($entry['screenshot_path'] ?? ''));
    if ($screenshotUrl === null) {
        continue;
    }
    $entryType = trim((string) ($entry['entry_type'] ?? 'comment'));
    $submissionScreenshots[] = [
        'url' => $screenshotUrl,
        'label' => $entryType === 'created' ? $createdEntryLabel : \App\Models\DevTrackerLog::entryTypeLabel($entryType),
    ];
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1 class="d-flex flex-wrap align-items-center gap-2">
            <?= e($itemTitle) ?>
            <span class="badge text-bg-light text-dark border fs-6"><?= e(DevTrackerItem::typeLabel($itemType)) ?></span>
            <?php if ($isPendingSubmission): ?>
                <span class="badge text-bg-warning text-dark fs-6">Pending Review</span>
            <?php endif; ?>
        </h1>
        <p class="muted mb-0">
            #<?= e((string) $itemId) ?>
            <?php if ($itemArea !== ''): ?> · <?= e($itemArea) ?><?php endif; ?>
            <?php if ($businessName !== ''): ?> · <?= e($businessName) ?><?php endif; ?>
        </p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if (!$isPendingSubmission): ?>
                    <li><a class="dropdown-item" href="<?= e(url('/dev/' . (string) $itemId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a></li>
                <?php endif; ?>
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

<?php if ($isPendingSubmission): ?>
<section class="card index-card mb-3 border-warning">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-inbox me-2"></i><?= e($reviewHeading) ?></strong>
    </div>
    <div class="card-body">
        <p class="mb-3"><?= e($acceptCardDesc) ?></p>

        <div class="jt-dev-review-screenshots mb-4">
            <div class="fw-semibold mb-2"><i class="fas fa-image me-2"></i>Submitted Screenshot<?= count($submissionScreenshots) === 1 ? '' : 's' ?></div>
            <?php if ($submissionScreenshots === []): ?>
                <div class="record-empty py-3">No screenshot attached to this submission.</div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-3 align-items-start">
                    <?php foreach ($submissionScreenshots as $screenshotRow): ?>
                        <div class="jt-dev-review-screenshot-card">
                            <?php if (($screenshotRow['label'] ?? '') !== ''): ?>
                                <div class="small muted mb-2"><?= e((string) $screenshotRow['label']) ?></div>
                            <?php endif; ?>
                            <?php
                            $url = (string) $screenshotRow['url'];
                            $caption = (string) ($screenshotRow['label'] ?? 'Submitted screenshot');
                            $alt = 'Submitted screenshot';
                            require base_path('app/Views/components/screenshot_thumbnail.php');
                            ?>
                            <div class="small muted mt-2">Click to enlarge</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <form method="post" action="<?= e(url('/dev/' . (string) $itemId . '/accept-submission')) ?>" class="card h-100 border-success">
                    <div class="card-body">
                        <h3 class="h6"><?= e($acceptCardTitle) ?></h3>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="accept-status">Initial dev status</label>
                            <select id="accept-status" name="status" class="form-select">
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>" <?= $statusOption === $defaultAcceptStatus ? 'selected' : '' ?>><?= e(DevTrackerItem::statusLabel($statusOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="accept-body">Note</label>
                            <textarea id="accept-body" name="body" class="form-control" rows="3" placeholder="Optional note for the activity log..."></textarea>
                        </div>
                        <button class="btn btn-success w-100" type="submit"><?= e($acceptButtonLabel) ?></button>
                    </div>
                </form>
            </div>
            <div class="col-12 col-xl-6">
                <form method="post" action="<?= e(url('/dev/' . (string) $itemId . '/reject-submission')) ?>" class="card h-100 border-danger">
                    <div class="card-body">
                        <h3 class="h6"><?= e($rejectCardTitle) ?></h3>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="reject-body">Reason</label>
                            <textarea id="reject-body" name="body" class="form-control" rows="3" placeholder="Explain why this is not being tracked as a bug..." required></textarea>
                        </div>
                        <button class="btn btn-outline-danger w-100" type="submit"><?= e($declineButtonLabel) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-traffic-light me-2"></i>Progress</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/dev/' . (string) $itemId . '/quick-status')) ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e(url('/dev/' . (string) $itemId)) ?>">
            <div class="col-12 col-md-3">
                <label class="form-label fw-semibold" for="dev-quick-status">Status</label>
                <select id="dev-quick-status" name="status" class="form-select">
                    <?php foreach ($statusOptions as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $itemStatus === $statusOption ? 'selected' : '' ?>><?= e(DevTrackerItem::statusLabel($statusOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label fw-semibold" for="dev-status-note">Update note</label>
                <input id="dev-status-note" type="text" name="body" class="form-control" placeholder="Optional note for the activity log..." />
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label fw-semibold" for="dev-status-screenshot">Screenshot</label>
                <input id="dev-status-screenshot" type="file" name="screenshot" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" />
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100" type="submit">Update</button>
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
<?php endif; ?>

<?php if ($itemNotes !== ''): ?>
<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-sticky-note me-2"></i>Current Notes</strong>
        <?php if (!$isPendingSubmission): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/dev/' . (string) $itemId . '/edit')) ?>">Edit Details</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="dev-tracker-notes"><?= nl2br(e($itemNotes)) ?></div>
    </div>
</section>
<?php endif; ?>

<?php
$showAddForm = true;
$addLogUrl = url('/dev/' . (string) $itemId . '/log');
require base_path('app/Views/dev_tracker/_log_timeline.php');
?>