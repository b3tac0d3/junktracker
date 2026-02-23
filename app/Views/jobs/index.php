<?php
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : ['pending', 'dispatch', 'active', 'complete', 'cancelled'];
$savedPresets = is_array($savedPresets ?? null) ? $savedPresets : [];
$selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;
$filterPresetModule = (string) ($filterPresetModule ?? 'jobs');
$currentFilters = [
    'q' => (string) ($filters['q'] ?? ''),
    'status' => (string) ($filters['status'] ?? 'dispatch'),
    'record_status' => (string) ($filters['record_status'] ?? 'active'),
    'billing_state' => (string) ($filters['billing_state'] ?? 'all'),
    'start_date' => (string) ($filters['start_date'] ?? ''),
    'end_date' => (string) ($filters['end_date'] ?? ''),
];
$currentPath = '/jobs';
$currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
$activeFilterCount = count(array_filter([
    $currentFilters['q'] !== '',
    $currentFilters['status'] !== 'dispatch' && $currentFilters['status'] !== '',
    $currentFilters['record_status'] !== 'active',
    $currentFilters['billing_state'] !== 'all',
    $currentFilters['start_date'] !== '',
    $currentFilters['end_date'] !== '',
]));
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Jobs</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Jobs</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-outline-primary" href="<?= url('/jobs/schedule') ?>">
                <i class="fas fa-calendar-days me-1"></i>
                Schedule Board
            </a>
            <a class="btn btn-primary" href="<?= url('/jobs/new') ?>">
                <i class="fas fa-plus me-1"></i>
                Add Job
            </a>
        </div>
    </div>

    <!-- Filter card -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2" data-bs-toggle="collapse" data-bs-target="#jobsFilterCollapse" aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="jobsFilterCollapse" style="cursor:pointer;">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= $activeFilterCount ?> active</span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="jobsFilterCollapse">
            <div class="card-body">
                <form method="GET" action="<?= url('/jobs') ?>">
                    <?php if ($selectedPresetId > 0): ?>
                        <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label small fw-bold text-muted">Search</label>
                            <input class="form-control" type="text" name="q" placeholder="Search jobs or clients..." value="<?= e($filters['q'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?= ($filters['status'] ?? 'dispatch') == 'all' ? 'selected' : '' ?>>All</option>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?= e((string) $statusOption) ?>" <?= ($filters['status'] ?? '') == (string) $statusOption ? 'selected' : '' ?>><?= e(ucfirst((string) $statusOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Record</label>
                            <select class="form-select" name="record_status">
                                <option value="active" <?= ($filters['record_status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="deleted" <?= ($filters['record_status'] ?? '') == 'deleted' ? 'selected' : '' ?>>Deleted</option>
                                <option value="all" <?= ($filters['record_status'] ?? '') == 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Billing</label>
                            <select class="form-select" name="billing_state">
                                <option value="all" <?= ($filters['billing_state'] ?? 'all') == 'all' ? 'selected' : '' ?>>All</option>
                                <option value="billed" <?= ($filters['billing_state'] ?? '') == 'billed' ? 'selected' : '' ?>>Billed</option>
                                <option value="unbilled" <?= ($filters['billing_state'] ?? '') == 'unbilled' ? 'selected' : '' ?>>Unbilled</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label small fw-bold text-muted">Start Date</label>
                            <input class="form-control" type="date" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label class="form-label small fw-bold text-muted">End Date</label>
                            <input class="form-control" type="date" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 mt-3">
                            <button class="btn btn-primary px-4" type="submit">Apply Filters</button>
                            <a class="btn btn-light border" href="<?= url('/jobs') ?>">Clear</a>
                        </div>
                    </div>
                </form>

                <hr class="my-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Saved Filters</label>
                        <form class="d-flex gap-2" method="GET" action="<?= url('/filters/load') ?>">
                            <select class="form-select" name="preset_id">
                                <option value="">Choose preset...</option>
                                <?php foreach ($savedPresets as $presetId => $presetName): ?>
                                    <option value="<?= e((string) $presetId) ?>" <?= $selectedPresetId === $presetId ? 'selected' : '' ?>><?= e((string) $presetName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="submit">Load</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">Reset</a>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Save Current Filters</label>
                        <form class="d-flex gap-2" method="POST" action="<?= url('/filters/save') ?>">
                            <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>">
                            <input type="hidden" name="return_to" value="<?= e($currentReturnTo) ?>">
                            <input type="hidden" name="filters_json" value="<?= e((string) json_encode($currentFilters)) ?>">
                            <input class="form-control" type="text" name="preset_name" placeholder="Preset name...">
                            <button class="btn btn-outline-success" type="submit">Save</button>
                        </form>
                    </div>
                    <?php if ($selectedPresetId > 0): ?>
                        <div class="col-12 text-end">
                            <form method="POST" action="<?= url('/filters/delete/' . $selectedPresetId) ?>" onsubmit="return confirm('Delete this filter preset?')">
                                <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>">
                                <input type="hidden" name="return_to" value="<?= e('/jobs') ?>">
                                <button class="btn btn-link text-danger p-0 small" type="submit">Delete Preset</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs list -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-list me-2 text-primary"></i>
                <h5 class="mb-0">All Jobs</h5>
            </div>
            <span id="jobsCountBadge" class="badge bg-light text-dark border"></span>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0 js-card-list-source" data-card-primary-col="1">
                <thead class="table-dark">
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Job</th>
                        <th>Client</th>
                        <th>City</th>
                        <th style="width:120px;">Status</th>
                        <th>Scheduled</th>
                        <th>Quote</th>
                        <th>Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No jobs found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $clientName = trim(($job['first_name'] ?? '') . ' ' . ($job['last_name'] ?? ''));
                        if ($clientName === '') { $clientName = $job['business_name'] ?? ''; }
                        $status = $job['job_status'] ?? '';
                        $statusClass = match ($status) {
                            'dispatch' => 'bg-info text-dark',
                            'active' => 'bg-primary',
                            'complete' => 'bg-success',
                            'cancelled' => 'bg-secondary',
                            default => 'bg-warning',
                        };
                        ?>
                        <tr>
                            <td><?= e((string) $job['id']) ?></td>
                            <td><a class="text-decoration-none fw-semibold" href="<?= url('/jobs/' . $job['id']) ?>"><?= e($job['name'] ?? '') ?></a></td>
                            <td><?= e($clientName) ?></td>
                            <td><?= e(trim(($job['city'] ?? '') . (isset($job['state']) && $job['state'] !== '' ? ', ' . $job['state'] : ''))) ?></td>
                            <td><span class="badge <?= $statusClass ?> text-uppercase"><?= e($status) ?></span></td>
                            <td><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></td>
                            <td><?= isset($job['total_quote']) ? '$' . number_format((float) $job['total_quote'], 2) : '&mdash;' ?></td>
                            <td><?= e(format_datetime($job['last_activity_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

