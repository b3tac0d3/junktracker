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
    $tasksOutstanding = is_array($overview['tasks_outstanding'] ?? null) ? $overview['tasks_outstanding'] : [];
    $overdueTasks = is_array($tasksOutstanding['overdue'] ?? null) ? $tasksOutstanding['overdue'] : [];
    $upcomingTasks = is_array($tasksOutstanding['upcoming'] ?? null) ? $tasksOutstanding['upcoming'] : [];
    $invites = is_array($overview['invites'] ?? null) ? $overview['invites'] : [];
    $inviteSummary = is_array($invites['summary'] ?? null) ? $invites['summary'] : [];
    $inviteRows = is_array($invites['rows'] ?? null) ? $invites['rows'] : [];
    $completedUnbilled = is_array($overview['completed_unbilled_jobs'] ?? null) ? $overview['completed_unbilled_jobs'] : [];
    $completedUnbilledRows = is_array($completedUnbilled['rows'] ?? null) ? $completedUnbilled['rows'] : [];
    $completedUnbilledSummary = is_array($completedUnbilled['summary'] ?? null) ? $completedUnbilled['summary'] : [];
    $alertQueue = is_array($overview['alert_queue'] ?? null) ? $overview['alert_queue'] : [];
    $consignorPayments = is_array($overview['consignor_payments'] ?? null) ? $overview['consignor_payments'] : [];
    $consignorPaymentRows = is_array($consignorPayments['rows'] ?? null) ? $consignorPayments['rows'] : [];
    $consignorPaymentSummary = is_array($consignorPayments['summary'] ?? null) ? $consignorPayments['summary'] : [];
    $mtdStart = (string) ($period['mtd_start'] ?? date('Y-m-01'));
    $ytdStart = (string) ($period['ytd_start'] ?? date('Y-01-01'));
    $today = (string) ($period['today'] ?? date('Y-m-d'));
    $salesMtdUrl = url('/sales?start_date=' . urlencode($mtdStart) . '&end_date=' . urlencode($today));
    $salesYtdUrl = url('/sales?start_date=' . urlencode($ytdStart) . '&end_date=' . urlencode($today));
    $jobsMtdUrl = url('/jobs?start_date=' . urlencode($mtdStart) . '&end_date=' . urlencode($today));
    $jobsYtdUrl = url('/jobs?start_date=' . urlencode($ytdStart) . '&end_date=' . urlencode($today));
    $expensesMtdUrl = url('/expenses?start_date=' . urlencode($mtdStart) . '&end_date=' . urlencode($today));
    $expensesYtdUrl = url('/expenses?start_date=' . urlencode($ytdStart) . '&end_date=' . urlencode($today));
    $tasksOpenUrl = url('/tasks?status=open');
    $tasksOverdueUrl = url('/tasks?status=overdue');
    $consignorsUrl = url('/consignors');
    $usersUrl = url('/users');
    $canViewUsers = can_access('users', 'view');
    $selfPunch = is_array($selfPunch ?? null) ? $selfPunch : [];
    $selfEmployee = is_array($selfPunch['employee'] ?? null) ? $selfPunch['employee'] : null;
    $selfOpenEntry = is_array($selfPunch['open_entry'] ?? null) ? $selfPunch['open_entry'] : null;
    $selfCanManage = !empty($selfPunch['can_manage']);
    $selfCanPunchIn = !empty($selfPunch['can_punch_in']);
    $selfCanPunchOut = !empty($selfPunch['can_punch_out']);
    $selfOpenLabel = trim((string) ($selfPunch['open_label'] ?? ''));
    $selfMessage = trim((string) ($selfPunch['message'] ?? ''));
    $selfJobLookupUrl = url('/time-tracking/lookup/jobs');
    $businessLabel = trim((string) ($businessLabel ?? ''));

    $money = static fn (mixed $value): string => '$' . number_format((float) ($value ?? 0), 2);
    $minutes = static function (mixed $value): string {
        $total = (int) ($value ?? 0);
        if ($total <= 0) {
            return '0h 00m';
        }

        return intdiv($total, 60) . 'h ' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT) . 'm';
    };
