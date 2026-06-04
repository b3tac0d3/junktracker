<?php
$employees = is_array($employees ?? null) ? $employees : [];
$grandTotals = is_array($grandTotals ?? null) ? $grandTotals : [];
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$canManageEmployees = (bool) ($canManageEmployees ?? false);

$employeeCardUrl = static function (int $employeeId) use ($fromDate, $toDate): string {
    return url('/time-tracking/time-cards/' . (string) $employeeId) . '?' . http_build_query([
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Time Cards</h1>
        <p class="muted">Employee hours and pay for the selected date range</p>
    </div>
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/time-tracking')) ?>"><i class="fas fa-clock me-2"></i>Time Log</a>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e(url('/time-tracking/punch-board')) ?>"><i class="fas fa-user-clock me-2"></i><?= e($canManageEmployees ? 'Punch Board' : 'My Punch Clock') ?></a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Date Range</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/time-tracking/time-cards')) ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="time-cards-from">From</label>
                <input id="time-cards-from" class="form-control" type="date" name="from_date" value="<?= e($fromDate) ?>" />
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="time-cards-to">To</label>
                <input id="time-cards-to" class="form-control" type="date" name="to_date" value="<?= e($toDate) ?>" />
            </div>
            <div class="col-12 col-md-4 d-grid d-md-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/time-tracking/time-cards')) ?>">This Month</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4">
            <div class="record-field">
                <span class="record-label">Employees</span>
                <span class="record-value"><?= e((string) ((int) ($grandTotals['employees'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Entries</span>
                <span class="record-value"><?= e((string) ((int) ($grandTotals['entry_count'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Hours</span>
                <span class="record-value"><?= e(number_format((float) ($grandTotals['total_hours'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Payout</span>
                <span class="record-value">$<?= e(number_format((float) ($grandTotals['payout_total'] ?? 0), 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-id-card me-2"></i>Employees</strong>
        <span class="small muted"><?= e((string) count($employees)) ?> employee(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php if ($employees === []): ?>
            <div class="record-empty">No active employees found.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($employees as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $employeeId = (int) ($row['employee_id'] ?? 0);
                    if ($employeeId <= 0) {
                        continue;
                    }
                    $employeeName = trim((string) ($row['employee_name'] ?? '')) ?: ('Employee #' . (string) $employeeId);
                    $hourlyRate = (float) ($row['hourly_rate'] ?? 0);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e($employeeCardUrl($employeeId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($employeeName) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">Hourly Rate</span>
                                    <span class="record-value"><?= $hourlyRate > 0 ? e('$' . number_format($hourlyRate, 2)) : '—' ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Entries</span>
                                    <span class="record-value"><?= e((string) ((int) ($row['entry_count'] ?? 0))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Hours</span>
                                    <span class="record-value"><?= e(number_format((float) ($row['total_hours'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Payout</span>
                                    <span class="record-value">$<?= e(number_format((float) ($row['payout_total'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Open</span>
                                    <span class="record-value"><?= e((string) ((int) ($row['open_entries'] ?? 0))) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
