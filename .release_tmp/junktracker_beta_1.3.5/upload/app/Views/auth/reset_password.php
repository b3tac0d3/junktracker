<?php
$errors = is_array($errors ?? null) ? $errors : [];
$form = is_array($form ?? null) ? $form : [];
$token = trim((string) ($token ?? ''));
$targetUser = is_array($targetUser ?? null) ? $targetUser : null;
$linkExpired = (bool) ($linkExpired ?? false);

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};

$targetName = $targetUser !== null ? trim((string) ($targetUser['first_name'] ?? '') . ' ' . (string) ($targetUser['last_name'] ?? '')) : '';
if ($targetName === '' && $targetUser !== null) {
    $targetName = trim((string) ($targetUser['email'] ?? ''));
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="card index-card">
            <div class="card-header index-card-header">
                <strong><i class="fas fa-key me-2"></i>Reset Password</strong>
            </div>
            <div class="card-body">
                <?php if ($linkExpired): ?>
                    <div class="alert alert-danger mb-3">
                        This password reset link is invalid or expired.
                    </div>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/login')) ?>">Back to Login</a>
                <?php else: ?>
                    <?php if ($targetName !== ''): ?>
                        <p class="muted">Reset password for <?= e($targetName) ?>.</p>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/reset-password/' . rawurlencode($token))) ?>" class="row g-3">
                        <?= csrf_field() ?>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="reset-password">New Password</label>
                            <input id="reset-password" type="password" name="password" class="form-control <?= $hasError('password') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                            <?php if ($hasError('password')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password')) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="reset-password-confirm">Confirm Password</label>
                            <input id="reset-password-confirm" type="password" name="password_confirm" class="form-control <?= $hasError('password_confirm') ? 'is-invalid' : '' ?>" autocomplete="new-password" />
                            <?php if ($hasError('password_confirm')): ?><div class="invalid-feedback d-block"><?= e($fieldError('password_confirm')) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Set Password</button>
                            <a class="btn btn-outline-secondary" href="<?= e(url('/login')) ?>">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
