<?php
    $summary = $summary ?? [];
    $filters = $filters ?? [];
    $categories = $categories ?? [];
    $expenses = $expenses ?? [];
    $byJob = $byJob ?? [];
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Expenses</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Expenses</li>
            </ol>
        </div>
        <a class="btn btn-primary" href="<?= url('/expenses/new') ?>">
            <i class="fas fa-plus me-1"></i>
            Add Expense
        </a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Total Expenses</div>
                    <div class="h4 mb-1 text-danger">
                        <?= e('$' . number_format((float) ($summary['total_amount'] ?? 0), 2)) ?>
                    </div>
                    <div class="small text-muted"><?= e((string) ((int) ($summary['expense_count'] ?? 0))) ?> records</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Job-Linked Expenses</div>
                    <div class="h4 mb-1 text-primary">
                        <?= e('$' . number_format((float) ($summary['linked_amount'] ?? 0), 2)) ?>
                    </div>
                    <div class="small text-muted">Assigned to jobs</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase small text-muted">Unlinked Expenses</div>
                    <div class="h4 mb-1 text-warning">
                        <?= e('$' . number_format((float) ($summary['unlinked_amount'] ?? 0), 2)) ?>
                    </div>
                    <div class="small text-muted">Not tied to a job</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= url('/expenses') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input
                                class="form-control"
                                type="text"
                                name="q"
                                placeholder="Search description, category, job..."
                                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                            />
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category_id">
                            <option value="">All</option>
                            <?php foreach ($categories as $category): ?>
                                <?php $categoryId = (string) ((int) ($category['id'] ?? 0)); ?>
                                <option value="<?= e($categoryId) ?>" <?= (string) ($filters['category_id'] ?? '') === $categoryId ? 'selected' : '' ?>>
                                    <?= e((string) ($category['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Link</label>
                        <select class="form-select" name="job_link">
                            <option value="all" <?= ($filters['job_link'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="linked" <?= ($filters['job_link'] ?? '') === 'linked' ? 'selected' : '' ?>>Job Linked</option>
                            <option value="unlinked" <?= ($filters['job_link'] ?? '') === 'unlinked' ? 'selected' : '' ?>>Unlinked</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Record</label>
                        <select class="form-select" name="record_status">
                            <option value="active" <?= ($filters['record_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deleted" <?= ($filters['record_status'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= ($filters['record_status'] ?? '') === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-1">
                        <label class="form-label">Start</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e((string) ($filters['start_date'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 col-lg-1">
                        <label class="form-label">End</label>
                        <input class="form-control" type="date" name="end_date" value="<?= e((string) ($filters['end_date'] ?? '')) ?>" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a class="btn btn-outline-secondary" href="<?= url('/expenses') ?>">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-receipt me-1"></i>
            Expense Log
        </div>
        <div class="card-body">
            <?php if (empty($expenses)): ?>
                <div class="text-muted">No expenses found for this filter set.</div>
            <?php else: ?>
                <table id="expensesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Job</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <?php
                                $jobId = isset($expense['job_id']) ? (int) $expense['job_id'] : 0;
                                $jobName = trim((string) ($expense['job_name'] ?? ''));
                                $categoryLabel = trim((string) ($expense['category_label'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e((string) ($expense['id'] ?? '')) ?></td>
                                <td><?= e(format_date($expense['expense_date'] ?? null)) ?></td>
                                <td><?= e($categoryLabel !== '' ? $categoryLabel : '—') ?></td>
                                <td><?= e((string) (($expense['description'] ?? '') !== '' ? $expense['description'] : '—')) ?></td>
                                <td>
                                    <?php if ($jobId > 0): ?>
                                        <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                            <?= e($jobName !== '' ? $jobName : ('Job #' . $jobId)) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unlinked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-danger"><?= e('$' . number_format((float) ($expense['amount'] ?? 0), 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-layer-group me-1"></i>
            Expenses by Job
        </div>
        <div class="card-body">
            <?php if (empty($byJob)): ?>
                <div class="text-muted">No job-linked expenses in this range.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Entries</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byJob as $row): ?>
                                <?php $jobId = (int) ($row['job_id'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <?php if ($jobId > 0): ?>
                                            <a class="text-decoration-none" href="<?= url('/jobs/' . $jobId) ?>">
                                                <?= e((string) (($row['job_name'] ?? '') !== '' ? $row['job_name'] : ('Job #' . $jobId))) ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) ((int) ($row['expense_count'] ?? 0))) ?></td>
                                    <td class="text-end text-danger"><?= e('$' . number_format((float) ($row['total_amount'] ?? 0), 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
