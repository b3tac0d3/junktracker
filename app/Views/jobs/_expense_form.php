<?php
    $expense = $expense ?? [];
    $categories = $categories ?? [];
    $isEdit = !empty($expense['id']);

    $expenseDate = (string) old('expense_date', $expense['expense_date'] ?? '');
    $categoryId = (string) old('expense_category_id', $expense['expense_category_id'] ?? '');
    $legacyCategory = (string) ($expense['category'] ?? '');
    $description = (string) old('description', $expense['description'] ?? '');
    $amount = (string) old('amount', isset($expense['amount']) ? (string) $expense['amount'] : '');
?>
<form method="post" action="<?= url($isEdit ? '/jobs/' . ($job['id'] ?? '') . '/expenses/' . ($expense['id'] ?? '') . '/edit' : '/jobs/' . ($job['id'] ?? '') . '/expenses/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="expense_date">Expense Date</label>
            <input class="form-control" id="expense_date" name="expense_date" type="date" value="<?= e($expenseDate) ?>" required />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="expense_category_id">Category</label>
            <select class="form-select" id="expense_category_id" name="expense_category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category): ?>
                    <?php $optionId = (string) ($category['id'] ?? ''); ?>
                    <option value="<?= e($optionId) ?>" <?= $categoryId !== '' && $categoryId === $optionId ? 'selected' : '' ?>>
                        <?= e((string) ($category['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($categoryId === '' && $legacyCategory !== ''): ?>
                <div class="form-text">Existing value: <?= e($legacyCategory) ?>. Please choose a category.</div>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="amount">Amount</label>
            <input class="form-control" id="amount" name="amount" type="number" step="0.01" value="<?= e($amount) ?>" />
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= e($description) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Expense' : 'Save Expense' ?></button>
        <a class="btn btn-outline-secondary" href="<?= url('/jobs/' . ($job['id'] ?? '')) ?>">Cancel</a>
    </div>
</form>
