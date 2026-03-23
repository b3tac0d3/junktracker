<?php
$expense = is_array($expense ?? null) ? $expense : [];

$expenseId = (int) ($expense['id'] ?? 0);
$dateRaw = trim((string) ($expense['expense_date'] ?? ''));
$dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
$dateDisplay = $dateTs === false ? '—' : date('m/d/Y', $dateTs);
$jobId = (int) ($expense['job_id'] ?? 0);
$jobTitle = trim((string) ($expense['job_title'] ?? ''));
if ($jobTitle === '' && $jobId > 0) {
    $jobTitle = 'Job #' . (string) $jobId;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Expense #<?= e((string) $expenseId) ?></h1>
        <p class="muted"><?= e($jobId > 0 ? 'Job Expense' : 'General Expense') ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($jobId <= 0): ?>
            <a class="btn btn-primary" href="<?= e(url('/expenses/' . (string) $expenseId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a>
            <form method="post" action="<?= e(url('/expenses/' . (string) $expenseId . '/delete')) ?>" onsubmit="return confirm('Delete this expense?');">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
            </form>
        <?php endif; ?>
        <?php if ($jobId > 0): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId . '/edit')) ?>">Edit in Job</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">View Job</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/expenses')) ?>">Back to Expenses</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-receipt me-2"></i>Expense Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Date</span>
                <span class="record-value"><?= e($dateDisplay) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Amount</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($expense['amount'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Category</span>
                <span class="record-value"><?= e(trim((string) ($expense['category'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Scope</span>
                <span class="record-value"><?= e($jobId > 0 ? 'Job' : 'General') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Payment Method</span>
                <span class="record-value"><?= e(trim((string) ($expense['payment_method'] ?? '')) ?: '—') ?></span>
            </div>
        </div>

        <div class="record-row-fields mt-3">
            <div class="record-field">
                <span class="record-label">Job</span>
                <span class="record-value">
                    <?php if ($jobId > 0): ?>
                        <a href="<?= e(url('/jobs/' . (string) $jobId)) ?>"><?= e($jobTitle) ?></a>
                    <?php else: ?>
                        General Expense
                    <?php endif; ?>
                </span>
            </div>
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($expense['note'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
