<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/subs'));
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$selectedStatus = strtolower(trim((string) ($form['status'] ?? 'active')));
$hasAddressFields = (bool) ($hasAddressFields ?? \App\Models\Subcontractor::hasAddressFields());
$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Sub-Contractor' : 'Add Sub-Contractor') ?></h1>
        <p class="muted">People you send work to when you sub out a job.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/subs')) ?>">Back to Sub-Contractors</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-hard-hat me-2"></i><?= e($mode === 'edit' ? 'Update Sub-Contractor' : 'Create Sub-Contractor') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-first-name">First Name</label>
                <input id="sub-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-last-name">Last Name</label>
                <input id="sub-last-name" name="last_name" class="form-control" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-company">Company</label>
                <input id="sub-company" name="company" class="form-control" value="<?= e((string) ($form['company'] ?? '')) ?>" maxlength="190" />
            </div>

            <?php if ($hasAddressFields): ?>
            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sub-address-line1">Address Line 1</label>
                <input id="sub-address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sub-address-line2">Address Line 2</label>
                <input id="sub-address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="sub-city">City</label>
                <input id="sub-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold" for="sub-state">State</label>
                <select id="sub-state" name="state" class="form-select">
                    <?php foreach ($stateOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedState === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold" for="sub-postal-code">Postal Code</label>
                <input id="sub-postal-code" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12"><hr class="my-1"></div>
            <?php endif; ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-status">Status</label>
                <select id="sub-status" name="status" class="form-select <?= $hasError('status') ? 'is-invalid' : '' ?>">
                    <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <?php if ($hasError('status')): ?><div class="invalid-feedback d-block"><?= e($fieldError('status')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-phone">Phone</label>
                <input id="sub-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="sub-email">Email</label>
                <input id="sub-email" type="email" name="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="sub-notes">Notes</label>
                <textarea id="sub-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Sub-Contractor') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/subs')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
