<?php
$entry = is_array($entry ?? null) ? $entry : [];
$durationMinutes = (int) ($entry['duration_minutes'] ?? 0);
$durationHours = $durationMinutes > 0 ? number_format($durationMinutes / 60, 2) : '—';
$isOpenEntry = trim((string) ($entry['clock_out_at'] ?? '')) === '';
$employeeId = (int) ($entry['employee_id'] ?? 0);
$hourlyRate = (float) ($entry['hourly_rate'] ?? 0);
$effectiveMinutes = $durationMinutes;
if ($effectiveMinutes <= 0 && $isOpenEntry) {
    $clockInTs = strtotime((string) ($entry['clock_in_at'] ?? ''));
    if ($clockInTs !== false) {
        $effectiveMinutes = max(0, (int) floor((time() - $clockInTs) / 60));
    }
}
$totalPaid = $hourlyRate > 0 ? (($effectiveMinutes / 60) * $hourlyRate) : 0.0;
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Time Entry</h1>
        <p class="muted">Entry #<?= e((string) ((int) ($entry['id'] ?? 0))) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isOpenEntry && $employeeId > 0): ?>
            <form method="post" action="<?= e(url('/time-tracking/punch-out')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="employee_id" value="<?= e((string) $employeeId) ?>" />
                <button class="btn btn-outline-danger" type="submit"><i class="fas fa-stop me-2"></i>Punch Out</button>
            </form>
        <?php endif; ?>
        <?php if (workspace_role() !== 'punch_only'): ?>
            <a class="btn btn-primary" href="<?= e(url('/time-tracking/' . (string) ((int) ($entry['id'] ?? 0)) . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Entry</a>
            <form method="post" action="<?= e(url('/time-tracking/' . (string) ((int) ($entry['id'] ?? 0)) . '/delete')) ?>" onsubmit="return confirm('Delete this time entry?');">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/time-tracking')) ?>">Back to Time Tracking</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-clock me-2"></i>Entry Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4">
            <div class="record-field">
                <span class="record-label">Employee</span>
                <span class="record-value"><?= e(trim((string) ($entry['employee_name'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Job</span>
                <span class="record-value"><?= e(trim((string) ($entry['job_title'] ?? '')) ?: 'Non-Job Time') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Clock In</span>
                <span class="record-value"><?= e(format_datetime((string) ($entry['clock_in_at'] ?? null))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Clock Out</span>
                <span class="record-value"><?= e(format_datetime((string) ($entry['clock_out_at'] ?? null))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Duration (Hours)</span>
                <span class="record-value"><?= e($durationHours) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Duration (Minutes)</span>
                <span class="record-value"><?= e($durationMinutes > 0 ? (string) $durationMinutes : '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Paid</span>
                <span class="record-value">$<?= e(number_format($totalPaid, 2)) ?></span>
            </div>
            <div class="record-field record-field-full">
                <span class="record-label">Note</span>
                <span class="record-value"><?= e(trim((string) ($entry['notes'] ?? '')) ?: '—') ?></span>
            </div>
        </div>
    </div>
</section>
