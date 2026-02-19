<?php
    $activeReport = (string) ($activeReport ?? 'overview');
    $range = is_array($range ?? null) ? $range : ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
    $totals = is_array($totals ?? null) ? $totals : [];
    $jobProfitability = is_array($jobProfitability ?? null) ? $jobProfitability : [];
    $disposalPerformance = is_array($disposalPerformance ?? null) ? $disposalPerformance : [];
    $employeeLabor = is_array($employeeLabor ?? null) ? $employeeLabor : [];
    $salesBySource = is_array($salesBySource ?? null) ? $salesBySource : [];
    $presets = is_array($presets ?? null) ? $presets : [];
    $selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;

    $reportLabels = [
        'overview' => 'Overview',
        'job_profitability' => 'Job Profitability',
        'disposal_performance' => 'Disposal Spend vs Scrap Revenue',
        'employee_labor' => 'Employee Labor Cost',
        'sales_by_source' => 'Sales by Source',
    ];

    $money = static fn (mixed $value): string => '$' . number_format((float) ($value ?? 0), 2);
    $minutes = static function (mixed $value): string {
        $total = (int) ($value ?? 0);
        if ($total <= 0) {
            return '0h 00m';
        }
        return intdiv($total, 60) . 'h ' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT) . 'm';
    };

    $queryForExport = http_build_query([
        'report' => $activeReport,
        'start_date' => $range['start_date'] ?? '',
        'end_date' => $range['end_date'] ?? '',
        'export' => 'csv',
    ]);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Reporting Hub</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="<?= url('/reports?' . $queryForExport) ?>">
                <i class="fas fa-file-csv me-1"></i>
                Export Current CSV
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/reports') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="report">Primary Report</label>
                    <select class="form-select" id="report" name="report">
                        <?php foreach ($reportLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $activeReport === $key ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/reports') ?>">Reset</a>
                </div>
            </form>

            <hr />

            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6 col-lg-7">
                    <form method="post" action="<?= url('/reports/presets/save') ?>" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="report" value="<?= e($activeReport) ?>" />
                        <input type="hidden" name="start_date" value="<?= e((string) ($range['start_date'] ?? '')) ?>" />
                        <input type="hidden" name="end_date" value="<?= e((string) ($range['end_date'] ?? '')) ?>" />
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="preset_name">Save Preset</label>
                            <input class="form-control" id="preset_name" name="preset_name" type="text" maxlength="120" placeholder="e.g. Month-End Job Profitability" required />
                        </div>
                        <div class="col-12 col-md-5 d-grid">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-floppy-disk me-1"></i>
                                Save Preset
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-12 col-md-6 col-lg-5">
                    <label class="form-label" for="preset_id">Load Preset</label>
                    <form method="get" action="<?= url('/reports') ?>" class="d-flex gap-2">
                        <select class="form-select" id="preset_id" name="preset_id">
                            <option value="">Choose preset...</option>
                            <?php foreach ($presets as $preset): ?>
                                <option value="<?= e((string) ($preset['id'] ?? '')) ?>" <?= $selectedPresetId === (int) ($preset['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= e((string) ($preset['name'] ?? 'Preset')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" type="submit">Load</button>
                    </form>
                </div>
            </div>

            <?php if (!empty($presets)): ?>
                <div class="mt-3">
                    <div class="small text-muted mb-1">Saved Presets</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($presets as $preset): ?>
                            <?php $presetId = (int) ($preset['id'] ?? 0); ?>
                            <form method="post" action="<?= url('/reports/presets/' . $presetId . '/delete') ?>" class="d-inline-flex gap-1 align-items-center">
                                <?= csrf_field() ?>
                                <a class="badge text-bg-light text-decoration-none" href="<?= url('/reports?preset_id=' . $presetId) ?>">
                                    <?= e((string) ($preset['name'] ?? 'Preset')) ?>
                                </a>
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete preset" aria-label="Delete preset">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Jobs Net</div>
                    <div class="h4 mb-0 text-success"><?= e($money($totals['jobs_net'] ?? 0)) ?></div>
                    <div class="small text-muted">Revenue <?= e($money($totals['jobs_revenue'] ?? 0)) ?> | Costs <?= e($money($totals['jobs_cost'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Sales Net</div>
                    <div class="h4 mb-0 text-primary"><?= e($money($totals['sales_net'] ?? 0)) ?></div>
                    <div class="small text-muted">Gross <?= e($money($totals['sales_gross'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Combined Net</div>
                    <div class="h4 mb-0 text-danger"><?= e($money($totals['combined_net'] ?? 0)) ?></div>
                    <div class="small text-muted">Combined Gross <?= e($money($totals['combined_gross'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Date Range</div>
                    <div class="fw-semibold"><?= e(format_date($range['start_date'] ?? null)) ?> - <?= e(format_date($range['end_date'] ?? null)) ?></div>
                    <div class="small text-muted">Primary: <?= e($reportLabels[$activeReport] ?? 'Overview') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="report-job-profitability">
        <div class="card-header">
            <i class="fas fa-briefcase me-1"></i>
            Job Profitability
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Revenue</th>
                            <th>Costs</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jobProfitability)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No jobs in this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jobProfitability as $row): ?>
                                <tr>
                                    <td>
                                        <a class="text-decoration-none" href="<?= url('/jobs/' . (string) ($row['id'] ?? '')) ?>">
                                            #<?= e((string) ($row['id'] ?? '')) ?> - <?= e((string) ($row['name'] ?? 'Job')) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string) (($row['client_name'] ?? '') !== '' ? $row['client_name'] : '—')) ?></td>
                                    <td class="text-capitalize"><?= e((string) ($row['job_status'] ?? '')) ?></td>
                                    <td><?= e($money($row['revenue_total'] ?? 0)) ?></td>
                                    <td><?= e($money($row['cost_total'] ?? 0)) ?></td>
                                    <td class="fw-semibold <?= (float) ($row['net_total'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= e($money($row['net_total'] ?? 0)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="report-disposal-performance">
        <div class="card-header">
            <i class="fas fa-recycle me-1"></i>
            Disposal Spend vs Scrap Revenue
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Scrap Revenue</th>
                            <th>Dump Spend</th>
                            <th>Expense Spend</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($disposalPerformance)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No disposal activity in this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($disposalPerformance as $row): ?>
                                <tr>
                                    <td><?= e((string) (($row['name'] ?? '') !== '' ? $row['name'] : '—')) ?></td>
                                    <td class="text-uppercase"><?= e((string) ($row['type'] ?? '')) ?></td>
                                    <td class="text-success"><?= e($money($row['scrap_revenue'] ?? 0)) ?></td>
                                    <td class="text-danger"><?= e($money($row['dump_spend'] ?? 0)) ?></td>
                                    <td class="text-danger"><?= e($money($row['expense_spend'] ?? 0)) ?></td>
                                    <td class="fw-semibold <?= (float) ($row['net_total'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= e($money($row['net_total'] ?? 0)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="report-employee-labor">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Employee Labor Cost
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Entries</th>
                            <th>Time</th>
                            <th>Paid</th>
                            <th>Eff. Rate</th>
                            <th>Non-Job Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employeeLabor)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No labor activity in this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employeeLabor as $row): ?>
                                <tr>
                                    <td>
                                        <a class="text-decoration-none" href="<?= url('/employees/' . (string) ($row['id'] ?? '')) ?>">
                                            <?= e((string) ($row['employee_name'] ?? 'Employee')) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string) ($row['entry_count'] ?? 0)) ?></td>
                                    <td><?= e($minutes($row['total_minutes'] ?? 0)) ?></td>
                                    <td><?= e($money($row['total_paid'] ?? 0)) ?></td>
                                    <td><?= e($money($row['hourly_effective_rate'] ?? 0)) ?>/hr</td>
                                    <td><?= e($minutes($row['non_job_minutes'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4" id="report-sales-by-source">
        <div class="card-header">
            <i class="fas fa-sack-dollar me-1"></i>
            Sales by Source
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Count</th>
                            <th>Gross</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salesBySource)): ?>
                            <tr>
                                <td colspan="4" class="text-muted">No sales in this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salesBySource as $row): ?>
                                <tr>
                                    <td class="text-uppercase"><?= e((string) ($row['source'] ?? 'other')) ?></td>
                                    <td><?= e((string) ($row['sale_count'] ?? 0)) ?></td>
                                    <td><?= e($money($row['gross_total'] ?? 0)) ?></td>
                                    <td><?= e($money($row['net_total'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
