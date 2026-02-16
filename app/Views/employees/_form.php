<?php
    $employee = $employee ?? [];
    $isEdit = !empty($employee['id']);

    $firstName = (string) old('first_name', $employee['first_name'] ?? '');
    $lastName = (string) old('last_name', $employee['last_name'] ?? '');
    $phone = (string) old('phone', $employee['phone'] ?? '');
    $email = (string) old('email', $employee['email'] ?? '');
    $hireDate = (string) old('hire_date', $employee['hire_date'] ?? '');
    $fireDate = (string) old('fire_date', $employee['fire_date'] ?? '');
    $wageType = (string) old('wage_type', $employee['wage_type'] ?? 'hourly');
    if (!in_array($wageType, ['hourly', 'salary'], true)) {
        $wageType = 'hourly';
    }

    $defaultPayRate = $employee['hourly_rate'] ?? ($employee['wage_rate'] ?? '');
    $payRate = (string) old('pay_rate', (string) $defaultPayRate);
    $note = (string) old('note', $employee['note'] ?? '');

    $activeValue = old('active', isset($employee['active']) ? (string) $employee['active'] : '1');
    $activeChecked = $activeValue === '1' || $activeValue === 1 || $activeValue === 'on';
?>
<form method="post" action="<?= url($isEdit ? '/employees/' . ($employee['id'] ?? '') . '/edit' : '/employees/new') ?>">
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
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" type="text" value="<?= e($phone) ?>" />
        </div>

        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="hire_date">Hire Date</label>
            <input class="form-control" id="hire_date" name="hire_date" type="date" value="<?= e($hireDate) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="fire_date">Fire Date</label>
            <input class="form-control" id="fire_date" name="fire_date" type="date" value="<?= e($fireDate) ?>" />
        </div>
        <div class="col-md-2">
            <label class="form-label" for="wage_type">Wage Type</label>
            <select class="form-select" id="wage_type" name="wage_type">
                <option value="hourly" <?= $wageType === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                <option value="salary" <?= $wageType === 'salary' ? 'selected' : '' ?>>Salary</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="pay_rate">Pay Rate</label>
            <input class="form-control" id="pay_rate" name="pay_rate" type="number" step="0.01" min="0" value="<?= e($payRate) ?>" />
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" id="active" name="active" type="checkbox" value="1" <?= $activeChecked ? 'checked' : '' ?> />
                <label class="form-check-label" for="active">Active</label>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label" for="note">Notes</label>
            <textarea class="form-control" id="note" name="note" rows="4"><?= e($note) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Employee' : 'Save Employee' ?></button>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/employees/' . ($employee['id'] ?? '')) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/employees') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
