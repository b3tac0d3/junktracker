<?php
    $token = (string) ($token ?? '');
    $inviteUser = is_array($inviteUser ?? null) ? $inviteUser : null;
    $firstName = $inviteUser['first_name'] ?? '';
    $email = $inviteUser['email'] ?? '';
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header"><h3 class="text-center font-weight-light my-4">Set Password</h3></div>
                <div class="card-body">
                    <?php if ($success = flash('success')): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($inviteUser === null): ?>
                        <div class="alert alert-warning mb-0">This password setup link is invalid or expired.</div>
                    <?php else: ?>
                        <p class="small text-muted mb-3">
                            <?= e($firstName !== '' ? ('Hi ' . $firstName . ',') : 'Welcome,') ?> set your password for <strong><?= e((string) $email) ?></strong>.
                            This link expires 72 hours after it is sent.
                        </p>

                        <form method="post" action="<?= url('/set-password') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="token" value="<?= e($token) ?>" />

                            <div class="form-floating mb-3">
                                <input class="form-control" id="inputPassword" name="password" type="password" placeholder="New password" minlength="8" required />
                                <label for="inputPassword">New Password</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="inputPasswordConfirm" name="password_confirm" type="password" placeholder="Confirm password" minlength="8" required />
                                <label for="inputPasswordConfirm">Confirm Password</label>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary" type="submit">Save Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small"><a href="<?= url('/login') ?>">Back to Login</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
