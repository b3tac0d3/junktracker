<?php
    $userName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    if ($userName === '') {
        $userName = (string) ($user['email'] ?? ('User #' . ($user['id'] ?? '')));
    }
    $records = is_array($records ?? null) ? $records : [];
    $query = (string) ($query ?? '');
    $isReady = !empty($isReady);
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">User Login Records</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/users') ?>">Users</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/users/' . ($user['id'] ?? '')) ?>"><?= e($userName) ?></a></li>
                <li class="breadcrumb-item active">Login Records</li>
            </ol>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/users/' . ($user['id'] ?? '')) ?>">Back to User</a>
    </div>

    <?php if (!$isReady): ?>
        <div class="alert alert-warning mb-3">
            Login records are not enabled yet. Run the latest SQL migration to create the <code>user_login_records</code> table.
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <i class="fas fa-search me-1"></i>
            Search Login Records
        </div>
        <div class="card-body">
            <form method="get" action="<?= url('/users/' . ($user['id'] ?? '') . '/logins') ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-10">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" name="q" placeholder="Search IP, browser, OS, method..." value="<?= e($query) ?>" />
                            <?php if ($query !== ''): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('/users/' . ($user['id'] ?? '') . '/logins') ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-shield-alt me-1"></i>
            <?= e($userName) ?> Login History
        </div>
        <div class="card-body">
            <table id="userActivityTable">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Method</th>
                        <th>IP</th>
                        <th>Browser / System</th>
                        <th>Device</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <?php
                            $browser = trim((string) ($record['browser_name'] ?? ''));
                            $browserVersion = trim((string) ($record['browser_version'] ?? ''));
                            if ($browser !== '' && $browserVersion !== '') {
                                $browser .= ' ' . $browserVersion;
                            }
                            $osName = trim((string) ($record['os_name'] ?? ''));
                            $browserSystem = $browser;
                            if ($osName !== '') {
                                $browserSystem = $browserSystem !== '' ? $browserSystem . ' on ' . $osName : $osName;
                            }
                        ?>
                        <tr>
                            <td><?= e(format_datetime($record['logged_in_at'] ?? null)) ?></td>
                            <td><?= e(login_method_label((string) ($record['login_method'] ?? ''))) ?></td>
                            <td><?= e((string) ($record['ip_address'] ?? '—')) ?></td>
                            <td><?= e($browserSystem !== '' ? $browserSystem : '—') ?></td>
                            <td><?= e(ucfirst((string) ($record['device_type'] ?? 'unknown'))) ?></td>
                            <td><?= e((string) ($record['user_agent'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
