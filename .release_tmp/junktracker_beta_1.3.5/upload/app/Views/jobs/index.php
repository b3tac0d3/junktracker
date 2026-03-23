<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? 'dispatch')));
$fromDate = trim((string) ($fromDate ?? date('Y-01-01')));
$toDate = trim((string) ($toDate ?? date('Y-12-31')));
$sortBy = strtolower(trim((string) ($sortBy ?? 'date')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'desc')));
$jobs = is_array($jobs ?? null) ? $jobs : [];
$filteredSummary = is_array($filteredSummary ?? null) ? $filteredSummary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($jobs), count($jobs));
$perPage = (int) ($pagination['per_page'] ?? 25);
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : ['prospect', 'pending', 'active', 'complete', 'cancelled'];
$statusOptions = [
    'dispatch' => 'Dispatch (Pending + Active)',
    '' => 'All',
];
foreach ($statusOptionsRaw as $statusOptionRaw) {
    $statusOption = strtolower(trim((string) $statusOptionRaw));
    if ($statusOption === '') {
        continue;
    }
    if (array_key_exists($statusOption, $statusOptions)) {
        continue;
    }
    $statusOptions[$statusOption] = ucwords(str_replace('_', ' ', $statusOption));
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Jobs</h1>
        <p class="muted">Simple job index</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/jobs/create')) ?>"><i class="fas fa-plus me-2"></i>Add Job</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5">
            <div class="record-field">
                <span class="record-label">Job Potential</span>
                <span class="record-value">$<?= e(number_format((float) ($filteredSummary['job_potential'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Gross MTD</span>
                <span class="record-value">$<?= e(number_format((float) ($filteredSummary['gross_mtd'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net MTD</span>
                <span class="record-value">$<?= e(number_format((float) ($filteredSummary['net_mtd'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Gross YTD</span>
                <span class="record-value">$<?= e(number_format((float) ($filteredSummary['gross_ytd'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net YTD</span>
                <span class="record-value">$<?= e(number_format((float) ($filteredSummary['net_ytd'] ?? 0), 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/jobs')) ?>">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold" for="jobs-search">Search</label>
                    <input id="jobs-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by job, client, city, or id..." />
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="jobs-status">Status</label>
                    <select id="jobs-status" class="form-select" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 align-items-end mt-2 mt-md-3">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold" for="jobs-from-date">From</label>
                    <input id="jobs-from-date" class="form-control" type="date" name="from_date" value="<?= e($fromDate) ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold" for="jobs-to-date">To</label>
                    <input id="jobs-to-date" class="form-control" type="date" name="to_date" value="<?= e($toDate) ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label fw-semibold" for="jobs-sort-by">Sort By</label>
                    <select id="jobs-sort-by" class="form-select" name="sort_by">
                        <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Date</option>
                        <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                        <option value="client_name" <?= $sortBy === 'client_name' ? 'selected' : '' ?>>Client Name</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label fw-semibold" for="jobs-sort-dir">Order</label>
                    <select id="jobs-sort-dir" class="form-select" name="sort_dir">
                        <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                        <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid d-sm-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/jobs')) ?>">Clear</a>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-briefcase me-2"></i>Job List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($jobs)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/jobs';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($jobs === []): ?>
            <div class="record-empty">No jobs found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $jobStatus = strtolower(trim((string) ($job['status'] ?? 'pending')));
                    $isInactive = $jobStatus === 'inactive' || (array_key_exists('is_active', $job) && (int) ($job['is_active'] ?? 1) === 0);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/jobs/' . (string) ((int) ($job['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)))) ?></h3>
                                <?php if ($isInactive): ?>
                                    <span class="badge text-bg-secondary">Deactivated</span>
                                <?php endif; ?>
                            </div>
                            <div class="record-row-fields record-row-fields-4">
                                <div class="record-field">
                                    <span class="record-label">Scheduled</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($job['scheduled_start_at'] ?? null))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Client</span>
                                    <span class="record-value"><?= e(trim((string) ($job['client_name'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value text-capitalize"><?= e((string) ($job['status'] ?? 'pending')) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">City</span>
                                    <span class="record-value"><?= e(trim((string) ($job['city'] ?? '')) ?: '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
