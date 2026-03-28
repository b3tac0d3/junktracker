<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$mode = (string) ($mode ?? 'create');
$actionUrl = (string) ($actionUrl ?? url('/clients'));
$hasClientType = (bool) ($hasClientType ?? false);
$hasNewsletter = (bool) ($hasNewsletter ?? false);
$clientTypeOptions = is_array($clientTypeOptions ?? null) ? $clientTypeOptions : ['client', 'company', 'realtor', 'other'];
$clientId = (int) ($clientId ?? 0);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$stateOptions = us_state_options();
$selectedState = strtoupper(trim((string) ($form['state'] ?? '')));
if ($selectedState !== '' && !array_key_exists($selectedState, $stateOptions)) {
    $stateOptions[$selectedState] = $selectedState;
}
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($mode === 'edit' ? 'Edit Client' : 'Add Client') ?></h1>
        <p class="muted">Contact details and client classification</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients')) ?>">Back to Clients</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-plus me-2"></i><?= e($mode === 'edit' ? 'Update Client' : 'Create Client') ?></strong>
    </div>
    <div class="card-body">
        <div id="client-duplicate-alert" class="alert alert-warning alert-persistent alert-dismissible d-none" role="status" aria-live="polite"></div>
        <form
            id="client-form"
            method="post"
            action="<?= e($actionUrl) ?>"
            class="row g-3"
            data-check-url="<?= e(url('/clients/check-duplicates')) ?>"
            data-clients-base="<?= e(url('/clients')) ?>"
            data-client-id="<?= (string) $clientId ?>"
        >
            <?= csrf_field() ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-first-name">First Name</label>
                <input id="client-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-last-name">Last Name</label>
                <input id="client-last-name" name="last_name" class="form-control" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
            </div>

            <?php if ($hasClientType): ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="client-type">Client Type</label>
                    <select id="client-type" name="client_type" class="form-select <?= $hasError('client_type') ? 'is-invalid' : '' ?>">
                        <?php foreach ($clientTypeOptions as $optionRaw): ?>
                            <?php
                            $option = strtolower(trim((string) $optionRaw));
                            if ($option === '') {
                                continue;
                            }
                            $label = ucwords(str_replace('_', ' ', $option));
                            ?>
                            <option value="<?= e($option) ?>" <?= ((string) ($form['client_type'] ?? 'client')) === $option ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasError('client_type')): ?><div class="invalid-feedback d-block"><?= e($fieldError('client_type')) ?></div><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="client-company-name">Company Name</label>
                    <input id="client-company-name" name="company_name" class="form-control" value="<?= e((string) ($form['company_name'] ?? '')) ?>" maxlength="150" />
                </div>
            <?php endif; ?>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-email">Email</label>
                <input id="client-email" name="email" type="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <?php if ($hasClientType): ?>
                <div class="col-12 col-lg-8">
                    <label class="form-label fw-semibold" for="client-company-name">Company Name</label>
                    <input id="client-company-name" name="company_name" class="form-control" value="<?= e((string) ($form['company_name'] ?? '')) ?>" maxlength="150" />
                </div>
            <?php endif; ?>

            <?php if ($hasNewsletter): ?>
                <div class="col-12 col-lg-8">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="client-newsletter" name="newsletter_subscribed" value="1" <?= ((string) ($form['newsletter_subscribed'] ?? '0')) === '1' ? 'checked' : '' ?> />
                        <label class="form-check-label fw-semibold" for="client-newsletter">Subscribe to newsletter</label>
                    </div>
                    <div class="form-text">When you send email campaigns, include clients who opted in here. A unique unsubscribe token is stored for each subscriber.</div>
                </div>
            <?php endif; ?>

            <?php if (!$hasClientType): ?>
                <input type="hidden" name="client_type" value="<?= e((string) ($form['client_type'] ?? 'client')) ?>" />
            <?php endif; ?>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-phone">Primary Phone</label>
                <input id="client-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="client-can-text" name="can_text" <?= ((string) ($form['can_text'] ?? '0')) === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label fw-semibold text-nowrap" for="client-can-text">Can Text</label>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="client-secondary-phone">Secondary Phone</label>
                <input id="client-secondary-phone" name="secondary_phone" class="form-control" value="<?= e((string) ($form['secondary_phone'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12 col-lg-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="client-secondary-can-text" name="secondary_can_text" <?= ((string) ($form['secondary_can_text'] ?? '0')) === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label fw-semibold text-nowrap" for="client-secondary-can-text">Can Text</label>
                </div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="client-address-line1">Address Line 1</label>
                <input id="client-address-line1" name="address_line1" class="form-control" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="client-address-line2">Address Line 2</label>
                <input id="client-address-line2" name="address_line2" class="form-control" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="client-city">City</label>
                <input id="client-city" name="city" class="form-control" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold" for="client-state">State</label>
                <select id="client-state" name="state" class="form-select">
                    <?php foreach ($stateOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedState === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold" for="client-postal-code">Postal Code</label>
                <input id="client-postal-code" name="postal_code" class="form-control" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="client-primary-note">Primary Note</label>
                <textarea id="client-primary-note" name="primary_note" class="form-control" rows="4"><?= e((string) ($form['primary_note'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e($mode === 'edit' ? 'Save Changes' : 'Create Client') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e($mode === 'edit' && isset($clientId) ? url('/clients/' . (string) ((int) $clientId)) : url('/clients')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
