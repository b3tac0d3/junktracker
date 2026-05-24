<?php
/** Estate sale Metrics tab (overall, per-day, employee labor breakdown). */
$metricsReport = is_array($metricsReport ?? null) ? $metricsReport : [];
$overall = is_array($metricsReport['overall'] ?? null) ? $metricsReport['overall'] : [];
$dayRows = is_array($metricsReport['days'] ?? null) ? $metricsReport['days'] : [];
$charts = is_array($metricsReport['charts'] ?? null) ? $metricsReport['charts'] : [];
$saleDays = is_array($metricsReport['sale_days'] ?? null) ? $metricsReport['sale_days'] : [];
$labor = is_array($metricsReport['labor'] ?? null) ? $metricsReport['labor'] : [];
$laborDays = is_array($labor['days'] ?? null) ? $labor['days'] : [];
$laborEmployees = is_array($labor['employees'] ?? null) ? $labor['employees'] : [];
$laborGrandTotal = (float) ($labor['grand_total'] ?? 0);
$laborByDate = [];
foreach ($laborDays as $laborDay) {
    if (!is_array($laborDay)) {
        continue;
    }
    $laborByDate[(string) ($laborDay['date'] ?? '')] = $laborDay;
}
$dayCount = count($saleDays);

$formatMoney = static fn (float $amount): string => '$' . number_format($amount, 2);
$formatAmount = static function (float $amount, string $kind) use ($formatMoney): string {
    if ($kind === 'subtract') {
        return '−' . $formatMoney(abs($amount));
    }

    return $formatMoney($amount);
};

$renderMetricCards = static function (array $snapshot) use ($formatMoney): void {
    $avgSale = $snapshot['avg_sale_price'] ?? null;
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="record-field h-100">
                <span class="record-label">Customers</span>
                <span class="record-value fs-5"><?= e((string) ((int) ($snapshot['customer_count'] ?? 0))) ?></span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="record-field h-100">
                <span class="record-label">Sales</span>
                <span class="record-value fs-5"><?= e((string) ((int) ($snapshot['sale_count'] ?? 0))) ?></span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="record-field h-100">
                <span class="record-label">Avg wait time</span>
                <span class="record-value"><?= e((string) ($snapshot['avg_wait_display'] ?? '—')) ?></span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="record-field h-100">
                <span class="record-label">Avg shopping time</span>
                <span class="record-value"><?= e((string) ($snapshot['avg_shopping_display'] ?? '—')) ?></span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="record-field h-100">
                <span class="record-label">Avg price paid</span>
                <span class="record-value"><?= $avgSale !== null ? e($formatMoney((float) $avgSale)) : '—' ?></span>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="record-field h-100">
                <span class="record-label">Gross sales</span>
                <span class="record-value"><?= e($formatMoney((float) (($snapshot['financial']['gross'] ?? $snapshot['financial']['total_sales'] ?? 0)))) ?></span>
            </div>
        </div>
    </div>
    <?php
};

