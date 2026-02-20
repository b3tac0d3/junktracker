<?php
    $displayName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $globalTwoFactorEnabled = !array_key_exists('globalTwoFactorEnabled', get_defined_vars()) || !empty($globalTwoFactorEnabled);
    $userTwoFactorEnabled = !array_key_exists('two_factor_enabled', $user) || (int) ($user['two_factor_enabled'] ?? 1) === 1;
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Settings</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <?php if (can_access('admin_settings', 'view')): ?>
                <a class="btn btn-outline-primary" href="<?= url('/admin/settings') ?>">Admin Settings</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-gear me-1"></i>
            My Account
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Current User</div>
                    <div class="fw-semibold"><?= e($displayName !== '' ? $displayName : ($user['email'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Role</div>
                    <div class="fw-semibold"><?= e(role_label(isset($user['role']) ? (int) $user['role'] : null)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">
                        <?php if (!empty($user['is_active'])): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <hr class="my-4" />

            <form method="post" action="<?= url('/settings') ?>">
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
                    <div class="col-md-6">
                        <label class="form-label d-block">Login Security</label>
                        <?php $twoFactorInputValue = (string) old('two_factor_enabled', $userTwoFactorEnabled ? '1' : '0'); ?>
                        <?php $twoFactorHiddenValue = $globalTwoFactorEnabled ? '0' : ($twoFactorInputValue === '1' ? '1' : '0'); ?>
                        <input type="hidden" name="two_factor_enabled" value="<?= e($twoFactorHiddenValue) ?>" />
                        <div class="form-check form-switch mt-2">
                            <input
                                class="form-check-input"
                                id="two_factor_enabled"
                                name="two_factor_enabled"
                                type="checkbox"
                                value="1"
                                <?= $twoFactorInputValue === '1' ? 'checked' : '' ?>
                                <?= $globalTwoFactorEnabled ? '' : 'disabled' ?>
                            />
                            <label class="form-check-label" for="two_factor_enabled">Require email 2FA on my login</label>
                        </div>
                        <?php if ($globalTwoFactorEnabled): ?>
                            <div class="form-text">You can disable or enable 2FA for your own account here.</div>
                        <?php else: ?>
                            <div class="form-text text-warning">Global 2FA is currently disabled by admin settings.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">New Password (optional)</label>
                        <input class="form-control" id="password" name="password" type="password" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirm">Confirm Password</label>
                        <input class="form-control" id="password_confirm" name="password_confirm" type="password" />
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-save me-1"></i>
                        Save Settings
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
