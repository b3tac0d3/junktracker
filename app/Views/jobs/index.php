<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? 'dispatch')));
$fromDate = trim((string) ($fromDate ?? date('Y-01-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$jobs = is_array($jobs ?? null) ? $jobs : [];
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
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/jobs')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="jobs-search">Search</label>
                <input id="jobs-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by job, client, city, or id..." />
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="jobs-status">Status</label>
                <select id="jobs-status" class="form-select" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="jobs-from-date">From</label>
                <input id="jobs-from-date" class="form-control" type="date" name="from_date" value="<?= e($fromDate) ?>">
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="jobs-to-date">To</label>
                <input id="jobs-to-date" class="form-control" type="date" name="to_date" value="<?= e($toDate) ?>">
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/jobs')) ?>">Clear</a>
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
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/jobs/' . (string) ((int) ($job['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)))) ?></h3>
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
