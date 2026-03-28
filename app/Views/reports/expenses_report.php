<?php
$fromDate = trim((string) ($fromDate ?? date('Y-m-01')));
$toDate = trim((string) ($toDate ?? date('Y-m-d')));
$expenseReport = is_array($expenseReport ?? null) ? $expenseReport : [];
$byCategory = is_array($expenseReport['by_category'] ?? null) ? $expenseReport['by_category'] : [];
$expenseCount = max(0, (int) ($expenseReport['count'] ?? 0));
$expenseTotalAmt = (float) ($expenseReport['total'] ?? 0);
$expenseAvg = $expenseCount > 0 ? round($expenseTotalAmt / $expenseCount, 2) : 0.0;

$formatMoney = static function ($value): string {
    return '$' . number_format((float) $value, 2);
};

$formAction = url('/reports/expenses');
$resetHref = url('/reports/expenses');
?>

<div class="reports-shell">
    <div class="mb-2">
        <a class="small text-decoration-none fw-semibold" href="<?= e(url('/reports')) ?>"><i class="fas fa-arrow-left me-1"></i>All reports</a>
    </div>
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
        <div>
            <h1>Expenses (within range)</h1>
            <p class="muted">Totals and category breakdown for the selected period</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/reports/export/expenses') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate])) ?>">
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

    <section class="card index-card mb-3 reports-card-expenses-summary">
        <div class="card-header index-card-header d-flex align-items-center justify-content-between">
            <strong><i class="fas fa-receipt me-2 jt-report-icon--expenses" aria-hidden="true"></i>Summary</strong>
            <a class="small text-decoration-none fw-semibold" href="<?= e(url('/expenses')) ?>">Open Expenses</a>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3 mb-md-2">Expenses reduce profit; totals are cash out / recognized cost in the period.</p>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Records</div>
                    <div class="fs-5 fw-semibold"><?= $expenseCount ?></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Job-linked <span class="text-muted fw-normal">(net)</span></div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney((float) ($expenseReport['job_total'] ?? 0))) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">General <span class="text-muted fw-normal">(net)</span></div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney((float) ($expenseReport['general_total'] ?? 0))) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Total expense <span class="text-muted fw-normal">(loss)</span></div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney($expenseTotalAmt)) ?></span></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="small text-muted">Avg per record</div>
                    <div class="fs-5 fw-semibold"><span class="jt-report-out"><?= e($formatMoney($expenseAvg)) ?></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card index-card reports-card-expenses-categories">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-tags me-2 jt-report-icon--expenses" aria-hidden="true"></i>By category</strong>
        </div>
        <div class="card-body">
            <?php if ($byCategory === []): ?>
                <div class="record-empty">No expenses in this date range.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Category</th>
                                <th scope="col" class="text-end">Records</th>
                                <th scope="col" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byCategory as $row): ?>
                                <?php if (!is_array($row)) {
                                    continue;
                                } ?>
                                <tr>
                                    <td><?= e(trim((string) ($row['category'] ?? 'Uncategorized')) ?: 'Uncategorized') ?></td>
                                    <td class="text-end"><?= (int) ($row['count'] ?? 0) ?></td>
                                    <td class="text-end"><?= e($formatMoney((float) ($row['total'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
