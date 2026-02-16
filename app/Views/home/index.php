<?php
    $overview = $overview ?? [];
    $period = is_array($overview['period'] ?? null) ? $overview['period'] : [];
    $counts = is_array($overview['counts'] ?? null) ? $overview['counts'] : [];
    $revenue = is_array($overview['revenue'] ?? null) ? $overview['revenue'] : [];
    $sales = is_array($revenue['sales'] ?? null) ? $revenue['sales'] : [];
    $jobsRevenue = is_array($revenue['jobs'] ?? null) ? $revenue['jobs'] : [];
    $totals = is_array($revenue['totals'] ?? null) ? $revenue['totals'] : [];
    $expenses = is_array($revenue['expenses'] ?? null) ? $revenue['expenses'] : [];
    $onClock = is_array($overview['on_clock'] ?? null) ? $overview['on_clock'] : [];
    $onClockSummary = is_array($onClock['summary'] ?? null) ? $onClock['summary'] : [];
    $onClockEntries = is_array($onClock['entries'] ?? null) ? $onClock['entries'] : [];
    $prospects = is_array($overview['prospects'] ?? null) ? $overview['prospects'] : [];
    $followUps = is_array($prospects['follow_ups'] ?? null) ? $prospects['follow_ups'] : [];
    $jobPipeline = is_array($overview['job_pipeline'] ?? null) ? $overview['job_pipeline'] : [];
    $pendingJobs = is_array($jobPipeline['pending'] ?? null) ? $jobPipeline['pending'] : [];
    $activeJobs = is_array($jobPipeline['active'] ?? null) ? $jobPipeline['active'] : [];
    $tasks = is_array($overview['tasks'] ?? null) ? $overview['tasks'] : [];

    $money = static fn (mixed $value): string => '$' . number_format((float) ($value ?? 0), 2);
    $minutes = static function (mixed $value): string {
        $total = (int) ($value ?? 0);
        if ($total <= 0) {
            return '0h 00m';
        }

        return intdiv($total, 60) . 'h ' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT) . 'm';
    };
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Dashboard</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">Operations Summary</li>
            </ol>
        </div>
        <div class="text-muted small">
            MTD: <?= e(format_date($period['mtd_start'] ?? null)) ?> - <?= e(format_date($period['today'] ?? null)) ?>
            &nbsp;|&nbsp;
            YTD: <?= e(format_date($period['ytd_start'] ?? null)) ?> - <?= e(format_date($period['today'] ?? null)) ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card text-white shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #ff6a00, #ff3d00);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase">Active Prospects</div>
                            <div class="h3 mb-1"><?= e((string) ((int) ($counts['prospects_active'] ?? 0))) ?></div>
                            <div class="small">Follow-Up Due: <?= e((string) ((int) ($counts['prospects_follow_up_due'] ?? 0))) ?></div>
                        </div>
                        <i class="fas fa-user-plus fs-4 opacity-75"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a class="small text-white text-decoration-none" href="<?= url('/prospects') ?>">Open Prospects <i class="fas fa-angle-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card text-white shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #ffb300, #ff8f00);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase">Pending Jobs</div>
                            <div class="h3 mb-1"><?= e((string) ((int) ($counts['jobs_pending'] ?? 0))) ?></div>
                            <div class="small">Ready to schedule/activate</div>
                        </div>
                        <i class="fas fa-hourglass-half fs-4 opacity-75"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a class="small text-white text-decoration-none" href="<?= url('/jobs?status=pending') ?>">View Pending <i class="fas fa-angle-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card text-white shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #16a34a, #0284c7);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase">Active Jobs</div>
                            <div class="h3 mb-1"><?= e((string) ((int) ($counts['jobs_active'] ?? 0))) ?></div>
                            <div class="small">In progress</div>
                        </div>
                        <i class="fas fa-briefcase fs-4 opacity-75"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a class="small text-white text-decoration-none" href="<?= url('/jobs?status=active') ?>">View Active Jobs <i class="fas fa-angle-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card text-white shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #0ea5e9, #1d4ed8);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase">Crew On Clock</div>
                            <div class="h3 mb-1"><?= e((string) ((int) ($onClockSummary['active_count'] ?? 0))) ?></div>
                            <div class="small">Open hours: <?= e($minutes($onClockSummary['total_open_minutes'] ?? 0)) ?></div>
                        </div>
                        <i class="fas fa-user-clock fs-4 opacity-75"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a class="small text-white text-decoration-none" href="<?= url('/time-tracking/open') ?>">Open Punch Clock <i class="fas fa-angle-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Sales Gross MTD</div>
                    <div class="h3 mb-1 text-primary"><?= e($money($sales['gross_mtd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net MTD: <?= e($money($sales['net_mtd'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Jobs Gross MTD</div>
                    <div class="h3 mb-1 text-success"><?= e($money($jobsRevenue['gross_mtd'] ?? 0)) ?></div>
                    <div class="small text-muted">Based on billed/paid dates</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Gross MTD</div>
                    <div class="h3 mb-1 text-danger"><?= e($money($totals['gross_mtd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net after expenses: <?= e($money($totals['net_mtd'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Sales Gross YTD</div>
                    <div class="h3 mb-1 text-primary"><?= e($money($sales['gross_ytd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net YTD: <?= e($money($sales['net_ytd'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Jobs Gross YTD</div>
                    <div class="h3 mb-1 text-success"><?= e($money($jobsRevenue['gross_ytd'] ?? 0)) ?></div>
                    <div class="small text-muted">Based on billed/paid dates</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Gross YTD</div>
                    <div class="h3 mb-1 text-danger"><?= e($money($totals['gross_ytd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net after expenses: <?= e($money($totals['net_ytd'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Expenses MTD</div>
                    <div class="h4 mb-0 text-warning"><?= e($money($expenses['mtd'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Expenses YTD</div>
                    <div class="h4 mb-0 text-warning"><?= e($money($expenses['ytd'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Open Tasks</div>
                    <div class="h4 mb-0 text-info"><?= e((string) ((int) ($tasks['open_count'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Overdue Tasks</div>
                    <div class="h4 mb-0 text-danger"><?= e((string) ((int) ($tasks['overdue_count'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-user-clock me-1"></i>Employees Currently Punched In</div>
                    <a class="small text-decoration-none" href="<?= url('/time-tracking/open') ?>">Open Clock</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Job</th>
                                    <th>Since</th>
                                    <th>Elapsed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($onClockEntries)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No employees currently punched in.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($onClockEntries as $entry): ?>
                                        <?php
                                            $entryId = (int) ($entry['id'] ?? 0);
                                            $employeeId = (int) ($entry['employee_id'] ?? 0);
                                            $jobId = (int) ($entry['job_id'] ?? 0);
                                            $since = trim((string) ($entry['work_date'] ?? '') . ' ' . (string) ($entry['start_time'] ?? ''));
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($employeeId > 0): ?>
                                                    <a class="text-decoration-none" href="<?= url('/employees/' . $employeeId) ?>">
                                                        <?= e((string) ($entry['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e((string) ($entry['employee_name'] ?? '—')) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($jobId > 0): ?>
                                                    <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                                        <?= e((string) ($entry['job_name'] ?? ('Job #' . $jobId))) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non-Job Time</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e($since !== '' ? format_datetime($since) : '—') ?></td>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/time-tracking/' . $entryId . '?return_to=' . urlencode('/')) ?>">
                                                    <?= e($minutes($entry['open_minutes'] ?? 0)) ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-bullseye me-1"></i>Prospect Follow-Ups</div>
                    <a class="small text-decoration-none" href="<?= url('/prospects') ?>">Open Prospects</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Prospect</th>
                                    <th>Next Step</th>
                                    <th>Follow-Up</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($followUps)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No active follow-ups queued.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($followUps as $prospect): ?>
                                        <?php $prospectId = (int) ($prospect['id'] ?? 0); ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/prospects/' . $prospectId) ?>">
                                                    <?= e((string) ($prospect['client_name'] ?? ('Prospect #' . $prospectId))) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string) (($prospect['next_step'] ?? '') !== '' ? $prospect['next_step'] : '—')) ?></td>
                                            <td><?= e(format_date($prospect['follow_up_on'] ?? null)) ?></td>
                                            <td><?= e((string) ((int) ($prospect['priority_rating'] ?? 0) > 0 ? (int) $prospect['priority_rating'] : '—')) ?></td>
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

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-hourglass-half me-1"></i>Pending Jobs Queue</div>
                    <a class="small text-decoration-none" href="<?= url('/jobs?status=pending') ?>">All Pending</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Client</th>
                                    <th>Scheduled</th>
                                    <th>Quote</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingJobs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No pending jobs in queue.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendingJobs as $job): ?>
                                        <?php $jobId = (int) ($job['id'] ?? 0); ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                                    #<?= e((string) $jobId) ?> - <?= e((string) (($job['name'] ?? '') !== '' ? $job['name'] : ('Job #' . $jobId))) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string) (($job['client_name'] ?? '') !== '' ? $job['client_name'] : '—')) ?></td>
                                            <td><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></td>
                                            <td><?= isset($job['total_quote']) ? e($money($job['total_quote'])) : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-play-circle me-1"></i>Active Jobs In Progress</div>
                    <a class="small text-decoration-none" href="<?= url('/jobs?status=active') ?>">All Active</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Client</th>
                                    <th>Scheduled</th>
                                    <th>Billed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activeJobs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No active jobs currently running.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activeJobs as $job): ?>
                                        <?php $jobId = (int) ($job['id'] ?? 0); ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                                    #<?= e((string) $jobId) ?> - <?= e((string) (($job['name'] ?? '') !== '' ? $job['name'] : ('Job #' . $jobId))) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string) (($job['client_name'] ?? '') !== '' ? $job['client_name'] : '—')) ?></td>
                                            <td><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></td>
                                            <td><?= isset($job['total_billed']) ? e($money($job['total_billed'])) : '—' ?></td>
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
