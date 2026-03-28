<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Login</h1>
        <form method="post" action="<?= e(url('/login')) ?>">
            <?= csrf_field() ?>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <div class="form-check auth-remember">
                <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="remember_me">
                <label class="form-check-label" for="remember_me">Remember me</label>
            </div>
            <p class="small text-muted mb-0 mt-1">Stay signed in on this device until you log out or clear site data. Unchecked ends the session when you close the browser.</p>
            <button class="btn btn-primary mt-3" type="submit">Sign In</button>
        </form>
    </div>
</div>
