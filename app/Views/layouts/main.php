<?php
$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'JunkTracker';
$publicPage = (bool) ($publicPage ?? false);
$user = auth_user();
$appVersion = (string) config('app.version', '3.2 (beta)');
$workspaceRole = workspace_role();
$businessId = current_business_id();
$isGlobalSiteAdminContext = is_site_admin() && $businessId <= 0;
$canAccessBusinessAdmin = !$isGlobalSiteAdminContext && (is_site_admin() || $workspaceRole === 'admin');
$canManageUsers = is_site_admin() || (!$isGlobalSiteAdminContext && $workspaceRole === 'admin');
$globalSearchQuery = trim((string) ($_GET['global_q'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?= e($pageTitle) ?> - JunkTracker</title>
    <link href="<?= e(asset('css/styles.css')) ?>" rel="stylesheet" />
    <link href="<?= e(asset('css/jt-theme.css')) ?>" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body class="sb-nav-fixed">
<?php if ($publicPage): ?>
    <main class="container py-5">
        <?php if ($success = flash('success')): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error = flash('error')): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php
        $file = isset($viewFile) ? base_path((string) $viewFile) : null;
        if ($file !== null && is_file($file)) {
            require $file;
        }
        ?>
    </main>
<?php else: ?>
    <nav class="sb-topnav navbar navbar-expand navbar-dark">
        <a class="navbar-brand ps-3" href="<?= e(url('/')) ?>">JunkTracker</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" type="button"><i class="fas fa-bars"></i></button>

        <ul class="navbar-nav ms-auto me-3 me-lg-4 align-items-center">
            <li class="nav-item d-none d-md-block me-2">
                <form class="form-inline" method="get" action="<?= e(url('/search')) ?>">
                    <div class="input-group">
                        <input class="form-control" type="text" name="global_q" value="<?= e($globalSearchQuery) ?>" placeholder="Search everything..." aria-label="Search" autocomplete="off" />
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </li>
            <?php if (is_site_admin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('/site-admin/businesses')) ?>" title="Site Admin"><i class="fas fa-building-shield fa-fw"></i></a>
                </li>
            <?php endif; ?>
            <?php if (!$isGlobalSiteAdminContext): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle nav-quick-add-link" id="quickAddDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Add">
                        <i class="fas fa-circle-plus fa-fw"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="quickAddDropdown">
                        <li><a class="dropdown-item" href="<?= e(url('/jobs/create')) ?>"><i class="fas fa-briefcase me-2"></i>Add Job</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/clients/create')) ?>"><i class="fas fa-users me-2"></i>Add Client</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/tasks')) ?>"><i class="fas fa-list-check me-2"></i>Add Task</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/sales/create')) ?>"><i class="fas fa-sack-dollar me-2"></i>Add Sale</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/purchases/create')) ?>"><i class="fas fa-cart-arrow-down me-2"></i>Add Purchase</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/expenses/create')) ?>"><i class="fas fa-receipt me-2"></i>Add Expense</a></li>
                    </ul>
                </li>
            <?php endif; ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><span class="dropdown-item-text small text-muted"><?= e((string) ($user['email'] ?? '')) ?></span></li>
                    <li><span class="dropdown-item-text small text-muted">Role: <?= e($workspaceRole) ?></span></li>
                    <?php if ($businessId > 0): ?><li><span class="dropdown-item-text small text-muted">Business #<?= e((string) $businessId) ?></span></li><?php endif; ?>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item" href="<?= e(url('/settings')) ?>">Settings</a></li>
                    <?php if ($canManageUsers && !$isGlobalSiteAdminContext): ?>
                        <li><a class="dropdown-item" href="<?= e(url('/admin/users')) ?>">Manage Users</a></li>
                    <?php endif; ?>
                    <?php if (is_site_admin()): ?>
                        <li><a class="dropdown-item" href="<?= e(url('/site-admin/businesses')) ?>">Switch Workspace</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider" /></li>
                    <li>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit">Logout</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading">Overview</div>
                        <a class="nav-link" href="<?= e($isGlobalSiteAdminContext ? url('/site-admin/businesses') : url('/')) ?>">
                            <div class="sb-nav-link-icon"><i class="fas fa-gauge-high"></i></div>
                            <?= e($isGlobalSiteAdminContext ? 'Site Admin Dashboard' : 'Dashboard') ?>
                        </a>
                        <?php if ($isGlobalSiteAdminContext && $canManageUsers): ?>
                            <a class="nav-link" href="<?= e(url('/admin/users')) ?>">
                                <div class="sb-nav-link-icon"><i class="fas fa-users-gear"></i></div>
                                Manage Users
                            </a>
                        <?php endif; ?>

                        <?php if (!$isGlobalSiteAdminContext): ?>
                            <div class="sb-sidenav-menu-heading">Core</div>
                            <a class="nav-link" href="<?= e(url('/clients')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>Clients</a>
                            <a class="nav-link" href="<?= e(url('/jobs')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-briefcase"></i></div>Jobs</a>
                            <a class="nav-link" href="<?= e(url('/sales')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-sack-dollar"></i></div>Sales</a>
                            <a class="nav-link" href="<?= e(url('/purchases')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-cart-arrow-down"></i></div>Purchasing</a>
                            <a class="nav-link" href="<?= e(url('/tasks')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-list-check"></i></div>Tasks</a>
                            <!-- <a class="nav-link" href="<?= e(url('/time-tracking')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-clock"></i></div>Time Tracking</a>
                            <a class="nav-link jt-nav-sublink" href="<?= e(url('/time-tracking/punch-board')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-user-clock"></i></div>Punch Board</a> -->
                            <a class="nav-link" href="<?= e(url('/billing')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-file-invoice-dollar"></i></div>Billing</a>
                            <a class="nav-link" href="<?= e(url('/expenses')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-receipt"></i></div>Expenses</a>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseTimeTracking" aria-expanded="false" aria-controls="collapseCustomers">
                                <div class="sb-nav-link-icon"><i class="fas fa-stopwatch"></i></div>
                                Time Tracking
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseTimeTracking" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="<?= e(url('/time-tracking')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-clock-rotate-left"></i></div>Time Log</a>
                                    <a class="nav-link" href="<?= e(url('/time-tracking/punch-board')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-user-clock"></i></div>Punch Board</a>
                                </nav>
                            </div>
                        <?php endif; ?>

                        <?php if ($canAccessBusinessAdmin): ?>
                            <div class="sb-sidenav-menu-heading">Admin</div>
                            <a class="nav-link" href="<?= e(url('/admin')) ?>"><div class="sb-nav-link-icon"><i class="fas fa-gear"></i></div>Business Admin</a>
                        <?php endif; ?>

                    </div>
                </div>
                <div class="sb-sidenav-footer">
                    <div class="small">Logged in as:</div>
                    <?= e((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                    <div class="small text-muted mt-1">v<?= e($appVersion) ?></div>
                </div>
            </nav>
        </div>

        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 py-3">
                    <?php if ($success = flash('success')): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                    <?php if ($error = flash('error')): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <?php
                    $file = isset($viewFile) ? base_path((string) $viewFile) : null;
                    if ($file !== null && is_file($file)) {
                        require $file;
                    }
                    ?>
                </div>
            </main>
            <footer class="py-3 mt-auto border-top">
                <div class="container-fluid px-4">
                    <div class="small text-muted">JunkTracker · v<?= e($appVersion) ?></div>
                </div>
            </footer>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="<?= e(asset('js/scripts.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
