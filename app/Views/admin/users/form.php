<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$targetUser = is_array($targetUser ?? null) ? $targetUser : [];
$actionUrl = (string) ($actionUrl ?? '#');

$targetName = trim(((string) ($targetUser['first_name'] ?? '')) . ' ' . ((string) ($targetUser['last_name'] ?? '')));
if ($targetName === '') {
    $targetName = trim((string) ($targetUser['email'] ?? ''));
}
if ($targetName === '') {
    $targetName = 'User #' . (string) ((int) ($targetUser['id'] ?? 0));
}

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Edit User</h1>
        <p class="muted"><?= e($targetName) ?></p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/users')) ?>">Back to Users</a>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-pen me-2"></i>User Profile</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

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

            <div class="col-12">
                <hr class="my-1" />
                <p class="small muted mb-0">Leave password fields blank to keep the current password.</p>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="user-password">New Password</label>
                <input id="user-password" type="password" name="password" class="form-control <?= $hasError('password') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                <?php if ($hasError('password')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="user-password-confirm">Confirm Password</label>
                <input id="user-password-confirm" type="password" name="password_confirm" class="form-control <?= $hasError('password_confirm') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                <?php if ($hasError('password_confirm')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password_confirm')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save User</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin/users')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

