<?php
$job = is_array($job ?? null) ? $job : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$categoryOptions = is_array($categoryOptions ?? null) ? $categoryOptions : [];
$actionUrl = (string) ($actionUrl ?? url('/jobs'));
$mode = (string) ($mode ?? 'create');
$expenseId = (int) ($expenseId ?? 0);

$jobId = (int) ($job['id'] ?? 0);
$jobTitle = trim((string) ($job['title'] ?? ''));
if ($jobTitle === '') {
    $jobTitle = 'Job #' . (string) $jobId;
}
$cancelUrl = $mode === 'edit' && $expenseId > 0
    ? url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId)
    : url('/jobs/' . (string) $jobId);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$currentCategory = trim((string) ($form['category'] ?? ''));
if ($currentCategory !== '' && !in_array($currentCategory, $categoryOptions, true)) {
    $categoryOptions[] = $currentCategory;
}
natcasesort($categoryOptions);
$categoryOptions = array_values($categoryOptions);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Expense' : 'Add Expense') ?></h1>
        <p class="muted"><?= e($jobTitle) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>"><?= e($mode === 'edit' ? 'Back to Expense' : 'Back to Job') ?></a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-receipt me-2"></i>Expense Details</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="expense-date">Date</label>
                <input
                    id="expense-date"
                    type="date"
                    name="expense_date"
                    class="form-control <?= $hasError('expense_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['expense_date'] ?? '')) ?>"
                />
                <?php if ($hasError('expense_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('expense_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="expense-amount">Amount</label>
                <input
                    id="expense-amount"
                    type="number"
                    min="0"
                    step="0.01"
                    name="amount"
                    class="form-control <?= $hasError('amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['amount'] ?? '')) ?>"
                    placeholder="0.00"
                />
                <?php if ($hasError('amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="expense-category">Category</label>
                <select id="expense-category" name="category" class="form-select">
                    <option value="">Select category...</option>
                    <?php foreach ($categoryOptions as $option): ?>
                        <?php $optionText = trim((string) $option); ?>
                        <?php if ($optionText === '') {
                            continue;
                        } ?>
                        <option value="<?= e($optionText) ?>" <?= strcasecmp($currentCategory, $optionText) === 0 ? 'selected' : '' ?>><?= e($optionText) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="expense-payment-method">Payment Method</label>
                <input
                    id="expense-payment-method"
                    type="text"
                    name="payment_method"
                    class="form-control"
                    value="<?= e((string) ($form['payment_method'] ?? '')) ?>"
                    maxlength="80"
                    placeholder="Discover, Amex, Cash..."
                />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="expense-note">Note</label>
                <textarea id="expense-note" name="note" class="form-control" rows="3" maxlength="255"><?= e((string) ($form['note'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Save Expense') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
