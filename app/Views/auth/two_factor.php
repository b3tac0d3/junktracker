<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header"><h3 class="text-center font-weight-light my-4">Two-Factor Verification</h3></div>
                <div class="card-body">
                    <?php if ($success = flash('success')): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <p class="small text-muted">Enter the 6-digit code sent to <strong><?= e((string) ($maskedEmail ?? 'your email')) ?></strong>.</p>

                    <form method="post" action="<?= url('/login/2fa') ?>">
                        <?= csrf_field() ?>
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputCode" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="123456" required />
                            <label for="inputCode">Verification Code</label>
                        </div>
                        <div class="d-grid mb-3">
                            <button class="btn btn-primary" type="submit">Verify & Login</button>
                        </div>
                    </form>

                    <form method="post" action="<?= url('/login/2fa/resend') ?>">
                        <?= csrf_field() ?>
                        <div class="d-flex align-items-center justify-content-between">
                            <small class="text-muted">Attempts left: <?= e((string) ($attemptsLeft ?? 0)) ?></small>
                            <button class="btn btn-link p-0" type="submit">Resend Code</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small"><a href="<?= url('/login') ?>">Back to Login</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
