<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? '#');
$tableAvailable = (bool) ($tableAvailable ?? true);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Invoice Item Type' : 'Add Invoice Item Type') ?></h1>
        <p class="muted">Reusable defaults for invoice and estimate line items.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/invoice-item-types')) ?>">Back to Item Types</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen-to-square me-2"></i><?= e($mode === 'edit' ? 'Update Item Type' : 'Create Item Type') ?></strong>
    </div>
    <div class="card-body">
        <?php if (!$tableAvailable): ?>
            <div class="alert alert-warning">Invoice item types table is missing. Run migrations first.</div>
        <?php endif; ?>

        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="item-type-name">Name</label>
                <input id="item-type-name" name="name" class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['name'] ?? '')) ?>" maxlength="90" <?= !$tableAvailable ? 'disabled' : '' ?> />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="item-type-rate">Default Price / Unit</label>
                <input id="item-type-rate" name="default_unit_price" type="number" min="0" step="0.01" class="form-control <?= $hasError('default_unit_price') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['default_unit_price'] ?? '0.00')) ?>" <?= !$tableAvailable ? 'disabled' : '' ?> />
                <?php if ($hasError('default_unit_price')): ?><div class="invalid-feedback d-block"><?= e($fieldError('default_unit_price')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="item-type-sort-order">Sort Order</label>
                <input id="item-type-sort-order" name="sort_order" type="number" min="0" step="1" class="form-control <?= $hasError('sort_order') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['sort_order'] ?? '100')) ?>" <?= !$tableAvailable ? 'disabled' : '' ?> />
                <?php if ($hasError('sort_order')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sort_order')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="item-type-taxable">Taxable By Default</label>
                <select id="item-type-taxable" name="default_taxable" class="form-select" <?= !$tableAvailable ? 'disabled' : '' ?>>
                    <option value="1" <?= ((string) ($form['default_taxable'] ?? '1')) === '1' ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ((string) ($form['default_taxable'] ?? '1')) === '0' ? 'selected' : '' ?>>No</option>
                </select>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="item-type-active">Status</label>
                <select id="item-type-active" name="is_active" class="form-select" <?= !$tableAvailable ? 'disabled' : '' ?>>
                    <option value="1" <?= ((string) ($form['is_active'] ?? '1')) === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ((string) ($form['is_active'] ?? '1')) === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="item-type-note">Default Note</label>
                <textarea id="item-type-note" name="default_note" rows="3" class="form-control" <?= !$tableAvailable ? 'disabled' : '' ?>><?= e((string) ($form['default_note'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit" <?= !$tableAvailable ? 'disabled' : '' ?>><?= e($mode === 'edit' ? 'Save Changes' : 'Add Item Type') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin/invoice-item-types')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
