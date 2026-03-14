<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$report = is_array($report ?? null) ? $report : [];

$sales = is_array($report['sales'] ?? null) ? $report['sales'] : [];
$service = is_array($report['service'] ?? null) ? $report['service'] : [];
$expenses = is_array($report['expenses'] ?? null) ? $report['expenses'] : [];
$purchases = is_array($report['purchases'] ?? null) ? $report['purchases'] : [];
$overall = is_array($report['overall'] ?? null) ? $report['overall'] : [];
$lists = is_array($report['lists'] ?? null) ? $report['lists'] : [];

$jobs = is_array($lists['jobs'] ?? null) ? $lists['jobs'] : [];
$salesList = is_array($lists['sales'] ?? null) ? $lists['sales'] : [];
$expensesByCategory = is_array($expenses['by_category'] ?? null) ? $expenses['by_category'] : [];

$formatMoney = static function ($value): string {
    return '$' . number_format((float) $value, 2);
};

$formatDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '—';
    }
    return date('m/d/Y', $ts);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Reports</h1>
        <p class="muted">Period summary for service, sales, expenses, and purchases</p>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Time Period</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/reports')) ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="report-from">From</label>
                <input id="report-from" class="form-control" type="date" name="from" value="<?= e($fromDate) ?>" />
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="report-to">To</label>
                <input id="report-to" class="form-control" type="date" name="to" value="<?= e($toDate) ?>" />
            </div>
            <div class="col-12 col-md-4 d-grid d-md-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Run Report</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/reports')) ?>">Current Month</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-chart-column me-2"></i>Totals</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4">
            <div class="record-field">
                <span class="record-label">Overall Gross</span>
                <span class="record-value"><?= e($formatMoney($overall['gross'] ?? 0)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Overall Net</span>
                <span class="record-value"><?= e($formatMoney($overall['net'] ?? 0)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Expense Total</span>
                <span class="record-value"><?= e($formatMoney($expenses['total'] ?? 0)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Purchase Total</span>
                <span class="record-value"><?= e($formatMoney($purchases['total'] ?? 0)) ?></span>
            </div>
        </div>
    </div>
</section>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100">
            <div class="card-header index-card-header"><strong><i class="fas fa-file-invoice-dollar me-2"></i>Service (Invoices)</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-2">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><?= e((string) ((int) ($service['count'] ?? 0))) ?></span></div>
                    <div class="record-field"><span class="record-label">Gross</span><span class="record-value"><?= e($formatMoney($service['gross'] ?? 0)) ?></span></div>
                    <div class="record-field"><span class="record-label">Job Expenses</span><span class="record-value"><?= e($formatMoney($service['job_expenses'] ?? 0)) ?></span></div>
                    <div class="record-field"><span class="record-label">Net</span><span class="record-value"><?= e($formatMoney($service['net'] ?? 0)) ?></span></div>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100">
            <div class="card-header index-card-header"><strong><i class="fas fa-sack-dollar me-2"></i>Sales</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-3">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><?= e((string) ((int) ($sales['count'] ?? 0))) ?></span></div>
                    <div class="record-field"><span class="record-label">Gross</span><span class="record-value"><?= e($formatMoney($sales['gross'] ?? 0)) ?></span></div>
                    <div class="record-field"><span class="record-label">Net</span><span class="record-value"><?= e($formatMoney($sales['net'] ?? 0)) ?></span></div>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100">
            <div class="card-header index-card-header"><strong><i class="fas fa-receipt me-2"></i>Expenses</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-4 mb-3">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><?= e((string) ((int) ($expenses['count'] ?? 0))) ?></span></div>
                    <div class="record-field"><span class="record-label">Job Expenses</span><span class="record-value"><?= e($formatMoney($expenses['job_total'] ?? 0)) ?></span></div>
                    <div class="record-field"><span class="record-label">General Expenses</span><span class="record-value"><?= e($formatMoney($expenses['general_total'] ?? 0)) ?></span></div>
                    <div class="record-field"><span class="record-label">Total</span><span class="record-value"><?= e($formatMoney($expenses['total'] ?? 0)) ?></span></div>
                </div>

                <?php if ($expensesByCategory === []): ?>
                    <div class="record-empty">No category breakdown available for this period.</div>
                <?php else: ?>
                    <div class="simple-list-table">
                        <?php foreach ($expensesByCategory as $row): ?>
                            <?php
                            $category = trim((string) ($row['category'] ?? 'Uncategorized')) ?: 'Uncategorized';
                            $count = (int) ($row['count'] ?? 0);
                            $total = (float) ($row['total'] ?? 0);
                            ?>
                            <div class="simple-list-row">
                                <span class="simple-list-title"><?= e($category) ?></span>
                                <span class="simple-list-meta"><?= e((string) $count) ?> record(s) · <?= e($formatMoney($total)) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100">
            <div class="card-header index-card-header"><strong><i class="fas fa-cart-arrow-down me-2"></i>Purchasing</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-2">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><?= e((string) ((int) ($purchases['count'] ?? 0))) ?></span></div>
                    <div class="record-field"><span class="record-label">Total Cost</span><span class="record-value"><?= e($formatMoney($purchases['total'] ?? 0)) ?></span></div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100">
            <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-briefcase me-2"></i>Jobs (Within Range)</strong>
                <a class="small text-decoration-none fw-semibold" href="<?= e(url('/jobs')) ?>">Open Jobs</a>
            </div>
            <div class="card-body">
                <?php if ($jobs === []): ?>
                    <div class="record-empty">No jobs in this date range.</div>
                <?php else: ?>
                    <div class="simple-list-table">
                        <?php foreach ($jobs as $job): ?>
                            <?php
                            $jobId = (int) ($job['id'] ?? 0);
                            $title = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
                            $client = trim((string) ($job['client_name'] ?? '')) ?: '—';
                            $status = trim((string) ($job['status'] ?? ''));
                            $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '—';
                            $scheduled = $formatDate((string) ($job['scheduled_start_at'] ?? ''));
                            ?>
                            <a class="simple-list-row simple-list-row-link" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">
                                <span class="simple-list-title"><?= e($title) ?></span>
                                <span class="simple-list-meta"><?= e($client) ?> · <?= e($statusLabel) ?> · <?= e($scheduled) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100">
            <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-cash-register me-2"></i>Sales (Within Range)</strong>
                <a class="small text-decoration-none fw-semibold" href="<?= e(url('/sales')) ?>">Open Sales</a>
            </div>
            <div class="card-body">
                <?php if ($salesList === []): ?>
                    <div class="record-empty">No sales in this date range.</div>
                <?php else: ?>
                    <div class="simple-list-table">
                        <?php foreach ($salesList as $sale): ?>
                            <?php
                            $saleId = (int) ($sale['id'] ?? 0);
                            $name = trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . (string) $saleId);
                            $type = trim((string) ($sale['type'] ?? ''));
                            $typeLabel = $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '—';
                            $date = $formatDate((string) ($sale['sale_date'] ?? ''));
                            $gross = $formatMoney((float) ($sale['gross_amount'] ?? 0));
                            $net = $formatMoney((float) ($sale['net_amount'] ?? 0));
                            ?>
                            <a class="simple-list-row simple-list-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                                <span class="simple-list-title"><?= e($name) ?></span>
                                <span class="simple-list-meta"><?= e($typeLabel) ?> · <?= e($date) ?> · Gross <?= e($gross) ?> · Net <?= e($net) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
