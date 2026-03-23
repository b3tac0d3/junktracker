<?php
$business = is_array($business ?? null) ? $business : [];
$businessId = (int) ($business['id'] ?? 0);
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$isCreate = (bool) ($isCreate ?? false);
$actionUrl = (string) ($actionUrl ?? url('/site-admin/businesses/' . (string) $businessId . '/update'));

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($isCreate ? 'Add Company' : 'Edit Company') ?></h1>
        <p class="muted mb-0">
            <?= e($isCreate ? 'Create a new company profile.' : (string) ($business['name'] ?? ('Business #' . (string) $businessId))) ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e($isCreate ? url('/site-admin/businesses') : url('/site-admin/businesses/' . (string) $businessId)) ?>">
            <?= e($isCreate ? 'Back to Businesses' : 'Back to Company') ?>
        </a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-pen me-2"></i>Primary Information</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="company-name">Company Name</label>
                <input id="company-name" name="name" class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['name'] ?? '')) ?>" maxlength="150" />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="company-legal-name">Official Name</label>
                <input id="company-legal-name" name="legal_name" class="form-control" value="<?= e((string) ($form['legal_name'] ?? '')) ?>" maxlength="200" />
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="company-email">Email</label>
                <input id="company-email" type="email" name="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="company-phone">Phone</label>
                <input id="company-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>
            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="company-address-1">Address Line 1</label>
                <input id="company-address-1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="company-address-2">Address Line 2</label>
                <input id="company-address-2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="company-city">City</label>
                <input id="company-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="company-state">State</label>
                <input id="company-state" name="state" class="form-control" value="<?= e((string) ($form['state'] ?? '')) ?>" maxlength="60" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="company-postal-code">Postal Code</label>
                <input id="company-postal-code" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><?= e($isCreate ? 'Create Company' : 'Save Company') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($isCreate ? url('/site-admin/businesses') : url('/site-admin/businesses/' . (string) $businessId)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
