<?php
$search = trim((string) ($search ?? ''));
$type = trim((string) ($type ?? ''));
$sortBy = strtolower(trim((string) ($sortBy ?? 'date')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'desc')));
$fromDate = trim((string) ($fromDate ?? date('Y-01-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$sales = is_array($sales ?? null) ? $sales : [];
$summary = is_array($summary ?? null) ? $summary : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($sales), count($sales));
$perPage = (int) ($pagination['per_page'] ?? 25);

$formatSaleDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '—';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return '—';
    }

    return date('m/d/Y', $ts);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Sales</h1>
        <p class="muted">Sales history and margin summary</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/sales/create')) ?>"><i class="fas fa-plus me-2"></i>Add Sale</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4">
            <div class="record-field">
                <span class="record-label">Gross MTD</span>
                <span class="record-value">$<?= e(number_format((float) ($summary['gross_mtd'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net MTD</span>
                <span class="record-value">$<?= e(number_format((float) ($summary['net_mtd'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Gross YTD</span>
                <span class="record-value">$<?= e(number_format((float) ($summary['gross_ytd'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net YTD</span>
                <span class="record-value">$<?= e(number_format((float) ($summary['net_ytd'] ?? 0), 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/sales')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sales-search">Search</label>
                <input id="sales-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by sale name, type, note, or id..." />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="sales-type">Type</label>
                <select id="sales-type" class="form-select" name="type">
                    <option value="">All</option>
                    <?php foreach ($typeOptions as $option): ?>
                        <option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label class="form-label fw-semibold" for="sales-from-date">From</label>
                <input id="sales-from-date" class="form-control" type="date" name="from" value="<?= e($fromDate) ?>" />
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label class="form-label fw-semibold" for="sales-to-date">To</label>
                <input id="sales-to-date" class="form-control" type="date" name="to" value="<?= e($toDate) ?>" />
            </div>
            <div class="col-12 col-md-3 col-lg-1">
                <label class="form-label fw-semibold" for="sales-sort-by">Sort By</label>
                <select id="sales-sort-by" class="form-select" name="sort_by">
                    <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Date</option>
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                    <option value="client_name" <?= $sortBy === 'client_name' ? 'selected' : '' ?>>Client Name</option>
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-1">
                <label class="form-label fw-semibold" for="sales-sort-dir">Sort Order</label>
                <select id="sales-sort-dir" class="form-select" name="sort_dir">
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/sales')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-dollar-sign me-2"></i>Sales List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($sales)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/sales';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($sales === []): ?>
            <div class="record-empty">No sales found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($sales as $sale): ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/sales/' . (string) ((int) ($sale['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($sale['name'] ?? '')) !== '' ? (string) $sale['name'] : ('Sale #' . (string) ((int) ($sale['id'] ?? 0)))) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">Date</span>
                                    <span class="record-value"><?= e($formatSaleDate((string) ($sale['sale_date'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Type</span>
                                    <span class="record-value"><?= e(trim((string) ($sale['sale_type'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Gross</span>
                                    <span class="record-value">$<?= e(number_format((float) ($sale['gross_amount'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Net</span>
                                    <span class="record-value">$<?= e(number_format((float) ($sale['net_amount'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Client</span>
                                    <span class="record-value"><?= e(trim((string) ($sale['client_name'] ?? '')) ?: '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
