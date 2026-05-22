<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Login</h1>
        <form method="post" action="<?= e(url('/login')) ?>" autocomplete="on" data-allow-autocomplete>
            <?= csrf_field() ?>
            <label for="login-email">Email</label>
            <input id="login-email" type="email" name="email" autocomplete="username" inputmode="email" spellcheck="false" required>
            <label for="login-password">Password</label>
            <input id="login-password" type="password" name="password" autocomplete="current-password" required>
            <div class="form-check auth-remember">
                <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="remember_me">
                <label class="form-check-label" for="remember_me">Remember me</label>
            </div>
            <p class="small text-muted mb-0 mt-1">Stay signed in</p>
            <button class="btn btn-primary mt-3" type="submit">Sign In</button>
        </form>
    </div>
</div>
