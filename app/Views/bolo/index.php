<?php
$search = trim((string) ($search ?? ''));
$sortBy = strtolower(trim((string) ($sortBy ?? 'name')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'asc')));
$status = strtolower(trim((string) ($status ?? 'active')));
$rows = is_array($rows ?? null) ? $rows : [];
$hasActiveFlag = (bool) ($hasActiveFlag ?? false);
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($rows), count($rows));
$perPage = (int) ($pagination['per_page'] ?? 25);

$displayName = static function (array $row): string {
    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }

    $company = trim((string) ($row['company_name'] ?? ''));
    if ($company !== '') {
        return $company;
    }

    return 'Client #' . (string) ((int) ($row['client_id'] ?? 0));
};

$previewLines = static function (array $row): string {
    $raw = trim((string) ($row['lines_concat'] ?? ''));
    if ($raw === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($raw) > 220) {
        return mb_substr($raw, 0, 217) . '…';
    }
    if (strlen($raw) > 220) {
        return substr($raw, 0, 217) . '…';
    }

    return $raw;
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>BOLO list</h1>
        <p class="muted">Buyer wants: search line items, notes, or client name</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients')) ?>">Clients</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/bolo')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="bolo-search">Search</label>
                <input
                    id="bolo-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Line items, notes, or client name..."
                    autocomplete="off"
                />
            </div>
            <?php if ($hasActiveFlag): ?>
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label fw-semibold" for="bolo-status">Status</label>
                    <select id="bolo-status" class="form-select" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="bolo-sort-by">Sort by</label>
                <select id="bolo-sort-by" class="form-select" name="sort_by">
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Client name</option>
                    <option value="updated" <?= $sortBy === 'updated' ? 'selected' : '' ?>>Updated</option>
                    <option value="client_id" <?= $sortBy === 'client_id' ? 'selected' : '' ?>>Client ID</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="bolo-sort-dir">Order</label>
                <select id="bolo-sort-dir" class="form-select" name="sort_dir">
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                </select>
            </div>
            <div class="col-12 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary" type="submit">Apply</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/bolo')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-binoculars me-2"></i>BOLO profiles</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($rows)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/bolo';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($rows === []): ?>
            <div class="record-empty">No BOLO profiles match the current filters.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($rows as $row): ?>
                    <?php if (!is_array($row)) {
                        continue;
                    } ?>
                    <?php
                    $cid = (int) ($row['client_id'] ?? 0);
                    $isRowActive = !$hasActiveFlag || (int) ($row['is_active'] ?? 1) === 1;
                    $notesPreview = trim((string) ($row['notes'] ?? ''));
                    $linesPrev = $previewLines($row);
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/clients/' . (string) $cid)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($displayName($row)) ?></h3>
                                <?php if ($hasActiveFlag && !$isRowActive): ?>
                                    <span class="badge text-bg-secondary">BOLO inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="muted small">
                                Client #<?= e((string) $cid) ?>
                                <?php if (!empty($row['updated_at'])): ?>
                                    · Updated <?= e(format_datetime((string) $row['updated_at'])) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($linesPrev !== ''): ?>
                                <div class="mt-1 small"><?= e($linesPrev) ?></div>
                            <?php endif; ?>
                            <?php if ($notesPreview !== ''): ?>
                                <div class="mt-1 small"><span class="fw-semibold">Notes:</span> <?= e($notesPreview) ?></div>
                            <?php endif; ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
