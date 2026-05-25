<?php
$businessName = (string) (($business['name'] ?? '') !== '' ? $business['name'] : ('Business #' . (string) current_business_id()));
$summary = is_array($summary ?? null) ? $summary : [];
$sales = is_array($summary['sales'] ?? null) ? $summary['sales'] : [];
$estateSales = is_array($summary['estate_sales'] ?? null) ? $summary['estate_sales'] : [];
$service = is_array($summary['service'] ?? null) ? $summary['service'] : [];
$expenses = is_array($summary['expenses'] ?? null) ? $summary['expenses'] : [];
$purchasesSummary = is_array($summary['purchases'] ?? null) ? $summary['purchases'] : [];
$receivables = is_array($summary['receivables'] ?? null) ? $summary['receivables'] : [];
$tasks = is_array($summary['tasks'] ?? null) ? $summary['tasks'] : [];
$lists = is_array($summary['lists'] ?? null) ? $summary['lists'] : [];

$totalMtdGross = (float) ($sales['mtd_gross'] ?? 0) + (float) ($estateSales['mtd_gross'] ?? 0) + (float) ($service['mtd_gross'] ?? 0);
$totalYtdGross = (float) ($sales['ytd_gross'] ?? 0) + (float) ($estateSales['ytd_gross'] ?? 0) + (float) ($service['ytd_gross'] ?? 0);
$totalMtdNet = (float) ($summary['mtd_overall_net'] ?? 0);
$totalYtdNet = (float) ($summary['ytd_overall_net'] ?? 0);
$profitYtd = $totalYtdNet;
$ytdNetMinusPurchases = (float) ($summary['ytd_net_minus_purchases'] ?? 0);

$myTasksDue = is_array($lists['my_tasks_due'] ?? null) ? $lists['my_tasks_due'] : [];
$upcomingSchedule = is_array($lists['upcoming_schedule'] ?? null) ? $lists['upcoming_schedule'] : [];
$threeMonthChart = is_array($summary['three_month_chart'] ?? null) ? $summary['three_month_chart'] : ['months' => []];
$chartMonths = is_array($threeMonthChart['months'] ?? null) ? $threeMonthChart['months'] : [];
$dashboardChartPayload = [
    'labels' => [],
    'total_gross' => [],
    'sales_gross' => [],
    'estate_sales_gross' => [],
    'service_gross' => [],
    'expenses_total' => [],
    'net_profit' => [],
];
foreach ($chartMonths as $m) {
    if (!is_array($m)) {
        continue;
    }
    $dashboardChartPayload['labels'][] = (string) ($m['label'] ?? '');
    $salesG = round((float) ($m['sales_gross'] ?? 0), 2);
    $estateSalesG = round((float) ($m['estate_sales_gross'] ?? 0), 2);
    $serviceG = round((float) ($m['service_gross'] ?? 0), 2);
    $dashboardChartPayload['total_gross'][] = isset($m['total_gross']) ? round((float) $m['total_gross'], 2) : round($salesG + $estateSalesG + $serviceG, 2);
    $dashboardChartPayload['sales_gross'][] = $salesG;
    $dashboardChartPayload['estate_sales_gross'][] = $estateSalesG;
    $dashboardChartPayload['service_gross'][] = $serviceG;
    $dashboardChartPayload['expenses_total'][] = round((float) ($m['expenses_total'] ?? 0), 2);
    $dashboardChartPayload['net_profit'][] = round((float) ($m['net_profit'] ?? 0), 2);
}
$selfEmployee = is_array($selfEmployee ?? null) ? $selfEmployee : null;
$selfOpenEntry = is_array($selfOpenEntry ?? null) ? $selfOpenEntry : null;
$canViewPunchBoard = (bool) ($canViewPunchBoard ?? false);
$openPunches = is_array($openPunches ?? null) ? $openPunches : [];

$authUser = auth_user();
$userFirstName = trim((string) ($authUser['first_name'] ?? ''));
$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
$todayLabel = date('l, F j, Y');

$tasksMineOverdue = (int) ($tasks['mine_overdue'] ?? 0);
$tasksMineDueToday = (int) ($tasks['mine_due_today'] ?? 0);

$formatDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $stamp = strtotime($raw);
    if ($stamp === false) {
        return '—';
    }
    return date('m/d/Y g:i A', $stamp);
};

$formatEventTime = static function (?string $iso, bool $allDay): string {
    if ($allDay) {
        return 'All day';
    }
    $raw = trim((string) ($iso ?? ''));
    if ($raw === '') {
        return '—';
    }
    $stamp = strtotime($raw);
    if ($stamp === false) {
        return '—';
    }
    return date('g:i A', $stamp);
};

$employeeDisplayName = static function (array $row): string {
    $linked = trim((string) ($row['linked_user_name'] ?? ''));
    if ($linked !== '') {
        return $linked;
    }
    $employee = trim((string) ($row['employee_name'] ?? ''));
    if ($employee !== '') {
        return $employee;
    }
    return 'Employee #' . (string) ((int) ($row['id'] ?? 0));
};

$canViewFinancials = can_view_financials();
$nowTs = time();
?>

<div class="page-header dashboard-page-header">
    <div>
        <h1><?= e($greeting) ?><?= $userFirstName !== '' ? ', ' . e($userFirstName) : '' ?></h1>
        <p class="muted mb-0"><?= e($todayLabel) ?> · <?= e($businessName) ?></p>
    </div>
</div>

