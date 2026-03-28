<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$invoicesList = is_array($invoicesList ?? null) ? $invoicesList : [];
$serviceTotals = is_array($serviceTotals ?? null) ? $serviceTotals : [];

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

$formAction = url('/reports/service');
$resetHref = url('/reports/service');
?>

<div class="reports-shell">
    <div class="mb-2">
        <a class="small text-decoration-none fw-semibold" href="<?= e(url('/reports')) ?>"><i class="fas fa-arrow-left me-1"></i>All reports</a>
    </div>
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
        <div>
            <h1>Service (within range)</h1>
            <p class="muted">Invoices with an issue or created date in the selected period</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export/service') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate])) ?>">
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

    <section class="card index-card mb-3 reports-card-service-summary">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-file-invoice-dollar me-2 jt-report-icon--service-list" aria-hidden="true"></i>Summary</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/billing')) ?>">Open Billing</a>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3 mb-md-2">Invoiced service: gross, job-linked expenses, and net (profit).</p>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Invoice count</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($serviceTotals['count'] ?? 0) ?></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Gross (invoiced)</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-in"><?= e($formatMoney((float) ($serviceTotals['gross'] ?? 0))) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Job expenses</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney((float) ($serviceTotals['job_expenses'] ?? 0))) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="jt-report-summary-label small">Net (service profit)</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-net"><?= e($formatMoney((float) ($serviceTotals['net'] ?? 0))) ?></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card index-card reports-card-service-list">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-file-invoice-dollar me-2 jt-report-icon--service-list" aria-hidden="true"></i>Invoices in range</strong>
        </div>
        <div class="card-body">
            <?php if ($invoicesList === []): ?>
                <div class="record-empty">No invoices in this date range.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($invoicesList as $inv): ?>
                        <?php
                        $iid = (int) ($inv['id'] ?? 0);
                        $num = trim((string) ($inv['invoice_number'] ?? '')) ?: ('Invoice #' . (string) $iid);
                        $client = trim((string) ($inv['client_name'] ?? '')) ?: '—';
                        $status = trim((string) ($inv['status'] ?? ''));
                        $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '—';
                        $issue = $formatDate((string) ($inv['issue_date'] ?? ''));
                        $total = $formatMoney((float) ($inv['total'] ?? 0));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/billing/' . (string) $iid)) ?>">
                            <span class="simple-list-title"><?= e($num) ?></span>
                            <span class="simple-list-meta">
                                <?= e($client) ?> · <?= e($statusLabel) ?> · <?= e($issue) ?> · <?= e($total) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
