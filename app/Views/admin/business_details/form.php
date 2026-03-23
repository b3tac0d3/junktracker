<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? url('/admin/business-details/update'));
$logoUrl = isset($logoUrl) ? $logoUrl : null;
$logoUrl = is_string($logoUrl) && $logoUrl !== '' ? $logoUrl : null;

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Business Details</h1>
        <p class="muted">Your internal business information</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Back to Admin</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-building me-2"></i>Business Profile</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12">
                <hr class="my-1" />
                <h3 class="h5 mb-2">Company logo</h3>
                <p class="small text-muted mb-2">Shown on estimates and invoices (print and future client-facing sends). PNG or JPG recommended; max 2&nbsp;MB.</p>
                <?php if ($logoUrl !== null): ?>
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                        <img src="<?= e($logoUrl) ?>" alt="Current logo" class="border rounded bg-white p-2" style="max-height: 96px; max-width: 240px; object-fit: contain;" />
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="remove_logo" value="1" id="business-remove-logo" />
                            <label class="form-check-label" for="business-remove-logo">Remove logo</label>
                        </div>
                    </div>
                <?php endif; ?>
                <label class="form-label fw-semibold" for="business-logo"><?= $logoUrl !== null ? 'Replace logo' : 'Upload logo' ?></label>
                <input id="business-logo" type="file" name="logo" class="form-control <?= isset($errors['logo']) ? 'is-invalid' : '' ?>" accept="image/png,image/jpeg,image/gif,image/webp" />
                <?php if (isset($errors['logo'])): ?><div class="invalid-feedback d-block"><?= e((string) $errors['logo']) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="business-name">Company Name</label>
                <input id="business-name" name="name" class="form-control <?= $hasError('name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['name'] ?? '')) ?>" maxlength="150" />
                <?php if ($hasError('name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('name')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="business-legal-name">Official Name</label>
                <input id="business-legal-name" name="legal_name" class="form-control" value="<?= e((string) ($form['legal_name'] ?? '')) ?>" maxlength="200" />
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="business-phone">Phone Number</label>
                <input id="business-phone" name="phone" class="form-control" value="<?= e((string) ($form['phone'] ?? '')) ?>" maxlength="40" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="business-primary-contact">Primary Contact</label>
                <input id="business-primary-contact" name="primary_contact_name" class="form-control" value="<?= e((string) ($form['primary_contact_name'] ?? '')) ?>" maxlength="190" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="business-ein">EIN Number</label>
                <input id="business-ein" name="ein_number" class="form-control" value="<?= e((string) ($form['ein_number'] ?? '')) ?>" maxlength="40" />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="business-website">Web Address</label>
                <input id="business-website" name="website_url" class="form-control <?= $hasError('website_url') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['website_url'] ?? '')) ?>" maxlength="255" placeholder="example.com" />
                <?php if ($hasError('website_url')): ?><div class="invalid-feedback d-block"><?= e($fieldError('website_url')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 mt-1">
                <hr class="my-1" />
                <h3 class="h5 mb-2">Financial Document Numbering</h3>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="business-estimate-number-start">Estimate Start Number</label>
                <input id="business-estimate-number-start" name="estimate_number_start" class="form-control <?= $hasError('estimate_number_start') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['estimate_number_start'] ?? '')) ?>" maxlength="30" placeholder="Example: 752" />
                <?php if ($hasError('estimate_number_start')): ?><div class="invalid-feedback d-block"><?= e($fieldError('estimate_number_start')) ?></div><?php endif; ?>
                <div class="form-text">If set to <code>752</code>, generated estimates will be <code>7521</code>, <code>7522</code>, and so on.</div>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="business-invoice-number-start">Invoice Start Number</label>
                <input id="business-invoice-number-start" name="invoice_number_start" class="form-control <?= $hasError('invoice_number_start') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['invoice_number_start'] ?? '')) ?>" maxlength="30" placeholder="Example: 900" />
                <?php if ($hasError('invoice_number_start')): ?><div class="invalid-feedback d-block"><?= e($fieldError('invoice_number_start')) ?></div><?php endif; ?>
                <div class="form-text">Uses a separate sequence from estimates.</div>
            </div>

            <div class="col-12 mt-1">
                <hr class="my-1" />
                <h3 class="h5 mb-2">Physical Address</h3>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="physical-address-line1">Address Line 1</label>
                <input id="physical-address-line1" name="address_line1" class="form-control js-physical-address" value="<?= e((string) ($form['address_line1'] ?? '')) ?>" maxlength="190" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="physical-address-line2">Address Line 2</label>
                <input id="physical-address-line2" name="address_line2" class="form-control js-physical-address" value="<?= e((string) ($form['address_line2'] ?? '')) ?>" maxlength="190" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="physical-city">City</label>
                <input id="physical-city" name="city" class="form-control js-physical-address" value="<?= e((string) ($form['city'] ?? '')) ?>" maxlength="120" />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="physical-state">State</label>
                <input id="physical-state" name="state" class="form-control js-physical-address" value="<?= e((string) ($form['state'] ?? '')) ?>" maxlength="60" />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="physical-postal">Postal Code</label>
                <input id="physical-postal" name="postal_code" class="form-control js-physical-address" value="<?= e((string) ($form['postal_code'] ?? '')) ?>" maxlength="30" />
            </div>
            <div class="col-12 mt-1">
                <hr class="my-1" />
                <div class="form-check">
                    <input id="mailing-same-as-physical" type="checkbox" class="form-check-input" name="mailing_same_as_physical" value="1" <?= ((string) ($form['mailing_same_as_physical'] ?? '1')) === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label fw-semibold" for="mailing-same-as-physical">Mailing address same as physical</label>
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="mailing-address-line1">Mailing Address Line 1</label>
                <input id="mailing-address-line1" name="mailing_address_line1" class="form-control js-mailing-address <?= $hasError('mailing_address_line1') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['mailing_address_line1'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('mailing_address_line1')): ?><div class="invalid-feedback d-block"><?= e($fieldError('mailing_address_line1')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="mailing-address-line2">Mailing Address Line 2</label>
                <input id="mailing-address-line2" name="mailing_address_line2" class="form-control js-mailing-address" value="<?= e((string) ($form['mailing_address_line2'] ?? '')) ?>" maxlength="190" />
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold" for="mailing-city">Mailing City</label>
                <input id="mailing-city" name="mailing_city" class="form-control js-mailing-address" value="<?= e((string) ($form['mailing_city'] ?? '')) ?>" maxlength="120" />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="mailing-state">Mailing State</label>
                <input id="mailing-state" name="mailing_state" class="form-control js-mailing-address" value="<?= e((string) ($form['mailing_state'] ?? '')) ?>" maxlength="60" />
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold" for="mailing-postal">Mailing Postal Code</label>
                <input id="mailing-postal" name="mailing_postal_code" class="form-control js-mailing-address" value="<?= e((string) ($form['mailing_postal_code'] ?? '')) ?>" maxlength="30" />
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save Business Details</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const sameCheckbox = document.getElementById('mailing-same-as-physical');
    const physicalFields = Array.from(document.querySelectorAll('.js-physical-address'));
    const mailingFields = Array.from(document.querySelectorAll('.js-mailing-address'));
    if (!sameCheckbox || physicalFields.length === 0 || mailingFields.length === 0) {
        return;
    }

    const mapping = [
        ['physical-address-line1', 'mailing-address-line1'],
        ['physical-address-line2', 'mailing-address-line2'],
        ['physical-city', 'mailing-city'],
        ['physical-state', 'mailing-state'],
        ['physical-postal', 'mailing-postal'],
    ];

    const copyPhysicalToMailing = () => {
        mapping.forEach(([fromId, toId]) => {
            const from = document.getElementById(fromId);
            const to = document.getElementById(toId);
            if (!from || !to) return;
            to.value = from.value;
        });
    };

    const syncMailingState = () => {
        const isSame = sameCheckbox.checked;
        if (isSame) {
            copyPhysicalToMailing();
        }
        mailingFields.forEach((field) => {
            field.readOnly = isSame;
            field.classList.toggle('bg-light', isSame);
        });
    };

    sameCheckbox.addEventListener('change', syncMailingState);
    physicalFields.forEach((field) => {
        field.addEventListener('input', () => {
            if (sameCheckbox.checked) {
                copyPhysicalToMailing();
            }
        });
    });

    syncMailingState();
});
</script>
