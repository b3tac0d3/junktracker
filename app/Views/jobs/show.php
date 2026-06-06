<?php

use App\Models\Expense;

$job = is_array($job ?? null) ? $job : [];
$financial = is_array($financial ?? null) ? $financial : [];
$timeSummary = is_array($timeSummary ?? null) ? $timeSummary : [];
$timeLogs = is_array($timeLogs ?? null) ? $timeLogs : [];
$employeeLaborTotals = is_array($employeeLaborTotals ?? null) ? $employeeLaborTotals : [];
$laborTotalsByEmployeeId = [];
foreach ($employeeLaborTotals as $laborTotalRow) {
    if (!is_array($laborTotalRow)) {
        continue;
    }
    $laborEmployeeId = (int) ($laborTotalRow['employee_id'] ?? 0);
    if ($laborEmployeeId > 0) {
        $laborTotalsByEmployeeId[$laborEmployeeId] = $laborTotalRow;
    }
}
$expenses = is_array($expenses ?? null) ? $expenses : [];
$adjustments = is_array($adjustments ?? null) ? $adjustments : [];
$documents = is_array($documents ?? null) ? $documents : [];
$assignedEmployees = is_array($assignedEmployees ?? null) ? $assignedEmployees : [];
$subAssignment = is_array($subAssignment ?? null) ? $subAssignment : null;

$addressStreet = implode(', ', array_filter([
    trim((string) ($job['address_line1'] ?? '')),
    trim((string) ($job['address_line2'] ?? '')),
], static fn (string $value): bool => $value !== ''));
$addressRegion = implode(', ', array_filter([
    trim((string) ($job['city'] ?? '')),
    trim((string) ($job['state'] ?? '')),
    trim((string) ($job['postal_code'] ?? '')),
], static fn (string $value): bool => $value !== ''));
$mapsAddressUrl = maps_directions_url_from_parts([
    (string) ($job['address_line1'] ?? ''),
    (string) ($job['address_line2'] ?? ''),
    (string) ($job['city'] ?? ''),
    (string) ($job['state'] ?? ''),
    (string) ($job['postal_code'] ?? ''),
]);
if ($addressStreet === '' && $addressRegion === '') {
    $addressStreet = '—';
}

$title = trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)));
$jobId = (int) ($job['id'] ?? 0);
$subOutUrl = url('/jobs/' . (string) $jobId . '/sub-out');
$subOutEditUrl = url('/jobs/' . (string) $jobId . '/sub-out/edit');
$clientId = (int) ($job['client_id'] ?? 0);
$estimateCreateUrl = url('/billing/create') . '?type=estimate&from=job&job_id=' . (string) $jobId . '&client_id=' . (string) $clientId;
$invoiceCreateUrl = url('/billing/create') . '?type=invoice&from=job&job_id=' . (string) $jobId . '&client_id=' . (string) $clientId;
$estimateDocs = is_array($documents['estimates'] ?? null) ? $documents['estimates'] : [];
$invoiceDocs = is_array($documents['invoices'] ?? null) ? $documents['invoices'] : [];
$paymentDocs = is_array($documents['payments'] ?? null) ? $documents['payments'] : [];
$saleDocs = is_array($documents['sales'] ?? null) ? $documents['sales'] : [];
$defaultInvoiceId = (int) ($invoiceDocs[0]['id'] ?? 0);
$paymentCreateUrl = url('/billing/payments/create') . '?job_id=' . (string) $jobId;
if ($defaultInvoiceId > 0) {
    $paymentCreateUrl .= '&invoice_id=' . (string) $defaultInvoiceId;
}
$purchaseQuery = ['title' => $title];
if ($clientId > 0) {
    $purchaseQuery['client_id'] = (string) $clientId;
}
$purchaseCreateUrl = url('/purchases/create') . '?' . http_build_query($purchaseQuery);
$expenseCreateUrl = url('/jobs/' . (string) $jobId . '/expenses/create' . detail_return_tab_query('financial'));
$adjustmentCreateUrl = url('/jobs/' . (string) $jobId . '/adjustments/create' . detail_return_tab_query('financial'));
$timeEntryCreateUrl = url('/time-tracking/create') . '?job_id=' . (string) $jobId . '&return_to=' . urlencode('/jobs/' . (string) $jobId . '?tab=labor');
$employeeAddUrl = url('/jobs/' . (string) $jobId . '/employees/add' . detail_return_tab_query('labor'));
$bonusPayoutCreateUrl = url('/jobs/' . (string) $jobId . '/expenses/create?preset=bonus&return_tab=labor');
$bulkPunchUrl = url('/jobs/' . (string) $jobId . '/employees/bulk-punch');
$laborBonusesByEmployeeId = is_array($laborBonusesByEmployeeId ?? null) ? $laborBonusesByEmployeeId : [];
$bonusPayoutUrlForEmployee = static function (int $employeeId) use ($jobId): string {
    return url('/jobs/' . (string) $jobId . '/expenses/create?preset=bonus&employee_id=' . (string) $employeeId . '&return_tab=labor');
};
$jobStatus = strtolower(trim((string) ($job['status'] ?? 'pending')));
$isInactive = $jobStatus === 'inactive' || (array_key_exists('is_active', $job) && (int) ($job['is_active'] ?? 1) === 0);
$jobStatusOptions = is_array($jobStatusOptions ?? null) ? $jobStatusOptions : ['prospect', 'pending', 'active', 'complete', 'cancelled'];

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', strtolower(trim($value))));
};

