<?php
    $statusOptions = is_array($statusOptions ?? null) ? $statusOptions : ['pending', 'active', 'complete', 'cancelled'];
    $savedPresets = is_array($savedPresets ?? null) ? $savedPresets : [];
    $selectedPresetId = isset($selectedPresetId) ? (int) $selectedPresetId : 0;
    $filterPresetModule = (string) ($filterPresetModule ?? 'jobs');
    $currentFilters = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? 'all'),
        'record_status' => (string) ($filters['record_status'] ?? 'active'),
        'billing_state' => (string) ($filters['billing_state'] ?? 'all'),
        'start_date' => (string) ($filters['start_date'] ?? ''),
        'end_date' => (string) ($filters['end_date'] ?? ''),
    ];
    $exportParams = array_merge($currentFilters, ['preset_id' => $selectedPresetId > 0 ? (string) $selectedPresetId : '', 'export' => 'csv']);
    $exportParams = array_filter($exportParams, static fn (mixed $value): bool => (string) $value !== '');
    $currentPath = '/jobs';
    $currentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $currentReturnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
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
        <a class="btn btn-primary" href="<?= url('/jobs/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Job
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
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
                <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                    <?php if ($selectedPresetId > 0): ?>
                        <form method="post" action="<?= url('/filter-presets/' . $selectedPresetId . '/delete') ?>" onsubmit="return confirm('Delete this preset?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="module_key" value="<?= e($filterPresetModule) ?>" />
                            <input type="hidden" name="return_to" value="<?= e('/jobs') ?>" />
                            <button class="btn btn-outline-danger" type="submit">Delete Preset</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary" href="<?= url('/jobs?' . http_build_query($exportParams)) ?>">
                        <i class="fas fa-file-csv me-1"></i>
                        Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
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
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?= ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($statusOptions as $statusOption): ?>
                                <option value="<?= e((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === (string) $statusOption ? 'selected' : '' ?>>
                                    <?= e(ucwords(str_replace('_', ' ', (string) $statusOption))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Billing</label>
                        <select class="form-select" name="billing_state">
                            <option value="all" <?= ($filters['billing_state'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="billed" <?= ($filters['billing_state'] ?? '') === 'billed' ? 'selected' : '' ?>>Billed</option>
                            <option value="unbilled" <?= ($filters['billing_state'] ?? '') === 'unbilled' ? 'selected' : '' ?>>Unbilled</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Start Date</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>" />
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">End Date</label>
                        <input class="form-control" type="date" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/jobs') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-briefcase me-1"></i>
            All Jobs
        </div>
        <div class="card-body">
            <table id="jobsTable">
                <thead>
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
                                'active' => 'bg-primary',
                                'complete' => 'bg-success',
                                'cancelled' => 'bg-secondary',
                                default => 'bg-warning',
                            };
                        ?>
                        <tr>
                            <td><?= e((string) $job['id']) ?></td>
                            <td>
                                <a class="text-decoration-none" href="<?= url('/jobs/' . $job['id']) ?>">
                                    <?= e($job['name'] ?? '') ?>
                                </a>
                            </td>
                            <td><?= e($clientName) ?></td>
                            <td><?= e(trim(($job['city'] ?? '') . (isset($job['state']) && $job['state'] !== '' ? ', ' . $job['state'] : ''))) ?></td>
                            <td><span class="badge <?= $statusClass ?> text-uppercase"><?= e($status) ?></span></td>
                            <td><?= e(format_datetime($job['scheduled_date'] ?? null)) ?></td>
                            <td><?= isset($job['total_quote']) ? e('$' . number_format((float) $job['total_quote'], 2)) : '—' ?></td>
                            <td><?= e(format_datetime($job['last_activity_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
