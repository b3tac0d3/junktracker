<?php
$job = is_array($job ?? null) ? $job : [];
$adjustment = is_array($adjustment ?? null) ? $adjustment : [];

$jobId = (int) ($job['id'] ?? 0);
$adjustmentId = (int) ($adjustment['id'] ?? 0);
$jobTitle = trim((string) ($job['title'] ?? ''));
if ($jobTitle === '') {
    $jobTitle = 'Job #' . (string) $jobId;
}
$dateRaw = trim((string) ($adjustment['adjustment_date'] ?? ''));
$dateStamp = $dateRaw !== '' ? strtotime($dateRaw) : false;
$dateDisplay = $dateStamp === false ? '—' : date('m/d/Y', $dateStamp);
$adjustmentName = trim((string) ($adjustment['name'] ?? ''));
if ($adjustmentName === '') {
    $adjustmentName = 'Adjustment #' . (string) $adjustmentId;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($adjustmentName) ?></h1>
        <p class="muted"><?= e($jobTitle) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a>
        <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId . '/delete')) ?>" onsubmit="return confirm('Delete this adjustment?');">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">Back to Job</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-sliders-h me-2"></i>Adjustment Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Name</span>
                <span class="record-value"><?= e($adjustmentName) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Date</span>
                <span class="record-value"><?= e($dateDisplay) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Amount</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($adjustment['amount'] ?? 0), 2)) ?></span>
            </div>
        </div>

        <div class="record-row-fields mt-3">
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($adjustment['note'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
