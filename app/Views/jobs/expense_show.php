<?php

use App\Models\Expense;

$job = is_array($job ?? null) ? $job : [];
$expense = is_array($expense ?? null) ? $expense : [];

$jobId = (int) ($job['id'] ?? 0);
$expenseId = (int) ($expense['id'] ?? 0);
$jobTitle = trim((string) ($job['title'] ?? ''));
if ($jobTitle === '') {
    $jobTitle = 'Job #' . (string) $jobId;
}
$expenseDateRaw = trim((string) ($expense['expense_date'] ?? ''));
$expenseDateStamp = $expenseDateRaw !== '' ? strtotime($expenseDateRaw) : false;
$expenseDateDisplay = $expenseDateStamp === false ? '—' : date('m/d/Y', $expenseDateStamp);
$expenseCategory = trim((string) ($expense['category'] ?? ''));
$showDisposalWeight = Expense::isDisposalCategory($expenseCategory);
$disposalWeightDisplay = $showDisposalWeight
    ? (Expense::formatWeightDisplay($expense['weight'] ?? null) ?: '—')
    : '';
$showBonusEmployee = Expense::isBonusCategory($expenseCategory);
$bonusEmployeeName = trim((string) ($expense['employee_name'] ?? ''));
if ($bonusEmployeeName === '' && (int) ($expense['employee_id'] ?? 0) > 0) {
    $bonusEmployeeName = 'Employee #' . (string) ((int) $expense['employee_id']);
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Expense #<?= e((string) $expenseId) ?></h1>
        <p class="muted"><?= e($jobTitle) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit</a></li>
                <li>
                    <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId . '/delete')) ?>" class="m-0" onsubmit="return confirm('Delete this expense?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">Back to Job</a>
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
                <span class="record-value"><?= e($expenseDateDisplay) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Amount</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($expense['amount'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Category</span>
                <span class="record-value"><?= e($expenseCategory !== '' ? $expenseCategory : '—') ?></span>
            </div>
            <?php if ($showDisposalWeight): ?>
            <div class="record-field">
                <span class="record-label">Weight</span>
                <span class="record-value fw-bold"><?= e($disposalWeightDisplay) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($showBonusEmployee): ?>
            <div class="record-field">
                <span class="record-label">Employee</span>
                <span class="record-value fw-bold"><?= e($bonusEmployeeName !== '' ? $bonusEmployeeName : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Labor</span>
                <span class="record-value">Included in job labor cost</span>
            </div>
            <?php endif; ?>
            <div class="record-field">
                <span class="record-label">Reference</span>
                <span class="record-value"><?= e(trim((string) ($expense['reference_number'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Payment Method</span>
                <span class="record-value"><?= e(trim((string) ($expense['payment_method'] ?? '')) ?: '—') ?></span>
            </div>
        </div>

        <div class="record-row-fields mt-3">
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($expense['note'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