$formatDocDate = static function (?string $value): string {
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

$formatDocStatus = static function (?string $value): string {
    $normalized = strtolower(trim((string) $value));
    return match ($normalized) {
        'unsent' => 'Unsent',
        'sent' => 'Sent',
        'partially_paid', 'partial' => 'Partially Paid',
        'paid_in_full', 'paid' => 'Paid in Full',
        'write_off' => 'Non Payment / Write Off',
        'draft' => 'Draft',
        'approved' => 'Approved',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
        default => $normalized === '' ? '—' : ucwords(str_replace('_', ' ', $normalized)),
    };
};

$formatDuration = static function (int $minutes): string {
    if ($minutes <= 0) {
        return '0h 00m';
    }
    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%dh %02dm', $hours, $mins);
};

$canViewFinancials = (bool) ($canViewFinancials ?? can_view_financials());
$activeTab = strtolower(trim((string) ($activeTab ?? 'details')));
$allowedTabs = ['details', 'labor'];
if ($canViewFinancials) {
    $allowedTabs[] = 'financial';
    $allowedTabs[] = 'transactions';
}
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'details';
}

$saleReturnTab = in_array($activeTab, ['financial', 'transactions'], true) ? $activeTab : 'transactions';
$saleCreateQuery = [
    'from' => 'job',
    'job_id' => (string) $jobId,
    'return_tab' => $saleReturnTab,
];
if ($clientId > 0) {
    $saleCreateQuery['client_id'] = (string) $clientId;
}
$saleCreateUrl = url('/sales/create') . '?' . http_build_query($saleCreateQuery);

$detailsTabActive = $activeTab === 'details';
$financialTabActive = $activeTab === 'financial';
$transactionsTabActive = $activeTab === 'transactions';
$laborTabActive = $activeTab === 'labor';

$formatMoney = static fn (float $value): string => '$' . number_format($value, 2);
$transactions = [];
foreach ($estimateDocs as $estimate) {
    if (!is_array($estimate)) {
        continue;
    }
    $docId = (int) ($estimate['id'] ?? 0);
    if ($docId <= 0) {
        continue;
    }
    $transactions[] = [
        'type' => 'Estimate',
        'id' => $docId,
        'number' => 'Estimate',
        'status' => $formatDocStatus((string) ($estimate['status'] ?? '')),
        'date' => $formatDocDate((string) ($estimate['issue_date'] ?? '')),
        'total_label' => '',
        'total_value' => (float) ($estimate['total'] ?? 0),
        'created_at' => (string) ($estimate['created_at'] ?? ''),
        'url' => url('/billing/' . (string) $docId) . '?from=job&job_id=' . (string) $jobId,
    ];
}
foreach ($invoiceDocs as $invoice) {
    if (!is_array($invoice)) {
        continue;
    }
    $docId = (int) ($invoice['id'] ?? 0);
    if ($docId <= 0) {
        continue;
    }
    $transactions[] = [
        'type' => 'Invoice',
        'id' => $docId,
        'number' => 'Invoice',
        'status' => $formatDocStatus((string) ($invoice['status'] ?? '')),
        'date' => $formatDocDate((string) ($invoice['issue_date'] ?? '')),
        'total_label' => '',
        'total_value' => (float) ($invoice['total'] ?? 0),
        'created_at' => (string) ($invoice['created_at'] ?? ''),
        'url' => url('/billing/' . (string) $docId) . '?from=job&job_id=' . (string) $jobId,
    ];
}
foreach ($paymentDocs as $payment) {
    if (!is_array($payment)) {
        continue;
    }
    $paymentId = (int) ($payment['id'] ?? 0);
    if ($paymentId <= 0) {
        continue;
    }
    $method = strtolower(trim((string) ($payment['method'] ?? 'other')));
    $methodLabel = match ($method) {
        'check' => 'Check',
        'cc' => 'CC',
        'cash' => 'Cash',
        'venmo' => 'Venmo',
        'cashapp' => 'Cashapp',
        default => 'Other',
    };
    $transactions[] = [
        'type' => 'Payment',
        'id' => $paymentId,
        'number' => 'Payment',
        'status' => $methodLabel,
        'date' => $formatDocDate((string) ($payment['paid_at'] ?? '')),
        'total_label' => '',
        'total_value' => (float) ($payment['amount'] ?? 0),
        'created_at' => (string) ($payment['created_at'] ?? ''),
        'url' => url('/billing/payments/' . (string) $paymentId) . '?job_id=' . (string) $jobId . '&invoice_id=' . (string) ((int) ($payment['invoice_id'] ?? 0)),
    ];
}
foreach ($saleDocs as $sale) {
    if (!is_array($sale)) {
        continue;
    }
    $saleId = (int) ($sale['id'] ?? 0);
    if ($saleId <= 0) {
        continue;
    }
    $saleTitle = trim((string) ($sale['name'] ?? ''));
    if ($saleTitle === '' || strcasecmp($saleTitle, $title) === 0) {
        $saleLabel = 'Sale #' . (string) $saleId;
    } else {
        $saleLabel = 'Sale — ' . $saleTitle;
    }
    $saleType = trim((string) ($sale['sale_type'] ?? ''));
    if ($saleType === '') {
        $saleType = 'Sale';
    } else {
        $saleType = ucwords(str_replace('_', ' ', strtolower($saleType)));
    }
    $transactions[] = [
        'type' => 'Sale',
        'id' => $saleId,
        'number' => $saleLabel,
        'status' => $saleType . ' · Net ' . $formatMoney((float) ($sale['net_amount'] ?? 0)),
        'date' => $formatDocDate((string) ($sale['sale_date'] ?? '')),
        'total_label' => 'Gross',
        'total_value' => (float) ($sale['gross_amount'] ?? 0),
        'created_at' => (string) ($sale['created_at'] ?? ''),
        'url' => url('/sales/' . (string) $saleId),
    ];
}
usort($transactions, static function (array $a, array $b): int {
    $aStamp = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $bStamp = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    if ($aStamp !== $bStamp) {
        return $aStamp <=> $bStamp;
    }
    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
});

