<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? '')));
$invoices = is_array($invoices ?? null) ? $invoices : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($invoices), count($invoices));
$perPage = (int) ($pagination['per_page'] ?? 25);
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
if (!array_key_exists('', $statusOptions)) {
    $statusOptions = ['' => 'All'] + $statusOptions;
}

$statusLabelMap = [];
foreach ($statusOptions as $value => $label) {
    $key = strtolower(trim((string) $value));
    if ($key === '') {
        continue;
    }
    $statusLabelMap[$key] = (string) $label;
}

if (!isset($statusLabelMap['partial']) && isset($statusLabelMap['partially_paid'])) {
    $statusLabelMap['partial'] = $statusLabelMap['partially_paid'];
}
if (!isset($statusLabelMap['paid']) && isset($statusLabelMap['paid_in_full'])) {
    $statusLabelMap['paid'] = $statusLabelMap['paid_in_full'];
}

$statusLabel = static function (?string $value) use ($statusLabelMap): string {
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return '—';
    }

    if (isset($statusLabelMap[$normalized])) {
        return $statusLabelMap[$normalized];
    }

    return ucwords(str_replace('_', ' ', $normalized));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Billing</h1>
        <p class="muted">View billing records</p>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Open</span>
                <span class="record-value"><?= e((string) ((int) ($summary['open'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Paid</span>
                <span class="record-value"><?= e((string) ((int) ($summary['paid'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Total Records</span>
                <span class="record-value"><?= e((string) ((int) ($summary['total'] ?? 0))) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/billing')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="billing-search">Search</label>
                <input id="billing-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by invoice, client, status, or id..." />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="billing-status">Status</label>
                <select id="billing-status" class="form-select" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/billing')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-file-invoice-dollar me-2"></i>Billing Records</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($invoices)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/billing';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($invoices === []): ?>
            <div class="record-empty">No billing records found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($invoices as $invoice): ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/billing/' . (string) ((int) ($invoice['id'] ?? 0)))) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e(trim((string) ($invoice['invoice_number'] ?? '')) !== '' ? (string) $invoice['invoice_number'] : ('Invoice #' . (string) ((int) ($invoice['id'] ?? 0)))) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-4">
                                <div class="record-field">
                                    <span class="record-label">Type</span>
                                    <span class="record-value text-capitalize"><?= e((string) ($invoice['type'] ?? 'invoice')) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Status</span>
                                    <span class="record-value"><?= e($statusLabel((string) ($invoice['status'] ?? ''))) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Client</span>
                                    <span class="record-value"><?= e(trim((string) ($invoice['client_name'] ?? '')) ?: '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Total</span>
                                    <span class="record-value">$<?= e(number_format((float) ($invoice['total'] ?? 0), 2)) ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
