<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$purchasesList = is_array($purchasesList ?? null) ? $purchasesList : [];
$purchaseTotals = is_array($purchaseTotals ?? null) ? $purchaseTotals : [];

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

$formAction = url('/reports/purchases');
$resetHref = url('/reports/purchases');
?>

<div class="reports-shell">
    <div class="mb-2">
        <a class="small text-decoration-none fw-semibold" href="<?= e(url('/reports')) ?>"><i class="fas fa-arrow-left me-1"></i>All reports</a>
    </div>
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
        <div>
            <h1>Purchases (within range)</h1>
            <p class="muted">Purchases with a date in the selected period</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export/purchases') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate])) ?>">
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

    <section class="card index-card mb-3 reports-card-purchases-summary">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-cart-arrow-down me-2 jt-report-icon--purchases-list" aria-hidden="true"></i>Summary</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/purchases')) ?>">Open Purchases</a>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3 mb-md-2">Purchase cost in the period (inventory / COGS).</p>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Purchase count</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($purchaseTotals['count'] ?? 0) ?></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Total cost</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney((float) ($purchaseTotals['total'] ?? 0))) ?></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card index-card reports-card-purchases-list">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-cart-arrow-down me-2 jt-report-icon--purchases-list" aria-hidden="true"></i>Purchases in range</strong>
        </div>
        <div class="card-body">
            <?php if ($purchasesList === []): ?>
                <div class="record-empty">No purchases in this date range.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($purchasesList as $purchase): ?>
                        <?php
                        $pid = (int) ($purchase['id'] ?? 0);
                        $title = trim((string) ($purchase['title'] ?? '')) ?: ('Purchase #' . (string) $pid);
                        $client = trim((string) ($purchase['client_name'] ?? '')) ?: '—';
                        $status = trim((string) ($purchase['status'] ?? ''));
                        $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '—';
                        $date = $formatDate((string) ($purchase['purchase_date'] ?? ''));
                        $price = $formatMoney((float) ($purchase['purchase_price'] ?? 0));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/purchases/' . (string) $pid)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta">
                                <?= e($client) ?> · <?= e($statusLabel) ?> · <?= e($date) ?> · <?= e($price) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
