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

    <!-- Mobile-first collapsible filter panel -->
    <div class="card mb-4 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2"
             data-bs-toggle="collapse" data-bs-target="#jobsFilterCollapse"
             aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="jobsFilterCollapse"
             role="button" style="cursor:pointer;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-filter"></i>
                <span class="fw-semibold">Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                <span class="badge rounded-pill bg-primary"><?= $activeFilterCount ?></span>
                <?php endif; ?>
            </div>
            <i class="fas fa-chevron-down jt-filter-chevron"></i>
        </div>
        <div class="collapse <?= $activeFilterCount > 0 ? 'show' : '' ?>" id="jobsFilterCollapse">
            <div class="card-body">
                <form method="get" action="<?= url('/jobs') ?>">
                    <?php if ($selectedPresetId > 0): ?>
                    <input type="hidden" name="preset_id" value="<?= e((string) $selectedPresetId) ?>" />
                    <?php endif; ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-4">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input class="form-control" type="text" name="q" placeholder="Search jobs or clients..." value="<?= e($filters['q'] ?? '') ?>" />
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?= ($filters['status'] ?? 'dispatch') === 'all' ? 'selected' : '' ?>>All</option>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                <option value="<?= e((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === (string) $statusOption ? 'selected' : '' ?>>
                                    <?= e(ucwords(str_replace('_', ' ', (string) $statusOption))) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Record</label>
                            <select class="form-select" name="record_status">
                                <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                                <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Billing</label>
                            <select class="form-select" name="billing_state">
                                <option value="all" <?= ($filters['billing_state'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="billed" <?= ($filters['billing_state'] ?? '') === 'billed' ? 'selected' : '' ?>>Billed</option>
                                <option value="unbilled" <?= ($filters['billing_state'] ?? '') === 'unbilled' ? 'selected' : '' ?>>Unbilled</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label">Start Date</label>
                            <input class="form-control" type="date" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>" />
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label">End Date</label>
                            <input class="form-control" type="date" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>" />
                        </div>
                        <div class="col-12 d-flex gap-2 mobile-two-col-buttons">
                            <button class="btn btn-primary" type="submit">Apply Filters</button>
                            <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">Clear</a>
                        </div>
                    </div>
                </form>
                <div class="filter-presets-section border-top mt-3 pt-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-5">
                            <form method="get" action="<?= url('/jobs') ?>">
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
                                    <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">Reset</a>
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
                        <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end mobile-two-col-buttons">
                            <?php if ($selectedPresetId > 0): ?>
                            <form method="post" action="<?= url('/filter-presets/' . $selectedPresetId . '/delete') ?>" onsubmit="return confirm('Delete this preset?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                                <input type="hidden" name="return_to" value="<?= e('/jobs') ?>" />
                                <button class="btn btn-outline-danger" type="submit">Delete Preset</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs list card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <i class="fas fa-briefcase me-1"></i>
                All Jobs
            </div>
            <span class="badge bg-secondary" id="jobsCountBadge"></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="jobsTable" class="table table-hover align-middle mb-0 js-card-list-source">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Job</th>
                            <th>Client</th>
                            <th>City</th>
                            <th>Status</th>
                            <th>Scheduled</th>
                            <th>Quote</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <?php
                            $clientName = trim(($job['first_name'] ?? '') . ' ' . ($job['last_name'] ?? ''));
                            if ($clientName === '') {
                                $clientName = $job['business_name'] ?? '';
                            }
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
                            <td data-label="ID"><?= e((string) $job['id']) ?></td>
                            <td data-label="Job">
                                <a class="text-decoration-none fw-semibold" href="<?= url('/jobs/' . $job['id']) ?>">
                                    <?= e($job['name'] ?? '') ?>
                                </a>
                            </td>
                            <td data-label="Client"><?= e($clientName) ?></td>
                            <td data-label="City"><?= e(trim(($job['city'] ?? '') . (isset($job['state']) && $job['state'] !== '' ? ', ' . $job['state'] : ''))) ?></td>
                            <td data-label="Status"><span class="badge <?= $statusClass ?> text-uppercase"><?= e($status) ?></span></td>
                            <td data-label="Scheduled"><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></td>
                            <td data-label="Quote"><?= isset($job['total_quote']) ? e('$' . number_format((float) $job['total_quote'], 2)) : '&#8212;' ?></td>
                            <td data-label="Last Activity"><?= e(format_datetime($job['last_activity_at'] ?? null)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
