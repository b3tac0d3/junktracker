<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? '')));
$dateFrom = trim((string) ($dateFrom ?? ''));
$dateTo = trim((string) ($dateTo ?? ''));
$pastDueOnly = (bool) ($pastDueOnly ?? false);
$billingTab = strtolower(trim((string) ($billingTab ?? 'invoices')));
if (!in_array($billingTab, ['invoices', 'estimates'], true)) {
    $billingTab = 'invoices';
}
$isInvoicesTab = $billingTab === 'invoices';
$isEstimatesTab = !$isInvoicesTab;

$billingGrouped = is_array($billingGrouped ?? null) ? $billingGrouped : [];
$bucketRows = is_array($billingGrouped['buckets'] ?? null) ? $billingGrouped['buckets'] : [];
$bucketTotals = is_array($billingGrouped['totals'] ?? null) ? $billingGrouped['totals'] : [];
$estimates = is_array($estimates ?? null) ? $estimates : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
if (!array_key_exists('', $statusOptions)) {
    $statusOptions = ['' => 'All'] + $statusOptions;
}

$pastDueRows = is_array($bucketRows['past_due'] ?? null) ? $bucketRows['past_due'] : [];
$unpaidRows = is_array($bucketRows['unpaid'] ?? null) ? $bucketRows['unpaid'] : [];
$paidRows = is_array($bucketRows['paid'] ?? null) ? $bucketRows['paid'] : [];
$invoiceCount = (int) ($bucketTotals['all']['count'] ?? 0);
$estimateCount = count($estimates);

$billingSections = [
    'past_due' => [
        'id' => 'billing-past-due',
        'label' => 'Past due',
        'hint' => 'Open balance with a due date before today',
        'rows' => $pastDueRows,
        'total' => is_array($bucketTotals['past_due'] ?? null) ? $bucketTotals['past_due'] : ['count' => 0, 'amount' => 0.0],
        'amount_class' => 'jt-billing-total--unpaid',
        'row_class' => 'billing-record-row--past-due',
        'show_due' => true,
    ],
    'unpaid' => [
        'id' => 'billing-unpaid',
        'label' => 'Unpaid',
        'hint' => 'Open balance not yet due',
        'rows' => $unpaidRows,
        'total' => is_array($bucketTotals['unpaid'] ?? null) ? $bucketTotals['unpaid'] : ['count' => 0, 'amount' => 0.0],
        'amount_class' => 'jt-billing-total--invoice',
        'row_class' => '',
        'show_due' => true,
    ],
    'paid' => [
        'id' => 'billing-paid',
        'label' => 'Paid',
        'hint' => 'Paid in full, written off, or zero balance',
        'rows' => $paidRows,
        'total' => is_array($bucketTotals['paid'] ?? null) ? $bucketTotals['paid'] : ['count' => 0, 'amount' => 0.0],
        'amount_class' => 'jt-billing-total--paid',
        'row_class' => '',
        'show_due' => false,
    ],
];

if ($pastDueOnly) {
    $billingSections = ['past_due' => $billingSections['past_due']];
}

$billingTabUrl = static function (string $tab) use ($search, $dateFrom, $dateTo, $pastDueOnly): string {
    $params = [];
    if ($tab !== '') {
        $params['tab'] = $tab;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }
    if ($pastDueOnly && $tab === 'invoices') {
        $params['past_due'] = '1';
    }

    return url('/billing') . query_with($params);
};

$billingTotalClass = static function (string $docType, string $status, string $sectionAmountClass = ''): string {
    $t = strtolower(trim($docType));
    if ($t === 'estimate') {
        return 'jt-billing-total jt-billing-total--estimate';
    }
    if ($sectionAmountClass !== '') {
        return 'jt-billing-total ' . $sectionAmountClass;
    }
    $s = strtolower(trim($status));
    if (in_array($s, ['paid_in_full', 'paid', 'write_off'], true)) {
        return 'jt-billing-total jt-billing-total--paid';
    }
    if (in_array($s, ['partially_paid', 'partial'], true)) {
        return 'jt-billing-total jt-billing-total--partial';
    }

    return 'jt-billing-total jt-billing-total--unpaid';
};

$formatBillingStatus = static function (?string $value): string {
    $normalized = strtolower(trim((string) ($value ?? '')));
    return match ($normalized) {
        'write_off' => 'Non Payment / Write Off',
        'paid_in_full', 'paid' => 'Paid in Full',
        'partially_paid', 'partial' => 'Partially Paid',
        'unsent' => 'Unsent',
        'converted' => 'Converted',
        default => $normalized === '' ? '—' : ucwords(str_replace('_', ' ', $normalized)),
    };
};

