<?php
$search = trim((string) ($search ?? ''));
$sortBy = strtolower(trim((string) ($sortBy ?? 'date')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'desc')));
$fromDate = trim((string) ($fromDate ?? date('Y-01-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$records = is_array($records ?? null) ? $records : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($records), count($records));
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
        <h1>Estate Sale Records</h1>
        <p class="muted">On-site transactions from estate sales — separate from your main sales list</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/estate-sales')) ?>"><i class="fas fa-store me-2"></i>Estate Sales</a>
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
        <form method="get" action="<?= e(url('/estate-sale-records')) ?>" class="d-flex flex-column gap-3">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label fw-semibold" for="estate-sale-records-search">Search</label>
                    <input id="estate-sale-records-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by item, customer, estate sale, notes, or id…" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="estate-sale-records-sort-by">Sort By</label>
                    <select id="estate-sale-records-sort-by" class="form-select" name="sort_by">
                        <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Date</option>
                        <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                        <option value="estate_sale_title" <?= $sortBy === 'estate_sale_title' ? 'selected' : '' ?>>Estate Sale</option>
                        <option value="customer_name" <?= $sortBy === 'customer_name' ? 'selected' : '' ?>>Customer</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold" for="estate-sale-records-sort-dir">Sort Order</label>
                    <select id="estate-sale-records-sort-dir" class="form-select" name="sort_dir">
                        <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                        <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="estate-sale-records-from-date">From</label>
                    <input id="estate-sale-records-from-date" class="form-control" type="date" name="from" value="<?= e($fromDate) ?>" />
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="estate-sale-records-to-date">To</label>
                    <input id="estate-sale-records-to-date" class="form-control" type="date" name="to" value="<?= e($toDate) ?>" />
                </div>
                <div class="col-12 col-md-12 col-lg-6 d-grid d-md-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/estate-sale-records')) ?>">Clear</a>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-receipt me-2"></i>Records List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($records)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/estate-sale-records';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($records === []): ?>
            <div class="record-empty">No estate sale records found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($records as $record): ?>
                    <?php
                    $recordId = (int) ($record['id'] ?? 0);
                    $estateSaleId = (int) ($record['estate_sale_id'] ?? 0);
                    $recordUrl = url('/sales/' . (string) $recordId);
                    $estateSaleUrl = $estateSaleId > 0 ? url('/estate-sales/' . (string) $estateSaleId) : '';
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e($recordUrl) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($record['name'] ?? '')) !== '' ? (string) $record['name'] : ('Sale #' . (string) $recordId)) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">Date</span>
                                    <span class="record-value"><?= e($formatSaleDate((string) ($record['sale_date'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Gross</span>
                                    <span class="record-value">$<?= e(number_format((float) ($record['gross_amount'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Net</span>
                                    <span class="record-value"><?= ($record['net_amount'] ?? null) !== null ? '$' . e(number_format((float) $record['net_amount'], 2)) : '—' ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Estate Sale</span>
                                    <span class="record-value"><?= e(trim((string) ($record['estate_sale_title'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Customer</span>
                                    <span class="record-value"><?= e(trim((string) ($record['estate_customer_name'] ?? '')) ?: '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
