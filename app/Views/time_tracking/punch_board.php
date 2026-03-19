<?php
$punchEmployees = is_array($punchEmployees ?? null) ? $punchEmployees : [];
$canManageEmployees = (bool) ($canManageEmployees ?? false);
$recentEntries = is_array($recentEntries ?? null) ? $recentEntries : [];
$isPunchOnly = (bool) ($isPunchOnly ?? false);
$employeeLinkMissing = (bool) ($employeeLinkMissing ?? false);
$punchBoardJobs = is_array($punchBoardJobs ?? null) ? $punchBoardJobs : [];

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

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($canManageEmployees ? 'Punch Board' : 'My Punch Clock') ?></h1>
        <p class="muted"><?= e($isPunchOnly ? 'Punch in, punch out, and review your recent time.' : 'Punch employees in or out with optional job selection.') ?></p>
    </div>
    <?php if (!$isPunchOnly): ?>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/time-tracking')) ?>"><i class="fas fa-arrow-left me-2"></i>Back to Time Tracking</a>
    </div>
    <?php endif; ?>
</div>

<section class="card index-card index-card-overflow-visible mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-clock me-2"></i><?= e($canManageEmployees ? 'Employee Punch Board' : 'My Punch Clock') ?></strong>
    </div>
    <div class="card-body">
        <?php if ($employeeLinkMissing): ?>
            <div class="alert alert-warning mb-0">
                No employee profile is linked to this user yet. An admin needs to link your user to an employee before punch in/out will work.
            </div>
        <?php elseif ($punchEmployees === []): ?>
            <div class="record-empty mb-0">No active employees available for punch tracking.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($punchEmployees as $employee): ?>
                    <?php
                    $employeeId = (int) ($employee['id'] ?? 0);
                    $openEntryId = (int) ($employee['open_entry_id'] ?? 0);
                    $isOpen = $openEntryId > 0;
                    $displayName = $employeeDisplayName($employee);
                    $employeeName = trim((string) ($employee['employee_name'] ?? ''));
                    $linkedUserName = trim((string) ($employee['linked_user_name'] ?? ''));
                    $linkedUserEmail = trim((string) ($employee['linked_user_email'] ?? ''));
                    $openClockInAt = trim((string) ($employee['open_clock_in_at'] ?? ''));
                    $openJobTitle = trim((string) ($employee['open_job_title'] ?? ''));
                    ?>
                    <article class="record-row-simple">
                        <div class="record-row-main mb-2">
                            <h3 class="record-title-simple mb-0"><?= e($displayName) ?></h3>
                            <?php if ($linkedUserName !== '' && strcasecmp($linkedUserName, $employeeName) !== 0): ?>
                                <div class="record-subline small muted mt-1">
                                    <span>Employee: <?= e($employeeName !== '' ? $employeeName : ('#' . (string) $employeeId)) ?></span>
                                    <?php if ($linkedUserEmail !== ''): ?><span>· <?= e($linkedUserEmail) ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isOpen): ?>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="record-row-fields record-row-fields-3 flex-grow-1">
                                    <div class="record-field">
                                        <span class="record-label">Status</span>
                                        <span class="record-value"><span class="badge text-bg-success">Punched In</span></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Job</span>
                                        <span class="record-value"><?= e($openJobTitle !== '' ? $openJobTitle : 'Non-Job Time') ?></span>
                                    </div>
                                    <div class="record-field">
                                        <span class="record-label">Clock In</span>
                                        <span class="record-value"><?= e(format_datetime($openClockInAt !== '' ? $openClockInAt : null)) ?></span>
                                    </div>
                                </div>
                                <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>" class="d-flex">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                                    <button class="btn btn-outline-danger" type="submit"><i class="fas fa-stop me-2"></i>Punch Out</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/time-tracking/punch-in')) ?>" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />

                                <div class="col-12 col-xl-6">
                                    <label class="form-label fw-semibold">Job</label>
                                    <select class="form-select" name="job_selection">
                                        <option value="">Select active job or non-job type</option>
                                        <option value="shop_time">Shop Time</option>
                                        <option value="general_labor">General Labor</option>
                                        <?php foreach ($punchBoardJobs as $jobOption): ?>
                                            <?php
                                            $jobOptionId = (int) ($jobOption['id'] ?? 0);
                                            if ($jobOptionId <= 0) {
                                                continue;
                                            }
                                            $jobOptionTitle = trim((string) ($jobOption['title'] ?? ''));
                                            $jobOptionCity = trim((string) ($jobOption['city'] ?? ''));
                                            $jobOptionLabel = $jobOptionTitle !== '' ? $jobOptionTitle : ('Job #' . (string) $jobOptionId);
                                            if ($jobOptionCity !== '') {
                                                $jobOptionLabel .= ' - ' . $jobOptionCity;
                                            }
                                            ?>
                                            <option value="<?= e((string) $jobOptionId) ?>"><?= e($jobOptionLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-xl-4">
                                    <label class="form-label fw-semibold" for="time-punch-note-<?= e((string) $employeeId) ?>">Note</label>
                                    <input id="time-punch-note-<?= e((string) $employeeId) ?>" type="text" class="form-control" name="notes" placeholder="Optional" />
                                </div>

                                <div class="col-12 col-xl-2 d-grid">
                                    <button class="btn btn-success" type="submit"><i class="fas fa-play me-2"></i>Punch In</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($isPunchOnly): ?>
    <section class="card index-card">
        <div class="card-header index-card-header d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-clock-rotate-left me-2"></i>Recent Time</strong>
            <span class="muted small">Last 25 entries</span>
        </div>
        <div class="card-body">
            <?php if ($recentEntries === []): ?>
                <div class="record-empty mb-0">No time entries recorded yet.</div>
            <?php else: ?>
                <div class="record-list-simple">
                    <?php foreach ($recentEntries as $entry): ?>
                        <?php
                        $clockInAt = trim((string) ($entry['clock_in_at'] ?? ''));
                        $clockOutAt = trim((string) ($entry['clock_out_at'] ?? ''));
                        $durationMinutes = (int) ($entry['duration_minutes'] ?? 0);
                        $durationHours = $durationMinutes > 0 ? number_format($durationMinutes / 60, 2) . 'h' : 'Open';
                        ?>
                        <article class="record-row-simple">
                            <div class="record-row-main mb-2">
                                <h3 class="record-title-simple mb-0"><?= e(trim((string) ($entry['job_title'] ?? '')) !== '' ? (string) $entry['job_title'] : 'Non-Job Time') ?></h3>
                                <div class="record-subline small muted mt-1">
                                    <span>In <?= e(format_datetime($clockInAt !== '' ? $clockInAt : null)) ?></span>
                                    <span>· Out <?= e(format_datetime($clockOutAt !== '' ? $clockOutAt : null)) ?></span>
                                </div>
                            </div>
                            <div class="record-row-fields record-row-fields-2">
                                <div class="record-field">
                                    <span class="record-label">Hours</span>
                                    <span class="record-value"><?= e($durationHours) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($clockOutAt === '' ? 'Open' : 'Closed') ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
