<?php
$range = is_array($range ?? null) ? $range : ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$preset = trim((string) ($preset ?? 'month'));
$summary = is_array($summary ?? null) ? $summary : [];
$comparison = is_array($comparison ?? null) ? $comparison : [];
$comparisonRanges = is_array($comparisonRanges ?? null) ? $comparisonRanges : [];
$expenseBreakdown = is_array($expenseBreakdown ?? null) ? $expenseBreakdown : [];
$jobs = is_array($jobs ?? null) ? $jobs : [];
$sales = is_array($sales ?? null) ? $sales : [];
$charts = is_array($charts ?? null) ? $charts : [];
$snapshots = is_array($snapshots ?? null) ? $snapshots : [];
$selectedSnapshot = is_array($selectedSnapshot ?? null) ? $selectedSnapshot : null;
$isSavedSnapshot = !empty($isSavedSnapshot);
$snapshotId = isset($snapshotId) ? (int) $snapshotId : 0;

$money = static fn (mixed $value): string => '$' . number_format((float) ($value ?? 0), 2);
$number = static fn (mixed $value): string => number_format((float) ($value ?? 0), 0);

$deltaLabel = static function (mixed $delta): string {
    if (!is_array($delta)) {
        return '—';
    }

    $difference = (float) ($delta['difference'] ?? 0);
    $percent = $delta['percent'] ?? null;
    $prefix = $difference > 0 ? '+' : '';

    if ($percent === null) {
        return $prefix . '$' . number_format($difference, 2);
    }

    $percentLabel = $prefix . number_format((float) $percent, 1) . '%';
    return $prefix . '$' . number_format($difference, 2) . ' (' . $percentLabel . ')';
};

$deltaClass = static function (mixed $delta): string {
    if (!is_array($delta)) {
        return 'text-muted';
    }

    $difference = (float) ($delta['difference'] ?? 0);
    if ($difference > 0) {
        return 'text-success';
    }
    if ($difference < 0) {
        return 'text-danger';
    }

    return 'text-muted';
};

$chartPayload = [
    'comparison' => is_array($charts['comparison'] ?? null) ? $charts['comparison'] : ['labels' => [], 'gross' => [], 'expenses' => [], 'net' => []],
    'expenses' => is_array($charts['expenses'] ?? null) ? $charts['expenses'] : ['labels' => [], 'values' => []],
];

$presetOptions = [
    'month' => 'Current Month',
    'last_month' => 'Last Month',
    'quarter' => 'Current Quarter',
    'ytd' => 'Year To Date',
    'last_30_days' => 'Last 30 Days',
    'custom' => 'Custom Range',
];

