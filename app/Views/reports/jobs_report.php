<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$jobs = is_array($jobs ?? null) ? $jobs : [];
$jobsTotalCount = (int) ($jobsTotalCount ?? 0);
$jobsListedCount = count($jobs);
$marginTotals = is_array($marginTotals ?? null) ? $marginTotals : [];

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

$formAction = url('/reports/jobs');
$resetHref = url('/reports/jobs');
?>

<div class="reports-shell">
    <div class="mb-2">
        <a class="small text-decoration-none fw-semibold" href="<?= e(url('/reports')) ?>"><i class="fas fa-arrow-left me-1"></i>All reports</a>
    </div>
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
        <div>
            <h1>Jobs (within range)</h1>
            <p class="muted">Jobs with activity scheduled or recorded in the selected period</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export/jobs') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate])) ?>">
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

    <section class="card index-card mb-3 reports-card-jobs-summary">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-briefcase me-2 jt-report-icon--jobs" aria-hidden="true"></i>Summary</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/jobs')) ?>">Open Jobs</a>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3 mb-md-2">Job-linked sales in the period (same rules as margin by job on the income report).</p>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Jobs in period</div>
                    <div class="fs-5 fw-semibold"><?= $jobsTotalCount ?></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Listed below</div>
                    <div class="fs-5 fw-semibold"><?= $jobsListedCount ?><?php if ($jobsTotalCount > $jobsListedCount): ?> <span class="small text-muted fw-normal">(list capped)</span><?php endif; ?></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Sales net (revenue)</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-in"><?= e($formatMoney((float) ($marginTotals['sales_net'] ?? 0))) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Purchase COGS</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney((float) ($marginTotals['purchase_cogs'] ?? 0))) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Margin (gross profit)</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-net"><?= e($formatMoney((float) ($marginTotals['margin'] ?? 0))) ?></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card index-card reports-card-jobs">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-briefcase me-2 jt-report-icon--jobs" aria-hidden="true"></i>Jobs in range</strong>
        </div>
        <div class="card-body">
            <?php if ($jobs === []): ?>
                <div class="record-empty">No jobs in this date range.</div>
            <?php else: ?>
                <div class="simple-list-table">
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $jobId = (int) ($job['id'] ?? 0);
                        $title = trim((string) ($job['title'] ?? '')) ?: ('Job #' . (string) $jobId);
                        $client = trim((string) ($job['client_name'] ?? '')) ?: '—';
                        $status = trim((string) ($job['status'] ?? ''));
                        $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '—';
                        $scheduled = $formatDate((string) ($job['scheduled_start_at'] ?? ''));
                        ?>
                        <a class="simple-list-row simple-list-row-link" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">
                            <span class="simple-list-title"><?= e($title) ?></span>
                            <span class="simple-list-meta"><?= e($client) ?> · <?= e($statusLabel) ?> · <?= e($scheduled) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