$renderBillingRow = static function (
    array $invoice,
    string $sectionAmountClass = '',
    string $rowClass = '',
    bool $showDue = false,
    bool $showExpire = false
) use ($billingTotalClass, $formatBillingStatus): void {
    $invType = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
    $isEstimate = $invType === 'estimate';
    $numRaw = trim((string) ($invoice['invoice_number'] ?? ''));
    $numPart = $numRaw !== '' ? $numRaw : (string) ((int) ($invoice['id'] ?? 0));
    $docPrefix = $isEstimate ? 'Estimate #' : 'Invoice #';
    $titleLine = $docPrefix . $numPart;
    $issueRaw = trim((string) ($invoice['issue_date'] ?? ''));
    $dueRaw = trim((string) ($invoice['due_date'] ?? ''));
    $displayDateRaw = $issueRaw !== '' ? $issueRaw : (string) ($invoice['created_at'] ?? '');
    $dateLine = format_date($displayDateRaw !== '' ? $displayDateRaw : null);
    $dueLine = $dueRaw !== '' ? format_date($dueRaw) : '—';
    $jobName = trim((string) ($invoice['job_name'] ?? ''));
    $clientName = trim((string) ($invoice['client_name'] ?? ''));
    $statusLabel = $formatBillingStatus((string) ($invoice['status'] ?? ''));
    $balanceDue = (float) ($invoice['balance_due'] ?? ($invoice['total'] ?? 0));
    $displayAmount = $showDue ? $balanceDue : (float) ($invoice['total'] ?? 0);
    ?>
    <article class="record-row-simple<?= $rowClass !== '' ? ' ' . e($rowClass) : '' ?>">
        <a class="record-row-link" href="<?= e(url('/billing/' . (string) ((int) ($invoice['id'] ?? 0)))) ?>">
            <div class="record-row-main billing-record-head">
                <h3 class="record-title-simple billing-record-title"><?= e($titleLine) ?></h3>
                <span class="<?= e($billingTotalClass((string) ($invoice['type'] ?? 'invoice'), (string) ($invoice['status'] ?? ''), $sectionAmountClass)) ?>"><?= e(format_money_usd($displayAmount)) ?></span>
            </div>
            <div class="record-subline billing-record-subline text-muted small">
                <span class="billing-record-client"><?= e($clientName !== '' ? $clientName : '—') ?></span>
                <span class="billing-record-date-sep" aria-hidden="true">·</span>
                <span class="billing-record-job"><?= e($jobName !== '' ? $jobName : '—') ?></span>
                <span class="billing-record-date-sep" aria-hidden="true">·</span>
                <?php if ($showDue): ?>
                    <span class="billing-record-date">Due <?= e($dueLine) ?></span>
                    <span class="billing-record-date-sep" aria-hidden="true">·</span>
                <?php elseif ($showExpire): ?>
                    <span class="billing-record-date">Expires <?= e($dueLine) ?></span>
                    <span class="billing-record-date-sep" aria-hidden="true">·</span>
                <?php else: ?>
                    <span class="billing-record-date"><?= e($dateLine) ?></span>
                    <span class="billing-record-date-sep" aria-hidden="true">·</span>
                <?php endif; ?>
                <span class="billing-record-status"><?= e($statusLabel) ?></span>
            </div>
        </a>
    </article>
    <?php
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Billing</h1>
        <p class="muted"><?= $isInvoicesTab ? 'Invoices grouped by payment status' : 'Estimates and proposals' ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/billing/deposits')) ?>">Bank deposits</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/export.csv')) ?>">Export CSV</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/billing')) ?>" class="d-flex flex-column gap-3">
            <input type="hidden" name="tab" value="<?= e($billingTab) ?>">
            <?php if ($pastDueOnly): ?>
                <input type="hidden" name="past_due" value="1">
            <?php endif; ?>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="billing-search">Search</label>
                    <input id="billing-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="<?= $isInvoicesTab ? 'Invoice #, client, job, status, or id…' : 'Estimate #, client, job, status, or id…' ?>" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="billing-status">Status</label>
                    <select id="billing-status" class="form-select" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-grid d-md-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary flex-fill" href="<?= e($billingTabUrl($billingTab)) ?>">Clear</a>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="billing-date-from">From</label>
                    <input id="billing-date-from" class="form-control" type="date" name="date_from" value="<?= e($dateFrom) ?>" />
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="billing-date-to">To</label>
                    <input id="billing-date-to" class="form-control" type="date" name="date_to" value="<?= e($dateTo) ?>" />
                </div>
            </div>
        </form>
    </div>