$previous = is_array($comparison['previous'] ?? null) ? $comparison['previous'] : [];
$year = is_array($comparison['year'] ?? null) ? $comparison['year'] : [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Snapshot Hub</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/reports') ?>">Reports</a></li>
                <li class="breadcrumb-item active">Snapshots</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-primary" href="<?= url('/reports') ?>">
                <i class="fas fa-chart-line me-1"></i>
                Reporting Hub
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($selectedSnapshot !== null): ?>
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="fw-semibold">Viewing Saved Snapshot: <?= e((string) ($selectedSnapshot['label'] ?? 'Snapshot')) ?></div>
                <div class="small">
                    Range <?= e(format_date((string) ($selectedSnapshot['start_date'] ?? ''))) ?> - <?= e(format_date((string) ($selectedSnapshot['end_date'] ?? ''))) ?>
                    · Saved <?= e(format_datetime((string) ($selectedSnapshot['created_at'] ?? ''))) ?>
                </div>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('/reports/snapshot?start_date=' . urlencode((string) ($range['start_date'] ?? '')) . '&end_date=' . urlencode((string) ($range['end_date'] ?? '')) . '&preset=custom') ?>">Switch to Live Range</a>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span><i class="fas fa-camera-retro me-1"></i> Snapshot Controls</span>
            <span class="small text-muted">Capture and compare business performance</span>
        </div>
        <div class="card-body">
            <form method="get" action="<?= url('/reports/snapshot') ?>" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="snapshot_preset">Period</label>
                    <select class="form-select" id="snapshot_preset" name="preset">
                        <?php foreach ($presetOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $preset === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="start_date">Start Date</label>
                    <input class="form-control" id="start_date" name="start_date" type="date" value="<?= e((string) ($range['start_date'] ?? '')) ?>" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="end_date">End Date</label>
                    <input class="form-control" id="end_date" name="end_date" type="date" value="<?= e((string) ($range['end_date'] ?? '')) ?>" />
                </div>
                <div class="col-12 col-md-3 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/reports/snapshot') ?>">Reset</a>
                </div>
            </form>

            <?php if (!$isSavedSnapshot && can_access('reports', 'create')): ?>
                <form method="post" action="<?= url('/reports/snapshot/save') ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="preset" value="<?= e($preset) ?>" />
                    <input type="hidden" name="start_date" value="<?= e((string) ($range['start_date'] ?? '')) ?>" />
                    <input type="hidden" name="end_date" value="<?= e((string) ($range['end_date'] ?? '')) ?>" />
                    <div class="col-12 col-lg-8">
                        <label class="form-label" for="snapshot_label">Snapshot Label</label>
                        <input class="form-control" id="snapshot_label" name="snapshot_label" type="text" maxlength="120" placeholder="e.g. February 2026 Performance" />
                    </div>
                    <div class="col-12 col-lg-4 d-grid">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Save Snapshot
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Sales (Gross / Net)</div>
                    <div class="h5 mb-1"><?= e($money($summary['sales_gross'] ?? 0)) ?></div>
                    <div class="small text-muted">Net <?= e($money($summary['sales_net'] ?? 0)) ?> · <?= e($number($summary['sales_count'] ?? 0)) ?> records</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Jobs Billed</div>
                    <div class="h5 mb-1"><?= e($money($summary['jobs_gross'] ?? 0)) ?></div>
                    <div class="small text-muted"><?= e($number($summary['job_count'] ?? 0)) ?> jobs · Pending <?= e($number($summary['jobs_pending_count'] ?? 0)) ?> · Active <?= e($number($summary['jobs_active_count'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Expense Mix</div>
                    <div class="h5 mb-1 text-danger"><?= e($money($summary['total_expenses'] ?? 0)) ?></div>
                    <div class="small text-muted">Direct <?= e($money($summary['expense_total'] ?? 0)) ?> · Payroll <?= e($money($summary['payroll_total'] ?? 0)) ?></div>
                    <div class="small text-muted">Dump <?= e($money($summary['dump_fees'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Gross / Net</div>
                    <div class="h5 mb-1"><?= e($money($summary['total_gross'] ?? 0)) ?></div>
                    <div class="small <?= (float) ($summary['total_net'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">Net <?= e($money($summary['total_net'] ?? 0)) ?></div>
                    <div class="small text-muted">Scrap Revenue <?= e($money($summary['scrap_revenue'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-arrow-trend-up me-1"></i> Vs Previous Period</span>
                    <span class="small text-muted">
                        <?= e(format_date((string) ($comparisonRanges['previous']['start_date'] ?? null))) ?> - <?= e(format_date((string) ($comparisonRanges['previous']['end_date'] ?? null))) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small text-uppercase">Total Gross</span>
                        <span class="<?= e($deltaClass($previous['deltas']['total_gross'] ?? null)) ?> fw-semibold"><?= e($deltaLabel($previous['deltas']['total_gross'] ?? null)) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small text-uppercase">Expenses</span>
                        <span class="<?= e($deltaClass($previous['deltas']['total_expenses'] ?? null)) ?> fw-semibold"><?= e($deltaLabel($previous['deltas']['total_expenses'] ?? null)) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small text-uppercase">Net</span>
                        <span class="<?= e($deltaClass($previous['deltas']['total_net'] ?? null)) ?> fw-semibold"><?= e($deltaLabel($previous['deltas']['total_net'] ?? null)) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-calendar-day me-1"></i> Vs Same Period Last Year</span>
                    <span class="small text-muted">
                        <?= e(format_date((string) ($comparisonRanges['year']['start_date'] ?? null))) ?> - <?= e(format_date((string) ($comparisonRanges['year']['end_date'] ?? null))) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small text-uppercase">Total Gross</span>
                        <span class="<?= e($deltaClass($year['deltas']['total_gross'] ?? null)) ?> fw-semibold"><?= e($deltaLabel($year['deltas']['total_gross'] ?? null)) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small text-uppercase">Expenses</span>
                        <span class="<?= e($deltaClass($year['deltas']['total_expenses'] ?? null)) ?> fw-semibold"><?= e($deltaLabel($year['deltas']['total_expenses'] ?? null)) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small text-uppercase">Net</span>
                        <span class="<?= e($deltaClass($year['deltas']['total_net'] ?? null)) ?> fw-semibold"><?= e($deltaLabel($year['deltas']['total_net'] ?? null)) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-bar me-1"></i> Period Comparison</div>
                <div class="card-body">
                    <canvas id="snapshotComparisonChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-pie me-1"></i> Expense Breakdown</div>
                <div class="card-body">
                    <?php if (empty($expenseBreakdown)): ?>
                        <div class="text-muted">No expense records in this range.</div>
                    <?php else: ?>
                        <canvas id="snapshotExpenseChart" height="120"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-briefcase me-1"></i> Jobs In Period</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Billed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($jobs)): ?>
                                    <tr><td colspan="4" class="text-muted">No jobs found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($jobs as $row): ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/jobs/' . (string) ($row['id'] ?? '0')) ?>">
                                                    #<?= e((string) ($row['id'] ?? '')) ?> - <?= e((string) ($row['name'] ?? 'Job')) ?>
                                                </a>
                                                <div class="small text-muted"><?= e((string) (($row['client_name'] ?? '') !== '' ? $row['client_name'] : '—')) ?></div>
                                            </td>
                                            <td class="text-capitalize"><?= e((string) ($row['job_status'] ?? '')) ?></td>
                                            <td><?= e(format_date((string) ($row['job_date'] ?? null))) ?></td>
                                            <td><?= e($money($row['total_billed'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-sack-dollar me-1"></i> Sales In Period</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Sale</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Gross</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales)): ?>
                                    <tr><td colspan="4" class="text-muted">No sales found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $row): ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/sales/' . (string) ($row['id'] ?? '0')) ?>">
                                                    #<?= e((string) ($row['id'] ?? '')) ?> - <?= e((string) ($row['name'] ?? 'Sale')) ?>
                                                </a>
                                                <?php if (!empty($row['job_id'])): ?>
                                                    <div class="small text-muted">
                                                        <a class="text-muted text-decoration-none" href="<?= url('/jobs/' . (string) ($row['job_id'] ?? '0')) ?>"><?= e((string) ($row['job_name'] ?? '')) ?></a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-uppercase"><?= e((string) ($row['type'] ?? 'other')) ?></td>
                                            <td><?= e(format_date((string) ($row['sale_date'] ?? null))) ?></td>
                                            <td><?= e($money($row['gross_amount'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-tags me-1"></i> Expense Categories</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenseBreakdown)): ?>
                                    <tr><td colspan="3" class="text-muted">No expense records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($expenseBreakdown as $row): ?>
                                        <tr>
                                            <td><?= e((string) ($row['category'] ?? 'Uncategorized')) ?></td>
                                            <td class="text-end"><?= e((string) number_format((float) ($row['expense_count'] ?? 0), 0)) ?></td>
                                            <td class="text-end"><?= e($money($row['total_amount'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-bookmark me-1"></i> Saved Snapshots</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Range</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($snapshots)): ?>
                                    <tr><td colspan="4" class="text-muted">No snapshots saved yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($snapshots as $row): ?>
                                        <?php $id = (int) ($row['id'] ?? 0); ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/reports/snapshot?snapshot_id=' . $id) ?>">
                                                    <?= e((string) ($row['label'] ?? 'Snapshot')) ?>
                                                </a>
                                                <div class="small text-muted">Saved <?= e(format_datetime((string) ($row['created_at'] ?? null))) ?></div>
                                            </td>
                                            <td><?= e(format_date((string) ($row['start_date'] ?? null))) ?> - <?= e(format_date((string) ($row['end_date'] ?? null))) ?></td>
                                            <td class="text-end"><?= e($money($row['gross_total'] ?? 0)) ?></td>
                                            <td class="text-end <?= (float) ($row['net_total'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>"><?= e($money($row['net_total'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script id="snapshotChartData" type="application/json"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
