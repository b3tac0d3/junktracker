<?php
    $consignor = $consignor ?? [];
    $isEdit = !empty($consignor['id']);

    $firstName = (string) old('first_name', $consignor['first_name'] ?? '');
    $lastName = (string) old('last_name', $consignor['last_name'] ?? '');
    $businessName = (string) old('business_name', $consignor['business_name'] ?? '');
    $phone = (string) old('phone', $consignor['phone'] ?? '');
    $email = (string) old('email', $consignor['email'] ?? '');
    $address1 = (string) old('address_1', $consignor['address_1'] ?? '');
    $address2 = (string) old('address_2', $consignor['address_2'] ?? '');
    $city = (string) old('city', $consignor['city'] ?? '');
    $state = (string) old('state', $consignor['state'] ?? '');
    $zip = (string) old('zip', $consignor['zip'] ?? '');
    $consignorNumber = (string) old('consignor_number', $consignor['consignor_number'] ?? '');
    $consignmentStartDate = (string) old('consignment_start_date', $consignor['consignment_start_date'] ?? '');
    $consignmentEndDate = (string) old('consignment_end_date', $consignor['consignment_end_date'] ?? '');
    $paymentSchedules = is_array($paymentSchedules ?? null) ? $paymentSchedules : ['monthly', 'quarterly', 'yearly'];
    $paymentSchedule = strtolower((string) old('payment_schedule', $consignor['payment_schedule'] ?? 'monthly'));
    if (!in_array($paymentSchedule, $paymentSchedules, true)) {
        $paymentSchedule = 'monthly';
    }
    $nextPaymentDueDate = (string) old('next_payment_due_date', $consignor['next_payment_due_date'] ?? '');
    $inventoryEstimateAmount = (string) old('inventory_estimate_amount', $consignor['inventory_estimate_amount'] ?? '');
    $inventoryDescription = (string) old('inventory_description', $consignor['inventory_description'] ?? '');
    $note = (string) old('note', $consignor['note'] ?? '');
?>
<form method="post" action="<?= url($isEdit ? '/consignors/' . ((int) ($consignor['id'] ?? 0)) . '/edit' : '/consignors/new') ?>">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="first_name">First Name</label>
            <input class="form-control" id="first_name" name="first_name" type="text" value="<?= e($firstName) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="last_name">Last Name</label>
            <input class="form-control" id="last_name" name="last_name" type="text" value="<?= e($lastName) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="business_name">Business Name</label>
            <input class="form-control" id="business_name" name="business_name" type="text" value="<?= e($businessName) ?>" />
        </div>

        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="consignor_number">Consignor Number</label>
            <input class="form-control" id="consignor_number" name="consignor_number" type="text" value="<?= e($consignorNumber) ?>" placeholder="Internal ID" />
        </div>
        <div class="col-md-4">
            <label class="form-label" for="inventory_estimate_amount">Inventory Estimate</label>
            <input class="form-control" id="inventory_estimate_amount" name="inventory_estimate_amount" type="number" min="0" step="0.01" value="<?= e($inventoryEstimateAmount) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="consignment_start_date">Consignment Start</label>
            <input class="form-control" id="consignment_start_date" name="consignment_start_date" type="date" value="<?= e($consignmentStartDate) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="consignment_end_date">Potential End</label>
            <input class="form-control" id="consignment_end_date" name="consignment_end_date" type="date" value="<?= e($consignmentEndDate) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="payment_schedule">Payment Schedule</label>
            <select class="form-select" id="payment_schedule" name="payment_schedule">
                <?php foreach ($paymentSchedules as $schedule): ?>
                    <option value="<?= e((string) $schedule) ?>" <?= $paymentSchedule === (string) $schedule ? 'selected' : '' ?>>
                        <?= e(ucfirst((string) $schedule)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="next_payment_due_date">Next Payment Due</label>
            <input class="form-control" id="next_payment_due_date" name="next_payment_due_date" type="date" value="<?= e($nextPaymentDueDate) ?>" />
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
            <label class="form-label" for="inventory_description">Inventory Description</label>
            <textarea class="form-control" id="inventory_description" name="inventory_description" rows="4" placeholder="Brief notes about expected inventory."><?= e($inventoryDescription) ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4" placeholder="Internal notes."><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Consignor' : 'Save Consignor' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/consignors/' . ((int) ($consignor['id'] ?? 0))) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/consignors') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
