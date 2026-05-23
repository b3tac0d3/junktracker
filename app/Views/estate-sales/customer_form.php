<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$estateSale = is_array($estateSale ?? null) ? $estateSale : [];
$customer = is_array($customer ?? null) ? $customer : [];

$estateSaleId = (int) ($estateSale['id'] ?? 0);
$customerId = (int) ($customer['id'] ?? 0);
$estateSaleTitle = trim((string) ($estateSale['title'] ?? '')) ?: ('Estate Sale #' . (string) $estateSaleId);
$customerName = \App\Models\EstateSale::customerDisplayName($customer);
$backUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId);
$actionUrl = url('/estate-sales/' . (string) $estateSaleId . '/customers/' . (string) $customerId . '/update');

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
        <h1>Edit Customer</h1>
        <p class="muted mb-0"><?= e($customerName) ?> · <?= e($estateSaleTitle) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Back to Customer</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-pen me-2"></i>Customer Details</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-first-name">First name</label>
                <input
                    id="estate-sale-customer-edit-first-name"
                    name="first_name"
                    class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>"
                    maxlength="90"
                    value="<?= e((string) ($form['first_name'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-last-name">Last name</label>
                <input
                    id="estate-sale-customer-edit-last-name"
                    name="last_name"
                    class="form-control <?= $hasError('last_name') ? 'is-invalid' : '' ?>"
                    maxlength="90"
                    value="<?= e((string) ($form['last_name'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('last_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('last_name')) ?></div><?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-email">Email</label>
                <input
                    id="estate-sale-customer-edit-email"
                    name="email"
                    type="email"
                    class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>"
                    maxlength="190"
                    value="<?= e((string) ($form['email'] ?? '')) ?>"
                    autocomplete="off"
                />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-phone">Phone</label>
                <input
                    id="estate-sale-customer-edit-phone"
                    name="phone"
                    class="form-control"
                    maxlength="40"
                    value="<?= e((string) ($form['phone'] ?? '')) ?>"
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-city">City</label>
                <input
                    id="estate-sale-customer-edit-city"
                    name="city"
                    class="form-control"
                    maxlength="120"
                    value="<?= e((string) ($form['city'] ?? '')) ?>"
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="estate-sale-customer-edit-state">State</label>
                <select id="estate-sale-customer-edit-state" name="state" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($stateOptions as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $selectedState === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
