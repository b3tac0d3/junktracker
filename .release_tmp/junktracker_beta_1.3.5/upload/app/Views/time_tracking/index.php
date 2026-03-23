<?php
$search = trim((string) ($search ?? ''));
$state = strtolower(trim((string) ($state ?? '')));
$entries = is_array($entries ?? null) ? $entries : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($entries), count($entries));
$perPage = (int) ($pagination['per_page'] ?? 25);
$canManageEmployees = (bool) ($canManageEmployees ?? false);

$stateOptions = [
    '' => 'All',
    'open' => 'Open',
    'closed' => 'Closed',
];

?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= workspace_role() === 'punch_only' ? 'My Time' : 'Time Tracking' ?></h1>
        <p class="muted">Manual entry and punch clock</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (workspace_role() !== 'punch_only'): ?>
            <a class="btn btn-primary" href="<?= e(url('/time-tracking/create')) ?>"><i class="fas fa-plus me-2"></i>Add Time Entry</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/time-tracking/punch-board')) ?>"><i class="fas fa-user-clock me-2"></i><?= e($canManageEmployees ? 'Punch Board' : 'My Punch Clock') ?></a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Entries</span>
                <span class="record-value"><?= e((string) ((int) ($summary['entries'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Open Entries</span>
                <span class="record-value"><?= e((string) ((int) ($summary['open_entries'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Hours</span>
                <span class="record-value"><?= e(number_format((float) ($summary['hours'] ?? 0), 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/time-tracking')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="time-search">Search</label>
                <input id="time-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by employee, job, or entry id..." />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="time-state">State</label>
                <select id="time-state" class="form-select" name="state">
                    <?php foreach ($stateOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $state === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/time-tracking')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-clock me-2"></i>Time Entry List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($entries)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/time-tracking';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($entries === []): ?>
            <div class="record-empty">No time entries found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($entries as $entry): ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/time-tracking/' . (string) ((int) ($entry['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($entry['employee_name'] ?? '')) ?: ('Entry #' . (string) ((int) ($entry['id'] ?? 0)))) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-4">
                                <div class="record-field">
                                    <span class="record-label">Entry ID</span>
                                    <span class="record-value"><?= e((string) ((int) ($entry['id'] ?? 0))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Job</span>
                                    <span class="record-value"><?= e(trim((string) ($entry['job_title'] ?? '')) ?: 'Non-Job Time') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Clock In</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($entry['clock_in_at'] ?? null))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Clock Out</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($entry['clock_out_at'] ?? null))) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
