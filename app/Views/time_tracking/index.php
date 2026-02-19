<?php
    $filters = $filters ?? [];
    $entries = $entries ?? [];
    $summary = $summary ?? [];
    $byEmployee = $byEmployee ?? [];
    $employees = $employees ?? [];
    $jobs = $jobs ?? [];
    $savedPresets = is_array($savedPresets ?? null) ? $savedPresets : [];
    $selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;
    $filterPresetModule = (string) ($filterPresetModule ?? 'time_tracking');

    $totalMinutes = (int) ($summary['total_minutes'] ?? 0);
    $totalHours = $totalMinutes / 60;
    $totalPaid = (float) ($summary['total_paid'] ?? 0);
    $entryCount = (int) ($summary['entry_count'] ?? 0);
    $avgRate = $totalMinutes > 0 ? ($totalPaid / ($totalMinutes / 60)) : 0;

    $formatMinutes = static function (int $minutes): string {
        if ($minutes <= 0) {
            return '0h 00m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        return $hours . 'h ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 'm';
    };

    $formatTime = static function (?string $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $time = strtotime($value);
        return $time === false ? $value : date('g:i A', $time);
    };
    $currentFilters = [
        'q' => (string) ($filters['q'] ?? ''),
        'employee_id' => $filters['employee_id'] ?? '',
        'job_id' => $filters['job_id'] ?? '',
        'start_date' => (string) ($filters['start_date'] ?? ''),
        'end_date' => (string) ($filters['end_date'] ?? ''),
        'record_status' => (string) ($filters['record_status'] ?? 'active'),
    ];
    $currentPath = '/time-tracking';
    $currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
    $exportParams = array_merge($currentFilters, ['preset_id' => $selectedPresetId > 0 ? (string) $selectedPresetId : '', 'export' => 'csv']);
    $exportParams = array_filter($exportParams, static fn (mixed $value): bool => (string) $value !== '');
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
        <div class="d-flex gap-2">
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

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <form method="get" action="<?= url('/time-tracking') ?>">
                        <label class="form-label">Saved Filters</label>
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
                        <label class="form-label">Save Current Filters</label>
                        <div class="input-group">
                            <input class="form-control" type="text" name="preset_name" placeholder="Preset name..." />
                            <button class="btn btn-outline-success" type="submit">Save</button>
                        </div>
                    </form>
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                    <?php if ($selectedPresetId > 0): ?>
                        <form method="post" action="<?= url('/filter-presets/' . $selectedPresetId . '/delete') ?>" onsubmit="return confirm('Delete this preset?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                            <input type="hidden" name="return_to" value="<?= e('/time-tracking') ?>" />
                            <button class="btn btn-outline-danger" type="submit">Delete Preset</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary" href="<?= url('/time-tracking?' . http_build_query($exportParams)) ?>">
                        <i class="fas fa-file-csv me-1"></i>
                        Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Hours</div>
                    <div class="h4 mb-1 text-primary"><?= e(number_format($totalHours, 2)) ?></div>
                    <div class="small text-muted"><?= e($formatMinutes($totalMinutes)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Labor Owed</div>
                    <div class="h4 mb-1 text-danger"><?= e('$' . number_format($totalPaid, 2)) ?></div>
                    <div class="small text-muted">Current filter range</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Entries</div>
                    <div class="h4 mb-1 text-success"><?= e((string) $entryCount) ?></div>
                    <div class="small text-muted">Time records</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Avg Hourly Cost</div>
                    <div class="h4 mb-1 text-warning"><?= e('$' . number_format($avgRate, 2)) ?></div>
                    <div class="small text-muted">Based on logged minutes</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/time-tracking') ?>">
                <?php if ($selectedPresetId > 0): ?>
                    <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>" />
                <?php endif; ?>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Search</label>
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
                        <label class="form-label">Employee</label>
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
                        <label class="form-label">Job</label>
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
                    <div class="col-12 col-lg-1">
                        <label class="form-label">Start</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 col-lg-1">
                        <label class="form-label">End</label>
                        <input class="form-control" type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/time-tracking') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Time Entries
        </div>
        <div class="card-body">
            <?php if (empty($entries)): ?>
                <div class="text-muted">No time entries found for this filter set.</div>
            <?php else: ?>
                <table id="timeTrackingTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Job</th>
                            <th>Employee</th>
                            <th>Time</th>
                            <th>Hours</th>
                            <th>Rate</th>
                            <th>Owed</th>
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
                            ?>
                            <tr>
                                <td><?= e(format_date($entry['work_date'] ?? null)) ?></td>
                                <td>
                                    <?php if ($jobId > 0): ?>
                                        <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                            <?= e((string) ($entry['job_name'] ?? ('Job #' . $jobId))) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non-Job Time</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($employeeId > 0): ?>
                                        <a class="text-decoration-none" href="<?= url('/employees/' . $employeeId) ?>">
                                            <?= e((string) ($entry['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= e($timeRange) ?></td>
                                <td>
                                    <?php if ($entryId > 0): ?>
                                        <a class="text-decoration-none" href="<?= url('/time-tracking/' . $entryId . '?return_to=' . urlencode('/time-tracking')) ?>">
                                            <?= e(number_format($hours, 2)) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e(number_format($hours, 2)) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e('$' . number_format((float) ($entry['pay_rate'] ?? 0), 2)) ?></td>
                                <td class="text-danger"><?= e('$' . number_format((float) ($entry['paid_calc'] ?? 0), 2)) ?></td>
                                <td><?= e((string) (($entry['note'] ?? '') !== '' ? $entry['note'] : '—')) ?></td>
                                <td><?= e(format_datetime($entry['updated_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Labor by Employee
        </div>
        <div class="card-body">
            <?php if (empty($byEmployee)): ?>
                <div class="text-muted">No employee totals for this filter set.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Entries</th>
                                <th>Hours</th>
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
                                            <a class="text-decoration-none" href="<?= url('/employees/' . $employeeId) ?>">
                                                <?= e((string) ($row['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) ((int) ($row['entry_count'] ?? 0))) ?></td>
                                    <td><?= e(number_format($hours, 2)) ?></td>
                                    <td class="text-end text-danger"><?= e('$' . number_format((float) ($row['total_paid'] ?? 0), 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
