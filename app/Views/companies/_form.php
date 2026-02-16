<?php
    $company = $company ?? [];
    $isEdit = !empty($company['id']);

    $name = (string) old('name', $company['name'] ?? '');
    $phone = (string) old('phone', $company['phone'] ?? '');
    $webAddress = (string) old('web_address', $company['web_address'] ?? '');
    $facebook = (string) old('facebook', $company['facebook'] ?? '');
    $instagram = (string) old('instagram', $company['instagram'] ?? '');
    $linkedin = (string) old('linkedin', $company['linkedin'] ?? '');
    $address1 = (string) old('address_1', $company['address_1'] ?? '');
    $address2 = (string) old('address_2', $company['address_2'] ?? '');
    $city = (string) old('city', $company['city'] ?? '');
    $state = (string) old('state', $company['state'] ?? '');
    $zip = (string) old('zip', $company['zip'] ?? '');
    $note = (string) old('note', $company['note'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/companies/' . ($company['id'] ?? '') . '/edit' : '/companies/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Company Name</label>
            <input class="form-control" id="name" name="name" type="text" value="<?= e($name) ?>" required />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="web_address">Website</label>
            <input class="form-control" id="web_address" name="web_address" type="text" value="<?= e($webAddress) ?>" placeholder="https://" />
        </div>

        <div class="col-md-4">
            <label class="form-label" for="address_1">Address 1</label>
            <input class="form-control" id="address_1" name="address_1" type="text" value="<?= e($address1) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="address_2">Address 2</label>
            <input class="form-control" id="address_2" name="address_2" type="text" value="<?= e($address2) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="city">City</label>
            <input class="form-control" id="city" name="city" type="text" value="<?= e($city) ?>" />
        </div>
        <div class="col-md-1">
            <label class="form-label" for="state">State</label>
            <input class="form-control" id="state" name="state" type="text" maxlength="2" value="<?= e($state) ?>" />
        </div>
        <div class="col-md-1">
            <label class="form-label" for="zip">Zip</label>
            <input class="form-control" id="zip" name="zip" type="text" value="<?= e($zip) ?>" />
        </div>

        <div class="col-md-4">
            <label class="form-label" for="facebook">Facebook</label>
            <input class="form-control" id="facebook" name="facebook" type="text" value="<?= e($facebook) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="instagram">Instagram</label>
            <input class="form-control" id="instagram" name="instagram" type="text" value="<?= e($instagram) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="linkedin">LinkedIn</label>
            <input class="form-control" id="linkedin" name="linkedin" type="text" value="<?= e($linkedin) ?>" />
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Company' : 'Save Company' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/companies/' . ($company['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/companies') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
