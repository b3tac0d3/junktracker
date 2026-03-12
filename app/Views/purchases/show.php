<?php
$purchase = is_array($purchase ?? null) ? $purchase : [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$linkedSales = is_array($linkedSales ?? null) ? $linkedSales : [];
$salesTotals = is_array($salesTotals ?? null) ? $salesTotals : [];
$purchaseProfit = (float) ($purchaseProfit ?? 0);
$purchaseId = (int) ($purchase['id'] ?? 0);

$displayTitle = trim((string) ($purchase['title'] ?? ''));
if ($displayTitle === '') {
    $displayTitle = 'Purchase #' . (string) $purchaseId;
}

$statusLabel = static function (string $value): string {
    if ($value === '') {
        return '—';
    }

    return ucwords(str_replace('_', ' ', $value));
};

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
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($displayTitle) ?></h1>
        <p class="muted">Purchase Order</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/purchases/' . (string) $purchaseId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Purchase</a>
        <form method="post" action="<?= e(url('/purchases/' . (string) $purchaseId . '/delete')) ?>" onsubmit="return confirm('Delete this purchase order?');">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= e(url('/purchases')) ?>">Back to Purchases</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-cart-arrow-down me-2"></i>Purchase Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5">
            <div class="record-field">
                <span class="record-label">Purchase ID</span>
                <span class="record-value"><?= e((string) $purchaseId) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Status</span>
                <span class="record-value"><?= e($statusLabel((string) ($purchase['status'] ?? ''))) ?></span>
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
                <span class="record-label">Client</span>
                <?php if (((int) ($purchase['client_id'] ?? 0)) > 0): ?>
                    <span class="record-value"><a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/clients/' . (string) ((int) ($purchase['client_id'] ?? 0)))) ?>"><?= e(trim((string) ($purchase['client_name'] ?? '')) ?: ('Client #' . (string) ((int) ($purchase['client_id'] ?? 0)))) ?></a></span>
                <?php else: ?>
                    <span class="record-value">—</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-chart-line me-2"></i>Purchase Financial Snapshot</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-4 record-row-fields-mobile-2">
            <div class="record-field">
                <span class="record-label">Purchase Price</span>
                <span class="record-value">$<?= e(number_format((float) ($purchase['purchase_price'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Linked Sales Gross</span>
                <span class="record-value">$<?= e(number_format((float) ($salesTotals['gross'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Linked Sales Net</span>
                <span class="record-value">$<?= e(number_format((float) ($salesTotals['net'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Profit (Net - Purchase Price)</span>
                <span class="record-value">$<?= e(number_format($purchaseProfit, 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-sack-dollar me-2"></i>Linked Sales</strong>
        <span class="small muted"><?= e((string) ((int) ($salesTotals['count'] ?? count($linkedSales)))) ?> linked sale(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php if ($linkedSales === []): ?>
            <div class="record-empty">No sales linked to this purchase yet.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($linkedSales as $sale): ?>
                    <?php
                    if (!is_array($sale)) {
                        continue;
                    }
                    $saleId = (int) ($sale['id'] ?? 0);
                    $saleTitle = trim((string) ($sale['name'] ?? ''));
                    if ($saleTitle === '') {
                        $saleTitle = 'Sale #' . (string) $saleId;
                    }
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($saleTitle) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-4">
                                <div class="record-field">
                                    <span class="record-label">Date</span>
                                    <span class="record-value"><?= e($formatDate((string) ($sale['sale_date'] ?? null))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Type</span>
                                    <span class="record-value"><?= e($statusLabel((string) ($sale['sale_type'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Gross</span>
                                    <span class="record-value">$<?= e(number_format((float) ($sale['gross_amount'] ?? 0), 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Net</span>
                                    <span class="record-value">$<?= e(number_format((float) ($sale['net_amount'] ?? 0), 2)) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-note-sticky me-2"></i>Note</strong>
    </div>
    <div class="card-body">
        <div class="record-value"><?= e(trim((string) ($purchase['notes'] ?? '')) ?: '—') ?></div>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-list-check me-2"></i>Follow-Up Tasks</strong>
        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/tasks?q=' . rawurlencode('purchase #' . (string) $purchaseId))) ?>">Open Tasks</a>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php if ($tasks === []): ?>
            <div class="record-empty">No follow-up tasks linked to this purchase.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($tasks as $task): ?>
                    <?php $taskId = (int) ($task['id'] ?? 0); ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/tasks/' . (string) $taskId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($task['title'] ?? '')) !== '' ? (string) $task['title'] : ('Task #' . (string) $taskId)) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-3">
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($statusLabel((string) ($task['status'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Due</span>
                                    <span class="record-value"><?= e(format_datetime((string) ($task['due_at'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Owner</span>
                                    <span class="record-value"><?= e(trim((string) ($task['owner_name'] ?? '')) ?: '—') ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
