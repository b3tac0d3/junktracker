<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$targetUser = is_array($targetUser ?? null) ? $targetUser : [];
$actionUrl = (string) ($actionUrl ?? '#');
$mode = (string) ($mode ?? 'edit');
$isCreate = $mode === 'create';
$isSiteAdminGlobal = (bool) ($isSiteAdminGlobal ?? false);
$workspaceRoleOptions = is_array($workspaceRoleOptions ?? null) ? $workspaceRoleOptions : [];
$canToggleActive = (bool) ($canToggleActive ?? false);

$targetName = trim(((string) ($targetUser['first_name'] ?? '')) . ' ' . ((string) ($targetUser['last_name'] ?? '')));
if ($targetName === '') {
    $targetName = trim((string) ($targetUser['email'] ?? ''));
}
if ($targetName === '' && !$isCreate) {
    $targetName = 'User #' . (string) ((int) ($targetUser['id'] ?? 0));
}
if ($targetName === '' && $isCreate) {
    $targetName = $isSiteAdminGlobal ? 'New Site Admin' : 'New User';
}

$pageHeading = $isCreate
    ? ($isSiteAdminGlobal ? 'Add Site Admin' : 'Add User')
    : 'Edit User';

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$effectiveActive = (int) ($targetUser['effective_active'] ?? $targetUser['is_active'] ?? 1) === 1;
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($pageHeading) ?></h1>
        <p class="muted"><?= e($targetName) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/users')) ?>">Back to Users</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-pen me-2"></i><?= e($isCreate ? 'Create User' : 'User Profile') ?></strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <?php if (!$isCreate): ?>
                <div class="col-12">
                    <span class="small text-muted me-2">Current status:</span>
                    <span class="badge <?= $effectiveActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= e($effectiveActive ? 'Active' : 'Inactive') ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="user-first-name">First Name</label>
                <input id="user-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="user-last-name">Last Name</label>
                <input id="user-last-name" name="last_name" class="form-control" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="user-email">Email</label>
                <input id="user-email" type="email" name="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <?php if ($isSiteAdminGlobal): ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold">Role</label>
                    <input class="form-control" value="Site Admin" disabled />
                </div>
            <?php else: ?>
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold" for="user-workspace-role">Workspace Role</label>
                    <select id="user-workspace-role" name="workspace_role" class="form-select <?= $hasError('workspace_role') ? 'is-invalid' : '' ?>">
                        <?php foreach ($workspaceRoleOptions as $value => $label): ?>
                            <option value="<?= e((string) $value) ?>" <?= (string) ($form['workspace_role'] ?? 'general_user') === (string) $value ? 'selected' : '' ?>>
                                <?= e((string) $label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasError('workspace_role')): ?><div class="invalid-feedback d-block"><?= e($fieldError('workspace_role')) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="col-12">
                <hr class="my-1" />
                <p class="small muted mb-0">
                    <?= e($isCreate ? 'Password is required for new users.' : 'Leave password fields blank to keep the current password.') ?>
                </p>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="user-password"><?= e($isCreate ? 'Password' : 'New Password') ?></label>
                <input id="user-password" type="password" name="password" class="form-control <?= $hasError('password') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                <?php if ($hasError('password')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="user-password-confirm">Confirm Password</label>
                <input id="user-password-confirm" type="password" name="password_confirm" class="form-control <?= $hasError('password_confirm') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                <?php if ($hasError('password_confirm')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password_confirm')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><?= e($isCreate ? 'Create User' : 'Save User') ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin/users')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<?php if (!$isCreate): ?>
    <section class="card index-card">
        <div class="card-header index-card-header">
            <strong><i class="fas fa-user-slash me-2"></i>Activation</strong>
        </div>
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <?php if ($canToggleActive): ?>
                <form method="post" action="<?= e(url('/admin/users/' . (string) ((int) ($targetUser['id'] ?? 0)) . '/toggle-active')) ?>" onsubmit="return confirm('Are you sure?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="set_active" value="<?= $effectiveActive ? '0' : '1' ?>">
                    <button class="btn <?= $effectiveActive ? 'btn-outline-danger' : 'btn-success' ?>" type="submit">
                        <?= e($effectiveActive ? 'Deactivate User' : 'Reactivate User') ?>
                    </button>
                </form>
            <?php else: ?>
                <div class="small text-muted">You cannot deactivate your own account.</div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
