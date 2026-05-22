<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? 'dispatch')));
$sortBy = strtolower(trim((string) ($sortBy ?? 'date')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'desc')));
$fromDate = trim((string) ($fromDate ?? ''));
$toDate = trim((string) ($toDate ?? ''));
$estateSales = is_array($estateSales ?? null) ? $estateSales : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($estateSales), count($estateSales));
$perPage = (int) ($pagination['per_page'] ?? 25);
$statusOptionsRaw = is_array($statusOptions ?? null) ? $statusOptions : \App\Models\EstateSale::statusOptions(current_business_id());

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};

$filterStatusOptions = [
    'dispatch' => 'Upcoming (Scheduled + Active)',
    '' => 'All',
];
foreach ($statusOptionsRaw as $opt) {
    $opt = strtolower(trim((string) $opt));
    if ($opt === '' || array_key_exists($opt, $filterStatusOptions)) {
        continue;
    }
    $filterStatusOptions[$opt] = $statusLabel($opt);
}

$formatDt = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    return $ts === false ? '—' : date('m/d/Y g:i A', $ts);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Estate sales</h1>
        <p class="muted">Track sale events, locations, and customers on site</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/estate-sales/create')) ?>"><i class="fas fa-store me-2"></i>Add estate sale</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/estate-sales')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="estate-sales-search">Search</label>
                <input
                    id="estate-sales-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Title, address, city, or id..."
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="estate-sales-from">From</label>
                <input id="estate-sales-from" type="date" class="form-control" name="from" value="<?= e($fromDate) ?>" />
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="estate-sales-to">To</label>
                <input id="estate-sales-to" type="date" class="form-control" name="to" value="<?= e($toDate) ?>" />
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="estate-sales-status">Status</label>
                <select id="estate-sales-status" class="form-select" name="status">
                    <?php foreach ($filterStatusOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="estate-sales-sort-by">Sort by</label>
                <select id="estate-sales-sort-by" class="form-select" name="sort_by">
                    <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Start date</option>
                    <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="estate-sales-sort-dir">Order</label>
                <select id="estate-sales-sort-dir" class="form-select" name="sort_dir">
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/estate-sales')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-store me-2"></i>Estate sales</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($estateSales)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/estate-sales';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($estateSales === []): ?>
            <div class="record-empty">No estate sales match the current filters.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($estateSales as $row): ?>
                    <?php
                    $saleId = (int) ($row['id'] ?? 0);
                    $title = trim((string) ($row['title'] ?? '')) ?: ('Estate Sale #' . (string) $saleId);
                    $st = strtolower(trim((string) ($row['status'] ?? '')));
                    $customerCount = (int) ($row['customer_count'] ?? 0);
                    $addr = trim((string) ($row['address_line1'] ?? ''));
                    $citySt = trim(implode(', ', array_filter([
                        trim((string) ($row['city'] ?? '')),
                        trim((string) ($row['state'] ?? '')),
                    ], static fn (string $v): bool => $v !== '')));
                    $location = $addr !== '' ? $addr . ($citySt !== '' ? ' · ' . $citySt : '') : ($citySt !== '' ? $citySt : '—');
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/estate-sales/' . (string) $saleId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($title) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-3">
                                <div class="record-field">
                                    <span class="record-label">Start</span>
                                    <span class="record-value"><?= e($formatDt((string) ($row['start_at'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($statusLabel($st)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Customers</span>
                                    <span class="record-value"><?= e((string) $customerCount) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Location</span>
                                    <span class="record-value"><?= e($location) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
