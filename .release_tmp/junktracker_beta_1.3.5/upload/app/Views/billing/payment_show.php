<?php
$payment = is_array($payment ?? null) ? $payment : [];
$paymentId = (int) ($payment['id'] ?? 0);
$invoiceId = max(0, (int) ($invoiceId ?? 0));
$jobId = max(0, (int) ($jobId ?? 0));
$backUrl = (string) ($backUrl ?? ($invoiceId > 0 ? url('/billing/' . (string) $invoiceId) : url('/billing')));
$backLabel = (string) ($backLabel ?? 'Back');

$method = strtolower(trim((string) ($payment['method'] ?? 'other')));
$methodLabel = match ($method) {
    'check' => 'Check',
    'cc' => 'CC',
    'cash' => 'Cash',
    'venmo' => 'Venmo',
    'cashapp' => 'Cashapp',
    default => 'Other',
};
$paymentType = strtolower(trim((string) ($payment['payment_type'] ?? 'payment')));
$paymentTypeLabel = match ($paymentType) {
    'deposit' => 'Deposit',
    default => 'Payment',
};
$receivedBy = trim((string) ($payment['received_by_name'] ?? ''));
if ($receivedBy === '') {
    $receivedBy = '—';
}

$paidDate = format_datetime((string) ($payment['paid_at'] ?? null));
$invoiceNumber = trim((string) ($payment['invoice_number'] ?? ''));
if ($invoiceNumber === '') {
    $invoiceNumber = $invoiceId > 0 ? ('Invoice #' . (string) $invoiceId) : '—';
}

$editUrl = url('/billing/payments/' . (string) $paymentId . '/edit');
$query = [];
if ($jobId > 0) {
    $query['job_id'] = (string) $jobId;
}
if ($invoiceId > 0) {
    $query['invoice_id'] = (string) $invoiceId;
}
if ($query !== []) {
    $editUrl .= '?' . http_build_query($query);
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Payment #<?= e((string) $paymentId) ?></h1>
        <p class="muted"><?= e($invoiceNumber) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e($editUrl) ?>"><i class="fas fa-pen me-2"></i>Edit Payment</a>
        <form method="post" action="<?= e(url('/billing/payments/' . (string) $paymentId . '/delete')) ?>" onsubmit="return confirm('Delete this payment?');" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="return_job_id" value="<?= e((string) $jobId) ?>">
            <input type="hidden" name="return_invoice_id" value="<?= e((string) $invoiceId) ?>">
            <button class="btn btn-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-money-check-dollar me-2"></i>Payment Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Date</span>
                <span class="record-value"><?= e($paidDate) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Payment Type</span>
                <span class="record-value"><?= e($paymentTypeLabel) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Method</span>
                <span class="record-value"><?= e($methodLabel) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Amount</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($payment['amount'] ?? 0), 2)) ?></span>
            </div>
        </div>

        <div class="record-row-fields mt-3 record-row-fields-4 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Reference Number</span>
                <span class="record-value"><?= e(trim((string) ($payment['reference_number'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Received By</span>
                <span class="record-value"><?= e($receivedBy) ?></span>
            </div>
        </div>

        <div class="record-row-fields mt-3">
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($payment['note'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
