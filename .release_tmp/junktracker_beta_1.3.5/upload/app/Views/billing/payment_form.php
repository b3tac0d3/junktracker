<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$invoiceOptions = is_array($invoiceOptions ?? null) ? $invoiceOptions : [];
$paymentCategoryOptions = is_array($paymentCategoryOptions ?? null) ? $paymentCategoryOptions : [];
$paymentTypeOptions = is_array($paymentTypeOptions ?? null) ? $paymentTypeOptions : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/billing/payments'));
$backUrl = (string) ($backUrl ?? url('/billing'));
$backLabel = (string) ($backLabel ?? 'Back');
$returnJobId = (int) ($returnJobId ?? 0);
$returnInvoiceId = (int) ($returnInvoiceId ?? 0);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$selectedInvoiceId = (int) ($form['invoice_id'] ?? 0);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Payment' : 'Add Payment') ?></h1>
        <p class="muted">Simple payment form</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-money-check-dollar me-2"></i><?= e($mode === 'edit' ? 'Update Payment' : 'Create Payment') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="return_job_id" value="<?= e((string) $returnJobId) ?>">
            <input type="hidden" name="return_invoice_id" value="<?= e((string) $returnInvoiceId) ?>">

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="payment-date">Date</label>
                <input
                    id="payment-date"
                    type="date"
                    name="paid_date"
                    class="form-control <?= $hasError('paid_date') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['paid_date'] ?? '')) ?>"
                />
                <?php if ($hasError('paid_date')): ?><div class="invalid-feedback d-block"><?= e($fieldError('paid_date')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="payment-invoice">Invoice Name</label>
                <select id="payment-invoice" name="invoice_id" class="form-select <?= $hasError('invoice_id') ? 'is-invalid' : '' ?>">
                    <option value="">Choose invoice...</option>
                    <?php foreach ($invoiceOptions as $option): ?>
                        <?php
                        $invoiceId = (int) ($option['id'] ?? 0);
                        if ($invoiceId <= 0) {
                            continue;
                        }
                        $invoiceNumber = trim((string) ($option['invoice_number'] ?? '')) ?: ('Invoice #' . (string) $invoiceId);
                        $clientName = trim((string) ($option['client_name'] ?? ''));
                        $jobTitle = trim((string) ($option['job_title'] ?? ''));
                        $summaryParts = array_filter([$invoiceNumber, $jobTitle, $clientName], static fn (string $value): bool => $value !== '');
                        $label = implode(' · ', $summaryParts);
                        ?>
                        <option value="<?= e((string) $invoiceId) ?>" <?= $selectedInvoiceId === $invoiceId ? 'selected' : '' ?>><?= e($label !== '' ? $label : ('Invoice #' . (string) $invoiceId)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('invoice_id')): ?><div class="invalid-feedback d-block"><?= e($fieldError('invoice_id')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="payment-amount">Amount</label>
                <input
                    id="payment-amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="amount"
                    class="form-control <?= $hasError('amount') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['amount'] ?? '')) ?>"
                />
                <?php if ($hasError('amount')): ?><div class="invalid-feedback d-block"><?= e($fieldError('amount')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="payment-type">Payment Type</label>
                <select id="payment-type" name="payment_type" class="form-select <?= $hasError('payment_type') ? 'is-invalid' : '' ?>">
                    <?php foreach ($paymentCategoryOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= ((string) ($form['payment_type'] ?? 'payment')) === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('payment_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('payment_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="payment-method">Payment Method</label>
                <select id="payment-method" name="method" class="form-select <?= $hasError('method') ? 'is-invalid' : '' ?>">
                    <?php foreach ($paymentTypeOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= ((string) ($form['method'] ?? 'cash')) === (string) $value ? 'selected' : '' ?>><?= e((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('method')): ?><div class="invalid-feedback d-block"><?= e($fieldError('method')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="payment-reference">Reference Number</label>
                <input
                    id="payment-reference"
                    name="reference_number"
                    class="form-control <?= $hasError('reference_number') ? 'is-invalid' : '' ?>"
                    value="<?= e((string) ($form['reference_number'] ?? '')) ?>"
                    maxlength="120"
                    placeholder="Check #, Venmo ID, etc"
                />
                <?php if ($hasError('reference_number')): ?><div class="invalid-feedback d-block"><?= e($fieldError('reference_number')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="payment-note">Note</label>
                <textarea
                    id="payment-note"
                    name="note"
                    class="form-control <?= $hasError('note') ? 'is-invalid' : '' ?>"
                    rows="3"
                    placeholder="Optional note"
                ><?= e((string) ($form['note'] ?? '')) ?></textarea>
                <?php if ($hasError('note')): ?><div class="invalid-feedback d-block"><?= e($fieldError('note')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Add Payment') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
