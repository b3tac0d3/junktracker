<?php
$business = is_array($business ?? null) ? $business : [];
$formValues = is_array($formValues ?? null) ? $formValues : [];
$isEdit = !empty($business);
$logoPath = (string) old('logo_path', $formValues['logo_path'] ?? ($business['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? url('/' . ltrim($logoPath, '/')) : '';
?>

<form method="post" action="<?= e((string) ($action ?? url('/site-admin/businesses'))) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="logo_path" value="<?= e($logoPath) ?>" />
    <input type="hidden" name="logo_mime_type" value="<?= e((string) old('logo_mime_type', $formValues['logo_mime_type'] ?? ($business['logo_mime_type'] ?? ''))) ?>" />

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Business Name</label>
            <input class="form-control" id="name" name="name" type="text" value="<?= e((string) old('name', $formValues['name'] ?? ($business['name'] ?? ''))) ?>" required />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="legal_name">Legal Name</label>
            <input class="form-control" id="legal_name" name="legal_name" type="text" value="<?= e((string) old('legal_name', $formValues['legal_name'] ?? ($business['legal_name'] ?? ''))) ?>" />
        </div>

        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e((string) old('email', $formValues['email'] ?? ($business['email'] ?? ''))) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e((string) old('phone', $formValues['phone'] ?? ($business['phone'] ?? ''))) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="website">Website</label>
            <input class="form-control" id="website" name="website" type="url" placeholder="https://..." value="<?= e((string) old('website', $formValues['website'] ?? ($business['website'] ?? ''))) ?>" />
        </div>

        <div class="col-md-6">
            <label class="form-label" for="address_line1">Address Line 1</label>
            <input class="form-control" id="address_line1" name="address_line1" type="text" value="<?= e((string) old('address_line1', $formValues['address_line1'] ?? ($business['address_line1'] ?? ''))) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="address_line2">Address Line 2</label>
            <input class="form-control" id="address_line2" name="address_line2" type="text" value="<?= e((string) old('address_line2', $formValues['address_line2'] ?? ($business['address_line2'] ?? ''))) ?>" />
        </div>

        <div class="col-md-3">
            <label class="form-label" for="city">City</label>
            <input class="form-control" id="city" name="city" type="text" value="<?= e((string) old('city', $formValues['city'] ?? ($business['city'] ?? ''))) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="state">State</label>
            <input class="form-control" id="state" name="state" type="text" value="<?= e((string) old('state', $formValues['state'] ?? ($business['state'] ?? ''))) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="postal_code">Postal Code</label>
            <input class="form-control" id="postal_code" name="postal_code" type="text" value="<?= e((string) old('postal_code', $formValues['postal_code'] ?? ($business['postal_code'] ?? ''))) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="country">Country</label>
            <input class="form-control" id="country" name="country" type="text" value="<?= e((string) old('country', $formValues['country'] ?? ($business['country'] ?? 'US'))) ?>" />
        </div>

        <div class="col-md-4">
            <label class="form-label" for="tax_id">Tax ID / EIN</label>
            <input class="form-control" id="tax_id" name="tax_id" type="text" value="<?= e((string) old('tax_id', $formValues['tax_id'] ?? ($business['tax_id'] ?? ''))) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="invoice_default_tax_rate">Default Invoice Tax Rate (%)</label>
            <input class="form-control" id="invoice_default_tax_rate" name="invoice_default_tax_rate" type="number" min="0" max="100" step="0.01" value="<?= e((string) old('invoice_default_tax_rate', $formValues['invoice_default_tax_rate'] ?? ($business['invoice_default_tax_rate'] ?? '0.00'))) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="timezone">Timezone</label>
            <input class="form-control" id="timezone" name="timezone" type="text" value="<?= e((string) old('timezone', $formValues['timezone'] ?? ($business['timezone'] ?? 'America/New_York'))) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="is_active">Status</label>
            <?php $activeInput = (int) old('is_active', $formValues['is_active'] ?? ($business['is_active'] ?? 1)); ?>
            <select class="form-select" id="is_active" name="is_active">
                <option value="1" <?= $activeInput === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $activeInput === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="logo_file">Business Logo</label>
            <input class="form-control" id="logo_file" name="logo_file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" />
            <div class="form-text">PNG, JPG, GIF, WEBP up to 4MB.</div>
        </div>
        <div class="col-md-6">
            <?php if ($logoUrl !== ''): ?>
                <div class="small text-muted mb-2">Current Logo</div>
                <img src="<?= e($logoUrl) ?>" alt="Business logo" style="max-height:64px; max-width:200px; border:1px solid #dbe4f0; border-radius:8px; padding:4px; background:#fff;" />
                <?php if ($isEdit): ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1" />
                        <label class="form-check-label" for="remove_logo">Remove current logo</label>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="small text-muted mt-4">No logo uploaded yet.</div>
            <?php endif; ?>
        </div>

        <?php if (!$isEdit): ?>
            <div class="col-12">
                <div class="form-check">
                    <?php $switchNow = (int) old('switch_now', 1); ?>
                    <input class="form-check-input" type="checkbox" id="switch_now" name="switch_now" value="1" <?= $switchNow === 1 ? 'checked' : '' ?> />
                    <label class="form-check-label" for="switch_now">Switch into this business immediately after create</label>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-3 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Business' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/site-admin/businesses/' . ((int) ($business['id'] ?? 0))) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/site-admin') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
