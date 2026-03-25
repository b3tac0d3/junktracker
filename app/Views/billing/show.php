<?php
$invoice = is_array($invoice ?? null) ? $invoice : [];
$items = is_array($items ?? null) ? $items : [];
$payments = is_array($payments ?? null) ? $payments : [];
$business = is_array($business ?? null) ? $business : [];

$recordId = (int) ($invoice['id'] ?? 0);
$jobId = (int) ($invoice['job_id'] ?? 0);
$docType = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
if (!in_array($docType, ['estimate', 'invoice'], true)) {
    $docType = 'invoice';
}
$docTitle = $docType === 'estimate' ? 'Estimate' : 'Invoice';
$dateLabel = $docType === 'estimate' ? 'Estimate Date' : 'Invoice Date';
$dueLabel = $docType === 'estimate' ? 'Expire Date' : 'Due Date';

$docNumber = trim((string) ($invoice['invoice_number'] ?? ''));
if ($docNumber === '') {
    $docNumber = (string) $recordId;
}
$docHeaderNumber = $docNumber . ' (#' . (string) $recordId . ')';

$clientName = trim((string) ($invoice['client_name'] ?? ''));
if ($clientName === '') {
    $clientName = '—';
}

$jobTitle = trim((string) ($invoice['job_title'] ?? ''));
if ($jobTitle === '') {
    $jobTitle = '—';
}

$addressParts = [];
$line1 = trim((string) ($invoice['job_address_line1'] ?? ''));
$line2 = trim((string) ($invoice['job_address_line2'] ?? ''));
$city = trim((string) ($invoice['job_city'] ?? ''));
$state = trim((string) ($invoice['job_state'] ?? ''));
$zip = trim((string) ($invoice['job_postal_code'] ?? ''));
if ($line1 !== '') {
    $addressParts[] = $line1;
}
if ($line2 !== '') {
    $addressParts[] = $line2;
}
$cityStateZip = trim(implode(', ', array_filter([$city, $state], static fn ($value): bool => $value !== '')));
if ($zip !== '') {
    $cityStateZip = trim($cityStateZip . ' ' . $zip);
}
if ($cityStateZip !== '') {
    $addressParts[] = $cityStateZip;
}

$businessName = trim((string) ($business['name'] ?? ''));
if ($businessName === '') {
    $businessName = '—';
}
$businessLegal = trim((string) ($business['legal_name'] ?? ''));
$businessDisplayName = $businessLegal !== '' ? $businessLegal : $businessName;
$businessPhone = trim((string) ($business['phone'] ?? ''));
$businessContact = trim((string) ($business['primary_contact_name'] ?? ''));
$businessWebsite = trim((string) ($business['website_url'] ?? ''));
$businessEin = trim((string) ($business['ein_number'] ?? ''));

$businessLogoUrl = business_logo_url($business);

$businessAddress = [];
foreach ([
    trim((string) ($business['address_line1'] ?? '')),
    trim((string) ($business['address_line2'] ?? '')),
] as $line) {
    if ($line !== '') {
        $businessAddress[] = $line;
    }
}
$businessCityStateZip = trim(implode(', ', array_filter([
    trim((string) ($business['city'] ?? '')),
    trim((string) ($business['state'] ?? '')),
], static fn ($value): bool => $value !== '')));
$businessPostal = trim((string) ($business['postal_code'] ?? ''));
if ($businessPostal !== '') {
    $businessCityStateZip = trim($businessCityStateZip . ' ' . $businessPostal);
}
if ($businessCityStateZip !== '') {
    $businessAddress[] = $businessCityStateZip;
}

$totalPayments = 0.0;
foreach ($payments as $payment) {
    if (!is_array($payment)) {
        continue;
    }
    $totalPayments += (float) ($payment['amount'] ?? 0);
}
$totalPayments = round($totalPayments, 2);
$invoiceTotal = (float) ($invoice['total'] ?? 0);
$tipAmount = round(max(0.0, $totalPayments - $invoiceTotal), 2);
$balanceDue = round(max(0.0, $invoiceTotal - $totalPayments), 2);

$formatDate = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }
    $stamp = strtotime($raw);
    if ($stamp === false) {
        return '—';
    }
    return date('m/d/Y', $stamp);
};

