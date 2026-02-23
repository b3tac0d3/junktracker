<?php
$filters = $filters ?? [];
$entries = $entries ?? [];
$byEmployee = $byEmployee ?? [];
$employees = $employees ?? [];
$jobs = $jobs ?? [];
$savedPresets = is_array($savedPresets ?? null) ? $savedPresets : [];
$selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;
$filterPresetModule = (string) ($filterPresetModule ?? 'time_tracking');

$formatTime = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);
    return $time === false ? $value : date('g:i A', $time);
};

$currentPath = '/time-tracking';
$currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');

$currentFilters = [
    'q' => (string) ($filters['q'] ?? ''),
    'employee_id' => $filters['employee_id'] ?? '',
    'job_id' => $filters['job_id'] ?? '',
    'start_date' => (string) ($filters['start_date'] ?? ''),
    'end_date' => (string) ($filters['end_date'] ?? ''),
    'record_status' => (string) ($filters['record_status'] ?? 'active'),
];

$activeFilterCount = count(array_filter([
    $currentFilters['q'] !== '',
    $currentFilters['employee_id'] !== '',
    $currentFilters['job_id'] !== '',
    $currentFilters['start_date'] !== '',
    $currentFilters['end_date'] !== '',
    $currentFilters['record_status'] !== 'active',
]));
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Time Tracking</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Time Tracking</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-primary" href="<?= url('/time-tracking/open') ?>">
                <i class="fas fa-user-clock me-1"></i>
                Currently Punched In
            </a>
            <a class="btn btn-primary" href="<?= url('/time-tracking/new') ?>">
                <i class="fas fa-plus me-1"></i>
                Add Time Entry
            </a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Filter card -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2" 
             data-bs-toggle="collapse" 
             data-bs-target="#timeFilterCollapse" 
             aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" 
             aria-controls="timeFilterCollapse" 
             style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="timeFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/time-tracking') ?>">
                    <?php if ($selectedPresetId > 0): ?>
                        <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>" />
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="q"
                                    placeholder="Search employee, job, note..."
                                    value="<?= e((string) ($filters['q'] ?? '')) ?>"
                                />
                            </div>
                        </div>

                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Employee</label>
                            <select class="form-select" name="employee_id">
                                <option value="">All</option>
                                <?php foreach ($employees as $employee): ?>
                                    <?php $id = (string) ((int) ($employee['id'] ?? 0)); ?>
                                    <option value="<?= e($id) ?>" <?= (string) ($filters['employee_id'] ?? '') === $id ? 'selected' : '' ?>>
                                        <?= e((string) ($employee['name'] ?? ('Employee #' . $id))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Job</label>
                            <select class="form-select" name="job_id">
                                <option value="">All</option>
                                <option value="0" <?= (string) ($filters['job_id'] ?? '') === '0' ? 'selected' : '' ?>>Non-Job Time</option>
                                <?php foreach ($jobs as $job): ?>
                                    <?php $id = (string) ((int) ($job['id'] ?? 0)); ?>
                                    <option value="<?= e($id) ?>" <?= (string) ($filters['job_id'] ?? '') === $id ? 'selected' : '' ?>>
                                        #<?= e($id) ?> - <?= e((string) ($job['name'] ?? ('Job #' . $id))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Start Date</label>
                            <input class="form-control" type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')) ?>" />
                        </div>

                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">End Date</label>
                            <input class="form-control" type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')) ?>" />
                        </div>

                        <div class="col-12 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Record Status</label>
                            <select class="form-select" name="record_status">
                                <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                                <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>

                        <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/time-tracking') ?>">Clear</a>
                        </div>
                    </div>
                </form>

                <div class="filter-presets-section border-top mt-4 pt-3">
                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <form method="get" action="<?= url('/time-tracking') ?>">
                                <label class="form-label small fw-bold text-muted">Saved Filters</label>
                                <div class="input-group">
                                    <select class="form-select" name="preset_id">
                                        <option value="">Choose preset...</option>
                                        <?php foreach ($savedPresets as $preset): ?>
                                            <?php $presetId = (int) ($preset['id'] ?? 0); ?>
                                            <option value="<?= e((string) $presetId) ?>" <?= $selectedPresetId === $presetId ? 'selected' : '' ?>>
                                                <?= e((string) ($preset['preset_name'] ?? ('Preset #' . $presetId))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-outline-primary" type="submit">Load</button>
                                    <a class="btn btn-outline-secondary" href="<?= url('/time-tracking') ?>">Reset</a>
                                </div>
                            </form>
                        </div>

                        <div class="col-12 col-lg-4">
                            <form method="post" action="<?= url('/filter-presets/save') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                                <input type="hidden" name="return_to" value="<?= e($currentReturnTo) ?>" />
                                <input type="hidden" name="filters_json" value='<?= e((string) json_encode($currentFilters)) ?>' />
                                <label class="form-label small fw-bold text-muted">Save Current Filters</label>
                                <div class="input-group">
                                    <input class="form-control" type="text" name="preset_name" placeholder="Preset name..." />
                                    <button class="btn btn-outline-success" type="submit">Save</button>
                                </div>
                            </form>
                        </div>

                        <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end mobile-two-col-buttons">
                            <?php if ($selectedPresetId > 0): ?>
                                <form method="post" action="<?= url('/filter-presets/' . $selectedPresetId . '/delete') ?>" onsubmit="return confirm('Delete this preset?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                                    <input type="hidden" name="return_to" value="<?= e('/time-tracking') ?>" />
                                    <button class="btn btn-outline-danger" type="submit">Delete Preset</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Entries Card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="fas fa-table me-1"></i>
                <span class="fw-semibold">Time Entries</span>
            </div>
            <?php if (!empty($entries)): ?>
                <span class="badge bg-secondary rounded-pill"><?= count($entries) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($entries)): ?>
                <div class="jt-empty-state py-5 text-center">
                    <div class="empty-icon-wrapper bg-3 mb-3">
                        <i class="fas fa-clock fa-3x text-light-subtle"></i>
                    </div>
                    <h5 class="text-muted fw-normal">No time entries found</h5>
                    <p class="text-muted small mb-4">Try adjusting your filters or add a new entry.</p>
                    <a href="<?= url('/time-tracking/new') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add Time Entry
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="timeTrackingTable" class="table table-hover align-middle mb-0 js-card-list-source">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Job</th>
                                <th>Employee</th>
                                <th>Time</th>
                                <th class="text-center">Hours</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">Owed</th>
                                <th>Note</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <?php
                                $minutes = (int) ($entry['minutes_worked'] ?? 0);
                                $hours = $minutes / 60;
                                $start = $formatTime($entry['start_time'] ?? null);
                                $end = $formatTime($entry['end_time'] ?? null);
                                $timeRange = ($entry['start_time'] ?? null) || ($entry['end_time'] ?? null)
                                    ? $start . ' - ' . $end
                                    : '—';
                                $jobId = (int) ($entry['job_id'] ?? 0);
                                $employeeId = (int) ($entry['employee_id'] ?? 0);
                                $entryId = (int) ($entry['id'] ?? 0);
                                $rowHref = url('/time-tracking/' . $entryId . '?return_to=' . urlencode($currentReturnTo));
                                ?>
                                <tr onclick="window.location.href='<?= $rowHref ?>'" style="cursor: pointer;">
                                    <td><?= e(format_date($entry['work_date'] ?? null)) ?></td>
                                    <td>
                                        <?php if ($jobId > 0): ?>
                                            <a class="text-decoration-none fw-semibold" href="<?= url('/jobs/' . $jobId) ?>" onclick="event.stopPropagation();">
                                                <?= e((string) ($entry['job_name'] ?? ('Job #' . $jobId))) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non-Job Time</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($employeeId > 0): ?>
                                            <a class="text-decoration-none" href="<?= url('/employees/' . $employeeId) ?>" onclick="event.stopPropagation();">
                                                <?= e((string) ($entry['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= e($timeRange) ?></small></td>
                                    <td class="text-center fw-bold"><?= e(number_format($hours, 2)) ?></td>
                                    <td class="text-end text-muted small"><?= e('$' . number_format((float) ($entry['pay_rate'] ?? 0), 2)) ?></td>
                                    <td class="text-end text-danger fw-bold"><?= e('$' . number_format((float) ($entry['paid_calc'] ?? 0), 2)) ?></td>
                                    <td class="small text-truncate" style="max-width: 200px;"><?= e((string) (($entry['note'] ?? '') !== '' ? $entry['note'] : '—')) ?></td>
                                    <td><small class="text-muted"><?= e(format_datetime($entry['updated_at'] ?? null)) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Labor by Employee Card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="fas fa-users me-1"></i>
                <span class="fw-semibold">Labor by Employee</span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($byEmployee)): ?>
                <div class="jt-empty-state py-4 text-center">
                    <p class="text-muted small mb-0">No employee totals for this filter set.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 js-card-list-source">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th class="text-center">Entries</th>
                                <th class="text-center">Hours</th>
                                <th class="text-end">Owed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byEmployee as $row): ?>
                                <?php
                                $employeeId = (int) ($row['employee_id'] ?? 0);
                                $minutes = (int) ($row['total_minutes'] ?? 0);
                                $hours = $minutes / 60;
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($employeeId > 0): ?>
                                            <a class="text-decoration-none fw-semibold" href="<?= url('/employees/' . $employeeId) ?>">
                                                <?= e((string) ($row['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= e((string) ((int) ($row['entry_count'] ?? 0))) ?></td>
                                    <td class="text-center fw-bold"><?= e(number_format($hours, 2)) ?></td>
                                    <td class="text-end text-danger fw-bold"><?= e('$' . number_format((float) ($row['total_paid'] ?? 0), 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
