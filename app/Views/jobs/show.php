<?php
    $clientName = trim(($job['client_first_name'] ?? '') . ' ' . ($job['client_last_name'] ?? ''));
    if ($clientName === '') {
        $clientName = $job['client_business_name'] ?? '-';
    }

    $status = $job['job_status'] ?? '';
    $statusClass = match ($status) {
        'active' => 'bg-primary',
        'complete' => 'bg-success',
        'cancelled' => 'bg-secondary',
        default => 'bg-warning',
    };

    $depositTotal = (float) ($summary['deposit_total'] ?? 0);
    $expenseTotal = (float) ($summary['expense_total'] ?? 0);
    $disposalTotal = (float) ($summary['disposal_total'] ?? 0);
    $actionCount = (int) ($summary['action_count'] ?? 0);
    $timeTotalMinutes = (int) ($timeSummary['total_minutes'] ?? 0);
    $timeTotalPaid = (float) ($timeSummary['total_paid'] ?? 0);
    $timeEntryCount = (int) ($timeSummary['entry_count'] ?? 0);
    $profitability = is_array($profitability ?? null) ? $profitability : [];
    $profitRevenueTotal = (float) ($profitability['revenue_total'] ?? 0);
    $profitCostTotal = (float) ($profitability['cost_total'] ?? 0);
    $profitNetEstimate = (float) ($profitability['net_estimate'] ?? 0);
    $profitBillingCollected = (float) ($profitability['billing_collected'] ?? 0);
    $profitScrapTotal = (float) ($profitability['scrap_total'] ?? 0);
    $profitDumpTotal = (float) ($profitability['dump_total'] ?? 0);
    $crewEmployees = $crewEmployees ?? [];
    $openByEmployee = $openByEmployee ?? [];
    $openElsewhereByEmployee = $openElsewhereByEmployee ?? [];
    $tasks = $tasks ?? [];
    $documents = is_array($documents ?? null) ? $documents : [];
    $documentSummary = is_array($documentSummary ?? null) ? $documentSummary : [];
    $attachments = is_array($attachments ?? null) ? $attachments : [];
    $isDeleted = !empty($job['deleted_at']) || (isset($job['active']) && (int) $job['active'] === 0);
    $jobPath = '/jobs/' . (string) ($job['id'] ?? '');

    $formatTime = static function (?string $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $time = strtotime($value);
        return $time === false ? $value : date('g:i A', $time);
    };

    $formatMinutes = static function (int $minutes): string {
        if ($minutes <= 0) {
            return '0h 00m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        return $hours . 'h ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 'm';
    };
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Job Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/jobs') ?>">Jobs</a></li>
                <li class="breadcrumb-item active"><?= e($job['name'] ?? 'Job') ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$isDeleted): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteJobModal">
                    <i class="fas fa-trash"></i>
                </button>
            <?php else: ?>
                <span class="badge bg-secondary align-self-center">Deleted</span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">Back to Jobs</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="small text-uppercase">Status</div>
                    <div class="fs-5 fw-semibold text-uppercase"><?= e($status !== '' ? $status : 'pending') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="small text-uppercase">Scheduled</div>
                    <div class="fs-5 fw-semibold"><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="small text-uppercase">Total Quote</div>
                    <div class="fs-5 fw-semibold"><?= isset($job['total_quote']) ? e('$' . number_format((float) $job['total_quote'], 2)) : '-' ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="small text-uppercase">Total Billed</div>
                    <div class="fs-5 fw-semibold"><?= isset($job['total_billed']) ? e('$' . number_format((float) $job['total_billed'], 2)) : '-' ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card mb-4" id="crew-punch">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-briefcase me-1"></i>
                        Job Overview
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge <?= $statusClass ?> text-uppercase"><?= e($status !== '' ? $status : 'pending') ?></span>
                        <a class="btn btn-sm btn-warning" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/edit') ?>" title="Edit job" aria-label="Edit job">
                            <i class="fas fa-pen"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Job Name</div>
                            <div class="fw-semibold"><?= e($job['name'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Job Owner</div>
                            <div class="fw-semibold"><?= e((string) ($job['owner_display_name'] ?? $clientName)) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Address</div>
                            <div class="fw-semibold">
                                <?= e($job['address_1'] ?? '') ?>
                                <?php if (!empty($job['address_2'])): ?>
                                    <br /><?= e($job['address_2']) ?>
                                <?php endif; ?>
                                <?php if (!empty($job['city']) || !empty($job['state']) || !empty($job['zip'])): ?>
                                    <br /><?= e(trim(($job['city'] ?? '') . (isset($job['state']) && $job['state'] !== '' ? ', ' . $job['state'] : '') . ' ' . ($job['zip'] ?? ''))) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Contact</div>
                            <div class="fw-semibold">
                                <?= e((string) ($job['contact_display_name'] ?? $clientName)) ?>
                                <br />
                                <?= e(format_phone($job['phone'] ?? $job['client_phone'] ?? null)) ?>
                                <?php if (!empty($job['email']) || !empty($job['client_email'])): ?>
                                    <br /><?= e($job['email'] ?? $job['client_email']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Notes</div>
                            <?php if (!empty($job['note'])): ?>
                                <div class="fw-semibold"><?= nl2br(e((string) $job['note'])) ?></div>
                            <?php else: ?>
                                <div class="text-muted">No notes recorded for this job.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-check me-1"></i>
                    Status Dates
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Quote Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['quote_date'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Scheduled Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Start Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['start_date'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">End Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['end_date'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Billed Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['billed_date'] ?? null)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Paid Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['paid_date'] ?? null)) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                    <i class="fas fa-truck-loading me-1"></i>
                    Disposal Record
                    </div>
                    <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/disposals/new') ?>">
                        <i class="fas fa-plus me-1"></i>
                        Add
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Note</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($disposals)): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">No disposal events recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($disposals as $disposal): ?>
                                        <tr data-job-id="<?= e((string) ($job['id'] ?? '')) ?>" data-employee-id="<?= e((string) $employeeId) ?>">
                                            <td><?= e(format_date($disposal['event_date'] ?? null)) ?></td>
                                            <td><?= e((string) ($disposal['disposal_location_name'] ?? '-')) ?></td>
                                            <td class="text-uppercase"><?= e((string) ($disposal['type'] ?? '-')) ?></td>
                                            <td><?= e('$' . number_format((float) ($disposal['amount'] ?? 0), 2)) ?></td>
                                            <td><?= e((string) ($disposal['note'] ?? '-')) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/disposals/' . ($disposal['id'] ?? '') . '/edit') ?>" title="Edit disposal" aria-label="Edit disposal">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <form class="d-inline" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/disposals/' . ($disposal['id'] ?? '') . '/delete') ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete disposal" aria-label="Delete disposal">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                    <i class="fas fa-receipt me-1"></i>
                    Expense Record
                    </div>
                    <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/expenses/new') ?>">
                        <i class="fas fa-plus me-1"></i>
                        Add
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Location</th>
                                    <th>Amount</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">No expenses recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?= e(format_date($expense['expense_date'] ?? null)) ?></td>
                                            <td><?= e((string) ($expense['expense_category_name'] ?? $expense['category'] ?? '-')) ?></td>
                                            <td><?= e((string) ($expense['description'] ?? '-')) ?></td>
                                            <td><?= e((string) ($expense['disposal_location_name'] ?? '-')) ?></td>
                                            <td><?= e('$' . number_format((float) ($expense['amount'] ?? 0), 2)) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/expenses/' . ($expense['id'] ?? '') . '/edit') ?>" title="Edit expense" aria-label="Edit expense">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <form class="d-inline" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/expenses/' . ($expense['id'] ?? '') . '/delete') ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete expense" aria-label="Delete expense">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-clock me-1"></i>
                        Hours
                    </div>
                    <a class="btn btn-sm btn-primary" href="<?= url('/time-tracking/new?job_id=' . ($job['id'] ?? '') . '&return_to=' . urlencode('/jobs/' . ($job['id'] ?? ''))) ?>">
                        <i class="fas fa-plus me-1"></i>
                        Add
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Entries</div>
                            <div class="fw-semibold"><?= e((string) $timeEntryCount) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Total Hours</div>
                            <div class="fw-semibold"><?= e(number_format($timeTotalMinutes / 60, 2)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Labor Owed</div>
                            <div class="fw-semibold"><?= e('$' . number_format($timeTotalPaid, 2)) ?></div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Time</th>
                                    <th>Hours</th>
                                    <th>Rate</th>
                                    <th>Total</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($timeEntries)): ?>
                                    <tr>
                                        <td colspan="7" class="text-muted">No hours logged yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($timeEntries as $entry): ?>
                                        <?php
                                            $minutes = (int) ($entry['minutes_worked'] ?? 0);
                                            $timeEntryId = (int) ($entry['id'] ?? 0);
                                            $timeRange = (($entry['start_time'] ?? null) || ($entry['end_time'] ?? null))
                                                ? $formatTime($entry['start_time'] ?? null) . ' - ' . $formatTime($entry['end_time'] ?? null)
                                                : '—';
                                        ?>
                                        <tr>
                                            <td><?= e(format_date($entry['work_date'] ?? null)) ?></td>
                                            <td><?= e((string) ($entry['employee_name'] ?? '-')) ?></td>
                                            <td><?= e($timeRange) ?></td>
                                            <td>
                                                <?php if ($timeEntryId > 0): ?>
                                                    <a class="text-decoration-none" href="<?= url('/time-tracking/' . $timeEntryId . '?return_to=' . urlencode('/jobs/' . ($job['id'] ?? ''))) ?>">
                                                        <?= e(number_format($minutes / 60, 2)) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e(number_format($minutes / 60, 2)) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e('$' . number_format((float) ($entry['pay_rate'] ?? 0), 2)) ?></td>
                                            <td><?= e('$' . number_format((float) ($entry['paid_calc'] ?? 0), 2)) ?></td>
                                            <td><?= e((string) (($entry['note'] ?? '') !== '' ? $entry['note'] : '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-clock me-1"></i>
                    Crew Punch
                </div>
                <div class="card-body">
                    <input type="hidden" id="job_crew_lookup_url" value="<?= e(url('/jobs/' . ($job['id'] ?? '') . '/crew/lookup')) ?>" />
                    <form class="mb-3" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/crew/add') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-lg-9 position-relative">
                                <label class="form-label mb-1">Add Crew Member</label>
                                <input
                                    class="form-control"
                                    id="job_crew_search"
                                    name="crew_search"
                                    type="text"
                                    placeholder="Search employee by name, email, phone..."
                                    autocomplete="off"
                                />
                                <input type="hidden" id="job_crew_employee_id" name="employee_id" />
                                <div id="job_crew_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1050;"></div>
                            </div>
                            <div class="col-12 col-lg-3">
                                <button class="btn btn-primary w-100" type="submit">
                                    <i class="fas fa-user-plus me-1"></i>
                                    Add To Crew
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($crewEmployees)): ?>
                        <div class="text-muted">No crew members added yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Status</th>
                                        <th>Elapsed</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($crewEmployees as $employee): ?>
                                        <?php
                                            $employeeId = (int) ($employee['employee_id'] ?? 0);
                                            $employeeName = (string) ($employee['employee_name'] ?? ('Employee #' . $employeeId));
                                            $currentOpen = $employeeId > 0 ? ($openByEmployee[$employeeId] ?? null) : null;
                                            $otherOpen = $employeeId > 0 ? ($openElsewhereByEmployee[$employeeId] ?? null) : null;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= e($employeeName) ?></div>
                                                <?php if (!empty($employee['phone']) || !empty($employee['email'])): ?>
                                                    <small class="text-muted">
                                                        <?= e(!empty($employee['phone']) ? format_phone((string) $employee['phone']) : '') ?>
                                                        <?php if (!empty($employee['phone']) && !empty($employee['email'])): ?> • <?php endif; ?>
                                                        <?= e((string) (!empty($employee['email']) ? $employee['email'] : '')) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="js-crew-status">
                                                <?php if ($currentOpen): ?>
                                                    <span class="badge bg-success">On Clock</span>
                                                    <span class="small text-muted ms-2">
                                                        since <?= e($formatTime((string) ($currentOpen['start_time'] ?? ''))) ?>
                                                    </span>
                                                <?php elseif ($otherOpen): ?>
                                                    <span class="badge bg-warning text-dark"><?= !empty($otherOpen['job_id']) ? 'On Other Job' : 'On Non-Job Time' ?></span>
                                                    <?php if (!empty($otherOpen['job_id'])): ?>
                                                        <a class="small text-decoration-none ms-2" href="<?= url('/jobs/' . (int) $otherOpen['job_id']) ?>">
                                                            View Job #<?= e((string) $otherOpen['job_id']) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Punched Out</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="js-crew-elapsed">
                                                <?php if ($currentOpen): ?>
                                                    <?= e($formatMinutes((int) ($currentOpen['open_minutes'] ?? 0))) ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end js-crew-actions">
                                                <?php if (!$currentOpen && !$otherOpen): ?>
                                                    <form class="d-inline" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/crew/' . $employeeId . '/remove') ?>">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Remove from crew" aria-label="Remove from crew">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($currentOpen): ?>
                                                    <form class="d-inline js-job-punch-form" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/time/punch-out') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="time_entry_id" value="<?= e((string) ($currentOpen['id'] ?? '')) ?>" />
                                                        <button class="btn btn-sm btn-danger" type="submit">
                                                            <i class="fas fa-stop-circle me-1"></i>
                                                            Punch Out
                                                        </button>
                                                    </form>
                                                <?php elseif ($otherOpen): ?>
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled>
                                                        <i class="fas fa-lock me-1"></i>
                                                        Busy
                                                    </button>
                                                <?php else: ?>
                                                    <form class="d-inline js-job-punch-form" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/time/punch-in') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                                                        <button class="btn btn-sm btn-success" type="submit">
                                                            <i class="fas fa-play-circle me-1"></i>
                                                            Punch In
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-list-check me-1"></i>
                        Job Actions
                    </div>
                    <span class="badge bg-dark"><?= e((string) $actionCount) ?> entries</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Action</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($actions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-muted">No job actions recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($actions as $action): ?>
                                        <tr>
                                            <td><?= e(format_datetime($action['action_at'] ?? null)) ?></td>
                                            <td class="text-uppercase"><?= e(str_replace('_', ' ', (string) ($action['action_type'] ?? ''))) ?></td>
                                            <td><?= isset($action['amount']) && $action['amount'] !== null ? e('$' . number_format((float) $action['amount'], 2)) : '-' ?></td>
                                            <td>
                                                <?= !empty($action['ref_table']) ? e((string) $action['ref_table']) : '-' ?>
                                                <?php if (!empty($action['ref_id'])): ?>
                                                    #<?= e((string) $action['ref_id']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e((string) ($action['note'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-line me-1"></i>
                    Profit Snapshot
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small">Revenue Target</div>
                            <div class="fw-semibold"><?= e('$' . number_format($profitRevenueTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Costs</div>
                            <div class="fw-semibold text-danger"><?= e('$' . number_format($profitCostTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Estimated Net</div>
                            <div class="fw-semibold <?= $profitNetEstimate < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= e('$' . number_format($profitNetEstimate, 2)) ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Collected to Date</div>
                            <div class="fw-semibold"><?= e('$' . number_format($profitBillingCollected + $profitScrapTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Scrap Sales</div>
                            <div class="fw-semibold text-success"><?= e('$' . number_format($profitScrapTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Dump Costs</div>
                            <div class="fw-semibold text-danger"><?= e('$' . number_format($profitDumpTotal, 2)) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-file-invoice-dollar me-1"></i>
                        Billing Snapshot
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (empty($job['paid'])): ?>
                            <form method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/mark-paid') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-success" type="submit">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Mark as Paid
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-success" type="button" disabled>
                                <i class="fas fa-check-circle me-1"></i>
                                Paid in Full
                            </button>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/billing/new') ?>">
                            <i class="fas fa-plus me-1"></i>
                            Add
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small">Deposit</div>
                            <div class="fw-semibold"><?= e('$' . number_format($depositTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Quote</div>
                            <div class="fw-semibold"><?= isset($job['total_quote']) ? e('$' . number_format((float) $job['total_quote'], 2)) : '-' ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Billed</div>
                            <div class="fw-semibold"><?= isset($job['total_billed']) ? e('$' . number_format((float) $job['total_billed'], 2)) : '-' ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Paid</div>
                            <div class="fw-semibold"><?= !empty($job['paid']) ? 'Yes' : 'No' ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Paid Date</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['paid_date'] ?? null)) ?></div>
                        </div>
                    </div>
                    <hr />
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($billingEntries)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No billing entries yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($billingEntries as $billing): ?>
                                        <tr>
                                            <td><?= e(format_datetime($billing['action_at'] ?? null)) ?></td>
                                            <td class="text-uppercase"><?= e(str_replace('_', ' ', (string) ($billing['action_type'] ?? ''))) ?></td>
                                            <td><?= isset($billing['amount']) && $billing['amount'] !== null ? e('$' . number_format((float) $billing['amount'], 2)) : '-' ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/billing/' . ($billing['id'] ?? '') . '/edit') ?>" title="Edit billing entry" aria-label="Edit billing entry">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <form class="d-inline" method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/billing/' . ($billing['id'] ?? '') . '/delete') ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete billing entry" aria-label="Delete billing entry">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="estimate-invoice">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-file-signature me-1"></i>
                        Estimate / Invoice Workflow
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/documents/new?type=estimate') ?>">
                            <i class="fas fa-plus me-1"></i>
                            Add Estimate
                        </a>
                        <a class="btn btn-sm btn-primary" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/documents/new?type=invoice') ?>">
                            <i class="fas fa-plus me-1"></i>
                            Add Invoice
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="text-muted small">Documents</div>
                            <div class="fw-semibold"><?= e((string) ((int) ($documentSummary['total_count'] ?? 0))) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Estimates</div>
                            <div class="fw-semibold"><?= e((string) ((int) ($documentSummary['estimate_count'] ?? 0))) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Invoices</div>
                            <div class="fw-semibold"><?= e((string) ((int) ($documentSummary['invoice_count'] ?? 0))) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Paid</div>
                            <div class="fw-semibold text-success"><?= e((string) ((int) ($documentSummary['paid_count'] ?? 0))) ?></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Issued</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="5" class="text-muted">No estimates/invoices yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $document): ?>
                                        <?php
                                            $docId = (int) ($document['id'] ?? 0);
                                            $docType = \App\Models\JobDocument::typeLabel((string) ($document['document_type'] ?? 'document'));
                                            $docStatus = \App\Models\JobDocument::statusLabel((string) ($document['status'] ?? 'draft'));
                                        ?>
                                        <tr>
                                            <td><?= e($docType) ?></td>
                                            <td>
                                                <a class="text-decoration-none" href="<?= url('/jobs/' . ($job['id'] ?? '') . '/documents/' . $docId) ?>">
                                                    <?= e((string) (($document['title'] ?? '') !== '' ? $document['title'] : ('Document #' . $docId))) ?>
                                                </a>
                                            </td>
                                            <td><?= e($docStatus) ?></td>
                                            <td><?= isset($document['amount']) && $document['amount'] !== null ? e('$' . number_format((float) $document['amount'], 2)) : '—' ?></td>
                                            <td><?= e(format_datetime($document['issued_at'] ?? null)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Operations Snapshot
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small">Expenses</div>
                            <div class="fw-semibold"><?= e('$' . number_format($expenseTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Disposals</div>
                            <div class="fw-semibold"><?= e('$' . number_format($disposalTotal, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Labor</div>
                            <div class="fw-semibold"><?= e('$' . number_format($timeTotalPaid, 2)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Action Entries</div>
                            <div class="fw-semibold"><?= e((string) $actionCount) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="fas fa-list-check me-1"></i>
                        Tasks
                    </div>
                    <a class="btn btn-sm btn-primary" href="<?= url('/tasks/new?link_type=job&link_id=' . ($job['id'] ?? '')) ?>">
                        <i class="fas fa-plus me-1"></i>
                        Add Task
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Done</th>
                                    <th>Task</th>
                                    <th>Due</th>
                                    <th>Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No tasks linked to this job.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tasks as $task): ?>
                                        <?php $isCompleted = (string) ($task['status'] ?? '') === 'closed'; ?>
                                        <tr>
                                            <td>
                                                <form method="post" action="<?= url('/tasks/' . (string) ($task['id'] ?? '') . '/toggle-complete') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="return_to" value="<?= e($jobPath) ?>" />
                                                    <input type="hidden" name="is_completed" value="0" />
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="is_completed"
                                                        value="1"
                                                        <?= $isCompleted ? 'checked' : '' ?>
                                                        onchange="this.form.submit()"
                                                    />
                                                </form>
                                            </td>
                                            <td>
                                                <a class="text-decoration-none <?= $isCompleted ? 'text-muted text-decoration-line-through' : '' ?>" href="<?= url('/tasks/' . (string) ($task['id'] ?? '')) ?>">
                                                    <?= e((string) ($task['title'] ?? 'Task')) ?>
                                                </a>
                                                <div class="small text-muted text-capitalize"><?= e(ucwords(str_replace('_', ' ', (string) ($task['status'] ?? 'open')))) ?></div>
                                            </td>
                                            <td><?= e(format_datetime($task['due_at'] ?? null)) ?></td>
                                            <td><?= e((string) (($task['assigned_user_name'] ?? '') !== '' ? $task['assigned_user_name'] : 'Unassigned')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php
                $attachmentPanelTitle = 'Attachments';
                $attachmentLinkType = 'job';
                $attachmentLinkId = (int) ($job['id'] ?? 0);
                $attachmentReturnTo = $jobPath;
                require __DIR__ . '/../partials/attachments_panel.php';
            ?>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-1"></i>
                    Activity Metadata
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small">Created At</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['created_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Created By</div>
                            <div class="fw-semibold"><?= e((string) ($job['created_by'] ?? '-')) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Updated At</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['updated_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Deleted At</div>
                            <div class="fw-semibold"><?= e(format_datetime($job['deleted_at'] ?? null)) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Deleted By</div>
                            <div class="fw-semibold"><?= e((string) ($job['deleted_by'] ?? '-')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isDeleted): ?>
        <div class="modal fade" id="deleteJobModal" tabindex="-1" aria-labelledby="deleteJobModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteJobModalLabel">Delete Job</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will mark the job as deleted. You can still view it from the deleted filter. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/jobs/' . ($job['id'] ?? '') . '/delete') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit">Delete Job</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
