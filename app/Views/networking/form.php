<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/networking'));
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$selectedType = strtolower(trim((string) ($form['contact_type'] ?? '')));
$typeLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', trim($value)));
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Networking Contact' : 'Add Networking Contact') ?></h1>
        <p class="muted">Keep referral and partner details organized.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/networking')) ?>">Back to Networking</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-address-card me-2"></i><?= e($mode === 'edit' ? 'Update Contact' : 'Create Contact') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="networking-first-name">First Name</label>
                <input id="networking-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="networking-last-name">Last Name</label>
                <input id="networking-last-name" name="last_name" class="form-control <?= $hasError('last_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('last_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('last_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="networking-company">Company</label>
                <input id="networking-company" name="company" class="form-control" value="<?= e((string) ($form['company'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="networking-type">Type</label>
                <select id="networking-type" name="contact_type" class="form-select <?= $hasError('contact_type') ? 'is-invalid' : '' ?>">
                    <option value="">Select type...</option>
                    <?php foreach ($typeOptions as $option): ?>
                        <?php $value = strtolower(trim((string) $option)); ?>
                        <?php if ($value === '') continue; ?>
                        <option value="<?= e($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= e($typeLabel($value)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasError('contact_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('contact_type')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="networking-phone">Phone</label>
                <input id="networking-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="networking-email">Email</label>
                <input id="networking-email" type="email" name="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="networking-notes">Notes</label>
                <textarea id="networking-notes" name="notes" class="form-control" rows="4"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Contact') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/networking')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
