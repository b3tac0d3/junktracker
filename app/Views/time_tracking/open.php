<?php
    $filters = $filters ?? [];
    $entries = $entries ?? [];
    $summary = $summary ?? [];
    $employees = $employees ?? [];
    $jobs = $jobs ?? [];

    $activeCount = (int) ($summary['active_count'] ?? 0);
    $totalMinutes = (int) ($summary['total_open_minutes'] ?? 0);
    $totalHours = $totalMinutes / 60;
    $totalPaid = (float) ($summary['total_open_paid'] ?? 0);

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

    $openReturnTo = '/time-tracking/open';
    $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString !== '') {
        $openReturnTo .= '?' . $queryString;
    }
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Currently Punched In</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/time-tracking') ?>">Time Tracking</a></li>
                <li class="breadcrumb-item active">Open Clock</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-secondary" href="<?= url('/time-tracking') ?>">
                <i class="fas fa-history me-1"></i>
                Time History
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

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Employees On Clock</div>
                    <div class="h4 mb-1 text-primary"><?= e((string) $activeCount) ?></div>
                    <div class="small text-muted">Current open entries</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Open Hours</div>
                    <div class="h4 mb-1 text-success"><?= e(number_format($totalHours, 2)) ?></div>
                    <div class="small text-muted"><?= e($formatMinutes($totalMinutes)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Estimated Labor</div>
                    <div class="h4 mb-1 text-danger"><?= e('$' . number_format($totalPaid, 2)) ?></div>
                    <div class="small text-muted">Based on elapsed time</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/time-tracking/open') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                class="form-control"
                                type="text"
                                name="q"
                                placeholder="Search employee or job..."
                                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                            />
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
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
                    <div class="col-12 col-lg-2 d-flex gap-2 mobile-two-col-buttons">
                        <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                        <a class="btn btn-outline-secondary flex-fill" href="<?= url('/time-tracking/open') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-clock me-1"></i>
            Open Clock Entries
        </div>
        <div class="card-body">
            <?php if (empty($entries)): ?>
                <div class="text-muted">Nobody is currently punched in.</div>
            <?php else: ?>
                <table id="timeOpenTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Job</th>
                            <th>Date</th>
                            <th>Punched In</th>
                            <th>Elapsed</th>
                            <th>Rate</th>
                            <th>Est. Labor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                                $employeeId = (int) ($entry['employee_id'] ?? 0);
                                $jobId = (int) ($entry['job_id'] ?? 0);
                                $openMinutes = (int) ($entry['open_minutes'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($employeeId > 0): ?>
                                        <a class="text-decoration-none" href="<?= url('/employees/' . $employeeId) ?>">
                                            <?= e((string) ($entry['employee_name'] ?? ('Employee #' . $employeeId))) ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($jobId > 0): ?>
                                        <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                            <?= e((string) ($entry['job_name'] ?? ('Job #' . $jobId))) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non-Job Time</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(format_date($entry['work_date'] ?? null)) ?></td>
                                <td><?= e($formatTime($entry['start_time'] ?? null)) ?></td>
                                <td><?= e($formatMinutes($openMinutes)) ?></td>
                                <td><?= e('$' . number_format((float) ($entry['pay_rate'] ?? 0), 2)) ?></td>
                                <td class="text-danger"><?= e('$' . number_format((float) ($entry['open_paid'] ?? 0), 2)) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= url('/time-tracking/' . ((int) ($entry['id'] ?? 0)) . '/punch-out') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= e($openReturnTo) ?>" />
                                        <button class="btn btn-sm btn-danger" type="submit">Punch Out</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
