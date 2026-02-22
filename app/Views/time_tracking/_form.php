<?php
    $entry = $entry ?? [];
    $employees = $employees ?? [];
    $jobs = $jobs ?? [];
    $returnTo = (string) ($returnTo ?? '/time-tracking');
    $formAction = (string) ($formAction ?? '/time-tracking/new');
    $cancelUrl = (string) ($cancelUrl ?? $returnTo);

    $normalizeTime = static function (mixed $value): string {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            return substr($raw, 0, 5);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $raw) === 1) {
            return $raw;
        }

        $time = strtotime($raw);
        return $time === false ? '' : date('H:i', $time);
    };

    $employeeId = (string) old('employee_id', (string) ($entry['employee_id'] ?? ''));
    $jobId = (string) old('job_id', (string) ($entry['job_id'] ?? ''));

    $employeeNameById = [];
    foreach ($employees as $employee) {
        $id = (int) ($employee['id'] ?? 0);
        if ($id > 0) {
            $employeeNameById[$id] = (string) ($employee['name'] ?? ('Employee #' . $id));
        }
    }

    $jobNameById = [];
    foreach ($jobs as $job) {
        $id = (int) ($job['id'] ?? 0);
        if ($id > 0) {
            $label = '#' . $id . ' - ' . (string) ($job['name'] ?? ('Job #' . $id));
            $jobNameById[$id] = $label;
        }
    }

    $defaultEmployeeSearch = '';
    if ($employeeId !== '' && isset($employeeNameById[(int) $employeeId])) {
        $defaultEmployeeSearch = $employeeNameById[(int) $employeeId];
    }

    $defaultJobSearch = '';
    if ($jobId !== '' && isset($jobNameById[(int) $jobId])) {
        $defaultJobSearch = $jobNameById[(int) $jobId];
    }

    $employeeSearch = (string) old('employee_search', $defaultEmployeeSearch);
    $jobSearch = (string) old('job_search', $defaultJobSearch);
    $entryHasNonJob = !empty($entry['id']) && (
        ($entry['job_id'] ?? null) === null
        || (string) ($entry['job_id'] ?? '') === '0'
    );
    $nonJobTime = old('non_job_time', $entryHasNonJob ? '1' : '0') === '1';

    $workDate = (string) old('work_date', (string) ($entry['work_date'] ?? ''));
    $startTime = $normalizeTime(old('start_time', $entry['start_time'] ?? ''));
    $endTime = $normalizeTime(old('end_time', $entry['end_time'] ?? ''));
    $minutesWorked = (string) old('minutes_worked', (string) ($entry['minutes_worked'] ?? ''));
    $payRate = (string) old('pay_rate', (string) ($entry['pay_rate'] ?? ''));
    $totalPaid = (string) old('total_paid', (string) ($entry['total_paid'] ?? ''));
    $note = (string) old('note', (string) ($entry['note'] ?? ''));
?>
<form class="js-punch-geo-form" method="post" action="<?= url($formAction) ?>" id="timeEntryForm" data-punch-geo-submit-value="punch_in_now">
    <?= csrf_field() ?>
    <?= geo_capture_fields('time_entry_form') ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />

    <input type="hidden" id="time_employee_lookup_url" value="<?= e(url('/time-tracking/lookup/employees')) ?>" />
    <input type="hidden" id="time_job_lookup_url" value="<?= e(url('/time-tracking/lookup/jobs')) ?>" />

    <div class="row g-3">
        <div class="col-md-6 position-relative">
            <label class="form-label" for="employee_search">Employee</label>
            <input
                class="form-control"
                id="employee_search"
                name="employee_search"
                type="text"
                placeholder="Search employee by name, email, phone..."
                autocomplete="off"
                value="<?= e($employeeSearch) ?>"
                required
            />
            <input type="hidden" id="employee_id" name="employee_id" value="<?= e($employeeId) ?>" data-pay-rate="" />
            <div id="time_employee_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1050;"></div>
        </div>

        <div class="col-md-6 position-relative">
            <label class="form-label" for="job_search">Job</label>
            <input
                class="form-control"
                id="job_search"
                name="job_search"
                type="text"
                placeholder="Search job by name, id, city..."
                autocomplete="off"
                value="<?= e($jobSearch) ?>"
            />
            <input type="hidden" id="job_id" name="job_id" value="<?= e($jobId) ?>" />
            <div id="time_job_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1049;"></div>
        </div>

        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="non_job_time" name="non_job_time" value="1" <?= $nonJobTime ? 'checked' : '' ?> />
                <label class="form-check-label" for="non_job_time">Non-Job Time</label>
            </div>
            <div class="small text-muted">Use this for shop/admin/other time that is not tied to a job.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="work_date">Work Date</label>
            <input class="form-control" id="work_date" name="work_date" type="date" value="<?= e($workDate) ?>" required />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="start_time">Start Time</label>
            <input class="form-control" id="start_time" name="start_time" type="time" value="<?= e($startTime) ?>" />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="end_time">End Time</label>
            <input class="form-control" id="end_time" name="end_time" type="time" value="<?= e($endTime) ?>" />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="minutes_worked">Minutes Worked</label>
            <input class="form-control" id="minutes_worked" name="minutes_worked" type="number" min="1" step="1" value="<?= e($minutesWorked) ?>" />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="pay_rate">Pay Rate</label>
            <input class="form-control" id="pay_rate" name="pay_rate" type="number" min="0" step="0.01" value="<?= e($payRate) ?>" />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="total_paid">Total Paid</label>
            <input class="form-control" id="total_paid" name="total_paid" type="number" min="0" step="0.01" value="<?= e($totalPaid) ?>" />
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Note</label>
            <textarea class="form-control" id="note" name="note" rows="4" placeholder="Optional note about the work done."><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit" name="entry_mode" value="save"><?= !empty($entry['id']) ? 'Update Time Entry' : 'Save Time Entry' ?></button>
        <?php if (empty($entry['id'])): ?>
            <button class="btn btn-success" type="submit" name="entry_mode" value="punch_in_now">Punch In Now</button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= url($cancelUrl) ?>">Cancel</a>
    </div>
</form>
