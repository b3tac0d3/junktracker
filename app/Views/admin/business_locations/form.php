<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? '#');
$tableAvailable = (bool) ($tableAvailable ?? true);
$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Location' : 'Add Location') ?></h1>
        <p class="muted mb-0">Operating locations are separate from your base of operations on Business Details.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/business-locations')) ?>">Back to Locations</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-location-dot me-2"></i><?= e($mode === 'edit' ? 'Update Location' : 'Create Location') ?></strong>
    </div>
    <div class="card-body">
        <?php if (!$tableAvailable): ?>
            <div class="alert alert-warning">Business locations table is missing. Run migrations first.</div>
        <?php endif; ?>

        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="location-type">Designation</label>
                <select id="location-type" name="location_type" class="form-select <?= $hasError('location_type') ? 'is-invalid' : '' ?>" <?= !$tableAvailable ? 'disabled' : '' ?>>
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ((string) ($form['location_type'] ?? '')) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('location_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('location_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="location-name">Location Name</label>
                <input id="location-name" name="name" class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['name'] ?? '')) ?>" maxlength="150" placeholder="North warehouse, Main store..." <?= !$tableAvailable ? 'disabled' : '' ?> />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="location-address-line1">Address Line 1</label>
                <input id="location-address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" <?= !$tableAvailable ? 'disabled' : '' ?> />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="location-address-line2">Address Line 2</label>
                <input id="location-address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" <?= !$tableAvailable ? 'disabled' : '' ?> />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="location-city">City</label>
                <input id="location-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" <?= !$tableAvailable ? 'disabled' : '' ?> />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="location-state">State</label>
                <select id="location-state" name="state" class="form-select" <?= !$tableAvailable ? 'disabled' : '' ?>>
                    <option value="">—</option>
                    <?php foreach ($stateOptions as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $selectedState === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="location-postal">Postal Code</label>
                <input id="location-postal" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" <?= !$tableAvailable ? 'disabled' : '' ?> />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="location-sort">Sort</label>
                <input id="location-sort" name="sort_order" type="number" min="0" step="1" class="form-control <?= $hasError('sort_order') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['sort_order'] ?? '0')) ?>" <?= !$tableAvailable ? 'disabled' : '' ?> />
                <?php if ($hasError('sort_order')): ?><div class="invalid-feedback d-block"><?= e($fieldError('sort_order')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="location-phone">Phone</label>
                <input id="location-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" <?= !$tableAvailable ? 'disabled' : '' ?> />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="location-active">Status</label>
                <select id="location-active" name="is_active" class="form-select" <?= !$tableAvailable ? 'disabled' : '' ?>>
                    <option value="1" <?= ((string) ($form['is_active'] ?? '1')) === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ((string) ($form['is_active'] ?? '1')) === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="location-notes">Notes</label>
                <textarea id="location-notes" name="notes" rows="3" class="form-control" <?= !$tableAvailable ? 'disabled' : '' ?>><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit" <?= !$tableAvailable ? 'disabled' : '' ?>><?= e($mode === 'edit' ? 'Save Changes' : 'Add Location') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin/business-locations')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
