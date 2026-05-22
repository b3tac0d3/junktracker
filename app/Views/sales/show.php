<?php
$sale = is_array($sale ?? null) ? $sale : [];
$saleId = (int) ($sale['id'] ?? 0);
$estateSaleId = (int) ($sale['estate_sale_id'] ?? 0);
$saleEditUrl = $estateSaleId > 0
    ? url('/estate-sales/' . (string) $estateSaleId . '/sales/' . (string) $saleId . '/edit')
    : url('/sales/' . (string) $saleId . '/edit');
$backUrl = $estateSaleId > 0
    ? url('/estate-sale-records')
    : url('/sales');
$backLabel = $estateSaleId > 0 ? 'Back to Estate Sale Records' : 'Back to Sales';

$displayName = trim((string) ($sale['name'] ?? ''));
if ($displayName === '') {
    $displayName = 'Sale #' . (string) $saleId;
}

$grossAmt = (float) ($sale['gross_amount'] ?? 0);
$netAmt = (float) ($sale['net_amount'] ?? 0);

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
    <div class="jt-page-header-actions d-grid gap-2 d-md-flex d-md-flex-wrap justify-content-md-end align-items-md-center">
        <div class="dropdown w-100 w-md-auto">
            <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e($saleEditUrl) ?>"><i class="fas fa-pen me-2"></i>Edit Sale</a></li>
                <li>
                    <form method="post" action="<?= e(url('/sales/' . (string) $saleId . '/delete')) ?>" class="m-0" onsubmit="return confirm('Delete this sale?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete Sale</button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary w-100 w-md-auto" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
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
                <span class="record-value">$<?= e(number_format($grossAmt, 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net</span>
                <span class="record-value">$<?= e(number_format($netAmt, 2)) ?></span>
            </div>
        </div>
        <div class="record-row-fields record-row-fields-3 mt-3">
            <div class="record-field record-field-full">
                <span class="record-label">Client</span>
                <?php if (((int) ($sale['client_id'] ?? 0)) > 0): ?>
                    <span class="record-value"><a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/clients/' . (string) ((int) ($sale['client_id'] ?? 0)))) ?>"><?= e(trim((string) ($sale['client_name'] ?? '')) ?: ('Client #' . (string) ((int) ($sale['client_id'] ?? 0)))) ?></a></span>
                <?php else: ?>
                    <span class="record-value">—</span>
                <?php endif; ?>
            </div>
            <div class="record-field">
                <span class="record-label">Linked Job</span>
                <?php if (((int) ($sale['job_id'] ?? 0)) > 0): ?>
                    <span class="record-value"><a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/jobs/' . (string) ((int) ($sale['job_id'] ?? 0)))) ?>"><?= e(trim((string) ($sale['job_title'] ?? '')) ?: ('Job #' . (string) ((int) ($sale['job_id'] ?? 0)))) ?></a></span>
                <?php else: ?>
                    <span class="record-value">—</span>
                <?php endif; ?>
            </div>
            <div class="record-field">
                <span class="record-label">Linked Purchase</span>
                <?php if (((int) ($sale['purchase_id'] ?? 0)) > 0): ?>
                    <span class="record-value"><a class="link-gray-dark fw-semibold text-decoration-none" href="<?= e(url('/purchases/' . (string) ((int) ($sale['purchase_id'] ?? 0)))) ?>"><?= e(trim((string) ($sale['purchase_title'] ?? '')) ?: ('Purchase #' . (string) ((int) ($sale['purchase_id'] ?? 0)))) ?></a></span>
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