$transactionsCount = count($transactions) + count($expenses) + count($adjustments);
$laborCount = count($assignedEmployees);
$hasCloseout = array_key_exists('closeout_truck_loaded', $job);
?>

<div class="page-header">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <h1 class="mb-0"><?= e($title) ?></h1>
        </div>
        <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap align-items-md-center justify-content-md-end">
        <?php if ($isInactive): ?>
            <span class="badge text-bg-secondary align-self-center justify-self-start">Deactivated</span>
        <?php endif; ?>
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end jt-actions-menu">
                <li><a class="dropdown-item" href="<?= e(url('/jobs/' . (string) $jobId . '/edit' . detail_return_tab_query($activeTab))) ?>"><i class="fas fa-pen me-2"></i>Edit Job</a></li>
                <?php if (\App\Models\Subcontractor::isAvailable()): ?>
                <li>
                    <a class="dropdown-item" href="<?= e($subAssignment !== null ? $subOutEditUrl : $subOutUrl) ?>">
                        <i class="fas fa-share-square me-2"></i><?= $subAssignment !== null ? 'Manage Sub Out' : 'Sub Out' ?>
                    </a>
                </li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= e($timeEntryCreateUrl) ?>"><i class="fas fa-clock me-2"></i>Add Time Entry</a></li>
                <li><a class="dropdown-item" href="<?= e($employeeAddUrl) ?>"><i class="fas fa-user-plus me-2"></i>Add Employee</a></li>
                <li><a class="dropdown-item" href="<?= e($estimateCreateUrl) ?>"><i class="fas fa-file-signature me-2"></i>Add Estimate</a></li>
                <li><a class="dropdown-item" href="<?= e($invoiceCreateUrl) ?>"><i class="fas fa-file-invoice me-2"></i>Add Invoice</a></li>
                <li><a class="dropdown-item" href="<?= e($paymentCreateUrl) ?>"><i class="fas fa-money-check-dollar me-2"></i>Add Payment</a></li>
                <li><a class="dropdown-item" href="<?= e($purchaseCreateUrl) ?>"><i class="fas fa-cart-arrow-down me-2"></i>Add Purchase</a></li>
                <?php if ($canViewFinancials): ?>
                <li><a class="dropdown-item" href="<?= e($saleCreateUrl) ?>"><i class="fas fa-sack-dollar me-2"></i>Add Sale</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= e($expenseCreateUrl) ?>"><i class="fas fa-receipt me-2"></i>Add Expense</a></li>
                <li><a class="dropdown-item" href="<?= e($adjustmentCreateUrl) ?>"><i class="fas fa-sliders-h me-2"></i>Add Adjustment</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/deactivate')) ?>" onsubmit="return confirm('Deactivate this job?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit" <?= $isInactive ? 'disabled' : '' ?>>
                            <i class="fas fa-ban me-2"></i><?= $isInactive ? 'Already Deactivated' : 'Deactivate Job' ?>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/jobs')) ?>">Back to Jobs</a>
        </div>
    </div>
</div>

