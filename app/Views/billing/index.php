<?php
$search = trim((string) ($search ?? ''));
$status = strtolower(trim((string) ($status ?? '')));
$sortBy = strtolower(trim((string) ($sortBy ?? 'date')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'desc')));
$dateFrom = trim((string) ($dateFrom ?? ''));
$dateTo = trim((string) ($dateTo ?? ''));
$invoices = is_array($invoices ?? null) ? $invoices : [];
$summary = is_array($summary ?? null) ? $summary : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($invoices), count($invoices));
$perPage = (int) ($pagination['per_page'] ?? 25);
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
if (!array_key_exists('', $statusOptions)) {
    $statusOptions = ['' => 'All'] + $statusOptions;
}

$billingTotalClass = static function (string $docType, string $status): string {
    $t = strtolower(trim($docType));
    if ($t === 'estimate') {
        return 'jt-billing-total jt-billing-total--estimate';
    }
    $s = strtolower(trim($status));
    if (in_array($s, ['paid_in_full', 'paid'], true)) {
        return 'jt-billing-total jt-billing-total--paid';
    }
    if (in_array($s, ['partially_paid', 'partial'], true)) {
        return 'jt-billing-total jt-billing-total--partial';
    }

    return 'jt-billing-total jt-billing-total--unpaid';
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Billing</h1>
        <p class="muted">View billing records</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/billing/deposits')) ?>">Bank deposits</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/export.csv')) ?>">Export CSV</a>
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
        <form method="get" action="<?= e(url('/billing')) ?>" class="d-flex flex-column gap-3">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="billing-search">Search</label>
                    <input id="billing-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Invoice #, client, job, status, or id…" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="billing-status">Status</label>
                    <select id="billing-status" class="form-select" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="billing-sort-by">Sort By</label>
                    <select id="billing-sort-by" class="form-select" name="sort_by">
                        <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Date</option>
                        <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                        <option value="client_name" <?= $sortBy === 'client_name' ? 'selected' : '' ?>>Client Name</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="billing-date-from">From</label>
                    <input id="billing-date-from" class="form-control" type="date" name="date_from" value="<?= e($dateFrom) ?>" />
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="billing-date-to">To</label>
                    <input id="billing-date-to" class="form-control" type="date" name="date_to" value="<?= e($dateTo) ?>" />
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label fw-semibold" for="billing-sort-dir">Sort Order</label>
                    <select id="billing-sort-dir" class="form-select" name="sort_dir">
                        <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                        <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                <div class="col-12 col-md-12 col-lg-3 d-grid d-md-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/billing')) ?>">Clear</a>
                </div>
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
                    <?php
                    $invType = strtolower(trim((string) ($invoice['type'] ?? 'invoice')));
                    $isEstimate = $invType === 'estimate';
                    $numRaw = trim((string) ($invoice['invoice_number'] ?? ''));
                    $numPart = $numRaw !== '' ? $numRaw : (string) ((int) ($invoice['id'] ?? 0));
                    $docPrefix = $isEstimate ? 'Estimate #' : 'Invoice #';
                    $titleLine = $docPrefix . $numPart;
                    $issueRaw = trim((string) ($invoice['issue_date'] ?? ''));
                    $displayDateRaw = $issueRaw !== '' ? $issueRaw : (string) ($invoice['created_at'] ?? '');
                    $dateLine = format_date($displayDateRaw !== '' ? $displayDateRaw : null);
                    $jobName = trim((string) ($invoice['job_name'] ?? ''));
                    $clientName = trim((string) ($invoice['client_name'] ?? ''));
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/billing/' . (string) ((int) ($invoice['id'] ?? 0)))) ?>">
                            <div class="record-row-main billing-record-head">
                                <h3 class="record-title-simple billing-record-title"><?= e($titleLine) ?></h3>
                                <span class="<?= e($billingTotalClass((string) ($invoice['type'] ?? 'invoice'), (string) ($invoice['status'] ?? ''))) ?>"><?= e(format_money_usd((float) ($invoice['total'] ?? 0))) ?></span>
                            </div>
                            <div class="record-subline billing-record-subline text-muted small">
                                <span class="billing-record-client"><?= e($clientName !== '' ? $clientName : '—') ?></span>
                                <span class="billing-record-date-sep" aria-hidden="true">·</span>
                                <span class="billing-record-job"><?= e($jobName !== '' ? $jobName : '—') ?></span>
                                <span class="billing-record-date-sep" aria-hidden="true">·</span>
                                <span class="billing-record-date"><?= e($dateLine) ?></span>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
