<?php
    $entry = $entry ?? [];
    $isActive = $isActive ?? (empty($entry['deleted_at']) && (int) ($entry['active'] ?? 1) === 1);
    $returnTo = (string) ($returnTo ?? '/time-tracking');

    $minutes = (int) ($entry['minutes_worked'] ?? 0);
    $hours = $minutes / 60;

    $formatTime = static function (?string $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $time = strtotime($value);
        return $time === false ? $value : date('g:i A', $time);
    };

    $timeRange = (($entry['start_time'] ?? null) || ($entry['end_time'] ?? null))
        ? $formatTime($entry['start_time'] ?? null) . ' - ' . $formatTime($entry['end_time'] ?? null)
        : '—';

    $jobId = (int) ($entry['job_id'] ?? 0);
    $employeeId = (int) ($entry['employee_id'] ?? 0);
    $isOpen = $isActive && !empty($entry['start_time']) && empty($entry['end_time']);
    $formatGeo = static function (mixed $latValue, mixed $lngValue, mixed $accuracyValue): array {
        if ($latValue === null || $lngValue === null || $latValue === '' || $lngValue === '') {
            return [
                'coords' => null,
                'maps_url' => null,
                'accuracy' => null,
            ];
        }

        $lat = is_numeric((string) $latValue) ? (float) $latValue : null;
        $lng = is_numeric((string) $lngValue) ? (float) $lngValue : null;
        if ($lat === null || $lng === null) {
            return [
                'coords' => null,
                'maps_url' => null,
                'accuracy' => null,
            ];
        }

        $coords = number_format($lat, 6) . ', ' . number_format($lng, 6);
        $accuracy = is_numeric((string) $accuracyValue) ? (float) $accuracyValue : null;

        return [
            'coords' => $coords,
            'maps_url' => 'https://maps.google.com/?q=' . rawurlencode((string) $lat . ',' . (string) $lng),
            'accuracy' => $accuracy,
        ];
    };
    $punchInGeo = $formatGeo($entry['punch_in_lat'] ?? null, $entry['punch_in_lng'] ?? null, $entry['punch_in_accuracy_m'] ?? null);
    $punchOutGeo = $formatGeo($entry['punch_out_lat'] ?? null, $entry['punch_out_lng'] ?? null, $entry['punch_out_accuracy_m'] ?? null);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Time Entry</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/time-tracking') ?>">Time Tracking</a></li>
                <li class="breadcrumb-item active">Entry #<?= e((string) ($entry['id'] ?? '')) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <?php if ($isActive): ?>
                <?php if ($isOpen): ?>
                    <form class="js-punch-geo-form" method="post" action="<?= url('/time-tracking/' . ($entry['id'] ?? '') . '/punch-out') ?>">
                        <?= csrf_field() ?>
                        <?= geo_capture_fields('time_entry_view_punch_out') ?>
                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                        <button class="btn btn-danger" type="submit">Punch Out</button>
                    </form>
                <?php endif; ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteTimeEntryModal">
                    <i class="fas fa-trash"></i>
                </button>
                <a class="btn btn-warning" href="<?= url('/time-tracking/' . ($entry['id'] ?? '') . '/edit?return_to=' . urlencode($returnTo)) ?>">
                    <i class="fas fa-pen me-1"></i>
                    Edit
                </a>
            <?php else: ?>
                <span class="badge bg-secondary align-self-center">Inactive</span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url($returnTo) ?>">Back</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clock me-1"></i>
            Entry Details
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Date</div>
                    <div class="fw-semibold"><?= e(format_date($entry['work_date'] ?? null)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Time</div>
                    <div class="fw-semibold"><?= e($timeRange) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                            <?= e($isActive ? 'Active' : 'Inactive') ?>
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Employee</div>
                    <div class="fw-semibold">
                        <?php if ($employeeId > 0): ?>
                            <a class="text-decoration-none" href="<?= url('/employees/' . $employeeId) ?>">
                                <?= e((string) ($entry['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Job</div>
                    <div class="fw-semibold">
                        <?php if ($jobId > 0): ?>
                            <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                <?= e((string) ($entry['job_name'] ?? ('Job #' . $jobId))) ?>
                            </a>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non-Job Time</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">Hours</div>
                    <div class="fw-semibold"><?= e(number_format($hours, 2)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Pay Rate</div>
                    <div class="fw-semibold"><?= e('$' . number_format((float) ($entry['pay_rate'] ?? 0), 2)) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Total Paid</div>
                    <div class="fw-semibold text-danger"><?= e('$' . number_format((float) ($entry['paid_calc'] ?? 0), 2)) ?></div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">Punch In Location</div>
                    <div class="fw-semibold">
                        <?php if ($punchInGeo['coords'] !== null): ?>
                            <a class="text-decoration-none" href="<?= e((string) $punchInGeo['maps_url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e((string) $punchInGeo['coords']) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <?php if ($punchInGeo['accuracy'] !== null): ?>
                        <div class="small text-muted">Accuracy: ±<?= e(number_format((float) $punchInGeo['accuracy'], 0)) ?>m</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Punch Out Location</div>
                    <div class="fw-semibold">
                        <?php if ($punchOutGeo['coords'] !== null): ?>
                            <a class="text-decoration-none" href="<?= e((string) $punchOutGeo['maps_url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e((string) $punchOutGeo['coords']) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <?php if ($punchOutGeo['accuracy'] !== null): ?>
                        <div class="small text-muted">Accuracy: ±<?= e(number_format((float) $punchOutGeo['accuracy'], 0)) ?>m</div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <div class="text-muted small">Note</div>
                    <div class="fw-semibold" style="white-space: pre-wrap;"><?= e((string) (($entry['note'] ?? '') !== '' ? $entry['note'] : '—')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isActive): ?>
        <div class="modal fade" id="deleteTimeEntryModal" tabindex="-1" aria-labelledby="deleteTimeEntryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteTimeEntryModalLabel">Delete Time Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        This will mark the time entry as deleted and hide it from active views. Continue?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= url('/time-tracking/' . ($entry['id'] ?? '') . '/delete') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>" />
                            <button class="btn btn-danger" type="submit">Delete Time Entry</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
