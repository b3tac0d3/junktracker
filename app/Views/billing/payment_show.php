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
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e($editUrl) ?>"><i class="fas fa-pen me-2"></i>Edit Payment</a></li>
                <li>
                    <form method="post" action="<?= e(url('/billing/payments/' . (string) $paymentId . '/delete')) ?>" class="m-0" onsubmit="return confirm('Delete this payment?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_job_id" value="<?= e((string) $jobId) ?>">
                        <input type="hidden" name="return_invoice_id" value="<?= e((string) $invoiceId) ?>">
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
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
