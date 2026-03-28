<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? '')));
$sortBy = strtolower(trim((string) ($sortBy ?? 'scheduled_at')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'asc')));
$deliveries = is_array($deliveries ?? null) ? $deliveries : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($deliveries), count($deliveries));
$perPage = (int) ($pagination['per_page'] ?? 25);
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : ['scheduled', 'completed', 'cancelled'];

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Client deliveries</h1>
        <p class="muted">Scheduled drop-offs and delivery windows by client</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/deliveries/create')) ?>"><i class="fas fa-truck me-2"></i>Add delivery</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/deliveries')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="deliveries-search">Search</label>
                <input
                    id="deliveries-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Client, address, notes, or id..."
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="deliveries-status">Status</label>
                <select id="deliveries-status" class="form-select" name="status">
                    <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($statusOptions as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($statusLabel($opt)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="deliveries-sort-by">Sort by</label>
                <select id="deliveries-sort-by" class="form-select" name="sort_by">
                    <option value="scheduled_at" <?= $sortBy === 'scheduled_at' ? 'selected' : '' ?>>Scheduled time</option>
                    <option value="client_name" <?= $sortBy === 'client_name' ? 'selected' : '' ?>>Client</option>
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-semibold" for="deliveries-sort-dir">Order</label>
                <select id="deliveries-sort-dir" class="form-select" name="sort_dir">
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/deliveries')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-truck me-2"></i>Deliveries</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($deliveries)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/deliveries';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($deliveries === []): ?>
            <div class="record-empty">No deliveries match the current filters.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($deliveries as $row): ?>
                    <?php
                    $did = (int) ($row['id'] ?? 0);
                    $clientName = trim((string) ($row['client_name'] ?? '')) ?: '—';
                    $when = trim((string) ($row['scheduled_at'] ?? ''));
                    $whenTs = $when !== '' ? strtotime($when) : false;
                    $whenDisplay = $whenTs === false ? '—' : date('m/d/Y g:i A', $whenTs);
                    $st = strtolower(trim((string) ($row['status'] ?? '')));
                    $addr = trim((string) ($row['address_line1'] ?? ''));
                    $citySt = trim(implode(', ', array_filter([
                        trim((string) ($row['city'] ?? '')),
                        trim((string) ($row['state'] ?? '')),
                    ], static fn (string $v): bool => $v !== '')));
                    $addrLine = $addr !== '' ? $addr . ($citySt !== '' ? ' · ' . $citySt : '') : ($citySt !== '' ? $citySt : '');
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/deliveries/' . (string) $did)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($clientName) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-3">
                                <div class="record-field">
                                    <span class="record-label">Scheduled</span>
                                    <span class="record-value"><?= e($whenDisplay) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($statusLabel($st)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Delivery address</span>
                                    <span class="record-value"><?= e($addrLine !== '' ? $addrLine : '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
