<?php
    $expense = $expense ?? [];
    $categories = $categories ?? [];

    $jobId = (string) old('job_id', isset($expense['job_id']) ? (string) $expense['job_id'] : '');
    $jobSearch = (string) old('job_search', $expense['job_name'] ?? '');
    $categoryId = (string) old('expense_category_id', isset($expense['expense_category_id']) ? (string) $expense['expense_category_id'] : '');
    $expenseDate = (string) old('expense_date', $expense['expense_date'] ?? date('Y-m-d'));
    $amount = (string) old('amount', isset($expense['amount']) ? (string) $expense['amount'] : '');
    $description = (string) old('description', $expense['description'] ?? '');
?>
<form method="post" action="<?= url('/expenses/new') ?>">
    <?= csrf_field() ?>
    <input id="expense_job_lookup_url" type="hidden" value="<?= e(url('/expenses/lookup/jobs')) ?>" />

    <div class="row g-3">
        <div class="col-md-6 position-relative">
            <label class="form-label" for="job_search">Linked Job (Optional)</label>
            <input id="job_id" name="job_id" type="hidden" value="<?= e($jobId) ?>" />
            <input
                class="form-control"
                id="job_search"
                name="job_search"
                type="text"
                autocomplete="off"
                placeholder="Search jobs..."
                value="<?= e($jobSearch) ?>"
            />
            <div id="expense_job_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
            <div class="form-text">Leave blank to keep this expense unlinked.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="expense_date">Expense Date</label>
            <input class="form-control" id="expense_date" name="expense_date" type="date" value="<?= e($expenseDate) ?>" required />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="amount">Amount</label>
            <input class="form-control" id="amount" name="amount" type="number" step="0.01" min="0" value="<?= e($amount) ?>" required />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="expense_category_id">Category</label>
            <select class="form-select" id="expense_category_id" name="expense_category_id" required>
                <option value="">Select category...</option>
                <?php foreach ($categories as $category): ?>
                    <?php $id = (string) ((int) ($category['id'] ?? 0)); ?>
                    <option value="<?= e($id) ?>" <?= $categoryId === $id ? 'selected' : '' ?>>
                        <?= e((string) ($category['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="description">Description</label>
            <input class="form-control" id="description" name="description" type="text" value="<?= e($description) ?>" />
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit">Save Expense</button>
        <a class="btn btn-outline-secondary" href="<?= url('/expenses') ?>">Cancel</a>
    </div>
</form>
