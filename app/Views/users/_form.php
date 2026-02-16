<?php
    $user = $user ?? [];
    $isEdit = !empty($user);
?>
<form method="post" action="<?= url(isset($user['id']) ? '/users/' . $user['id'] : '/users') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="first_name">First Name</label>
            <input class="form-control" id="first_name" name="first_name" type="text" value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_name">Last Name</label>
            <input class="form-control" id="last_name" name="last_name" type="text" value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" />
        </div>
        <div class="col-md-3">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role">
                <?php $roleValue = (int) old('role', isset($user['role']) ? (int) $user['role'] : 1); ?>
                <option value="1" <?= $roleValue === 1 ? 'selected' : '' ?>>User</option>
                <option value="2" <?= $roleValue === 2 ? 'selected' : '' ?>>Manager</option>
                <option value="3" <?= $roleValue === 3 ? 'selected' : '' ?>>Admin</option>
                <option value="99" <?= $roleValue === 99 ? 'selected' : '' ?>>Dev</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="is_active">Status</label>
            <select class="form-select" id="is_active" name="is_active">
                <?php $activeValue = (int) old('is_active', isset($user['is_active']) ? (int) $user['is_active'] : 1); ?>
                <option value="1" <?= $activeValue === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $activeValue === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password">Password <?= $isEdit ? '(leave blank to keep)' : '' ?></label>
            <input class="form-control" id="password" name="password" type="password" />
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password_confirm">Confirm Password</label>
            <input class="form-control" id="password_confirm" name="password_confirm" type="password" />
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save User</button>
        <?php if (!empty($user['id'])): ?>
            <a class="btn btn-outline-secondary" href="<?= url('/users/' . $user['id']) ?>">Cancel</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= url('/users') ?>">Cancel</a>
        <?php endif; ?>
    </div>
</form>
