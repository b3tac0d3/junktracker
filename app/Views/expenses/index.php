<?php
$search = trim((string) ($search ?? ''));
$scope = strtolower(trim((string) ($scope ?? 'all')));
$sortBy = strtolower(trim((string) ($sortBy ?? 'date')));
$sortDir = strtolower(trim((string) ($sortDir ?? 'desc')));
if (!in_array($scope, ['all', 'general', 'job'], true)) {
    $scope = 'all';
}
$expenses = is_array($expenses ?? null) ? $expenses : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($expenses), count($expenses));
$perPage = (int) ($pagination['per_page'] ?? 25);

$scopeOptions = [
    'all' => 'All',
    'general' => 'General Only',
    'job' => 'Job Linked',
];
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Expenses</h1>
        <p class="muted">All expenses across the business</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= e(url('/expenses/create')) ?>"><i class="fas fa-plus me-2"></i>Add General Expense</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-filter me-2"></i>Filters</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/expenses')) ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="expenses-search">Search</label>
                <input
                    id="expenses-search"
                    class="form-control"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Search by category, note, reference, job, client, or id..."
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="expenses-scope">Scope</label>
                <select id="expenses-scope" class="form-select" name="scope">
                    <?php foreach ($scopeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $scope === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-1">
                <label class="form-label fw-semibold" for="expenses-sort-by">Sort By</label>
                <select id="expenses-sort-by" class="form-select" name="sort_by">
                    <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Date</option>
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
                    <option value="client_name" <?= $sortBy === 'client_name' ? 'selected' : '' ?>>Client Name</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-1">
                <label class="form-label fw-semibold" for="expenses-sort-dir">Sort Order</label>
                <select id="expenses-sort-dir" class="form-select" name="sort_dir">
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/expenses')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-receipt me-2"></i>Expense List</strong>
        <span class="small muted"><?= e((string) ((int) ($pagination['total_rows'] ?? count($expenses)))) ?> record(s)</span>
    </div>
    <div class="card-body p-2 p-lg-3">
        <?php
        $basePath = '/expenses';
        require base_path('app/Views/components/index_pagination.php');
        ?>
        <?php if ($expenses === []): ?>
            <div class="record-empty">No expenses found for the current filter.</div>
        <?php else: ?>
            <div class="record-list-simple">
                <?php foreach ($expenses as $expense): ?>
                    <?php
                    $expenseId = (int) ($expense['id'] ?? 0);
                    $dateRaw = trim((string) ($expense['expense_date'] ?? ''));
                    $dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
                    $dateDisplay = $dateTs === false ? '—' : date('m/d/Y', $dateTs);
                    $amount = (float) ($expense['amount'] ?? 0);
                    $category = trim((string) ($expense['category'] ?? ''));
                    $paymentMethod = trim((string) ($expense['payment_method'] ?? ''));
                    $note = trim((string) ($expense['note'] ?? ''));
                    $jobId = (int) ($expense['job_id'] ?? 0);
                    $jobTitle = trim((string) ($expense['job_title'] ?? ''));
                    $clientName = trim((string) ($expense['client_name'] ?? ''));
                    if ($jobTitle === '' && $jobId > 0) {
                        $jobTitle = 'Job #' . (string) $jobId;
                    }
                    $primaryTitle = $category !== '' ? $category : ($note !== '' ? $note : ('Expense #' . (string) $expenseId));
                    ?>
                    <article class="record-row-simple">
                        <a class="record-row-link" href="<?= e(url('/expenses/' . (string) $expenseId)) ?>">
                            <div class="record-row-main">
                                <h3 class="record-title-simple"><?= e($primaryTitle) ?></h3>
                            </div>
                            <div class="record-row-fields record-row-fields-5">
                                <div class="record-field">
                                    <span class="record-label">Date</span>
                                    <span class="record-value"><?= e($dateDisplay) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Amount</span>
                                    <span class="record-value fw-semibold">$<?= e(number_format($amount, 2)) ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Category</span>
                                    <span class="record-value"><?= e($category !== '' ? $category : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label">Payment</span>
                                    <span class="record-value"><?= e($paymentMethod !== '' ? $paymentMethod : '—') ?></span>
                                </div>
                                <div class="record-field">
                                    <span class="record-label"><?= e($jobId > 0 ? 'Job' : 'Scope') ?></span>
                                    <span class="record-value">
                                        <?php if ($jobId > 0): ?>
                                            <?= e($jobTitle) ?><?= $clientName !== '' ? e(' · ' . $clientName) : '' ?>
                                        <?php else: ?>
                                            General
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($note !== ''): ?>
                                <div class="record-subline small muted"><?= e($note) ?></div>
                            <?php endif; ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
