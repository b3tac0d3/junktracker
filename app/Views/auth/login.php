<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Login</h1>
        <p class="muted">JunkTracker v3 Phase A</p>
        <form method="post" action="<?= e(url('/login')) ?>">
            <?= csrf_field() ?>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button class="btn btn-primary" type="submit">Sign In</button>
        </form>
    </div>
</div>
