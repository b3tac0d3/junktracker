<?php
    $location = $location ?? [];
    $isEdit = !empty($location['id']);

    $name = (string) old('name', $location['name'] ?? '');
    $type = (string) old('type', $location['type'] ?? 'dump');
    $address1 = (string) old('address_1', $location['address_1'] ?? '');
    $address2 = (string) old('address_2', $location['address_2'] ?? '');
    $city = (string) old('city', $location['city'] ?? '');
    $state = (string) old('state', $location['state'] ?? '');
    $zip = (string) old('zip', $location['zip'] ?? '');
    $phone = (string) old('phone', $location['phone'] ?? '');
    $email = (string) old('email', $location['email'] ?? '');
    $note = (string) old('note', $location['note'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/admin/disposal-locations/' . ($location['id'] ?? '') . '/edit' : '/admin/disposal-locations/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Location Name</label>
            <input class="form-control" id="name" name="name" type="text" value="<?= e($name) ?>" required />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type" required>
                <option value="dump" <?= $type === 'dump' ? 'selected' : '' ?>>Dump</option>
                <option value="scrap" <?= $type === 'scrap' ? 'selected' : '' ?>>Scrap</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="zip">ZIP</label>
            <input class="form-control" id="zip" name="zip" type="text" value="<?= e($zip) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="address_1">Address</label>
            <input class="form-control" id="address_1" name="address_1" type="text" value="<?= e($address1) ?>" required />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="address_2">Address 2</label>
            <input class="form-control" id="address_2" name="address_2" type="text" value="<?= e($address2) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="city">City</label>
            <input class="form-control" id="city" name="city" type="text" value="<?= e($city) ?>" required />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="state">State</label>
            <input class="form-control text-uppercase" id="state" name="state" type="text" maxlength="2" value="<?= e($state) ?>" required />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>" />
        </div>
        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="3"><?= e($note) ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Location' : 'Save Location' ?></button>
        <a class="btn btn-outline-secondary" href="<?= url('/admin/disposal-locations') ?>">Cancel</a>
    </div>
</form>
