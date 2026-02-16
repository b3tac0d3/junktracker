<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header"><h3 class="text-center font-weight-light my-4">Login</h3></div>
                <div class="card-body">
                    <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= url('/login') ?>">
                        <?= csrf_field() ?>
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputEmail" name="email" type="email" placeholder="name@example.com" required />
                            <label for="inputEmail">Email address</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputPassword" name="password" type="password" placeholder="Password" required />
                            <label for="inputPassword">Password</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="inputRememberPassword" name="remember" type="checkbox" value="1" />
                            <label class="form-check-label" for="inputRememberPassword">Remember Me</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                            <!-- <a class="small" href="<?= url('/forgot-password') ?>">Forgot Password?</a> -->
                            <button class="btn btn-primary" type="submit">Login</button>
                        </div>
                    </form>
                </div>
                <!-- <div class="card-footer text-center py-3">
                    <div class="small"><a href="<?= url('/register') ?>">Need an account? Sign up!</a></div>
                </div> -->
            </div>
        </div>
    </div>
</div>