<section class="card index-card index-card-overflow-visible">
    <div class="card-header index-card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs index-card-tabs estate-sale-tabs client-tabs" id="job-tabs" role="tablist" data-detail-tabs>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $detailsTabActive ? ' active' : '' ?>"
                    id="job-details-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#job-tab-details"
                    data-tab="details"
                    aria-controls="job-tab-details"
                    aria-selected="<?= $detailsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-address-card"></i></span>
                    <span class="estate-sale-tab-label">Details</span>
                </button>
            </li>
            <?php if ($canViewFinancials): ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $financialTabActive ? ' active' : '' ?>"
                    id="job-financial-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#job-tab-financial"
                    data-tab="financial"
                    aria-controls="job-tab-financial"
                    aria-selected="<?= $financialTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                    <span class="estate-sale-tab-label">Financial</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $transactionsTabActive ? ' active' : '' ?>"
                    id="job-transactions-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#job-tab-transactions"
                    data-tab="transactions"
                    aria-controls="job-tab-transactions"
                    aria-selected="<?= $transactionsTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
                    <span class="estate-sale-tab-label">Transactions</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $transactionsCount) ?>"><?= e((string) $transactionsCount) ?></span>
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link estate-sale-tab-link<?= $laborTabActive ? ' active' : '' ?>"
                    id="job-labor-tab"
                    type="button"
                    role="tab"
                    data-bs-toggle="tab"
                    data-bs-target="#job-tab-labor"
                    data-tab="labor"
                    aria-controls="job-tab-labor"
                    aria-selected="<?= $laborTabActive ? 'true' : 'false' ?>"
                >
                    <span class="estate-sale-tab-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                    <span class="estate-sale-tab-label">Labor</span>
                    <span class="estate-sale-tab-badge" data-count="<?= e((string) $laborCount) ?>"><?= e((string) $laborCount) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body tab-content" id="job-tab-content">
        <div
            class="tab-pane fade<?= $detailsTabActive ? ' show active' : '' ?>"
            id="job-tab-details"
            role="tabpanel"
            aria-labelledby="job-details-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                <strong class="mb-0"><i class="fas fa-briefcase me-2"></i>Job Details</strong>
                <?php if ($jobStatusOptions !== []): ?>
                    <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/quick-status')) ?>" class="d-flex flex-wrap align-items-center gap-2">
                        <?= csrf_field() ?>
                        <label class="small text-muted mb-0 fw-semibold" for="job-quick-status">Status</label>
                        <select
                            id="job-quick-status"
                            name="status"
                            class="form-select form-select-sm"
                            style="width: auto; min-width: 10rem;"
                            aria-label="Job status"
                            onchange="this.form.submit()"
                        >
                            <?php foreach ($jobStatusOptions as $opt): ?>
                                <?php $opt = strtolower(trim((string) $opt)); ?>
                                <?php if ($opt === '') {
                                    continue;
                                } ?>
                                <option value="<?= e($opt) ?>" <?= $jobStatus === $opt ? 'selected' : '' ?>><?= e($statusLabel($opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>

            <div class="record-row-fields">
                <div class="record-field">
                    <span class="record-label">Job Type</span>
                    <span class="record-value"><?= e(trim((string) ($job['job_type'] ?? '')) ?: '—') ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Job ID</span>
                    <span class="record-value"><?= e((string) ((int) ($job['id'] ?? 0))) ?></span>
                </div>
            </div>

            <div class="record-row-fields mt-3">
                <div class="record-field">
                    <span class="record-label">Client</span>
                    <span class="record-value">
                        <?php
                        $clientId = (int) ($job['client_id'] ?? 0);
                        $clientName = trim((string) ($job['client_name'] ?? ''));
                        ?>
                        <?php if ($clientId > 0 && $clientName !== ''): ?>
                            <a class="link-gray-dark text-decoration-none fw-bold" href="<?= e(url('/clients/' . (string) $clientId)) ?>"><?= e($clientName) ?></a>
                        <?php else: ?>
                            <?= e($clientName !== '' ? $clientName : '—') ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="record-row-fields mt-3">
                <div class="record-field">
                    <span class="record-label">Scheduled Start</span>
                    <span class="record-value"><?= e(format_datetime((string) ($job['scheduled_start_at'] ?? null))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Scheduled End</span>
                    <span class="record-value"><?= e(format_datetime((string) ($job['scheduled_end_at'] ?? null))) ?></span>
                </div>
            </div>

            <div class="record-row-fields mt-3">
                <div class="record-field">
                    <span class="record-label">Actual Start</span>
                    <span class="record-value"><?= e(format_datetime((string) ($job['actual_start_at'] ?? null))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Actual End</span>
                    <span class="record-value"><?= e(format_datetime((string) ($job['actual_end_at'] ?? null))) ?></span>
                </div>
            </div>

            <div class="record-row-fields mt-3">
                <div class="record-field record-field-full">
                    <span class="record-label">Address</span>
                    <span class="record-value record-value-stack">
                        <?php if ($mapsAddressUrl !== '' && $addressStreet !== '—'): ?>
                            <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e($addressStreet) ?>
                            </a>
                            <?php if ($addressRegion !== ''): ?>
                                <a href="<?= e($mapsAddressUrl) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= e($addressRegion) ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span><?= e($addressStreet) ?></span>
                            <?php if ($addressRegion !== ''): ?>
                                <span><?= e($addressRegion) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="record-row-fields mt-3 mb-0">
                <div class="record-field record-field-full">
                    <span class="record-label">Primary Note</span>
                    <span class="record-value"><?= e(trim((string) ($job['notes'] ?? '')) ?: '—') ?></span>
                </div>
            </div>

            <?php if ($subAssignment !== null): ?>
                <?php
                $subStatus = strtolower(trim((string) ($subAssignment['status'] ?? 'assigned')));
                $subName = trim((string) ($subAssignment['subcontractor_name'] ?? ''));
                $subId = (int) ($subAssignment['subcontractor_id'] ?? 0);
                $subStatusLabel = ucwords(str_replace('_', ' ', $subStatus));
                ?>
                <hr class="my-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 mb-0"><i class="fas fa-hard-hat me-2"></i>Subbed out</h2>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e($subOutEditUrl) ?>">Manage</a>
                </div>
                <div class="record-row-fields">
                    <div class="record-field">
                        <span class="record-label">Sub-Contractor</span>
                        <span class="record-value">
                            <?php if ($subId > 0 && $subName !== ''): ?>
                                <a class="link-gray-dark text-decoration-none fw-bold" href="<?= e(url('/subs/' . (string) $subId)) ?>"><?= e($subName) ?></a>
                            <?php else: ?>
                                <?= e($subName !== '' ? $subName : '—') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Status</span>
                        <span class="record-value"><?= e($subStatusLabel) ?></span>
                    </div>
                </div>
                <?php if ($canViewFinancials && $subStatus === 'completed'): ?>
                    <div class="record-row-fields mt-3">
                        <div class="record-field">
                            <span class="record-label">Sub-contractor charged</span>
                            <span class="record-value"><?= ($subAssignment['sub_amount'] ?? null) !== null ? e($formatMoney((float) $subAssignment['sub_amount'])) : '—' ?></span>
                        </div>
                        <div class="record-field">
                            <span class="record-label">Our cut</span>
                            <span class="record-value"><?= ($subAssignment['our_cut'] ?? null) !== null ? e($formatMoney((float) $subAssignment['our_cut'])) : '—' ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php $subNotes = trim((string) ($subAssignment['notes'] ?? '')); ?>
                <?php if ($subNotes !== ''): ?>
                    <div class="record-row-fields mt-3 mb-0">
                        <div class="record-field record-field-full">
                            <span class="record-label">Sub-contractor notes</span>
                            <span class="record-value"><?= nl2br(e($subNotes)) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($hasCloseout): ?>
                <hr class="my-4">
                <h2 class="h6 mb-3"><i class="fas fa-clipboard-check me-2"></i>Job close-out</h2>
                <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/closeout')) ?>" class="row g-3 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="closeout_truck_loaded" value="1" id="closeout_truck_loaded" <?= ((int) ($job['closeout_truck_loaded'] ?? 0)) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="closeout_truck_loaded">Truck loaded</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="closeout_site_clean" value="1" id="closeout_site_clean" <?= ((int) ($job['closeout_site_clean'] ?? 0)) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="closeout_site_clean">Site clean</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="closeout_signature_name">Signature / name</label>
                        <input class="form-control" type="text" name="closeout_signature_name" id="closeout_signature_name" value="<?= e((string) ($job['closeout_signature_name'] ?? '')) ?>" maxlength="190" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="mark_closeout_complete" value="1" id="mark_closeout_complete" <?= trim((string) ($job['closeout_completed_at'] ?? '')) !== '' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mark_closeout_complete">Mark close-out complete (sets timestamp)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save close-out</button>
                        <?php if (trim((string) ($job['closeout_completed_at'] ?? '')) !== ''): ?>
                            <span class="small text-muted ms-2">Completed: <?= e($formatDocDate((string) ($job['closeout_completed_at'] ?? ''))) ?></span>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($canViewFinancials): ?>
        <div
            class="tab-pane fade<?= $financialTabActive ? ' show active' : '' ?>"
            id="job-tab-financial"
            role="tabpanel"
            aria-labelledby="job-financial-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-chart-line me-2"></i>Financial Snapshot</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e($saleCreateUrl) ?>">Add Sale</a>
            </div>
            <div class="jt-financial-snapshot">
                <div class="jt-fin-snapshot-row">
                    <div class="record-field">
                        <span class="record-label">Invoice Gross</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['job_gross'] ?? ($financial['invoice_gross'] ?? ($financial['raw_gross'] ?? 0))), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Sales Gross</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['sales_gross'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Job Gross</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['total_gross'] ?? ($financial['gross'] ?? 0)), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Payments</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['payments'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="jt-fin-snapshot-spacer" aria-hidden="true"></div>
                </div>
                <div class="jt-fin-snapshot-row">
                    <div class="record-field">
                        <span class="record-label">Labor</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['labor_cost'] ?? ($financial['labor'] ?? 0)), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Expenses</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['expenses'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Adjustments</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['adjustments'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Tips</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['tips'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="jt-fin-snapshot-spacer" aria-hidden="true"></div>
                </div>
                <div class="jt-fin-snapshot-row">
                    <div class="record-field">
                        <span class="record-label">Job Net</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['job_net'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Total Net</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['total_net'] ?? ($financial['net'] ?? 0)), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Balance Due</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['balance'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="record-field">
                        <span class="record-label">Sales Net</span>
                        <span class="record-value">$<?= e(number_format((float) ($financial['sales_net'] ?? 0), 2)) ?></span>
                    </div>
                    <div class="jt-fin-snapshot-spacer" aria-hidden="true"></div>
                </div>
            </div>
        </div>

        <div
            class="tab-pane fade<?= $transactionsTabActive ? ' show active' : '' ?>"
            id="job-tab-transactions"
            role="tabpanel"
            aria-labelledby="job-transactions-tab"
            tabindex="0"
        >
            <div class="mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Billing &amp; Sales</h2>
                    <?php if ($canViewFinancials): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e($saleCreateUrl) ?>">Add Sale</a>
                    <?php endif; ?>
                </div>
                <?php if ($transactions === []): ?>
                    <div class="record-empty">No financial transactions yet.</div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($transactions as $row): ?>
                            <li class="mb-1">
                                <a class="fw-bold text-decoration-none" href="<?= e((string) ($row['url'] ?? '#')) ?>"><?= e($row['number']) ?></a>
                                <span class="small muted">· <?= e($row['status']) ?> · <?= e($row['date']) ?> · <?= e((string) ($row['total_label'] ?? 'Total')) ?> <?= e($formatMoney((float) ($row['total_value'] ?? 0))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <?php
            $disposalWeightTotal = (float) ($disposalWeightTotal ?? 0);
            $disposalWeightTotalDisplay = $disposalWeightTotal > 0 ? Expense::formatWeightDisplay($disposalWeightTotal) : '';
            ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-0"><i class="fas fa-receipt me-2"></i>Expenses</h2>
                    <?php if ($disposalWeightTotalDisplay !== ''): ?>
                        <div class="small muted">Disposal weight: <?= e($disposalWeightTotalDisplay) ?></div>
                    <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="<?= e($expenseCreateUrl) ?>">Add Expense</a>
            </div>
            <?php if ($expenses === []): ?>
                <div class="record-empty">No expenses yet.</div>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($expenses as $expense): ?>
                        <?php
                        if (!is_array($expense)) {
                            continue;
                        }
                        $expenseId = (int) ($expense['id'] ?? 0);
                        $expenseDate = $formatDocDate((string) ($expense['expense_date'] ?? ($expense['created_at'] ?? '')));
                        $expenseCategory = trim((string) ($expense['category'] ?? ''));
                        $expensePaymentMethod = trim((string) ($expense['payment_method'] ?? ''));
                        $expenseNote = trim((string) ($expense['note'] ?? ''));
                        $expenseWeightDisplay = Expense::isDisposalCategory($expenseCategory)
                            ? Expense::formatWeightDisplay($expense['weight'] ?? null)
                            : '';
                        $expenseEmployeeName = trim((string) ($expense['employee_name'] ?? ''));
                        $expenseIsBonus = Expense::isBonusCategory($expenseCategory);
                        $expenseTitle = $expenseIsBonus && $expenseEmployeeName !== ''
                            ? ('Bonus — ' . $expenseEmployeeName)
                            : ($expenseCategory !== '' ? $expenseCategory : ($expenseNote !== '' ? $expenseNote : ('Expense #' . (string) $expenseId)));
                        ?>
                        <li class="mb-1">
                            <a class="fw-bold text-decoration-none" href="<?= e(url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId)) ?>"><?= e($expenseTitle) ?></a>
                            <span class="small muted">· <?= e($expenseDate) ?> · $<?= e(number_format((float) ($expense['amount'] ?? 0), 2)) ?></span>
                            <?php if ($expenseIsBonus): ?>
                                <span class="small muted">· labor</span>
                            <?php endif; ?>
                            <?php if ($expenseWeightDisplay !== ''): ?>
                                <span class="small muted">· <?= e($expenseWeightDisplay) ?></span>
                            <?php endif; ?>
                            <?php if ($expenseCategory !== '' && $expenseTitle !== $expenseCategory): ?>
                                <span class="small muted">· <?= e($expenseCategory) ?></span>
                            <?php endif; ?>
                            <?php if ($expensePaymentMethod !== ''): ?>
                                <span class="small muted">· <?= e($expensePaymentMethod) ?></span>
                            <?php endif; ?>
                            <?php if ($expenseNote !== ''): ?>
                                <span class="small muted">· <?= e($expenseNote) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <hr class="my-4">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-sliders-h me-2"></i>Adjustments</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e($adjustmentCreateUrl) ?>">Add Adjustment</a>
            </div>
            <?php if ($adjustments === []): ?>
                <div class="record-empty mb-0">No adjustments yet.</div>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($adjustments as $adjustment): ?>
                        <?php
                        if (!is_array($adjustment)) {
                            continue;
                        }
                        $adjustmentId = (int) ($adjustment['id'] ?? 0);
                        $adjustmentDate = $formatDocDate((string) ($adjustment['adjustment_date'] ?? ($adjustment['created_at'] ?? '')));
                        $adjustmentName = trim((string) ($adjustment['name'] ?? ''));
                        if ($adjustmentName === '') {
                            $adjustmentName = 'Adjustment #' . (string) $adjustmentId;
                        }
                        $adjustmentNote = trim((string) ($adjustment['note'] ?? ''));
                        ?>
                        <li class="mb-1">
                            <a class="fw-bold text-decoration-none" href="<?= e(url('/jobs/' . (string) $jobId . '/adjustments/' . (string) $adjustmentId)) ?>"><?= e($adjustmentName) ?></a>
                            <span class="small muted">· <?= e($adjustmentDate) ?> · $<?= e(number_format((float) ($adjustment['amount'] ?? 0), 2)) ?></span>
                            <?php if ($adjustmentNote !== ''): ?>
                                <span class="small muted">· <?= e($adjustmentNote) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div
            class="tab-pane fade<?= $laborTabActive ? ' show active' : '' ?>"
            id="job-tab-labor"
            role="tabpanel"
            aria-labelledby="job-labor-tab"
            tabindex="0"
        >
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                    <?php if ($assignedEmployees !== []): ?>
                        <div class="form-check flex-shrink-0 mb-0">
                            <input class="form-check-input" type="checkbox" id="jt-bulk-select-all" title="Select all" aria-label="Select all employees for mass punch">
                        </div>
                    <?php endif; ?>
                    <h2 class="h6 mb-0"><i class="fas fa-user-clock me-2"></i>Punch Clock</h2>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                    <a class="btn btn-outline-success btn-sm" href="<?= e($bonusPayoutCreateUrl) ?>"><i class="fas fa-gift me-1"></i>Add Bonus</a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e($employeeAddUrl) ?>"><i class="fas fa-user-plus me-1"></i>Add Employee</a>
                    <?php if ($assignedEmployees !== []): ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                                Mass punch
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button type="button" class="dropdown-item" id="jt-bulk-punch-in">Punch in selected</button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item" id="jt-bulk-punch-out">Punch out selected</button>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($assignedEmployees === []): ?>
                <div class="record-empty mb-4">No employees assigned to this job yet.</div>
            <?php else: ?>
                <form id="jt-bulk-punch-form" method="post" action="<?= e($bulkPunchUrl) ?>" class="d-none" aria-hidden="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bulk_action" id="jt-bulk-action" value="">
                </form>
                <div class="record-list-simple mb-4">
                    <?php foreach ($assignedEmployees as $employee): ?>
                        <?php
                        if (!is_array($employee)) {
                            continue;
                        }
                        $employeeId = (int) ($employee['employee_id'] ?? 0);
                        if ($employeeId <= 0) {
                            continue;
                        }
                        $displayName = trim((string) ($employee['display_name'] ?? ''));
                        if ($displayName === '') {
                            $displayName = 'Employee #' . (string) $employeeId;
                        }
                        $openEntryId = (int) ($employee['open_entry_id'] ?? 0);
                        $isOpen = $openEntryId > 0;
                        $isOpenForThisJob = (bool) ($employee['is_open_for_this_job'] ?? false);
                        $openJobTitle = trim((string) ($employee['open_job_title'] ?? ''));
                        if ($openJobTitle === '') {
                            $openJobTitle = 'Non-Job Time';
                        }
                        $openClockInAt = trim((string) ($employee['open_clock_in_at'] ?? ''));
                        $linkedUserEmail = trim((string) ($employee['linked_user_email'] ?? ''));
                        $canManageEmployeeTime = is_site_admin() || workspace_role() === 'admin';
                        $addTimeEntryUrl = url('/time-tracking/create?job_id=' . rawurlencode((string) $jobId) . '&employee_id=' . rawurlencode((string) $employeeId) . '&return_to=' . rawurlencode('/jobs/' . (string) $jobId . '?tab=labor'));
                        $canRemoveEmployee = (bool) ($employee['can_remove'] ?? false);
                        $employeeBonuses = $laborBonusesByEmployeeId[$employeeId] ?? [];
                        $employeeBonusTotal = 0.0;
                        foreach ($employeeBonuses as $employeeBonusRow) {
                            if (!is_array($employeeBonusRow)) {
                                continue;
                            }
                            $employeeBonusTotal += (float) ($employeeBonusRow['amount'] ?? 0);
                        }
                        ?>
                        <article class="record-row-simple">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">
                                    <div class="form-check flex-shrink-0 pt-1">
                                        <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= (string) $employeeId ?>" form="jt-bulk-punch-form" id="jt-bulk-emp-<?= (string) $employeeId ?>" aria-label="<?= e('Select ' . $displayName) ?>">
                                    </div>
                                    <div class="record-row-main mb-0 min-w-0">
                                    <h3 class="record-title-simple mb-0"><?= e($displayName) ?></h3>
                                    <div class="record-subline small muted mt-1">
                                        <?php if ($isOpen): ?>
                                            <?php if ($isOpenForThisJob): ?>
                                                <span class="badge text-bg-success">Punched In</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning">Open on Other Job</span>
                                            <?php endif; ?>
                                            <span>· <?= e($openJobTitle) ?></span>
                                            <span>· <?= e(format_datetime($openClockInAt !== '' ? $openClockInAt : null)) ?></span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Punched Out</span>
                                        <?php endif; ?>
                                        <?php
                                        $assignedLaborTotal = $laborTotalsByEmployeeId[$employeeId] ?? null;
                                        if (is_array($assignedLaborTotal) && (int) ($assignedLaborTotal['total_minutes'] ?? 0) > 0):
                                            ?>
                                            <span>· <?= e($formatDuration((int) ($assignedLaborTotal['total_minutes'] ?? 0))) ?> on this job</span>
                                        <?php endif; ?>
                                        <?php if ($employeeBonusTotal > 0): ?>
                                            <span>· $<?= e(number_format($employeeBonusTotal, 2)) ?> bonus</span>
                                        <?php endif; ?>
                                        <?php if ($linkedUserEmail !== ''): ?><span>· <?= e($linkedUserEmail) ?></span><?php endif; ?>
                                    </div>
                                    <?php if ($employeeBonuses !== []): ?>
                                        <ul class="list-unstyled small mb-0 mt-2 ps-1">
                                            <?php foreach ($employeeBonuses as $employeeBonusRow): ?>
                                                <?php
                                                if (!is_array($employeeBonusRow)) {
                                                    continue;
                                                }
                                                $bonusExpenseId = (int) ($employeeBonusRow['id'] ?? 0);
                                                $bonusDate = $formatDocDate((string) ($employeeBonusRow['expense_date'] ?? ''));
                                                $bonusNote = trim((string) ($employeeBonusRow['note'] ?? ''));
                                                ?>
                                                <li class="mb-1">
                                                    <a class="text-decoration-none" href="<?= e(url('/jobs/' . (string) $jobId . '/expenses/' . (string) $bonusExpenseId)) ?>">Bonus</a>
                                                    <span class="muted">· <?= e($bonusDate) ?> · $<?= e(number_format((float) ($employeeBonusRow['amount'] ?? 0), 2)) ?></span>
                                                    <?php if ($bonusNote !== ''): ?>
                                                        <span class="muted">· <?= e($bonusNote) ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap justify-content-end">
                                    <a class="btn btn-outline-success btn-sm" href="<?= e($bonusPayoutUrlForEmployee($employeeId)) ?>"><i class="fas fa-gift me-1"></i>Add Bonus</a>
                                    <?php if ($canManageEmployeeTime): ?>
                                        <a class="btn btn-outline-primary btn-sm" href="<?= e($addTimeEntryUrl) ?>"><i class="fas fa-plus me-1"></i>Add Time Entry</a>
                                    <?php endif; ?>
                                    <?php if ($isOpen): ?>
                                        <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/employees/' . (string) $employeeId . '/punch-out')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fas fa-stop me-1"></i>Punch Out</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/employees/' . (string) $employeeId . '/punch-in')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-play me-1"></i>Punch In</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canRemoveEmployee): ?>
                                        <form method="post" action="<?= e(url('/jobs/' . (string) $jobId . '/employees/' . (string) $employeeId . '/remove')) ?>" onsubmit="return confirm('Remove this employee from the job?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="fas fa-user-minus me-1"></i>Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <script>
                    (function () {
                        var form = document.getElementById('jt-bulk-punch-form');
                        var actionInput = document.getElementById('jt-bulk-action');
                        var selectAll = document.getElementById('jt-bulk-select-all');
                        if (!form || !actionInput) {
                            return;
                        }
                        function getEmployeeBoxes() {
                            return document.querySelectorAll('input[form="jt-bulk-punch-form"][name="employee_ids[]"]');
                        }
                        function syncSelectAll() {
                            if (!selectAll) {
                                return;
                            }
                            var boxes = getEmployeeBoxes();
                            var n = boxes.length;
                            var checked = 0;
                            for (var i = 0; i < n; i++) {
                                if (boxes[i].checked) {
                                    checked++;
                                }
                            }
                            selectAll.indeterminate = checked > 0 && checked < n;
                            selectAll.checked = n > 0 && checked === n;
                        }
                        if (selectAll) {
                            selectAll.addEventListener('change', function () {
                                var boxes = getEmployeeBoxes();
                                var on = selectAll.checked;
                                for (var i = 0; i < boxes.length; i++) {
                                    boxes[i].checked = on;
                                }
                                selectAll.indeterminate = false;
                            });
                            var boxesInit = getEmployeeBoxes();
                            for (var j = 0; j < boxesInit.length; j++) {
                                boxesInit[j].addEventListener('change', syncSelectAll);
                            }
                            syncSelectAll();
                        }
                        function submitBulk(action) {
                            var boxes = getEmployeeBoxes();
                            var any = false;
                            for (var i = 0; i < boxes.length; i++) {
                                if (boxes[i].checked) {
                                    any = true;
                                    break;
                                }
                            }
                            if (!any) {
                                window.alert('Select at least one employee.');
                                return;
                            }
                            actionInput.value = action;
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                form.submit();
                            }
                        }
                        var btnIn = document.getElementById('jt-bulk-punch-in');
                        var btnOut = document.getElementById('jt-bulk-punch-out');
                        if (btnIn) {
                            btnIn.addEventListener('click', function () { submitBulk('in'); });
                        }
                        if (btnOut) {
                            btnOut.addEventListener('click', function () { submitBulk('out'); });
                        }
                    })();
                </script>
            <?php endif; ?>

            <hr class="my-4">
            <h2 class="h6 mb-3"><i class="fas fa-clock me-2"></i>Time Snapshot</h2>
            <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2 mb-4">
                <div class="record-field">
                    <span class="record-label">Entries</span>
                    <span class="record-value"><?= e((string) ((int) ($timeSummary['entries'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Open Entries</span>
                    <span class="record-value"><?= e((string) ((int) ($timeSummary['open_entries'] ?? 0))) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Total Hours</span>
                    <span class="record-value"><?= e(number_format((float) ($timeSummary['hours'] ?? 0), 2)) ?></span>
                </div>
                <?php if ($canViewFinancials): ?>
                <div class="record-field">
                    <span class="record-label">Labor Cost</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['labor_cost'] ?? ($financial['labor'] ?? 0)), 2)) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">
            <h2 class="h6 mb-3"><i class="fas fa-users me-2"></i>Time by Employee</h2>
            <?php if ($employeeLaborTotals === []): ?>
                <div class="record-empty mb-4">No employee time logged on this job yet.</div>
            <?php else: ?>
                <div class="record-list-simple mb-4">
                    <?php foreach ($employeeLaborTotals as $laborTotalRow): ?>
                        <?php
                        if (!is_array($laborTotalRow)) {
                            continue;
                        }
                        $laborEmployeeId = (int) ($laborTotalRow['employee_id'] ?? 0);
                        if ($laborEmployeeId <= 0) {
                            continue;
                        }
                        $laborEmployeeName = trim((string) ($laborTotalRow['employee_name'] ?? '')) ?: ('Employee #' . (string) $laborEmployeeId);
                        $laborMinutes = (int) ($laborTotalRow['total_minutes'] ?? 0);
                        $laborOpenEntries = (int) ($laborTotalRow['open_entries'] ?? 0);
                        ?>
                        <article class="record-row-simple">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($laborEmployeeName) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-<?= $canViewFinancials ? '4' : '3' ?> record-row-fields-mobile-2">
                                <div class="record-field">
                                    <span class="record-label">Total on Job</span>
                                    <span class="record-value"><?= e($formatDuration($laborMinutes)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Hours</span>
                                    <span class="record-value"><?= e(number_format((float) ($laborTotalRow['total_hours'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Entries</span>
                                    <span class="record-value"><?= e((string) ((int) ($laborTotalRow['entry_count'] ?? 0))) ?><?= $laborOpenEntries > 0 ? ' (' . (string) $laborOpenEntries . ' open)' : '' ?></span>
                                </div>
                                <?php if ($canViewFinancials): ?>
                                <div class="record-field">
                                    <span class="record-label">Labor Cost</span>
                                    <span class="record-value">$<?= e(number_format((float) ($laborTotalRow['labor_cost'] ?? 0), 2)) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr class="my-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="record-label mb-0">Employee Time Logs</span>
            </div>
            <?php if ($timeLogs === []): ?>
                <div class="record-empty mb-4">No time logs for this job yet.</div>
            <?php else: ?>
                <ul class="list-unstyled mb-4">
                    <?php foreach ($timeLogs as $entry): ?>
                        <?php
                        if (!is_array($entry)) {
                            continue;
                        }
                        $entryId = (int) ($entry['id'] ?? 0);
                        $minutes = (int) ($entry['duration_minutes'] ?? 0);
                        $hourlyRate = (float) ($entry['hourly_rate'] ?? 0);
                        $laborCost = (float) ($entry['labor_cost'] ?? 0);
                        $clockOutAt = trim((string) ($entry['clock_out_at'] ?? ''));
                        $entryUrl = $entryId > 0 ? url('/time-tracking/' . (string) $entryId) . '?from=job&job_id=' . (string) $jobId : '#';
                        ?>
                        <li class="mb-2">
                            <a class="fw-bold text-decoration-none" href="<?= e($entryUrl) ?>"><?= e(trim((string) ($entry['employee_name'] ?? '')) ?: ('Employee #' . (string) ((int) ($entry['employee_id'] ?? 0)))) ?></a>
                            <ul class="time-log-meta-list small muted">
                                <li>In <?= e(format_datetime((string) ($entry['clock_in_at'] ?? null))) ?></li>
                                <li>Out <?= e(format_datetime($clockOutAt !== '' ? $clockOutAt : null)) ?></li>
                                <li><?= e($formatDuration($minutes)) ?></li>
                                <?php if ($canViewFinancials): ?>
                                <li>Rate $<?= e(number_format($hourlyRate, 2)) ?></li>
                                <li>Cost $<?= e(number_format($laborCost, 2)) ?></li>
                                <?php endif; ?>
                                <?php if ($clockOutAt === ''): ?><li><span class="badge text-bg-warning">Open</span></li><?php endif; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const tabList = document.getElementById('job-tabs');
    const syncTabUrl = (tabName) => {
        if (!tabName) {
            return;
        }
        const url = new URL(window.location.href);
        if (tabName === 'details') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tabName);
        }
        const next = url.pathname + url.search + url.hash;
        const current = window.location.pathname + window.location.search + window.location.hash;
        if (next !== current) {
            window.history.replaceState(null, '', next);
        }
    };

    if (tabList) {
        tabList.addEventListener('shown.bs.tab', (event) => {
            const trigger = event.target;
            if (!(trigger instanceof HTMLElement)) {
                return;
            }
            syncTabUrl(String(trigger.dataset.tab || '').trim());
        });

        const urlTab = new URLSearchParams(window.location.search).get('tab');
        if (urlTab) {
            const normalizedTab = urlTab.toLowerCase();
            const trigger = tabList.querySelector('[data-tab="' + normalizedTab + '"]');
            if (trigger instanceof HTMLElement && !trigger.classList.contains('active') && window.bootstrap) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    }
});
</script>
