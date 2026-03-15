<?php
$businessName = (string) (($business['name'] ?? '') !== '' ? $business['name'] : ('Business #' . (string) current_business_id()));
$summary = is_array($summary ?? null) ? $summary : [];
$sales = is_array($summary['sales'] ?? null) ? $summary['sales'] : [];
$service = is_array($summary['service'] ?? null) ? $summary['service'] : [];
$expenses = is_array($summary['expenses'] ?? null) ? $summary['expenses'] : [];
$purchasesSummary = is_array($summary['purchases'] ?? null) ? $summary['purchases'] : [];
$jobs = is_array($summary['jobs'] ?? null) ? $summary['jobs'] : [];
$tasks = is_array($summary['tasks'] ?? null) ? $summary['tasks'] : [];
$lists = is_array($summary['lists'] ?? null) ? $summary['lists'] : [];

$totalMtdGross = (float) ($sales['mtd_gross'] ?? 0) + (float) ($service['mtd_gross'] ?? 0);
$totalYtdGross = (float) ($sales['ytd_gross'] ?? 0) + (float) ($service['ytd_gross'] ?? 0);
$totalMtdNet = (float) ($sales['mtd_net'] ?? 0) + (float) ($service['mtd_net'] ?? 0);
$totalYtdNet = (float) ($sales['ytd_net'] ?? 0) + (float) ($service['ytd_net'] ?? 0);

$dispatchJobs = is_array($lists['dispatch_jobs'] ?? null) ? $lists['dispatch_jobs'] : [];
$prospects = is_array($lists['prospects'] ?? null) ? $lists['prospects'] : [];
$purchaseProspects = is_array($lists['purchase_prospects'] ?? null) ? $lists['purchase_prospects'] : [];
$myTasksDue = is_array($lists['my_tasks_due'] ?? null) ? $lists['my_tasks_due'] : [];
$recentSales = is_array($lists['recent_sales'] ?? null) ? $lists['recent_sales'] : [];
$selfEmployee = is_array($selfEmployee ?? null) ? $selfEmployee : null;
$selfOpenEntry = is_array($selfOpenEntry ?? null) ? $selfOpenEntry : null;
$canViewPunchBoard = (bool) ($canViewPunchBoard ?? false);
$openPunches = is_array($openPunches ?? null) ? $openPunches : [];

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

$formatDateOnly = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $stamp = strtotime($raw);
    if ($stamp === false) {
        return '—';
    }
    return date('m/d/Y', $stamp);
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
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p class="muted"><?= e($businessName) ?></p>
</div>

<div class="kpi-grid">
    <a class="kpi-card kpi-card-link" href="<?= e(url('/sales')) ?>">
        <span>Sales MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($sales['mtd_gross'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($sales['ytd_gross'] ?? 0), 2)) ?></strong>
        <small>Net MTD $<?= e(number_format((float) ($sales['mtd_net'] ?? 0), 2)) ?> · Net YTD $<?= e(number_format((float) ($sales['ytd_net'] ?? 0), 2)) ?></small>
    </a>
    <a class="kpi-card kpi-card-link" href="<?= e(url('/reports')) ?>">
        <span>Service MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($service['mtd_gross'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($service['ytd_gross'] ?? 0), 2)) ?></strong>
        <small>Net MTD $<?= e(number_format((float) ($service['mtd_net'] ?? 0), 2)) ?> · Net YTD $<?= e(number_format((float) ($service['ytd_net'] ?? 0), 2)) ?></small>
    </a>
    <a class="kpi-card kpi-card-link" href="<?= e(url('/reports')) ?>">
        <span>Total Income MTD / YTD</span>
        <strong>$<?= e(number_format($totalMtdGross, 2)) ?> / $<?= e(number_format($totalYtdGross, 2)) ?></strong>
        <small>Net MTD $<?= e(number_format($totalMtdNet, 2)) ?> · Net YTD $<?= e(number_format($totalYtdNet, 2)) ?></small>
    </a>
    <a class="kpi-card kpi-card-link" href="<?= e(url('/purchases')) ?>">
        <span>Purchases MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($purchasesSummary['mtd_total'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($purchasesSummary['ytd_total'] ?? 0), 2)) ?></strong>
        <small>MTD <?= e((string) ((int) ($purchasesSummary['mtd_count'] ?? 0))) ?> · YTD <?= e((string) ((int) ($purchasesSummary['ytd_count'] ?? 0))) ?></small>
    </a>
    <a class="kpi-card kpi-card-link" href="<?= e(url('/expenses')) ?>">
        <span>Expenses MTD / YTD</span>
        <strong>$<?= e(number_format((float) ($expenses['mtd_total'] ?? 0), 2)) ?> / $<?= e(number_format((float) ($expenses['ytd_total'] ?? 0), 2)) ?></strong>
        <small>All recorded expenses</small>
    </a>