$from = strtolower(trim((string) ($_GET['from'] ?? '')));
$jobBackId = (int) ($_GET['job_id'] ?? 0);
if ($jobBackId <= 0) {
    $jobBackId = $jobId;
}
$fromJob = $from === 'job' && $jobBackId > 0;
$backUrl = $fromJob ? url('/jobs/' . (string) $jobBackId) : url('/billing');
$backLabel = $fromJob ? 'Back to Job' : 'Back to Billing';
$editUrl = url('/billing/' . (string) $recordId . '/edit') . ($fromJob ? ('?from=job&job_id=' . (string) $jobBackId) : '');
$deleteUrl = url('/billing/' . (string) $recordId . '/delete');
$quickStatusUrl = url('/billing/' . (string) $recordId . '/quick-status');
$paymentCreateJobId = $fromJob ? $jobBackId : $jobId;
$paymentCreateUrl = url('/billing/payments/create') . '?invoice_id=' . (string) $recordId;
if ($paymentCreateJobId > 0) {
    $paymentCreateUrl .= '&job_id=' . (string) $paymentCreateJobId;
}
$quickStatusOptions = is_array($quickStatusOptions ?? null) ? $quickStatusOptions : [];
if ($quickStatusOptions === []) {
    $quickStatusOptions = $docType === 'estimate'
        ? [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'approved' => 'Approved',
            'declined' => 'Declined',
        ]
        : [
            'unsent' => 'Unsent',
            'sent' => 'Sent',
            'partially_paid' => 'Partially Paid',
            'paid_in_full' => 'Paid in Full',
        ];
}

$normalizedQuickStatusOptions = [];
foreach ($quickStatusOptions as $statusValue => $statusLabel) {
    $key = strtolower(trim((string) $statusValue));
    if ($key === '' || array_key_exists($key, $normalizedQuickStatusOptions)) {
        continue;
    }
    $normalizedQuickStatusOptions[$key] = (string) $statusLabel;
}
if ($normalizedQuickStatusOptions !== []) {
    $quickStatusOptions = $normalizedQuickStatusOptions;
}
$currentStatus = strtolower(trim((string) ($invoice['status'] ?? '')));
?>

<style>
@media print {
    .page-header,
    .app-footer,
    .main-actions,
    .sb-topnav,
    #layoutSidenav_nav,
    .btn {
        display: none !important;
    }

    .jt-business-logo {
        max-height: 72px !important;
        max-width: 200px !important;
    }

    .print-card {
        border: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
    }

    .print-page {
        padding: 0 !important;
        margin: 0 !important;
    }
}
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($docTitle) ?></h1>
        <p class="muted"><?= e($docHeaderNumber) ?></p>
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button type="button" class="dropdown-item" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button></li>
                <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= e(url('/billing/' . (string) $recordId . '/document')) ?>"><i class="fas fa-file-alt me-2"></i>Print-ready view</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/billing/' . (string) $recordId . '/document?download=1')) ?>"><i class="fas fa-download me-2"></i>Download HTML pack</a></li>
                <li>
                    <form method="post" action="<?= e(url('/billing/' . (string) $recordId . '/send-email')) ?>" class="m-0" onsubmit="return confirm('Send email to the client on file?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kind" value="<?= e($docType === 'estimate' ? 'estimate' : 'invoice') ?>">
                        <button type="submit" class="dropdown-item"><i class="fas fa-envelope me-2"></i>Email to client</button>
                    </form>
                </li>
                <li>
                    <form method="post" action="<?= e(url('/billing/' . (string) $recordId . '/portal-link')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item"><i class="fas fa-link me-2"></i>Create client portal link</button>
                    </form>
                </li>
                <?php if ($docType === 'invoice' && $payments !== []): ?>
                    <?php foreach ($payments as $_p): ?>
                        <?php
                        $_pid = (int) (is_array($_p) ? ($_p['id'] ?? 0) : 0);
                        if ($_pid <= 0) {
                            continue;
                        }
                        ?>
                        <li>
                            <form method="post" action="<?= e(url('/billing/' . (string) $recordId . '/send-email')) ?>" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="kind" value="payment_receipt">
                                <input type="hidden" name="payment_id" value="<?= e((string) $_pid) ?>">
                                <button type="submit" class="dropdown-item"><i class="fas fa-receipt me-2"></i>Email payment receipt #<?= e((string) $_pid) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($docType === 'estimate'): ?>
                    <?php
                    $convertToInvoiceUrl = url('/billing/create') . '?type=invoice&from_estimate_id=' . (string) $recordId;
                    if ($fromJob && $jobBackId > 0) {
                        $convertToInvoiceUrl .= '&from=job&job_id=' . (string) $jobBackId;
                    }
                    ?>
                    <li><a class="dropdown-item" href="<?= e($convertToInvoiceUrl) ?>"><i class="fas fa-file-invoice me-2"></i>Convert to Invoice</a></li>
                <?php endif; ?>
                <?php if ($docType === 'invoice'): ?>
                    <li><a class="dropdown-item" href="<?= e($paymentCreateUrl) ?>"><i class="fas fa-money-check-dollar me-2"></i>Add Payment</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= e($editUrl) ?>"><i class="fas fa-pen me-2"></i>Edit</a></li>
                <li>
                    <form method="post" action="<?= e($deleteUrl) ?>" onsubmit="return confirm('Delete this <?= e($docType) ?>?');" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="from" value="<?= e($fromJob ? 'job' : '') ?>">
                        <input type="hidden" name="job_id" value="<?= e((string) $jobBackId) ?>">
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    </div>
</div>