$renderSplitTable = static function (array $rows) use ($formatMoney): void {
    if ($rows === []) {
        echo '<div class="record-empty mb-0">No sales recorded yet.</div>';

        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Client split %</th>
                    <th scope="col" class="text-end"># of sales</th>
                    <th scope="col" class="text-end">Gross total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php if (!is_array($row)) {
                        continue;
                    } ?>
                    <tr>
                        <td><?= e((string) ($row['label'] ?? '—')) ?></td>
                        <td class="text-end"><?= e((string) ((int) ($row['sale_count'] ?? 0))) ?></td>
                        <td class="text-end"><?= e($formatMoney((float) ($row['gross_total'] ?? 0))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$renderProfitSteps = static function (array $steps) use ($formatAmount): void {
    if ($steps === []) {
        echo '<div class="record-empty mb-0">Profit breakdown unavailable until client split is configured.</div>';

        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 estate-sale-profit-steps">
            <tbody>
                <?php foreach ($steps as $step): ?>
                    <?php
                    if (!is_array($step)) {
                        continue;
                    }
                    $kind = (string) ($step['kind'] ?? 'line');
                    $rowClass = $kind === 'total' ? 'table-active fw-semibold' : '';
                    ?>
                    <tr class="<?= e($rowClass) ?>">
                        <td><?= e((string) ($step['label'] ?? '')) ?></td>
                        <td class="text-end"><?= e($formatAmount((float) ($step['amount'] ?? 0), $kind)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$renderLaborTable = static function (array $employees, ?float $tableTotal = null) use ($formatMoney): void {
    if ($employees === []) {
        echo '<div class="record-empty mb-0">No labor recorded for this period.</div>';

        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Employee</th>
                    <th scope="col" class="text-end">Hours</th>
                    <th scope="col" class="text-end">Rate</th>
                    <th scope="col" class="text-end">Owed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <?php
                    if (!is_array($employee)) {
                        continue;
                    }
                    $rate = $employee['hourly_rate'] ?? null;
                    ?>
                    <tr>
                        <td><?= e((string) ($employee['employee_name'] ?? '—')) ?></td>
                        <td class="text-end"><?= e((string) ($employee['hours_display'] ?? '—')) ?></td>
                        <td class="text-end"><?= $rate !== null ? e($formatMoney((float) $rate)) . '/hr' : '—' ?></td>
                        <td class="text-end"><?= e($formatMoney((float) ($employee['amount_owed'] ?? 0))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($tableTotal !== null): ?>
            <tfoot>
                <tr class="table-active fw-semibold">
                    <td colspan="3">Total</td>
                    <td class="text-end"><?= e($formatMoney($tableTotal)) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
};
?>

<div class="small muted mb-3">
    <?= $dayCount === 1 ? '1 sale day' : e((string) $dayCount) . ' sale days' ?>
    <?php if ($saleDays !== []): ?>
        · <?= e(date('M j', strtotime($saleDays[0] . ' 12:00:00'))) ?>
        <?php if ($dayCount > 1): ?>
            – <?= e(date('M j, Y', strtotime($saleDays[$dayCount - 1] . ' 12:00:00'))) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<section class="mb-4">
    <h3 class="h6 mb-3"><i class="fas fa-chart-pie me-2"></i>Overall sale metrics</h3>
    <?php $renderMetricCards($overall); ?>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <h4 class="h6 record-label mb-2">Split breakdown</h4>
            <?php $renderSplitTable(is_array($overall['split_breakdown'] ?? null) ? $overall['split_breakdown'] : []); ?>
        </div>
        <div class="col-12 col-lg-6">
            <h4 class="h6 record-label mb-2">Profit after labor &amp; expenses</h4>
            <?php $renderProfitSteps(is_array($overall['profit_steps'] ?? null) ? $overall['profit_steps'] : []); ?>
        </div>
    </div>
</section>

<section class="mb-4">
    <h3 class="h6 mb-3"><i class="fas fa-users-cog me-2"></i>Employee labor</h3>
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <h4 class="h6 record-label mb-2">Total owed (all days)</h4>
            <?php $renderLaborTable($laborEmployees, $laborGrandTotal); ?>
        </div>
        <div class="col-12 col-lg-6">
            <h4 class="h6 record-label mb-2">Daily labor totals</h4>
            <?php if ($laborDays === []): ?>
                <div class="record-empty mb-0">No sale days defined.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Day</th>
                                <th scope="col" class="text-end">Employees</th>
                                <th scope="col" class="text-end">Total owed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($laborDays as $laborDay): ?>
                                <?php
                                if (!is_array($laborDay)) {
                                    continue;
                                }
                                $dayEmployees = is_array($laborDay['employees'] ?? null) ? $laborDay['employees'] : [];
                                ?>
                                <tr>
                                    <td><?= e((string) ($laborDay['label'] ?? '')) ?></td>
                                    <td class="text-end"><?= e((string) count($dayEmployees)) ?></td>
                                    <td class="text-end"><?= e($formatMoney((float) ($laborDay['day_total'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active fw-semibold">
                                <td colspan="2">Grand total</td>
                                <td class="text-end"><?= e($formatMoney($laborGrandTotal)) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($dayCount > 0): ?>
<section class="mb-4">
    <h3 class="h6 mb-3"><i class="fas fa-chart-column me-2"></i>Charts</h3>
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card border-0 bg-light-subtle">
                <div class="card-body">
                    <div class="record-label mb-2">Sales by client split %</div>
                    <div class="jt-dashboard-chart-holder" style="min-height: 220px;">
                        <canvas id="estate-sale-metrics-split-chart" aria-label="Bar chart of sales count by client split percentage" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 bg-light-subtle">
                <div class="card-body">
                    <div class="record-label mb-2">Daily gross &amp; customers</div>
                    <div class="jt-dashboard-chart-holder" style="min-height: 220px;">
                        <canvas id="estate-sale-metrics-daily-chart" aria-label="Bar chart of daily gross sales and customer counts" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section>
    <h3 class="h6 mb-3"><i class="fas fa-calendar-day me-2"></i>Per-day breakdown</h3>
    <?php if ($dayRows === []): ?>
        <div class="record-empty mb-0">No sale days defined for this estate sale.</div>
    <?php else: ?>
        <div class="accordion" id="estate-sale-metrics-days">
            <?php foreach ($dayRows as $index => $dayRow): ?>
                <?php
                if (!is_array($dayRow)) {
                    continue;
                }
                $collapseId = 'estate-sale-metrics-day-' . (string) ($index + 1);
                $isFirst = $index === 0;
                ?>
                <div class="accordion-item">
                    <h4 class="accordion-header" id="<?= e($collapseId) ?>-heading">
                        <button
                            class="accordion-button<?= $isFirst ? '' : ' collapsed' ?>"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= e($collapseId) ?>"
                            aria-expanded="<?= $isFirst ? 'true' : 'false' ?>"
                            aria-controls="<?= e($collapseId) ?>"
                        >
                            Day <?= e((string) ((int) ($dayRow['day_number'] ?? ($index + 1)))) ?> · <?= e((string) ($dayRow['label'] ?? '')) ?>
                        </button>
                    </h4>
                    <div
                        id="<?= e($collapseId) ?>"
                        class="accordion-collapse collapse<?= $isFirst ? ' show' : '' ?>"
                        aria-labelledby="<?= e($collapseId) ?>-heading"
                        data-bs-parent="#estate-sale-metrics-days"
                    >
                        <div class="accordion-body">
                            <?php $renderMetricCards($dayRow); ?>
                            <div class="row g-4">
                                <div class="col-12 col-lg-6">
                                    <h5 class="h6 record-label mb-2">Split breakdown</h5>
                                    <?php $renderSplitTable(is_array($dayRow['split_breakdown'] ?? null) ? $dayRow['split_breakdown'] : []); ?>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <h5 class="h6 record-label mb-2">Profit after labor &amp; expenses</h5>
                                    <?php $renderProfitSteps(is_array($dayRow['profit_steps'] ?? null) ? $dayRow['profit_steps'] : []); ?>
                                </div>
                            </div>
                            <?php
                            $dayLabor = $laborByDate[(string) ($dayRow['date'] ?? '')] ?? null;
                            $dayLaborEmployees = is_array($dayLabor['employees'] ?? null) ? $dayLabor['employees'] : [];
                            $dayLaborTotal = is_array($dayLabor) ? (float) ($dayLabor['day_total'] ?? 0) : 0.0;
                            ?>
                            <div class="mt-4">
                                <h5 class="h6 record-label mb-2">Employee labor</h5>
                                <?php $renderLaborTable($dayLaborEmployees, $dayLaborTotal); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($dayCount > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const charts = <?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    const cssVar = (name, fallback) => {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    };
    const barColors = (fillVar, strokeVar, fallbackFill, fallbackStroke) => ({
        backgroundColor: cssVar(fillVar, fallbackFill),
        borderColor: cssVar(strokeVar, fallbackStroke),
        borderWidth: 1,
    });

    const splitCanvas = document.getElementById('estate-sale-metrics-split-chart');
    if (splitCanvas && Array.isArray(charts.split_labels) && charts.split_labels.length) {
        new Chart(splitCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: charts.split_labels,
                datasets: [
                    {
                        label: '# of sales',
                        data: charts.split_counts || [],
                        ...barColors('--jt-chart-estate-sales-fill', '--jt-chart-estate-sales-stroke', 'rgba(13, 110, 253, 0.55)', 'rgba(13, 110, 253, 1)'),
                        yAxisID: 'y',
                    },
                    {
                        label: 'Gross total',
                        data: charts.split_gross || [],
                        ...barColors('--jt-chart-total-gross-fill', '--jt-chart-total-gross-stroke', 'rgba(25, 135, 84, 0.55)', 'rgba(25, 135, 84, 1)'),
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Sales' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Gross ($)' } },
                },
            },
        });
    }

    const dailyCanvas = document.getElementById('estate-sale-metrics-daily-chart');
    if (dailyCanvas && Array.isArray(charts.daily_labels) && charts.daily_labels.length) {
        new Chart(dailyCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: charts.daily_labels,
                datasets: [
                    {
                        label: 'Gross sales',
                        data: charts.daily_gross || [],
                        ...barColors('--jt-chart-sales-fill', '--jt-chart-sales-stroke', 'rgba(255, 193, 7, 0.55)', 'rgba(255, 193, 7, 1)'),
                        yAxisID: 'y',
                    },
                    {
                        label: 'Customers',
                        data: charts.daily_customers || [],
                        ...barColors('--jt-chart-service-fill', '--jt-chart-service-stroke', 'rgba(111, 66, 193, 0.55)', 'rgba(111, 66, 193, 1)'),
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Gross ($)' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Customers' } },
                },
            },
        });
    }
})();
</script>
<?php endif; ?>
