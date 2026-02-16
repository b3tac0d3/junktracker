<?php
    $estate = $estate ?? [];
    $selectedClient = $selectedClient ?? null;
    $isEdit = !empty($estate['id']);

    $name = (string) old('name', $estate['name'] ?? '');
    $phone = (string) old('phone', $estate['phone'] ?? '');
    $email = (string) old('email', $estate['email'] ?? '');
    $address1 = (string) old('address_1', $estate['address_1'] ?? '');
    $address2 = (string) old('address_2', $estate['address_2'] ?? '');
    $city = (string) old('city', $estate['city'] ?? '');
    $state = (string) old('state', $estate['state'] ?? '');
    $zip = (string) old('zip', $estate['zip'] ?? '');
    $note = (string) old('note', $estate['note'] ?? '');

    $canTextValue = old('can_text', isset($estate['can_text']) ? (string) $estate['can_text'] : '0');
    $canTextChecked = $canTextValue === '1' || $canTextValue === 1 || $canTextValue === 'on';

    $activeValue = old('active', isset($estate['active']) ? (string) $estate['active'] : '1');
    $activeChecked = $activeValue === '1' || $activeValue === 1 || $activeValue === 'on';

    $clientId = (string) old('client_id', $selectedClient['id'] ?? $estate['client_id'] ?? '');

    $selectedClientName = '';
    if (isset($selectedClient['label']) && is_string($selectedClient['label'])) {
        $selectedClientName = $selectedClient['label'];
    } elseif (is_array($selectedClient)) {
        $selectedClientName = trim((string) (($selectedClient['first_name'] ?? '') . ' ' . ($selectedClient['last_name'] ?? '')));
        if ($selectedClientName === '' && isset($selectedClient['id'])) {
            $selectedClientName = 'Client #' . (string) $selectedClient['id'];
        }
    }
    $clientName = (string) old('client_name', $selectedClientName);
?>
<form method="post" action="<?= url($isEdit ? '/estates/' . ($estate['id'] ?? '') . '/edit' : '/estates/new') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="name">Estate Name</label>
            <input class="form-control" id="name" name="name" type="text" value="<?= e($name) ?>" required />
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" id="active" name="active" type="checkbox" value="1" <?= $activeChecked ? 'checked' : '' ?> />
                <label class="form-check-label" for="active">Active</label>
            </div>
        </div>

        <div class="col-md-8 position-relative">
            <label class="form-label" for="client_name">Primary Client</label>
            <input id="client_lookup_url" type="hidden" value="<?= e(url('/clients/lookup')) ?>" />
            <input id="client_id" name="client_id" type="hidden" value="<?= e($clientId) ?>" />
            <input
                class="form-control"
                id="client_name"
                name="client_name"
                type="text"
                value="<?= e($clientName) ?>"
                placeholder="Search client name..."
                autocomplete="off"
                required
            />
            <div id="client_name_suggestions" class="list-group position-absolute w-100 d-none" style="z-index: 1100;"></div>
            <div class="form-text">Required. Select from suggestions.</div>
        </div>
        <!-- <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" id="can_text" name="can_text" type="checkbox" value="1" <?= $canTextChecked ? 'checked' : '' ?> />
                <label class="form-check-label" for="can_text">Can Text</label>
            </div>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div> -->
        <div class="col-md-8">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>" />
        </div>

        <div class="col-md-5">
            <label class="form-label" for="address_1">Address 1</label>
            <input class="form-control" id="address_1" name="address_1" type="text" value="<?= e($address1) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="address_2">Address 2</label>
            <input class="form-control" id="address_2" name="address_2" type="text" value="<?= e($address2) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="city">City</label>
            <input class="form-control" id="city" name="city" type="text" value="<?= e($city) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="state">State</label>
            <input class="form-control" id="state" name="state" type="text" maxlength="2" value="<?= e($state) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="zip">Zip</label>
            <input class="form-control" id="zip" name="zip" type="text" value="<?= e($zip) ?>" />
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Estate' : 'Save Estate' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/estates/' . ($estate['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/estates') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
