<?php
$job = is_array($job ?? null) ? $job : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? url('/jobs'));
$mode = (string) ($mode ?? 'create');
$adjustmentId = (int) ($adjustmentId ?? 0);

$jobId = (int) ($job['id'] ?? 0);
$jobTitle = trim((string) ($job['title'] ?? ''));
if ($jobTitle === '') {
    $jobTitle = 'Job #' . (string) $jobId;
}
$cancelUrl = $mode === 'edit' && $adjustmentId > 0
    ? url('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId)
    : url('/jobs/' . (string) $jobId);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Adjustment' : 'Add Adjustment') ?></h1>
        <p class="muted"><?= e($jobTitle) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>"><?= e($mode === 'edit' ? 'Back to Adjustment' : 'Back to Job') ?></a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-sliders-h me-2"></i>Adjustment Details</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="adjustment-name">Name</label>
                <input
                    id="adjustment-name"
                    type="text"
                    name="name"
                    class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['name'] ?? '')) ?>"
                    maxlength="120"
                    placeholder="Payroll true-up, bonus hours, etc."
                />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="adjustment-date">Date</label>
                <input
                    id="adjustment-date"
                    type="date"
                    name="adjustment_date"
                    class="form-control <?= $hasError('adjustment_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['adjustment_date'] ?? '')) ?>"
                />
                <?php if ($hasError('adjustment_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('adjustment_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="adjustment-amount">Amount</label>
                <input
                    id="adjustment-amount"
                    type="number"
                    step="0.01"
                    name="amount"
                    class="form-control <?= $hasError('amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['amount'] ?? '')) ?>"
                    placeholder="0.00"
                />
                <div class="form-text">Positive values reduce profit. Negative values add back to profit.</div>
                <?php if ($hasError('amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="adjustment-note">Note</label>
                <textarea id="adjustment-note" name="note" class="form-control" rows="3" maxlength="255"><?= e((string) ($form['note'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Save Adjustment') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