<?php if ($canViewFinancials): ?>
<div class="kpi-grid">
    <a class="kpi-card kpi-card-link kpi-card--sales" href="<?= e(url('/sales')) ?>">
        <span>Sales MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($sales['mtd_gross'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($sales['ytd_gross'] ?? 0), 2)) ?></strong>
        <small>Net MTD $<?= e(number_format((float) ($sales['mtd_net'] ?? 0), 2)) ?> · Net YTD $<?= e(number_format((float) ($sales['ytd_net'] ?? 0), 2)) ?> · excludes estate on-site</small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--service" href="<?= e(url('/reports')) ?>">
        <span>Service Paid MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($service['mtd_gross'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($service['ytd_gross'] ?? 0), 2)) ?></strong>
        <small>Received payments (not invoiced totals)</small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--income" href="<?= e(url('/reports')) ?>">
        <span>Total Income MTD / YTD</span>
        <strong>$<?= e(number_format($totalMtdGross, 2)) ?> / $<?= e(number_format($totalYtdGross, 2)) ?></strong>
        <small>Net MTD $<?= e(number_format($totalMtdNet, 2)) ?> · Net YTD $<?= e(number_format($totalYtdNet, 2)) ?> · sales + estate + service, less general expenses</small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--purchases" href="<?= e(url('/purchases')) ?>">
        <span>Purchases MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($purchasesSummary['mtd_total'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($purchasesSummary['ytd_total'] ?? 0), 2)) ?></strong>
        <small>MTD <?= e((string) ((int) ($purchasesSummary['mtd_count'] ?? 0))) ?> · YTD <?= e((string) ((int) ($purchasesSummary['ytd_count'] ?? 0))) ?></small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--expenses" href="<?= e(url('/expenses')) ?>">
        <span>Expenses MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($expenses['mtd_total'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($expenses['ytd_total'] ?? 0), 2)) ?></strong>
        <small>All recorded expenses</small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--estate-sales" href="<?= e(url('/estate-sale-records')) ?>">
        <span>Estate Sales MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($estateSales['mtd_gross'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($estateSales['ytd_gross'] ?? 0), 2)) ?></strong>
        <small>Net MTD $<?= e(number_format((float) ($estateSales['mtd_net'] ?? 0), 2)) ?> · Net YTD $<?= e(number_format((float) ($estateSales['ytd_net'] ?? 0), 2)) ?> · our share after split</small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--receivables" href="<?= e(url('/billing')) ?>">
        <span>Payments Due / Past Due</span>
        <strong>$<?= e(number_format((float) ($receivables['payments_due'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($receivables['past_due'] ?? 0), 2)) ?></strong>
        <small><?= e((string) ((int) ($receivables['open_invoices'] ?? 0))) ?> open invoice(s)</small>
    </a>
    <a class="kpi-card kpi-card-link kpi-card--profit" href="<?= e(url('/reports')) ?>">
        <span>Profit YTD</span>
        <strong>$<?= e(number_format($profitYtd, 2)) ?><span class="kpi-card-subamount"> ($<?= e(number_format($ytdNetMinusPurchases, 2)) ?>)</span></strong>
        <small>Net YTD less general expenses · After purchase costs (YTD) in parentheses</small>
    </a>
</div>
<?php endif; ?>

<section class="card index-card shadow-sm mt-3 dashboard-agenda-card">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong class="fs-5"><i class="fas fa-briefcase me-2 jt-dashboard-icon--income" aria-hidden="true"></i>Work in progress</strong>
            <div class="small text-muted">Jobs, quotes, deliveries, purchase quotes, estate sales, and appointments</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/events')) ?>">Full calendar</a>
    </div>
    <div class="card-body dashboard-agenda-list">
            <?php if ($upcomingSchedule === []): ?>
                <div class="record-empty">Nothing scheduled on the calendar.</div>
            <?php else: ?>
                <?php foreach ($upcomingSchedule as $dayGroup): ?>
                    <?php
                    if (!is_array($dayGroup)) {
                        continue;
                    }
                    $dayItems = is_array($dayGroup['items'] ?? null) ? $dayGroup['items'] : [];
                    if ($dayItems === []) {
                        continue;
                    }
                    $dayLabel = trim((string) ($dayGroup['label'] ?? ''));
                    $dayIsPast = (bool) ($dayGroup['is_past'] ?? false);
                    $dayIsToday = (bool) ($dayGroup['is_today'] ?? false);
                    ?>
                    <div class="dashboard-agenda-day<?= $dayIsPast ? ' is-past' : '' ?><?= $dayIsToday ? ' is-today' : '' ?>">
                        <div class="dashboard-agenda-day-label"><?= e($dayLabel !== '' ? $dayLabel : 'Scheduled') ?></div>
                        <div class="dashboard-agenda-day-items">
                            <?php foreach ($dayItems as $item): ?>
                                <?php
                                if (!is_array($item)) {
                                    continue;
                                }
                                $itemTitle = trim((string) ($item['title'] ?? '')) ?: 'Event';
                                $itemUrl = trim((string) ($item['url'] ?? ''));
                                $itemType = trim((string) ($item['event_type'] ?? ''));
                                $itemCustomer = trim((string) ($item['customer_name'] ?? ''));
                                $itemAllDay = (bool) ($item['all_day'] ?? false);
                                $itemStart = trim((string) ($item['start'] ?? ''));
                                $itemTime = $formatEventTime($itemStart, $itemAllDay);
                                $itemTs = $itemStart !== '' ? strtotime($itemStart) : false;
                                $isOverdue = ($dayIsPast || ($dayIsToday && !$itemAllDay && $itemTs !== false && $itemTs < $nowTs));
                                $itemColor = trim((string) ($item['color'] ?? ''));
                                $metaParts = array_filter([$itemType !== '' ? $itemType : null, $itemCustomer !== '' ? $itemCustomer : null]);
                                ?>
                                <?php if ($itemUrl !== ''): ?>
                                    <a class="dashboard-agenda-item<?= $isOverdue ? ' is-overdue' : '' ?>" href="<?= e($itemUrl) ?>">
                                        <span class="dashboard-agenda-time"><?= e($itemTime) ?></span>
                                        <span class="dashboard-agenda-dot"<?= $itemColor !== '' ? ' style="background-color:' . e($itemColor) . '"' : '' ?> aria-hidden="true"></span>
                                        <span class="dashboard-agenda-body">
                                            <span class="dashboard-agenda-title"><?= e($itemTitle) ?></span>
                                            <?php if ($metaParts !== []): ?>
                                                <span class="dashboard-agenda-meta"><?= e(implode(' · ', $metaParts)) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                <?php else: ?>
                                    <div class="dashboard-agenda-item<?= $isOverdue ? ' is-overdue' : '' ?>">
                                        <span class="dashboard-agenda-time"><?= e($itemTime) ?></span>
                                        <span class="dashboard-agenda-dot"<?= $itemColor !== '' ? ' style="background-color:' . e($itemColor) . '"' : '' ?> aria-hidden="true"></span>
                                        <span class="dashboard-agenda-body">
                                            <span class="dashboard-agenda-title"><?= e($itemTitle) ?></span>
                                            <?php if ($metaParts !== []): ?>
                                                <span class="dashboard-agenda-meta"><?= e(implode(' · ', $metaParts)) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
    </div>
</section>

<div class="dashboard-secondary-grid mt-3">
    <section class="card index-card shadow-sm dashboard-time-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-user-clock me-2 jt-dashboard-icon--sales" aria-hidden="true"></i>Time</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/time-tracking/punch-board')) ?>">Punch board</a>
        </div>
        <div class="card-body">
                <?php if (!is_array($selfEmployee) || ((int) ($selfEmployee['id'] ?? 0)) <= 0): ?>
                    <div class="record-empty">No linked employee profile.</div>
                <?php else: ?>
                    <?php
                    $selfEmployeeId = (int) ($selfEmployee['id'] ?? 0);
                    $isPunchedIn = is_array($selfOpenEntry) && ((int) ($selfOpenEntry['id'] ?? 0)) > 0;
                    $selfJobTitle = trim((string) ($selfOpenEntry['job_title'] ?? ''));
                    $selfClockInAt = trim((string) ($selfOpenEntry['clock_in_at'] ?? ''));
                    ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <span class="badge <?= $isPunchedIn ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e($isPunchedIn ? 'Punched in' : 'Punched out') ?>
                            </span>
                            <?php if ($isPunchedIn): ?>
                                <div class="small muted mt-1">
                                    <?= e($selfJobTitle !== '' ? $selfJobTitle : 'Non-job time') ?> · Since <?= e($formatDate($selfClockInAt !== '' ? $selfClockInAt : null)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($isPunchedIn): ?>
                            <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="employee_id" value="<?= e((string) $selfEmployeeId) ?>" />
                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fas fa-stop me-1"></i>Punch out</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/time-tracking/punch-in')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="employee_id" value="<?= e((string) $selfEmployeeId) ?>" />
                                <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-play me-1"></i>Punch in</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canViewPunchBoard): ?>
                    <?php if ($openPunches === []): ?>
                        <div class="small text-muted">No one else is punched in.</div>
                    <?php else: ?>
                        <div class="jt-dashboard-subsection-title">Team on the clock</div>
                        <div class="simple-list-table">
                            <?php foreach (array_slice($openPunches, 0, 4) as $row): ?>
                                <?php
                                $employeeId = (int) ($row['id'] ?? 0);
                                $jobTitle = trim((string) ($row['open_job_title'] ?? ''));
                                $clockInAt = trim((string) ($row['open_clock_in_at'] ?? ''));
                                ?>
                                <div class="simple-list-row d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                                    <div>
                                        <span class="simple-list-title"><?= e($employeeDisplayName($row)) ?></span>
                                        <span class="simple-list-meta"><?= e($jobTitle !== '' ? $jobTitle : 'Non-job time') ?> · <?= e($formatDate($clockInAt !== '' ? $clockInAt : null)) ?></span>
                                    </div>
                                    <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                                        <button class="btn btn-outline-danger btn-sm" type="submit" title="Punch out"><i class="fas fa-stop"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($openPunches) > 4): ?>
                            <div class="small text-muted mt-2"><?= e((string) (count($openPunches) - 4)) ?> more on punch board</div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
        </div>
    </section>

    <section class="card index-card shadow-sm dashboard-tasks-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <div>
                <strong class="fs-5">
                    <i class="fas fa-list-check me-2 jt-dashboard-icon--profit" aria-hidden="true"></i>My tasks
                    <?php if ($tasksMineOverdue > 0): ?>
                        <span class="badge text-bg-danger ms-1"><?= e((string) $tasksMineOverdue) ?> overdue</span>
                    <?php elseif ($tasksMineDueToday > 0): ?>
                        <span class="badge text-bg-warning ms-1"><?= e((string) $tasksMineDueToday) ?> due today</span>
                    <?php endif; ?>
                </strong>
                <div class="small text-muted">Open tasks assigned to you</div>
            </div>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/tasks?status=open')) ?>">All tasks</a>
        </div>
        <div class="card-body">
            <?php if ($myTasksDue === []): ?>
                <div class="record-empty">No open tasks assigned to you.</div>
            <?php else: ?>
                <div class="simple-list-table dashboard-tasks-list">
                    <?php foreach ($myTasksDue as $task): ?>
                        <?php
                        $taskId = (int) ($task['id'] ?? 0);
                        $title = trim((string) ($task['title'] ?? '')) ?: ('Task #' . (string) $taskId);
                        $status = strtolower(trim((string) ($task['status'] ?? 'open')));
                        $dueRaw = trim((string) ($task['due_at'] ?? ''));
                        $due = $formatDate($dueRaw !== '' ? $dueRaw : null);
                        $dueTs = $dueRaw !== '' ? strtotime($dueRaw) : false;
                        $taskOverdue = $dueTs !== false && $dueTs < $nowTs;
                        ?>
                        <a class="simple-list-row simple-list-row-link<?= $taskOverdue ? ' is-overdue' : '' ?>" href="<?= e(url('/tasks/' . (string) $taskId)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta"><?= e(str_replace('_', ' ', ucfirst($status))) ?> · <?= e($due) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($canViewFinancials): ?>
<section class="card index-card shadow-sm mt-3 dashboard-trend-chart">
    <div class="card-header index-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong class="fs-5"><i class="fas fa-chart-column me-2 jt-dashboard-icon--sales" aria-hidden="true"></i>Last 3 months</strong>
            <div class="small text-muted">Total gross, sales gross, estate sales gross, service gross, expenses, and net profit by calendar month (same logic as Reports).</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports')) ?>">Full reports</a>
    </div>
    <div class="card-body">
        <div class="jt-dashboard-chart-holder">
            <canvas id="jtDashboardChart" aria-label="Bar chart of last three months" role="img"></canvas>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($canViewFinancials): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    const canvas = document.getElementById('jtDashboardChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }
    const payload = <?= json_encode($dashboardChartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const labels = payload.labels && payload.labels.length ? payload.labels : ['—', '—', '—'];
        const ds = function (key) {
            return Array.isArray(payload[key]) && payload[key].length ? payload[key] : [0, 0, 0];
        };
        const rs = getComputedStyle(document.documentElement);
        const barColors = function (fillVar, strokeVar) {
            return {
                backgroundColor: rs.getPropertyValue(fillVar).trim(),
                borderColor: rs.getPropertyValue(strokeVar).trim(),
                borderWidth: 1,
                borderRadius: 4,
            };
        };
        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    Object.assign({ label: 'Total gross', data: ds('total_gross') }, barColors('--jt-chart-total-gross-fill', '--jt-chart-total-gross-stroke')),
                    Object.assign({ label: 'Sales gross', data: ds('sales_gross') }, barColors('--jt-chart-sales-fill', '--jt-chart-sales-stroke')),
                    Object.assign({ label: 'Estate sales gross', data: ds('estate_sales_gross') }, barColors('--jt-chart-estate-sales-fill', '--jt-chart-estate-sales-stroke')),
                    Object.assign({ label: 'Service gross', data: ds('service_gross') }, barColors('--jt-chart-service-fill', '--jt-chart-service-stroke')),
                    Object.assign({ label: 'Expenses', data: ds('expenses_total') }, barColors('--jt-chart-expenses-fill', '--jt-chart-expenses-stroke')),
                    Object.assign({ label: 'Net profit', data: ds('net_profit') }, barColors('--jt-chart-profit-fill', '--jt-chart-profit-stroke')),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 14, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + fmt.format(ctx.raw);
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        border: { display: false },
                        grid: { color: 'rgba(148, 163, 184, 0.35)' },
                        ticks: {
                            callback: function (v) {
                                return '$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });
                            },
                        },
                    },
                    x: { grid: { display: false }, border: { display: false } },
                },
            },
        });
})();
</script>
<?php endif; ?>
