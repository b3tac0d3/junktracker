<?php
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$actionUrl = (string) ($actionUrl ?? url('/settings/update'));
$mustChangePassword = (bool) ($mustChangePassword ?? false);

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
        <p class="muted"><?= e($mustChangePassword ? 'Set a new password before continuing.' : 'Update your name, email, and password.') ?></p>
    </div>
</div>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-user-gear me-2"></i>My Account</strong>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e($actionUrl) ?>" class="row g-3">
            <?= csrf_field() ?>

            <?php if ($mustChangePassword): ?>
                <div class="col-12">
                    <div class="alert alert-warning mb-0">
                        You are signed in with a temporary password. Set a new password to continue.
                    </div>
                </div>
            <?php endif; ?>

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
                <p class="small muted mb-0">
                    <?= e($mustChangePassword ? 'A new password is required for this account before you can continue.' : 'Leave password fields blank to keep your current password.') ?>
                </p>
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
                <?php if ($mustChangePassword): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/logout')) ?>">Logout</a>
                <?php else: ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php
$googleCalendarConfigured = (bool) ($googleCalendarConfigured ?? false);
$googleCalendarConnected = (bool) ($googleCalendarConnected ?? false);
$googleCalendarEmail = trim((string) ($googleCalendarEmail ?? ''));
$googleCalendarCalendarId = trim((string) ($googleCalendarCalendarId ?? 'primary')) ?: 'primary';
?>
<section class="card index-card mt-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-calendar-plus me-2"></i>Google Calendar</strong>
    </div>
    <div class="card-body">
        <p class="small muted mb-3">
            Connect Google to sync appointments to Calendar and optionally send Gmail updates when appointments are added, changed, or removed.
        </p>

        <?php if (!$googleCalendarConfigured): ?>
            <div class="alert alert-warning mb-0">
                Google Calendar sync is not configured on this server yet. Add your OAuth client secret in <code>config/google.local.php</code>.
            </div>
        <?php else: ?>
            <p class="small text-muted mb-3">
                OAuth redirect URI for Google Cloud:
                <code><?= e(absolute_url('/settings/google-calendar/callback')) ?></code>
            </p>
        <?php endif; ?>
        <?php
        $appointmentGmailNotifyEnabled = (bool) ($appointmentGmailNotifyEnabled ?? false);
        $appointmentGmailNotifyTo = trim((string) ($appointmentGmailNotifyTo ?? ''));
        ?>
        <?php if ($googleCalendarConfigured && $googleCalendarConnected): ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="fw-semibold">Connected</div>
                    <div class="small text-muted">
                        <?= e($googleCalendarEmail !== '' ? $googleCalendarEmail : 'Google account connected') ?>
                        · Calendar: <code><?= e($googleCalendarCalendarId) ?></code>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <form method="post" action="<?= e(url('/settings/google-calendar/backfill')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Sync upcoming (90 days)</button>
                    </form>
                    <form
                        method="post"
                        action="<?= e(url('/settings/google-calendar/backfill-past')) ?>"
                        class="d-flex flex-wrap gap-2 align-items-center"
                        onsubmit="return confirm('Send past JunkTracker events and scheduled jobs to Google Calendar? This may take a while for large histories.');"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="past_days" value="365">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Sync past (365 days)</button>
                    </form>
                    <form method="post" action="<?= e(url('/settings/google-calendar/disconnect')) ?>" onsubmit="return confirm('Disconnect Google? Calendar links and Gmail notifications will stop.');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Disconnect</button>
                    </form>
                </div>
            </div>

            <hr class="my-4">

            <h3 class="h6 mb-2"><i class="fas fa-envelope me-2"></i>Appointment Gmail notifications</h3>
            <p class="small text-muted mb-3">
                When enabled, JunkTracker sends email through your connected Gmail when <strong>appointments</strong> are created, updated, cancelled, or deleted.
                Leave recipients blank to notify your connected Google address, or enter comma-separated emails.
            </p>
            <form method="post" action="<?= e(url('/settings/google-appointment-gmail')) ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-12">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="appointment_gmail_notify_enabled"
                            id="appointment-gmail-notify-enabled"
                            value="1"
                            <?= $appointmentGmailNotifyEnabled ? 'checked' : '' ?>
                        />
                        <label class="form-check-label" for="appointment-gmail-notify-enabled">Send Gmail updates for appointments</label>
                    </div>
                </div>
                <div class="col-12 col-lg-8">
                    <label class="form-label fw-semibold" for="appointment-gmail-notify-to">Notify these addresses</label>
                    <input
                        id="appointment-gmail-notify-to"
                        type="text"
                        name="appointment_gmail_notify_to"
                        class="form-control"
                        value="<?= e($appointmentGmailNotifyTo) ?>"
                        placeholder="office@example.com, team@example.com (optional)"
                        maxlength="500"
                    />
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">Save Gmail notification settings</button>
                </div>
            </form>
            <p class="small text-muted mt-2 mb-0">
                If you connected Google before this feature, use <strong>Disconnect</strong> then connect again so Gmail send permission is granted.
            </p>
        <?php elseif ($googleCalendarConfigured): ?>
            <a class="btn btn-primary" href="<?= e(url('/settings/google-calendar/connect')) ?>">Connect Google Calendar</a>
        <?php endif; ?>
    </div>
</section>
