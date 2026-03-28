<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$salesList = is_array($salesList ?? null) ? $salesList : [];
$salesTotals = is_array($salesTotals ?? null) ? $salesTotals : [];
$salesGross = (float) ($salesTotals['gross'] ?? 0);
$salesNet = (float) ($salesTotals['net'] ?? 0);
$salesGrossVsNet = round($salesGross - $salesNet, 2);

$formatMoney = static function ($value): string {
    return '$' . number_format((float) $value, 2);
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

$formAction = url('/reports/sales');
$resetHref = url('/reports/sales');
?>

<div class="reports-shell">
    <div class="mb-2">
        <a class="small text-decoration-none fw-semibold" href="<?= e(url('/reports')) ?>"><i class="fas fa-arrow-left me-1"></i>All reports</a>
    </div>
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
        <div>
            <h1>Sales (within range)</h1>
            <p class="muted">Individual sales with a date in the selected period</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export/sales') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate])) ?>">
            <i class="fas fa-download me-1" aria-hidden="true"></i>Download CSV
        </a>
    </div>

    <section class="card index-card mb-3 reports-card-period">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-filter me-2 jt-report-icon--period" aria-hidden="true"></i>Time Period</strong>
        </div>
        <div class="card-body">
            <?php require base_path('app/Views/reports/partials/period_form.php'); ?>
        </div>
    </section>

    <section class="card index-card mb-3 reports-card-sales-summary">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-sack-dollar me-2 jt-report-icon--sales-list" aria-hidden="true"></i>Summary</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/sales')) ?>">Open Sales</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Sales count</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($salesTotals['count'] ?? 0) ?></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Gross</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-in"><?= e($formatMoney($salesGross)) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Net</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-in"><?= e($formatMoney($salesNet)) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Gross − net <span class="fw-normal opacity-75">(fees, discounts)</span></div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney($salesGrossVsNet)) ?></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card index-card reports-card-sales-list">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-cash-register me-2 jt-report-icon--sales-list" aria-hidden="true"></i>Sales in range</strong>
        </div>
        <div class="card-body">
            <?php if ($salesList === []): ?>
                <div class="record-empty">No sales in this date range.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($salesList as $sale): ?>
                        <?php
                        $saleId = (int) ($sale['id'] ?? 0);
                        $name = trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . (string) $saleId);
                        $type = trim((string) ($sale['type'] ?? ''));
                        $typeLabel = $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : '—';
                        $date = $formatDate((string) ($sale['sale_date'] ?? ''));
                        $gross = $formatMoney((float) ($sale['gross_amount'] ?? 0));
                        $net = $formatMoney((float) ($sale['net_amount'] ?? 0));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                            <span class="simple-list-title"><?= e($name) ?></span>
                            <span class="simple-list-meta">
                                <span class="jt-report-muted"><?= e($typeLabel) ?> · <?= e($date) ?> ·</span>
                                <span class="jt-report-muted">Gross</span> <span class="jt-report-in"><?= e($gross) ?></span>
                                <span class="jt-report-muted">·</span>
                                <span class="jt-report-muted">Net</span> <span class="jt-report-in"><?= e($net) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
