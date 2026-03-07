<?php
$sale = is_array($sale ?? null) ? $sale : [];
$saleId = (int) ($sale['id'] ?? 0);

$displayName = trim((string) ($sale['name'] ?? ''));
if ($displayName === '') {
    $displayName = 'Sale #' . (string) $saleId;
}

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
        <h1><?= e($displayName) ?></h1>
        <p class="muted">Sale Details</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url('/sales/' . (string) $saleId . '/edit')) ?>"><i class="fas fa-pen me-2"></i>Edit Sale</a>
        <form method="post" action="<?= e(url('/sales/' . (string) $saleId . '/delete')) ?>" onsubmit="return confirm('Delete this sale?');">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete Sale</button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= e(url('/sales')) ?>">Back to Sales</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-sack-dollar me-2"></i>Sale Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5">
            <div class="record-field">
                <span class="record-label">Sale ID</span>
                <span class="record-value"><?= e((string) $saleId) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Type</span>
                <span class="record-value"><?= e(trim((string) ($sale['sale_type'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Date</span>
                <span class="record-value"><?= e($formatSaleDate((string) ($sale['sale_date'] ?? ''))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Gross</span>
                <span class="record-value">$<?= e(number_format((float) ($sale['gross_amount'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net</span>
                <span class="record-value">$<?= e(number_format((float) ($sale['net_amount'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field record-field-full">
                <span class="record-label">Client</span>
                <?php if (((int) ($sale['client_id'] ?? 0)) > 0): ?>
                    <span class="record-value"><a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/clients/' . (string) ((int) ($sale['client_id'] ?? 0)))) ?>"><?= e(trim((string) ($sale['client_name'] ?? '')) ?: ('Client #' . (string) ((int) ($sale['client_id'] ?? 0)))) ?></a></span>
                <?php else: ?>
                    <span class="record-value">—</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-note-sticky me-2"></i>Note</strong>
    </div>
    <div class="card-body">
        <div class="record-value"><?= e(trim((string) ($sale['notes'] ?? '')) ?: '—') ?></div>
    </div>
</section>
