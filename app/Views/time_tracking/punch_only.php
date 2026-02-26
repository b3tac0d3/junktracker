<?php
    $employee = $employee ?? null;
    $openEntry = $openEntry ?? null;
    $historyEntries = $historyEntries ?? [];
    $historySummary = $historySummary ?? [];
    $historyStartDate = (string) ($historyStartDate ?? '');
    $historyEndDate = (string) ($historyEndDate ?? '');
    $openBasePath = (string) ($openBasePath ?? '/punch-clock');

    $employeeName = trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')));
    if ($employeeName === '') {
        $employeeName = trim((string) ($employee['name'] ?? ''));
    }
    if ($employeeName === '') {
        $employeeName = 'My Profile';
    }

    $formatTime = static function (?string $value): string {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        $ts = strtotime($value);
        return $ts === false ? (string) $value : date('g:i A', $ts);
    };

    $formatMinutes = static function (?int $minutes): string {
        $total = (int) ($minutes ?? 0);
        if ($total <= 0) {
            return '—';
        }

        $hours = intdiv($total, 60);
        $remainder = $total % 60;
        return $hours . 'h ' . str_pad((string) $remainder, 2, '0', STR_PAD_LEFT) . 'm';
    };

    $summaryMinutes = (int) ($historySummary['total_minutes'] ?? 0);
    $summaryHours = number_format($summaryMinutes / 60, 2);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Punch Clock</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Punch Clock</li>
            </ol>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><i class="fas fa-user-clock me-1"></i> My Clock</div>
            <?php if ($openEntry): ?>
                <span class="badge bg-success">Punched In</span>
            <?php else: ?>
                <span class="badge bg-secondary">Punched Out</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="fw-semibold fs-5"><?= e($employeeName) ?></div>
                <?php if ($openEntry): ?>
                    <div class="text-muted">
                        Started <?= e(format_date((string) ($openEntry['work_date'] ?? null))) ?> at <?= e($formatTime((string) ($openEntry['start_time'] ?? null))) ?>
                        <?php if ((int) ($openEntry['job_id'] ?? 0) > 0): ?>
                            · <?= e((string) ($openEntry['job_name'] ?? 'Job')) ?>
                        <?php else: ?>
                            · Non-Job Time
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">You are currently punched out.</div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 mobile-two-col-buttons">
                <?php if ($openEntry): ?>
                    <form class="js-punch-geo-form" method="post" action="<?= url('/time-tracking/' . ((int) ($openEntry['id'] ?? 0)) . '/punch-out') ?>">
                        <?= csrf_field() ?>
                        <?= geo_capture_fields('punch_only_punch_out') ?>
                        <input type="hidden" name="return_to" value="<?= e($openBasePath) ?>" />
                        <button class="btn btn-danger" type="submit">
                            <i class="fas fa-stop me-1"></i>
                            Punch Out
                        </button>
                    </form>
                <?php else: ?>
                    <button
                        class="btn btn-success js-open-clock-punch-in"
                        type="button"
                        data-employee-id="<?= e((string) ((int) ($employee['id'] ?? 0))) ?>"
                        data-employee-name="<?= e($employeeName) ?>"
                    >
                        <i class="fas fa-play me-1"></i>
                        Punch In
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><i class="fas fa-clock-rotate-left me-1"></i> Last 30 Days</div>
            <div class="small text-muted">
                <?= e(format_date($historyStartDate)) ?> - <?= e(format_date($historyEndDate)) ?>
                · <?= e($summaryHours) ?>h
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($historyEntries)): ?>
                <div class="text-muted">No time entries in this period.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Job</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyEntries as $entry): ?>
                                <?php $jobId = (int) ($entry['job_id'] ?? 0); ?>
                                <tr>
                                    <td><?= e(format_date((string) ($entry['work_date'] ?? null))) ?></td>
                                    <td>
                                        <?php if ($jobId > 0): ?>
                                            <?= e((string) ($entry['job_name'] ?? ('Job #' . $jobId))) ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non-Job Time</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($formatTime((string) ($entry['start_time'] ?? null))) ?></td>
                                    <td><?= e($formatTime((string) ($entry['end_time'] ?? null))) ?></td>
                                    <td><?= e($formatMinutes(isset($entry['minutes_worked']) ? (int) $entry['minutes_worked'] : null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$openEntry): ?>
        <div class="modal fade" id="openClockPunchInModal" tabindex="-1" aria-labelledby="openClockPunchInModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="js-punch-geo-form" method="post" action="<?= url('/time-tracking/open/punch-in') ?>" id="openClockPunchInForm">
                        <?= csrf_field() ?>
                        <?= geo_capture_fields('punch_only_punch_in') ?>
                        <input type="hidden" name="employee_id" id="open_clock_punch_employee_id" value="<?= e((string) ((int) ($employee['id'] ?? 0))) ?>" />
                        <input type="hidden" name="return_to" value="<?= e($openBasePath) ?>" />
                        <input type="hidden" name="job_id" id="open_clock_punch_job_id" value="" />
                        <input type="hidden" id="open_clock_job_lookup_url" value="<?= e(url('/time-tracking/lookup/jobs')) ?>" />

                        <div class="modal-header">
                            <h5 class="modal-title" id="openClockPunchInModalLabel">Punch In</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Search for a job (optional). If left blank, this punch-in is saved as Non-Job Time.</p>
                            <div class="position-relative">
                                <label class="form-label" for="open_clock_punch_job_search">Job (optional)</label>
                                <input
                                    class="form-control"
                                    id="open_clock_punch_job_search"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Search job by name, id, city..."
                                />
                                <div id="open_clock_punch_job_suggestions" class="list-group position-absolute w-100 shadow-sm d-none"></div>
                                <div class="form-text">Pick from suggestions to attach this punch to a job.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-success" type="submit">
                                <i class="fas fa-play me-1"></i>
                                Punch In
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
