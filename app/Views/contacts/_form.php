<?php
    $contact = $contact ?? [];
    $contactTypeOptions = is_array($contactTypeOptions ?? null) ? $contactTypeOptions : [];
    $isEdit = !empty($contact['id']);
    $canCreateClient = !empty($canCreateClient);

    $contactType = (string) old('contact_type', $contact['contact_type'] ?? 'general');
    if (!array_key_exists($contactType, $contactTypeOptions) && !empty($contactTypeOptions)) {
        $contactType = (string) array_key_first($contactTypeOptions);
    }

    $firstName = (string) old('first_name', $contact['first_name'] ?? '');
    $lastName = (string) old('last_name', $contact['last_name'] ?? '');
    $displayName = (string) old('display_name', $contact['display_name'] ?? '');
    $phone = (string) old('phone', $contact['phone'] ?? '');
    $email = (string) old('email', $contact['email'] ?? '');
    $address1 = (string) old('address_1', $contact['address_1'] ?? '');
    $address2 = (string) old('address_2', $contact['address_2'] ?? '');
    $city = (string) old('city', $contact['city'] ?? '');
    $state = (string) old('state', $contact['state'] ?? '');
    $zip = (string) old('zip', $contact['zip'] ?? '');
    $note = (string) old('note', $contact['note'] ?? '');
    $companyId = (string) old('company_id', (string) ($contact['company_id'] ?? ''));
    $companyName = (string) old('company_name', $contact['company_name'] ?? '');
    $linkedClientId = (string) old('linked_client_id', (string) ($contact['linked_client_id'] ?? ''));
    $activeValue = old('is_active', isset($contact['is_active']) ? (string) $contact['is_active'] : '1');
    $activeChecked = $activeValue === '1' || $activeValue === 'on';
?>
<form method="post" action="<?= url($isEdit ? '/network/' . ($contact['id'] ?? '') . '/edit' : '/network/new') ?>">
    <?= csrf_field() ?>
    <input id="contact_company_lookup_url" type="hidden" value="<?= e(url('/network/lookup/companies')) ?>" />
    <input id="company_id" name="company_id" type="hidden" value="<?= e($companyId) ?>" />
    <input id="linked_client_id" name="linked_client_id" type="hidden" value="<?= e($linkedClientId) ?>" />

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="contact_type">Network Type</label>
            <select class="form-select" id="contact_type" name="contact_type">
                <?php foreach ($contactTypeOptions as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= $contactType === (string) $value ? 'selected' : '' ?>>
                        <?= e((string) $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="display_name">Display Name</label>
            <input class="form-control" id="display_name" name="display_name" type="text" value="<?= e($displayName) ?>" />
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" <?= $activeChecked ? 'checked' : '' ?> />
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="first_name">First Name</label>
            <input class="form-control" id="first_name" name="first_name" type="text" value="<?= e($firstName) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="last_name">Last Name</label>
            <input class="form-control" id="last_name" name="last_name" type="text" value="<?= e($lastName) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div>

        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>" />
        </div>
        <div class="col-md-6 position-relative">
            <label class="form-label" for="company_name">Company</label>
            <input class="form-control" id="company_name" name="company_name" type="text" value="<?= e($companyName) ?>" placeholder="Search company..." autocomplete="off" />
            <div id="company_name_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
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

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>

        <?php if (!$isEdit && $canCreateClient): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" id="create_client_now" name="create_client_now" type="checkbox" value="1" <?= old('create_client_now') === '1' ? 'checked' : '' ?> />
                    <label class="form-check-label" for="create_client_now">
                        Also create this network client as a client
                    </label>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Network Client' : 'Save Network Client' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/network/' . ($contact['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/network') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
