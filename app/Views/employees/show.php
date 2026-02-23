<?php
    $employee = $employee ?? [];
    $fullName = trim(((string) ($employee['first_name'] ?? '')) . ' ' . ((string) ($employee['last_name'] ?? '')));
    if ($fullName === '') {
        $fullName = 'Employee #' . (string) ($employee['id'] ?? '');
    }

    $payRate = $employee['hourly_rate'] ?? ($employee['wage_rate'] ?? null);
    $isActive = empty($employee['deleted_at']) && !empty($employee['active']);
    $laborSummary = $laborSummary ?? [];
    $openClockEntry = $openClockEntry ?? null;
    $openClockElapsed = $openClockElapsed ?? null;
    $openClockSince = '—';
    if (!empty($openClockEntry['work_date']) && !empty($openClockEntry['start_time'])) {
        $openClockSince = format_datetime((string) $openClockEntry['work_date'] . ' ' . (string) $openClockEntry['start_time']);
    }
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Employee Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/employees') ?>">Employees</a></li>
                <li class="breadcrumb-item active">#<?= e((string) ($employee['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-warning" href="<?= url('/employees/' . ($employee['id'] ?? '') . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Employee
            </a>
            <a class="btn btn-outline-secondary" href="<?= url('/employees') ?>">Back to Employees</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 mobile-two-col-buttons">
            <div>
                <i class="fas fa-user-clock me-1"></i>
                Punch Clock
            </div>
            <?php if ($openClockEntry): ?>
                <span class="badge bg-success">Punched In</span>
            <?php else: ?>
                <span class="badge bg-secondary">Punched Out</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$isActive): ?>
                <div class="text-muted">Inactive employees cannot be punched in or out.</div>
            <?php elseif ($openClockEntry): ?>
                <?php $openJobId = (int) ($openClockEntry['job_id'] ?? 0); ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <div class="text-muted small">Current Job</div>
                        <div class="fw-semibold">
                            <?php if ($openJobId > 0): ?>
                                <a class="text-decoration-none" href="<?= url('/jobs/' . $openJobId) ?>">
                                    <?= e((string) ($openClockEntry['job_name'] ?? ('Job #' . $openJobId))) ?>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">Non-Job Time</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Since</div>
                        <div class="fw-semibold"><?= e($openClockSince) ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Elapsed</div>
                        <div class="fw-semibold"><?= e((string) ($openClockElapsed ?? '—')) ?></div>
                    </div>
                    <div class="col-md-2 text-md-end">
                        <form class="js-punch-geo-form" method="post" action="<?= url('/employees/' . ((int) ($employee['id'] ?? 0)) . '/punch-out') ?>">
                            <?= csrf_field() ?>
                            <?= geo_capture_fields('employee_details_punch_out') ?>
                            <button class="btn btn-danger w-100" type="submit">Punch Out</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <form class="js-punch-geo-form" method="post" action="<?= url('/employees/' . ((int) ($employee['id'] ?? 0)) . '/punch-in') ?>" id="employeeQuickPunchForm">
                    <?= csrf_field() ?>
                    <?= geo_capture_fields('employee_details_punch_in') ?>
                    <input type="hidden" id="employee_punch_job_lookup_url" value="<?= e(url('/time-tracking/lookup/jobs')) ?>" />
                    <div class="row g-3 align-items-end">
                        <div class="col-md-10 position-relative">
                            <label class="form-label" for="employee_punch_job_search">Job</label>
                            <input
                                class="form-control"
                                id="employee_punch_job_search"
                                name="job_search"
                                type="text"
                                placeholder="Search job by name, id, city... (optional)"
                                autocomplete="off"
                            />
                            <input type="hidden" id="employee_punch_job_id" name="job_id" value="" />
                            <div id="employee_punch_job_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1049;"></div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-success w-100" type="submit">Punch In</button>
                        </div>
                    </div>
                    <div class="form-text">Quick punch in: pick a job from suggestions, or leave blank for non-job time.</div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user me-1"></i>
            Employee
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Name</div>
                    <div class="fw-semibold"><?= e($fullName) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                            <?= e($isActive ? 'Active' : 'Inactive') ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold"><?= e(format_phone($employee['phone'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold"><?= e((string) (($employee['email'] ?? '') !== '' ? $employee['email'] : '—')) ?></div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Hire Date</div>
                    <div class="fw-semibold"><?= e(format_date($employee['hire_date'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Fire Date</div>
                    <div class="fw-semibold"><?= e(format_date($employee['fire_date'] ?? null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Wage Type</div>
                    <div class="fw-semibold"><?= e((string) (($employee['wage_type'] ?? '') !== '' ? ucfirst((string) $employee['wage_type']) : '—')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Pay Rate</div>
                    <div class="fw-semibold">
                        <?php if ($payRate !== null && $payRate !== ''): ?>
                            <?= e('$' . number_format((float) $payRate, 2)) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($employee['note'] ?? '') !== '' ? $employee['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clock me-1"></i>
            Hours & Income
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach (['wtd', 'mtd', 'ytd'] as $period): ?>
                    <?php
                        $item = $laborSummary[$period] ?? ['label' => strtoupper($period), 'range' => '—', 'data' => []];
                        $data = $item['data'] ?? [];
                        $minutes = (int) ($data['total_minutes'] ?? 0);
                        $hours = $minutes / 60;
                        $paid = (float) ($data['total_paid'] ?? 0);
                        $entries = (int) ($data['entry_count'] ?? 0);
                    ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-uppercase small text-muted"><?= e((string) ($item['label'] ?? strtoupper($period))) ?></div>
                                <div class="small text-muted mb-2"><?= e((string) ($item['range'] ?? '—')) ?></div>
                                <div class="fw-semibold">Hours: <?= e(number_format($hours, 2)) ?></div>
                                <div class="fw-semibold text-success">Income: <?= e('$' . number_format($paid, 2)) ?></div>
                                <div class="small text-muted"><?= e((string) $entries) ?> entries</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list me-1"></i>
            Activity Log
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold"><?= e(format_datetime($employee['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold"><?= e((string) ($employee['created_by_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated At</div>
                    <div class="fw-semibold"><?= e(format_datetime($employee['updated_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated By</div>
                    <div class="fw-semibold"><?= e((string) ($employee['updated_by_name'] ?? '—')) ?></div>
                </div>
                <?php if (!empty($employee['deleted_at'])): ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted At</div>
                        <div class="fw-semibold"><?= e(format_datetime($employee['deleted_at'] ?? null)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Deleted By</div>
                        <div class="fw-semibold"><?= e((string) ($employee['deleted_by_name'] ?? '—')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
