<?php
    $settings = is_array($settings ?? null) ? $settings : [];
    $isReady = !empty($isReady);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Business Info</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Business Info</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Back to Admin</a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$isReady): ?>
        <div class="alert alert-warning">`app_settings` table is not available yet. Run migrations to enable persisted business settings.</div>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/business-info') ?>" class="card border-0 shadow-sm">
        <?= csrf_field() ?>
        <div class="card-header"><i class="fas fa-building me-1"></i>Business Profile</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="business_name">Business Name</label>
                    <input class="form-control" id="business_name" name="business_name" type="text" value="<?= e((string) old('business.name', $settings['business.name'] ?? '')) ?>" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="business_legal_name">Legal Name</label>
                    <input class="form-control" id="business_legal_name" name="business_legal_name" type="text" value="<?= e((string) old('business.legal_name', $settings['business.legal_name'] ?? '')) ?>" />
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="business_email">Email</label>
                    <input class="form-control" id="business_email" name="business_email" type="email" value="<?= e((string) old('business.email', $settings['business.email'] ?? '')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="business_phone">Phone</label>
                    <input class="form-control" id="business_phone" name="business_phone" type="text" value="<?= e((string) old('business.phone', $settings['business.phone'] ?? '')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="business_website">Website</label>
                    <input class="form-control" id="business_website" name="business_website" type="url" value="<?= e((string) old('business.website', $settings['business.website'] ?? '')) ?>" placeholder="https://..." />
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="business_address_line1">Address Line 1</label>
                    <input class="form-control" id="business_address_line1" name="business_address_line1" type="text" value="<?= e((string) old('business.address_line1', $settings['business.address_line1'] ?? '')) ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="business_address_line2">Address Line 2</label>
                    <input class="form-control" id="business_address_line2" name="business_address_line2" type="text" value="<?= e((string) old('business.address_line2', $settings['business.address_line2'] ?? '')) ?>" />
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="business_city">City</label>
                    <input class="form-control" id="business_city" name="business_city" type="text" value="<?= e((string) old('business.city', $settings['business.city'] ?? '')) ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="business_state">State</label>
                    <input class="form-control" id="business_state" name="business_state" type="text" value="<?= e((string) old('business.state', $settings['business.state'] ?? '')) ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="business_postal_code">Postal Code</label>
                    <input class="form-control" id="business_postal_code" name="business_postal_code" type="text" value="<?= e((string) old('business.postal_code', $settings['business.postal_code'] ?? '')) ?>" />
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="business_country">Country</label>
                    <input class="form-control" id="business_country" name="business_country" type="text" value="<?= e((string) old('business.country', $settings['business.country'] ?? 'US')) ?>" />
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="business_tax_id">Tax ID / EIN</label>
                    <input class="form-control" id="business_tax_id" name="business_tax_id" type="text" value="<?= e((string) old('business.tax_id', $settings['business.tax_id'] ?? '')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="business_timezone">Timezone</label>
                    <input class="form-control" id="business_timezone" name="business_timezone" type="text" value="<?= e((string) old('business.timezone', $settings['business.timezone'] ?? 'America/New_York')) ?>" />
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 mobile-two-col-buttons">
            <button class="btn btn-primary" type="submit" <?= !$isReady ? 'disabled' : '' ?>>Save Business Info</button>
            <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Cancel</a>
        </div>
    </form>
</div>

