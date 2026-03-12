<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? url('/settings/update'));

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Settings</h1>
        <p class="muted">Update your name, email, and password.</p>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-gear me-2"></i>My Account</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="settings-first-name">First Name</label>
                <input id="settings-first-name" name="first_name" class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['first_name'] ?? '')) ?>" maxlength="90" />
                <?php if ($hasError('first_name')): ?><div class="invalid-feedback d-block"><?= e($fieldError('first_name')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="settings-last-name">Last Name</label>
                <input id="settings-last-name" name="last_name" class="form-control" value="<?= e((string) ($form['last_name'] ?? '')) ?>" maxlength="90" />
            </div>

            <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold" for="settings-email">Email</label>
                <input id="settings-email" type="email" name="email" class="form-control <?= $hasError('email') ? 'is-invalid' : '' ?>" value="<?= e((string) ($form['email'] ?? '')) ?>" maxlength="190" />
                <?php if ($hasError('email')): ?><div class="invalid-feedback d-block"><?= e($fieldError('email')) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <hr class="my-1" />
                <p class="small muted mb-0">Leave password fields blank to keep your current password.</p>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="settings-password">New Password</label>
                <input id="settings-password" type="password" name="password" class="form-control <?= $hasError('password') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                <?php if ($hasError('password')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold" for="settings-password-confirm">Confirm Password</label>
                <input id="settings-password-confirm" type="password" name="password_confirm" class="form-control <?= $hasError('password_confirm') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                <?php if ($hasError('password_confirm')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password_confirm')) ?></div><?php endif; ?>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save Settings</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

