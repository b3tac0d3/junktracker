<?php
    $business = is_array($business ?? null) ? $business : [];
    $formValues = is_array($formValues ?? null) ? $formValues : [];
    $isEdit = !empty($business);
?>

<form method="post" action="<?= e((string) ($action ?? url('/site-admin/businesses'))) ?>">
    <?= csrf_field() ?>
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
        <div class="col-md-3">
            <label class="form-label" for="is_active">Status</label>
            <?php $activeInput = (int) old('is_active', $formValues['is_active'] ?? ($business['is_active'] ?? 1)); ?>
            <select class="form-select" id="is_active" name="is_active">
                <option value="1" <?= $activeInput === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $activeInput === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <?php if (!$isEdit): ?>
            <div class="col-md-9 d-flex align-items-end">
                <div class="form-check">
                    <?php $switchNow = (int) old('switch_now', 1); ?>
                    <input class="form-check-input" type="checkbox" id="switch_now" name="switch_now" value="1" <?= $switchNow === 1 ? 'checked' : '' ?> />
                    <label class="form-check-label" for="switch_now">
                        Switch into this business immediately after create
                    </label>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="mt-3 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit">
            <i class="fas fa-save me-1"></i>
            <?= $isEdit ? 'Save Changes' : 'Create Business' ?>
        </button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/site-admin/businesses/' . ((int) ($business['id'] ?? 0))) ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
