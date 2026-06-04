<?php
$employee = is_array($employee ?? null) ? $employee : [];
$entries = is_array($entries ?? null) ? $entries : [];
$totals = is_array($totals ?? null) ? $totals : [];
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$employeeId = (int) ($employee['id'] ?? 0);
$employeeName = trim((string) ($employeeName ?? '')) ?: ('Employee #' . (string) $employeeId);
$hourlyRate = (float) ($hourlyRate ?? ($employee['hourly_rate'] ?? 0));

$timeCardsUrl = url('/time-tracking/time-cards') . '?' . http_build_query([
    'from_date' => $fromDate,
    'to_date' => $toDate,
]);

$formatDuration = static function (int $minutes): string {
    if ($minutes <= 0) {
        return '—';
    }
    return number_format($minutes / 60, 2) . ' h';
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($employeeName) ?></h1>
        <p class="muted">Time card · <?= e(date('m/d/Y', strtotime($fromDate))) ?> – <?= e(date('m/d/Y', strtotime($toDate))) ?></p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e($timeCardsUrl) ?>"><i class="fas fa-arrow-left me-2"></i>All Time Cards</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Date Range</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/time-tracking/time-cards/' . (string) $employeeId)) ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="time-card-from">From</label>
                <input id="time-card-from" class="form-control" type="date" name="from_date" value="<?= e($fromDate) ?>" />
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="time-card-to">To</label>
                <input id="time-card-to" class="form-control" type="date" name="to_date" value="<?= e($toDate) ?>" />
            </div>
            <div class="col-12 col-md-4 d-grid d-md-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/time-tracking/time-cards/' . (string) $employeeId)) ?>">This Month</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5">
            <div class="record-field">
                <span class="record-label">Hourly Rate</span>
                <span class="record-value"><?= $hourlyRate > 0 ? e('$' . number_format($hourlyRate, 2)) : '—' ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Entries</span>
                <span class="record-value"><?= e((string) ((int) ($totals['entry_count'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Open Entries</span>
                <span class="record-value"><?= e((string) ((int) ($totals['open_entries'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Hours</span>
                <span class="record-value"><?= e(number_format((float) ($totals['total_hours'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Payout</span>
                <span class="record-value">$<?= e(number_format((float) ($totals['payout_total'] ?? 0), 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-list me-2"></i>Time Sheet</strong>
        <span class="small muted"><?= e((string) count($entries)) ?> entry(ies)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php if ($entries === []): ?>
            <div class="record-empty">No time entries in this date range.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    if (!is_array($entry)) {
                        continue;
                    }
                    $entryId = (int) ($entry['id'] ?? 0);
                    if ($entryId <= 0) {
                        continue;
                    }
                    $durationMinutes = (int) ($entry['duration_minutes'] ?? 0);
                    $isOpen = trim((string) ($entry['clock_out_at'] ?? '')) === '';
                    $entryRate = (float) ($entry['hourly_rate'] ?? $hourlyRate);
                    $payout = (float) ($entry['payout_total'] ?? 0);
                    if ($payout <= 0 && $entryRate > 0 && $durationMinutes > 0) {
                        $payout = round(($durationMinutes / 60) * $entryRate, 2);
                    }
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/time-tracking/' . (string) $entryId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple">
                                    <?= e(trim((string) ($entry['job_title'] ?? '')) ?: 'Non-Job Time') ?>
                                    <?php if ($isOpen): ?>
                                        <span class="badge text-bg-warning ms-1">Open</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">Clock In</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($entry['clock_in_at'] ?? null))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Clock Out</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($entry['clock_out_at'] ?? null))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Duration</span>
                                    <span class="record-value"><?= e($formatDuration($durationMinutes)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Rate</span>
                                    <span class="record-value"><?= $entryRate > 0 ? e('$' . number_format($entryRate, 2)) : '—' ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Payout</span>
                                    <span class="record-value">$<?= e(number_format($payout, 2)) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