<section class="card index-card print-card print-page mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h4 class="mb-1"><?= e($docTitle) ?></h4>
                <div class="text-muted"><?= e($docHeaderNumber) ?></div>
            </div>
            <div class="text-end">
                <form method="post" action="<?= e($quickStatusUrl) ?>" class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="from" value="<?= e($fromJob ? 'job' : '') ?>">
                    <input type="hidden" name="job_id" value="<?= e((string) $jobBackId) ?>">
                    <label class="small text-muted fw-semibold" for="quick-status-select">Status</label>
                    <select id="quick-status-select" name="status" class="form-select form-select-sm" style="min-width: 12rem;">
                        <?php foreach ($quickStatusOptions as $statusValue => $statusText): ?>
                            <option value="<?= e($statusValue) ?>" <?= $currentStatus === $statusValue ? 'selected' : '' ?>><?= e($statusText) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                </form>
                <div><strong><?= e($dateLabel) ?>:</strong> <?= e($formatDate((string) ($invoice['issue_date'] ?? ''))) ?></div>
                <div><strong><?= e($dueLabel) ?>:</strong> <?= e($formatDate((string) ($invoice['due_date'] ?? ''))) ?></div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
                <div class="d-flex flex-wrap gap-3 align-items-start">
                    <?php if ($businessLogoUrl !== null): ?>
                        <img src="<?= e($businessLogoUrl) ?>" alt="" class="jt-business-logo flex-shrink-0 rounded border bg-white" style="max-height: 96px; max-width: 220px; object-fit: contain;" />
                    <?php endif; ?>
                    <div class="flex-grow-1 min-w-0">
                        <div class="record-label">Business</div>
                        <div class="record-value fw-semibold"><?= e($businessDisplayName) ?></div>
                        <?php foreach ($businessAddress as $line): ?>
                            <div class="small text-muted"><?= e($line) ?></div>
                        <?php endforeach; ?>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <?php if ($businessPhone !== ''): ?><span class="small text-muted">Phone: <?= e($businessPhone) ?></span><?php endif; ?>
                            <?php if ($businessContact !== ''): ?><span class="small text-muted">Contact: <?= e($businessContact) ?></span><?php endif; ?>
                            <?php if ($businessWebsite !== ''): ?><span class="small text-muted">Web: <?= e($businessWebsite) ?></span><?php endif; ?>
                            <?php if ($businessEin !== ''): ?><span class="small text-muted">EIN: <?= e($businessEin) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="record-label">Client</div>
                <div class="record-value fw-semibold"><?= e($clientName) ?></div>
            </div>
            <div class="col-12">
                <div class="record-label">Job</div>
                <div class="record-value fw-semibold"><?= e($jobTitle) ?></div>
            </div>
            <div class="col-12">
                <div class="record-label">Address</div>
                <?php if ($addressParts === []): ?>
                    <div class="record-value">—</div>
                <?php else: ?>
                    <?php foreach ($addressParts as $line): ?>
                        <div class="record-value"><?= e($line) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Note</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Taxable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="6" class="text-muted">No line items.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $name = trim((string) ($item['description'] ?? $item['item_type'] ?? ''));
                            $note = trim((string) ($item['note'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e($name !== '' ? $name : '—') ?></td>
                                <td><?= e($note !== '' ? $note : '—') ?></td>
                                <td class="text-end"><?= e(number_format((float) ($item['quantity'] ?? 0), 2)) ?></td>
                                <td class="text-end">$<?= e(number_format((float) ($item['unit_price'] ?? 0), 2)) ?></td>
                                <td class="text-end">$<?= e(number_format((float) ($item['line_total'] ?? 0), 2)) ?></td>
                                <td class="text-center"><?= ((int) ($item['taxable'] ?? 0)) === 1 ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="record-row-fields record-row-fields-mobile-2 <?= $docType === 'invoice' ? ($tipAmount > 0.0 ? 'record-row-fields-6' : 'record-row-fields-5') : 'record-row-fields-3' ?> mb-3">
            <div class="record-field text-md-end">
                <span class="record-label">Sub-total</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($invoice['subtotal'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field text-md-end">
                <span class="record-label">Tax</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($invoice['tax_amount'] ?? 0), 2)) ?></span>
                <span class="small text-muted">Rate: <?= e(number_format((float) ($invoice['tax_rate'] ?? 0), 2)) ?>%</span>
            </div>
            <div class="record-field text-md-end">
                <span class="record-label">Total</span>
                <span class="record-value fw-bold">$<?= e(number_format((float) ($invoice['total'] ?? 0), 2)) ?></span>
            </div>
            <?php if ($docType === 'invoice'): ?>
                <div class="record-field text-md-end">
                    <span class="record-label">Total Payments</span>
                    <span class="record-value fw-bold">$<?= e(number_format($totalPayments, 2)) ?></span>
                </div>
                <?php if ($tipAmount > 0.0): ?>
                    <div class="record-field text-md-end">
                        <span class="record-label">Tip (over invoice)</span>
                        <span class="record-value fw-bold">$<?= e(number_format($tipAmount, 2)) ?></span>
                    </div>
                <?php endif; ?>
                <div class="record-field text-md-end">
                    <span class="record-label">Balance due</span>
                    <span class="record-value fw-bold">$<?= e(number_format($balanceDue, 2)) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div class="record-label">Notes</div>
            <div class="record-value"><?= e(trim((string) ($invoice['customer_note'] ?? '')) !== '' ? (string) ($invoice['customer_note'] ?? '') : '—') ?></div>
        </div>

        <?php if ($docType === 'invoice'): ?>
            <hr class="my-4">
            <div>
                <div class="record-label mb-2">Payments</div>
                <?php if ($payments === []): ?>
                    <div class="record-value">No payments yet.</div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($payments as $payment): ?>
                            <?php
                            $paymentId = (int) ($payment['id'] ?? 0);
                            if ($paymentId <= 0) {
                                continue;
                            }
                            $paymentLink = url('/billing/payments/' . (string) $paymentId)
                                . '?invoice_id=' . (string) $recordId
                                . ($fromJob ? ('&job_id=' . (string) $jobBackId) : '');
                            $method = strtolower(trim((string) ($payment['method'] ?? 'other')));
                            $methodLabel = match ($method) {
                                'check' => 'Check',
                                'cc' => 'CC',
                                'cash' => 'Cash',
                                'venmo' => 'Venmo',
                                'cashapp' => 'Cashapp',
                                default => 'Other',
                            };
                            $reference = trim((string) ($payment['reference_number'] ?? ''));
                            $paymentDate = trim((string) ($payment['paid_at'] ?? ''));
                            $paymentDateLabel = '—';
                            if ($paymentDate !== '') {
                                $stamp = strtotime($paymentDate);
                                if ($stamp !== false) {
                                    $paymentDateLabel = date('m/d/Y', $stamp);
                                }
                            }
                            ?>
                            <li class="mb-1">
                                <a class="fw-bold text-decoration-none" href="<?= e($paymentLink) ?>">Payment #<?= e((string) $paymentId) ?></a>
                                <span class="small muted">· <?= e($paymentDateLabel) ?> · <?= e($methodLabel) ?><?php if ($reference !== ''): ?> · Ref: <?= e($reference) ?><?php endif; ?> · $<?= e(number_format((float) ($payment['amount'] ?? 0), 2)) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