?>
<div class="container-fluid px-4 dashboard-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Dashboard</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">
                    <?php if ($businessLabel !== ''): ?>
                        <div class="fw-bold"><?= e($businessLabel) ?></div>
                    <?php endif; ?>
                </li>
            </ol>
        </div>
        <div class="text-muted small">
            MTD: <?= e(format_date($period['mtd_start'] ?? null)) ?> - <?= e(format_date($period['today'] ?? null)) ?>
            &nbsp;|&nbsp;
            YTD: <?= e(format_date($period['ytd_start'] ?? null)) ?> - <?= e(format_date($period['today'] ?? null)) ?>
        </div>
    </div>

    <?php if ($selfCanManage): ?>
        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div><i class="fas fa-bolt me-1"></i>Quick Actions</div>
                        <span class="small text-muted">Daily workflow</span>
                    </div>
                    <div class="card-body">
                        <div class="dashboard-top-actions-buttons">
                            <a class="btn btn-primary" href="<?= url('/jobs/new') ?>"><i class="fas fa-plus me-1"></i>Add Job</a>
                            <a class="btn btn-warning text-dark" href="<?= url('/prospects/new') ?>"><i class="fas fa-user-plus me-1"></i>Add Prospect</a>
                            <a class="btn btn-success" href="<?= url('/time-tracking/open') ?>"><i class="fas fa-user-clock me-1"></i>Open Punch Clock</a>
                            <a class="btn btn-info text-white" href="<?= url('/expenses/new') ?>"><i class="fas fa-receipt me-1"></i>Add Expense</a>
                            <a class="btn btn-outline-secondary" href="<?= url('/sales/new') ?>"><i class="fas fa-sack-dollar me-1"></i>Add Sale</a>
                            <a class="btn btn-outline-dark" href="<?= url('/tasks/new') ?>"><i class="fas fa-list-check me-1"></i>Add Task</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div><i class="fas fa-user-clock me-1"></i>My Punch Clock</div>
                        <?php if ($selfEmployee): ?>
                            <?php if ($selfOpenEntry): ?>
                                <span class="badge bg-success">Punched In</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Punched Out</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <?php if ($selfEmployee): ?>
                                    <div class="fw-semibold"><?= e((string) ($selfEmployee['name'] ?? 'Employee')) ?></div>
                                    <?php if ($selfOpenEntry): ?>
                                        <div class="small text-muted">
                                            On: <?= e($selfOpenLabel !== '' ? $selfOpenLabel : 'Non-Job Time') ?>
                                            &nbsp;•&nbsp;
                                            Since: <?= e(format_datetime((string) ($selfOpenEntry['work_date'] ?? '') . ' ' . (string) ($selfOpenEntry['start_time'] ?? ''))) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-muted">Quick Punch In / Out</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="fw-semibold text-danger"><?= e($selfMessage !== '' ? $selfMessage : 'No linked employee profile found.') ?></div>
                                    <div class="small text-muted">Set employee email to match your login (or add an employee profile).</div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mobile-two-col-buttons">
                                <?php if ($selfCanPunchOut && $selfEmployee): ?>
                                    <form class="js-punch-geo-form" method="post" action="<?= url('/dashboard/punch-out') ?>">
                                        <?= csrf_field() ?>
                                        <?= geo_capture_fields('dashboard_self_punch_out') ?>
                                        <button class="btn btn-danger" type="submit">
                                            <i class="fas fa-stop-circle me-1"></i>
                                            Punch Me Out
                                        </button>
                                    </form>
                                <?php elseif ($selfCanPunchIn && $selfEmployee): ?>
                                    <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#dashboardPunchInModal">
                                        <i class="fas fa-play-circle me-1"></i>
                                        Punch Me In
                                    </button>
                                <?php endif; ?>

                                <?php if ($selfEmployee): ?>
                                    <a class="btn btn-outline-secondary" href="<?= url('/time-tracking/new') ?>">
                                        <i class="fas fa-clock me-1"></i>
                                        Time Tracking
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div><i class="fas fa-bolt me-1"></i>Quick Actions</div>
                <span class="small text-muted">Daily workflow</span>
            </div>
            <div class="card-body">
                <div class="dashboard-top-actions-buttons">
                    <a class="btn btn-primary" href="<?= url('/jobs/new') ?>"><i class="fas fa-plus me-1"></i>Add Job</a>
                    <a class="btn btn-warning text-dark" href="<?= url('/prospects/new') ?>"><i class="fas fa-user-plus me-1"></i>Add Prospect</a>
                    <a class="btn btn-success" href="<?= url('/time-tracking/open') ?>"><i class="fas fa-user-clock me-1"></i>Open Punch Clock</a>
                    <a class="btn btn-info text-white" href="<?= url('/expenses/new') ?>"><i class="fas fa-receipt me-1"></i>Add Expense</a>
                    <a class="btn btn-outline-secondary" href="<?= url('/sales/new') ?>"><i class="fas fa-sack-dollar me-1"></i>Add Sale</a>
                    <a class="btn btn-outline-dark" href="<?= url('/tasks/new') ?>"><i class="fas fa-list-check me-1"></i>Add Task</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selfCanPunchIn && $selfEmployee): ?>
        <div class="modal fade" id="dashboardPunchInModal" tabindex="-1" aria-labelledby="dashboardPunchInModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content dashboard-self-punch-modal">
                    <form class="js-punch-geo-form" method="post" action="<?= url('/dashboard/punch-in') ?>" id="dashboardSelfPunchForm">
                        <?= csrf_field() ?>
                        <?= geo_capture_fields('dashboard_self_punch_in') ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="dashboardPunchInModalLabel">Punch Me In</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Search for a job (optional). If left blank, this punch-in is saved as Non-Job Time.</p>

                            <input type="hidden" id="dashboard_self_job_lookup_url" value="<?= e($selfJobLookupUrl) ?>">
                            <input type="hidden" id="dashboard_self_job_id" name="job_id" value="">

                            <div id="dashboardSelfJobLookupWrap" class="position-relative">
                                <label class="form-label" for="dashboard_self_job_search">Job (optional)</label>
                                <input
                                    class="form-control"
                                    id="dashboard_self_job_search"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Search job by name, id, city..."
                                />
                                <div id="dashboard_self_job_suggestions" class="list-group position-absolute w-100 shadow-sm d-none"></div>
                                <div class="form-text">Pick from suggestions to attach this punch to a job.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-play-circle me-1"></i>
                                Punch In
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card text-white shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #ff6a00, #ff3d00);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase">Active Prospects</div>
                            <div class="h3 mb-1 text-white"><?= e((string) ((int) ($counts['prospects_active'] ?? 0))) ?></div>
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
                            <div class="h3 mb-1 text-white"><?= e((string) ((int) ($counts['jobs_pending'] ?? 0))) ?></div>
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
                            <div class="h3 mb-1 text-white"><?= e((string) ((int) ($counts['jobs_active'] ?? 0))) ?></div>
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
                            <div class="h3 mb-1 text-white"><?= e((string) ((int) ($onClockSummary['active_count'] ?? 0))) ?></div>
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

    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div><i class="fas fa-triangle-exclamation me-1"></i>Alert Queue</div>
            <div class="d-flex gap-2 flex-wrap mobile-two-col-buttons">
                <a class="badge text-bg-danger text-decoration-none" href="<?= url('/tasks?status=overdue') ?>">Overdue Tasks: <?= e((string) ((int) ($tasks['overdue_count'] ?? 0))) ?></a>
                <a class="badge text-bg-warning text-decoration-none" href="<?= url('/jobs?status=complete&billing_state=unbilled') ?>">Completed Unbilled Jobs: <?= e((string) ((int) ($completedUnbilledSummary['count_total'] ?? 0))) ?></a>
                <a class="badge text-bg-primary text-decoration-none" href="<?= url('/consignors') ?>">Consignor Payments Due: <?= e((string) ((int) ($consignorPaymentSummary['due_now_count'] ?? 0))) ?></a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($alertQueue)): ?>
                <div class="p-3 text-muted">No active alerts right now.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($alertQueue as $alert): ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= url((string) ($alert['url'] ?? '/')) ?>">
                            <span class="fw-semibold"><?= e((string) ($alert['label'] ?? 'Alert')) ?></span>
                            <span class="small text-muted"><?= e((string) ($alert['meta'] ?? '')) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canViewUsers && (int) ($inviteSummary['outstanding_count'] ?? 0) > 0): ?>
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div><i class="fas fa-user-clock me-1"></i>Outstanding User Invites</div>
                <a class="small text-decoration-none" href="<?= e($usersUrl) ?>">Open Users</a>
            </div>
            <div class="card-body p-0">
                <div class="px-3 py-2 border-bottom">
                    <span class="badge text-bg-warning">Invited: <?= e((string) ((int) ($inviteSummary['invited_count'] ?? 0))) ?></span>
                    <span class="badge text-bg-danger ms-1">Expired: <?= e((string) ((int) ($inviteSummary['expired_count'] ?? 0))) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Invited</th>
                                <th>Expires</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inviteRows as $inviteRow): ?>
                                <?php
                                    $inviteUserId = (int) ($inviteRow['id'] ?? 0);
                                    $inviteName = trim((string) ($inviteRow['first_name'] ?? '') . ' ' . (string) ($inviteRow['last_name'] ?? ''));
                                    if ($inviteName === '') {
                                        $inviteName = 'User #' . $inviteUserId;
                                    }
                                    $inviteMeta = is_array($inviteRow['invite'] ?? null) ? $inviteRow['invite'] : [];
                                ?>
                                <tr>
                                    <td><a class="text-decoration-none" href="<?= url('/users/' . $inviteUserId) ?>"><?= e($inviteName) ?></a></td>
                                    <td><?= e((string) ($inviteRow['email'] ?? '')) ?></td>
                                    <td>
                                        <span class="badge <?= e((string) ($inviteMeta['badge_class'] ?? 'bg-secondary')) ?>">
                                            <?= e((string) ($inviteMeta['label'] ?? 'Invited')) ?>
                                        </span>
                                    </td>
                                    <td><?= e(format_datetime($inviteMeta['sent_at'] ?? null)) ?></td>
                                    <td><?= e(format_datetime($inviteMeta['expires_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Sales Gross MTD</div>
                    <div class="h3 mb-1 text-primary"><?= e($money($sales['gross_mtd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net MTD: <?= e($money($sales['net_mtd'] ?? 0)) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($salesMtdUrl) ?>" aria-label="Open sales month-to-date"></a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Jobs Gross MTD</div>
                    <div class="h3 mb-1 text-success"><?= e($money($jobsRevenue['gross_mtd'] ?? 0)) ?></div>
                    <div class="small text-muted">Based on billed/paid dates</div>
                </div>
                <a class="stretched-link" href="<?= e($jobsMtdUrl) ?>" aria-label="Open jobs month-to-date"></a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Gross MTD</div>
                    <div class="h3 mb-1 text-danger"><?= e($money($totals['gross_mtd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net after expenses: <?= e($money($totals['net_mtd'] ?? 0)) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($jobsMtdUrl) ?>" aria-label="Open total month-to-date breakdown"></a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Sales Gross YTD</div>
                    <div class="h3 mb-1 text-primary"><?= e($money($sales['gross_ytd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net YTD: <?= e($money($sales['net_ytd'] ?? 0)) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($salesYtdUrl) ?>" aria-label="Open sales year-to-date"></a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Jobs Gross YTD</div>
                    <div class="h3 mb-1 text-success"><?= e($money($jobsRevenue['gross_ytd'] ?? 0)) ?></div>
                    <div class="small text-muted">Based on billed/paid dates</div>
                </div>
                <a class="stretched-link" href="<?= e($jobsYtdUrl) ?>" aria-label="Open jobs year-to-date"></a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Gross YTD</div>
                    <div class="h3 mb-1 text-danger"><?= e($money($totals['gross_ytd'] ?? 0)) ?></div>
                    <div class="small text-muted">Net after expenses: <?= e($money($totals['net_ytd'] ?? 0)) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($jobsYtdUrl) ?>" aria-label="Open total year-to-date breakdown"></a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Expenses MTD</div>
                    <div class="h4 mb-0 text-warning"><?= e($money($expenses['mtd'] ?? 0)) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($expensesMtdUrl) ?>" aria-label="Open expenses month-to-date"></a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Expenses YTD</div>
                    <div class="h4 mb-0 text-warning"><?= e($money($expenses['ytd'] ?? 0)) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($expensesYtdUrl) ?>" aria-label="Open expenses year-to-date"></a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Open Tasks</div>
                    <div class="h4 mb-0 text-info"><?= e((string) ((int) ($tasks['open_count'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($tasksOpenUrl) ?>" aria-label="Open active tasks"></a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Overdue Tasks</div>
                    <div class="h4 mb-0 text-danger"><?= e((string) ((int) ($tasks['overdue_count'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($tasksOverdueUrl) ?>" aria-label="Open overdue tasks"></a>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 position-relative">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Consignor Payments Due</div>
                    <div class="h4 mb-0 text-primary"><?= e((string) ((int) ($consignorPaymentSummary['due_now_count'] ?? 0))) ?></div>
                    <div class="small text-muted">Upcoming: <?= e((string) ((int) ($consignorPaymentSummary['upcoming_count'] ?? 0))) ?></div>
                </div>
                <a class="stretched-link" href="<?= e($consignorsUrl) ?>" aria-label="Open consignors"></a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-list-check me-1"></i>Outstanding Tasks</div>
                    <a class="small text-decoration-none" href="<?= url('/tasks') ?>">Open Tasks</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
                            <thead>
                                <tr>
                                    <th>Done</th>
                                    <th>Bucket</th>
                                    <th>Task</th>
                                    <th>Linked</th>
                                    <th>Assigned</th>
                                    <th>Due</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($overdueTasks) && empty($upcomingTasks)): ?>
                                    <tr>
                                        <td colspan="7" class="text-muted">No overdue or upcoming tasks.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($overdueTasks as $taskRow): ?>
                                        <?php $taskId = (int) ($taskRow['id'] ?? 0); ?>
                                        <tr data-task-id="<?= e((string) $taskId) ?>">
                                            <td>
                                                <form method="post" action="<?= url('/tasks/' . $taskId . '/toggle-complete') ?>" class="js-task-toggle-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="/" />
                                                    <input type="hidden" name="is_completed" value="0" />
                                                    <input class="form-check-input" type="checkbox" name="is_completed" value="1" onchange="this.form.submit()" />
                                                </form>
                                            </td>
                                            <td><span class="badge bg-danger">Overdue</span></td>
                                            <td>
                                                <a class="text-decoration-none js-task-title" href="<?= url('/tasks/' . $taskId) ?>">
                                                    <?= e((string) (($taskRow['title'] ?? '') !== '' ? $taskRow['title'] : ('Task #' . $taskId))) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (!empty($taskRow['link_url'])): ?>
                                                    <a class="text-decoration-none" href="<?= url((string) $taskRow['link_url']) ?>">
                                                        <?= e((string) ($taskRow['link_label'] ?? '—')) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e((string) ($taskRow['link_label'] ?? '—')) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e((string) (($taskRow['assigned_user_name'] ?? '') !== '' ? $taskRow['assigned_user_name'] : 'Unassigned')) ?></td>
                                            <td class="text-danger fw-semibold"><?= e(format_datetime($taskRow['due_at'] ?? null)) ?></td>
                                            <td><?= e((string) ($taskRow['importance'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($upcomingTasks as $taskRow): ?>
                                        <?php $taskId = (int) ($taskRow['id'] ?? 0); ?>
                                        <tr data-task-id="<?= e((string) $taskId) ?>">
                                            <td>
                                                <form method="post" action="<?= url('/tasks/' . $taskId . '/toggle-complete') ?>" class="js-task-toggle-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="/" />
                                                    <input type="hidden" name="is_completed" value="0" />
                                                    <input class="form-check-input" type="checkbox" name="is_completed" value="1" onchange="this.form.submit()" />
                                                </form>
                                            </td>
                                            <td><span class="badge bg-warning text-dark">Upcoming</span></td>
                                            <td>
                                                <a class="text-decoration-none js-task-title" href="<?= url('/tasks/' . $taskId) ?>">
                                                    <?= e((string) (($taskRow['title'] ?? '') !== '' ? $taskRow['title'] : ('Task #' . $taskId))) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (!empty($taskRow['link_url'])): ?>
                                                    <a class="text-decoration-none" href="<?= url((string) $taskRow['link_url']) ?>">
                                                        <?= e((string) ($taskRow['link_label'] ?? '—')) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e((string) ($taskRow['link_label'] ?? '—')) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e((string) (($taskRow['assigned_user_name'] ?? '') !== '' ? $taskRow['assigned_user_name'] : 'Unassigned')) ?></td>
                                            <td><?= e(format_datetime($taskRow['due_at'] ?? null)) ?></td>
                                            <td><?= e((string) ($taskRow['importance'] ?? '—')) ?></td>
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
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-handshake me-1"></i>Consignor Payment Schedule</div>
                    <a class="small text-decoration-none" href="<?= url('/consignors') ?>">Open Consignors</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
                            <thead>
                                <tr>
                                    <th>Consignor</th>
                                    <th>#</th>
                                    <th>Schedule</th>
                                    <th>Next Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($consignorPaymentRows)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No consignor payment due dates set.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($consignorPaymentRows as $row): ?>
                                        <?php
                                            $consignorId = (int) ($row['id'] ?? 0);
                                            $name = trim((string) ($row['business_name'] ?? ''));
                                            if ($name === '') {
                                                $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                                            }
                                            if ($name === '') {
                                                $name = 'Consignor #' . $consignorId;
                                            }
                                            $dueDate = (string) ($row['next_payment_due_date'] ?? '');
                                            $dueClass = '';
                                            if ($dueDate !== '' && strtotime($dueDate) !== false && strtotime($dueDate) <= strtotime(date('Y-m-d'))) {
                                                $dueClass = 'text-danger fw-semibold';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/consignors/' . $consignorId) ?>">
                                                    <?= e($name) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string) (($row['consignor_number'] ?? '') !== '' ? $row['consignor_number'] : '—')) ?></td>
                                            <td><?= e((string) (($row['payment_schedule'] ?? '') !== '' ? ucfirst((string) $row['payment_schedule']) : '—')) ?></td>
                                            <td class="<?= e($dueClass) ?>"><?= e(format_date($row['next_payment_due_date'] ?? null)) ?></td>
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
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-user-clock me-1"></i>Employees Currently Punched In</div>
                    <a class="small text-decoration-none" href="<?= url('/time-tracking/open') ?>">Open Clock</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
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
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-bullseye me-1"></i>Prospect Follow-Ups</div>
                    <a class="small text-decoration-none" href="<?= url('/prospects') ?>">Open Prospects</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
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
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-hourglass-half me-1"></i>Pending Jobs Queue</div>
                    <a class="small text-decoration-none" href="<?= url('/jobs?status=pending') ?>">All Pending</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
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
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div><i class="fas fa-play-circle me-1"></i>Active Jobs In Progress</div>
                    <a class="small text-decoration-none" href="<?= url('/jobs?status=active') ?>">All Active</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 js-card-list-source">
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
