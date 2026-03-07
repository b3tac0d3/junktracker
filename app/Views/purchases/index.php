<?php
$search = trim((string) ($search ?? ''));
$status = trim((string) ($status ?? ''));
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$purchases = is_array($purchases ?? null) ? $purchases : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($purchases), count($purchases));
$perPage = (int) ($pagination['per_page'] ?? 25);

$formatDate = static function (?string $value): string {
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

$statusLabel = static function (string $value): string {
    if ($value === '') {
        return '—';
    }

    return ucwords(str_replace('_', ' ', $value));
};

$notePreview = static function (?string $value): string {
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return '—';
    }

    if (mb_strlen($text) <= 80) {
        return $text;
    }

    return mb_substr($text, 0, 77) . '...';
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Purchasing</h1>
        <p class="muted">Track clients selling inventory to your business.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/purchases/create')) ?>"><i class="fas fa-plus me-2"></i>Add Purchase Order</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5">
            <div class="record-field">
                <span class="record-label">Prospect</span>
                <span class="record-value"><?= e((string) ((int) ($summary['prospect'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Pending</span>
                <span class="record-value"><?= e((string) ((int) ($summary['pending'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Active</span>
                <span class="record-value"><?= e((string) ((int) ($summary['active'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Complete</span>
                <span class="record-value"><?= e((string) ((int) ($summary['complete'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Cancelled</span>
                <span class="record-value"><?= e((string) ((int) ($summary['cancelled'] ?? 0))) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/purchases')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="purchase-search">Search</label>
                <input id="purchase-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by title, client, notes, or id..." />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="purchase-status">Status</label>
                <select id="purchase-status" class="form-select" name="status">
                    <option value="">All</option>
                    <?php foreach ($statusOptions as $option): ?>
                        <option value="<?= e((string) $option) ?>" <?= $status === (string) $option ? 'selected' : '' ?>><?= e($statusLabel((string) $option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/purchases')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-cart-arrow-down me-2"></i>Purchase Orders</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($purchases)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/purchases';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($purchases === []): ?>
            <div class="record-empty">No purchase orders found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($purchases as $purchase): ?>
                    <?php $purchaseId = (int) ($purchase['id'] ?? 0); ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/purchases/' . (string) $purchaseId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($purchase['title'] ?? '')) !== '' ? (string) $purchase['title'] : ('Purchase #' . (string) $purchaseId)) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($statusLabel((string) ($purchase['status'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Client</span>
                                    <span class="record-value"><?= e(trim((string) ($purchase['client_name'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Contact Date</span>
                                    <span class="record-value"><?= e($formatDate((string) ($purchase['contact_date'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Purchase Date</span>
                                    <span class="record-value"><?= e($formatDate((string) ($purchase['purchase_date'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Note</span>
                                    <span class="record-value"><?= e($notePreview((string) ($purchase['notes'] ?? ''))) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