</section>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs index-card-tabs estate-sale-tabs billing-tabs px-2 pt-2" role="tablist">
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link estate-sale-tab-link<?= $isInvoicesTab ? ' active' : '' ?>"
                    href="<?= e($billingTabUrl('invoices')) ?>"
                    role="tab"
                    aria-selected="<?= $isInvoicesTab ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span class="estate-sale-tab-label">Invoices</span>
                    <span class="badge rounded-pill text-bg-light ms-1"><?= e((string) $invoiceCount) ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link estate-sale-tab-link<?= $isEstimatesTab ? ' active' : '' ?>"
                    href="<?= e($billingTabUrl('estimates')) ?>"
                    role="tab"
                    aria-selected="<?= $isEstimatesTab ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
                    <span class="estate-sale-tab-label">Estimates</span>
                    <span class="badge rounded-pill text-bg-light ms-1"><?= e((string) $estimateCount) ?></span>
                </a>
            </li>
        </ul>
    </div>

    <?php if ($isInvoicesTab): ?>
        <div class="card-body border-bottom pb-0 pt-3">
            <div class="record-row-fields record-row-fields-3">
                <div class="record-field">
                    <span class="record-label">Past due</span>
                    <span class="record-value jt-billing-total jt-billing-total--unpaid">$<?= e(number_format((float) ($bucketTotals['past_due']['amount'] ?? 0), 2)) ?></span>
                    <span class="small text-muted"><?= e((string) ((int) ($bucketTotals['past_due']['count'] ?? 0))) ?> invoice(s)</span>
                </div>
                <div class="record-field">
                    <span class="record-label">Unpaid</span>
                    <span class="record-value jt-billing-total jt-billing-total--invoice">$<?= e(number_format((float) ($bucketTotals['unpaid']['amount'] ?? 0), 2)) ?></span>
                    <span class="small text-muted"><?= e((string) ((int) ($bucketTotals['unpaid']['count'] ?? 0))) ?> invoice(s)</span>
                </div>
                <div class="record-field">
                    <span class="record-label">Paid</span>
                    <span class="record-value jt-billing-total jt-billing-total--paid">$<?= e(number_format((float) ($bucketTotals['paid']['amount'] ?? 0), 2)) ?></span>
                    <span class="small text-muted"><?= e((string) ((int) ($bucketTotals['paid']['count'] ?? 0))) ?> invoice(s)</span>
                </div>
            </div>
        </div>
        <div class="card-body p-2 p-lg-3 billing-buckets">
            <?php if ($invoiceCount === 0): ?>
                <div class="record-empty">No invoices found for the current filter.</div>
            <?php else: ?>
                <?php foreach ($billingSections as $section): ?>
                    <?php
                    $sectionRows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
                    $sectionCount = count($sectionRows);
                    $sectionTotal = is_array($section['total'] ?? null) ? $section['total'] : ['count' => 0, 'amount' => 0.0];
                    ?>
                    <div class="billing-bucket" id="<?= e((string) ($section['id'] ?? '')) ?>">
                        <div class="billing-bucket-header">
                            <div>
                                <div class="billing-bucket-label"><?= e((string) ($section['label'] ?? 'Records')) ?></div>
                                <div class="billing-bucket-hint"><?= e((string) ($section['hint'] ?? '')) ?></div>
                            </div>
                            <div class="billing-bucket-summary text-end">
                                <div class="<?= e($billingTotalClass('invoice', '', (string) ($section['amount_class'] ?? ''))) ?>">$<?= e(number_format((float) ($sectionTotal['amount'] ?? 0), 2)) ?></div>
                                <div class="small text-muted"><?= e((string) $sectionCount) ?> invoice(s)</div>
                            </div>
                        </div>
                        <?php if ($sectionRows === []): ?>
                            <div class="billing-bucket-empty">Nothing in this category.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($sectionRows as $invoice): ?>
                                    <?php
                                    if (!is_array($invoice)) {
                                        continue;
                                    }
                                    $renderBillingRow(
                                        $invoice,
                                        (string) ($section['amount_class'] ?? ''),
                                        (string) ($section['row_class'] ?? ''),
                                        (bool) ($section['show_due'] ?? false),
                                        false
                                    );
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card-body p-2 p-lg-3">
            <?php if ($estimates === []): ?>
                <div class="record-empty">No estimates found for the current filter.</div>
            <?php else: ?>
                <div class="record-list-simple">
                    <?php foreach ($estimates as $estimate): ?>
                        <?php
                        if (!is_array($estimate)) {
                            continue;
                        }
                        $renderBillingRow($estimate, 'jt-billing-total--estimate', '', false, true);
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
