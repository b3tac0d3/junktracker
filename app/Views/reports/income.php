<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$report = is_array($report ?? null) ? $report : [];

$sales = is_array($report['sales'] ?? null) ? $report['sales'] : [];
$service = is_array($report['service'] ?? null) ? $report['service'] : [];
$expenses = is_array($report['expenses'] ?? null) ? $report['expenses'] : [];
$purchases = is_array($report['purchases'] ?? null) ? $report['purchases'] : [];
$overall = is_array($report['overall'] ?? null) ? $report['overall'] : [];

$expensesByCategory = is_array($expenses['by_category'] ?? null) ? $expenses['by_category'] : [];
$salesByType = is_array($sales['by_type'] ?? null) ? $sales['by_type'] : [];
$marginByJob = is_array($report['margin_by_job'] ?? null) ? $report['margin_by_job'] : [];

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

$reportChartData = [
    'gross' => round((float) ($overall['gross'] ?? 0), 2),
    'expenses' => round((float) ($expenses['total'] ?? 0), 2),
    'profit' => round((float) ($overall['net'] ?? 0), 2),
];
$formAction = url('/reports/income');
$resetHref = url('/reports/income');
?>

<div class="reports-shell">
    <div class="mb-2">
        <a class="small text-decoration-none fw-semibold" href="<?= e(url('/reports')) ?>"><i class="fas fa-arrow-left me-1"></i>All reports</a>
    </div>
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
        <div>
            <h1>Income report</h1>
            <p class="muted">Period summary for service, sales, expenses, and purchases</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export/income') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate])) ?>">
            <i class="fas fa-download me-1" aria-hidden="true"></i>Download CSV
        </a>
    </div>

<section class="card index-card mb-3 reports-card-period">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2 jt-report-icon--period" aria-hidden="true"></i>Time Period</strong>
    </div>
    <div class="card-body">
        <?php
        require base_path('app/Views/reports/partials/period_form.php');
        ?>
    </div>
    </section>

<section class="card index-card mb-3 reports-card-totals">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-chart-column me-2 jt-report-icon--totals" aria-hidden="true"></i>Summary</strong>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3 mb-md-2">Gross revenue, net profit, expenses (loss), and purchase COGS for the period.</p>
        <div class="row g-3">
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <div class="small text-muted">Gross revenue</div>
                <div class="fs-5 fw-semibold"><span class="jt-report-in"><?= e($formatMoney($overall['gross'] ?? 0)) ?></span></div>
            </div>
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <div class="small text-muted">Net profit <span class="text-muted fw-normal">(after general expenses)</span></div>
                <div class="fs-5 fw-semibold"><span class="jt-report-net"><?= e($formatMoney($overall['net'] ?? 0)) ?></span></div>
            </div>
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <div class="small text-muted">Net after purchases <span class="text-muted fw-normal">(profit)</span></div>
                <div class="fs-5 fw-semibold"><span class="jt-report-net"><?= e($formatMoney($overall['net_minus_purchases'] ?? 0)) ?></span></div>
            </div>
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <div class="small text-muted">Total expense <span class="text-muted fw-normal">(loss)</span></div>
                <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney($expenses['total'] ?? 0)) ?></span></div>
            </div>
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <div class="small text-muted">Purchase total <span class="text-muted fw-normal">(COGS)</span></div>
                <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney($purchases['total'] ?? 0)) ?></span></div>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3 reports-card-chart">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong class="mb-0"><i class="fas fa-chart-simple me-2 jt-report-icon--chart" aria-hidden="true"></i>Period overview</strong>
        <div class="d-flex align-items-center gap-2 ms-md-auto">
            <label class="small text-muted mb-0 fw-semibold text-nowrap" for="jtReportsChartType">Chart type</label>
            <select id="jtReportsChartType" class="form-select form-select-sm jt-report-chart-type-select" aria-label="Chart type">
                <option value="bar" selected>Bar</option>
                <option value="pie">Pie</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3 mb-md-2">Gross (sales + invoiced service), total expenses, and profit (net after general expenses) for <?= e($formatDate($fromDate)) ?>–<?= e($formatDate($toDate)) ?>.</p>
        <div class="jt-report-chart-holder">
            <canvas id="jtReportsChart" aria-label="Chart of gross, expenses, and profit" role="img"></canvas>
        </div>
    </div>