</div>

<div class="dashboard-panels mt-3">
    <section class="card index-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-user-clock me-2"></i>My Punch Status</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/time-tracking/punch-board')) ?>">Open Punch Board</a>
        </div>
        <div class="card-body">
            <?php if (!is_array($selfEmployee) || ((int) ($selfEmployee['id'] ?? 0)) <= 0): ?>
                <div class="record-empty">No linked employee profile. Ask a business admin to link your user.</div>
            <?php else: ?>
                <?php
                $selfEmployeeId = (int) ($selfEmployee['id'] ?? 0);
                $isPunchedIn = is_array($selfOpenEntry) && ((int) ($selfOpenEntry['id'] ?? 0)) > 0;
                $selfJobTitle = trim((string) ($selfOpenEntry['job_title'] ?? ''));
                $selfClockInAt = trim((string) ($selfOpenEntry['clock_in_at'] ?? ''));
                ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="mb-1">
                            <span class="badge <?= $isPunchedIn ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e($isPunchedIn ? 'Punched In' : 'Punched Out') ?>
                            </span>
                        </div>
                        <?php if ($isPunchedIn): ?>
                            <div class="small muted">
                                <?= e($selfJobTitle !== '' ? $selfJobTitle : 'Non-Job Time') ?> · Since <?= e($formatDate($selfClockInAt !== '' ? $selfClockInAt : null)) ?>
                            </div>
                        <?php else: ?>
                            <div class="small muted">Punch in starts non-job time unless a job is selected from Punch Board.</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isPunchedIn): ?>
                        <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="employee_id" value="<?= e((string) $selfEmployeeId) ?>" />
                            <button class="btn btn-outline-danger" type="submit"><i class="fas fa-stop me-2"></i>Punch Out</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/time-tracking/punch-in')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="employee_id" value="<?= e((string) $selfEmployeeId) ?>" />
                            <button class="btn btn-success" type="submit"><i class="fas fa-play me-2"></i>Punch In</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canViewPunchBoard): ?>
        <section class="card index-card">
            <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-users me-2"></i>Currently Punched In</strong>
                <a class="small text-decoration-none fw-semibold" href="<?= e(url('/time-tracking/punch-board')) ?>">Open Punch Board</a>
            </div>
            <div class="card-body">
                <?php if ($openPunches === []): ?>
                    <div class="record-empty">No one is currently punched in.</div>
                <?php else: ?>
                    <div class="simple-list-table">
                        <?php foreach ($openPunches as $row): ?>
                            <?php
                            $employeeId = (int) ($row['id'] ?? 0);
                            $jobTitle = trim((string) ($row['open_job_title'] ?? ''));
                            $clockInAt = trim((string) ($row['open_clock_in_at'] ?? ''));
                            ?>
                            <div class="simple-list-row d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <span class="simple-list-title"><?= e($employeeDisplayName($row)) ?></span>
                                    <span class="simple-list-meta"><?= e($jobTitle !== '' ? $jobTitle : 'Non-Job Time') ?> · Since <?= e($formatDate($clockInAt !== '' ? $clockInAt : null)) ?></span>
                                </div>
                                <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fas fa-stop me-1"></i>Punch Out</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card index-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-clipboard-list me-2"></i>Dispatch Queue</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/jobs?status=dispatch')) ?>">Open Jobs</a>
        </div>
        <div class="card-body">
            <?php if ($dispatchJobs === []): ?>
                <div class="record-empty">No pending/active jobs right now.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($dispatchJobs as $job): ?>
                        <?php
                        $jobId = (int) ($job['id'] ?? 0);
                        $title = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
                        $client = trim((string) ($job['client_name'] ?? '')) ?: '—';
                        $scheduled = $formatDate((string) ($job['scheduled_start_at'] ?? ''));
                        $status = strtolower(trim((string) ($job['status'] ?? 'pending')));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta"><?= e($client) ?> · <?= e(ucfirst($status)) ?> · <?= e($scheduled) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card index-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-bullseye me-2"></i>Job Prospects</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/jobs?status=prospect')) ?>">Open Job Prospects</a>
        </div>
        <div class="card-body">
            <?php if ($prospects === []): ?>
                <div class="record-empty">No prospects in queue.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($prospects as $job): ?>
                        <?php
                        $jobId = (int) ($job['id'] ?? 0);
                        $title = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
                        $client = trim((string) ($job['client_name'] ?? '')) ?: '—';
                        $scheduled = $formatDate((string) ($job['scheduled_start_at'] ?? ''));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta"><?= e($client) ?> · <?= e($scheduled) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card index-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-cart-shopping me-2"></i>Purchase Prospects</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/purchases')) ?>">Open Purchases</a>
        </div>
        <div class="card-body">
            <?php if ($purchaseProspects === []): ?>
                <div class="record-empty">No purchase prospects in queue.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($purchaseProspects as $purchase): ?>
                        <?php
                        $purchaseId = (int) ($purchase['id'] ?? 0);
                        $title = trim((string) ($purchase['title'] ?? '')) ?: ('Purchase #' . (string) $purchaseId);
                        $client = trim((string) ($purchase['client_name'] ?? '')) ?: '—';
                        $status = trim((string) ($purchase['status'] ?? ''));
                        $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Open';
                        $when = $formatDateOnly((string) ($purchase['purchase_date'] ?? ''));
                        if ($when === '—') {
                            $when = $formatDateOnly((string) ($purchase['contact_date'] ?? ''));
                        }
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/purchases/' . (string) $purchaseId)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta"><?= e($client) ?> · <?= e($statusLabel) ?> · <?= e($when) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card index-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-list-check me-2"></i>My Tasks Due</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/tasks?status=open')) ?>">Open Tasks</a>
        </div>
        <div class="card-body">
            <?php if ($myTasksDue === []): ?>
                <div class="record-empty">No open tasks assigned to you.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($myTasksDue as $task): ?>
                        <?php
                        $taskId = (int) ($task['id'] ?? 0);
                        $title = trim((string) ($task['title'] ?? '')) ?: ('Task #' . (string) $taskId);
                        $status = strtolower(trim((string) ($task['status'] ?? 'open')));
                        $due = $formatDate((string) ($task['due_at'] ?? ''));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/tasks/' . (string) $taskId)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta"><?= e(str_replace('_', ' ', ucfirst($status))) ?> · <?= e($due) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card index-card">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-sack-dollar me-2"></i>Recent Sales (MTD)</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/sales')) ?>">Open Sales</a>
        </div>
        <div class="card-body">
            <?php if ($recentSales === []): ?>
                <div class="record-empty">No sales recorded this month.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($recentSales as $sale): ?>
                        <?php
                        $saleId = (int) ($sale['id'] ?? 0);
                        $name = trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . (string) $saleId);
                        $type = trim((string) ($sale['sale_type'] ?? '')) ?: 'sale';
                        $gross = (float) ($sale['gross_amount'] ?? 0);
                        $net = (float) ($sale['net_amount'] ?? 0);
                        $saleDate = $formatDate((string) ($sale['sale_date'] ?? ''));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                            <span class="simple-list-title"><?= e($name) ?></span>
                            <span class="simple-list-meta"><?= e($saleDate) ?> · <?= e(strtoupper($type)) ?> · Gross $<?= e(number_format($gross, 2)) ?> · Net $<?= e(number_format($net, 2)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
