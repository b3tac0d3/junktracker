<?php
    $settings = is_array($settings ?? null) ? $settings : [];
    $isReady = !empty($isReady);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2 mobile-two-col-buttons">
        <div>
            <h1 class="mb-1">Admin Settings</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Back to Admin</a>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$isReady): ?>
        <div class="alert alert-warning">`app_settings` table is not available yet. Run migrations to enable persisted system settings.</div>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/settings') ?>" class="card border-0 shadow-sm mb-3">
        <?= csrf_field() ?>
        <div class="card-header">
            <i class="fas fa-sliders me-1"></i>System Configuration
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label d-block">2FA Required</label>
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="security_two_factor_enabled"
                            name="security_two_factor_enabled"
                            value="1"
                            <?= ((string) old('security.two_factor_enabled', (string) ($settings['security.two_factor_enabled'] ?? '1')) === '1') ? 'checked' : '' ?>
                        />
                        <label class="form-check-label" for="security_two_factor_enabled">Enable login verification by email</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="display_timezone">Display Timezone</label>
                    <input class="form-control" id="display_timezone" name="display_timezone" type="text" value="<?= e((string) old('display.timezone', $settings['display.timezone'] ?? 'America/New_York')) ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="display_date_format">Date Format</label>
                    <input class="form-control" id="display_date_format" name="display_date_format" type="text" value="<?= e((string) old('display.date_format', $settings['display.date_format'] ?? 'm/d/Y')) ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="display_datetime_format">Date/Time Format</label>
                    <input class="form-control" id="display_datetime_format" name="display_datetime_format" type="text" value="<?= e((string) old('display.datetime_format', $settings['display.datetime_format'] ?? 'm/d/Y g:i A')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mail_from_address">Mail From Address</label>
                    <input class="form-control" id="mail_from_address" name="mail_from_address" type="email" value="<?= e((string) old('mail.from_address', $settings['mail.from_address'] ?? '')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mail_from_name">Mail From Name</label>
                    <input class="form-control" id="mail_from_name" name="mail_from_name" type="text" value="<?= e((string) old('mail.from_name', $settings['mail.from_name'] ?? '')) ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mail_reply_to">Reply-To Address</label>
                    <input class="form-control" id="mail_reply_to" name="mail_reply_to" type="email" value="<?= e((string) old('mail.reply_to', $settings['mail.reply_to'] ?? '')) ?>" />
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="mail_subject_prefix">Mail Subject Prefix</label>
                    <input class="form-control" id="mail_subject_prefix" name="mail_subject_prefix" type="text" value="<?= e((string) old('mail.subject_prefix', $settings['mail.subject_prefix'] ?? '')) ?>" />
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="submit" <?= !$isReady ? 'disabled' : '' ?>>Save System Settings</button>
        </div>
    </form>

    <form method="post" action="<?= url('/admin/settings/test-email') ?>" class="card border-0 shadow-sm">
        <?= csrf_field() ?>
        <div class="card-header">
            <i class="fas fa-envelope-circle-check me-1"></i>Mail Test
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="mail_test_recipient">Test Recipient</label>
                    <input class="form-control" id="mail_test_recipient" name="mail_test_recipient" type="email" value="<?= e((string) old('mail.test_recipient', $settings['mail.test_recipient'] ?? '')) ?>" />
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-primary w-100" type="submit" <?= !$isReady ? 'disabled' : '' ?>>Send Test Email</button>
                </div>
            </div>
        </div>
    </form>
</div>