</section>

<?php if ($marginByJob !== []): ?>
<section class="card index-card mb-3 reports-card-margin">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-scale-balanced me-2 jt-report-icon--margin" aria-hidden="true"></i>Margin by job (sales net − purchase COGS)</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th class="text-end">Sales net</th>
                        <th class="text-end">Purchase COGS</th>
                        <th class="text-end">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marginByJob as $row): ?>
                        <?php if (!is_array($row)) { continue; } ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/jobs/' . (string) ((int) ($row['job_id'] ?? 0)))) ?>"><?= e((string) ($row['title'] ?? '')) ?></a>
                            </td>
                            <td class="text-end"><span class="jt-report-in"><?= e($formatMoney($row['sales_net'] ?? 0)) ?></span></td>
                            <td class="text-end"><span class="jt-report-out"><?= e($formatMoney($row['purchase_cogs'] ?? 0)) ?></span></td>
                            <td class="text-end"><span class="jt-report-net"><?= e($formatMoney($row['margin'] ?? 0)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100 reports-card-service">
            <div class="card-header index-card-header"><strong><i class="fas fa-file-invoice-dollar me-2 jt-report-icon--service" aria-hidden="true"></i>Service (Invoices)</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-2">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><span class="jt-report-count"><?= e((string) ((int) ($service['count'] ?? 0))) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Gross</span><span class="record-value"><span class="jt-report-in"><?= e($formatMoney($service['gross'] ?? 0)) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Job Expenses</span><span class="record-value"><span class="jt-report-out"><?= e($formatMoney($service['job_expenses'] ?? 0)) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Net</span><span class="record-value"><span class="jt-report-in"><?= e($formatMoney($service['net'] ?? 0)) ?></span></span></div>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100 reports-card-sales">
            <div class="card-header index-card-header"><strong><i class="fas fa-sack-dollar me-2 jt-report-icon--sales" aria-hidden="true"></i>Sales</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-3 mb-3">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><span class="jt-report-count"><?= e((string) ((int) ($sales['count'] ?? 0))) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Gross</span><span class="record-value"><span class="jt-report-in"><?= e($formatMoney($sales['gross'] ?? 0)) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Net</span><span class="record-value"><span class="jt-report-in"><?= e($formatMoney($sales['net'] ?? 0)) ?></span></span></div>
                </div>
                <?php
                $saleTypeLabel = static function (string $value): string {
                    $normalized = trim($value);
                    if ($normalized === '') {
                        return 'Uncategorized';
                    }
                    $map = [
                        'b2b' => 'B2B',
                        'ebay' => 'Ebay',
                    ];
                    $key = strtolower($normalized);
                    if (isset($map[$key])) {
                        return $map[$key];
                    }
                    return ucwords(str_replace('_', ' ', $normalized));
                };
                ?>
                <?php if ($salesByType === []): ?>
                    <div class="record-empty">No sale type totals available for this period.</div>
                <?php else: ?>
                    <div class="simple-list-table">
                        <?php foreach ($salesByType as $typeKey => $typeSummary): ?>
                            <?php $typeSummary = is_array($typeSummary) ? $typeSummary : ['count' => 0, 'gross' => 0.0, 'net' => 0.0]; ?>
                            <div class="simple-list-row">
                                <span class="simple-list-title"><?= e($saleTypeLabel((string) $typeKey)) ?></span>
                                <span class="simple-list-meta">
                                    <span class="jt-report-muted">Gross</span> <span class="jt-report-in"><?= e($formatMoney($typeSummary['gross'] ?? 0)) ?></span>
                                    <span class="jt-report-muted">·</span>
                                    <span class="jt-report-muted">Net</span> <span class="jt-report-in"><?= e($formatMoney($typeSummary['net'] ?? 0)) ?></span>
                                    <span class="jt-report-muted">·</span>
                                    <span class="jt-report-count"><?= e((string) ((int) ($typeSummary['count'] ?? 0))) ?> sale(s)</span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100 reports-card-expenses">
            <div class="card-header index-card-header"><strong><i class="fas fa-receipt me-2 jt-report-icon--expenses" aria-hidden="true"></i>Expenses</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-4 mb-3">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><span class="jt-report-count"><?= e((string) ((int) ($expenses['count'] ?? 0))) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Job Expenses</span><span class="record-value"><span class="jt-report-out"><?= e($formatMoney($expenses['job_total'] ?? 0)) ?></span></span></div>
                    <div class="record-field"><span class="record-label">General Expenses</span><span class="record-value"><span class="jt-report-out"><?= e($formatMoney($expenses['general_total'] ?? 0)) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Total</span><span class="record-value"><span class="jt-report-out"><?= e($formatMoney($expenses['total'] ?? 0)) ?></span></span></div>
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
                                <span class="simple-list-meta">
                                    <span class="jt-report-count"><?= e((string) $count) ?> record(s)</span>
                                    <span class="jt-report-muted">·</span>
                                    <span class="jt-report-out"><?= e($formatMoney($total)) ?></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card index-card h-100 reports-card-purchasing">
            <div class="card-header index-card-header"><strong><i class="fas fa-cart-arrow-down me-2 jt-report-icon--purchasing" aria-hidden="true"></i>Purchasing</strong></div>
            <div class="card-body">
                <div class="record-row-fields record-row-fields-2">
                    <div class="record-field"><span class="record-label">Count</span><span class="record-value"><span class="jt-report-count"><?= e((string) ((int) ($purchases['count'] ?? 0))) ?></span></span></div>
                    <div class="record-field"><span class="record-label">Total Cost</span><span class="record-value"><span class="jt-report-out"><?= e($formatMoney($purchases['total'] ?? 0)) ?></span></span></div>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    const canvas = document.getElementById('jtReportsChart');
    const typeSelect = document.getElementById('jtReportsChartType');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }
    const ctx = canvas.getContext('2d');
    const data = <?= json_encode($reportChartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const labels = ['Gross', 'Expenses', 'Profit'];
    const values = [data.gross, data.expenses, data.profit];
    const rs = getComputedStyle(document.documentElement);
    const cv = function (name) {
        return rs.getPropertyValue(name).trim();
    };
    const colors = [
        cv('--jt-chart-reports-gross-fill'),
        cv('--jt-chart-reports-expenses-fill'),
        cv('--jt-chart-reports-net-fill'),
    ];
    const borders = [
        cv('--jt-chart-reports-gross-stroke'),
        cv('--jt-chart-reports-expenses-stroke'),
        cv('--jt-chart-reports-net-stroke'),
    ];

    let chart = null;

    function buildConfig(type) {
        const dataset = {
            label: 'Amount',
            data: values,
            backgroundColor: colors,
            borderColor: borders,
            borderWidth: 1,
            borderRadius: 4,
        };

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            if (type === 'pie') {
                                const v = ctx.raw;
                                const lbl = ctx.label ? ctx.label + ': ' : '';
                                return lbl + fmt.format(v);
                            }
                            return fmt.format(ctx.raw);
                        },
                    },
                },
            },
        };

        if (type === 'pie') {
            options.plugins.legend = {
                display: true,
                position: 'bottom',
            };
        } else {
            options.plugins.legend = { display: false };
            options.scales = {
                y: {
                    suggestedMin: Math.min(0, ...values) * 1.05,
                    suggestedMax: Math.max(0, ...values) * 1.05,
                    ticks: {
                        callback: function (v) {
                            return '$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });
                        },
                    },
                },
                x: {
                    grid: { display: false },
                },
            };
        }

        return {
            type: type,
            data: {
                labels: labels,
                datasets: [dataset],
            },
            options: options,
        };
    }

    function render() {
        const type = typeSelect && typeSelect.value ? typeSelect.value : 'bar';
        if (chart) {
            chart.destroy();
            chart = null;
        }
        chart = new Chart(ctx, buildConfig(type));
        canvas.setAttribute('aria-label', type.charAt(0).toUpperCase() + type.slice(1) + ' chart of gross, expenses, and profit');
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', render);
    }
    render();
})();
</script>
</div>
