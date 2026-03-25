<?php
$job = is_array($job ?? null) ? $job : [];
$financial = is_array($financial ?? null) ? $financial : [];
$timeSummary = is_array($timeSummary ?? null) ? $timeSummary : [];
$timeLogs = is_array($timeLogs ?? null) ? $timeLogs : [];
$expenses = is_array($expenses ?? null) ? $expenses : [];
$adjustments = is_array($adjustments ?? null) ? $adjustments : [];
$documents = is_array($documents ?? null) ? $documents : [];
$assignedEmployees = is_array($assignedEmployees ?? null) ? $assignedEmployees : [];

$addressStreet = implode(', ', array_filter([
    trim((string) ($job['address_line1'] ?? '')),
    trim((string) ($job['address_line2'] ?? '')),
], static fn (string $value): bool => $value !== ''));
$addressRegion = implode(', ', array_filter([
    trim((string) ($job['city'] ?? '')),
    trim((string) ($job['state'] ?? '')),
    trim((string) ($job['postal_code'] ?? '')),
], static fn (string $value): bool => $value !== ''));
if ($addressStreet === '' && $addressRegion === '') {
    $addressStreet = '—';
}

$title = trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)));
$jobId = (int) ($job['id'] ?? 0);
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
$expenseCreateUrl = url('/jobs/' . (string) $jobId . '/expenses/create');
$adjustmentCreateUrl = url('/jobs/' . (string) $jobId . '/adjustments/create');
$timeEntryCreateUrl = url('/time-tracking/create') . '?job_id=' . (string) $jobId . '&return_to=' . urlencode('/jobs/' . (string) $jobId);
$employeeAddUrl = url('/jobs/' . (string) $jobId . '/employees/add');
$bulkPunchUrl = url('/jobs/' . (string) $jobId . '/employees/bulk-punch');
$jobStatus = strtolower(trim((string) ($job['status'] ?? 'pending')));
$isInactive = $jobStatus === 'inactive' || (array_key_exists('is_active', $job) && (int) ($job['is_active'] ?? 1) === 0);

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
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($title) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isInactive): ?>
            <span class="badge text-bg-secondary align-self-center">Deactivated</span>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('/jobs/' . (string) $jobId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Job</a></li>
                <li><a class="dropdown-item" href="<?= e($timeEntryCreateUrl) ?>"><i class="fas fa-clock me-2"></i>Add Time Entry</a></li>
                <li><a class="dropdown-item" href="<?= e($employeeAddUrl) ?>"><i class="fas fa-user-plus me-2"></i>Add Employee</a></li>
                <li><a class="dropdown-item" href="<?= e($estimateCreateUrl) ?>"><i class="fas fa-file-signature me-2"></i>Add Estimate</a></li>
                <li><a class="dropdown-item" href="<?= e($invoiceCreateUrl) ?>"><i class="fas fa-file-invoice me-2"></i>Add Invoice</a></li>
                <li><a class="dropdown-item" href="<?= e($paymentCreateUrl) ?>"><i class="fas fa-money-check-dollar me-2"></i>Add Payment</a></li>
                <li><a class="dropdown-item" href="<?= e($purchaseCreateUrl) ?>"><i class="fas fa-cart-arrow-down me-2"></i>Add Purchase</a></li>
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
        <a class="btn btn-outline-secondary" href="<?= e(url('/jobs')) ?>">Back to Jobs</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-briefcase me-2"></i>Job Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value text-capitalize"><?= e((string) ($job['status'] ?? 'pending')) ?></span>
            </div>
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
                    <span><?= e($addressStreet) ?></span>
                    <?php if ($addressRegion !== ''): ?>
                        <span><?= e($addressRegion) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="record-row-fields mt-3">
            <div class="record-field record-field-full">
                <span class="record-label">Primary Note</span>
                <span class="record-value"><?= e(trim((string) ($job['notes'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>

<?php if (array_key_exists('closeout_truck_loaded', $job)): ?>
<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-clipboard-check me-2"></i>Job close-out</strong>
    </div>
    <div class="card-body">
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
    </div>
</section>
<?php endif; ?>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-chart-line me-2"></i>Financial Snapshot</strong>
    </div>
    <div class="card-body">
        <div class="jt-financial-snapshot">
            <div class="jt-fin-snapshot-row">
                <div class="record-field">
                    <span class="record-label">Job Gross</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['job_gross'] ?? ($financial['invoice_gross'] ?? ($financial['raw_gross'] ?? 0))), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Sales Gross</span>
                    <span class="record-value">$<?= e(number_format((float) ($financial['sales_gross'] ?? 0), 2)) ?></span>
                </div>
                <div class="record-field">
                    <span class="record-label">Total Gross</span>
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
        <hr class="my-4">
        <?php
        $transactions = [];
        $formatMoney = static fn (float $value): string => '$' . number_format($value, 2);
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
            $saleName = trim((string) ($sale['name'] ?? ''));
            if ($saleName === '') {
                $saleName = 'Sale #' . (string) $saleId;
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
                'number' => $saleName,
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
        ?>
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

        <hr class="my-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="record-label mb-0">Expenses</span>
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
                    $expenseTitle = $expenseCategory !== '' ? $expenseCategory : ($expenseNote !== '' ? $expenseNote : ('Expense #' . (string) $expenseId));
                    ?>
                    <li class="mb-1">
                        <a class="fw-bold text-decoration-none" href="<?= e(url('/jobs/' . (string) $jobId . '/expenses/' . (string) $expenseId)) ?>"><?= e($expenseTitle) ?></a>
                        <span class="small muted">· <?= e($expenseDate) ?> · $<?= e(number_format((float) ($expense['amount'] ?? 0), 2)) ?></span>
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
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="record-label mb-0">Adjustments</span>
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
</section>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-clock me-2"></i>Time Snapshot</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2">
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
            <div class="record-field">
                <span class="record-label">Labor Cost</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['labor_cost'] ?? ($financial['labor'] ?? 0)), 2)) ?></span>
            </div>
        </div>

        <hr class="my-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="record-label mb-0">Employee Time Logs</span>
        </div>
        <?php if ($timeLogs === []): ?>
            <div class="record-empty mb-0">No time logs for this job yet.</div>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
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
                            <li>Rate $<?= e(number_format($hourlyRate, 2)) ?></li>
                            <li>Cost $<?= e(number_format($laborCost, 2)) ?></li>
                            <?php if ($clockOutAt === ''): ?><li><span class="badge text-bg-warning">Open</span></li><?php endif; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card mt-3">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <strong><i class="fas fa-users me-2"></i>Assigned Employees</strong>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
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
    <div class="card-body">
        <?php if ($assignedEmployees === []): ?>
            <div class="record-empty mb-0">No employees assigned to this job yet.</div>
        <?php else: ?>
            <form id="jt-bulk-punch-form" method="post" action="<?= e($bulkPunchUrl) ?>" class="d-none" aria-hidden="true">
                <?= csrf_field() ?>
                <input type="hidden" name="bulk_action" id="jt-bulk-action" value="">
            </form>
            <div class="record-list-simple">
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
                    $addTimeEntryUrl = url('/time-tracking/create?job_id=' . rawurlencode((string) $jobId) . '&employee_id=' . rawurlencode((string) $employeeId) . '&return_to=' . rawurlencode('/jobs/' . (string) $jobId));
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
                                    <?php if ($linkedUserEmail !== ''): ?><span>· <?= e($linkedUserEmail) ?></span><?php endif; ?>
                                </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
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
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <script>
                (function () {
                    var form = document.getElementById('jt-bulk-punch-form');
                    var actionInput = document.getElementById('jt-bulk-action');
                    if (!form || !actionInput) {
                        return;
                    }
                    function submitBulk(action) {
                        var boxes = document.querySelectorAll('input[form="jt-bulk-punch-form"][name="employee_ids[]"]');
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
    </div>
</section>
